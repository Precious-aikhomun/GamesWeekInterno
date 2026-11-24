<?php
// api/inscripcion.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido. Usa POST.']);
    exit;
}

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode([
        'success'                => false,
        'requiere_autenticacion' => true,
        'error'                  => 'Debes iniciar sesión o registrarte para inscribirte en una actividad.'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$actividadId  = filter_var($data['actividad_id'] ?? null, FILTER_VALIDATE_INT);
$nickname     = trim($data['nickname'] ?? '');
$nombreEquipo = trim($data['nombre_equipo'] ?? '');
$usuarioId    = (int) $_SESSION['usuario_id'];

if (!$actividadId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'El campo actividad_id es obligatorio.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Verificar que el usuario de la sesión existe en BD
    $stmtUser = $pdo->prepare('SELECT id, nombre, apellidos, email, fecha_nacimiento, nickname FROM usuarios WHERE id = ?');
    $stmtUser->execute([$usuarioId]);
    $usuario = $stmtUser->fetch();

    if (!$usuario) {
        $pdo->rollBack();
        session_unset();
        session_destroy();
        http_response_code(401);
        echo json_encode([
            'success'                => false,
            'requiere_autenticacion' => true,
            'error'                  => 'La sesión ha caducado o el usuario no existe. Inicia sesión de nuevo.'
        ]);
        exit;
    }

    // 2. Bloquear fila de la actividad para evitar condiciones de carrera en el aforo
    $sqlActividad = "
        SELECT a.*, td.formato, v.nombre AS videojuego_nombre, v.pegi
        FROM actividades a
        LEFT JOIN torneos_detalle td ON a.id = td.actividad_id
        LEFT JOIN videojuegos v ON td.videojuego_id = v.id
        WHERE a.id = ?
        FOR UPDATE
    ";
    $stmtAct = $pdo->prepare($sqlActividad);
    $stmtAct->execute([$actividadId]);
    $actividad = $stmtAct->fetch();

    if (!$actividad) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'La actividad indicada no existe.']);
        exit;
    }

    if ((int) $actividad['requiere_inscripcion'] === 0) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Esta actividad es de acceso libre y no requiere inscripción previa.']);
        exit;
    }

    if (new DateTime($actividad['fecha_fin']) < new DateTime()) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Esta actividad ya ha finalizado.']);
        exit;
    }

    // 3. Validaciones específicas de torneos (PEGI + Nickname)
    $nicknameActual = $usuario['nickname'];

    if ($actividad['tipo'] === 'torneo') {
        if (!empty($actividad['pegi'])) {
            $nacimiento  = new DateTime($usuario['fecha_nacimiento']);
            $fechaTorneo = new DateTime($actividad['fecha_inicio']);
            $edad        = $nacimiento->diff($fechaTorneo)->y;

            if ($edad < (int) $actividad['pegi']) {
                $pdo->rollBack();
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error'   => "No cumples la edad mínima requerida (PEGI {$actividad['pegi']}) para participar en {$actividad['videojuego_nombre']}."
                ]);
                exit;
            }
        }

        if ($nickname !== '') {
            if (mb_strlen($nickname) > 50) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El nickname no puede superar los 50 caracteres.']);
                exit;
            }

            $stmtNick = $pdo->prepare('UPDATE usuarios SET nickname = ? WHERE id = ?');
            $stmtNick->execute([$nickname, $usuarioId]);
            $nicknameActual = $nickname;
        }

        if (empty($nicknameActual)) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode([
                'success'           => false,
                'requiere_nickname' => true,
                'error'             => 'Para apuntarte a un torneo necesitas indicar tu Nickname / Gamer Tag.'
            ]);
            exit;
        }
    }

    // 4. Comprobar inscripción previa (activa o cancelada)
    $stmtPrev = $pdo->prepare(
        'SELECT id, estado FROM inscripciones WHERE usuario_id = ? AND actividad_id = ? FOR UPDATE'
    );
    $stmtPrev->execute([$usuarioId, $actividadId]);
    $inscripcionPrevia = $stmtPrev->fetch();

    if ($inscripcionPrevia && $inscripcionPrevia['estado'] !== 'cancelada') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Ya tienes una inscripción activa en esta actividad.']);
        exit;
    }

    // 5. Calcular disponibilidad de aforo
    $estadoInscripcion = 'confirmada';

    if ($actividad['aforo_maximo'] !== null) {
        $stmtAforo = $pdo->prepare(
            "SELECT COUNT(*) FROM inscripciones WHERE actividad_id = ? AND estado = 'confirmada'"
        );
        $stmtAforo->execute([$actividadId]);
        $plazasOcupadas = (int) $stmtAforo->fetchColumn();

        if ($plazasOcupadas >= (int) $actividad['aforo_maximo']) {
            $estadoInscripcion = 'lista_espera';
        }
    }

    $codigoAcceso = sprintf(
        'GW-%02d-%s',
        $actividadId,
        strtoupper(substr(bin2hex(random_bytes(3)), 0, 4))
    );

    $equipoFinal = ($actividad['tipo'] === 'torneo' && $nombreEquipo !== '') ? $nombreEquipo : null;

    // 6. Reactivar fila cancelada o insertar nueva inscripción
    if ($inscripcionPrevia) {
        $stmtReactivar = $pdo->prepare(
            'UPDATE inscripciones
             SET nombre_equipo = ?, fecha_inscripcion = CURRENT_TIMESTAMP, estado = ?, codigo_acceso = ?
             WHERE id = ?'
        );
        $stmtReactivar->execute([
            $equipoFinal,
            $estadoInscripcion,
            $codigoAcceso,
            $inscripcionPrevia['id']
        ]);
        $inscripcionId = (int) $inscripcionPrevia['id'];
    } else {
        $stmtInsert = $pdo->prepare(
            'INSERT INTO inscripciones (usuario_id, actividad_id, nombre_equipo, estado, codigo_acceso)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmtInsert->execute([
            $usuarioId,
            $actividadId,
            $equipoFinal,
            $estadoInscripcion,
            $codigoAcceso
        ]);
        $inscripcionId = (int) $pdo->lastInsertId();
    }

    $pdo->commit();

    http_response_code(201);
    echo json_encode([
        'success'        => true,
        'mensaje'        => $estadoInscripcion === 'confirmada'
            ? 'Inscripción confirmada correctamente.'
            : 'El aforo está completo. Has quedado apuntado en lista de espera.',
        'inscripcion_id' => $inscripcionId,
        'codigo_acceso'  => $codigoAcceso,
        'estado'         => $estadoInscripcion,
        'actividad'      => $actividad['titulo'],
        'nickname'       => $nicknameActual
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Error interno al procesar la inscripción.'
    ]);
}

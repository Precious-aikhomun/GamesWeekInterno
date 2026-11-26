<?php
// api/inscripcion.php
require_once __DIR__ . '/../includes/db.php';

requireHttpMethod('POST');

$usuarioId = requireAuthenticatedUserId(
    'Debes iniciar sesión o registrarte para inscribirte en una actividad.'
);

$data         = getRequestData();
$actividadId  = filter_var($data['actividad_id'] ?? null, FILTER_VALIDATE_INT);
$nickname     = trim((string) ($data['nickname'] ?? ''));
$nombreEquipo = trim((string) ($data['nombre_equipo'] ?? ''));

if (!$actividadId || $actividadId <= 0) {
    jsonError('El campo actividad_id es obligatorio.', 400);
}
$actividadId = (int) $actividadId;

if ($nickname !== '' && mb_strlen($nickname) > 50) {
    jsonError('El nickname no puede superar los 50 caracteres.', 400);
}

if ($nombreEquipo !== '' && mb_strlen($nombreEquipo) > 100) {
    jsonError('El nombre del equipo no puede superar los 100 caracteres.', 400);
}

/**
 * Genera un código de acceso único por actividad (p. ej. GW-03-A9F1C2).
 */
function generarCodigoAcceso(int $actividadId): string
{
    return sprintf('GW-%02d-%s', $actividadId, strtoupper(bin2hex(random_bytes(3))));
}

try {
    $pdo->beginTransaction();

    // 1. Verificar que el usuario de la sesión existe en BD
    $stmtUser = $pdo->prepare('SELECT id, fecha_nacimiento, nickname FROM usuarios WHERE id = ?');
    $stmtUser->execute([$usuarioId]);
    $usuario = $stmtUser->fetch();

    if (!$usuario) {
        $pdo->rollBack();
        clearUserSession();
        jsonError(
            'La sesión ha caducado o el usuario no existe. Inicia sesión de nuevo.',
            401,
            ['requiere_autenticacion' => true]
        );
    }

    // 2. Bloquear fila de la actividad para serializar control de aforo
    $sqlActividad = "
        SELECT
            a.id,
            a.titulo,
            a.tipo,
            a.fecha_inicio,
            a.fecha_fin,
            a.aforo_maximo,
            a.requiere_inscripcion,
            td.formato,
            v.nombre AS videojuego_nombre,
            v.pegi
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
        jsonError('La actividad indicada no existe.', 404);
    }

    if ((int) $actividad['requiere_inscripcion'] === 0) {
        $pdo->rollBack();
        jsonError('Esta actividad es de acceso libre y no requiere inscripción previa.', 400);
    }

    if (new DateTimeImmutable($actividad['fecha_fin']) < new DateTimeImmutable()) {
        $pdo->rollBack();
        jsonError('Esta actividad ya ha finalizado.', 400);
    }

    // 3. Validaciones específicas de torneos (PEGI + Nickname)
    $nicknameActual = $usuario['nickname'];

    if ($actividad['tipo'] === 'torneo') {
        if (!empty($actividad['pegi'])) {
            $nacimiento  = new DateTimeImmutable($usuario['fecha_nacimiento']);
            $fechaTorneo = new DateTimeImmutable($actividad['fecha_inicio']);
            $edad        = $nacimiento->diff($fechaTorneo)->y;

            if ($edad < (int) $actividad['pegi']) {
                $pdo->rollBack();
                jsonError(
                    "No cumples la edad mínima requerida (PEGI {$actividad['pegi']}) para participar en {$actividad['videojuego_nombre']}.",
                    403
                );
            }
        }

        if ($nickname !== '' && $nickname !== $nicknameActual) {
            $stmtNick = $pdo->prepare('UPDATE usuarios SET nickname = ? WHERE id = ?');
            $stmtNick->execute([$nickname, $usuarioId]);
            $nicknameActual = $nickname;
        }

        if (empty($nicknameActual)) {
            $pdo->rollBack();
            jsonError(
                'Para apuntarte a un torneo necesitas indicar tu Nickname / Gamer Tag.',
                422,
                ['requiere_nickname' => true]
            );
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
        jsonError('Ya tienes una inscripción activa en esta actividad.', 409);
    }

    // 5. Calcular disponibilidad de aforo (usa idx_inscripciones_act_estado_fecha)
    $estadoInscripcion   = 'confirmada';
    $posicionListaEspera = null;

    if ($actividad['aforo_maximo'] !== null) {
        $stmtAforo = $pdo->prepare(
            "SELECT COUNT(*) FROM inscripciones WHERE actividad_id = ? AND estado = 'confirmada'"
        );
        $stmtAforo->execute([$actividadId]);
        $plazasOcupadas = (int) $stmtAforo->fetchColumn();

        if ($plazasOcupadas >= (int) $actividad['aforo_maximo']) {
            $estadoInscripcion = 'lista_espera';
            $stmtEspera = $pdo->prepare(
                "SELECT COUNT(*) FROM inscripciones WHERE actividad_id = ? AND estado = 'lista_espera'"
            );
            $stmtEspera->execute([$actividadId]);
            $posicionListaEspera = ((int) $stmtEspera->fetchColumn()) + 1;
        }
    }

    $equipoFinal = ($actividad['tipo'] === 'torneo' && $nombreEquipo !== '') ? $nombreEquipo : null;

    // 6. Reactivar fila cancelada o insertar nueva inscripción con reintento ante colisión de codigo_acceso
    $codigoAcceso  = null;
    $inscripcionId = null;

    for ($intento = 0; $intento < 5; $intento++) {
        $codigoAcceso = generarCodigoAcceso($actividadId);

        try {
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
                    (int) $inscripcionPrevia['id'],
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
                    $codigoAcceso,
                ]);
                $inscripcionId = (int) $pdo->lastInsertId();
            }
            break;
        } catch (PDOException $e) {
            $esDuplicado = $e->getCode() === '23000' || (int) ($e->errorInfo[1] ?? 0) === 1062;
            if ($esDuplicado && strpos($e->getMessage(), 'codigo_acceso') !== false && $intento < 4) {
                continue;
            }
            throw $e;
        }
    }

    $pdo->commit();

    $mensaje = $estadoInscripcion === 'confirmada'
        ? 'Inscripción confirmada correctamente.'
        : "El aforo está completo. Has quedado apuntado en lista de espera (posición #{$posicionListaEspera}).";

    jsonResponse([
        'success'               => true,
        'mensaje'               => $mensaje,
        'inscripcion_id'        => $inscripcionId,
        'actividad_id'          => $actividadId,
        'codigo_acceso'         => $codigoAcceso,
        'estado'                => $estadoInscripcion,
        'posicion_lista_espera' => $posicionListaEspera,
        'actividad'             => $actividad['titulo'],
        'nickname'              => $nicknameActual,
    ], 201);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Error interno al procesar la inscripción.', 500);
}

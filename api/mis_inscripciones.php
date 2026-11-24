<?php
// api/mis_inscripciones.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error'   => 'Método no permitido. Usa GET.'
    ]);
    exit;
}

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode([
        'success'                => false,
        'requiere_autenticacion' => true,
        'error'                  => 'Debes iniciar sesión para consultar tus inscripciones.'
    ]);
    exit;
}

$usuarioId         = (int) $_SESSION['usuario_id'];
$incluirCanceladas = isset($_GET['incluir_canceladas']) && $_GET['incluir_canceladas'] === '1';

try {
    // Verificar que el usuario de la sesión sigue existiendo en BD
    $stmtUser = $pdo->prepare('SELECT id FROM usuarios WHERE id = ?');
    $stmtUser->execute([$usuarioId]);

    if (!$stmtUser->fetch()) {
        session_unset();
        session_destroy();
        http_response_code(401);
        echo json_encode([
            'success'                => false,
            'requiere_autenticacion' => true,
            'error'                  => 'La sesión ha caducado o el usuario no existe.'
        ]);
        exit;
    }

    $filtroEstado = $incluirCanceladas ? '' : "AND i.estado != 'cancelada'";

    $sql = "
        SELECT
            i.id AS inscripcion_id,
            i.actividad_id,
            i.nombre_equipo,
            i.fecha_inscripcion,
            i.estado,
            i.codigo_acceso,
            a.codigo_programa,
            a.titulo,
            a.tipo,
            a.tematica,
            a.fecha_inicio,
            a.fecha_fin,
            s.nombre AS sala_nombre,
            s.ubicacion AS sala_ubicacion,
            td.formato AS torneo_formato,
            v.nombre AS videojuego_nombre,
            v.pegi AS videojuego_pegi,
            (
                SELECT ast.hora_entrada
                FROM asistencias ast
                WHERE ast.inscripcion_id = i.id
                ORDER BY ast.hora_entrada ASC
                LIMIT 1
            ) AS hora_checkin
        FROM inscripciones i
        INNER JOIN actividades a ON i.actividad_id = a.id
        LEFT JOIN salas s ON a.sala_id = s.id
        LEFT JOIN torneos_detalle td ON a.id = td.actividad_id
        LEFT JOIN videojuegos v ON td.videojuego_id = v.id
        WHERE i.usuario_id = ?
          $filtroEstado
        ORDER BY a.fecha_inicio ASC, i.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$usuarioId]);
    $filas = $stmt->fetchAll();

    $stmtPosicionEspera = $pdo->prepare(
        "SELECT COUNT(*) + 1
         FROM inscripciones
         WHERE actividad_id = ?
           AND estado = 'lista_espera'
           AND (fecha_inscripcion < ? OR (fecha_inscripcion = ? AND id < ?))"
    );

    $ahora = new DateTime();
    $inscripciones = [];

    foreach ($filas as $fila) {
        $inscripcionId = (int) $fila['inscripcion_id'];
        $actividadId   = (int) $fila['actividad_id'];
        $finalizada    = (new DateTime($fila['fecha_fin']) < $ahora);

        $posicionListaEspera = null;
        if ($fila['estado'] === 'lista_espera') {
            $stmtPosicionEspera->execute([
                $actividadId,
                $fila['fecha_inscripcion'],
                $fila['fecha_inscripcion'],
                $inscripcionId
            ]);
            $posicionListaEspera = (int) $stmtPosicionEspera->fetchColumn();
        }

        $inscripciones[] = [
            'inscripcion_id'        => $inscripcionId,
            'actividad_id'          => $actividadId,
            'codigo_programa'       => $fila['codigo_programa'],
            'titulo'                => $fila['titulo'],
            'tipo'                  => $fila['tipo'],
            'tematica'              => $fila['tematica'],
            'fecha_inicio'          => $fila['fecha_inicio'],
            'fecha_fin'             => $fila['fecha_fin'],
            'finalizada'            => $finalizada,
            'sala'                  => $fila['sala_nombre'] !== null ? [
                'nombre'    => $fila['sala_nombre'],
                'ubicacion' => $fila['sala_ubicacion']
            ] : null,
            'torneo'                => $fila['tipo'] === 'torneo' ? [
                'videojuego' => $fila['videojuego_nombre'],
                'formato'    => $fila['torneo_formato'],
                'pegi'       => $fila['videojuego_pegi'] !== null ? (int) $fila['videojuego_pegi'] : null
            ] : null,
            'nombre_equipo'         => $fila['nombre_equipo'],
            'fecha_inscripcion'     => $fila['fecha_inscripcion'],
            'estado'                => $fila['estado'],
            'posicion_lista_espera' => $posicionListaEspera,
            'codigo_acceso'         => $fila['codigo_acceso'],
            'checkin_realizado'     => $fila['hora_checkin'] !== null,
            'hora_checkin'          => $fila['hora_checkin']
        ];
    }

    echo json_encode([
        'success'       => true,
        'total'         => count($inscripciones),
        'inscripciones' => $inscripciones
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Error interno al obtener tus inscripciones.'
    ]);
}

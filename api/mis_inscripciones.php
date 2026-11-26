<?php
// api/mis_inscripciones.php
require_once __DIR__ . '/../includes/db.php';

requireHttpMethod('GET');

$usuarioId = requireAuthenticatedUserId('Debes iniciar sesión para consultar tus inscripciones.');
$incluirCanceladas = isset($_GET['incluir_canceladas']) && $_GET['incluir_canceladas'] === '1';

try {
    // Verificar que el usuario de la sesión sigue existiendo en BD
    $stmtUser = $pdo->prepare('SELECT id FROM usuarios WHERE id = ?');
    $stmtUser->execute([$usuarioId]);

    if (!$stmtUser->fetch()) {
        clearUserSession();
        jsonError('La sesión ha caducado o el usuario no existe.', 401, ['requiere_autenticacion' => true]);
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
            a.sala_id,
            s.nombre AS sala_nombre,
            s.ubicacion AS sala_ubicacion,
            td.formato AS torneo_formato,
            v.nombre AS videojuego_nombre,
            v.pegi AS videojuego_pegi
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

    $checkinsPorInscripcion = [];
    $posicionesListaEspera  = [];

    if (!empty($filas)) {
        $inscripcionIds = array_map('intval', array_column($filas, 'inscripcion_id'));
        $inPlaceholders = implode(',', array_fill(0, count($inscripcionIds), '?'));

        // 1. Obtener check-ins en una única consulta por lote (usa idx_asistencias_insc_hora)
        $stmtCheckins = $pdo->prepare(
            "SELECT inscripcion_id, MIN(hora_entrada) AS hora_checkin
             FROM asistencias
             WHERE inscripcion_id IN ($inPlaceholders)
             GROUP BY inscripcion_id"
        );
        $stmtCheckins->execute($inscripcionIds);

        foreach ($stmtCheckins->fetchAll() as $chk) {
            $checkinsPorInscripcion[(int) $chk['inscripcion_id']] = $chk['hora_checkin'];
        }

        // 2. Calcular posiciones en lista de espera en una sola consulta (sin N+1, compatible MySQL 5.7/8/MariaDB)
        $waitlistActIds = [];
        foreach ($filas as $fila) {
            if ($fila['estado'] === 'lista_espera') {
                $waitlistActIds[(int) $fila['actividad_id']] = true;
            }
        }

        if (!empty($waitlistActIds)) {
            $actIds          = array_keys($waitlistActIds);
            $actPlaceholders = implode(',', array_fill(0, count($actIds), '?'));

            // Recorrido solo de índice sobre idx_inscripciones_act_estado_fecha
            $stmtCola = $pdo->prepare(
                "SELECT id, actividad_id
                 FROM inscripciones
                 WHERE actividad_id IN ($actPlaceholders)
                   AND estado = 'lista_espera'
                 ORDER BY actividad_id ASC, fecha_inscripcion ASC, id ASC"
            );
            $stmtCola->execute($actIds);

            $contadorPorAct = [];
            foreach ($stmtCola->fetchAll() as $item) {
                $actId = (int) $item['actividad_id'];
                $contadorPorAct[$actId] = ($contadorPorAct[$actId] ?? 0) + 1;
                $posicionesListaEspera[(int) $item['id']] = $contadorPorAct[$actId];
            }
        }
    }

    $ahora         = new DateTimeImmutable();
    $inscripciones = [];

    foreach ($filas as $fila) {
        $inscripcionId = (int) $fila['inscripcion_id'];
        $actividadId   = (int) $fila['actividad_id'];
        $finalizada    = (new DateTimeImmutable($fila['fecha_fin']) < $ahora);
        $horaCheckin   = $checkinsPorInscripcion[$inscripcionId] ?? null;

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
            'sala'                  => $fila['sala_id'] !== null ? [
                'id'        => (int) $fila['sala_id'],
                'nombre'    => $fila['sala_nombre'],
                'ubicacion' => $fila['sala_ubicacion'],
            ] : null,
            'torneo'                => $fila['tipo'] === 'torneo' ? [
                'videojuego' => $fila['videojuego_nombre'],
                'formato'    => $fila['torneo_formato'],
                'pegi'       => $fila['videojuego_pegi'] !== null ? (int) $fila['videojuego_pegi'] : null,
            ] : null,
            'nombre_equipo'         => $fila['nombre_equipo'],
            'fecha_inscripcion'     => $fila['fecha_inscripcion'],
            'estado'                => $fila['estado'],
            'posicion_lista_espera' => $fila['estado'] === 'lista_espera'
                ? ($posicionesListaEspera[$inscripcionId] ?? null)
                : null,
            'codigo_acceso'         => $fila['codigo_acceso'],
            'checkin_realizado'     => $horaCheckin !== null,
            'hora_checkin'          => $horaCheckin,
        ];
    }

    jsonResponse([
        'success'       => true,
        'total'         => count($inscripciones),
        'inscripciones' => $inscripciones,
    ]);

} catch (Exception $e) {
    jsonError('Error interno al obtener tus inscripciones.', 500);
}

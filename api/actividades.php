<?php
// api/actividades.php
require_once __DIR__ . '/../includes/db.php';

requireHttpMethod('GET');

$tipoFiltro     = trim((string) ($_GET['tipo'] ?? ''));
$fechaFiltro    = trim((string) ($_GET['fecha'] ?? ''));
$tematicaFiltro = trim((string) ($_GET['tematica'] ?? ''));
$salaFiltro     = $_GET['sala_id'] ?? null;

$tiposValidos = ['charla', 'taller', 'torneo', 'actividad_libre'];
$whereClauses = [];
$params       = [];

if ($tipoFiltro !== '') {
    if (!in_array($tipoFiltro, $tiposValidos, true)) {
        jsonError('El filtro tipo no es válido.', 400);
    }
    $whereClauses[] = 'a.tipo = ?';
    $params[]       = $tipoFiltro;
}

if ($fechaFiltro !== '') {
    $fechaObj = DateTimeImmutable::createFromFormat('Y-m-d', $fechaFiltro);
    if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fechaFiltro) {
        jsonError('El filtro fecha debe tener formato YYYY-MM-DD.', 400);
    }
    // Rango sargable para aprovechar índices sobre a.fecha_inicio
    $whereClauses[] = 'a.fecha_inicio >= ? AND a.fecha_inicio < ?';
    $params[]       = $fechaObj->format('Y-m-d 00:00:00');
    $params[]       = $fechaObj->modify('+1 day')->format('Y-m-d 00:00:00');
}

if ($tematicaFiltro !== '') {
    if (mb_strlen($tematicaFiltro) > 60) {
        jsonError('El filtro temática no puede superar los 60 caracteres.', 400);
    }
    $whereClauses[] = 'a.tematica = ?';
    $params[]       = $tematicaFiltro;
}

if ($salaFiltro !== null && $salaFiltro !== '') {
    $salaId = filter_var($salaFiltro, FILTER_VALIDATE_INT);
    if (!$salaId || $salaId <= 0) {
        jsonError('El filtro sala_id no es válido.', 400);
    }
    $whereClauses[] = 'a.sala_id = ?';
    $params[]       = (int) $salaId;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

try {
    $sql = "
        SELECT
            a.id,
            a.sala_id,
            a.codigo_programa,
            a.tematica,
            a.titulo,
            a.descripcion,
            a.tipo,
            a.fecha_inicio,
            a.fecha_fin,
            a.aforo_maximo,
            a.requiere_inscripcion,
            a.datos_extra,
            s.nombre AS sala_nombre,
            s.ubicacion AS sala_ubicacion,
            s.orden AS sala_orden,
            td.formato AS torneo_formato,
            td.premios AS torneo_premios,
            td.reglas AS torneo_reglas,
            v.id AS videojuego_id,
            v.nombre AS videojuego_nombre,
            v.genero AS videojuego_genero,
            v.plataforma AS videojuego_plataforma,
            v.pegi AS videojuego_pegi
        FROM actividades a
        LEFT JOIN salas s ON a.sala_id = s.id
        LEFT JOIN torneos_detalle td ON a.id = td.actividad_id
        LEFT JOIN videojuegos v ON td.videojuego_id = v.id
        $whereSql
        ORDER BY a.fecha_inicio ASC, s.orden ASC, a.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll();

    $statsPorActividad    = [];
    $ponentesPorActividad = [];
    $partidasPorTorneo    = [];
    $miInscripcionPorAct  = [];

    if (!empty($filas)) {
        $ids          = array_map('intval', array_column($filas, 'id'));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // 1. Contadores de ocupación solo para las actividades listadas (usa idx_inscripciones_act_estado_fecha)
        $sqlStats = "
            SELECT actividad_id, estado, COUNT(*) AS total
            FROM inscripciones
            WHERE actividad_id IN ($placeholders)
              AND estado IN ('confirmada', 'lista_espera')
            GROUP BY actividad_id, estado
        ";
        $stmtStats = $pdo->prepare($sqlStats);
        $stmtStats->execute($ids);

        foreach ($stmtStats->fetchAll() as $stat) {
            $actId = (int) $stat['actividad_id'];
            $statsPorActividad[$actId][$stat['estado']] = (int) $stat['total'];
        }

        // 2. Ponentes asociados en una única consulta (sin N+1)
        $sqlPonentes = "
            SELECT
                ap.actividad_id,
                ap.rol,
                p.id AS ponente_id,
                p.nombre,
                p.apellidos,
                p.titular,
                p.empresa_o_rol,
                p.biografia,
                p.foto_url,
                p.web_personal,
                p.redes_sociales
            FROM actividad_ponentes ap
            INNER JOIN ponentes p ON ap.ponente_id = p.id
            WHERE ap.actividad_id IN ($placeholders)
            ORDER BY FIELD(ap.rol, 'Moderador', 'Ponente', 'Invitado'), p.apellidos ASC
        ";
        $stmtPon = $pdo->prepare($sqlPonentes);
        $stmtPon->execute($ids);

        foreach ($stmtPon->fetchAll() as $pon) {
            $actId = (int) $pon['actividad_id'];
            $redes = null;
            if (!empty($pon['redes_sociales'])) {
                $decodedRedes = json_decode($pon['redes_sociales'], true);
                $redes        = is_array($decodedRedes) ? $decodedRedes : null;
            }

            $ponentesPorActividad[$actId][] = [
                'id'             => (int) $pon['ponente_id'],
                'nombre'         => $pon['nombre'],
                'apellidos'      => $pon['apellidos'],
                'rol'            => $pon['rol'],
                'titular'        => $pon['titular'],
                'empresa_o_rol'  => $pon['empresa_o_rol'],
                'biografia'      => $pon['biografia'],
                'foto_url'       => $pon['foto_url'],
                'web_personal'   => $pon['web_personal'],
                'redes_sociales' => $redes,
            ];
        }

        // 3. Partidas / cuadro de clasificación de torneos en una única consulta
        $torneoIds = [];
        foreach ($filas as $fila) {
            if ($fila['tipo'] === 'torneo' && $fila['videojuego_id'] !== null) {
                $torneoIds[] = (int) $fila['id'];
            }
        }

        if (!empty($torneoIds)) {
            $torneoPlaceholders = implode(',', array_fill(0, count($torneoIds), '?'));
            $sqlPartidas = "
                SELECT
                    tp.id,
                    tp.actividad_id,
                    tp.ronda,
                    tp.numero_partida,
                    tp.inscripcion_1_id,
                    tp.inscripcion_2_id,
                    tp.puntos_1,
                    tp.puntos_2,
                    tp.ganador_inscripcion_id,
                    tp.estado_partida,
                    COALESCE(NULLIF(i1.nombre_equipo, ''), u1.nickname, u1.nombre) AS participante_1,
                    COALESCE(NULLIF(i2.nombre_equipo, ''), u2.nickname, u2.nombre) AS participante_2
                FROM torneo_partidas tp
                LEFT JOIN inscripciones i1 ON tp.inscripcion_1_id = i1.id
                LEFT JOIN usuarios u1 ON i1.usuario_id = u1.id
                LEFT JOIN inscripciones i2 ON tp.inscripcion_2_id = i2.id
                LEFT JOIN usuarios u2 ON i2.usuario_id = u2.id
                WHERE tp.actividad_id IN ($torneoPlaceholders)
                ORDER BY tp.actividad_id ASC, tp.id ASC
            ";
            $stmtPartidas = $pdo->prepare($sqlPartidas);
            $stmtPartidas->execute($torneoIds);

            foreach ($stmtPartidas->fetchAll() as $partida) {
                $actId = (int) $partida['actividad_id'];
                $partidasPorTorneo[$actId][] = [
                    'id'                     => (int) $partida['id'],
                    'ronda'                  => $partida['ronda'],
                    'numero_partida'         => (int) $partida['numero_partida'],
                    'inscripcion_1_id'       => $partida['inscripcion_1_id'] !== null ? (int) $partida['inscripcion_1_id'] : null,
                    'inscripcion_2_id'       => $partida['inscripcion_2_id'] !== null ? (int) $partida['inscripcion_2_id'] : null,
                    'participante_1'         => $partida['participante_1'],
                    'participante_2'         => $partida['participante_2'],
                    'puntos_1'               => (int) $partida['puntos_1'],
                    'puntos_2'               => (int) $partida['puntos_2'],
                    'ganador_inscripcion_id' => $partida['ganador_inscripcion_id'] !== null ? (int) $partida['ganador_inscripcion_id'] : null,
                    'estado_partida'         => $partida['estado_partida'],
                ];
            }
        }

        // 4. Si hay sesión activa, adjuntar el estado de inscripción del usuario actual
        $sessionUserId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
        if ($sessionUserId && $sessionUserId > 0) {
            $sqlMia = "
                SELECT id, actividad_id, estado, codigo_acceso, nombre_equipo
                FROM inscripciones
                WHERE usuario_id = ?
                  AND actividad_id IN ($placeholders)
                  AND estado != 'cancelada'
            ";
            $stmtMia = $pdo->prepare($sqlMia);
            $stmtMia->execute(array_merge([(int) $sessionUserId], $ids));

            foreach ($stmtMia->fetchAll() as $mia) {
                $miInscripcionPorAct[(int) $mia['actividad_id']] = [
                    'inscripcion_id' => (int) $mia['id'],
                    'estado'         => $mia['estado'],
                    'codigo_acceso'  => $mia['codigo_acceso'],
                    'nombre_equipo'  => $mia['nombre_equipo'],
                ];
            }
        }
    }

    $ahora       = new DateTimeImmutable();
    $actividades = [];

    foreach ($filas as $fila) {
        $id             = (int) $fila['id'];
        $aforoMaximo    = $fila['aforo_maximo'] !== null ? (int) $fila['aforo_maximo'] : null;
        $plazasOcupadas = $statsPorActividad[$id]['confirmada'] ?? 0;
        $listaEspera    = $statsPorActividad[$id]['lista_espera'] ?? 0;

        $plazasDisponibles = $aforoMaximo !== null
            ? max(0, $aforoMaximo - $plazasOcupadas)
            : null;

        $aforoCompleto = ($aforoMaximo !== null && $plazasOcupadas >= $aforoMaximo);
        $finalizada    = (new DateTimeImmutable($fila['fecha_fin']) < $ahora);

        $datosExtra = null;
        if (!empty($fila['datos_extra'])) {
            $decoded    = json_decode($fila['datos_extra'], true);
            $datosExtra = is_array($decoded) ? $decoded : null;
        }

        $torneo = null;
        if ($fila['tipo'] === 'torneo' && $fila['videojuego_id'] !== null) {
            $torneo = [
                'formato'    => $fila['torneo_formato'],
                'premios'    => $fila['torneo_premios'],
                'reglas'     => $fila['torneo_reglas'],
                'videojuego' => [
                    'id'         => (int) $fila['videojuego_id'],
                    'nombre'     => $fila['videojuego_nombre'],
                    'genero'     => $fila['videojuego_genero'],
                    'plataforma' => $fila['videojuego_plataforma'],
                    'pegi'       => (int) $fila['videojuego_pegi'],
                ],
                'partidas'   => $partidasPorTorneo[$id] ?? [],
            ];
        }

        $actividades[] = [
            'id'                   => $id,
            'codigo_programa'      => $fila['codigo_programa'],
            'tematica'             => $fila['tematica'],
            'titulo'               => $fila['titulo'],
            'descripcion'          => $fila['descripcion'],
            'tipo'                 => $fila['tipo'],
            'fecha_inicio'         => $fila['fecha_inicio'],
            'fecha_fin'            => $fila['fecha_fin'],
            'finalizada'           => $finalizada,
            'requiere_inscripcion' => ((int) $fila['requiere_inscripcion']) === 1,
            'aforo_maximo'         => $aforoMaximo,
            'plazas_ocupadas'      => $plazasOcupadas,
            'plazas_disponibles'   => $plazasDisponibles,
            'lista_espera_count'   => $listaEspera,
            'aforo_completo'       => $aforoCompleto,
            'sala'                 => $fila['sala_id'] !== null ? [
                'id'        => (int) $fila['sala_id'],
                'nombre'    => $fila['sala_nombre'],
                'ubicacion' => $fila['sala_ubicacion'],
            ] : null,
            'torneo'               => $torneo,
            'ponentes'             => $ponentesPorActividad[$id] ?? [],
            'datos_extra'          => $datosExtra,
            'mi_inscripcion'       => $miInscripcionPorAct[$id] ?? null,
        ];
    }

    jsonResponse([
        'success'     => true,
        'evento'      => [
            'titulo' => SITE_TITLE,
            'inicio' => EVENT_START,
        ],
        'total'       => count($actividades),
        'actividades' => $actividades,
    ]);

} catch (Exception $e) {
    jsonError('Error interno al obtener el listado de actividades.', 500);
}

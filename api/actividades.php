<?php
// api/actividades.php
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

$tipoFiltro  = trim($_GET['tipo'] ?? '');
$fechaFiltro = trim($_GET['fecha'] ?? '');

$tiposValidos = ['charla', 'taller', 'torneo', 'actividad_libre'];
$whereClauses = [];
$params       = [];

if ($tipoFiltro !== '') {
    if (!in_array($tipoFiltro, $tiposValidos, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'El filtro tipo no es válido.'
        ]);
        exit;
    }
    $whereClauses[] = 'a.tipo = ?';
    $params[]       = $tipoFiltro;
}

if ($fechaFiltro !== '') {
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fechaFiltro);
    if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fechaFiltro) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'El filtro fecha debe tener formato YYYY-MM-DD.'
        ]);
        exit;
    }
    $whereClauses[] = 'DATE(a.fecha_inicio) = ?';
    $params[]       = $fechaFiltro;
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
            v.pegi AS videojuego_pegi,
            COALESCE(stats.plazas_ocupadas, 0) AS plazas_ocupadas,
            COALESCE(stats.lista_espera_count, 0) AS lista_espera_count
        FROM actividades a
        LEFT JOIN salas s ON a.sala_id = s.id
        LEFT JOIN torneos_detalle td ON a.id = td.actividad_id
        LEFT JOIN videojuegos v ON td.videojuego_id = v.id
        LEFT JOIN (
            SELECT
                actividad_id,
                SUM(estado = 'confirmada') AS plazas_ocupadas,
                SUM(estado = 'lista_espera') AS lista_espera_count
            FROM inscripciones
            GROUP BY actividad_id
        ) stats ON a.id = stats.actividad_id
        $whereSql
        ORDER BY a.fecha_inicio ASC, s.orden ASC, a.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll();

    // Obtener ponentes asociados en una única consulta para evitar N+1
    $ponentesPorActividad = [];
    if (!empty($filas)) {
        $ids = array_column($filas, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sqlPonentes = "
            SELECT
                ap.actividad_id,
                ap.rol,
                p.id AS ponente_id,
                p.nombre,
                p.apellidos,
                p.titular,
                p.empresa_o_rol,
                p.foto_url
            FROM actividad_ponentes ap
            INNER JOIN ponentes p ON ap.ponente_id = p.id
            WHERE ap.actividad_id IN ($placeholders)
            ORDER BY FIELD(ap.rol, 'Moderador', 'Ponente', 'Invitado'), p.apellidos ASC
        ";

        $stmtPon = $pdo->prepare($sqlPonentes);
        $stmtPon->execute($ids);

        foreach ($stmtPon->fetchAll() as $pon) {
            $actId = (int) $pon['actividad_id'];
            $ponentesPorActividad[$actId][] = [
                'id'            => (int) $pon['ponente_id'],
                'nombre'        => $pon['nombre'],
                'apellidos'     => $pon['apellidos'],
                'rol'           => $pon['rol'],
                'titular'       => $pon['titular'],
                'empresa_o_rol' => $pon['empresa_o_rol'],
                'foto_url'      => $pon['foto_url']
            ];
        }
    }

    $ahora = new DateTime();
    $actividades = [];

    foreach ($filas as $fila) {
        $id             = (int) $fila['id'];
        $aforoMaximo    = $fila['aforo_maximo'] !== null ? (int) $fila['aforo_maximo'] : null;
        $plazasOcupadas = (int) $fila['plazas_ocupadas'];
        $listaEspera    = (int) $fila['lista_espera_count'];

        $plazasDisponibles = $aforoMaximo !== null
            ? max(0, $aforoMaximo - $plazasOcupadas)
            : null;

        $aforoCompleto = ($aforoMaximo !== null && $plazasOcupadas >= $aforoMaximo);
        $finalizada    = (new DateTime($fila['fecha_fin']) < $ahora);

        $datosExtra = null;
        if (!empty($fila['datos_extra'])) {
            $decoded = json_decode($fila['datos_extra'], true);
            $datosExtra = is_array($decoded) ? $decoded : null;
        }

        $torneo = null;
        if ($fila['tipo'] === 'torneo' && $fila['videojuego_id'] !== null) {
            $torneo = [
                'formato' => $fila['torneo_formato'],
                'premios' => $fila['torneo_premios'],
                'reglas'  => $fila['torneo_reglas'],
                'videojuego' => [
                    'id'         => (int) $fila['videojuego_id'],
                    'nombre'     => $fila['videojuego_nombre'],
                    'genero'     => $fila['videojuego_genero'],
                    'plataforma' => $fila['videojuego_plataforma'],
                    'pegi'       => (int) $fila['videojuego_pegi']
                ]
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
                'ubicacion' => $fila['sala_ubicacion']
            ] : null,
            'torneo'               => $torneo,
            'ponentes'             => $ponentesPorActividad[$id] ?? [],
            'datos_extra'          => $datosExtra
        ];
    }

    echo json_encode([
        'success'     => true,
        'total'       => count($actividades),
        'actividades' => $actividades
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Error interno al obtener el listado de actividades.'
    ]);
}

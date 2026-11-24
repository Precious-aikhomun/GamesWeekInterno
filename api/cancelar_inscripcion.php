<?php
// api/cancelar_inscripcion.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error'   => 'Método no permitido. Usa POST.'
    ]);
    exit;
}

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode([
        'success'                => false,
        'requiere_autenticacion' => true,
        'error'                  => 'Debes iniciar sesión para gestionar tus inscripciones.'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$usuarioId     = (int) $_SESSION['usuario_id'];
$inscripcionId = filter_var($data['inscripcion_id'] ?? null, FILTER_VALIDATE_INT);
$actividadId   = filter_var($data['actividad_id'] ?? null, FILTER_VALIDATE_INT);

if (!$inscripcionId && !$actividadId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Debes indicar inscripcion_id o actividad_id.'
    ]);
    exit;
}

try {
    // Localizar primero el actividad_id sin bloqueo para mantener el orden de locks (actividades -> inscripciones)
    if ($inscripcionId) {
        $stmtLookup = $pdo->prepare(
            'SELECT id, actividad_id FROM inscripciones WHERE id = ? AND usuario_id = ?'
        );
        $stmtLookup->execute([$inscripcionId, $usuarioId]);
    } else {
        $stmtLookup = $pdo->prepare(
            'SELECT id, actividad_id FROM inscripciones WHERE actividad_id = ? AND usuario_id = ?'
        );
        $stmtLookup->execute([$actividadId, $usuarioId]);
    }

    $registroBase = $stmtLookup->fetch();

    if (!$registroBase) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error'   => 'No se ha encontrado ninguna inscripción asociada a tu cuenta.'
        ]);
        exit;
    }

    $targetInscripcionId = (int) $registroBase['id'];
    $targetActividadId   = (int) $registroBase['actividad_id'];

    $pdo->beginTransaction();

    // 1. Bloquear la fila de la actividad (mismo orden que api/inscripcion.php)
    $stmtAct = $pdo->prepare(
        'SELECT id, titulo, fecha_fin, aforo_maximo FROM actividades WHERE id = ? FOR UPDATE'
    );
    $stmtAct->execute([$targetActividadId]);
    $actividad = $stmtAct->fetch();

    if (!$actividad) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error'   => 'La actividad asociada ya no existe.'
        ]);
        exit;
    }

    if (new DateTime($actividad['fecha_fin']) < new DateTime()) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'No puedes cancelar la inscripción de una actividad que ya ha finalizado.'
        ]);
        exit;
    }

    // 2. Bloquear la inscripción del usuario y comprobar estado actual
    $stmtInsc = $pdo->prepare(
        'SELECT id, estado FROM inscripciones WHERE id = ? AND usuario_id = ? FOR UPDATE'
    );
    $stmtInsc->execute([$targetInscripcionId, $usuarioId]);
    $inscripcion = $stmtInsc->fetch();

    if (!$inscripcion || $inscripcion['estado'] === 'cancelada') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error'   => 'Esta inscripción ya se encontraba cancelada.'
        ]);
        exit;
    }

    $estadoAnterior = $inscripcion['estado'];

    // 3. Marcar la inscripción como cancelada
    $stmtCancelar = $pdo->prepare(
        "UPDATE inscripciones SET estado = 'cancelada' WHERE id = ?"
    );
    $stmtCancelar->execute([$targetInscripcionId]);

    // 4. Si se liberó una plaza confirmada, promover al siguiente en lista de espera
    $promovidoListaEspera = false;

    if ($estadoAnterior === 'confirmada') {
        $stmtSiguiente = $pdo->prepare(
            "SELECT id
             FROM inscripciones
             WHERE actividad_id = ? AND estado = 'lista_espera'
             ORDER BY fecha_inscripcion ASC, id ASC
             LIMIT 1
             FOR UPDATE"
        );
        $stmtSiguiente->execute([$targetActividadId]);
        $siguienteEnCola = $stmtSiguiente->fetch();

        if ($siguienteEnCola) {
            $stmtPromover = $pdo->prepare(
                "UPDATE inscripciones SET estado = 'confirmada' WHERE id = ?"
            );
            $stmtPromover->execute([(int) $siguienteEnCola['id']]);
            $promovidoListaEspera = true;
        }
    }

    $pdo->commit();

    echo json_encode([
        'success'                => true,
        'mensaje'                => 'Tu inscripción ha sido cancelada correctamente.',
        'inscripcion_id'         => $targetInscripcionId,
        'actividad_id'           => $targetActividadId,
        'actividad'              => $actividad['titulo'],
        'promovido_lista_espera' => $promovidoListaEspera
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Error interno al cancelar la inscripción.'
    ]);
}

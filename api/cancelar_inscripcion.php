<?php
// api/cancelar_inscripcion.php
require_once __DIR__ . '/../includes/db.php';

requireHttpMethod('POST');

$usuarioId = requireAuthenticatedUserId('Debes iniciar sesión para gestionar tus inscripciones.');

$data          = getRequestData();
$inscripcionId = filter_var($data['inscripcion_id'] ?? null, FILTER_VALIDATE_INT);
$actividadId   = filter_var($data['actividad_id'] ?? null, FILTER_VALIDATE_INT);

if ((!$inscripcionId || $inscripcionId <= 0) && (!$actividadId || $actividadId <= 0)) {
    jsonError('Debes indicar inscripcion_id o actividad_id.', 400);
}

try {
    // Localizar primero el actividad_id sin bloqueo para mantener el orden de locks (actividades -> inscripciones)
    if ($inscripcionId && $inscripcionId > 0) {
        $stmtLookup = $pdo->prepare(
            'SELECT id, actividad_id FROM inscripciones WHERE id = ? AND usuario_id = ?'
        );
        $stmtLookup->execute([(int) $inscripcionId, $usuarioId]);
    } else {
        $stmtLookup = $pdo->prepare(
            'SELECT id, actividad_id FROM inscripciones WHERE actividad_id = ? AND usuario_id = ?'
        );
        $stmtLookup->execute([(int) $actividadId, $usuarioId]);
    }

    $registroBase = $stmtLookup->fetch();

    if (!$registroBase) {
        jsonError('No se ha encontrado ninguna inscripción asociada a tu cuenta.', 404);
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
        jsonError('La actividad asociada ya no existe.', 404);
    }

    if (new DateTimeImmutable($actividad['fecha_fin']) < new DateTimeImmutable()) {
        $pdo->rollBack();
        jsonError('No puedes cancelar la inscripción de una actividad que ya ha finalizado.', 400);
    }

    // 2. Bloquear la inscripción del usuario y comprobar estado actual
    $stmtInsc = $pdo->prepare(
        'SELECT id, estado FROM inscripciones WHERE id = ? AND usuario_id = ? FOR UPDATE'
    );
    $stmtInsc->execute([$targetInscripcionId, $usuarioId]);
    $inscripcion = $stmtInsc->fetch();

    if (!$inscripcion || $inscripcion['estado'] === 'cancelada') {
        $pdo->rollBack();
        jsonError('Esta inscripción ya se encontraba cancelada.', 409);
    }

    $estadoAnterior = $inscripcion['estado'];

    // 3. Marcar la inscripción como cancelada
    $stmtCancelar = $pdo->prepare(
        "UPDATE inscripciones SET estado = 'cancelada' WHERE id = ?"
    );
    $stmtCancelar->execute([$targetInscripcionId]);

    // 4. Si se liberó una plaza confirmada, promover al siguiente en lista de espera verificando aforo real
    $promovidoListaEspera = false;

    if ($estadoAnterior === 'confirmada') {
        $aforoMaximo = $actividad['aforo_maximo'] !== null ? (int) $actividad['aforo_maximo'] : null;
        $promovidoListaEspera = promoverSiguienteListaEspera($pdo, $targetActividadId, $aforoMaximo);
    }

    $pdo->commit();

    jsonResponse([
        'success'                => true,
        'mensaje'                => 'Tu inscripción ha sido cancelada correctamente.',
        'inscripcion_id'         => $targetInscripcionId,
        'actividad_id'           => $targetActividadId,
        'actividad'              => $actividad['titulo'],
        'estado'                 => 'cancelada',
        'promovido_lista_espera' => $promovidoListaEspera,
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Error interno al cancelar la inscripción.', 500);
}

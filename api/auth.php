<?php
// api/auth.php
require_once __DIR__ . '/../includes/db.php';

$data   = getRequestData();
$action = trim((string) ($_GET['action'] ?? $data['action'] ?? ''));
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/**
 * Obtiene los datos públicos del usuario autenticado sin acoplar sus inscripciones.
 */
function obtenerUsuarioPublico(PDO $pdo, int $usuarioId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, nombre, apellidos, email, telefono, fecha_nacimiento, nickname, fecha_registro
         FROM usuarios
         WHERE id = ?'
    );
    $stmt->execute([$usuarioId]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        return null;
    }

    $usuario['id'] = (int) $usuario['id'];
    return $usuario;
}

/**
 * Valida y normaliza los campos comunes de perfil entre registro y actualización.
 */
function validarCamposPerfil(array $data): array
{
    $nombre          = trim((string) ($data['nombre'] ?? ''));
    $apellidos       = trim((string) ($data['apellidos'] ?? ''));
    $email           = mb_strtolower(trim((string) ($data['email'] ?? '')));
    $fechaNacimiento = trim((string) ($data['fecha_nacimiento'] ?? ''));
    $telefonoRaw     = trim((string) ($data['telefono'] ?? ''));
    $telefono        = $telefonoRaw !== '' ? $telefonoRaw : null;
    $nicknameRaw     = trim((string) ($data['nickname'] ?? ''));
    $nickname        = $nicknameRaw !== '' ? $nicknameRaw : null;

    if ($nombre === '' || $apellidos === '' || $email === '' || $fechaNacimiento === '') {
        jsonError('Completa los campos obligatorios: nombre, apellidos, email y fecha de nacimiento.', 400);
    }

    if (mb_strlen($nombre) > 80) {
        jsonError('El nombre no puede superar los 80 caracteres.', 400);
    }

    if (mb_strlen($apellidos) > 120) {
        jsonError('Los apellidos no pueden superar los 120 caracteres.', 400);
    }

    if (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('El formato del correo electrónico no es válido o excede la longitud máxima.', 400);
    }

    if ($telefono !== null && mb_strlen($telefono) > 25) {
        jsonError('El teléfono no puede superar los 25 caracteres.', 400);
    }

    if ($nickname !== null && mb_strlen($nickname) > 50) {
        jsonError('El nickname no puede superar los 50 caracteres.', 400);
    }

    $fechaObj = DateTimeImmutable::createFromFormat('Y-m-d', $fechaNacimiento);
    $hoy      = new DateTimeImmutable('today');
    $minFecha = new DateTimeImmutable('1900-01-01');

    if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fechaNacimiento || $fechaObj > $hoy || $fechaObj < $minFecha) {
        jsonError('La fecha de nacimiento no es válida.', 400);
    }

    return [
        'nombre'           => $nombre,
        'apellidos'        => $apellidos,
        'email'            => $email,
        'telefono'         => $telefono,
        'fecha_nacimiento' => $fechaNacimiento,
        'nickname'         => $nickname,
    ];
}

try {
    // 1. CONSULTAR ESTADO DE SESIÓN ACTUAL
    if ($action === 'status' && $method === 'GET') {
        $sessionUserId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$sessionUserId || $sessionUserId <= 0) {
            jsonResponse([
                'success'       => true,
                'authenticated' => false,
                'usuario'       => null,
            ]);
        }

        $usuario = obtenerUsuarioPublico($pdo, (int) $sessionUserId);

        if (!$usuario) {
            clearUserSession();
            jsonResponse([
                'success'       => true,
                'authenticated' => false,
                'usuario'       => null,
            ]);
        }

        jsonResponse([
            'success'       => true,
            'authenticated' => true,
            'usuario'       => $usuario,
        ]);
    }

    // 2. INICIAR SESIÓN
    if ($action === 'login' && $method === 'POST') {
        $email    = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            jsonError('Introduce tu correo electrónico y contraseña.', 400);
        }

        $stmt = $pdo->prepare('SELECT id, password_hash FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        $userRow = $stmt->fetch();

        if (!$userRow || !password_verify($password, $userRow['password_hash'])) {
            jsonError('Correo electrónico o contraseña incorrectos.', 401);
        }

        session_regenerate_id(true);
        $usuarioId = (int) $userRow['id'];
        $_SESSION['usuario_id'] = $usuarioId;

        $usuario = obtenerUsuarioPublico($pdo, $usuarioId);

        jsonResponse([
            'success'       => true,
            'mensaje'       => 'Sesión iniciada correctamente.',
            'authenticated' => true,
            'usuario'       => $usuario,
        ]);
    }

    // 3. REGISTRAR NUEVO USUARIO E INICIAR SESIÓN AUTOMÁTICAMENTE
    if ($action === 'register' && $method === 'POST') {
        $perfil   = validarCamposPerfil($data);
        $password = (string) ($data['password'] ?? '');

        if ($password === '') {
            jsonError('Introduce una contraseña para crear tu cuenta.', 400);
        }

        if (mb_strlen($password) < 6) {
            jsonError('La contraseña debe tener al menos 6 caracteres.', 400);
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmtInsert = $pdo->prepare(
                'INSERT INTO usuarios (nombre, apellidos, email, password_hash, telefono, fecha_nacimiento, nickname)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmtInsert->execute([
                $perfil['nombre'],
                $perfil['apellidos'],
                $perfil['email'],
                $passwordHash,
                $perfil['telefono'],
                $perfil['fecha_nacimiento'],
                $perfil['nickname'],
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' || (int) ($e->errorInfo[1] ?? 0) === 1062) {
                jsonError('Ya existe una cuenta registrada con ese correo electrónico.', 409);
            }
            throw $e;
        }

        $nuevoUsuarioId = (int) $pdo->lastInsertId();

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $nuevoUsuarioId;

        $usuario = obtenerUsuarioPublico($pdo, $nuevoUsuarioId);

        jsonResponse([
            'success'       => true,
            'mensaje'       => 'Cuenta creada correctamente.',
            'authenticated' => true,
            'usuario'       => $usuario,
        ], 201);
    }

    // 4. ACTUALIZAR DATOS DE PERFIL / CUENTA
    if ($action === 'update_profile' && $method === 'POST') {
        $usuarioId = requireAuthenticatedUserId('Debes iniciar sesión para editar tu perfil.');
        $perfil    = validarCamposPerfil($data);

        $stmtUser = $pdo->prepare('SELECT id, password_hash FROM usuarios WHERE id = ?');
        $stmtUser->execute([$usuarioId]);
        $userRow = $stmtUser->fetch();

        if (!$userRow) {
            clearUserSession();
            jsonError('La sesión ha caducado o el usuario no existe.', 401, ['requiere_autenticacion' => true]);
        }

        // Impedir borrar el nickname si el usuario participa en algún torneo activo
        if ($perfil['nickname'] === null) {
            $stmtTorneoActivo = $pdo->prepare(
                "SELECT 1
                 FROM inscripciones i
                 INNER JOIN actividades a ON i.actividad_id = a.id
                 WHERE i.usuario_id = ?
                   AND i.estado IN ('confirmada', 'lista_espera')
                   AND a.tipo = 'torneo'
                   AND a.fecha_fin >= ?
                 LIMIT 1"
            );
            $stmtTorneoActivo->execute([$usuarioId, date('Y-m-d H:i:s')]);
            if ($stmtTorneoActivo->fetch()) {
                jsonError(
                    'No puedes dejar tu nickname vacío mientras estés inscrito en un torneo activo.',
                    400
                );
            }
        }

        $passwordNueva  = (string) ($data['password_nueva'] ?? '');
        $passwordActual = (string) ($data['password_actual'] ?? '');
        $nuevoHash      = null;

        if ($passwordNueva !== '') {
            if ($passwordActual === '' || !password_verify($passwordActual, $userRow['password_hash'])) {
                jsonError('La contraseña actual no es correcta.', 401);
            }
            if (mb_strlen($passwordNueva) < 6) {
                jsonError('La nueva contraseña debe tener al menos 6 caracteres.', 400);
            }
            $nuevoHash = password_hash($passwordNueva, PASSWORD_DEFAULT);
        }

        try {
            if ($nuevoHash !== null) {
                $stmtUpdate = $pdo->prepare(
                    'UPDATE usuarios
                     SET nombre = ?, apellidos = ?, email = ?, telefono = ?, fecha_nacimiento = ?, nickname = ?, password_hash = ?
                     WHERE id = ?'
                );
                $stmtUpdate->execute([
                    $perfil['nombre'],
                    $perfil['apellidos'],
                    $perfil['email'],
                    $perfil['telefono'],
                    $perfil['fecha_nacimiento'],
                    $perfil['nickname'],
                    $nuevoHash,
                    $usuarioId,
                ]);
            } else {
                $stmtUpdate = $pdo->prepare(
                    'UPDATE usuarios
                     SET nombre = ?, apellidos = ?, email = ?, telefono = ?, fecha_nacimiento = ?, nickname = ?
                     WHERE id = ?'
                );
                $stmtUpdate->execute([
                    $perfil['nombre'],
                    $perfil['apellidos'],
                    $perfil['email'],
                    $perfil['telefono'],
                    $perfil['fecha_nacimiento'],
                    $perfil['nickname'],
                    $usuarioId,
                ]);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' || (int) ($e->errorInfo[1] ?? 0) === 1062) {
                jsonError('Ya existe otra cuenta registrada con ese correo electrónico.', 409);
            }
            throw $e;
        }

        $usuario = obtenerUsuarioPublico($pdo, $usuarioId);

        jsonResponse([
            'success'       => true,
            'mensaje'       => 'Tus datos se han actualizado correctamente.',
            'authenticated' => true,
            'usuario'       => $usuario,
        ]);
    }

    // 5. ELIMINAR CUENTA DE USUARIO
    if ($action === 'delete_account' && $method === 'POST') {
        $usuarioId = requireAuthenticatedUserId('Debes iniciar sesión para eliminar tu cuenta.');
        $password  = (string) ($data['password'] ?? '');

        if ($password === '') {
            jsonError('Introduce tu contraseña actual para confirmar la eliminación de tu cuenta.', 400);
        }

        $pdo->beginTransaction();

        // Identificar actividades confirmadas que quedarán libres para promover listas de espera en orden seguro
        $stmtConfirmadas = $pdo->prepare(
            "SELECT DISTINCT i.actividad_id
             FROM inscripciones i
             INNER JOIN actividades a ON i.actividad_id = a.id
             WHERE i.usuario_id = ?
               AND i.estado = 'confirmada'
               AND a.fecha_fin >= ?
             ORDER BY i.actividad_id ASC"
        );
        $stmtConfirmadas->execute([$usuarioId, date('Y-m-d H:i:s')]);
        $actIdsAfectadas = array_map('intval', $stmtConfirmadas->fetchAll(PDO::FETCH_COLUMN));

        $aforosPorAct = [];
        if (!empty($actIdsAfectadas)) {
            $placeholders = implode(',', array_fill(0, count($actIdsAfectadas), '?'));
            $stmtLockActs = $pdo->prepare(
                "SELECT id, aforo_maximo FROM actividades WHERE id IN ($placeholders) ORDER BY id ASC FOR UPDATE"
            );
            $stmtLockActs->execute($actIdsAfectadas);
            foreach ($stmtLockActs->fetchAll() as $actRow) {
                $aforosPorAct[(int) $actRow['id']] = $actRow['aforo_maximo'] !== null
                    ? (int) $actRow['aforo_maximo']
                    : null;
            }
        }

        $stmtUser = $pdo->prepare('SELECT id, password_hash FROM usuarios WHERE id = ? FOR UPDATE');
        $stmtUser->execute([$usuarioId]);
        $userRow = $stmtUser->fetch();

        if (!$userRow) {
            $pdo->rollBack();
            clearUserSession();
            jsonError('La cuenta ya no existe.', 404, ['authenticated' => false]);
        }

        if (!password_verify($password, $userRow['password_hash'])) {
            $pdo->rollBack();
            jsonError('La contraseña introducida no es correcta.', 401);
        }

        $stmtDelete = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
        $stmtDelete->execute([$usuarioId]);

        foreach ($aforosPorAct as $actId => $aforoMax) {
            promoverSiguienteListaEspera($pdo, $actId, $aforoMax);
        }

        $pdo->commit();
        clearUserSession();

        jsonResponse([
            'success'       => true,
            'authenticated' => false,
            'mensaje'       => 'Tu cuenta ha sido eliminada definitivamente.',
        ]);
    }

    // 6. CERRAR SESIÓN
    if ($action === 'logout' && $method === 'POST') {
        clearUserSession();
        jsonResponse([
            'success'       => true,
            'authenticated' => false,
            'mensaje'       => 'Sesión cerrada correctamente.',
        ]);
    }

    jsonError('Acción o método HTTP no válido.', 400);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Error interno en el servidor de autenticación.', 500);
}

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
        $nombre          = trim((string) ($data['nombre'] ?? ''));
        $apellidos       = trim((string) ($data['apellidos'] ?? ''));
        $email           = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $password        = (string) ($data['password'] ?? '');
        $fechaNacimiento = trim((string) ($data['fecha_nacimiento'] ?? ''));
        $telefonoRaw     = trim((string) ($data['telefono'] ?? ''));
        $telefono        = $telefonoRaw !== '' ? $telefonoRaw : null;
        $nicknameRaw     = trim((string) ($data['nickname'] ?? ''));
        $nickname        = $nicknameRaw !== '' ? $nicknameRaw : null;

        if ($nombre === '' || $apellidos === '' || $email === '' || $password === '' || $fechaNacimiento === '') {
            jsonError(
                'Completa todos los campos obligatorios: nombre, apellidos, email, contraseña y fecha de nacimiento.',
                400
            );
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

        if (mb_strlen($password) < 6) {
            jsonError('La contraseña debe tener al menos 6 caracteres.', 400);
        }

        $fechaObj = DateTimeImmutable::createFromFormat('Y-m-d', $fechaNacimiento);
        $hoy      = new DateTimeImmutable('today');
        $minFecha = new DateTimeImmutable('1900-01-01');

        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fechaNacimiento || $fechaObj > $hoy || $fechaObj < $minFecha) {
            jsonError('La fecha de nacimiento no es válida.', 400);
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmtInsert = $pdo->prepare(
                'INSERT INTO usuarios (nombre, apellidos, email, password_hash, telefono, fecha_nacimiento, nickname)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmtInsert->execute([
                $nombre,
                $apellidos,
                $email,
                $passwordHash,
                $telefono,
                $fechaNacimiento,
                $nickname,
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

    // 4. CERRAR SESIÓN
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
    jsonError('Error interno en el servidor de autenticación.', 500);
}

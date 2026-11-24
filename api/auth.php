<?php
// api/auth.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data =$_POST;
}

$action = trim($_GET['action'] ?? $data['action'] ?? '');
$method =$_SERVER['REQUEST_METHOD'];

/**
 * Obtiene los datos públicos del usuario autenticado sin acoplar sus inscripciones.
 */
function obtenerUsuarioPublico(PDO $pdo, int$usuarioId): ?array
{
    $stmt =$pdo->prepare(
        'SELECT id, nombre, apellidos, email, telefono, fecha_nacimiento, nickname, fecha_registro
         FROM usuarios
         WHERE id = ?'
    );
    $stmt->execute([$usuarioId]);
    $usuario =$stmt->fetch();

    if (!$usuario) {
        return null;
    }

    $usuario['id'] = (int)$usuario['id'];
    return $usuario;
}

try {
    // 1. CONSULTAR ESTADO DE SESIÓN ACTUAL
    if ($action === 'status' &&$method === 'GET') {
        if (empty($_SESSION['usuario_id'])) {
            echo json_encode([
                'success'       => true,
                'authenticated' => false,
                'usuario'       => null
            ]);
            exit;
        }

        $usuario = obtenerUsuarioPublico($pdo, (int)$_SESSION['usuario_id']);

        if (!$usuario) {$_SESSION = [];
            session_destroy();
            echo json_encode([
                'success'       => true,
                'authenticated' => false,
                'usuario'       => null
            ]);
            exit;
        }

        echo json_encode([
            'success'       => true,
            'authenticated' => true,
            'usuario'       => $usuario
        ]);
        exit;
    }

    // 2. INICIAR SESIÓN
    if ($action === 'login' &&$method === 'POST') {
        $email    = mb_strtolower(trim($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' \vert{}\vert{}$password === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Introduce tu correo electrónico y contraseña.'
            ]);
            exit;
        }

        $stmt =$pdo->prepare('SELECT id, password_hash FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        $userRow =$stmt->fetch();

        if (!$userRow || !password_verify($password,$userRow['password_hash'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error'   => 'Correo electrónico o contraseña incorrectos.'
            ]);
            exit;
        }

        session_regenerate_id(true);
        $usuarioId = (int)$userRow['id'];
        $_SESSION['usuario_id'] =$usuarioId;

        $usuario = obtenerUsuarioPublico($pdo,$usuarioId);

        echo json_encode([
            'success'       => true,
            'mensaje'       => 'Sesión iniciada correctamente.',
            'authenticated' => true,
            'usuario'       => $usuario
        ]);
        exit;
    }

    // 3. REGISTRAR NUEVO USUARIO E INICIAR SESIÓN AUTOMÁTICAMENTE
    if ($action === 'register' &&$method === 'POST') {
        $nombre          = trim($data['nombre'] ?? '');
        $apellidos       = trim($data['apellidos'] ?? '');
        $email           = mb_strtolower(trim($data['email'] ?? ''));
        $password        = (string) ($data['password'] ?? '');
        $fechaNacimiento = trim($data['fecha_nacimiento'] ?? '');
        $telefono        = trim($data['telefono'] ?? '') ?: null;

        if ($nombre === '' || $apellidos === '' \vert{}\vert{}$email === '' || $password === '' \vert{}\vert{}$fechaNacimiento === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Completa todos los campos obligatorios: nombre, apellidos, email, contraseña y fecha de nacimiento.'
            ]);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'El formato del correo electrónico no es válido.'
            ]);
            exit;
        }

        if (mb_strlen($password) < 6) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'La contraseña debe tener al menos 6 caracteres.'
            ]);
            exit;
        }

        $fechaObj = DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fechaNacimiento || $fechaObj > new DateTime()) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'La fecha de nacimiento no es válida.'
            ]);
            exit;
        }

        $stmtCheck = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
        $stmtCheck->execute([$email]);
        if ($stmtCheck->fetch()) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error'   => 'Ya existe una cuenta registrada con ese correo electrónico.'
            ]);
            exit;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmtInsert = $pdo->prepare(
            'INSERT INTO usuarios (nombre, apellidos, email, password_hash, telefono, fecha_nacimiento, nickname)
             VALUES (?, ?, ?, ?, ?, ?, NULL)'
        );
        $stmtInsert->execute([
            $nombre,
            $apellidos,
            $email,
            $passwordHash,
            $telefono,
            $fechaNacimiento
        ]);

        $nuevoUsuarioId = (int) $pdo->lastInsertId();

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $nuevoUsuarioId;

        $usuario = obtenerUsuarioPublico($pdo, $nuevoUsuarioId);

        http_response_code(201);
        echo json_encode([
            'success'       => true,
            'mensaje'       => 'Cuenta creada correctamente.',
            'authenticated' => true,
            'usuario'       => $usuario
        ]);
        exit;
    }

    // 4. CERRAR SESIÓN
    if ($action === 'logout' && $method === 'POST') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();

        echo json_encode([
            'success'       => true,
            'authenticated' => false,
            'mensaje'       => 'Sesión cerrada correctamente.'
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Acción o método HTTP no válido.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Error interno en el servidor de autenticación.'
    ]);
}

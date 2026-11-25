<?php
// includes/db.php
require_once __DIR__ . '/config.php';

// Alinear la zona horaria de PHP con la del evento (Madrid)
date_default_timezone_set(defined('SITE_TIMEZONE') ? SITE_TIMEZONE : 'Europe/Madrid');

// 1. Inicializar sesión segura para compartir autenticación entre vistas y api/*.php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!function_exists('isApiRequest')) {
    /**
     * Detecta si la petición actual va dirigida a un endpoint bajo /api/ o espera JSON.
     */
    function isApiRequest(): bool
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        return strpos($script, '/api/') !== false
            || strpos($accept, 'application/json') !== false;
    }
}

if (!function_exists('jsonResponse')) {
    /**
     * Envía una respuesta JSON UTF-8 y finaliza la ejecución.
     */
    function jsonResponse(array $payload, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, private');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('jsonError')) {
    /**
     * Envía una respuesta de error estándar en formato JSON y finaliza.
     */
    function jsonError(string $message, int $statusCode = 400, array $extra = []): void
    {
        jsonResponse(array_merge(['success' => false, 'error' => $message], $extra), $statusCode);
    }
}

if (!function_exists('requireHttpMethod')) {
    /**
     * Valida el método HTTP esperado en un endpoint de API.
     */
    function requireHttpMethod(string $expectedMethod): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== $expectedMethod) {
            jsonError("Método no permitido. Usa {$expectedMethod}.", 405);
        }
    }
}

if (!function_exists('getRequestData')) {
    /**
     * Obtiene el payload de entrada desde JSON (php://input) o $_POST.
     */
    function getRequestData(): array
    {
        $rawInput = file_get_contents('php://input');
        if ($rawInput !== false && $rawInput !== '') {
            $decoded = json_decode($rawInput, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return is_array($_POST) ? $_POST : [];
    }
}

if (!function_exists('clearUserSession')) {
    /**
     * Limpia variables, cookie y almacenamiento de la sesión actual.
     */
    function clearUserSession(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!headers_sent() && ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'] ?? '/',
                    $params['domain'] ?? '',
                    (bool) ($params['secure'] ?? false),
                    (bool) ($params['httponly'] ?? true)
                );
            }
            session_destroy();
        }
    }
}

if (!function_exists('requireAuthenticatedUserId')) {
    /**
     * Exige que exista una sesión iniciada y devuelve el ID de usuario autenticado.
     */
    function requireAuthenticatedUserId(string $message = 'Debes iniciar sesión para realizar esta acción.'): int
    {
        $usuarioId = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$usuarioId || $usuarioId <= 0) {
            jsonError($message, 401, ['requiere_autenticacion' => true]);
        }

        return (int) $usuarioId;
    }
}

if (!function_exists('promoverSiguienteListaEspera')) {
    /**
     * Promueve al siguiente inscrito en lista de espera si existe plaza confirmada disponible.
     * Debe invocarse dentro de una transacción con la fila de actividades bloqueada FOR UPDATE.
     */
    function promoverSiguienteListaEspera(PDO $pdo, int $actividadId, ?int $aforoMaximo): bool
    {
        if ($aforoMaximo !== null) {
            $stmtAforo = $pdo->prepare(
                "SELECT COUNT(*) FROM inscripciones WHERE actividad_id = ? AND estado = 'confirmada'"
            );
            $stmtAforo->execute([$actividadId]);
            $confirmadas = (int) $stmtAforo->fetchColumn();

            if ($confirmadas >= $aforoMaximo) {
                return false;
            }
        }

        $stmtSiguiente = $pdo->prepare(
            "SELECT id
             FROM inscripciones
             WHERE actividad_id = ? AND estado = 'lista_espera'
             ORDER BY fecha_inscripcion ASC, id ASC
             LIMIT 1
             FOR UPDATE"
        );
        $stmtSiguiente->execute([$actividadId]);
        $siguienteEnCola = $stmtSiguiente->fetch();

        if (!$siguienteEnCola) {
            return false;
        }

        $stmtPromover = $pdo->prepare(
            "UPDATE inscripciones SET estado = 'confirmada' WHERE id = ?"
        );
        $stmtPromover->execute([(int) $siguienteEnCola['id']]);

        return true;
    }
}

if (!function_exists('db')) {
    /**
     * Devuelve la conexión PDO a MySQL (Singleton por petición).
     */
    function db(): PDO
    {
        static $pdo = null;

        if ($pdo === null) {
            $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . $charset;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                if (isApiRequest()) {
                    jsonError('Error de conexión con la base de datos.', 500);
                }
                if (!headers_sent()) {
                    http_response_code(500);
                }
                echo 'Error de conexión con la base de datos.';
                exit;
            }
        }

        return $pdo;
    }
}

// 2. Exponer $pdo globalmente para compatibilidad con api/*.php y vistas
$pdo = db();

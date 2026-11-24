<?php
// includes/db.php
require_once __DIR__ . '/config.php';

// 1. Inicializar sesión de forma segura para compartir autenticación entre vistas y API
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Devuelve la conexión PDO a MySQL (patrón Singleton: se crea una sola vez por petición).
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
            http_response_code(500);
            $esApi = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;

            if ($esApi) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error'   => 'Error de conexión con la base de datos.'
                ]);
            } else {
                echo 'Error de conexión con la base de datos.';
            }
            exit;
        }
    }

    return $pdo;
}

// 2. Exponer $pdo globalmente usando la misma instancia singleton para compatibilidad con api/*.php
$pdo = db();

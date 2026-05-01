<?php
/**
 * Single source of truth for the application.
 * Every page should start with: require_once __DIR__ . '/config/config.php';
 * (Adjust the relative path depending on the file's location.)
 */

require_once __DIR__ . '/env.php';

define('APP_NAME',  $SECRETS['app']['name']);
define('APP_URL',   rtrim($SECRETS['app']['url'], '/'));
define('APP_DEBUG', !empty($SECRETS['app']['debug']));
define('APP_ROOT',  dirname(__DIR__));

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

date_default_timezone_set('Africa/Johannesburg');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !APP_DEBUG,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $SECRETS['db']['host'],
        $SECRETS['db']['name'],
        $SECRETS['db']['charset']
    );

    $pdo = new PDO($dsn, $SECRETS['db']['user'], $SECRETS['db']['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die(
        '<h2>Database connection failed</h2>'
        . (APP_DEBUG
            ? '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>'
              . '<p>Check <code>config/secrets.local.php</code> — host/user/pass and that the database <code>autowagen_master</code> exists.</p>'
            : '<p>Please contact the administrator.</p>')
    );
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(?string $token): bool {
    return !empty($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

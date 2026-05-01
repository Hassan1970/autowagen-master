<?php
/**
 * Auto-detects whether we are running on local (Laragon) or live host,
 * then loads the matching secrets file. No passwords live in this file.
 */

$host = $_SERVER['HTTP_HOST'] ?? gethostname();
$isLocal = (bool) preg_match('/(localhost|127\.0\.0\.1|::1|\.test$|\.local$)/i', $host);

define('APP_ENV', $isLocal ? 'local' : 'live');

$secretsFile = __DIR__ . '/secrets.' . APP_ENV . '.php';

if (!is_file($secretsFile)) {
    http_response_code(500);
    die(
        'Configuration error: missing <code>config/secrets.' . htmlspecialchars(APP_ENV) . '.php</code>.<br>'
        . 'Copy <code>secrets.' . htmlspecialchars(APP_ENV) . '.php.example</code> to <code>secrets.' . htmlspecialchars(APP_ENV) . '.php</code> and fill in your credentials.'
    );
}

$SECRETS = require $secretsFile;

if (!is_array($SECRETS) || !isset($SECRETS['db'], $SECRETS['app'])) {
    http_response_code(500);
    die('Configuration error: secrets file must return an array with "db" and "app" keys.');
}

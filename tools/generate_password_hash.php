<?php
/**
 * Laragon Terminal (php on PATH), or full path:
 *   C:\laragon\bin\php\php-*\php.exe tools\generate_password_hash.php YourPasswordHere
 *
 * Prints one bcrypt hash for pasting into MySQL column users.password_hash
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from command line only.\n");
    exit(1);
}

$pwd = $argv[1] ?? '';
if ($pwd === '') {
    fwrite(STDERR, "Usage: php tools/generate_password_hash.php YOUR_PASSWORD\n");
    exit(1);
}

echo password_hash($pwd, PASSWORD_BCRYPT), "\n";

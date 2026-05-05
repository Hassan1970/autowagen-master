<?php
/**
 * One-off: verify DB + user row for login debugging.
 * CLI: php tools/check_login_user.php
 * Optional: php tools/check_login_user.php YourPlainPassword   — tests password_verify only (no output of hash).
 */
declare(strict_types=1);

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once dirname(__DIR__) . '/config/config.php';

$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
echo "Connected database: {$dbName}\n";

$stmt = $pdo->prepare(
    'SELECT id, username, is_active,
            CHAR_LENGTH(password_hash) AS hash_len,
            SUBSTRING(password_hash, 1, 4) AS hash_algo_prefix
     FROM users
     WHERE username = :u
     LIMIT 1'
);
$stmt->execute([':u' => 'hassan']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo "PROBLEM: No user with username exactly 'hassan'.\n";
    $users = $pdo->query('SELECT id, username, is_active FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    echo "Users in this database:\n";
    foreach ($users as $u) {
        echo "  - id {$u['id']}: " . ($u['username'] ?? '') . " (active=" . (int)($u['is_active'] ?? 0) . ")\n";
    }
    exit(1);
}

echo "User hassan: id={$row['id']}, is_active={$row['is_active']}, hash_len={$row['hash_len']}, prefix={$row['hash_algo_prefix']}\n";

if ((int) $row['is_active'] !== 1) {
    echo "PROBLEM: is_active is not 1 — login always fails until you set is_active = 1 in phpMyAdmin.\n";
    exit(1);
}

$len = (int) $row['hash_len'];
$prefix = (string) $row['hash_algo_prefix'];
if ($len < 55 || ($prefix !== '$2y$' && $prefix !== '$2a$')) {
    echo "PROBLEM: password_hash column does not look like bcrypt (want ~60 chars, start \$2y\$ or \$2a\$).\n";
    exit(1);
}

$testPlain = $argv[1] ?? '';
if ($testPlain !== '') {
    $h = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $h->execute([':id' => $row['id']]);
    $hashRow = $h->fetch(PDO::FETCH_ASSOC);
    $hash = (string) ($hashRow['password_hash'] ?? '');
    $ok = $hash !== '' && password_verify($testPlain, $hash);
    echo 'password_verify(plain you passed): ' . ($ok ? "OK\n" : "FAIL — plain password does not match this hash\n");
    exit($ok ? 0 : 1);
}

echo "OK: row looks usable. If browser still fails: clear user_login_attempts, confirm URL is /autowagen-master/auth/login.php, username hassan.\n";
echo "To test a password: php tools/check_login_user.php 'YourPassword'\n";

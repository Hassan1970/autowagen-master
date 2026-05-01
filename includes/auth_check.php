<?php
/**
 * Drop this require at the top of any page that needs login:
 *   require_once __DIR__ . '/config/config.php';
 *   require_once __DIR__ . '/includes/auth_check.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    $next = $_SERVER['REQUEST_URI'] ?? '';
    $loginUrl = (defined('APP_URL') ? APP_URL : '') . '/auth/login.php';
    if ($next !== '') {
        $loginUrl .= '?next=' . urlencode($next);
    }
    header('Location: ' . $loginUrl);
    exit;
}

function current_user(): array {
    return [
        'id'        => $_SESSION['user_id']        ?? null,
        'username'  => $_SESSION['username']       ?? '',
        'full_name' => $_SESSION['full_name']      ?? '',
        'role'      => $_SESSION['role']           ?? 'viewer',
    ];
}

function user_has_role(string ...$roles): bool {
    return in_array($_SESSION['role'] ?? '', $roles, true);
}

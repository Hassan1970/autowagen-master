<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Failed logins (same normalized username + IP) allowed in the rolling window before lockout.
 * The next attempt after this many failures is blocked for 15 minutes (see SQL INTERVAL below).
 */
const LOGIN_MAX_FAILED_IN_WINDOW = 6;

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/main_dashboard.php');
    exit;
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '';
        $ip       = $ip !== '' ? $ip : '0.0.0.0';
        $ua       = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        // One bucket per login name + IP (lowercase) so case variants share the same 6-try limit.
        $attemptKey = mb_strtolower($username, 'UTF-8');

        // Require fields first so we do not count empty submits toward lockout.
        if ($username === '' || $password === '') {
            $error = 'Username and password are required.';
        } else {
            $recent = $pdo->prepare(
                'SELECT COUNT(*) FROM user_login_attempts
                 WHERE ip_address = :ip AND username = :uname AND success = 0
                   AND attempted_at > (NOW() - INTERVAL 15 MINUTE)'
            );
            $recent->execute([':ip' => $ip, ':uname' => $attemptKey]);
            $failed = (int) $recent->fetchColumn();

            if ($failed >= LOGIN_MAX_FAILED_IN_WINDOW) {
                $error = 'Too many failed attempts. Try again in 15 minutes.';
            } else {
                $stmt = $pdo->prepare(
                    'SELECT id, username, full_name, password_hash, role, is_active
                     FROM users WHERE username = :u LIMIT 1'
                );
                $stmt->execute([':u' => $username]);
                $user = $stmt->fetch();

                $ok = $user
                    && (int) $user['is_active'] === 1
                    && password_verify($password, $user['password_hash']);

                $log = $pdo->prepare(
                    'INSERT INTO user_login_attempts (username, ip_address, user_agent, success)
                     VALUES (:u, :ip, :ua, :s)'
                );
                $log->execute([
                    ':u'  => $attemptKey,
                    ':ip' => $ip,
                    ':ua' => $ua,
                    ':s'  => $ok ? 1 : 0,
                ]);

                if ($ok) {
                    // Clear prior failure streak for this account + IP after a good login.
                    $clr = $pdo->prepare(
                        'DELETE FROM user_login_attempts
                         WHERE ip_address = :ip AND username = :uname AND success = 0'
                    );
                    $clr->execute([':ip' => $ip, ':uname' => $attemptKey]);

                    session_regenerate_id(true);
                    $_SESSION['user_id']   = (int) $user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role']      = $user['role'];

                    $upd = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
                    $upd->execute([':id' => $user['id']]);

                    $next = $_GET['next'] ?? ($_POST['next'] ?? '');
                    $safeNext = ($next !== '' && strpos($next, '/') === 0 && strpos($next, '//') !== 0)
                        ? $next
                        : APP_URL . '/main_dashboard.php';

                    header('Location: ' . $safeNext);
                    exit;
                }
                $error = 'Invalid username or password.';
            }
        }
    }
}

$next = $_GET['next'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body {
    background: #0a0a0a;
    color: #f1f1f1;
    min-height: 100vh;
    display: flex;
    align-items: center;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
  }
  .login-card {
    max-width: 420px;
    width: 100%;
    background: #161616;
    border: 1px solid #c8102e;
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(200, 16, 46, 0.15);
  }
  .brand {
    color: #c8102e;
    letter-spacing: 0.18em;
    font-weight: 700;
  }
  .form-control {
    background: #0a0a0a;
    border-color: #333;
    color: #f1f1f1;
  }
  .form-control:focus {
    background: #0a0a0a;
    border-color: #c8102e;
    color: #f1f1f1;
    box-shadow: 0 0 0 0.2rem rgba(200, 16, 46, 0.25);
  }
  .btn-brand {
    background: #c8102e;
    border-color: #c8102e;
    color: #fff;
    font-weight: 600;
    letter-spacing: 0.05em;
  }
  .btn-brand:hover { background: #a00d25; border-color: #a00d25; color: #fff; }
  .footer-note { color: #777; font-size: 0.85rem; }
</style>
</head>
<body>
<div class="container">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
      <div class="login-card p-4 p-md-5">
        <h1 class="h4 brand text-center mb-1"><?= e(APP_NAME) ?></h1>
        <p class="text-center text-secondary mb-4">Sign in to continue</p>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="on" novalidate>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="next" value="<?= e($next) ?>">

          <div class="mb-3">
            <label class="form-label">Username</label>
            <input class="form-control" type="text" name="username"
                   value="<?= e($username) ?>" required autofocus>
          </div>

          <div class="mb-4">
            <label class="form-label">Password</label>
            <input class="form-control" type="password" name="password" required>
          </div>

          <button class="btn btn-brand w-100 py-2" type="submit">Sign in</button>
        </form>

        <p class="text-center footer-note mt-4 mb-0">
          v1.0 &middot; <?= date('Y') ?>
        </p>
      </div>
    </div>
  </div>
</div>
</body>
</html>

<?php
/**
 * Owner/admin: create a dated ZIP of the site (excludes .git and backups folder).
 * Files: autowagen_backup_YYYY-MM-DD_HHMMSS.zip under backups/
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

if (!user_has_role('owner', 'admin')) {
    http_response_code(403);
    $pageTitle = 'Backup';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-danger">Only owner or admin can use site backup.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if (!class_exists('ZipArchive')) {
    $pageTitle = 'Backup';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-danger">PHP <strong>zip</strong> extension is not enabled. In Laragon: enable <code>extension=zip</code> in php.ini and restart Apache.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$backupDir = APP_ROOT . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($backupDir)) {
    if (!@mkdir($backupDir, 0755, true)) {
        $pageTitle = 'Backup';
        require_once __DIR__ . '/includes/header.php';
        echo '<div class="alert alert-danger">Cannot create <code>backups/</code> folder. Check permissions.</div>';
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }
}

$flash = $_SESSION['backup_flash'] ?? ['type' => null, 'msg' => null];
unset($_SESSION['backup_flash']);

$namePattern = '/^autowagen_backup_\d{4}-\d{2}-\d{2}_\d{6}\.zip$/';

// ----- Secure download (before any HTML) -----
if (isset($_GET['download'])) {
    $f = basename((string) $_GET['download']);
    if (!preg_match($namePattern, $f)) {
        http_response_code(400);
        exit('Invalid file name.');
    }
    $path = $backupDir . DIRECTORY_SEPARATOR . $f;
    if (!is_file($path)) {
        http_response_code(404);
        exit('File not found.');
    }
    header('Content-Type: application/zip');
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: attachment; filename="' . $f . '"');
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

// ----- Create backup -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_backup') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $_SESSION['backup_flash'] = ['type' => 'danger', 'msg' => 'Security token invalid. Reload and try again.'];
    } else {
        @set_time_limit(600);
        $dt    = new DateTime('now', new DateTimeZone('Africa/Johannesburg'));
        $stamp = $dt->format('Y-m-d_His');
        $dest  = $backupDir . DIRECTORY_SEPARATOR . "autowagen_backup_{$stamp}.zip";

        $tmp = tempnam(sys_get_temp_dir(), 'awgb');
        if ($tmp === false) {
            $_SESSION['backup_flash'] = ['type' => 'danger', 'msg' => 'Could not create temp file.'];
        } else {
            @unlink($tmp);
            $zip = new ZipArchive();
            if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $_SESSION['backup_flash'] = ['type' => 'danger', 'msg' => 'Could not open ZIP archive for writing.'];
                @unlink($tmp);
            } else {
                $root = realpath(APP_ROOT);
                if ($root === false) {
                    $zip->close();
                    @unlink($tmp);
                    $_SESSION['backup_flash'] = ['type' => 'danger', 'msg' => 'Invalid application root.'];
                } else {
                    $rootNorm = str_replace('\\', '/', $root);
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    $added = 0;
                    foreach ($iterator as $item) {
                        if (!$item->isFile()) {
                            continue;
                        }
                        $full = $item->getRealPath();
                        if ($full === false) {
                            continue;
                        }
                        $fullNorm = str_replace('\\', '/', $full);
                        $rel      = ltrim(substr($fullNorm, strlen($rootNorm)), '/');
                        if ($rel === '' || str_starts_with($rel, 'backups/')) {
                            continue;
                        }
                        if (str_starts_with($rel, '.git/')) {
                            continue;
                        }
                        if (!$zip->addFile($full, str_replace('\\', '/', $rel))) {
                            // continue on single-file failure
                        } else {
                            $added++;
                        }
                    }
                    $zip->close();
                    if ($added === 0 || !is_file($tmp)) {
                        @unlink($tmp);
                        $_SESSION['backup_flash'] = [
                            'type' => 'danger',
                            'msg'  => 'Backup produced no files (permission issue?).',
                        ];
                    } elseif (@rename($tmp, $dest)) {
                        $_SESSION['backup_flash'] = [
                            'type' => 'success',
                            'msg'  => sprintf(
                                'Backup created: %s (%s MB, %d files).',
                                basename($dest),
                                number_format(filesize($dest) / 1048576, 2),
                                $added
                            ),
                        ];
                    } else {
                        @unlink($tmp);
                        $_SESSION['backup_flash'] = ['type' => 'danger', 'msg' => 'Could not move ZIP to backups folder.'];
                    }
                }
            }
        }
    }
    header('Location: ' . APP_URL . '/backups_admin.php');
    exit;
}

$files = glob($backupDir . DIRECTORY_SEPARATOR . 'autowagen_backup_*.zip') ?: [];
usort($files, static function (string $a, string $b): int {
    return filemtime($b) <=> filemtime($a);
});

$pageTitle = 'Site backup';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-3">
  <h1 class="h3 mb-2"><i class="bi bi-archive"></i> Site backup (ZIP)</h1>
  <p class="text-muted small">
    Creates one ZIP of this project folder with a name that includes <strong>date and time</strong>
    (Johannesburg). Excludes the <code>backups/</code> folder (so ZIPs are not nested) and <code>.git/</code>.
  </p>

  <div class="alert alert-danger small">
    <strong>Secrets:</strong> The ZIP includes <code>config/secrets.local.php</code>. Treat downloaded files like passwords — do not email unencrypted to untrusted people; store offline safely.
  </div>

  <?php if ($flash['msg']): ?>
    <div class="alert alert-<?= e($flash['type'] ?? 'info') ?>"><?= e($flash['msg']) ?></div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form method="post" class="d-flex flex-wrap align-items-center gap-3"
            onsubmit="return confirm('Create a full ZIP backup? Large sites may take several minutes.');">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_backup">
        <button type="submit" class="btn btn-danger">
          <i class="bi bi-cloud-arrow-down"></i> Create backup now
        </button>
        <span class="text-muted small">Filename pattern: <code>autowagen_backup_YYYY-MM-DD_HHMMSS.zip</code></span>
      </form>
    </div>
  </div>

  <h2 class="h5 mb-2">Existing backups</h2>
  <?php if (!$files): ?>
    <p class="text-muted">No ZIP files yet. Use <strong>Create backup now</strong> above.</p>
  <?php else: ?>
    <div class="table-responsive card border-0 shadow-sm">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>File</th>
            <th class="text-end">Size</th>
            <th>Created</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($files as $fp):
            $bn = basename($fp);
          ?>
          <tr>
            <td class="font-monospace small"><?= e($bn) ?></td>
            <td class="text-end"><?= number_format(filesize($fp) / 1048576, 2) ?> MB</td>
            <td class="text-muted small"><?= e(date('Y-m-d H:i:s', filemtime($fp))) ?></td>
            <td>
              <a class="btn btn-sm btn-outline-danger" href="<?= e(APP_URL) ?>/backups_admin.php?download=<?= rawurlencode($bn) ?>">
                <i class="bi bi-download"></i> Download
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <p class="small text-muted mt-3 mb-0">
    Restore: unzip on your PC or server, or extract over a copy of the project. Run SQL from phpMyAdmin separately if you need a database snapshot (not included in this ZIP).
  </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$pageTitle = 'Dashboard';
$user = current_user();

$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$lastLogin  = $pdo->prepare(
    'SELECT attempted_at, ip_address FROM user_login_attempts
     WHERE username = :u AND success = 1
     ORDER BY id DESC LIMIT 1 OFFSET 1'
);
$lastLogin->execute([':u' => $user['username']]);
$prev = $lastLogin->fetch();

include __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h3 mb-1">Welcome, <?= e($user['full_name']) ?></h1>
      <p class="text-muted mb-0">
        <?php if ($prev): ?>
          Last sign-in: <?= e($prev['attempted_at']) ?> from <?= e($prev['ip_address']) ?>
        <?php else: ?>
          This is your first sign-in.
        <?php endif; ?>
      </p>
    </div>
    <span class="badge bg-success fs-6 py-2 px-3">
      <i class="bi bi-check-circle"></i> Stages 1–5 live · Stage 6 next
    </span>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-uppercase text-muted small">Users</div>
          <div class="h3 mb-0"><?= $totalUsers ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-uppercase text-muted small">Environment</div>
          <div class="h3 mb-0"><?= e(APP_ENV) ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-uppercase text-muted small">Database</div>
          <div class="h3 mb-0">connected</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-uppercase text-muted small">Your role</div>
          <div class="h3 mb-0 text-capitalize"><?= e($user['role']) ?></div>
        </div>
      </div>
    </div>
  </div>

  <?php if (user_has_role('owner', 'admin')): ?>
  <div class="card border-0 shadow-sm border-danger border-2 mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h2 class="h5 mb-1"><i class="bi bi-archive"></i> Site backup</h2>
        <p class="text-muted small mb-0">Download a dated ZIP of all program files (date + time in the filename).</p>
      </div>
      <a class="btn btn-danger" href="<?= e(APP_URL) ?>/backups_admin.php"><i class="bi bi-cloud-arrow-down"></i> Open backup</a>
    </div>
  </div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <h2 class="h5">What's next</h2>
      <p class="text-muted mb-3">
        Roadmap status (see <code>CLAUDE.md</code> for detail). Stages 1–5 are built;
        use <strong>EPC</strong>, <strong>Master data</strong>, <strong>Inventory</strong>, and <strong>POS</strong> in the top menu.
        Stage 6 adds reports, polish, and optional shop.
      </p>
      <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex justify-content-between">
          <span><strong>Stage 1 — Foundation</strong> (auth, layout, config)</span>
          <span class="badge bg-success">done</span>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span><strong>Stage 2 — EPC</strong> (6-level parts catalogue tree)</span>
          <span class="badge bg-success">done</span>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span><strong>Stage 3 — Master data</strong> (vehicles, customers, suppliers)</span>
          <span class="badge bg-success">done</span>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span><strong>Stage 4 — Inventory</strong> (parts, purchases, accounts payable)</span>
          <span class="badge bg-success">done</span>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span><strong>Stage 5 — POS</strong> (invoices, payments, print)</span>
          <span class="badge bg-success">done</span>
        </li>
        <li class="list-group-item d-flex justify-content-between">
          <span><strong>Stage 6 — Reports &amp; shop</strong></span>
          <span class="badge bg-warning text-dark">next</span>
        </li>
      </ul>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

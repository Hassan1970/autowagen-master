<?php
/**
 * Stage 3 / 3b — Vehicles list / search / paginate.
 *
 * Owner / admin / manager can edit and toggle active.
 * Staff / viewer is read-only (no Add button, no toggle).
 *
 * Add and Edit live on vehicle_edit.php so the EPC link picker, the
 * legal-paper uploader and the photo gallery share the same screen.
 *
 * Search matches stock_code, make, model, plate, VIN (case-insensitive,
 * partial). Optional status filter via ?status=intake / stripping / etc.
 * Pagination is fixed at 50 rows / page (Stage-3 convention).
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$canEdit = user_has_role('owner', 'admin', 'manager');

$STATUS_OPTIONS = [
    'intake'      => 'Intake',
    'stripping'   => 'Being stripped',
    'stripped'    => 'Stripped',
    'scrapped'    => 'Scrapped',
    'shell_sold'  => 'Shell sold',
];
$STATUS_BADGE = [
    'intake'     => 'bg-info text-dark',
    'stripping'  => 'bg-warning text-dark',
    'stripped'   => 'bg-secondary',
    'scrapped'   => 'bg-dark',
    'shell_sold' => 'bg-success',
];

$flash = ['type' => null, 'msg' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        http_response_code(403);
        $flash = ['type' => 'danger', 'msg' => 'You do not have permission to change vehicles.'];
    } elseif (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = ['type' => 'danger', 'msg' => 'Security token invalid. Please reload the page.'];
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int) ($_POST['id'] ?? 0);
        try {
            if ($action === 'toggle_active' && $id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE vehicles SET is_active = 1 - is_active WHERE id = :id'
                );
                $stmt->execute([':id' => $id]);
                $flash = ['type' => 'success', 'msg' => 'Vehicle active state toggled.'];
            } else {
                throw new RuntimeException('Unknown action.');
            }
        } catch (Throwable $e) {
            $flash = ['type' => 'danger', 'msg' => APP_DEBUG ? $e->getMessage() : 'Database error.'];
        }
    }
}

// ---------- search + filter + pagination ----------
$q          = trim((string) ($_GET['q']      ?? ''));
$statusF    = (string) ($_GET['status']      ?? '');
if ($statusF !== '' && !array_key_exists($statusF, $STATUS_OPTIONS)) {
    $statusF = '';
}
$showInact  = !empty($_GET['inactive']);
$page       = max(1, (int) ($_GET['page']    ?? 1));
$perPage    = 50;
$offset     = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(stock_code LIKE :q OR make LIKE :q OR model LIKE :q OR plate LIKE :q OR vin LIKE :q OR yard_location LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($statusF !== '') {
    $where[] = 'status = :status';
    $params[':status'] = $statusF;
}
if (!$showInact) {
    $where[] = 'is_active = 1';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles {$whereSql}");
$totalStmt->execute($params);
$total = (int) $totalStmt->fetchColumn();

$listSql = "SELECT id, stock_code, make, model, year, plate, vin, colour, mileage,
                   status, has_logbook, has_sellers_receipt, has_seller_id_copy,
                   has_proof_of_residence,
                   yard_location, is_active, updated_at
            FROM vehicles {$whereSql}
            ORDER BY is_active DESC,
                     COALESCE(stock_code, '') ASC,
                     make ASC, model ASC, year DESC
            LIMIT {$perPage} OFFSET {$offset}";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

$pageTitle = 'Vehicles';
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-1"><i class="bi bi-truck"></i> Vehicles</h1>
      <div class="text-muted small">
        <?= number_format($total) ?> result<?= $total === 1 ? '' : 's' ?>
        <?php if ($q !== ''): ?>for "<strong><?= e($q) ?></strong>"<?php endif; ?>
        <?php if ($statusF !== ''): ?> &middot; status <strong><?= e($STATUS_OPTIONS[$statusF]) ?></strong><?php endif; ?>
        <?php if ($showInact): ?> &middot; including inactive<?php endif; ?>
      </div>
    </div>
    <div>
      <?php if ($canEdit): ?>
        <a class="btn btn-sm btn-danger" href="<?= e(APP_URL) ?>/vehicle_edit.php">
          <i class="bi bi-plus-lg"></i> Add vehicle
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($flash['msg']): ?>
    <div class="alert alert-<?= e($flash['type']) ?> py-2"><?= e($flash['msg']) ?></div>
  <?php endif; ?>

  <form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
      <input type="text" name="q" value="<?= e($q) ?>"
             class="form-control form-control-sm"
             placeholder="Search stock code / make / model / plate / VIN / yard&hellip;">
    </div>
    <div class="col-md-3">
      <select class="form-select form-select-sm" name="status">
        <option value="">All statuses</option>
        <?php foreach ($STATUS_OPTIONS as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $statusF === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <div class="form-check form-check-inline mt-1">
        <input class="form-check-input" type="checkbox" id="inactive" name="inactive" value="1"
               <?= $showInact ? 'checked' : '' ?>>
        <label class="form-check-label small" for="inactive">Show inactive</label>
      </div>
    </div>
    <div class="col-md-3 text-end">
      <button class="btn btn-sm btn-outline-dark" type="submit">
        <i class="bi bi-search"></i> Search
      </button>
      <?php if ($q !== '' || $showInact || $statusF !== ''): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(APP_URL) ?>/vehicles_admin.php">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Stock</th>
            <th>Make / Model</th>
            <th>Year</th>
            <th>Status</th>
            <th>Papers</th>
            <th>Yard</th>
            <th>Plate</th>
            <th>VIN</th>
            <th class="text-end">Mileage</th>
            <th>Active</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr>
              <td colspan="11" class="text-center text-muted py-4">
                No vehicles match.
                <?php if ($canEdit): ?>
                  <a href="<?= e(APP_URL) ?>/vehicle_edit.php">Add the first one</a>.
                <?php endif; ?>
              </td>
            </tr>
          <?php else: foreach ($rows as $v):
            $papersCount = (int) $v['has_logbook']
                         + (int) $v['has_sellers_receipt']
                         + (int) $v['has_seller_id_copy']
                         + (int) $v['has_proof_of_residence'];
            $papersOk    = $papersCount === 4;
            $sBadge      = $STATUS_BADGE[$v['status']] ?? 'bg-light text-dark';
            $sLabel      = $STATUS_OPTIONS[$v['status']] ?? ucfirst((string) $v['status']);
          ?>
            <tr class="<?= $v['is_active'] ? '' : 'text-muted' ?>">
              <td class="font-monospace small">
                <?= e($v['stock_code'] ?: '—') ?>
              </td>
              <td>
                <strong><?= e($v['make']) ?></strong> <?= e($v['model']) ?>
                <?php if (!empty($v['colour'])): ?>
                  <div class="small text-muted"><?= e($v['colour']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= e((string) $v['year']) ?></td>
              <td><span class="badge <?= e($sBadge) ?>"><?= e($sLabel) ?></span></td>
              <td>
                <?php if ($papersOk): ?>
                  <span class="badge bg-success" title="Log book + receipt + seller ID + proof of residence all on file">
                    <i class="bi bi-shield-check"></i> 4/4
                  </span>
                <?php else: ?>
                  <span class="badge bg-danger"
                        title="Missing: <?= e(implode(', ', array_filter([
                          $v['has_logbook']             ? null : 'log book',
                          $v['has_sellers_receipt']     ? null : 'receipt',
                          $v['has_seller_id_copy']      ? null : 'seller ID',
                          $v['has_proof_of_residence']  ? null : 'proof of residence',
                        ]))) ?>">
                    <i class="bi bi-shield-exclamation"></i> <?= $papersCount ?>/4
                  </span>
                <?php endif; ?>
              </td>
              <td class="small"><?= e($v['yard_location'] ?: '—') ?></td>
              <td><?= e($v['plate'] ?: '—') ?></td>
              <td class="font-monospace small"><?= e($v['vin'] ?: '—') ?></td>
              <td class="text-end">
                <?= $v['mileage'] !== null
                    ? number_format((int) $v['mileage']) . ' km'
                    : '<span class="text-muted small">—</span>' ?>
              </td>
              <td>
                <?php if ($v['is_active']): ?>
                  <span class="badge bg-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Inactive</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-dark"
                   href="<?= e(APP_URL) ?>/vehicle_edit.php?id=<?= (int) $v['id'] ?>"
                   title="<?= $canEdit ? 'Edit' : 'View' ?>">
                  <i class="bi bi-<?= $canEdit ? 'pencil' : 'eye' ?>"></i>
                </a>
                <?php if ($canEdit): ?>
                  <form method="post" class="d-inline"
                        onsubmit="return confirm('<?= $v['is_active'] ? 'Deactivate' : 'Activate' ?> this vehicle?');">
                    <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="id"     value="<?= (int) $v['id'] ?>">
                    <button class="btn btn-sm btn-outline-secondary" type="submit"
                            title="<?= $v['is_active'] ? 'Deactivate' : 'Activate' ?>">
                      <i class="bi bi-power"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
      <ul class="pagination pagination-sm">
        <?php
          $base = '?' . http_build_query(array_filter([
            'q'        => $q !== '' ? $q : null,
            'status'   => $statusF !== '' ? $statusF : null,
            'inactive' => $showInact ? 1 : null,
          ]));
          $sep  = $base === '?' ? '' : '&';
          for ($p = 1; $p <= $totalPages; $p++):
        ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= e($base . $sep) ?>page=<?= $p ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

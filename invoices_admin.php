<?php
/**
 * Stage 5 — List sales invoices (POS).
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$pageTitle = 'Sales invoices';

if (!(int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE table_schema = DATABASE() AND table_name = 'sales_invoices'"
)->fetchColumn()) {
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-warning"><strong>Stage 5 tables missing.</strong> Run '
        . '<code>sql/05_pos.sql</code> in phpMyAdmin on <code>autowagen_master</code>, then refresh this page.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$status = preg_replace('/[^a-z]/', '', strtolower((string) ($_GET['status'] ?? '')));
if (!in_array($status, ['', 'draft', 'final', 'void'], true)) {
    $status = '';
}

$page = max(1, (int) ($_GET['p'] ?? 1));
$per  = 50;
$off  = ($page - 1) * $per;

$where = '1=1';
$params = [];
if ($status !== '') {
    $where .= ' AND i.status = :st';
    $params[':st'] = $status;
}

$countSt = $pdo->prepare("SELECT COUNT(*) FROM sales_invoices i WHERE i.is_active = 1 AND $where");
$countSt->execute($params);
$totalRows = (int) $countSt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $per));
if ($page > $totalPages) {
    $page = $totalPages;
    $off = ($page - 1) * $per;
}

$sql = "SELECT i.*, c.name AS customer_name
        FROM sales_invoices i
        LEFT JOIN customers c ON c.id = i.customer_id
        WHERE i.is_active = 1 AND $where
        ORDER BY i.id DESC
        LIMIT " . (int) $per . " OFFSET " . (int) $off;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0"><i class="bi bi-receipt"></i> Sales invoices</h1>
  <?php if (user_has_role('owner', 'admin', 'manager', 'staff')): ?>
    <a class="btn btn-danger" href="<?= e(APP_URL) ?>/invoice_edit.php?new=1"><i class="bi bi-plus-lg"></i> New sale</a>
  <?php endif; ?>
</div>

<form method="get" class="row g-2 mb-3 align-items-end">
  <div class="col-auto">
    <label class="form-label small mb-0">Status</label>
    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="" <?= $status === '' ? 'selected' : '' ?>>All</option>
      <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
      <option value="final" <?= $status === 'final' ? 'selected' : '' ?>>Final</option>
      <option value="void" <?= $status === 'void' ? 'selected' : '' ?>>Void</option>
    </select>
  </div>
</form>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light small">
        <tr>
          <th>#</th>
          <th>Invoice no.</th>
          <th>Customer</th>
          <th>Date</th>
          <th>Status</th>
          <th class="text-end">Total (incl. VAT)</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="text-muted py-4 text-center">No invoices yet. Start with <strong>New sale</strong>.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int) $r['id'] ?></td>
              <td><?= $r['invoice_no'] ? e($r['invoice_no']) : '<span class="text-muted">— draft —</span>' ?></td>
              <td><?= $r['customer_name'] ? e($r['customer_name']) : '—' ?></td>
              <td><?= e((string) $r['invoice_date']) ?></td>
              <td>
                <?php
                $st = $r['status'];
                $cls = $st === 'final' ? 'success' : ($st === 'draft' ? 'secondary' : 'dark');
                ?>
                <span class="badge bg-<?= e($cls) ?>"><?= e($st) ?></span>
              </td>
              <td class="text-end">R <?= number_format((float) $r['total_inc_vat'], 2) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL) ?>/invoice_edit.php?id=<?= (int) $r['id'] ?>">Open</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
  <nav class="mt-3">
    <ul class="pagination pagination-sm mb-0">
      <?php
      $qs = $status !== '' ? '&amp;status=' . rawurlencode($status) : '';
      for ($p = 1; $p <= $totalPages; $p++):
        $active = $p === $page ? ' active' : '';
        ?>
        <li class="page-item<?= $active ?>"><a class="page-link" href="?p=<?= $p ?><?= $qs ?>"><?= $p ?></a></li>
      <?php endfor; ?>
    </ul>
  </nav>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php';

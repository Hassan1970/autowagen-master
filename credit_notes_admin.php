<?php
/**
 * Stage 7 — Credit notes list (linked to POS invoices).
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/credit_note_helpers.php';

$pageTitle = 'Credit notes';
$canEdit   = user_has_role('owner', 'admin', 'manager', 'staff');

if (!cn_tables_ready($pdo)) {
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-warning"><strong>Credit notes tables missing.</strong> Run '
        . '<code>sql/07_credit_notes.sql</code> in phpMyAdmin, then refresh.</div>';
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

$where  = '1=1';
$params = [];
if ($status !== '') {
    $where          .= ' AND cn.status = :st';
    $params[':st']   = $status;
}

$cSt = $pdo->prepare(
    "SELECT COUNT(*) FROM sales_credit_notes cn WHERE cn.is_active = 1 AND $where"
);
$cSt->execute($params);
$totalRows = (int) $cSt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $per));
if ($page > $totalPages) {
    $page = $totalPages;
    $off  = ($page - 1) * $per;
}

$sql =
    'SELECT cn.*,
            i.invoice_no,
            COALESCE(c.name, \'—\') AS customer_name
     FROM sales_credit_notes cn
     INNER JOIN sales_invoices i ON i.id = cn.invoice_id
     LEFT JOIN customers c ON c.id = cn.customer_id
     WHERE cn.is_active = 1 AND ' . $where . '
     ORDER BY cn.id DESC
     LIMIT ' . (int) $per . ' OFFSET ' . (int) $off;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0"><i class="bi bi-arrow-counterclockwise"></i> Credit notes</h1>
  <?php if ($canEdit): ?>
    <a class="btn btn-outline-danger btn-sm" href="<?= e(APP_URL) ?>/credit_notes_admin.php#create">
      <i class="bi bi-plus-lg"></i> New credit note
    </a>
  <?php endif; ?>
</div>

<div class="alert alert-light border mb-3 small mb-4">
  Credit notes reduce what the buyer owes against the linked <strong>INV‑…</strong> invoice / account and (for part lines) <strong>put stock back when finalized</strong>.
  Choose <strong>AR reduction</strong> vs <strong>Cash refund</strong> before finalizing.
</div>

<?php if ($canEdit): ?>
<div id="create" class="card shadow-sm mb-4 border-danger border-2 no-print">
  <div class="card-header bg-dark text-white"><strong>Start credit note</strong></div>
  <div class="card-body row g-2 align-items-end">
    <div class="col-md-10">
      <label class="form-label small mb-1">Invoice # (paste id or INV number — use dropdown from recent finals)</label>
      <form method="get" class="d-flex flex-wrap gap-2" action="<?= e(APP_URL) ?>/credit_note_edit.php">
        <input type="hidden" name="new" value="1">
        <select name="invoice_id" class="form-select form-select-sm" style="max-width:28rem" required>
          <option value="">— choose final invoice —</option>
          <?php
            $recent = $pdo->query(
                "SELECT id, invoice_no, invoice_date, total_inc_vat, customer_id
                 FROM sales_invoices
                 WHERE status = 'final' AND invoice_no IS NOT NULL AND is_active = 1
                 ORDER BY id DESC LIMIT 200"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($recent as $ri):
                $label = '#' . $ri['id'] . ' · ' . $ri['invoice_no'] . ' · ' . $ri['invoice_date']
                    . ' · R ' . number_format((float) $ri['total_inc_vat'], 2);
                ?>
              <option value="<?= (int) $ri['id'] ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-danger btn-sm">Create draft</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<form method="get" class="row g-2 mb-3 align-items-end no-print">
  <input type="hidden" name="p" value="1">
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
          <th>Credit no.</th>
          <th>Linked invoice</th>
          <th>Customer</th>
          <th>Date</th>
          <th>Type</th>
          <th>Status</th>
          <th class="text-end">Total (incl. VAT)</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="9" class="text-muted py-4 text-center">No credit notes yet.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int) $r['id'] ?></td>
              <td><?= $r['credit_no'] ? '<span class="font-monospace">' . e((string) $r['credit_no']) . '</span>' : '<span class="text-muted">draft</span>' ?></td>
              <td><span class="font-monospace"><?= e((string) ($r['invoice_no'] ?? '')) ?></span></td>
              <td><?= e((string) $r['customer_name']) ?></td>
              <td><?= e((string) $r['credit_date']) ?></td>
              <td><?= ($r['adjustment_type'] ?? '') === 'cash_refund' ? 'Cash refund' : 'AR reduction' ?></td>
              <td>
                <?php
                  $st = $r['status'];
                  $cls = $st === 'final' ? 'success' : ($st === 'draft' ? 'secondary' : 'dark');
                  ?>
                <span class="badge bg-<?= e($cls) ?>"><?= e($st) ?></span>
              </td>
              <td class="text-end">R <?= number_format((float) $r['total_inc_vat'], 2) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary"
                   href="<?= e(APP_URL) ?>/credit_note_edit.php?id=<?= (int) $r['id'] ?>">Open</a>
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
    <ul class="pagination pagination-sm">
      <?php for ($pi = 1; $pi <= $totalPages; $pi++):
        $qp = $_GET;
        $qp['p'] = $pi;
        $url = APP_URL . '/credit_notes_admin.php?' . http_build_query($qp);
        ?>
        <li class="page-item <?= $pi === $page ? 'active' : '' ?>">
          <a class="page-link" href="<?= e($url) ?>"><?= $pi ?></a></li>
      <?php endfor; ?>
    </ul>
  </nav>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Stage 4d — Supplier / private-seller accounts payable (outstanding balances, ZAR).
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$apReady = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.`TABLES`
     WHERE table_schema = DATABASE() AND table_name = 'supplier_purchase_payments'"
)->fetchColumn() > 0
    && (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.`COLUMNS`
         WHERE table_schema = DATABASE() AND table_name = 'supplier_purchases' AND column_name = 'bill_amount'"
    )->fetchColumn() > 0;

$asOf = trim((string) ($_GET['as_of'] ?? ''));
if ($asOf === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) {
    $asOf = (new DateTime('now', new DateTimeZone('Africa/Johannesburg')))->format('Y-m-d');
}

$rows = [];
$totalOwed = 0.0;
if ($apReady) {
    $sql = "
    SELECT
      sp.id,
      sp.purchase_ref,
      sp.bill_amount,
      sp.bill_date,
      sp.due_date,
      sp.supplier_id,
      sp.seller_name,
      s.name AS supplier_name,
      COALESCE(pay.paid_sum, 0) AS paid_sum
    FROM supplier_purchases sp
    LEFT JOIN suppliers s ON s.id = sp.supplier_id
    LEFT JOIN (
      SELECT supplier_purchase_id, SUM(amount) AS paid_sum
      FROM supplier_purchase_payments
      WHERE is_active = 1
      GROUP BY supplier_purchase_id
    ) pay ON pay.supplier_purchase_id = sp.id
    WHERE sp.is_active = 1
      AND sp.bill_amount IS NOT NULL
      AND sp.bill_amount > 0
    ";
    $stmt = $pdo->query($sql);
    $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($raw as $r) {
        $bal = (float) $r['bill_amount'] - (float) $r['paid_sum'];
        if ($bal > 0.005) {
            $r['balance']     = $bal;
            $r['status']      = (float) $r['paid_sum'] < 0.005 ? 'unpaid' : 'partial';
            $rows[]           = $r;
            $totalOwed        += $bal;
        }
    }
    // Sort: due date nulls last, then by balance desc
    usort($rows, static function (array $a, array $b): int {
        $da = $a['due_date'] ?? null;
        $db = $b['due_date'] ?? null;
        if ($da === null && $db === null) {
            return (int) (($b['balance'] <=> $a['balance']));
        }
        if ($da === null) {
            return 1;
        }
        if ($db === null) {
            return -1;
        }
        return $da <=> $db;
    });
}

$grouped = [];
foreach ($rows as $r) {
    if (!empty($r['supplier_id'])) {
        $k = 's:' . (int) $r['supplier_id'];
        $name = (string) $r['supplier_name'];
    } else {
        $k = 'p:' . (int) $r['id'];
        $name = 'Private: ' . ($r['seller_name'] ? (string) $r['seller_name'] : '—');
    }
    if (!isset($grouped[$k])) {
        $grouped[$k] = ['name' => $name, 'total' => 0.0, 'rows' => []];
    }
    $grouped[$k]['total']  += (float) $r['balance'];
    $grouped[$k]['rows'][] = $r;
}

$pageTitle = 'Supplier payables (owed)';
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h1 class="h4 mb-0">
        <i class="bi bi-cash-coin"></i> Accounts payable — who we still owe
      </h1>
      <p class="text-muted small mb-0">ZAR only. Set bill amounts and record payments on each purchase. Private sellers included.</p>
    </div>
    <div class="d-flex flex-wrap align-items-end gap-2">
      <form method="get" class="d-flex align-items-end gap-2">
        <div>
          <label class="form-label small mb-0">As at (report date)</label>
          <input type="date" name="as_of" class="form-control form-control-sm" value="<?= e($asOf) ?>" title="For your records; all open balances are current">
        </div>
        <button class="btn btn-sm btn-outline-dark" type="submit">Apply</button>
      </form>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e(APP_URL) ?>/supplier_purchases_admin.php">Supplier purchases</a>
    </div>
  </div>

  <?php if (!$apReady): ?>
    <div class="alert alert-warning">
      Run <code>sql/04d_supplier_accounts_payable.sql</code> in phpMyAdmin (database <code>autowagen_master</code>) to enable this report.
    </div>
  <?php elseif (!$rows): ?>
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center text-muted py-5">
        <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
        <strong>Nothing outstanding</strong> — no purchase has a positive balance, or no bill amounts are set.
      </div>
    </div>
  <?php else: ?>
    <div class="card border-0 shadow-sm border-danger border-2 mb-3">
      <div class="card-body py-2 d-flex flex-wrap align-items-center justify-content-between">
        <span class="small"><strong>Total still owed (all rows below):</strong></span>
        <span class="h5 mb-0 text-danger">R <?= number_format($totalOwed, 2) ?></span>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th>Purchase</th>
              <th>Owed to</th>
              <th class="text-end">Bill (ZAR)</th>
              <th class="text-end">Paid so far</th>
              <th class="text-end">Balance</th>
              <th>Status</th>
              <th>Due</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r):
              $st = $r['status'] === 'unpaid'
                  ? '<span class="badge bg-danger">Unpaid</span>'
                  : '<span class="badge bg-warning text-dark">Part paid</span>';
            ?>
            <tr>
              <td class="font-monospace">#<?= (int) $r['id'] ?>
                <?php if (!empty($r['purchase_ref'])): ?>
                  <span class="text-muted">· <?= e($r['purchase_ref']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($r['supplier_id'])): ?>
                  <span class="badge bg-light text-dark border"><?= e($r['supplier_name']) ?></span>
                <?php else: ?>
                  <i class="bi bi-person"></i> <?= e($r['seller_name'] ?: '—') ?>
                <?php endif; ?>
              </td>
              <td class="text-end">R <?= number_format((float) $r['bill_amount'], 2) ?></td>
              <td class="text-end">R <?= number_format((float) $r['paid_sum'], 2) ?></td>
              <td class="text-end fw-bold">R <?= number_format((float) $r['balance'], 2) ?></td>
              <td><?= $st ?></td>
              <td class="text-muted"><?= $r['due_date'] ? e($r['due_date']) : '—' ?></td>
              <td>
                <a class="btn btn-sm btn-outline-dark" href="<?= e(APP_URL) ?>/supplier_purchase_edit.php?id=<?= (int) $r['id'] ?>">Open</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <h2 class="h6 mt-4 mb-2 text-muted">Summary by who we owe</h2>
    <div class="row g-2">
      <?php foreach ($grouped as $g): ?>
        <div class="col-md-4">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2 small">
              <div class="fw-bold"><?= e($g['name']) ?></div>
              <div class="text-end text-danger">R <?= number_format($g['total'], 2) ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

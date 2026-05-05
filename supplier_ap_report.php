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

<style>
  .aw-ap-print-title { border-bottom: 3px solid #c8102e; padding-bottom: 0.5rem; margin-bottom: 1rem; }
  .aw-ap-print-title h1 { color: #0a0a0a; font-size: 1.25rem; font-weight: 700; letter-spacing: .08em; }
  .aw-ap-print-meta { color: #444; font-size: 0.85rem; }
  @media print {
    @page { margin: 12mm; size: A4; }
    body { background: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    nav.navbar, .navbar-aw, footer, .no-print { display: none !important; }
    main.container-fluid { padding: 0 !important; max-width: 100% !important; }
    .aw-ap-print-title h1 { color: #000; }
    .table { font-size: 9pt; }
    .badge { border: 1px solid #333; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    .card { border: 1px solid #ccc !important; box-shadow: none !important; }
  }
</style>

<div class="container-fluid py-3 aw-ap-report">
  <div class="aw-ap-print-title">
    <h1 class="mb-1"><span style="color:#c8102e;"><?= e(APP_NAME) ?></span> — Accounts payable (who we owe)</h1>
    <div class="aw-ap-print-meta">
      Report date (as at) <strong><?= e($asOf) ?></strong> &middot; <?= e(APP_NAME) ?> &middot; ZAR
    </div>
  </div>

  <?php if ($apReady && $rows): ?>
    <div class="alert alert-light border small mb-3 no-print">
      <div class="fw-semibold mb-2 text-dark">How to read this report</div>
      <ul class="mb-0 ps-3">
        <li class="mb-2"><strong>A — Top total &amp; date</strong> — The red banner shows the <strong>grand total</strong> you still owe suppliers/private sellers (sum of every row below). Use <strong>As at (report date)</strong> for your printout’s “as at” stamp; money amounts are always <strong>today’s open balances</strong>, not a historical snapshot.</li>
        <li class="mb-2"><strong>B — Main table (each purchase)</strong> — One row per <strong>supplier batch purchase</strong> that still has money owing. <strong>Bill</strong> = what you owe on that purchase. <strong>Paid so far</strong> = payments you recorded. <strong>Balance</strong> = Bill minus paid (what is still left to pay).</li>
        <li class="mb-2"><strong>C — Status &amp; due</strong> — <strong>Unpaid</strong> = nothing recorded against the bill yet. <strong>Part paid</strong> = some payment lines exist but balance remains. <strong>Due</strong> shows the purchase’s due date when you set it on the purchase; blank (—) means no due date saved.</li>
        <li><strong>D — Summary blocks</strong> — “Summary by who we owe” adds up balances <strong>per supplier</strong> (or per private seller). It should match the detail rows above for that name.</li>
      </ul>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 no-print">
    <div>
      <p class="text-muted small mb-0">
        ZAR only. Bills and payments are edited on each purchase. This is <strong>accounts payable</strong>
        (money <strong>you owe</strong>), not customer <strong>accounts receivable</strong>.
      </p>
    </div>
    <div class="d-flex flex-wrap align-items-end gap-2">
      <form method="get" class="d-flex align-items-end gap-2">
        <div>
          <label class="form-label small mb-0">As at (report date)</label>
          <input type="date" name="as_of" class="form-control form-control-sm" value="<?= e($asOf) ?>" title="For your records; all open balances are current">
        </div>
        <button class="btn btn-sm btn-outline-dark" type="submit">Apply</button>
      </form>
      <button type="button" class="btn btn-sm btn-danger" onclick="window.print();">
        <i class="bi bi-printer"></i> Print / PDF
      </button>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e(APP_URL) ?>/supplier_purchases_admin.php">Supplier purchases</a>
      <a class="btn btn-sm btn-outline-dark" target="_blank" rel="noopener" href="<?= e(APP_URL) ?>/docs/supplier_ap_report_explained_print.html" title="Opens help page — use Ctrl+P to save as PDF">
        <i class="bi bi-journal-text"></i> What A–D means
      </a>
    </div>
  </div>

  <?php if (!$apReady): ?>
    <div class="alert alert-warning no-print">
      Run <code>sql/04d_supplier_accounts_payable.sql</code> in phpMyAdmin (database <code>autowagen_master</code>) to enable this report.
    </div>
  <?php elseif (!$rows): ?>
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center text-muted py-5">
        <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
        <strong>Nothing outstanding</strong> — no purchase has a positive balance, or no bill amounts are set.
      </div>
    </div>
    <p class="small text-muted no-print mb-0 mt-2">
      Tip: open <strong>Inventory → Supplier purchases</strong>, set <strong>Bill</strong> on each purchase, then record <strong>Payments</strong> until balance is zero.
    </p>
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
              <th class="no-print"></th>
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
              <td class="no-print">
                <a class="btn btn-sm btn-outline-dark" href="<?= e(APP_URL) ?>/supplier_purchase_edit.php?id=<?= (int) $r['id'] ?>">Open</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <p class="small text-muted no-print mb-0 mt-3">
      <strong>Print / PDF:</strong> click <strong>Print / PDF</strong> above — in the dialog choose your printer or <strong>Save as PDF</strong>. If the red bar fades, enable <strong>Background graphics</strong> in print options.
    </p>

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

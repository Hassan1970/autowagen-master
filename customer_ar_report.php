<?php
/**
 * Stage 6 — Accounts receivable: balances by customer and by invoice;
 * overdue = unpaid balance on a final invoice whose due_date is before the report "As at" date.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/credit_note_helpers.php';

$arReady = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.`TABLES`
     WHERE table_schema = DATABASE() AND table_name = 'sales_invoice_payments'"
)->fetchColumn() > 0;

$accountCols = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.`COLUMNS`
     WHERE table_schema = DATABASE() AND table_name = 'customers' AND column_name = 'account_customer'"
)->fetchColumn() > 0;

$asOf = trim((string) ($_GET['as_of'] ?? ''));
if ($asOf === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) {
    $asOf = (new DateTime('now', new DateTimeZone('Africa/Johannesburg')))->format('Y-m-d');
}

$acctOnly   = !empty($_GET['account_only']) && $accountCols;
$overdueOnly = !empty($_GET['overdue_only']);

/** Whether due_date from DB should be used (excludes legacy zero dates; matches strict MySQL). */
function ar_due_is_meaningful(?string $d): bool {
    if ($d === null || $d === '') {
        return false;
    }
    $d = trim($d);
    if ($d === '0000-00-00' || strncmp($d, '0000-', 5) === 0) {
        return false;
    }
    return true;
}

$rows           = [];
$totalOwed      = 0.0;
$totalOverdue   = 0.0;
$invoiceRows    = [];

$cnReady = cn_tables_ready($pdo);
$cnJoin       = '';
$netBalExpr   = 'i.total_inc_vat - COALESCE(pay.paid_sum, 0)';
$custCredCols = '';
if ($cnReady) {
    $cnJoin =
        "\n         LEFT JOIN (\n"
        . "          SELECT invoice_id,\n"
        . "            COALESCE(SUM(CASE WHEN adjustment_type = 'ar_reduction' THEN total_inc_vat ELSE 0 END), 0) AS cn_ar_sum,\n"
        . "            COALESCE(SUM(CASE WHEN adjustment_type = 'cash_refund' THEN total_inc_vat ELSE 0 END), 0) AS cn_refund_sum\n"
        . "          FROM sales_credit_notes WHERE status = 'final' AND is_active = 1\n"
        . "          GROUP BY invoice_id\n"
        . "        ) cn ON cn.invoice_id = i.id\n";
    $netBalExpr .= ' - COALESCE(cn.cn_ar_sum, 0) - COALESCE(cn.cn_refund_sum, 0)';
    $custCredCols = ",\n      SUM(COALESCE(cn.cn_ar_sum, 0)) AS credits_ar_reduction,\n"
        . "      SUM(COALESCE(cn.cn_refund_sum, 0)) AS credits_cash_refund";
}

if ($arReady) {
    $acctSelect = $accountCols ? ', c.account_customer' : '';
    $acctWhere  = $acctOnly ? ' AND c.account_customer = 1' : '';
    $groupExtra = $accountCols ? ', c.account_customer' : '';

    $having = $overdueOnly
        ? 'HAVING overdue_balance > 0.005'
        : 'HAVING balance_owed > 0.005';

    $sqlCust = "
    SELECT
      c.id,
      c.name,
      c.phone,
      c.email
      {$acctSelect},
      SUM($netBalExpr) AS balance_owed{$custCredCols},
      SUM(
        CASE
          WHEN i.due_date IS NOT NULL AND i.due_date >= '1900-01-01' AND i.due_date < :asof
          THEN ($netBalExpr)
          ELSE 0
        END
      ) AS overdue_balance
    FROM customers c
    INNER JOIN sales_invoices i
      ON i.customer_id = c.id AND i.status = 'final' AND i.is_active = 1
    LEFT JOIN (
      SELECT invoice_id, SUM(amount) AS paid_sum
      FROM sales_invoice_payments
      WHERE is_active = 1
      GROUP BY invoice_id
    ) pay ON pay.invoice_id = i.id{$cnJoin}
    WHERE c.is_active = 1
    {$acctWhere}
    GROUP BY c.id, c.name, c.phone, c.email{$groupExtra}
    {$having}
    ORDER BY overdue_balance DESC, balance_owed DESC, c.name ASC
    ";

    $st = $pdo->prepare($sqlCust);
    $st->execute([':asof' => $asOf]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $bal  = (float) $r['balance_owed'];
        $ov   = (float) $r['overdue_balance'];
        $r['balance_owed']    = $bal;
        $r['overdue_balance'] = $ov;
        $rows[] = $r;
        $totalOwed    += $bal;
        $totalOverdue += $ov;
    }

    // Open invoices (detail): balance > 0, optional overdue / account filters
    $invWhere = [
        'i.status = \'final\'',
        'i.is_active = 1',
        '(' . $netBalExpr . ') > 0.005',
    ];
    if ($acctOnly) {
        $invWhere[] = 'c.account_customer = 1';
    }
    if ($overdueOnly) {
        $invWhere[] = 'i.due_date IS NOT NULL AND i.due_date >= \'1900-01-01\' AND i.due_date < :asof';
    }
    $invWhereSql = implode(' AND ', $invWhere);

    $acctSelInv = $accountCols ? ', c.account_customer' : '';
    $invParams   = [':asof2' => $asOf];
    if ($overdueOnly) {
        $invParams[':asof'] = $asOf;
    }
    if ($cnReady) {
        $invCredSelect = ",
      COALESCE(cn.cn_ar_sum, 0) AS credit_ar_reduction,
      COALESCE(cn.cn_refund_sum, 0) AS credit_cash_refund";
    } else {
        $invCredSelect = ',
      0 AS credit_ar_reduction,
      0 AS credit_cash_refund';
    }
    $sqlInv     = "
    SELECT
      i.id,
      i.invoice_no,
      i.invoice_date,
      i.due_date,
      i.customer_id,
      c.name AS customer_name
      {$acctSelInv},
      ($netBalExpr) AS balance
      {$invCredSelect}
    FROM sales_invoices i
    INNER JOIN customers c ON c.id = i.customer_id AND c.is_active = 1
    LEFT JOIN (
      SELECT invoice_id, SUM(amount) AS paid_sum
      FROM sales_invoice_payments
      WHERE is_active = 1
      GROUP BY invoice_id
    ) pay ON pay.invoice_id = i.id{$cnJoin}
    WHERE {$invWhereSql}
    ORDER BY
      CASE
        WHEN i.due_date IS NOT NULL AND i.due_date >= '1900-01-01' AND i.due_date < :asof2 THEN 0
        WHEN i.due_date IS NOT NULL AND i.due_date >= '1900-01-01' THEN 1
        ELSE 2
      END,
      i.due_date ASC,
      i.id ASC
    ";
    $st2 = $pdo->prepare($sqlInv);
    $st2->execute($invParams);
    foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $ir) {
        $balance = (float) $ir['balance'];
        $due     = $ir['due_date'] ?? null;
        $isOd    = false;
        $daysOd  = 0;
        if (ar_due_is_meaningful($due) && $due < $asOf && $balance > 0.005) {
            $isOd   = true;
            $daysOd = (new DateTime($due))->diff(new DateTime($asOf))->days;
        }
        $ir['_is_overdue']   = $isOd;
        $ir['_days_overdue'] = $daysOd;
        $invoiceRows[]       = $ir;
    }
}

$pageTitle = 'Customer receivables (owed)';
include __DIR__ . '/includes/header.php';
?>

<style>
  /* Screen + print: AR document shell */
  .aw-ar-print-title { border-bottom: 3px solid #c8102e; padding-bottom: 0.5rem; margin-bottom: 1rem; }
  .aw-ar-print-title h1 { color: #0a0a0a; font-size: 1.25rem; font-weight: 700; letter-spacing: .08em; }
  .aw-ar-print-meta { color: #444; font-size: 0.85rem; }
  @media print {
    @page { margin: 12mm; size: A4; }
    body { background: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    nav.navbar, .navbar-aw, footer, .no-print { display: none !important; }
    main.container-fluid { padding: 0 !important; max-width: 100% !important; }
    .aw-ar-print-title h1 { color: #000; }
    .table { font-size: 9pt; }
    .badge { border: 1px solid #333; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
  }
</style>

<div class="container-fluid py-3 aw-ar-report">
  <div class="aw-ar-print-title">
    <h1 class="mb-1"><span style="color:#c8102e;">AUTOWAGEN</span> — Accounts receivable</h1>
    <div class="aw-ar-print-meta">
      As at <strong><?= e($asOf) ?></strong> (Johannesburg)
      <?php if ($overdueOnly): ?> &middot; <strong>Overdue only</strong><?php endif; ?>
      <?php if ($acctOnly): ?> &middot; <strong>Account customers only</strong><?php endif; ?>
    </div>
  </div>

  <?php if ($cnReady): ?>
    <div class="alert alert-light border no-print small mb-3 mb-md-2">
      <strong>Credit notes — locked rules:</strong>
      The <strong>Balance</strong> column is <strong>net due</strong>:
      invoice total − payments − <em>all</em> finalized credits (AR reduction and cash-refund credits both reduce net due).
      <strong>AR cr.</strong> / <strong>Refund</strong> split shows how each invoice&rsquo;s credits are classified (for bookkeeping).
      Cash-refund details stay on each credit note.
    </div>
  <?php endif; ?>

  <div class="alert alert-light border no-print small mb-3">
    <strong>Two different reports:</strong>
    This page is <strong>Accounts receivable</strong> — money <strong>customers still owe you</strong> after a sale (unpaid part of <strong>final</strong> sales invoices).
    Your screenshot with <strong>HASSAN NIZAMIE</strong> and <strong>Purchase #2</strong> is
    <strong><a href="<?= e(APP_URL) ?>/supplier_ap_report.php">Accounts payable</a></strong>
    (Inventory menu) — money <strong>you owe suppliers</strong>. It will <em>not</em> appear here.
  </div>

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 no-print">
    <p class="text-muted small mb-0">
      <strong>Overdue</strong> = unpaid amount on invoices whose <strong>due date</strong> is <em>before</em> the as-at date.
      No due date means not classified as overdue here (still shows in balance).
    </p>
    <div class="d-flex flex-wrap align-items-end gap-2">
      <form method="get" class="d-flex flex-wrap align-items-end gap-2">
        <div>
          <label class="form-label small mb-0">As at</label>
          <input type="date" name="as_of" class="form-control form-control-sm" value="<?= e($asOf) ?>"
                 title="Compare due dates to this day; balances are current">
        </div>
        <div class="form-check mt-4">
          <input class="form-check-input" type="checkbox" name="overdue_only" value="1" id="od"
                 <?= $overdueOnly ? 'checked' : '' ?>>
          <label class="form-check-label small" for="od">Overdue only</label>
        </div>
        <?php if ($accountCols): ?>
          <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" name="account_only" value="1" id="ao"
                   <?= $acctOnly ? 'checked' : '' ?>>
            <label class="form-check-label small" for="ao">Account customers only</label>
          </div>
        <?php endif; ?>
        <button class="btn btn-sm btn-outline-dark" type="submit">Apply</button>
      </form>
      <button type="button" class="btn btn-sm btn-danger" onclick="window.print();">
        <i class="bi bi-printer"></i> Print / PDF
      </button>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e(APP_URL) ?>/invoices_admin.php">Sales invoices</a>
    </div>
  </div>

  <?php if (!$arReady): ?>
    <div class="alert alert-warning no-print">
      Run <code>sql/05_pos.sql</code> in phpMyAdmin (database <code>autowagen_master</code>) to enable this report.
    </div>
  <?php elseif (!$accountCols): ?>
    <div class="alert alert-warning small no-print">
      Run <code>sql/06a_customer_account.sql</code> for <strong>Account customers only</strong> filtering.
    </div>
  <?php endif; ?>

  <?php if ($arReady && !$rows && !$invoiceRows): ?>
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center text-muted py-5">
        <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
        <strong>Nothing to show</strong> —
        <?php if ($overdueOnly): ?>
          no overdue balances for this filter.
        <?php else: ?>
          all final invoices are fully paid, or there are no final invoices yet.
        <?php endif; ?>
        <span class="d-block mt-2">
          Data here comes from <strong>POS → Sales invoices</strong> (customer sales), not from supplier purchases.
          To see supplier balances, open <a href="<?= e(APP_URL) ?>/supplier_ap_report.php">Accounts payable (owed)</a>.
        </span>
      </div>
    </div>
  <?php elseif ($arReady): ?>

    <div class="row g-2 mb-3">
      <div class="col-md-6">
        <div class="card border-0 shadow-sm border-danger border-2 h-100">
          <div class="card-body py-2 d-flex flex-wrap align-items-center justify-content-between bg-dark text-white">
            <span class="small"><strong>Total owed (<?= $overdueOnly ? 'overdue customers' : 'listed customers' ?>):</strong></span>
            <span class="h5 mb-0" style="color:#fff;">R <?= number_format($totalOwed, 2) ?></span>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #c8102e !important;">
          <div class="card-body py-2 d-flex flex-wrap align-items-center justify-content-between">
            <span class="small text-muted"><strong>Of which overdue</strong> (by due date &lt; as at):</span>
            <span class="h5 mb-0 text-danger">R <?= number_format($totalOverdue, 2) ?></span>
          </div>
        </div>
      </div>
    </div>

    <h2 class="h6 text-uppercase mb-2" style="color:#c8102e;">By customer</h2>
    <div class="card border-0 shadow-sm mb-4">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th>Customer</th>
              <?php if ($accountCols): ?><th>Account</th><?php endif; ?>
              <th>Phone</th>
              <th class="text-end"><abbr title="Net due (all credits subtract)">Balance</abbr></th>
              <?php if ($cnReady): ?>
                <th class="text-end text-muted"><abbr title="Finalized credits — AR reduction">AR cr.</abbr></th>
                <th class="text-end text-muted"><abbr title="Finalized credits — cash refund">Refund</abbr></th>
              <?php endif; ?>
              <th class="text-end">Overdue</th>
              <th class="no-print"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r):
              $isAc = $accountCols && !empty($r['account_customer']);
              $ov   = (float) $r['overdue_balance'];
            ?>
            <tr>
              <td><strong><?= e((string) $r['name']) ?></strong></td>
              <?php if ($accountCols): ?>
                <td>
                  <?php if ($isAc): ?>
                    <span class="badge text-white" style="background:#c8102e;">Account</span>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
              <td class="text-muted"><?= (trim((string) ($r['phone'] ?? '')) !== '') ? e((string) $r['phone']) : '—' ?></td>
              <td class="text-end fw-bold text-danger">R <?= number_format((float) $r['balance_owed'], 2) ?></td>
              <?php if ($cnReady): ?>
                <td class="text-end text-muted small">R <?= number_format((float) ($r['credits_ar_reduction'] ?? 0), 2) ?></td>
                <td class="text-end text-muted small">R <?= number_format((float) ($r['credits_cash_refund'] ?? 0), 2) ?></td>
              <?php endif; ?>
              <td class="text-end <?= $ov > 0.005 ? 'fw-bold text-danger' : 'text-muted' ?>">
                <?= $ov > 0.005 ? 'R ' . number_format($ov, 2) : '—' ?>
              </td>
              <td class="no-print">
                <a class="btn btn-sm btn-outline-danger mb-1"
                   href="<?= e(APP_URL) ?>/customer_statement.php?id=<?= (int) $r['id'] ?>"
                   title="Statement">Statement</a><br>
                <a class="btn btn-sm btn-outline-dark"
                   href="<?= e(APP_URL) ?>/customers_admin.php?edit=<?= (int) $r['id'] ?>">Customer</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <h2 class="h6 text-uppercase mb-2" style="color:#c8102e;">By invoice <span class="text-muted fw-normal text-lowercase">(open balance)</span></h2>
    <div class="card border-0 shadow-sm mb-3">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th>Invoice</th>
              <th>Customer</th>
              <th>Inv date</th>
              <th>Due date</th>
              <th>Status</th>
              <th class="text-end"><abbr title="Net due">Balance</abbr></th>
              <?php if ($cnReady): ?>
                <th class="text-end text-muted"><abbr title="Credits — AR reduction">AR cr.</abbr></th>
                <th class="text-end text-muted"><abbr title="Credits — cash refund">Refund cn.</abbr></th>
              <?php endif; ?>
              <th class="no-print"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($invoiceRows as $ir):
              $bal    = (float) $ir['balance'];
              $car    = isset($ir['credit_ar_reduction']) ? (float) $ir['credit_ar_reduction'] : 0.0;
              $cref   = isset($ir['credit_cash_refund']) ? (float) $ir['credit_cash_refund'] : 0.0;
              $stBadge = '';
              if ($ir['_is_overdue']) {
                  $stBadge = '<span class="badge bg-danger">Overdue ' . (int) $ir['_days_overdue'] . 'd</span>';
              } elseif (ar_due_is_meaningful($ir['due_date'] ?? null)) {
                  $stBadge = '<span class="badge bg-secondary text-white">Open</span>';
              } else {
                  $stBadge = '<span class="badge bg-light text-dark border">No due date</span>';
              }
            ?>
            <tr class="<?= $ir['_is_overdue'] ? 'table-danger' : '' ?>">
              <td class="font-monospace"><?= e((string) ($ir['invoice_no'] ?: '#' . $ir['id'])) ?></td>
              <td><?= e((string) $ir['customer_name']) ?></td>
              <td class="text-muted"><?= e((string) $ir['invoice_date']) ?></td>
              <td class="text-muted">
                <?= ar_due_is_meaningful($ir['due_date'] ?? null) ? e((string) $ir['due_date']) : '—' ?>
              </td>
              <td><?= $stBadge ?></td>
              <td class="text-end fw-bold text-danger">R <?= number_format($bal, 2) ?></td>
              <?php if ($cnReady): ?>
                <td class="text-end text-muted small"><?= $car > 0.005 ? 'R ' . number_format($car, 2) : '—' ?></td>
                <td class="text-end text-muted small"><?= $cref > 0.005 ? 'R ' . number_format($cref, 2) : '—' ?></td>
              <?php endif; ?>
              <td class="no-print">
                <a class="btn btn-sm btn-outline-dark"
                   href="<?= e(APP_URL) ?>/invoice_edit.php?id=<?= (int) $ir['id'] ?>">Open</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <p class="small text-muted no-print mb-0">
      Tip: use your browser <strong>Print</strong> dialog → <strong>Save as PDF</strong> for a file copy.
    </p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

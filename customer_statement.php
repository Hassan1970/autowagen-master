<?php
/**
 * Stage 6 — Printable customer account statement + WhatsApp / email helpers (staff send summary; attach PDF from Print).
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/credit_note_helpers.php';

function cs_pos_ready(PDO $pdo): bool {
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE table_schema = DATABASE() AND table_name = 'sales_invoices'"
    )->fetchColumn() > 0;
}

function cs_account_cols(PDO $pdo): bool {
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE table_schema = DATABASE() AND table_name = 'customers' AND column_name = 'account_customer'"
    )->fetchColumn() > 0;
}

/** E.164-style digits for wa.me (SA: 27 + 9 digits). */
function cs_whatsapp_digits(?string $phone): ?string {
    if ($phone === null || $phone === '') {
        return null;
    }
    $d = preg_replace('/\D/', '', $phone);
    if ($d === '') {
        return null;
    }
    if (strlen($d) === 10 && $d[0] === '0') {
        $d = '27' . substr($d, 1);
    } elseif (strlen($d) === 9) {
        $d = '27' . $d;
    }
    if (strlen($d) < 11 || substr($d, 0, 2) !== '27') {
        return null;
    }
    return $d;
}

function cs_due_meaningful(?string $d): bool {
    if ($d === null || $d === '') {
        return false;
    }
    $d = trim($d);
    if ($d === '0000-00-00' || strncmp($d, '0000-', 5) === 0) {
        return false;
    }
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

if (!cs_pos_ready($pdo)) {
    $pageTitle = 'Customer statement';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-warning">Run <code>sql/05_pos.sql</code> first.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$custId = (int) ($_GET['id'] ?? 0);
if ($custId <= 0) {
    header('Location: ' . APP_URL . '/customers_admin.php');
    exit;
}

$asOf = trim((string) ($_GET['as_of'] ?? ''));
if ($asOf === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) {
    $asOf = (new DateTime('now', new DateTimeZone('Africa/Johannesburg')))->format('Y-m-d');
}

$includePaid = !empty($_GET['include_paid']);
$accountCols = cs_account_cols($pdo);

$stc = $pdo->prepare('SELECT * FROM customers WHERE id = ? AND is_active = 1');
$stc->execute([$custId]);
$cust       = $stc->fetch(PDO::FETCH_ASSOC);
if (!$cust) {
    http_response_code(404);
    $pageTitle = 'Customer statement';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-danger">Customer not found or inactive.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
$cust = array_change_key_case($cust, CASE_LOWER);

$cnReady = cn_tables_ready($pdo);
$cnJoin      = '';
$credArSel   = '0';
$credRefSel  = '0';
$credTotSel  = '0';
$netExpr = 'i.total_inc_vat - COALESCE(pay.paid_sum, 0)';
if ($cnReady) {
    $cnJoin =
        "\nLEFT JOIN (\n"
        . "  SELECT invoice_id,\n"
        . "    COALESCE(SUM(CASE WHEN adjustment_type = 'ar_reduction' THEN total_inc_vat ELSE 0 END), 0) AS cn_ar_sum,\n"
        . "    COALESCE(SUM(CASE WHEN adjustment_type = 'cash_refund' THEN total_inc_vat ELSE 0 END), 0) AS cn_ref_sum\n"
        . "  FROM sales_credit_notes WHERE status = 'final' AND is_active = 1\n"
        . "  GROUP BY invoice_id\n"
        . ") cn ON cn.invoice_id = i.id\n";
    $credArSel  = 'COALESCE(cn.cn_ar_sum, 0)';
    $credRefSel = 'COALESCE(cn.cn_ref_sum, 0)';
    $credTotSel = 'COALESCE(cn.cn_ar_sum, 0) + COALESCE(cn.cn_ref_sum, 0)';
    $netExpr   .= ' - COALESCE(cn.cn_ar_sum, 0) - COALESCE(cn.cn_ref_sum, 0)';
}

$sqlInv = "
SELECT
  i.id,
  i.invoice_no,
  i.invoice_date,
  i.due_date,
  i.total_inc_vat,
  COALESCE(pay.paid_sum, 0) AS paid_sum,
  {$credArSel} AS credit_ar_reduction,
  {$credRefSel} AS credit_cash_refund,
  {$credTotSel} AS credit_sum,
  ({$netExpr}) AS balance
FROM sales_invoices i
LEFT JOIN (
  SELECT invoice_id, SUM(amount) AS paid_sum
  FROM sales_invoice_payments
  WHERE is_active = 1
  GROUP BY invoice_id
) pay ON pay.invoice_id = i.id{$cnJoin}
WHERE i.customer_id = ?
  AND i.status = 'final'
  AND i.is_active = 1
ORDER BY i.invoice_date ASC, i.id ASC
";
$sInv = $pdo->prepare($sqlInv);
$sInv->execute([$custId]);
$invoices = $sInv->fetchAll(PDO::FETCH_ASSOC);

$invLines = [];
$totalOutstanding = 0.0;
foreach ($invoices as $row) {
    $row = array_change_key_case($row, CASE_LOWER);
    $bal = round((float) $row['balance'], 2);
    if (!$includePaid && $bal <= 0.005) {
        continue;
    }
    $row['balance'] = $bal;
    $due            = $row['due_date'] ?? null;
    $row['_overdue'] = false;
    if (cs_due_meaningful($due) && $due < $asOf && $bal > 0.005) {
        $row['_overdue']    = true;
        $row['_days_over'] = (new DateTime($due))->diff(new DateTime($asOf))->days;
    }
    $invLines[] = $row;
    if ($bal > 0.005) {
        $totalOutstanding += $bal;
    }
}

$sqlPay = "
SELECT pay.paid_at, pay.amount, pay.payment_method, pay.reference_note,
       i.invoice_no, i.id AS invoice_id
FROM sales_invoice_payments pay
INNER JOIN sales_invoices i ON i.id = pay.invoice_id
WHERE pay.is_active = 1
  AND i.customer_id = ?
  AND i.status = 'final'
  AND i.is_active = 1
ORDER BY pay.paid_at DESC, pay.id DESC
LIMIT 80
";
$sPay = $pdo->prepare($sqlPay);
$sPay->execute([$custId]);
$payments = $sPay->fetchAll(PDO::FETCH_ASSOC);

$cName   = (string) $cust['name'];
$cPhone  = trim((string) ($cust['phone'] ?? ''));
$cEmail  = trim((string) ($cust['email'] ?? ''));
$cAddr   = trim((string) ($cust['billing_address'] ?? ''));

$waDigits = cs_whatsapp_digits($cPhone);
$linesTxt = [];
$linesTxt[] = APP_NAME . ' — Account statement';
$linesTxt[] = 'Customer: ' . $cName;
$linesTxt[] = 'As at: ' . $asOf . ' (Johannesburg)';
$linesTxt[] = '';
if (!$invLines) {
    $linesTxt[] = $includePaid ? 'No final invoices on file.' : 'No open balance (or no invoices).';
} else {
    $linesTxt[] = 'Outstanding total: R ' . number_format($totalOutstanding, 2);
    $linesTxt[] = '';
    foreach ($invLines as $il) {
        $no = $il['invoice_no'] ?: ('#' . $il['id']);
        $linesTxt[] = sprintf(
            '%s | %s | Total R %s | Paid R %s | AR cr R %s | Refund cn R %s | Bal R %s',
            $no,
            $il['invoice_date'],
            number_format((float) $il['total_inc_vat'], 2),
            number_format((float) $il['paid_sum'], 2),
            number_format((float) ($il['credit_ar_reduction'] ?? 0), 2),
            number_format((float) ($il['credit_cash_refund'] ?? 0), 2),
            number_format((float) $il['balance'], 2)
        );
    }
}
$linesTxt[] = '';
$linesTxt[] = 'Please settle any amounts due. Thank you.';
$shareBody = implode("\n", $linesTxt);

$waBody = $shareBody;
if (strlen($waBody) > 1700) {
    $waBody = substr($waBody, 0, 1600) . "\n… (open Statement on PC for full list; attach PDF from Print.)";
}

$waUrl = null;
if ($waDigits) {
    $waUrl = 'https://wa.me/' . $waDigits . '?text=' . rawurlencode($waBody);
}

$mailto = null;
if ($cEmail !== '' && filter_var($cEmail, FILTER_VALIDATE_EMAIL)) {
    $mailto = 'mailto:' . $cEmail
        . '?subject=' . rawurlencode(APP_NAME . ' — Account statement as at ' . $asOf)
        . '&body=' . rawurlencode(str_replace("\n", "\r\n", $shareBody));
}

$pageTitle = 'Statement — ' . $cName;
require_once __DIR__ . '/includes/header.php';
?>

<style>
  .aw-stmt-head { border-bottom: 3px solid #c8102e; padding-bottom: 0.75rem; margin-bottom: 1rem; }
  .aw-stmt-head h1 { font-size: 1.15rem; font-weight: 700; letter-spacing: .06em; color: #0a0a0a; }
  .aw-stmt-box { background: #0a0a0a; color: #fff; border-left: 4px solid #c8102e; }
  @media print {
    @page { margin: 12mm; size: A4; }
    body { background: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    nav.navbar, .navbar-aw, footer, .no-print { display: none !important; }
    main.container-fluid { padding: 0 !important; max-width: 100% !important; }
    .table { font-size: 9pt; }
    .aw-stmt-box { print-color-adjust: exact; }
  }
</style>

<div class="container-fluid py-3 aw-stmt-doc">
  <div class="aw-stmt-head">
    <h1 class="mb-0"><span style="color:#c8102e;"><?= e(APP_NAME) ?></span> — Customer account statement</h1>
    <div class="small text-muted">Statement date: <strong><?= e($asOf) ?></strong> · Currency ZAR · VAT as per invoices</div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-7">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body small">
          <div class="text-uppercase fw-bold mb-2" style="color:#c8102e;">Bill to</div>
          <div class="fw-bold"><?= e($cName) ?></div>
          <?php if ($cAddr !== ''): ?>
            <div class="text-muted mt-1" style="white-space:pre-wrap;"><?= e($cAddr) ?></div>
          <?php endif; ?>
          <?php if ($cPhone !== ''): ?><div class="mt-1"><i class="bi bi-telephone"></i> <?= e($cPhone) ?></div><?php endif; ?>
          <?php if ($cEmail !== ''): ?><div><i class="bi bi-envelope"></i> <?= e($cEmail) ?></div><?php endif; ?>
          <?php if ($accountCols && !empty($cust['account_customer'])): ?>
            <div class="mt-2"><span class="badge text-white" style="background:#c8102e;">Account customer</span></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-md-5">
      <div class="card border-0 shadow-sm h-100 aw-stmt-box">
        <div class="card-body py-3">
          <div class="small text-white-50">Amount outstanding</div>
          <div class="h3 mb-0 text-white">R <?= number_format($totalOutstanding, 2) ?></div>
          <div class="small mt-2" style="color:#ccc;">From final sales invoices with a balance. Use Print for a PDF to attach.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="no-print mb-3 d-flex flex-wrap gap-2 align-items-center">
    <form method="get" class="d-flex flex-wrap align-items-end gap-2">
      <input type="hidden" name="id" value="<?= (int) $custId ?>">
      <div>
        <label class="form-label small mb-0">Statement as at</label>
        <input type="date" name="as_of" class="form-control form-control-sm" value="<?= e($asOf) ?>">
      </div>
      <div class="form-check mt-4">
        <input class="form-check-input" type="checkbox" name="include_paid" value="1" id="ip" <?= $includePaid ? 'checked' : '' ?>>
        <label class="form-check-label small" for="ip">Include paid-in-full invoices</label>
      </div>
      <button type="submit" class="btn btn-sm btn-outline-dark">Apply</button>
    </form>
    <button type="button" class="btn btn-sm btn-danger" onclick="window.print();"><i class="bi bi-printer"></i> Print / PDF</button>
    <?php if ($waUrl): ?>
      <a class="btn btn-sm btn-success" href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer">
        <i class="bi bi-whatsapp"></i> WhatsApp
      </a>
    <?php else: ?>
      <span class="small text-muted">WhatsApp: add a mobile on the customer record (0… or 27…).</span>
    <?php endif; ?>
    <?php if ($mailto): ?>
      <a class="btn btn-sm btn-outline-primary" href="<?= e($mailto) ?>">
        <i class="bi bi-envelope"></i> Email (opens your mail program)
      </a>
    <?php else: ?>
      <span class="small text-muted">Email: add a valid email on the customer record.</span>
    <?php endif; ?>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(APP_URL) ?>/customers_admin.php?edit=<?= (int) $custId ?>">Edit customer</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(APP_URL) ?>/customer_ar_report.php">AR report</a>
  </div>

  <div class="alert alert-light border no-print small">
    <?php if ($cnReady): ?>
      <strong>Credit notes:</strong> <strong>Balance</strong> = total − paid − all credits (AR reduction and cash refunds both reduce balance).
      <strong>AR cr.</strong> / <strong>Refund cn.</strong> show the split for your books; full detail on each finalized credit note.
    <?php else: ?>
      Run <code>sql/07_credit_notes.sql</code> to enable credit-note columns on this statement.
    <?php endif; ?>
    Use <strong>Print / PDF</strong> for a formal copy for WhatsApp or email attachments.
    The <strong>WhatsApp</strong> and <strong>Email</strong> buttons paste a <em>short text summary</em> only — not full letterhead.
    Server-side SMTP is not enabled yet; <strong>Email</strong> opens your PC&rsquo;s mail client.
  </div>

  <h2 class="h6 text-uppercase mb-2" style="color:#c8102e;">Invoices</h2>
  <?php if (!$invLines): ?>
    <p class="text-muted"><?= $includePaid ? 'No final invoices for this customer.' : 'No open balance. Tick “Include paid-in-full” to list history.' ?></p>
  <?php else: ?>
    <div class="table-responsive card border-0 shadow-sm mb-4">
      <table class="table table-sm mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Invoice</th>
            <th>Date</th>
            <th>Due</th>
            <th class="text-end">Total</th>
            <th class="text-end">Paid</th>
            <th class="text-end text-muted"><abbr title="Finalized credits — AR reduction">AR cr.</abbr></th>
            <th class="text-end text-muted"><abbr title="Finalized credits — cash refund">Refund cn.</abbr></th>
            <th class="text-end">Balance</th>
            <th class="no-print"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invLines as $il):
            $bal = (float) $il['balance'];
          ?>
          <tr class="<?= !empty($il['_overdue']) ? 'table-danger' : '' ?>">
            <td class="font-monospace"><?= e((string) ($il['invoice_no'] ?: '#' . $il['id'])) ?></td>
            <td><?= e((string) $il['invoice_date']) ?></td>
            <td class="text-muted">
              <?= cs_due_meaningful($il['due_date'] ?? null) ? e((string) $il['due_date']) : '—' ?>
              <?php if (!empty($il['_overdue'])): ?>
                <span class="badge bg-danger ms-1"><?= (int) $il['_days_over'] ?>d overdue</span>
              <?php endif; ?>
            </td>
            <td class="text-end">R <?= number_format((float) $il['total_inc_vat'], 2) ?></td>
            <td class="text-end">R <?= number_format((float) $il['paid_sum'], 2) ?></td>
            <td class="text-end text-muted small">R <?= number_format((float) ($il['credit_ar_reduction'] ?? 0), 2) ?></td>
            <td class="text-end text-muted small">R <?= number_format((float) ($il['credit_cash_refund'] ?? 0), 2) ?></td>
            <td class="text-end fw-bold <?= $bal > 0.005 ? 'text-danger' : 'text-success' ?>">R <?= number_format($bal, 2) ?></td>
            <td class="no-print">
              <a class="btn btn-sm btn-outline-dark py-0" href="<?= e(APP_URL) ?>/invoice_edit.php?id=<?= (int) $il['id'] ?>">Open</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
          <tr>
            <th colspan="7" class="text-end">Total outstanding</th>
            <th class="text-end text-danger">R <?= number_format($totalOutstanding, 2) ?></th>
            <th class="no-print"></th>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($payments): ?>
    <h2 class="h6 text-uppercase mb-2" style="color:#c8102e;">Recent payments received</h2>
    <div class="table-responsive card border-0 shadow-sm">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Invoice</th>
            <th class="text-end">Amount</th>
            <th>Method</th>
            <th>Reference</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
          <tr>
            <td><?= e((string) $p['paid_at']) ?></td>
            <td class="font-monospace"><?= e((string) ($p['invoice_no'] ?: '#' . $p['invoice_id'])) ?></td>
            <td class="text-end">R <?= number_format((float) $p['amount'], 2) ?></td>
            <td><?= e((string) $p['payment_method']) ?></td>
            <td class="small text-muted"><?= e((string) ($p['reference_note'] ?? '')) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

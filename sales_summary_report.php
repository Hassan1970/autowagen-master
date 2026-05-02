<?php
/**
 * POS sales summary — read-only aggregates by invoice date range (ZAR).
 * Excludes web shop orders (separate workflow). No new DB tables — uses Stage 5 data.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$tz       = new DateTimeZone('Africa/Johannesburg');
$today    = (new DateTime('now', $tz))->format('Y-m-d');
$monthBeg = (new DateTime('first day of this month', $tz))->format('Y-m-d');

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo   = trim((string) ($_GET['date_to'] ?? ''));
if ($dateFrom === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = $monthBeg;
}
if ($dateTo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = $today;
}
if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$posReady = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE table_schema = DATABASE() AND table_name = 'sales_invoices'"
)->fetchColumn() > 0;

$summary = [
    'draft_count' => 0,
    'draft_total' => 0.0,
    'final_count' => 0,
    'final_total' => 0.0,
    'void_count'  => 0,
    'void_total'  => 0.0,
];

$paymentsInPeriod = 0.0;
$partLines         = 0;
$manualLines       = 0;
$invoiceRows       = [];
$topCustomers      = [];

if ($posReady) {
    $st = $pdo->prepare(
        'SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total_inc_vat), 0) AS tot
         FROM sales_invoices
         WHERE is_active = 1
           AND invoice_date >= :df AND invoice_date <= :dt
         GROUP BY status'
    );
    $st->execute([':df' => $dateFrom, ':dt' => $dateTo]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $stt = (string) $r['status'];
        if ($stt === 'draft') {
            $summary['draft_count'] = (int) $r['cnt'];
            $summary['draft_total']  = (float) $r['tot'];
        } elseif ($stt === 'final') {
            $summary['final_count'] = (int) $r['cnt'];
            $summary['final_total']  = (float) $r['tot'];
        } elseif ($stt === 'void') {
            $summary['void_count'] = (int) $r['cnt'];
            $summary['void_total']  = (float) $r['tot'];
        }
    }

    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0)
         FROM sales_invoice_payments
         WHERE is_active = 1
           AND paid_at >= :df AND paid_at <= :dt'
    );
    $st->execute([':df' => $dateFrom, ':dt' => $dateTo]);
    $paymentsInPeriod = (float) $st->fetchColumn();

    $st = $pdo->prepare(
        'SELECT
           SUM(CASE WHEN sil.part_id IS NOT NULL THEN 1 ELSE 0 END) AS part_lines,
           SUM(CASE WHEN sil.part_id IS NULL THEN 1 ELSE 0 END) AS manual_lines
         FROM sales_invoice_lines sil
         INNER JOIN sales_invoices si
           ON si.id = sil.invoice_id AND si.is_active = 1
         WHERE si.status = \'final\'
           AND si.invoice_date >= :df AND si.invoice_date <= :dt'
    );
    $st->execute([':df' => $dateFrom, ':dt' => $dateTo]);
    $mix = $st->fetch(PDO::FETCH_ASSOC);
    $partLines   = (int) ($mix['part_lines'] ?? 0);
    $manualLines = (int) ($mix['manual_lines'] ?? 0);

    $st = $pdo->prepare(
        'SELECT COALESCE(c.name, \'— no customer —\') AS customer_name,
                SUM(si.total_inc_vat) AS tot,
                COUNT(*) AS inv_cnt
         FROM sales_invoices si
         LEFT JOIN customers c ON c.id = si.customer_id
         WHERE si.is_active = 1 AND si.status = \'final\'
           AND si.invoice_date >= :df AND si.invoice_date <= :dt
         GROUP BY si.customer_id, c.name
         ORDER BY tot DESC
         LIMIT 20'
    );
    $st->execute([':df' => $dateFrom, ':dt' => $dateTo]);
    $topCustomers = $st->fetchAll(PDO::FETCH_ASSOC);

    $st = $pdo->prepare(
        'SELECT si.id, si.invoice_no, si.invoice_date, si.status, si.total_inc_vat,
                c.name AS customer_name
         FROM sales_invoices si
         LEFT JOIN customers c ON c.id = si.customer_id
         WHERE si.is_active = 1
           AND si.invoice_date >= :df AND si.invoice_date <= :dt
         ORDER BY si.invoice_date DESC, si.id DESC
         LIMIT 200'
    );
    $st->execute([':df' => $dateFrom, ':dt' => $dateTo]);
    $invoiceRows = $st->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Sales summary (period)';
require_once __DIR__ . '/includes/header.php';
?>

<style>
  .aw-ss-print-title { border-bottom: 3px solid #c8102e; padding-bottom: 0.5rem; margin-bottom: 1rem; }
  .aw-ss-print-title h1 { color: #0a0a0a; font-size: 1.25rem; font-weight: 700; letter-spacing: .08em; }
  .aw-ss-print-meta { color: #444; font-size: 0.85rem; }
  @media print {
    @page { margin: 12mm; size: A4; }
    body { background: #fff !important; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    nav.navbar, .navbar-aw, footer, .no-print { display: none !important; }
    main.container-fluid { padding: 0 !important; max-width: 100% !important; }
    .aw-ss-print-title h1 { color: #000; }
    .table { font-size: 9pt; }
  }
</style>

<div class="container-fluid py-3">
  <div class="aw-ss-print-title">
    <h1 class="mb-1"><span style="color:#c8102e;">AUTOWAGEN</span> — Sales summary</h1>
    <div class="aw-ss-print-meta">
      Invoice date from <strong><?= e($dateFrom) ?></strong> to <strong><?= e($dateTo) ?></strong> (Johannesburg)
      · <span class="text-muted">POS invoices only</span>
    </div>
  </div>

  <div class="alert alert-light border small no-print mb-3">
    <strong>Sales vs cash in:</strong>
    “<strong>Final sales (invoice dates)</strong>” uses each invoice’s <strong>invoice date</strong>.
    “<strong>Payments (paid dates)</strong>” sums payment rows whose <strong>paid date</strong> falls in the range —
    useful for banking, different from invoiced turnover.
    <strong>Web shop guest orders</strong> are tracked under <strong>Reports → Web shop orders</strong>, not here.
  </div>

  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3 no-print">
    <form method="get" class="d-flex flex-wrap align-items-end gap-2">
      <div>
        <label class="form-label small mb-0">From</label>
        <input type="date" name="date_from" class="form-control form-control-sm"
               value="<?= e($dateFrom) ?>" required>
      </div>
      <div>
        <label class="form-label small mb-0">To</label>
        <input type="date" name="date_to" class="form-control form-control-sm"
               value="<?= e($dateTo) ?>" required>
      </div>
      <button type="submit" class="btn btn-sm btn-outline-dark mt-4">Apply</button>
    </form>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-sm btn-danger" onclick="window.print();">
        <i class="bi bi-printer"></i> Print / PDF
      </button>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e(APP_URL) ?>/invoices_admin.php">
        <i class="bi bi-receipt"></i> Sales invoices list
      </a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= e(APP_URL) ?>/customer_ar_report.php">
        Accounts receivable
      </a>
    </div>
  </div>

  <?php if (!$posReady): ?>
    <div class="alert alert-warning no-print mb-0">
      Run <code>sql/05_pos.sql</code> in phpMyAdmin to enable POS and this report.
    </div>
  <?php else: ?>

    <div class="row g-2 mb-3">
      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #198754 !important;">
          <div class="card-body py-2">
            <div class="small text-muted">Final sales (invoice dates)</div>
            <div class="h5 mb-0 text-success"><?= (int) $summary['final_count'] ?> invoices</div>
            <div class="h4 mb-0" style="color:#c8102e;">R <?= number_format($summary['final_total'], 2) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-0 shadow-sm border-secondary h-100">
          <div class="card-body py-2">
            <div class="small text-muted">Draft (same date range)</div>
            <div class="h5 mb-0"><?= (int) $summary['draft_count'] ?> invoices</div>
            <div class="text-muted"><strong>R <?= number_format($summary['draft_total'], 2) ?></strong> draft total</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #0d6efd !important;">
          <div class="card-body py-2">
            <div class="small text-muted">Payments received (paid dates)</div>
            <div class="h4 mb-0 text-primary"><strong>R <?= number_format($paymentsInPeriod, 2) ?></strong></div>
            <div class="small text-muted mb-0">Sum of POS payment rows, not VAT analytics.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-2 mb-4">
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-dark text-white small">
            Void (invoice dates <span class="text-white-50">— historical only</span>)
          </div>
          <div class="card-body py-2">
            <?= (int) $summary['void_count'] ?> void ·
            R <?= number_format($summary['void_total'], 2) ?> <span class="text-muted small">(totals on file)</span>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-dark text-white small">Line mix (final invoices in range)</div>
          <div class="card-body py-2 small">
            <strong><?= number_format($partLines) ?></strong> part-linked lines ·
            <strong><?= number_format($manualLines) ?></strong> manual / fee lines
          </div>
        </div>
      </div>
    </div>

    <h2 class="h6 text-uppercase mb-2" style="color:#c8102e;">
      Top customers <span class="text-muted fw-normal text-lowercase">(final invoices, by total incl. VAT)</span>
    </h2>
    <div class="card border-0 shadow-sm mb-4">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th>Customer</th>
              <th class="text-end">Invoices</th>
              <th class="text-end">Turnover (incl. VAT)</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$topCustomers): ?>
              <tr><td colspan="3" class="text-muted text-center py-3">No final invoices in this range.</td></tr>
            <?php else: ?>
              <?php foreach ($topCustomers as $tc): ?>
                <tr>
                  <td><?= e((string) $tc['customer_name']) ?></td>
                  <td class="text-end"><?= (int) $tc['inv_cnt'] ?></td>
                  <td class="text-end fw-semibold">R <?= number_format((float) $tc['tot'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <h2 class="h6 text-uppercase mb-2" style="color:#c8102e;">
      Invoices in range <span class="text-muted fw-normal text-lowercase">(up to 200 rows)</span>
    </h2>
    <div class="card border-0 shadow-sm mb-3">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Invoice no.</th>
              <th>Date</th>
              <th>Customer</th>
              <th>Status</th>
              <th class="text-end">Total (incl. VAT)</th>
              <th class="no-print"></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$invoiceRows): ?>
              <tr><td colspan="7" class="text-muted text-center py-3">No invoices in this date range.</td></tr>
            <?php else: ?>
              <?php foreach ($invoiceRows as $ir):
                $st = (string) $ir['status'];
                $cls = $st === 'final' ? 'success' : ($st === 'draft' ? 'secondary' : 'dark');
                ?>
                <tr>
                  <td><?= (int) $ir['id'] ?></td>
                  <td class="font-monospace"><?= $ir['invoice_no'] ? e((string) $ir['invoice_no']) : '—' ?></td>
                  <td><?= e((string) $ir['invoice_date']) ?></td>
                  <td><?= $ir['customer_name'] ? e((string) $ir['customer_name']) : '—' ?></td>
                  <td><span class="badge bg-<?= e($cls) ?>"><?= e($st) ?></span></td>
                  <td class="text-end">R <?= number_format((float) $ir['total_inc_vat'], 2) ?></td>
                  <td class="no-print">
                    <a class="btn btn-sm btn-outline-primary"
                       href="<?= e(APP_URL) ?>/invoice_edit.php?id=<?= (int) $ir['id'] ?>">Open</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <p class="small text-muted no-print mb-0">
      <strong>Credit notes</strong> affect customer balances on the <strong>Accounts receivable</strong> report and <strong>invoice</strong> screen; this period summary is <strong>invoice / payment</strong> aggregates only — use AR + credit notes for returns detail.
    </p>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

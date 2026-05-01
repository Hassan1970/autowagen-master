<?php
/**
 * Stage 4 — Parts inventory list / search / filter / paginate.
 *
 * Owner / admin / manager / staff can edit and toggle active.
 * Viewer is read-only.
 *
 * Add and Edit live on part_edit.php (multipart upload form).
 *
 * Search matches sku, name, yard_location (case-insensitive, partial).
 * Optional filters: source / status / vehicle_id / inactive.
 * Pagination: 50 rows / page.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/shop_helpers.php';

$canEdit = user_has_role('owner', 'admin', 'manager', 'staff');

$SOURCE_OPTIONS = [
    'stripped'    => 'Stripped',
    'oem_new'     => 'OEM new',
    'replacement' => 'Replacement',
    'third_party' => 'Third-party',
];
$SOURCE_BADGE = [
    'stripped'    => 'bg-warning text-dark',
    'oem_new'     => 'bg-success',
    'replacement' => 'bg-info text-dark',
    'third_party' => 'bg-secondary',
];

$STATUS_OPTIONS = [
    'on_vehicle' => 'On vehicle',
    'available'  => 'Available',
    'reserved'   => 'Reserved',
    'sold'       => 'Sold',
    'scrapped'   => 'Scrapped',
];
$STATUS_BADGE = [
    'on_vehicle' => 'bg-warning text-dark',
    'available'  => 'bg-success',
    'reserved'   => 'bg-info text-dark',
    'sold'       => 'bg-dark',
    'scrapped'   => 'bg-secondary',
];

$CONDITION_OPTIONS = [
    'new'   => 'New',
    'good'  => 'Good',
    'fair'  => 'Fair',
    'poor'  => 'Poor',
    'scrap' => 'Scrap',
];
$CONDITION_BADGE = [
    'new'   => 'bg-success',
    'good'  => 'bg-primary',
    'fair'  => 'bg-info text-dark',
    'poor'  => 'bg-warning text-dark',
    'scrap' => 'bg-danger',
];

$flash = ['type' => null, 'msg' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        http_response_code(403);
        $flash = ['type' => 'danger', 'msg' => 'You do not have permission to change parts.'];
    } elseif (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = ['type' => 'danger', 'msg' => 'Security token invalid. Please reload the page.'];
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int) ($_POST['id'] ?? 0);
        try {
            if ($action === 'toggle_active' && $id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE parts SET is_active = 1 - is_active WHERE id = :id'
                );
                $stmt->execute([':id' => $id]);
                $flash = ['type' => 'success', 'msg' => 'Part active state toggled.'];
            } else {
                throw new RuntimeException('Unknown action.');
            }
        } catch (Throwable $e) {
            $flash = ['type' => 'danger', 'msg' => APP_DEBUG ? $e->getMessage() : 'Database error.'];
        }
    }
}

// ---------- search + filter + pagination ----------
$q          = trim((string) ($_GET['q']         ?? ''));
$sourceF    = (string) ($_GET['source']         ?? '');
$statusF    = (string) ($_GET['status']         ?? '');
$vehicleF   = (int)    ($_GET['vehicle_id']     ?? 0);
if ($sourceF !== '' && !array_key_exists($sourceF, $SOURCE_OPTIONS)) {
    $sourceF = '';
}
if ($statusF !== '' && !array_key_exists($statusF, $STATUS_OPTIONS)) {
    $statusF = '';
}
$showInact  = !empty($_GET['inactive']);
$page       = max(1, (int) ($_GET['page'] ?? 1));

// POS: invoice modal links here with ?return=invoice_edit.php?id=N — show a clear "Back" button.
$returnRaw               = trim((string) ($_GET['return'] ?? ''));
$returnInvoiceHref       = null;
$returnParamForForms     = '';
if ($returnRaw !== '' && preg_match('#^invoice_edit\\.php\\?id=\\d+$#', $returnRaw)) {
    $returnParamForForms = $returnRaw;
    $returnInvoiceHref   = rtrim(APP_URL, '/') . '/' . $returnRaw;
}
$returnInvoiceId    = 0;
$returnInvoiceDraft = false;
if ($returnParamForForms !== '' && preg_match('/id=(\d+)/', $returnParamForForms, $mInv)) {
    $returnInvoiceId = (int) $mInv[1];
    if ($returnInvoiceId > 0) {
        $stInvReturn = $pdo->prepare(
            'SELECT status FROM sales_invoices WHERE id = ? AND is_active = 1'
        );
        $stInvReturn->execute([$returnInvoiceId]);
        $returnInvoiceDraft = ($stInvReturn->fetchColumn() === 'draft');
    }
}
$perPage    = 50;
$offset     = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(p.sku LIKE :q OR p.name LIKE :q OR p.yard_location LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($sourceF !== '') {
    $where[] = 'p.source = :source';
    $params[':source'] = $sourceF;
}
if ($statusF !== '') {
    $where[] = 'p.status = :status';
    $params[':status'] = $statusF;
}
if ($vehicleF > 0) {
    $where[] = 'p.vehicle_id = :vehicle_id';
    $params[':vehicle_id'] = $vehicleF;
}
if (!$showInact) {
    $where[] = 'p.is_active = 1';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$spReady = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.`TABLES`
     WHERE table_schema = DATABASE() AND table_name = 'supplier_purchases'"
)->fetchColumn() > 0
    && (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.`COLUMNS`
         WHERE table_schema = DATABASE() AND table_name = 'parts' AND column_name = 'supplier_purchase_id'"
    )->fetchColumn() > 0;

$lonReady = shop_parts_list_online_ready($pdo);

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM parts p {$whereSql}");
$totalStmt->execute($params);
$total = (int) $totalStmt->fetchColumn();

if ($spReady) {
    $listSql = "SELECT p.id, p.sku, p.name, p.source, p.condition_grade, p.status,
                   p.asking_price, p.discount_price, p.yard_location, p.qty_on_hand,
                   p.is_active, " . ($lonReady ? 'p.list_online' : '0 AS list_online') . ", p.updated_at,
                   p.vehicle_id, v.stock_code AS vehicle_stock, v.make AS vehicle_make,
                   v.model AS vehicle_model,
                   p.supplier_id, s.name AS supplier_name,
                   p.seller_name, p.supplier_purchase_id,
                   p.has_tpp_id_doc, p.tpp_id_doc_path,
                   p.has_tpp_proof_of_address, p.tpp_proof_of_address_path,
                   sp.tpp_id_doc_path AS intake_id_doc,
                   sp.has_tpp_proof_of_address AS intake_hpoa,
                   sp.tpp_proof_of_address_path AS intake_poa
            FROM parts p
            LEFT JOIN vehicles  v ON v.id = p.vehicle_id
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            LEFT JOIN supplier_purchases sp ON sp.id = p.supplier_purchase_id AND sp.is_active = 1
            {$whereSql}
            ORDER BY p.is_active DESC,
                     p.sku ASC
            LIMIT {$perPage} OFFSET {$offset}";
} else {
    $listSql = "SELECT p.id, p.sku, p.name, p.source, p.condition_grade, p.status,
                   p.asking_price, p.discount_price, p.yard_location, p.qty_on_hand,
                   p.is_active, " . ($lonReady ? 'p.list_online' : '0 AS list_online') . ", p.updated_at,
                   p.vehicle_id, v.stock_code AS vehicle_stock, v.make AS vehicle_make,
                   v.model AS vehicle_model,
                   p.supplier_id, s.name AS supplier_name,
                   p.seller_name,
                   NULL AS supplier_purchase_id,
                   p.has_tpp_id_doc, p.tpp_id_doc_path,
                   p.has_tpp_proof_of_address, p.tpp_proof_of_address_path,
                   NULL AS intake_id_doc,
                   NULL AS intake_hpoa,
                   NULL AS intake_poa
            FROM parts p
            LEFT JOIN vehicles  v ON v.id = p.vehicle_id
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            {$whereSql}
            ORDER BY p.is_active DESC,
                     p.sku ASC
            LIMIT {$perPage} OFFSET {$offset}";
}
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

/** @param array<string,mixed> $r */
function part_tpp_docs_badge(array $r): string {
    if (($r['source'] ?? '') !== 'third_party') {
        return '<span class="text-muted">—</span>';
    }
    $idPath = $r['tpp_id_doc_path'] ?: ($r['intake_id_doc'] ?? null);
    $poaPath = $r['tpp_proof_of_address_path'] ?: ($r['intake_poa'] ?? null);
    $poaFlag = (int) ($r['has_tpp_proof_of_address'] ?? 0)
        | (int) ($r['intake_hpoa'] ?? 0);
    $n = 0;
    if (!empty($idPath)) {
        $n++;
    }
    if ($poaFlag && !empty($poaPath)) {
        $n++;
    }
    if ($n >= 2) {
        return '<span class="badge bg-success" title="Third-party: compliance docs on file">Docs 2/2 ✓</span>';
    }
    if ($n === 1) {
        return '<span class="badge bg-warning text-dark" title="Incomplete compliance docs">Docs 1/2</span>';
    }
    return '<span class="badge bg-danger" title="No compliance scans yet">Docs 0/2</span>';
}

/** @param array<string,mixed> $r */
function part_web_list_badge(array $r, bool $lonReady): string {
    if (!$lonReady) {
        return '<span class="text-muted small">—</span>';
    }
    $r = array_change_key_case($r, CASE_LOWER);
    if (empty($r['list_online'])) {
        return '<span class="text-muted">—</span>';
    }
    if (!shop_part_purchasable_online($r)) {
        return '<span class="badge bg-warning text-dark border border-dark" title="Listed; guest checkout is enquiry-only">Enquiry</span>';
    }
    return '<span class="badge" style="background:#0a0a0a;border-bottom:2px solid #c8102e;" title="Buy online: OEM new or Replacement, graded New / Good / Fair">Web</span>';
}

// vehicle dropdown — only vehicles that already have at least one part
$vehDropdown = $pdo->query(
    "SELECT v.id, v.stock_code, v.make, v.model
     FROM vehicles v
     WHERE EXISTS (SELECT 1 FROM parts p2 WHERE p2.vehicle_id = v.id)
     ORDER BY v.stock_code ASC, v.make ASC, v.model ASC"
)->fetchAll();

$pageTitle = 'Parts inventory';
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
  <?php if ($returnInvoiceHref): ?>
    <div class="alert alert-primary d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 py-2 shadow-sm">
      <span class="small mb-0"><i class="bi bi-receipt-cutoff"></i> You came from an <strong>invoice</strong>. Use this button to go back (same as browser Back).<?php if ($returnInvoiceDraft && $canEdit): ?> Parts with status <strong>Available</strong> can be added straight to the draft using <strong>Add to invoice</strong> in the table.<?php endif; ?></span>
      <a class="btn btn-sm btn-dark" href="<?= e($returnInvoiceHref) ?>"><i class="bi bi-arrow-left"></i> Back to invoice</a>
    </div>
  <?php endif; ?>
  <?php if ($returnInvoiceHref && !$returnInvoiceDraft): ?>
    <div class="alert alert-warning py-2 small mb-3">
      This invoice is <strong>not a draft</strong> (finalized, void, or missing). You can open it with <strong>Back to invoice</strong>, but part lines cannot be added from this list.
    </div>
  <?php endif; ?>
  <?php if (!$spReady): ?>
    <div class="alert alert-warning py-2 small mb-3">
      <strong>Database update needed</strong> — run <code>sql/04c_supplier_purchases.sql</code> in phpMyAdmin
      (database <code>autowagen_master</code> → SQL tab → paste file → Go) to enable <em>supplier purchase</em> batches
      and purchase links in this list. The rest of inventory still works without it.
    </div>
  <?php endif; ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-1"><i class="bi bi-box-seam"></i> Parts inventory</h1>
      <div class="text-muted small">
        <?= number_format($total) ?> result<?= $total === 1 ? '' : 's' ?>
        <?php if ($q !== ''): ?>for "<strong><?= e($q) ?></strong>"<?php endif; ?>
        <?php if ($sourceF !== ''): ?> &middot; source <strong><?= e($SOURCE_OPTIONS[$sourceF]) ?></strong><?php endif; ?>
        <?php if ($statusF !== ''): ?> &middot; status <strong><?= e($STATUS_OPTIONS[$statusF]) ?></strong><?php endif; ?>
        <?php if ($showInact): ?> &middot; including inactive<?php endif; ?>
      </div>
    </div>
    <div>
      <?php if ($canEdit): ?>
        <a class="btn btn-sm btn-danger" href="<?= e(APP_URL) ?>/part_edit.php">
          <i class="bi bi-plus-lg"></i> Add part
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($flash['msg']): ?>
    <div class="alert alert-<?= e($flash['type']) ?> py-2"><?= e($flash['msg']) ?></div>
  <?php endif; ?>

  <form method="get" class="row g-2 mb-3">
    <?php if ($returnParamForForms !== ''): ?>
      <input type="hidden" name="return" value="<?= e($returnParamForForms) ?>">
    <?php endif; ?>
    <div class="col-md-3">
      <input type="text" name="q" value="<?= e($q) ?>"
             class="form-control form-control-sm"
             placeholder="Search SKU / name / yard&hellip;">
    </div>
    <div class="col-md-2">
      <select class="form-select form-select-sm" name="source">
        <option value="">All sources</option>
        <?php foreach ($SOURCE_OPTIONS as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $sourceF === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select class="form-select form-select-sm" name="status">
        <option value="">All statuses</option>
        <?php foreach ($STATUS_OPTIONS as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $statusF === $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select class="form-select form-select-sm" name="vehicle_id">
        <option value="0">All vehicles</option>
        <?php foreach ($vehDropdown as $vd): ?>
          <option value="<?= (int) $vd['id'] ?>" <?= $vehicleF === (int) $vd['id'] ? 'selected' : '' ?>>
            <?= e($vd['stock_code'] ?: '(no code)') ?> · <?= e($vd['make']) ?> <?= e($vd['model']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-1">
      <div class="form-check form-check-inline mt-1">
        <input class="form-check-input" type="checkbox" id="inactive" name="inactive" value="1"
               <?= $showInact ? 'checked' : '' ?>>
        <label class="form-check-label small" for="inactive">Inactive</label>
      </div>
    </div>
    <div class="col-md-2 text-end">
      <button class="btn btn-sm btn-outline-dark" type="submit">
        <i class="bi bi-search"></i> Search
      </button>
      <?php if ($q !== '' || $showInact || $sourceF !== '' || $statusF !== '' || $vehicleF > 0): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(
            APP_URL . '/parts_admin.php'
            . ($returnParamForForms !== '' ? ('?' . http_build_query(['return' => $returnParamForForms])) : '')
        ) ?>">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>SKU</th>
            <th>Name</th>
            <th>Source</th>
            <th>TPP docs</th>
            <th>From</th>
            <th>Cond.</th>
            <th>Status</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Asking</th>
            <th>Yard</th>
            <?php if ($returnInvoiceHref): ?>
              <th class="text-end">Invoice</th>
            <?php endif; ?>
            <th>Web</th>
            <th>Active</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr>
              <td colspan="<?= $returnInvoiceHref ? '15' : '14' ?>" class="text-center text-muted py-4">
                No parts match.
                <?php if ($canEdit): ?>
                  <a href="<?= e(APP_URL) ?>/part_edit.php">Add the first one</a>.
                <?php endif; ?>
              </td>
            </tr>
          <?php else: foreach ($rows as $r):
            $sBadge   = $SOURCE_BADGE[$r['source']]      ?? 'bg-light text-dark';
            $sLabel   = $SOURCE_OPTIONS[$r['source']]    ?? ucfirst((string) $r['source']);
            $stBadge  = $STATUS_BADGE[$r['status']]      ?? 'bg-light text-dark';
            $stLabel  = $STATUS_OPTIONS[$r['status']]    ?? ucfirst((string) $r['status']);
            $cBadge   = $CONDITION_BADGE[$r['condition_grade']] ?? 'bg-light text-dark';
            $cLabel   = $CONDITION_OPTIONS[$r['condition_grade']] ?? ucfirst((string) $r['condition_grade']);

            // "From" cell: vehicle stock code (with link) OR supplier name OR seller name OR dash
            $fromHtml = '<span class="text-muted">—</span>';
            if (!empty($r['vehicle_id'])) {
                $fromHtml = '<a href="' . e(APP_URL) . '/vehicle_edit.php?id=' . (int) $r['vehicle_id'] . '" class="text-decoration-none">'
                          . '<span class="font-monospace small">' . e($r['vehicle_stock'] ?: '(no code)') . '</span>'
                          . '<span class="small text-muted ms-1">' . e($r['vehicle_make']) . ' ' . e($r['vehicle_model']) . '</span>'
                          . '</a>';
            } elseif (!empty($r['supplier_purchase_id'])) {
                $fromHtml = '<a class="small" href="' . e(APP_URL) . '/supplier_purchase_edit.php?id=' . (int) $r['supplier_purchase_id'] . '">'
                          . '<i class="bi bi-boxes"></i> Purchase #' . (int) $r['supplier_purchase_id'] . '</a>';
            } elseif (!empty($r['supplier_id'])) {
                $fromHtml = '<span class="small">' . e($r['supplier_name']) . '</span>';
            } elseif (!empty($r['seller_name'])) {
                $fromHtml = '<span class="small text-muted"><i class="bi bi-person"></i> ' . e($r['seller_name']) . '</span>';
            }
          ?>
            <tr class="<?= $r['is_active'] ? '' : 'text-muted' ?>">
              <td class="font-monospace small"><?= e($r['sku']) ?></td>
              <td><strong><?= e($r['name']) ?></strong></td>
              <td><span class="badge <?= e($sBadge) ?>"><?= e($sLabel) ?></span></td>
              <td><?= part_tpp_docs_badge($r) ?></td>
              <td><?= $fromHtml ?></td>
              <td><span class="badge <?= e($cBadge) ?>"><?= e($cLabel) ?></span></td>
              <td><span class="badge <?= e($stBadge) ?>"><?= e($stLabel) ?></span></td>
              <td class="text-end small"><?= (int) $r['qty_on_hand'] ?></td>
              <td class="text-end">
                <?php if ((float) $r['asking_price'] > 0): ?>
                  R <?= number_format((float) $r['asking_price'], 2) ?>
                  <?php if ($r['discount_price'] !== null && (float) $r['discount_price'] > 0): ?>
                    <div class="small text-success">
                      sale R <?= number_format((float) $r['discount_price'], 2) ?>
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <td class="small"><?= e($r['yard_location'] ?: '—') ?></td>
              <?php if ($returnInvoiceHref): ?>
                <td class="text-end">
                  <?php if (!$canEdit): ?>
                    <span class="text-muted small">—</span>
                  <?php elseif (!$returnInvoiceDraft): ?>
                    <span class="text-muted small">—</span>
                  <?php elseif (($r['status'] ?? '') === 'available' && (int) ($r['qty_on_hand'] ?? 0) > 0 && !empty($r['is_active'])): ?>
                    <?php
                    $maxQ       = max(1, (int) $r['qty_on_hand']);
                    $invPostUrl = rtrim(APP_URL, '/') . '/invoice_edit.php?id=' . (int) $returnInvoiceId;
                    ?>
                    <form method="post" action="<?= e($invPostUrl) ?>" class="d-inline-block text-end">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="add_line_part">
                      <input type="hidden" name="part_id" value="<?= (int) $r['id'] ?>">
                      <div class="d-flex flex-column flex-md-row gap-1 align-items-stretch align-items-md-center justify-content-end">
                        <input type="number"
                               class="form-control form-control-sm"
                               style="width:3.75rem;min-width:3rem"
                               name="part_qty"
                               value="1"
                               min="1"
                               max="<?= $maxQ ?>"
                               title="Quantity (max <?= $maxQ ?> on hand)">
                        <button type="submit" class="btn btn-danger btn-sm text-nowrap">
                          <i class="bi bi-cart-plus"></i> Add to invoice
                        </button>
                      </div>
                    </form>
                  <?php else: ?>
                    <span class="badge bg-secondary text-wrap" style="max-width:9rem;font-weight:500"
                          title="Change part status to Available (and ensure stock) on Edit part to sell it here."><?= e($stLabel) ?></span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
              <td class="small"><?= part_web_list_badge($r, $lonReady) ?></td>
              <td>
                <?php if ($r['is_active']): ?>
                  <span class="badge bg-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Inactive</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-dark"
                   href="<?= e(APP_URL) ?>/part_edit.php?id=<?= (int) $r['id'] ?>"
                   title="<?= $canEdit ? 'Edit' : 'View' ?>">
                  <i class="bi bi-<?= $canEdit ? 'pencil' : 'eye' ?>"></i>
                </a>
                <?php if ($canEdit): ?>
                  <form method="post" class="d-inline"
                        onsubmit="return confirm('<?= $r['is_active'] ? 'Deactivate' : 'Activate' ?> this part?');">
                    <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="id"     value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-sm btn-outline-secondary" type="submit"
                            title="<?= $r['is_active'] ? 'Deactivate' : 'Activate' ?>">
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
          $queryForNav = array_filter([
            'q'          => $q !== '' ? $q : null,
            'source'     => $sourceF !== '' ? $sourceF : null,
            'status'     => $statusF !== '' ? $statusF : null,
            'vehicle_id' => $vehicleF > 0 ? $vehicleF : null,
            'inactive'   => $showInact ? 1 : null,
            'return'     => $returnParamForForms !== '' ? $returnParamForForms : null,
          ]);
          $base = '?' . http_build_query($queryForNav);
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

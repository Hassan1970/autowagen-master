<?php
/**
 * Stage 3 — Customers list / search / paginate / add+edit (modal).
 * Stage 3d — SA Second-Hand Goods Act compliance docs:
 *   * SA ID (individuals) / CIPC reg number (businesses) + scan upload
 *   * Proof of address upload
 *   * Compliance badge on the list page
 * Stage 6a — Account customer flag + optional credit limit (`sql/06a_customer_account.sql`).
 *
 * Owner / admin / manager can add, edit and toggle active.
 * Staff / viewer is read-only (no Add button, no toggle).
 *
 * Modal-based add/edit. Multipart form so the modal can also accept
 * file uploads. Uploads are deferred until the customer record exists
 * (i.e. the upload widgets are visible only when editing an existing
 * customer, just like vehicle_edit.php's photo gallery).
 *
 * Filter: search across name / contact / phone / email / VAT / SA ID /
 * CIPC, plus a type filter (all / individual / business) and a
 * "compliance" filter (any / docs complete / docs missing).
 * Pagination: 50 rows / page (Stage-3 convention).
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/uploads.php';

/**
 * PDO sometimes returns different column name casing; normalize so
 * $row['id_doc_path'] always works in the view.
 */
function normalize_customers_row(?array $row): ?array {
    if ($row === null) {
        return null;
    }
    return array_change_key_case($row, CASE_LOWER);
}

$canEdit = user_has_role('owner', 'admin', 'manager');

$custAccountCols = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
       AND COLUMN_NAME = 'account_customer'"
)->fetchColumn() > 0;

$flash    = ['type' => null, 'msg' => null];
$editRow  = null;   // populated if a "save" failed; pre-fills the modal
$openModal = false;

// POS draft invoice → SHGA workflow: preserve ?return=invoice_edit.php?id=N (whitelist; same pattern as parts_admin.php).
$returnParamForForms = '';
if (!empty($_GET['return'])) {
    $r0 = trim((string) $_GET['return']);
    if ($r0 !== '' && preg_match('#^invoice_edit\.php\?id=\d+$#', $r0)) {
        $returnParamForForms = $r0;
    }
}
$returnInvoiceHref    = null;
$returnInvoiceId      = 0;
$returnInvoiceDraft   = false;
if ($returnParamForForms !== '') {
    $returnInvoiceHref = rtrim(APP_URL, '/') . '/' . $returnParamForForms;
    if (preg_match('/id=(\d+)/', $returnParamForForms, $mRet)) {
        $returnInvoiceId = (int) $mRet[1];
        if ($returnInvoiceId > 0) {
            $stInvRet = $pdo->prepare(
                "SELECT status FROM sales_invoices WHERE id = ? AND is_active = 1"
            );
            $stInvRet->execute([$returnInvoiceId]);
            $returnInvoiceDraft = ($stInvRet->fetchColumn() === 'draft');
        }
    }
}

// ---------- POST handling ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rp = trim((string) ($_POST['return'] ?? ''));
    if ($rp !== '' && preg_match('#^invoice_edit\.php\?id=\d+$#', $rp)) {
        $returnParamForForms = $rp;
        $returnInvoiceHref   = rtrim(APP_URL, '/') . '/' . $returnParamForForms;
        if (preg_match('/id=(\d+)/', $returnParamForForms, $mRet)) {
            $returnInvoiceId = (int) $mRet[1];
            if ($returnInvoiceId > 0) {
                $stInvRet = $pdo->prepare(
                    "SELECT status FROM sales_invoices WHERE id = ? AND is_active = 1"
                );
                $stInvRet->execute([$returnInvoiceId]);
                $returnInvoiceDraft = ($stInvRet->fetchColumn() === 'draft');
            }
        }
    }
    if (!$canEdit) {
        http_response_code(403);
        $flash = ['type' => 'danger', 'msg' => 'You do not have permission to change customers.'];
    } elseif (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = ['type' => 'danger', 'msg' => 'Security token invalid. Please reload the page.'];
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int) ($_POST['id'] ?? 0);
        try {
            if ($action === 'toggle_active' && $id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE customers SET is_active = 1 - is_active WHERE id = :id'
                );
                $stmt->execute([':id' => $id]);
                $flash = ['type' => 'success', 'msg' => 'Customer active state toggled.'];

            } elseif ($action === 'delete_doc' && $id > 0) {
                // Remove a compliance doc (id_doc or proof_of_address).
                $which = $_POST['which'] ?? '';
                $col   = $which === 'proof_of_address' ? 'proof_of_address_path'
                       : ($which === 'id_doc'           ? 'id_doc_path' : null);
                if ($col === null) {
                    throw new RuntimeException('Unknown document slot.');
                }
                $cur = $pdo->prepare("SELECT {$col} AS p FROM customers WHERE id = :id");
                $cur->execute([':id' => $id]);
                $oldPath = $cur->fetchColumn();
                if ($col === 'proof_of_address_path') {
                    $stmt = $pdo->prepare(
                        'UPDATE customers
                            SET proof_of_address_path = NULL,
                                has_proof_of_address  = 0
                          WHERE id = :id'
                    );
                } else {
                    $stmt = $pdo->prepare("UPDATE customers SET {$col} = NULL WHERE id = :id");
                }
                $stmt->execute([':id' => $id]);
                if ($oldPath) delete_uploaded_file($oldPath);
                $flash = ['type' => 'success', 'msg' => 'Document removed.'];
                // Re-open modal on the same customer so they can re-upload.
                $stmtR = $pdo->prepare('SELECT * FROM customers WHERE id = :id');
                $stmtR->execute([':id' => $id]);
                $editRow   = normalize_customers_row($stmtR->fetch() ?: null);
                // From POS draft: stay on list — use "Back to invoice" or Edit (return preserved).
                $openModal = (bool) $editRow && $returnParamForForms === '';

            } elseif ($action === 'save') {
                $type = ($_POST['type'] ?? 'individual') === 'business' ? 'business' : 'individual';
                $data = [
                    'type'                  => $type,
                    'name'                  => trim((string) ($_POST['name']                  ?? '')),
                    'contact_person'        => trim((string) ($_POST['contact_person']        ?? '')),
                    'phone'                 => trim((string) ($_POST['phone']                 ?? '')),
                    'email'                 => trim((string) ($_POST['email']                 ?? '')),
                    'billing_address'       => trim((string) ($_POST['billing_address']       ?? '')),
                    'delivery_address'      => trim((string) ($_POST['delivery_address']      ?? '')),
                    'vat_number'            => trim((string) ($_POST['vat_number']            ?? '')),
                    // Stage 3d compliance number — only the relevant one is
                    // saved per type. The other column is forced NULL so we
                    // don't carry stale data around when type changes.
                    'sa_id_number'          => $type === 'individual'
                                                ? trim((string) ($_POST['sa_id_number']       ?? ''))
                                                : '',
                    'company_reg_number'    => $type === 'business'
                                                ? trim((string) ($_POST['company_reg_number'] ?? ''))
                                                : '',
                    'has_proof_of_address'  => !empty($_POST['has_proof_of_address']) ? 1 : 0,
                    'notes'                 => trim((string) ($_POST['notes']                 ?? '')),
                    'is_active'             => !empty($_POST['is_active']) ? 1 : 0,
                ];
                if ($custAccountCols) {
                    $data['account_customer'] = !empty($_POST['account_customer']) ? 1 : 0;
                    $limRaw = trim((string) ($_POST['credit_limit_zar'] ?? ''));
                    if ($limRaw === '' || !is_numeric($limRaw)) {
                        $data['credit_limit_zar'] = null;
                    } else {
                        $v = round((float) $limRaw, 2);
                        $data['credit_limit_zar'] = $v >= 0 ? $v : null;
                    }
                }
                if ($data['name'] === '') throw new RuntimeException('Name is required.');

                $params = [
                    ':type'   => $data['type'],
                    ':name'   => $data['name'],
                    ':cp'     => $data['contact_person']   !== '' ? $data['contact_person']   : null,
                    ':phone'  => $data['phone']            !== '' ? $data['phone']            : null,
                    ':email'  => $data['email']            !== '' ? $data['email']            : null,
                    ':ba'     => $data['billing_address']  !== '' ? $data['billing_address']  : null,
                    ':da'     => $data['delivery_address'] !== '' ? $data['delivery_address'] : null,
                    ':vat'    => $data['vat_number']       !== '' ? $data['vat_number']       : null,
                    ':said'   => $data['sa_id_number']     !== '' ? $data['sa_id_number']     : null,
                    ':creg'   => $data['company_reg_number'] !== '' ? $data['company_reg_number'] : null,
                    ':hpoa'   => $data['has_proof_of_address'],
                    ':notes'  => $data['notes']            !== '' ? $data['notes']            : null,
                    ':active' => $data['is_active'],
                ];
                if ($custAccountCols) {
                    $params[':ac'] = $data['account_customer'];
                    $params[':clim'] = $data['credit_limit_zar'];
                }

                if ($id > 0) {
                    $params[':id'] = $id;
                    if ($custAccountCols) {
                        $stmt = $pdo->prepare(
                            'UPDATE customers SET
                               type=:type, name=:name, contact_person=:cp, phone=:phone,
                               email=:email, billing_address=:ba, delivery_address=:da,
                               vat_number=:vat, sa_id_number=:said,
                               company_reg_number=:creg, has_proof_of_address=:hpoa,
                               notes=:notes, account_customer=:ac, credit_limit_zar=:clim, is_active=:active
                             WHERE id=:id'
                        );
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE customers SET
                               type=:type, name=:name, contact_person=:cp, phone=:phone,
                               email=:email, billing_address=:ba, delivery_address=:da,
                               vat_number=:vat, sa_id_number=:said,
                               company_reg_number=:creg, has_proof_of_address=:hpoa,
                               notes=:notes, is_active=:active
                             WHERE id=:id'
                        );
                    }
                    $stmt->execute($params);
                    $savedId = $id;
                } else {
                    $params[':uid'] = (int) ($_SESSION['user_id'] ?? 0);
                    if ($custAccountCols) {
                        $stmt = $pdo->prepare(
                            'INSERT INTO customers
                             (type, name, contact_person, phone, email,
                              billing_address, delivery_address, vat_number,
                              sa_id_number, company_reg_number, has_proof_of_address,
                              notes, account_customer, credit_limit_zar, is_active, created_by)
                             VALUES
                             (:type, :name, :cp, :phone, :email,
                              :ba, :da, :vat,
                              :said, :creg, :hpoa,
                              :notes, :ac, :clim, :active, :uid)'
                        );
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO customers
                             (type, name, contact_person, phone, email,
                              billing_address, delivery_address, vat_number,
                              sa_id_number, company_reg_number, has_proof_of_address,
                              notes, is_active, created_by)
                             VALUES
                             (:type, :name, :cp, :phone, :email,
                              :ba, :da, :vat,
                              :said, :creg, :hpoa,
                              :notes, :active, :uid)'
                        );
                    }
                    $stmt->execute($params);
                    $savedId = (int) $pdo->lastInsertId();
                }

                // Handle optional file uploads (only meaningful once a row exists).
                $uploadFlash = [];

                if (!empty($_FILES['id_doc_file']['name'])) {
                    $newPath = save_uploaded_customer_doc($_FILES['id_doc_file'], $savedId, 'id_doc');
                    // Replace any previous one.
                    $cur = $pdo->prepare('SELECT id_doc_path FROM customers WHERE id = :id');
                    $cur->execute([':id' => $savedId]);
                    $old = $cur->fetchColumn();
                    $pdo->prepare('UPDATE customers SET id_doc_path = :p WHERE id = :id')
                        ->execute([':p' => $newPath, ':id' => $savedId]);
                    if ($old && $old !== $newPath) delete_uploaded_file($old);
                    $uploadFlash[] = 'ID document uploaded';
                }

                if (!empty($_FILES['proof_of_address_file']['name'])) {
                    $newPath = save_uploaded_customer_doc($_FILES['proof_of_address_file'], $savedId, 'proof_of_address');
                    $cur = $pdo->prepare('SELECT proof_of_address_path FROM customers WHERE id = :id');
                    $cur->execute([':id' => $savedId]);
                    $old = $cur->fetchColumn();
                    $pdo->prepare(
                        'UPDATE customers
                            SET proof_of_address_path = :p,
                                has_proof_of_address  = 1
                          WHERE id = :id'
                    )->execute([':p' => $newPath, ':id' => $savedId]);
                    if ($old && $old !== $newPath) delete_uploaded_file($old);
                    $uploadFlash[] = 'Proof of address uploaded';
                }

                $verb = $id > 0 ? 'Saved' : 'Added';
                $msg  = "$verb customer: " . $data['name'];
                if ($uploadFlash) $msg .= '. ' . implode(', ', $uploadFlash) . '.';
                $flash = ['type' => 'success', 'msg' => $msg];

                // Re-open the modal on the row that was just created/edited — except when
                // staff came from a draft invoice (POS / SHGA) so they are not trapped in modal.
                $stmtR = $pdo->prepare('SELECT * FROM customers WHERE id = :id');
                $stmtR->execute([':id' => $savedId]);
                $editRow   = normalize_customers_row($stmtR->fetch() ?: null);
                $openModal = (bool) $editRow && $returnParamForForms === '';

            } else {
                throw new RuntimeException('Unknown action.');
            }
        } catch (Throwable $e) {
            $flash = ['type' => 'danger', 'msg' => APP_DEBUG ? $e->getMessage() : 'Database error.'];
            $editRow = $_POST + ['id' => $id]; // re-open modal with the user's input
            $openModal = true;
        }
    }
}

// ---------- Edit-link handler (?edit=N) ----------
if (!$openModal && !empty($_GET['edit'])) {
    $eid = (int) $_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id');
    $stmt->execute([':id' => $eid]);
    $row = normalize_customers_row($stmt->fetch() ?: null);
    if ($row) {
        $editRow   = $row;
        $openModal = true;
    }
}

// ---------- Search + pagination ----------
$q          = trim((string) ($_GET['q']        ?? ''));
$typeFilt   = $_GET['type']                    ?? '';
$compFilt   = $_GET['compliance']              ?? '';
$showInact  = !empty($_GET['inactive']);
$page       = max(1, (int) ($_GET['page']      ?? 1));
$perPage    = 50;
$offset     = ($page - 1) * $perPage;

if ($compFilt === 'account' && !$custAccountCols) {
    $compFilt = '';
}

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(name LIKE :q OR contact_person LIKE :q OR phone LIKE :q
                 OR email LIKE :q OR vat_number LIKE :q
                 OR sa_id_number LIKE :q OR company_reg_number LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($typeFilt === 'individual' || $typeFilt === 'business') {
    $where[] = 'type = :type';
    $params[':type'] = $typeFilt;
}
if ($compFilt === 'complete') {
    $where[] = '(id_doc_path IS NOT NULL AND has_proof_of_address = 1)';
} elseif ($compFilt === 'missing') {
    $where[] = '(id_doc_path IS NULL OR has_proof_of_address = 0)';
} elseif ($compFilt === 'account' && $custAccountCols) {
    $where[] = 'account_customer = 1';
}
if (!$showInact) {
    $where[] = 'is_active = 1';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM customers {$whereSql}");
$totalStmt->execute($params);
$total = (int) $totalStmt->fetchColumn();

$acctListSel = $custAccountCols ? ', account_customer, credit_limit_zar' : '';
$listStmt = $pdo->prepare(
    "SELECT id, type, name, contact_person, phone, email, vat_number,
            sa_id_number, company_reg_number,
            id_doc_path, has_proof_of_address, proof_of_address_path,
            is_active {$acctListSel}
       FROM customers {$whereSql}
       ORDER BY is_active DESC, name ASC
       LIMIT {$perPage} OFFSET {$offset}"
);
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

$pageTitle = 'Customers';
include __DIR__ . '/includes/header.php';

// Default modal values (for "Add new")
$mv = [
    'id'                    => 0,
    'type'                  => 'individual',
    'name'                  => '',
    'contact_person'        => '',
    'phone'                 => '',
    'email'                 => '',
    'billing_address'       => '',
    'delivery_address'      => '',
    'vat_number'            => '',
    'sa_id_number'          => '',
    'company_reg_number'    => '',
    'id_doc_path'           => null,
    'has_proof_of_address'  => 0,
    'proof_of_address_path' => null,
    'notes'                 => '',
    'is_active'             => 1,
    'account_customer'      => 0,
    'credit_limit_zar'      => null,
];
if ($editRow) {
    $mv = array_merge($mv, $editRow);
}
$mvId = (int) $mv['id'];

$custAdminReturnQs = $returnParamForForms !== ''
    ? http_build_query(['return' => $returnParamForForms])
    : '';

/**
 * Compliance score: 0/2, 1/2 or 2/2.
 *  - 1 point for an ID document on file (id_doc_path)
 *  - 1 point for a proof-of-address on file (proof_of_address_path
 *    + has_proof_of_address flag)
 */
function compliance_score(array $r): int {
    $s = 0;
    if (!empty($r['id_doc_path']))                                                $s++;
    if (!empty($r['has_proof_of_address']) && !empty($r['proof_of_address_path'])) $s++;
    return $s;
}
?>

<div class="container-fluid">
  <?php if ($returnInvoiceHref): ?>
    <div class="alert alert-primary py-2 d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <span class="small mb-0">
        <i class="bi bi-receipt-cutoff"></i>
        Draft invoice workflow: capture <strong>SHGA</strong> below, then return to <strong>POS</strong>.
        <?php if (!$returnInvoiceDraft): ?>
          <span class="badge bg-secondary ms-1">Invoice is no longer draft — finalize may already have run.</span>
        <?php endif; ?>
      </span>
      <a class="btn btn-sm btn-dark" href="<?= e($returnInvoiceHref) ?>">
        <i class="bi bi-arrow-left"></i> Back to invoice
      </a>
    </div>
  <?php endif; ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-1"><i class="bi bi-people-fill"></i> Customers</h1>
      <div class="text-muted small">
        <?= number_format($total) ?> result<?= $total === 1 ? '' : 's' ?>
        <?php if ($q !== ''): ?>for "<strong><?= e($q) ?></strong>"<?php endif; ?>
        <?php if ($typeFilt !== ''): ?> &middot; <?= e($typeFilt) ?> only<?php endif; ?>
        <?php if ($compFilt !== ''): ?>
          &middot; <?php if ($compFilt === 'account'): ?>account customers<?php else: ?>docs <?= e($compFilt) ?><?php endif; ?>
        <?php endif; ?>
        <?php if ($showInact): ?> &middot; including inactive<?php endif; ?>
      </div>
    </div>
    <div>
      <?php if ($canEdit): ?>
        <button type="button" class="btn btn-sm btn-danger"
                data-bs-toggle="modal" data-bs-target="#customerModal"
                onclick="cmReset();">
          <i class="bi bi-plus-lg"></i> Add customer
        </button>
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
    <div class="col-md-4">
      <input type="text" name="q" value="<?= e($q) ?>"
             class="form-control form-control-sm"
             placeholder="Search name / contact / phone / email / VAT / ID&hellip;">
    </div>
    <div class="col-md-2">
      <select name="type" class="form-select form-select-sm">
        <option value=""           <?= $typeFilt === ''           ? 'selected' : '' ?>>All types</option>
        <option value="individual" <?= $typeFilt === 'individual' ? 'selected' : '' ?>>Individuals</option>
        <option value="business"   <?= $typeFilt === 'business'   ? 'selected' : '' ?>>Businesses</option>
      </select>
    </div>
    <div class="col-md-2">
      <select name="compliance" class="form-select form-select-sm">
        <option value=""         <?= $compFilt === ''         ? 'selected' : '' ?>>Any docs</option>
        <option value="complete" <?= $compFilt === 'complete' ? 'selected' : '' ?>>Docs 2/2</option>
        <option value="missing"  <?= $compFilt === 'missing'  ? 'selected' : '' ?>>Docs missing</option>
        <?php if ($custAccountCols): ?>
          <option value="account" <?= $compFilt === 'account' ? 'selected' : '' ?>>Account customers</option>
        <?php endif; ?>
      </select>
    </div>
    <div class="col-md-2">
      <div class="form-check form-check-inline mt-1">
        <input class="form-check-input" type="checkbox" id="inactive" name="inactive" value="1"
               <?= $showInact ? 'checked' : '' ?>>
        <label class="form-check-label small" for="inactive">Show inactive</label>
      </div>
    </div>
    <div class="col-md-2 text-end">
      <button class="btn btn-sm btn-outline-dark" type="submit">
        <i class="bi bi-search"></i> Search
      </button>
      <?php if ($q !== '' || $typeFilt !== '' || $compFilt !== '' || $showInact): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(rtrim(APP_URL, '/') . '/customers_admin.php' . ($custAdminReturnQs !== '' ? '?' . $custAdminReturnQs : '')) ?>">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Type</th>
            <th>Name</th>
            <th>Contact</th>
            <th>Phone</th>
            <th>SA&nbsp;ID&nbsp;/&nbsp;CIPC</th>
            <th>Compliance</th>
            <?php if ($custAccountCols): ?><th>Account</th><?php endif; ?>
            <th>Status</th>
            <?php if ($canEdit): ?><th class="text-end">Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr>
              <td colspan="<?= 7 + ($custAccountCols ? 1 : 0) + ($canEdit ? 1 : 0) ?>" class="text-center text-muted py-4">
                No customers match.
              </td>
            </tr>
          <?php else: foreach ($rows as $c): ?>
            <?php
              $score   = compliance_score($c);
              $idValue = $c['type'] === 'business' ? $c['company_reg_number'] : $c['sa_id_number'];
            ?>
            <tr class="<?= $c['is_active'] ? '' : 'text-muted' ?>">
              <td>
                <span class="badge bg-<?= $c['type'] === 'business' ? 'primary' : 'secondary' ?>">
                  <?= e($c['type']) ?>
                </span>
              </td>
              <td><strong><?= e($c['name']) ?></strong>
                <a class="small ms-1 d-block d-md-inline" href="<?= e(APP_URL) ?>/customer_statement.php?id=<?= (int) $c['id'] ?>">Statement</a>
              </td>
              <td><?= e($c['contact_person']) ?></td>
              <td><?= e($c['phone']) ?></td>
              <td class="font-monospace small"><?= e($idValue) ?></td>
              <td>
                <?php if ($score === 2): ?>
                  <span class="badge bg-success" title="ID + proof of address on file">
                    <i class="bi bi-shield-check"></i> Docs 2/2
                  </span>
                <?php elseif ($score === 1): ?>
                  <span class="badge bg-warning text-dark"
                        title="<?= empty($c['id_doc_path']) ? 'Missing ID document' : 'Missing proof of address' ?>">
                    <i class="bi bi-exclamation-triangle"></i> Docs 1/2
                  </span>
                <?php else: ?>
                  <span class="badge bg-light text-muted border" title="No compliance docs on file">
                    Docs 0/2
                  </span>
                <?php endif; ?>
              </td>
              <?php if ($custAccountCols): ?>
                <td>
                  <?php if (!empty($c['account_customer'])): ?>
                    <span class="badge" style="background:#c8102e;">Account</span>
                  <?php else: ?>
                    <span class="text-muted small">—</span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
              <td>
                <?php if ($c['is_active']): ?>
                  <span class="badge bg-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Inactive</span>
                <?php endif; ?>
              </td>
              <?php if ($canEdit): ?>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-dark"
                     href="<?= e(rtrim(APP_URL, '/') . '/customers_admin.php?' . http_build_query(array_filter(['edit' => (int) $c['id'], 'return' => $returnParamForForms ?: null], fn ($x) => $x !== null && $x !== ''))) ?>"
                     title="Edit">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <form method="post" class="d-inline"
                        onsubmit="return confirm('<?= $c['is_active'] ? 'Deactivate' : 'Activate' ?> this customer?');">
                    <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="id"     value="<?= (int) $c['id'] ?>">
                    <?php if ($returnParamForForms !== ''): ?>
                      <input type="hidden" name="return" value="<?= e($returnParamForForms) ?>">
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-secondary" type="submit"
                            title="<?= $c['is_active'] ? 'Deactivate' : 'Activate' ?>">
                      <i class="bi bi-power"></i>
                    </button>
                  </form>
                </td>
              <?php endif; ?>
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
            'q'           => $q !== '' ? $q : null,
            'type'        => $typeFilt !== '' ? $typeFilt : null,
            'compliance'  => $compFilt !== '' ? $compFilt : null,
            'inactive'    => $showInact ? 1 : null,
            'return'      => $returnParamForForms !== '' ? $returnParamForForms : null,
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

<?php if ($canEdit): ?>
<!-- Add / Edit customer modal: entire .modal-content is one <form> so the red
     Save is a native type="submit" (no JS .click() on a hidden submit). -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form method="post" id="cm-form" class="modal-content" enctype="multipart/form-data">
      <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id"     value="<?= $mvId ?>">
      <?php if ($returnParamForForms !== ''): ?>
        <input type="hidden" name="return" value="<?= e($returnParamForForms) ?>">
      <?php endif; ?>
      <div class="modal-header">
        <h5 class="modal-title" id="cm-title">
          <?php if ($mvId > 0): ?>
            Edit customer #<?= $mvId ?>
          <?php else: ?>
            Add customer
          <?php endif; ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label small">Type</label>
              <select name="type" id="cm-type" class="form-select form-select-sm" onchange="cmTypeChanged();">
                <option value="individual" <?= $mv['type'] === 'individual' ? 'selected' : '' ?>>Individual</option>
                <option value="business"   <?= $mv['type'] === 'business'   ? 'selected' : '' ?>>Business</option>
              </select>
            </div>
            <div class="col-md-9">
              <label class="form-label small">Name *</label>
              <input class="form-control form-control-sm" name="name" required
                     value="<?= e($mv['name']) ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small">Contact person <small class="text-muted">(business only)</small></label>
              <input class="form-control form-control-sm" name="contact_person"
                     value="<?= e($mv['contact_person']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small">Phone</label>
              <input class="form-control form-control-sm" name="phone"
                     value="<?= e($mv['phone']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small">Email</label>
              <input class="form-control form-control-sm" name="email" type="email"
                     value="<?= e($mv['email']) ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small">Billing address</label>
              <textarea class="form-control form-control-sm" name="billing_address" rows="2"><?= e($mv['billing_address']) ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label small">Delivery address</label>
              <textarea class="form-control form-control-sm" name="delivery_address" rows="2"><?= e($mv['delivery_address']) ?></textarea>
            </div>

            <div class="col-md-4">
              <label class="form-label small">VAT / tax number</label>
              <input class="form-control form-control-sm" name="vat_number"
                     value="<?= e($mv['vat_number']) ?>">
            </div>
            <div class="col-md-8">
              <label class="form-label small">Notes</label>
              <input class="form-control form-control-sm" name="notes"
                     value="<?= e($mv['notes']) ?>">
            </div>

            <?php if ($custAccountCols): ?>
            <div class="col-12">
              <div class="p-3 rounded-1" style="background:#0a0a0a;color:#fff;border-left:4px solid #c8102e;">
                <div class="small text-uppercase fw-bold mb-2" style="color:#c8102e;">Account / POS</div>
                <p class="small mb-2" style="color:#e0e0e0;">
                  Account customers may have a <strong>due date</strong> on invoices. Without this flag, finalize blocks if a due date is set.
                </p>
                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" name="account_customer" value="1" id="cm-ac"
                         <?= !empty($mv['account_customer']) ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="cm-ac" style="color:#fff;">Account customer (on-account sales)</label>
                </div>
                <label class="form-label small" style="color:#b0b0b0;">Credit limit (ZAR, optional)</label>
                <input class="form-control form-control-sm" name="credit_limit_zar" id="cm-clim"
                       placeholder="e.g. 25000"
                       value="<?= isset($mv['credit_limit_zar']) && $mv['credit_limit_zar'] !== null && $mv['credit_limit_zar'] !== ''
                         ? e((string) $mv['credit_limit_zar']) : '' ?>">
              </div>
            </div>
            <?php endif; ?>

            <!-- ===================== COMPLIANCE DOCS ===================== -->
            <div class="col-12">
              <hr class="my-2">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-check text-danger"></i>
                <strong class="small text-uppercase">Compliance docs</strong>
                <span class="text-muted small">(SA Second-Hand Goods Act &mdash; required at sale time for stripped/used parts)</span>
              </div>
              <p class="small text-muted mb-2 mt-1">
                <em>These fields are optional on the customer record. Stage 5 POS will block any sale of a stripped or used part until the buyer's ID and proof of address are on file.</em>
                <br>
                <strong>Note:</strong> Choosing a file only queues it &mdash; press <strong>Save</strong> at the bottom to upload. The &ldquo;On file&rdquo; badge appears <em>after</em> a successful save.
              </p>
            </div>

            <!-- SA ID number (individuals) -->
            <div class="col-md-6 cm-individual-only">
              <label class="form-label small">SA ID number <small class="text-muted">(13 digits)</small></label>
              <input class="form-control form-control-sm" name="sa_id_number"
                     maxlength="40" placeholder="e.g. 8501015800089"
                     value="<?= e($mv['sa_id_number']) ?>">
            </div>

            <!-- CIPC reg number (businesses) -->
            <div class="col-md-6 cm-business-only">
              <label class="form-label small">CIPC company registration number</label>
              <input class="form-control form-control-sm" name="company_reg_number"
                     maxlength="40" placeholder="e.g. 2018/123456/07"
                     value="<?= e($mv['company_reg_number']) ?>">
            </div>

            <!-- ID / CIPC document upload -->
            <div class="col-md-6">
              <div class="card border h-100">
                <div class="card-body py-2 px-3">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-semibold">
                      <i class="bi bi-card-text"></i>
                      <span class="cm-individual-only">SA ID copy</span>
                      <span class="cm-business-only">CIPC certificate</span>
                    </span>
                    <?php if (!empty($mv['id_doc_path'])): ?>
                      <span class="badge bg-success">On file</span>
                    <?php else: ?>
                      <span class="badge bg-light text-muted border">Not uploaded</span>
                    <?php endif; ?>
                  </div>
                  <?php if ($mvId > 0): ?>
                    <?php if (!empty($mv['id_doc_path'])): ?>
                      <div class="small mb-1">
                        <a href="<?= e(uploads_public_url($mv['id_doc_path'])) ?>" target="_blank" rel="noopener">
                          <i class="bi bi-eye"></i> View current scan
                        </a>
                      </div>
                    <?php endif; ?>
                    <label class="form-label small mb-0 mt-1">
                      <?= !empty($mv['id_doc_path']) ? 'Replace scan' : 'Upload scan' ?>
                    </label>
                    <input type="file" name="id_doc_file"
                           accept=".pdf,.jpg,.jpeg,.png"
                           class="form-control form-control-sm">
                    <?php if (!empty($mv['id_doc_path'])): ?>
                      <button type="button"
                              class="btn btn-link btn-sm text-danger p-0 mt-1"
                              onclick="cmDeleteDoc(<?= $mvId ?>, 'id_doc');">
                        <i class="bi bi-trash"></i> Remove this scan
                      </button>
                    <?php endif; ?>
                  <?php else: ?>
                    <p class="small text-muted mb-0">
                      <em>Upload becomes available after the first save.</em>
                    </p>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Proof of address upload -->
            <div class="col-md-6">
              <div class="card border h-100">
                <div class="card-body py-2 px-3">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-semibold">
                      <i class="bi bi-house-door"></i> Proof of address
                    </span>
                    <?php if (!empty($mv['proof_of_address_path'])): ?>
                      <span class="badge bg-success">On file</span>
                    <?php else: ?>
                      <span class="badge bg-light text-muted border">Not uploaded</span>
                    <?php endif; ?>
                  </div>
                  <?php if ($mvId > 0): ?>
                    <?php if (!empty($mv['proof_of_address_path'])): ?>
                      <div class="small mb-1">
                        <a href="<?= e(uploads_public_url($mv['proof_of_address_path'])) ?>" target="_blank" rel="noopener">
                          <i class="bi bi-eye"></i> View current scan
                        </a>
                      </div>
                    <?php endif; ?>
                    <label class="form-label small mb-0 mt-1">
                      <?= !empty($mv['proof_of_address_path']) ? 'Replace scan' : 'Upload scan' ?>
                    </label>
                    <div class="form-check form-check-sm small mb-1">
                      <input class="form-check-input" type="checkbox"
                             id="cm-hpoa" name="has_proof_of_address" value="1"
                             <?= !empty($mv['has_proof_of_address']) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="cm-hpoa">
                        I have this document on file
                      </label>
                    </div>
                    <input type="file" name="proof_of_address_file"
                           accept=".pdf,.jpg,.jpeg,.png"
                           class="form-control form-control-sm">
                    <?php if (!empty($mv['proof_of_address_path'])): ?>
                      <button type="button"
                              class="btn btn-link btn-sm text-danger p-0 mt-1"
                              onclick="cmDeleteDoc(<?= $mvId ?>, 'proof_of_address');">
                        <i class="bi bi-trash"></i> Remove this scan
                      </button>
                    <?php endif; ?>
                  <?php else: ?>
                    <p class="small text-muted mb-0">
                      <em>Upload becomes available after the first save.</em>
                    </p>
                    <input type="hidden" name="has_proof_of_address" value="0">
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <!-- =================== /COMPLIANCE DOCS ===================== -->

            <div class="col-12">
              <hr class="my-2">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="cm-active"
                       name="is_active" value="1" <?= $mv['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="cm-active">Active (visible in lists)</label>
              </div>
            </div>
          </div>
      </div>
      <div class="modal-footer d-flex flex-wrap gap-2">
        <?php $cmCloseHref = rtrim(APP_URL, '/') . '/customers_admin.php' . ($custAdminReturnQs !== '' ? '?' . $custAdminReturnQs : ''); ?>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <button type="button" class="btn btn-link p-0 text-decoration-none" data-bs-dismiss="modal">
            <?= $returnInvoiceHref ? 'Close' : 'Cancel' ?>
          </button>
          <?php if ($returnInvoiceHref): ?>
            <span class="text-muted small">&middot;</span>
            <a class="small" href="<?= e($cmCloseHref) ?>">Refresh list only</a>
          <?php endif; ?>
        </div>
        <div class="ms-auto d-flex flex-wrap gap-2">
          <?php if ($returnInvoiceHref): ?>
            <a class="btn btn-outline-dark" href="<?= e($returnInvoiceHref) ?>">
              <i class="bi bi-arrow-left"></i> Back to invoice<?= $returnInvoiceId ? ' #' . (int) $returnInvoiceId : '' ?>
            </a>
          <?php endif; ?>
          <button class="btn btn-danger" type="submit" id="cm-save-btn">
            <i class="bi bi-save"></i> Save
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Hidden helper form for the "remove this scan" buttons. Lives outside
     the modal so it doesn't interfere with the main modal's flex layout. -->
<form id="cm-delete-form" method="post" class="d-none">
  <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="delete_doc">
  <input type="hidden" name="id"     value="">
  <input type="hidden" name="which"  value="">
  <?php if ($returnParamForForms !== ''): ?>
    <input type="hidden" name="return" value="<?= e($returnParamForForms) ?>">
  <?php endif; ?>
</form>

<script>
  function cmTypeChanged() {
    const type = document.getElementById('cm-type').value;
    document.querySelectorAll('.cm-individual-only').forEach(el => {
      el.style.display = (type === 'individual') ? '' : 'none';
    });
    document.querySelectorAll('.cm-business-only').forEach(el => {
      el.style.display = (type === 'business') ? '' : 'none';
    });
  }

  function cmReset() {
    const f = document.getElementById('cm-form');
    if (f) f.reset();
    const idField = document.querySelector('#customerModal [name=id]');
    if (idField) idField.value = '';
    const active = document.querySelector('#customerModal [name=is_active]');
    if (active) active.checked = true;
    const typeSel = document.getElementById('cm-type');
    if (typeSel) typeSel.value = 'individual';
    cmTypeChanged();
    document.getElementById('cm-title').textContent = 'Add customer';
    const ac = document.getElementById('cm-ac');
    if (ac) ac.checked = false;
    const clim = document.getElementById('cm-clim');
    if (clim) clim.value = '';
  }

  function cmDeleteDoc(id, which) {
    if (!confirm('Remove this scan? This cannot be undone.')) return;
    const f = document.getElementById('cm-delete-form');
    f.querySelector('[name=id]').value = id;
    f.querySelector('[name=which]').value = which;
    f.submit();
  }

  document.addEventListener('DOMContentLoaded', function () {
    cmTypeChanged();
  });
</script>

<?php if ($openModal): ?>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const m = new bootstrap.Modal(document.getElementById('customerModal'));
    cmTypeChanged();
    m.show();
  });
</script>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

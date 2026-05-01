<?php
/**
 * Stage 4c/4d — Supplier purchase (batch) + optional accounts payable (bill + payments, ZAR).
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/uploads.php';

$canEdit = user_has_role('owner', 'admin', 'manager', 'staff');

$apReady = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.`TABLES`
     WHERE table_schema = DATABASE() AND table_name = 'supplier_purchase_payments'"
)->fetchColumn() > 0
    && (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.`COLUMNS`
         WHERE table_schema = DATABASE() AND table_name = 'supplier_purchases' AND column_name = 'bill_amount'"
    )->fetchColumn() > 0;

$id    = (int) ($_GET['id'] ?? 0);
$isNew = $id === 0;
$flash = ['type' => null, 'msg' => null];

$supplierList = $pdo->query(
    "SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC"
)->fetchAll();

$row = [
    'id' => 0,
    'supplier_id' => null,
    'seller_name' => '',
    'seller_phone' => '',
    'seller_id_number' => '',
    'has_tpp_id_doc' => 0,
    'tpp_id_doc_path' => null,
    'has_tpp_proof_of_address' => 0,
    'tpp_proof_of_address_path' => null,
    'purchase_ref' => '',
    'notes' => '',
    'bill_amount' => null,
    'bill_date'   => null,
    'due_date'    => null,
];

$payRows = [];

$intakeFound = true;
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM supplier_purchases WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $loaded = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$loaded) {
        http_response_code(404);
        $flash = ['type' => 'danger', 'msg' => 'Purchase not found.'];
        $intakeFound = false;
    } else {
        $row = array_merge($row, array_change_key_case($loaded, CASE_LOWER));
    }
}

$showIntakeForm = $isNew || ($id > 0 && $intakeFound);

$partsInBatch = [];
if ($id > 0 && $intakeFound) {
    $stmt = $pdo->prepare(
        'SELECT id, sku, name, asking_price, status, condition_grade, source
         FROM parts WHERE supplier_purchase_id = :i AND is_active = 1 ORDER BY id ASC'
    );
    $stmt->execute([':i' => $id]);
    $partsInBatch = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($id > 0 && $intakeFound && $apReady) {
    $st = $pdo->prepare(
        'SELECT * FROM supplier_purchase_payments
         WHERE supplier_purchase_id = :i AND is_active = 1
         ORDER BY paid_at DESC, id DESC'
    );
    $st->execute([':i' => $id]);
    $payRows = $st->fetchAll(PDO::FETCH_ASSOC);
}

$tabSupplier = !empty($row['supplier_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = ['type' => 'danger', 'msg' => 'Security token invalid.'];
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'save') {
                $tppSupId   = (int) ($_POST['tpp_supplier_id'] ?? 0);
                $sellerName = trim((string) ($_POST['seller_name'] ?? ''));
                $sellerPh   = trim((string) ($_POST['seller_phone'] ?? ''));
                $sellerId   = trim((string) ($_POST['seller_id_number'] ?? ''));
                $purchaseRef = trim((string) ($_POST['purchase_ref'] ?? ''));
                $notes       = trim((string) ($_POST['notes'] ?? ''));

                if ($tppSupId > 0) {
                    $supplierId = $tppSupId;
                    $sellerName = $sellerPh = $sellerId = '';
                } else {
                    $supplierId = 0;
                }

                if ($supplierId <= 0 && $sellerName === '') {
                    throw new RuntimeException('Pick a supplier or enter a private seller name.');
                }

                $hasPoa = !empty($_POST['has_tpp_proof_of_address']) ? 1 : 0;

                $billAmt  = null;
                $billDate = null;
                $dueDate  = null;
                if ($apReady) {
                    $bRaw = trim((string) ($_POST['bill_amount'] ?? ''));
                    if ($bRaw !== '') {
                        $billAmt = round((float) preg_replace('/[^\d.]/', '', $bRaw), 2);
                        if ($billAmt < 0) {
                            throw new RuntimeException('Bill amount cannot be negative.');
                        }
                    }
                    $bd = trim((string) ($_POST['bill_date'] ?? ''));
                    $billDate = ($bd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $bd)) ? $bd : null;
                    $dd = trim((string) ($_POST['due_date'] ?? ''));
                    $dueDate = ($dd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dd)) ? $dd : null;
                }

                if ($id > 0) {
                    $oldId = $row['tpp_id_doc_path'] ?? null;
                    $oldPoa = $row['tpp_proof_of_address_path'] ?? null;
                    $tid = $oldId;
                    $tpoa = $oldPoa;
                    if (!$hasPoa && $oldPoa) {
                        delete_uploaded_file($oldPoa);
                        $tpoa = null;
                    }
                    $hasIdDoc = !empty($tid) ? 1 : 0;

                    if ($apReady) {
                        $pdo->prepare(
                            'UPDATE supplier_purchases SET
                               supplier_id = :sid,
                               seller_name = :snm, seller_phone = :sph, seller_id_number = :sidn,
                               has_tpp_id_doc = :hid, tpp_id_doc_path = :tidp,
                               has_tpp_proof_of_address = :hpoa, tpp_proof_of_address_path = :tpoa,
                               purchase_ref = :pref, notes = :nt,
                               bill_amount = :ba, bill_date = :bd, due_date = :dd
                             WHERE id = :id'
                        )->execute([
                            ':sid'  => $supplierId > 0 ? $supplierId : null,
                            ':snm'  => $sellerName !== '' ? $sellerName : null,
                            ':sph'  => $sellerPh !== '' ? $sellerPh : null,
                            ':sidn' => $sellerId !== '' ? $sellerId : null,
                            ':hid'  => $hasIdDoc,
                            ':tidp' => $tid,
                            ':hpoa' => $hasPoa,
                            ':tpoa' => $tpoa,
                            ':pref' => $purchaseRef !== '' ? $purchaseRef : null,
                            ':nt'   => $notes !== '' ? $notes : null,
                            ':ba'   => $billAmt,
                            ':bd'   => $billDate,
                            ':dd'   => $dueDate,
                            ':id'   => $id,
                        ]);
                    } else {
                        $pdo->prepare(
                            'UPDATE supplier_purchases SET
                               supplier_id = :sid,
                               seller_name = :snm, seller_phone = :sph, seller_id_number = :sidn,
                               has_tpp_id_doc = :hid, tpp_id_doc_path = :tidp,
                               has_tpp_proof_of_address = :hpoa, tpp_proof_of_address_path = :tpoa,
                               purchase_ref = :pref, notes = :nt
                             WHERE id = :id'
                        )->execute([
                            ':sid'  => $supplierId > 0 ? $supplierId : null,
                            ':snm'  => $sellerName !== '' ? $sellerName : null,
                            ':sph'  => $sellerPh !== '' ? $sellerPh : null,
                            ':sidn' => $sellerId !== '' ? $sellerId : null,
                            ':hid'  => $hasIdDoc,
                            ':tidp' => $tid,
                            ':hpoa' => $hasPoa,
                            ':tpoa' => $tpoa,
                            ':pref' => $purchaseRef !== '' ? $purchaseRef : null,
                            ':nt'   => $notes !== '' ? $notes : null,
                            ':id'   => $id,
                        ]);
                    }

                    if (!empty($_FILES['tpp_id_doc_file']['name'])) {
                        if ($oldId) {
                            delete_uploaded_file($oldId);
                        }
                        $rel = save_uploaded_supplier_purchase_doc($_FILES['tpp_id_doc_file'], $id, 'tpp_id_doc');
                        $pdo->prepare(
                            'UPDATE supplier_purchases SET tpp_id_doc_path = :p, has_tpp_id_doc = 1 WHERE id = :id'
                        )->execute([':p' => $rel, ':id' => $id]);
                    }
                    if (!empty($_FILES['tpp_proof_of_address_file']['name'])) {
                        if ($oldPoa) {
                            delete_uploaded_file($oldPoa);
                        }
                        $rel = save_uploaded_supplier_purchase_doc(
                            $_FILES['tpp_proof_of_address_file'],
                            $id,
                            'tpp_proof_of_address'
                        );
                        $pdo->prepare(
                            'UPDATE supplier_purchases SET tpp_proof_of_address_path = :p, has_tpp_proof_of_address = 1 WHERE id = :id'
                        )->execute([':p' => $rel, ':id' => $id]);
                    }

                    $flash = ['type' => 'success', 'msg' => 'Purchase saved.'];
                } else {
                    if ($apReady) {
                        $pdo->prepare(
                            'INSERT INTO supplier_purchases
                              (supplier_id, seller_name, seller_phone, seller_id_number,
                               has_tpp_id_doc, tpp_id_doc_path, has_tpp_proof_of_address, tpp_proof_of_address_path,
                               purchase_ref, notes, bill_amount, bill_date, due_date, created_by)
                             VALUES
                              (:sid, :snm, :sph, :sidn, 0, NULL, :hpoa, NULL, :pref, :nt, :ba, :bd, :dd, :cb)'
                        )->execute([
                            ':sid'  => $supplierId > 0 ? $supplierId : null,
                            ':snm'  => $sellerName !== '' ? $sellerName : null,
                            ':sph'  => $sellerPh !== '' ? $sellerPh : null,
                            ':sidn' => $sellerId !== '' ? $sellerId : null,
                            ':hpoa' => $hasPoa,
                            ':pref' => $purchaseRef !== '' ? $purchaseRef : null,
                            ':nt'   => $notes !== '' ? $notes : null,
                            ':ba'   => $billAmt,
                            ':bd'   => $billDate,
                            ':dd'   => $dueDate,
                            ':cb'   => $_SESSION['user_id'] ?? null,
                        ]);
                    } else {
                        $pdo->prepare(
                            'INSERT INTO supplier_purchases
                              (supplier_id, seller_name, seller_phone, seller_id_number,
                               has_tpp_id_doc, tpp_id_doc_path, has_tpp_proof_of_address, tpp_proof_of_address_path,
                               purchase_ref, notes, created_by)
                             VALUES
                              (:sid, :snm, :sph, :sidn, 0, NULL, :hpoa, NULL, :pref, :nt, :cb)'
                        )->execute([
                            ':sid'  => $supplierId > 0 ? $supplierId : null,
                            ':snm'  => $sellerName !== '' ? $sellerName : null,
                            ':sph'  => $sellerPh !== '' ? $sellerPh : null,
                            ':sidn' => $sellerId !== '' ? $sellerId : null,
                            ':hpoa' => $hasPoa,
                            ':pref' => $purchaseRef !== '' ? $purchaseRef : null,
                            ':nt'   => $notes !== '' ? $notes : null,
                            ':cb'   => $_SESSION['user_id'] ?? null,
                        ]);
                    }
                    $newId = (int) $pdo->lastInsertId();
                    if (!empty($_FILES['tpp_id_doc_file']['name'])) {
                        $rel = save_uploaded_supplier_purchase_doc(
                            $_FILES['tpp_id_doc_file'],
                            $newId,
                            'tpp_id_doc'
                        );
                        $pdo->prepare(
                            'UPDATE supplier_purchases SET tpp_id_doc_path = :p, has_tpp_id_doc = 1 WHERE id = :id'
                        )->execute([':p' => $rel, ':id' => $newId]);
                    }
                    if (!empty($_FILES['tpp_proof_of_address_file']['name'])) {
                        $rel = save_uploaded_supplier_purchase_doc(
                            $_FILES['tpp_proof_of_address_file'],
                            $newId,
                            'tpp_proof_of_address'
                        );
                        $pdo->prepare(
                            'UPDATE supplier_purchases SET tpp_proof_of_address_path = :p, has_tpp_proof_of_address = 1 WHERE id = :id'
                        )->execute([':p' => $rel, ':id' => $newId]);
                    }
                    header('Location: ' . APP_URL . '/supplier_purchase_edit.php?id=' . $newId . '&saved=1');
                    exit;
                }

                $stmt = $pdo->prepare('SELECT * FROM supplier_purchases WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $row = array_merge($row, array_change_key_case($stmt->fetch(PDO::FETCH_ASSOC) ?: [], CASE_LOWER));
                $tabSupplier = !empty($row['supplier_id']);
            } elseif ($action === 'add_payment' && $apReady && $id > 0) {
                $payAmt = round((float) preg_replace('/[^\d.]/', '', (string) ($_POST['pay_amount'] ?? '0')), 2);
                if ($payAmt <= 0) {
                    throw new RuntimeException('Enter a positive payment amount.');
                }
                $pAt = trim((string) ($_POST['pay_paid_at'] ?? ''));
                if ($pAt === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pAt)) {
                    throw new RuntimeException('Pick a valid payment date.');
                }
                $pMethod = (string) ($_POST['pay_method'] ?? 'eft');
                if (!in_array($pMethod, ['eft', 'cash', 'card', 'other'], true)) {
                    $pMethod = 'eft';
                }
                $pRef  = trim((string) ($_POST['pay_ref'] ?? ''));
                $pNote = trim((string) ($_POST['pay_note'] ?? ''));

                $st = $pdo->prepare('SELECT bill_amount FROM supplier_purchases WHERE id = :id AND is_active = 1');
                $st->execute([':id' => $id]);
                $bill = $st->fetchColumn();
                if ($bill === null || (float) $bill <= 0) {
                    throw new RuntimeException('Set a bill amount (ZAR) on this purchase before recording payments.');
                }
                $sumSt = $pdo->prepare(
                    'SELECT COALESCE(SUM(amount),0) FROM supplier_purchase_payments
                     WHERE supplier_purchase_id = :i AND is_active = 1'
                );
                $sumSt->execute([':i' => $id]);
                $paidSoFar = (float) $sumSt->fetchColumn();
                if ($paidSoFar + $payAmt - (float) $bill > 0.01) {
                    $rem = max(0, (float) $bill - $paidSoFar);
                    throw new RuntimeException('Payment is more than the remaining balance. Remaining: R ' . number_format($rem, 2));
                }
                $pdo->prepare(
                    'INSERT INTO supplier_purchase_payments
                      (supplier_purchase_id, amount, paid_at, payment_method, reference_note, notes, created_by)
                     VALUES
                      (:pid, :am, :dt, :pm, :ref, :nt, :cb)'
                )->execute([
                    ':pid' => $id,
                    ':am'  => $payAmt,
                    ':dt'  => $pAt,
                    ':pm'  => $pMethod,
                    ':ref' => $pRef !== '' ? $pRef : null,
                    ':nt'  => $pNote !== '' ? $pNote : null,
                    ':cb'  => $_SESSION['user_id'] ?? null,
                ]);
                $flash = ['type' => 'success', 'msg' => 'Payment recorded.'];
            } elseif ($action === 'toggle_payment' && $apReady && $id > 0) {
                $pid = (int) ($_POST['payment_id'] ?? 0);
                if ($pid <= 0) {
                    throw new RuntimeException('Invalid payment.');
                }
                $ch = $pdo->prepare(
                    'SELECT id FROM supplier_purchase_payments WHERE id = :p AND supplier_purchase_id = :i'
                );
                $ch->execute([':p' => $pid, ':i' => $id]);
                if (!$ch->fetchColumn()) {
                    throw new RuntimeException('Payment not found.');
                }
                $pdo->prepare('UPDATE supplier_purchase_payments SET is_active = 0 WHERE id = :p')->execute([':p' => $pid]);
                $flash = ['type' => 'success', 'msg' => 'Payment removed.'];
            }
            if ($id > 0 && $apReady) {
                $stmt = $pdo->prepare('SELECT * FROM supplier_purchases WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $f = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($f) {
                    $row = array_merge(
                        $row,
                        array_change_key_case($f, CASE_LOWER)
                    );
                }
                $st = $pdo->prepare(
                    'SELECT * FROM supplier_purchase_payments
                     WHERE supplier_purchase_id = :i AND is_active = 1
                     ORDER BY paid_at DESC, id DESC'
                );
                $st->execute([':i' => $id]);
                $payRows = $st->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            $flash = ['type' => 'danger', 'msg' => APP_DEBUG ? $e->getMessage() : 'Operation failed.'];
        }
    }
}

if (!empty($_GET['saved'])) {
    $flash = [
        'type' => 'success',
        'msg'  => 'Purchase created. Add parts below — each line can be OEM, replacement, or third-party (supplier) or only third-party (private).',
    ];
}

$heading = $isNew ? 'New supplier purchase' : 'Supplier purchase #' . (int) $row['id'];
$pageTitle = $isNew ? 'New supplier purchase' : 'Purchase #' . $id;

$SOURCE_LINE_LABELS = [
    'stripped'    => 'Stripped',
    'oem_new'     => 'OEM new',
    'replacement' => 'Replacement',
    'third_party' => 'Third-party',
];

$paidSum = 0.0;
foreach ($payRows as $_p) {
    $paidSum += (float) $_p['amount'];
}
$billNum = (isset($row['bill_amount']) && $row['bill_amount'] !== null && $row['bill_amount'] !== '') ? (float) $row['bill_amount'] : null;
$balanceNum = $billNum !== null ? $billNum - $paidSum : null;

include __DIR__ . '/includes/header.php';
?>

<div class="container py-3" style="max-width: 900px;">
  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h1 class="h4 mb-1">
        <i class="bi bi-boxes"></i> <?= e($heading) ?>
      </h1>
      <p class="text-muted small mb-0">
        Enter <strong>who you bought from</strong> and <strong>compliance (private sellers)</strong> once. Then
        <strong>Add part to this purchase</strong> for each line — for <strong>supplier</strong> buys you can
        set each part as OEM new, replacement, or third-party; <strong>private</strong> buys are third-party per line.
      </p>
    </div>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(APP_URL) ?>/supplier_purchases_admin.php">All purchases</a>
  </div>

  <?php if ($flash['msg']): ?>
    <div class="alert alert-<?= e($flash['type']) ?> py-2"><?= e($flash['msg']) ?></div>
  <?php endif; ?>

  <?php if ($showIntakeForm): ?>
  <form method="post" enctype="multipart/form-data" id="intakeForm">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">

    <div class="card mb-3 shadow-sm">
      <div class="card-header bg-light"><strong>Reference</strong></div>
      <div class="card-body row g-2">
        <div class="col-md-6">
          <label class="form-label small">Invoice / bundle name <span class="text-muted">(optional)</span></label>
          <input type="text" name="purchase_ref" class="form-control" value="<?= e($row['purchase_ref'] ?? '') ?>"
                 placeholder="e.g. INV-2026-041" <?= $canEdit ? '' : 'readonly' ?>>
        </div>
        <div class="col-12">
          <label class="form-label small">Notes</label>
          <textarea name="notes" class="form-control" rows="2" <?= $canEdit ? '' : 'readonly' ?>><?= e($row['notes'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <?php if ($apReady): ?>
    <div class="card mb-3 shadow-sm border-secondary">
      <div class="card-header bg-light"><strong>Bill (ZAR)</strong> — what you owe for this buy</div>
      <div class="card-body row g-2">
        <div class="col-md-4">
          <label class="form-label small">Bill / invoice amount</label>
          <div class="input-group">
            <span class="input-group-text">R</span>
            <input type="text" name="bill_amount" class="form-control" inputmode="decimal"
                   value="<?= $row['bill_amount'] !== null && $row['bill_amount'] !== '' ? e(number_format((float) $row['bill_amount'], 2, '.', '')) : '' ?>"
                   placeholder="e.g. 5000.00" <?= $canEdit ? '' : 'readonly' ?>>
          </div>
          <div class="form-text small">Set when you know the supplier’s or seller’s total. You can change it later.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label small">Bill / invoice date</label>
          <input type="date" name="bill_date" class="form-control" value="<?= e($row['bill_date'] && $row['bill_date'] !== '0000-00-00' ? (string) $row['bill_date'] : '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
        </div>
        <div class="col-md-4">
          <label class="form-label small">Due date <span class="text-muted">(optional)</span></label>
          <input type="date" name="due_date" class="form-control" value="<?= e($row['due_date'] && $row['due_date'] !== '0000-00-00' ? (string) $row['due_date'] : '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="alert alert-light border small">
      <strong>Accounts payable</strong> will appear here after you run <code>sql/04d_supplier_accounts_payable.sql</code> in phpMyAdmin.
    </div>
    <?php endif; ?>

    <div class="card mb-3 shadow-sm">
      <div class="card-header bg-light"><strong>Supplier or private seller</strong></div>
      <div class="card-body">
        <ul class="nav nav-pills mb-2">
          <li class="nav-item">
            <button type="button" class="nav-link <?= $tabSupplier ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tiSup">Registered supplier</button>
          </li>
          <li class="nav-item">
            <button type="button" class="nav-link <?= !$tabSupplier ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tiPriv">Private individual</button>
          </li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade <?= $tabSupplier ? 'show active' : '' ?>" id="tiSup">
            <select name="tpp_supplier_id" class="form-select" <?= $canEdit ? '' : 'disabled' ?>>
              <option value="0">— pick supplier —</option>
              <?php foreach ($supplierList as $sp): ?>
                <option value="<?= (int) $sp['id'] ?>" <?= (int) ($row['supplier_id'] ?? 0) === (int) $sp['id'] ? 'selected' : '' ?>>
                  <?= e($sp['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="form-text small mb-0">Then add many parts per line: OEM, replacement, or third-party with this supplier on file.</p>
          </div>
          <div class="tab-pane fade <?= !$tabSupplier ? 'show active' : '' ?>" id="tiPriv">
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label small">Name</label>
                <input type="text" name="seller_name" class="form-control" value="<?= e($row['seller_name'] ?? '') ?>"
                       <?= $canEdit ? '' : 'readonly' ?>>
              </div>
              <div class="col-md-3">
                <label class="form-label small">SA ID</label>
                <input type="text" name="seller_id_number" class="form-control" value="<?= e($row['seller_id_number'] ?? '') ?>"
                       <?= $canEdit ? '' : 'readonly' ?>>
              </div>
              <div class="col-md-3">
                <label class="form-label small">Phone</label>
                <input type="text" name="seller_phone" class="form-control" value="<?= e($row['seller_phone'] ?? '') ?>"
                       <?= $canEdit ? '' : 'readonly' ?>>
              </div>
            </div>
            <p class="form-text small mb-0">Each part line will be <strong>third-party</strong>; use scans below for SHGA where required.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3 shadow-sm">
      <div class="card-header bg-light"><strong>ID / address on file</strong> <span class="text-muted">(typical for private sellers)</span></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="small fw-bold mb-1">ID / CIPC scan</div>
            <?php if (!empty($row['tpp_id_doc_path'])): ?>
              <div class="small text-success mb-1">
                <a href="<?= e(uploads_public_url($row['tpp_id_doc_path'])) ?>" target="_blank" rel="noopener">View on file</a>
              </div>
            <?php endif; ?>
            <?php if ($canEdit): ?>
              <input type="file" name="tpp_id_doc_file" class="form-control form-control-sm" accept=".pdf,image/*">
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <div class="small fw-bold mb-1">Proof of residence</div>
            <?php if (!empty($row['tpp_proof_of_address_path'])): ?>
              <div class="small text-success mb-1">
                <a href="<?= e(uploads_public_url($row['tpp_proof_of_address_path'])) ?>" target="_blank" rel="noopener">View on file</a>
              </div>
            <?php endif; ?>
            <?php if ($canEdit): ?>
              <div class="form-check mb-1">
                <input class="form-check-input" type="checkbox" name="has_tpp_proof_of_address" value="1" id="tiPoa"
                       <?= !empty($row['has_tpp_proof_of_address']) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="tiPoa">I have this on file (after upload)</label>
              </div>
              <input type="file" name="tpp_proof_of_address_file" class="form-control form-control-sm" accept=".pdf,image/*">
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <?php if ($canEdit): ?>
      <div class="mb-3">
        <button type="submit" class="btn btn-danger">
          <i class="bi bi-check-lg"></i> <?= $isNew ? 'Create purchase' : 'Save purchase' ?>
        </button>
      </div>
    <?php endif; ?>
  </form>

  <?php
  $todayJhb = (new DateTime('now', new DateTimeZone('Africa/Johannesburg')))->format('Y-m-d');
  ?>
  <?php if ($id > 0 && $apReady && (int) ($row['id'] ?? 0) === $id): ?>
  <div class="card mb-3 shadow-sm border-primary">
    <div class="card-header bg-light d-flex justify-content-between flex-wrap">
      <strong><i class="bi bi-calculator"></i> Payments (ZAR)</strong>
      <?php if ($billNum !== null): ?>
        <span class="small">
          Remaining: <strong class="text-danger">R <?= number_format((float) $balanceNum, 2) ?></strong>
          · Bill: R <?= number_format($billNum, 2) ?>
          · Paid: R <?= number_format($paidSum, 2) ?>
        </span>
      <?php else: ?>
        <span class="text-muted small">Set the bill amount above, then record payments here.</span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if ($payRows): ?>
        <div class="table-responsive mb-3">
          <table class="table table-sm align-middle">
            <thead class="table-light small">
              <tr><th>Date</th><th class="text-end">Amount</th><th>Method</th><th>Ref / note</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($payRows as $pr): ?>
                <tr>
                  <td><?= e($pr['paid_at']) ?></td>
                  <td class="text-end">R <?= number_format((float) $pr['amount'], 2) ?></td>
                  <td class="text-capitalize small"><?= e($pr['payment_method']) ?></td>
                  <td class="small text-muted">
                    <?php if (!empty($pr['reference_note'])): ?><?= e($pr['reference_note']) ?><?php endif; ?>
                    <?php if (!empty($pr['notes'])): ?><span class="d-block"><?= e($pr['notes']) ?></span><?php endif; ?>
                  </td>
                  <td>
                    <?php if ($canEdit): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Remove this payment line?');">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="toggle_payment">
                      <input type="hidden" name="payment_id" value="<?= (int) $pr['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">×</button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-muted small">No payments recorded yet.</p>
      <?php endif; ?>
      <?php if ($canEdit && $billNum !== null && (float) $balanceNum > 0.005): ?>
        <form method="post" class="row g-2 align-items-end small">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="add_payment">
          <div class="col-md-2 col-6">
            <label class="form-label">Amount</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">R</span>
              <input name="pay_amount" class="form-control" inputmode="decimal" required>
            </div>
          </div>
          <div class="col-md-2 col-6">
            <label class="form-label">Paid on</label>
            <input type="date" name="pay_paid_at" class="form-control form-control-sm" value="<?= e($todayJhb) ?>" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Method</label>
            <select name="pay_method" class="form-select form-select-sm">
              <option value="eft">EFT</option>
              <option value="cash">Cash</option>
              <option value="card">Card</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Bank ref (optional)</label>
            <input name="pay_ref" class="form-control form-control-sm" placeholder="Same ref for split EFTs">
          </div>
          <div class="col-md-3">
            <label class="form-label">Note</label>
            <input name="pay_note" class="form-control form-control-sm" placeholder="Optional">
          </div>
          <div class="col-md-1">
            <button type="submit" class="btn btn-sm btn-primary w-100">Add</button>
          </div>
        </form>
        <p class="form-text small mb-0">One EFT for two purchases? Log two lines with the <strong>same bank ref</strong> on each purchase page.</p>
      <?php elseif ($canEdit && $billNum === null): ?>
        <p class="text-muted small mb-0">Save a bill amount in the <strong>Bill (ZAR)</strong> section, then you can add payments here.</p>
      <?php elseif ($canEdit && (float) $balanceNum <= 0.005 && $billNum !== null): ?>
        <p class="text-success small mb-0"><i class="bi bi-check-circle"></i> Bill fully paid (within 1c).</p>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <script>
  document.getElementById('intakeForm')?.addEventListener('submit', function() {
    document.querySelectorAll('#tiSup, #tiPriv').forEach(function(p) {
      p.classList.add('show', 'active');
      p.style.setProperty('display', 'block', 'important');
    });
  });
  </script>
  <?php endif; ?>

  <?php if ($id > 0 && $intakeFound && (int) ($row['id'] ?? 0) === $id): ?>
    <div class="card border-danger shadow-sm">
      <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-plus-lg"></i> Parts in this purchase</strong>
        <span class="badge bg-danger"><?= count($partsInBatch) ?> part(s)</span>
      </div>
      <div class="card-body">
        <?php if ($partsInBatch): ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr><th>SKU</th><th>Name</th><th>Line type</th><th>Asking</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($partsInBatch as $p):
                  $srcL = $SOURCE_LINE_LABELS[$p['source'] ?? ''] ?? ($p['source'] ?? '—');
                ?>
                  <tr>
                    <td class="font-monospace small"><?= e($p['sku']) ?></td>
                    <td><?= e($p['name']) ?></td>
                    <td class="small"><?= e($srcL) ?></td>
                    <td>R <?= number_format((float) $p['asking_price'], 2) ?></td>
                    <td>
                      <a class="btn btn-sm btn-outline-dark" href="<?= e(APP_URL) ?>/part_edit.php?id=<?= (int) $p['id'] ?>">Edit</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted small mb-2">No parts yet. Add the first line item.</p>
        <?php endif; ?>
        <a class="btn btn-danger" href="<?= e(APP_URL) ?>/part_edit.php?supplier_purchase_id=<?= (int) $id ?>">
          <i class="bi bi-plus-lg"></i> Add part to this purchase
        </a>
        <p class="small text-muted mt-2 mb-0">Seller + ID scans stay on the purchase. You only set part name, line type, price, and yard for each part.</p>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

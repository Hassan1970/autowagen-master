<?php
/**
 * Stage 3 / 3b — Single-vehicle edit page (strip & sell business).
 *
 *   /vehicle_edit.php          -> create new vehicle (form is empty)
 *   /vehicle_edit.php?id=42    -> edit vehicle 42
 *
 * Owner / admin / manager can save changes, upload legal paper scans,
 * manage photos, and link / unlink EPC variants. Staff / viewer see a
 * read-only version.
 *
 * Stage 3b additions:
 *   * Stock code (AWG-XXXX, unique).
 *   * Status / transmission / fuel type / body type.
 *   * Acquisition block (supplier OR private seller with SA ID number).
 *   * Four legal paper scans (logbook / receipt / seller-ID-copy /
 *     seller's proof of residence).
 *   * Up to 7 photos.
 *   * Yard location.
 *
 * The vehicle save and the photo / EPC actions are separate POSTs so
 * one doesn't undo the other while the user is mid-edit.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/epc_helpers.php';
require_once __DIR__ . '/includes/uploads.php';

$canEdit = user_has_role('owner', 'admin', 'manager');

$id      = (int) ($_GET['id'] ?? 0);
$isNew   = $id === 0;
$flash   = ['type' => null, 'msg' => null];

// Empty defaults for a brand-new vehicle.
$vehicle = [
    'id' => 0, 'stock_code' => '',
    'make' => '', 'model' => '', 'year' => '',
    'vin' => '', 'engine_code' => '', 'plate' => '', 'colour' => '',
    'mileage' => '',
    'status' => 'intake', 'transmission' => 'unknown',
    'fuel_type' => 'unknown', 'body_type' => '',
    'supplier_id' => null,
    'seller_name' => '', 'seller_id_number' => '', 'seller_phone' => '',
    'purchase_price' => '', 'date_acquired' => '', 'purchase_notes' => '',
    'has_logbook' => 0,             'logbook_path' => null,
    'has_sellers_receipt' => 0,     'sellers_receipt_path' => null,
    'has_seller_id_copy' => 0,      'seller_id_copy_path' => null,
    'has_proof_of_residence' => 0,  'proof_of_residence_path' => null,
    'yard_location' => '',
    'notes' => '', 'is_active' => 1,
];

// Display options for dropdowns.
$STATUS_OPTIONS = [
    'intake'      => 'Intake',
    'stripping'   => 'Being stripped',
    'stripped'    => 'Stripped',
    'scrapped'    => 'Scrapped',
    'shell_sold'  => 'Shell sold',
];
$TRANSMISSION_OPTIONS = [
    'manual'    => 'Manual',
    'automatic' => 'Automatic',
    'cvt'       => 'CVT',
    'semi_auto' => 'Semi-auto',
    'unknown'   => 'Unknown',
];
$FUEL_OPTIONS = [
    'petrol'   => 'Petrol',
    'diesel'   => 'Diesel',
    'hybrid'   => 'Hybrid',
    'electric' => 'Electric',
    'lpg'      => 'LPG',
    'unknown'  => 'Unknown',
];

const PHOTO_LIMIT = 7;

/**
 * Normalise the stock code the user typed. We always store as "AWG-XXXX".
 * If they typed the prefix already we keep it; if they typed only digits
 * we prepend "AWG-".
 */
function normalise_stock_code(string $raw): string {
    $raw = strtoupper(trim($raw));
    if ($raw === '') return '';
    if (str_starts_with($raw, 'AWG-')) {
        $tail = substr($raw, 4);
    } elseif (str_starts_with($raw, 'AWG')) {
        $tail = substr($raw, 3);
    } else {
        $tail = $raw;
    }
    $tail = preg_replace('/[^A-Z0-9]/', '', $tail) ?? '';
    return 'AWG-' . $tail;
}

// =========================================================================
// POST handling
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        http_response_code(403);
        $flash = ['type' => 'danger', 'msg' => 'You do not have permission to change vehicles.'];
    } elseif (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = ['type' => 'danger', 'msg' => 'Security token invalid. Please reload the page.'];
    } else {
        $action = $_POST['action'] ?? '';
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        try {
            switch ($action) {

                // -----------------------------------------------------------
                // SAVE main vehicle form (covers all Stage-3b fields + the
                // three legal-paper file uploads).
                // -----------------------------------------------------------
                case 'save': {
                    $stockCode = normalise_stock_code((string) ($_POST['stock_code'] ?? ''));
                    $make      = trim((string) ($_POST['make']  ?? ''));
                    $model     = trim((string) ($_POST['model'] ?? ''));

                    if ($stockCode === 'AWG-' || $stockCode === '') {
                        throw new RuntimeException('Stock code is required (e.g. AWG-0001).');
                    }
                    if ($make  === '') throw new RuntimeException('Make is required.');
                    if ($model === '') throw new RuntimeException('Model is required.');

                    $year    = trim((string) ($_POST['year'] ?? ''));
                    $year    = $year !== '' ? (int) $year : null;
                    $mileage = trim((string) ($_POST['mileage'] ?? ''));
                    $mileage = $mileage !== '' ? (int) $mileage : null;
                    $vin     = trim((string) ($_POST['vin']   ?? '')) ?: null;
                    $plate   = trim((string) ($_POST['plate'] ?? '')) ?: null;
                    $engine  = trim((string) ($_POST['engine_code'] ?? '')) ?: null;
                    $colour  = trim((string) ($_POST['colour']      ?? '')) ?: null;
                    $body    = trim((string) ($_POST['body_type']   ?? '')) ?: null;
                    $yard    = trim((string) ($_POST['yard_location'] ?? '')) ?: null;
                    $notes   = trim((string) ($_POST['notes'] ?? '')) ?: null;

                    $status  = (string) ($_POST['status']       ?? 'intake');
                    $trans   = (string) ($_POST['transmission'] ?? 'unknown');
                    $fuel    = (string) ($_POST['fuel_type']    ?? 'unknown');
                    if (!array_key_exists($status, $STATUS_OPTIONS))      $status = 'intake';
                    if (!array_key_exists($trans,  $TRANSMISSION_OPTIONS)) $trans  = 'unknown';
                    if (!array_key_exists($fuel,   $FUEL_OPTIONS))         $fuel   = 'unknown';

                    $supplierId = (int) ($_POST['supplier_id'] ?? 0);
                    $supplierId = $supplierId > 0 ? $supplierId : null;

                    $sellerName  = trim((string) ($_POST['seller_name']      ?? '')) ?: null;
                    $sellerId    = trim((string) ($_POST['seller_id_number'] ?? '')) ?: null;
                    $sellerPhone = trim((string) ($_POST['seller_phone']     ?? '')) ?: null;

                    $purchasePrice = trim((string) ($_POST['purchase_price'] ?? ''));
                    $purchasePrice = $purchasePrice !== '' ? (float) $purchasePrice : null;
                    if ($purchasePrice !== null && $purchasePrice < 0) {
                        throw new RuntimeException('Purchase price cannot be negative.');
                    }

                    $dateAcquired = trim((string) ($_POST['date_acquired'] ?? ''));
                    if ($dateAcquired !== '') {
                        $dt = DateTime::createFromFormat('Y-m-d', $dateAcquired);
                        if (!$dt || $dt->format('Y-m-d') !== $dateAcquired) {
                            throw new RuntimeException('Date acquired is not a valid date.');
                        }
                    } else {
                        $dateAcquired = null;
                    }

                    $purchaseNotes = trim((string) ($_POST['purchase_notes'] ?? '')) ?: null;

                    $hasLogbook   = !empty($_POST['has_logbook'])             ? 1 : 0;
                    $hasReceipt   = !empty($_POST['has_sellers_receipt'])     ? 1 : 0;
                    $hasIdCopy    = !empty($_POST['has_seller_id_copy'])      ? 1 : 0;
                    $hasResidence = !empty($_POST['has_proof_of_residence']) ? 1 : 0;
                    $isActive     = !empty($_POST['is_active']) ? 1 : 0;

                    // INSERT new vehicle (no file uploads on first save —
                    // we need the vehicle id before we can create its folder).
                    if ($isNew) {
                        $stmt = $pdo->prepare(
                            'INSERT INTO vehicles
                             (stock_code, make, model, year, vin, engine_code, plate,
                              colour, mileage, status, transmission, fuel_type, body_type,
                              supplier_id, seller_name, seller_id_number, seller_phone,
                              purchase_price, date_acquired, purchase_notes,
                              has_logbook, has_sellers_receipt, has_seller_id_copy,
                              has_proof_of_residence,
                              yard_location, notes, is_active, created_by)
                             VALUES
                             (:stock, :make, :model, :year, :vin, :ec, :plate,
                              :colour, :mileage, :status, :trans, :fuel, :body,
                              :sup, :sname, :sid, :sphone,
                              :pprice, :dacq, :pnotes,
                              :hlog, :hrec, :hidc,
                              :hpor,
                              :yard, :notes, :active, :uid)'
                        );
                        $stmt->execute([
                            ':stock'   => $stockCode,
                            ':make'    => $make,
                            ':model'   => $model,
                            ':year'    => $year,
                            ':vin'     => $vin,
                            ':ec'      => $engine,
                            ':plate'   => $plate,
                            ':colour'  => $colour,
                            ':mileage' => $mileage,
                            ':status'  => $status,
                            ':trans'   => $trans,
                            ':fuel'    => $fuel,
                            ':body'    => $body,
                            ':sup'     => $supplierId,
                            ':sname'   => $sellerName,
                            ':sid'     => $sellerId,
                            ':sphone'  => $sellerPhone,
                            ':pprice'  => $purchasePrice,
                            ':dacq'    => $dateAcquired,
                            ':pnotes'  => $purchaseNotes,
                            ':hlog'    => $hasLogbook,
                            ':hrec'    => $hasReceipt,
                            ':hidc'    => $hasIdCopy,
                            ':hpor'    => $hasResidence,
                            ':yard'    => $yard,
                            ':notes'   => $notes,
                            ':active'  => $isActive,
                            ':uid'     => $userId,
                        ]);
                        $newId = (int) $pdo->lastInsertId();
                        header('Location: ' . APP_URL . '/vehicle_edit.php?id=' . $newId . '&saved=1');
                        exit;
                    }

                    // UPDATE existing vehicle. We compute file paths here:
                    // start with whatever the DB already has, replace any
                    // file the user actually uploaded this round.
                    $logbookPath   = $vehicle['logbook_path']            ?? null;
                    $receiptPath   = $vehicle['sellers_receipt_path']    ?? null;
                    $idCopyPath    = $vehicle['seller_id_copy_path']     ?? null;
                    $residencePath = $vehicle['proof_of_residence_path'] ?? null;

                    // Pull current paths fresh so we can safely replace.
                    $cur = $pdo->prepare('SELECT logbook_path, sellers_receipt_path, seller_id_copy_path, proof_of_residence_path FROM vehicles WHERE id=:id');
                    $cur->execute([':id' => $id]);
                    if ($curRow = $cur->fetch()) {
                        $logbookPath   = $curRow['logbook_path'];
                        $receiptPath   = $curRow['sellers_receipt_path'];
                        $idCopyPath    = $curRow['seller_id_copy_path'];
                        $residencePath = $curRow['proof_of_residence_path'];
                    }

                    // Each upload (if file present) replaces the previous one.
                    if (isset($_FILES['logbook_file']) && ($_FILES['logbook_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $new = save_uploaded_doc($_FILES['logbook_file'], $id, 'logbook');
                        delete_uploaded_file($logbookPath);
                        $logbookPath = $new;
                        $hasLogbook  = 1;
                    }
                    if (isset($_FILES['receipt_file']) && ($_FILES['receipt_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $new = save_uploaded_doc($_FILES['receipt_file'], $id, 'receipt');
                        delete_uploaded_file($receiptPath);
                        $receiptPath = $new;
                        $hasReceipt  = 1;
                    }
                    if (isset($_FILES['id_copy_file']) && ($_FILES['id_copy_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $new = save_uploaded_doc($_FILES['id_copy_file'], $id, 'id_copy');
                        delete_uploaded_file($idCopyPath);
                        $idCopyPath = $new;
                        $hasIdCopy  = 1;
                    }
                    if (isset($_FILES['proof_residence_file']) && ($_FILES['proof_residence_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $new = save_uploaded_doc($_FILES['proof_residence_file'], $id, 'proof_residence');
                        delete_uploaded_file($residencePath);
                        $residencePath = $new;
                        $hasResidence = 1;
                    }

                    $stmt = $pdo->prepare(
                        'UPDATE vehicles SET
                           stock_code=:stock, make=:make, model=:model, year=:year,
                           vin=:vin, engine_code=:ec, plate=:plate, colour=:colour, mileage=:mileage,
                           status=:status, transmission=:trans, fuel_type=:fuel, body_type=:body,
                           supplier_id=:sup, seller_name=:sname, seller_id_number=:sid, seller_phone=:sphone,
                           purchase_price=:pprice, date_acquired=:dacq, purchase_notes=:pnotes,
                           has_logbook=:hlog, logbook_path=:lpath,
                           has_sellers_receipt=:hrec, sellers_receipt_path=:rpath,
                           has_seller_id_copy=:hidc, seller_id_copy_path=:ipath,
                           has_proof_of_residence=:hpor, proof_of_residence_path=:ppath,
                           yard_location=:yard, notes=:notes, is_active=:active
                         WHERE id=:id'
                    );
                    $stmt->execute([
                        ':stock'   => $stockCode,
                        ':make'    => $make,
                        ':model'   => $model,
                        ':year'    => $year,
                        ':vin'     => $vin,
                        ':ec'      => $engine,
                        ':plate'   => $plate,
                        ':colour'  => $colour,
                        ':mileage' => $mileage,
                        ':status'  => $status,
                        ':trans'   => $trans,
                        ':fuel'    => $fuel,
                        ':body'    => $body,
                        ':sup'     => $supplierId,
                        ':sname'   => $sellerName,
                        ':sid'     => $sellerId,
                        ':sphone'  => $sellerPhone,
                        ':pprice'  => $purchasePrice,
                        ':dacq'    => $dateAcquired,
                        ':pnotes'  => $purchaseNotes,
                        ':hlog'    => $hasLogbook,   ':lpath' => $logbookPath,
                        ':hrec'    => $hasReceipt,   ':rpath' => $receiptPath,
                        ':hidc'    => $hasIdCopy,    ':ipath' => $idCopyPath,
                        ':hpor'    => $hasResidence, ':ppath' => $residencePath,
                        ':yard'    => $yard,
                        ':notes'   => $notes,
                        ':active'  => $isActive,
                        ':id'      => $id,
                    ]);
                    $flash = ['type' => 'success', 'msg' => 'Saved.'];
                    break;
                }

                // -----------------------------------------------------------
                // Remove a stored legal-paper file (the checkbox stays —
                // user can re-upload later).
                // -----------------------------------------------------------
                case 'delete_doc': {
                    if ($isNew) throw new RuntimeException('Save the vehicle first.');
                    $kind = (string) ($_POST['kind'] ?? '');
                    $map  = [
                        'logbook'         => 'logbook_path',
                        'receipt'         => 'sellers_receipt_path',
                        'id_copy'         => 'seller_id_copy_path',
                        'proof_residence' => 'proof_of_residence_path',
                    ];
                    if (!isset($map[$kind])) throw new RuntimeException('Unknown document kind.');
                    $col = $map[$kind];

                    $cur = $pdo->prepare("SELECT {$col} AS p FROM vehicles WHERE id=:id");
                    $cur->execute([':id' => $id]);
                    $oldPath = $cur->fetchColumn();
                    delete_uploaded_file($oldPath ?: null);

                    $upd = $pdo->prepare("UPDATE vehicles SET {$col} = NULL WHERE id = :id");
                    $upd->execute([':id' => $id]);
                    $flash = ['type' => 'success', 'msg' => 'File removed.'];
                    break;
                }

                // -----------------------------------------------------------
                // Add a photo (max PHOTO_LIMIT enforced).
                // -----------------------------------------------------------
                case 'add_photo': {
                    if ($isNew) throw new RuntimeException('Save the vehicle first.');
                    $cnt = (int) $pdo->query("SELECT COUNT(*) FROM vehicle_photos WHERE vehicle_id = " . (int) $id)->fetchColumn();
                    if ($cnt >= PHOTO_LIMIT) {
                        throw new RuntimeException('Photo limit reached (' . PHOTO_LIMIT . '). Delete one first.');
                    }
                    if (!isset($_FILES['photo_file']) || ($_FILES['photo_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('Pick a photo file before clicking add.');
                    }
                    $caption = trim((string) ($_POST['caption'] ?? '')) ?: null;
                    $rel = save_uploaded_photo($_FILES['photo_file'], $id);

                    $stmt = $pdo->prepare(
                        'INSERT INTO vehicle_photos (vehicle_id, file_path, caption, sort_order, created_by)
                         VALUES (:v, :p, :c, :s, :uid)'
                    );
                    $stmt->execute([
                        ':v'   => $id,
                        ':p'   => $rel,
                        ':c'   => $caption,
                        ':s'   => $cnt,           // 0-based, so newcomers go to the end
                        ':uid' => $userId,
                    ]);
                    $flash = ['type' => 'success', 'msg' => 'Photo added.'];
                    break;
                }

                // -----------------------------------------------------------
                // Delete one photo.
                // -----------------------------------------------------------
                case 'delete_photo': {
                    if ($isNew) throw new RuntimeException('Save the vehicle first.');
                    $photoId = (int) ($_POST['photo_id'] ?? 0);
                    if ($photoId <= 0) throw new RuntimeException('Bad photo id.');

                    $cur = $pdo->prepare('SELECT file_path FROM vehicle_photos WHERE id=:p AND vehicle_id=:v');
                    $cur->execute([':p' => $photoId, ':v' => $id]);
                    $oldPath = $cur->fetchColumn();
                    if ($oldPath === false) throw new RuntimeException('Photo not found.');

                    $del = $pdo->prepare('DELETE FROM vehicle_photos WHERE id=:p AND vehicle_id=:v');
                    $del->execute([':p' => $photoId, ':v' => $id]);
                    delete_uploaded_file($oldPath ?: null);
                    $flash = ['type' => 'success', 'msg' => 'Photo deleted.'];
                    break;
                }

                // -----------------------------------------------------------
                // EPC link / unlink (unchanged from Stage 3).
                // -----------------------------------------------------------
                case 'link_epc': {
                    if ($isNew) throw new RuntimeException('Save the vehicle first.');
                    $variantId = (int) ($_POST['variant_id'] ?? 0);
                    $note      = trim((string) ($_POST['note'] ?? ''));
                    if ($variantId <= 0) throw new RuntimeException('Pick an EPC variant first.');

                    $stmt = $pdo->prepare(
                        'INSERT INTO vehicle_epc_links
                         (vehicle_id, variant_id, note, created_by)
                         VALUES (:v, :var, :note, :uid)
                         ON DUPLICATE KEY UPDATE note = VALUES(note)'
                    );
                    $stmt->execute([
                        ':v'    => $id,
                        ':var'  => $variantId,
                        ':note' => $note !== '' ? $note : null,
                        ':uid'  => $userId,
                    ]);
                    $flash = ['type' => 'success', 'msg' => 'EPC variant linked to this vehicle.'];
                    break;
                }

                case 'unlink_epc': {
                    if ($isNew) throw new RuntimeException('Vehicle not saved yet.');
                    $variantId = (int) ($_POST['variant_id'] ?? 0);
                    if ($variantId <= 0) throw new RuntimeException('Bad variant id.');
                    $stmt = $pdo->prepare(
                        'DELETE FROM vehicle_epc_links
                          WHERE vehicle_id = :v AND variant_id = :var'
                    );
                    $stmt->execute([':v' => $id, ':var' => $variantId]);
                    $flash = ['type' => 'success', 'msg' => 'EPC link removed.'];
                    break;
                }

                default:
                    throw new RuntimeException('Unknown action.');
            }
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if ($e->getCode() === '23000' || stripos($msg, 'duplicate') !== false) {
                $msg = 'Stock code, VIN or plate is already used by another vehicle.';
            } elseif (!APP_DEBUG) {
                $msg = 'Database error.';
            }
            $flash = ['type' => 'danger', 'msg' => $msg];
        } catch (Throwable $e) {
            $flash = ['type' => 'danger', 'msg' => $e->getMessage()];
        }
    }
}

// =========================================================================
// Load row (after any POST-save)
// =========================================================================
if (!$isNew) {
    $stmt = $pdo->prepare('SELECT * FROM vehicles WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        $pageTitle = 'Vehicle not found';
        include __DIR__ . '/includes/header.php';
        echo '<div class="container"><div class="alert alert-warning">Vehicle #'
           . (int) $id . ' was not found. <a href="' . e(APP_URL) . '/vehicles_admin.php">Back to list</a>.</div></div>';
        include __DIR__ . '/includes/footer.php';
        exit;
    }
    $vehicle = array_merge($vehicle, $row);
}

if (!empty($_GET['saved'])) {
    $flash = ['type' => 'success', 'msg' => 'Vehicle created.'];
}

// Load suppliers for the dropdown.
$suppliers = $pdo->query(
    'SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC'
)->fetchAll();

// Existing EPC links.
$links = [];
if (!$isNew) {
    $linkStmt = $pdo->prepare(
        'SELECT vel.variant_id, vel.note, vel.created_at, ev.full_path
           FROM vehicle_epc_links vel
           INNER JOIN epc_full_view ev ON ev.variant_id = vel.variant_id
          WHERE vel.vehicle_id = :v
          ORDER BY ev.full_path'
    );
    $linkStmt->execute([':v' => $id]);
    $links = $linkStmt->fetchAll();
}

// Existing photos.
$photos = [];
if (!$isNew) {
    $pStmt = $pdo->prepare(
        'SELECT id, file_path, caption, sort_order, created_at
           FROM vehicle_photos
          WHERE vehicle_id = :v
          ORDER BY sort_order ASC, id ASC'
    );
    $pStmt->execute([':v' => $id]);
    $photos = $pStmt->fetchAll();
}
$photoCount = count($photos);
$photoSlotsLeft = max(0, PHOTO_LIMIT - $photoCount);

// Stage 4 — parts stripped from this vehicle.
$strippedParts = [];
$strippedSummary = ['total' => 0, 'available' => 0, 'sold' => 0, 'on_vehicle' => 0];
if (!$isNew) {
    $sp = $pdo->prepare(
        "SELECT id, sku, name, condition_grade, status, asking_price,
                discount_price, qty_on_hand, yard_location, is_active
           FROM parts
          WHERE vehicle_id = :v AND source = 'stripped'
          ORDER BY sku ASC"
    );
    $sp->execute([':v' => $id]);
    $strippedParts = $sp->fetchAll();
    $strippedSummary['total'] = count($strippedParts);
    foreach ($strippedParts as $sx) {
        $st = (string) $sx['status'];
        if (isset($strippedSummary[$st])) {
            $strippedSummary[$st]++;
        }
    }
}

// Stock code split for the input (we display "AWG-" as a locked prefix and
// let the user type only the number portion).
$stockTail = '';
if (!empty($vehicle['stock_code'])) {
    $stockTail = preg_replace('/^AWG-?/i', '', (string) $vehicle['stock_code']);
}

// "Papers complete" badge logic — 4 papers (Stage 3c added proof of residence).
$papersComplete = ((int) $vehicle['has_logbook']
                 + (int) $vehicle['has_sellers_receipt']
                 + (int) $vehicle['has_seller_id_copy']
                 + (int) $vehicle['has_proof_of_residence']) === 4;

$pageTitle  = $isNew ? 'New vehicle'
                     : trim(($vehicle['stock_code'] ?? '') . '  ' . $vehicle['make'] . ' ' . $vehicle['model']);
$cascadeUrl = e(APP_URL) . '/ajax/epc_cascade.php';
$levels     = array_keys(EPC_LEVELS);
$readonly   = $canEdit ? '' : 'readonly';
$disabled   = $canEdit ? '' : 'disabled';

include __DIR__ . '/includes/header.php';
?>

<div class="container">

  <!-- ===================== Page header ===================== -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-1">
        <i class="bi bi-truck"></i>
        <?= $isNew
            ? 'Add new vehicle'
            : e(($vehicle['stock_code'] ?: '(no stock code)') . ' — ' . $vehicle['make'] . ' ' . $vehicle['model']) ?>
        <?php if (!$isNew && !$vehicle['is_active']): ?>
          <span class="badge bg-secondary">Inactive</span>
        <?php endif; ?>
        <?php if (!$isNew):
            $sLabel = $STATUS_OPTIONS[$vehicle['status']] ?? ucfirst((string) $vehicle['status']);
            $sCls   = match ($vehicle['status']) {
                'intake'     => 'bg-info text-dark',
                'stripping'  => 'bg-warning text-dark',
                'stripped'   => 'bg-secondary',
                'scrapped'   => 'bg-dark',
                'shell_sold' => 'bg-success',
                default      => 'bg-light text-dark',
            }; ?>
          <span class="badge <?= e($sCls) ?>"><?= e($sLabel) ?></span>
          <?php if ($papersComplete): ?>
            <span class="badge bg-success" title="Log book, receipt, seller ID and proof of residence all on file">
              <i class="bi bi-shield-check"></i> Papers complete
            </span>
          <?php else: ?>
            <span class="badge bg-danger" title="One or more legal documents missing">
              <i class="bi bi-shield-exclamation"></i> Papers incomplete
            </span>
          <?php endif; ?>
        <?php endif; ?>
      </h1>
      <?php if (!$isNew): ?>
        <div class="text-muted small">
          Vehicle #<?= (int) $vehicle['id'] ?>
          <?php if (!empty($vehicle['updated_at'])): ?>
            &middot; last edit <?= e($vehicle['updated_at']) ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <a class="btn btn-sm btn-outline-dark" href="<?= e(APP_URL) ?>/vehicles_admin.php">
      <i class="bi bi-arrow-left"></i> All vehicles
    </a>
  </div>

  <?php if ($flash['msg']): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
  <?php endif; ?>

  <!-- ===================== MAIN SAVE FORM (multipart for file uploads) ===================== -->
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">

    <!-- ----- Card A: Stock & Identity ----- -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 text-uppercase text-muted mb-3">
          <i class="bi bi-tag"></i> Stock &amp; identity
        </h2>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label small">Stock code *</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">AWG-</span>
              <input class="form-control font-monospace" name="stock_code" maxlength="16"
                     placeholder="0001"
                     value="<?= e($stockTail) ?>" <?= $readonly ?> required>
            </div>
            <div class="form-text small">Saved as <code>AWG-XXXX</code>. Must be unique.</div>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Make *</label>
            <input class="form-control form-control-sm" name="make" required
                   value="<?= e($vehicle['make']) ?>" <?= $readonly ?>>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Model *</label>
            <input class="form-control form-control-sm" name="model" required
                   value="<?= e($vehicle['model']) ?>" <?= $readonly ?>>
          </div>
          <div class="col-md-1">
            <label class="form-label small">Year</label>
            <input class="form-control form-control-sm" name="year" type="number" min="1900" max="2100"
                   value="<?= e((string) $vehicle['year']) ?>" <?= $readonly ?>>
          </div>
          <div class="col-md-2">
            <label class="form-label small">Body type</label>
            <input class="form-control form-control-sm" name="body_type" maxlength="40"
                   placeholder="Sedan, Bakkie, SUV…"
                   value="<?= e((string) $vehicle['body_type']) ?>" <?= $readonly ?>>
          </div>

          <div class="col-md-4">
            <label class="form-label small">VIN</label>
            <input class="form-control form-control-sm font-monospace" name="vin" maxlength="32"
                   value="<?= e((string) $vehicle['vin']) ?>" <?= $readonly ?>>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Plate</label>
            <input class="form-control form-control-sm" name="plate" maxlength="20"
                   value="<?= e((string) $vehicle['plate']) ?>" <?= $readonly ?>>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Engine code</label>
            <input class="form-control form-control-sm" name="engine_code" maxlength="40"
                   value="<?= e((string) $vehicle['engine_code']) ?>" <?= $readonly ?>>
          </div>
          <div class="col-md-2">
            <label class="form-label small">Colour</label>
            <input class="form-control form-control-sm" name="colour" maxlength="40"
                   value="<?= e((string) $vehicle['colour']) ?>" <?= $readonly ?>>
          </div>
        </div>
      </div>
    </div>

    <!-- ----- Card B: Status / specs ----- -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 text-uppercase text-muted mb-3">
          <i class="bi bi-gear"></i> Status &amp; specs
        </h2>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label small">Status</label>
            <select class="form-select form-select-sm" name="status" <?= $disabled ?>>
              <?php foreach ($STATUS_OPTIONS as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= $vehicle['status'] === $k ? 'selected' : '' ?>>
                  <?= e($v) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Transmission</label>
            <select class="form-select form-select-sm" name="transmission" <?= $disabled ?>>
              <?php foreach ($TRANSMISSION_OPTIONS as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= $vehicle['transmission'] === $k ? 'selected' : '' ?>>
                  <?= e($v) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Fuel type</label>
            <select class="form-select form-select-sm" name="fuel_type" <?= $disabled ?>>
              <?php foreach ($FUEL_OPTIONS as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= $vehicle['fuel_type'] === $k ? 'selected' : '' ?>>
                  <?= e($v) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Mileage (km)</label>
            <input class="form-control form-control-sm" name="mileage" type="number" min="0"
                   value="<?= e((string) $vehicle['mileage']) ?>" <?= $readonly ?>>
          </div>
        </div>
      </div>
    </div>

    <!-- ----- Card C: Acquisition ----- -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 text-uppercase text-muted mb-3">
          <i class="bi bi-cash-stack"></i> Acquisition
        </h2>
        <p class="text-muted small mb-3">
          Use <strong>Supplier</strong> when you bought from a registered business
          (auction, salvage yard). Use the <strong>Private seller</strong> fields
          when you bought from an individual — South African law requires capturing
          their full name and ID number.
        </p>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small">Supplier (registered business)</label>
            <select class="form-select form-select-sm" name="supplier_id" <?= $disabled ?>>
              <option value="0">— None / private seller —</option>
              <?php foreach ($suppliers as $s): ?>
                <option value="<?= (int) $s['id'] ?>"
                        <?= ((int) $vehicle['supplier_id']) === (int) $s['id'] ? 'selected' : '' ?>>
                  <?= e($s['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Seller's full name (private)</label>
            <input class="form-control form-control-sm" name="seller_name" maxlength="150"
                   value="<?= e((string) $vehicle['seller_name']) ?>" <?= $readonly ?>>
          </div>
          <div class="col-md-2">
            <label class="form-label small">SA ID number</label>
            <input class="form-control form-control-sm font-monospace" name="seller_id_number" maxlength="20"
                   placeholder="13 digits"
                   value="<?= e((string) $vehicle['seller_id_number']) ?>" <?= $readonly ?>>
          </div>
          <div class="col-md-2">
            <label class="form-label small">Seller phone</label>
            <input class="form-control form-control-sm" name="seller_phone" maxlength="30"
                   value="<?= e((string) $vehicle['seller_phone']) ?>" <?= $readonly ?>>
          </div>

          <div class="col-md-3">
            <label class="form-label small">Purchase price (R)</label>
            <input class="form-control form-control-sm" name="purchase_price" type="number"
                   min="0" step="0.01"
                   value="<?= e((string) $vehicle['purchase_price']) ?>" <?= $readonly ?>>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Date acquired</label>
            <input class="form-control form-control-sm" name="date_acquired" type="date"
                   value="<?= e((string) $vehicle['date_acquired']) ?>" <?= $readonly ?>>
          </div>
          <div class="col-md-6">
            <label class="form-label small">Purchase notes</label>
            <input class="form-control form-control-sm" name="purchase_notes" maxlength="500"
                   placeholder="e.g. accident damage, engine seized, sold cheap…"
                   value="<?= e((string) $vehicle['purchase_notes']) ?>" <?= $readonly ?>>
          </div>
        </div>
      </div>
    </div>

    <!-- ----- Card D: Legal papers (SA law) ----- -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 text-uppercase text-muted mb-3">
          <i class="bi bi-shield-check"></i> Legal papers on file (SA law)
        </h2>
        <?php if ($isNew): ?>
          <p class="text-muted small mb-2">
            Tick the boxes for any papers you already have. You can upload the
            scanned files once the vehicle is created (this section becomes
            available after the first save).
          </p>
        <?php else: ?>
          <p class="text-muted small mb-3">
            Tick the box if you have the paper, then upload the scan/photo.
            Accepted: <strong>PDF, JPG, PNG</strong> (max 5 MB each).
          </p>
        <?php endif; ?>

        <div class="row g-3">
          <?php
          $docRows = [
              ['kind' => 'logbook',         'label' => 'Vehicle log book',           'flag' => 'has_logbook',             'pathCol' => 'logbook_path',             'fileField' => 'logbook_file'],
              ['kind' => 'receipt',         'label' => "Seller's receipt",           'flag' => 'has_sellers_receipt',     'pathCol' => 'sellers_receipt_path',     'fileField' => 'receipt_file'],
              ['kind' => 'id_copy',         'label' => "Copy of seller's SA ID",     'flag' => 'has_seller_id_copy',      'pathCol' => 'seller_id_copy_path',      'fileField' => 'id_copy_file'],
              ['kind' => 'proof_residence', 'label' => "Seller's proof of residence",'flag' => 'has_proof_of_residence',  'pathCol' => 'proof_of_residence_path',  'fileField' => 'proof_residence_file'],
          ];
          foreach ($docRows as $d):
              $hasIt   = (int) ($vehicle[$d['flag']] ?? 0) === 1;
              $path    = (string) ($vehicle[$d['pathCol']] ?? '');
              $url     = uploads_public_url($path ?: null);
          ?>
            <div class="col-md-6 col-lg-3">
              <div class="border rounded p-3 h-100">
                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox"
                         id="flag_<?= e($d['kind']) ?>" name="<?= e($d['flag']) ?>"
                         value="1" <?= $hasIt ? 'checked' : '' ?> <?= $disabled ?>>
                  <label class="form-check-label" for="flag_<?= e($d['kind']) ?>">
                    <strong><?= e($d['label']) ?></strong> — I have this on file
                  </label>
                </div>

                <?php if ($url): ?>
                  <p class="small mb-2">
                    <i class="bi bi-paperclip"></i>
                    <a href="<?= e($url) ?>" target="_blank" rel="noopener">
                      View current scan
                    </a>
                  </p>
                <?php else: ?>
                  <p class="small text-muted mb-2"><i class="bi bi-file-earmark"></i> No scan uploaded yet.</p>
                <?php endif; ?>

                <?php if (!$isNew && $canEdit): ?>
                  <label class="form-label small mb-1">
                    <?= $url ? 'Replace scan' : 'Upload scan' ?>
                  </label>
                  <input class="form-control form-control-sm" type="file"
                         name="<?= e($d['fileField']) ?>"
                         accept=".pdf,.jpg,.jpeg,.png">
                <?php elseif ($isNew): ?>
                  <p class="small text-muted fst-italic mb-0">
                    Upload becomes available after the first save.
                  </p>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ----- Card E: Yard / general notes / active flag ----- -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 text-uppercase text-muted mb-3">
          <i class="bi bi-geo-alt"></i> Yard &amp; general notes
        </h2>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small">Yard location</label>
            <input class="form-control form-control-sm" name="yard_location" maxlength="80"
                   placeholder="Bay 3 / Row B-12 / Behind workshop"
                   value="<?= e((string) $vehicle['yard_location']) ?>" <?= $readonly ?>>
          </div>
          <div class="col-md-8">
            <label class="form-label small">General notes</label>
            <textarea class="form-control form-control-sm" name="notes" rows="2"
                      <?= $readonly ?>><?= e((string) $vehicle['notes']) ?></textarea>
          </div>

          <?php if ($canEdit): ?>
            <div class="col-md-6">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_active"
                       name="is_active" value="1" <?= $vehicle['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Active (visible in lists)</label>
              </div>
            </div>
            <div class="col-md-6 text-end">
              <button class="btn btn-danger" type="submit">
                <i class="bi bi-save"></i> <?= $isNew ? 'Create vehicle' : 'Save changes' ?>
              </button>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- File upload reminder for the legal-paper section above. The browser
         needs the form to be enctype=multipart/form-data, which is set on
         the <form> element. No further hidden inputs needed. -->
  </form>

  <!-- ===================== Photos (only after vehicle exists) ===================== -->
  <?php if (!$isNew): ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 text-uppercase text-muted mb-3">
          <i class="bi bi-images"></i> Vehicle photos
          <small class="text-muted ms-1">
            <?= $photoCount ?> / <?= PHOTO_LIMIT ?> used
          </small>
        </h2>

        <?php if ($photos): ?>
          <div class="row g-3 mb-3">
            <?php foreach ($photos as $ph):
              $thumbUrl = uploads_public_url($ph['file_path']);
            ?>
              <div class="col-md-3 col-6">
                <div class="border rounded overflow-hidden position-relative" style="aspect-ratio:4/3;background:#222;">
                  <a href="<?= e($thumbUrl) ?>" target="_blank" rel="noopener">
                    <img src="<?= e($thumbUrl) ?>" alt="<?= e((string) $ph['caption']) ?>"
                         style="object-fit:cover;width:100%;height:100%;">
                  </a>
                  <?php if ($canEdit): ?>
                    <form method="post" class="position-absolute top-0 end-0 m-1"
                          onsubmit="return confirm('Delete this photo?');">
                      <input type="hidden" name="csrf"     value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action"   value="delete_photo">
                      <input type="hidden" name="photo_id" value="<?= (int) $ph['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger" title="Delete photo">
                        <i class="bi bi-x-lg"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
                <div class="small text-muted mt-1 text-truncate" title="<?= e((string) $ph['caption']) ?>">
                  <?= $ph['caption'] !== null && $ph['caption'] !== '' ? e($ph['caption']) : '<em>no caption</em>' ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-muted small mb-3">No photos yet.</p>
        <?php endif; ?>

        <?php if ($canEdit): ?>
          <?php if ($photoSlotsLeft > 0): ?>
            <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
              <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="add_photo">
              <div class="col-md-5">
                <label class="form-label small mb-1">Pick photo (JPG / PNG / WebP)</label>
                <input class="form-control form-control-sm" type="file" name="photo_file"
                       accept=".jpg,.jpeg,.png,.webp" required>
              </div>
              <div class="col-md-5">
                <label class="form-label small mb-1">Caption (optional)</label>
                <input class="form-control form-control-sm" name="caption" maxlength="120"
                       placeholder="front, engine bay, left damage…">
              </div>
              <div class="col-md-2 text-end">
                <button class="btn btn-sm btn-danger" type="submit">
                  <i class="bi bi-plus-lg"></i> Add photo
                </button>
              </div>
            </form>
            <p class="form-text small mt-1">
              <?= $photoSlotsLeft ?> slot<?= $photoSlotsLeft === 1 ? '' : 's' ?> remaining (max <?= PHOTO_LIMIT ?>).
            </p>
          <?php else: ?>
            <div class="alert alert-warning py-2 mb-0">
              Photo limit reached (<?= PHOTO_LIMIT ?>). Delete one above to add another.
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- ===================== Stage 4: Parts stripped from this vehicle ===================== -->
  <?php if (!$isNew):
    $PART_STATUS_OPTIONS = [
        'on_vehicle' => 'On vehicle',
        'available'  => 'Available',
        'reserved'   => 'Reserved',
        'sold'       => 'Sold',
        'scrapped'   => 'Scrapped',
    ];
    $PART_STATUS_BADGE = [
        'on_vehicle' => 'bg-warning text-dark',
        'available'  => 'bg-success',
        'reserved'   => 'bg-info text-dark',
        'sold'       => 'bg-dark',
        'scrapped'   => 'bg-secondary',
    ];
    $PART_COND_OPTIONS = [
        'new'   => 'New',
        'good'  => 'Good',
        'fair'  => 'Fair',
        'poor'  => 'Poor',
        'scrap' => 'Scrap',
    ];
  ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <h2 class="h6 text-uppercase text-muted mb-0">
            <i class="bi bi-box-seam"></i> Parts stripped from this vehicle
            <small class="text-muted ms-1">
              <?= $strippedSummary['total'] ?> total
              <?php if ($strippedSummary['total'] > 0): ?>
                ·
                <?= $strippedSummary['on_vehicle'] ?> on vehicle
                · <?= $strippedSummary['available'] ?> available
                · <?= $strippedSummary['sold'] ?> sold
              <?php endif; ?>
            </small>
          </h2>
          <?php if ($canEdit): ?>
            <a class="btn btn-sm btn-danger"
               href="<?= e(APP_URL) ?>/part_edit.php?vehicle_id=<?= (int) $id ?>&source=stripped">
              <i class="bi bi-plus-lg"></i> Add part from this vehicle
            </a>
          <?php endif; ?>
        </div>

        <?php if (!$strippedParts): ?>
          <p class="text-muted small mb-0">
            No parts have been logged from this vehicle yet.
            <?php if ($canEdit): ?>
              Click <strong>Add part from this vehicle</strong> to record one.
            <?php endif; ?>
          </p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>SKU</th>
                  <th>Name</th>
                  <th>Cond.</th>
                  <th>Status</th>
                  <th class="text-end">Qty</th>
                  <th class="text-end">Asking</th>
                  <th>Yard</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($strippedParts as $sp): ?>
                  <tr class="<?= $sp['is_active'] ? '' : 'text-muted' ?>">
                    <td class="font-monospace small"><?= e($sp['sku']) ?></td>
                    <td><?= e($sp['name']) ?></td>
                    <td>
                      <span class="badge bg-light text-dark border">
                        <?= e($PART_COND_OPTIONS[$sp['condition_grade']] ?? $sp['condition_grade']) ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge <?= e($PART_STATUS_BADGE[$sp['status']] ?? 'bg-light text-dark') ?>">
                        <?= e($PART_STATUS_OPTIONS[$sp['status']] ?? $sp['status']) ?>
                      </span>
                    </td>
                    <td class="text-end"><?= (int) $sp['qty_on_hand'] ?></td>
                    <td class="text-end">
                      <?php if ((float) $sp['asking_price'] > 0): ?>
                        R <?= number_format((float) $sp['asking_price'], 2) ?>
                        <?php if ($sp['discount_price'] !== null && (float) $sp['discount_price'] > 0): ?>
                          <div class="small text-success">
                            sale R <?= number_format((float) $sp['discount_price'], 2) ?>
                          </div>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="text-muted small">—</span>
                      <?php endif; ?>
                    </td>
                    <td class="small"><?= e($sp['yard_location'] ?: '—') ?></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-dark"
                         href="<?= e(APP_URL) ?>/part_edit.php?id=<?= (int) $sp['id'] ?>"
                         title="<?= $canEdit ? 'Edit part' : 'View part' ?>">
                        <i class="bi bi-<?= $canEdit ? 'pencil' : 'eye' ?>"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- ===================== EPC variant links (unchanged from Stage 3) ===================== -->
  <?php if (!$isNew): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <h2 class="h6 text-uppercase text-muted mb-3">
          <i class="bi bi-diagram-3"></i> Linked EPC variants
        </h2>

        <?php if (!$links): ?>
          <p class="text-muted small mb-3">
            No EPC variants are linked to this vehicle yet.
            <?php if ($canEdit): ?>Use the picker below to attach one.<?php endif; ?>
          </p>
        <?php else: ?>
          <div class="table-responsive mb-3">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr><th>EPC path</th><th>Note</th><th>Linked at</th>
                  <?php if ($canEdit): ?><th class="text-end">&nbsp;</th><?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($links as $l): ?>
                  <tr>
                    <td><?= e($l['full_path']) ?></td>
                    <td><?= e((string) $l['note']) ?></td>
                    <td class="small text-muted"><?= e($l['created_at']) ?></td>
                    <?php if ($canEdit): ?>
                      <td class="text-end">
                        <form method="post" class="d-inline"
                              onsubmit="return confirm('Remove this EPC link?');">
                          <input type="hidden" name="csrf"       value="<?= e(csrf_token()) ?>">
                          <input type="hidden" name="action"     value="unlink_epc">
                          <input type="hidden" name="variant_id" value="<?= (int) $l['variant_id'] ?>">
                          <button class="btn btn-sm btn-outline-danger" type="submit" title="Unlink">
                            <i class="bi bi-x-lg"></i>
                          </button>
                        </form>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <?php if ($canEdit): ?>
          <h3 class="h6 mb-2">Add a new link</h3>
          <p class="text-muted small">
            Pick a variant by drilling down through the six levels. Once you've
            chosen a variant (the bottom dropdown), the Link button activates.
          </p>
          <form method="post" class="row g-2 align-items-end" id="epc-link-form">
            <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="link_epc">
            <input type="hidden" name="variant_id" id="picked-variant-id" value="">

            <?php foreach ($levels as $i => $lvl):
                    $meta = EPC_LEVELS[$lvl]; ?>
              <div class="col-md-2">
                <label class="form-label small mb-1"><?= e($meta['label']) ?></label>
                <select class="form-select form-select-sm" data-level="<?= e($lvl) ?>" data-index="<?= $i ?>"
                        <?= $i === 0 ? '' : 'disabled' ?>>
                  <option value="">&mdash;</option>
                </select>
              </div>
            <?php endforeach; ?>

            <div class="col-md-12 mt-2">
              <div class="row g-2 align-items-end">
                <div class="col-md-9">
                  <label class="form-label small mb-1">Optional note (e.g. "fits 2018 facelift only")</label>
                  <input class="form-control form-control-sm" name="note" maxlength="255">
                </div>
                <div class="col-md-3 text-end">
                  <button type="submit" class="btn btn-sm btn-danger" id="link-btn" disabled>
                    <i class="bi bi-link-45deg"></i> Link to vehicle
                  </button>
                </div>
              </div>
              <div class="small text-muted mt-1" id="picked-path">No variant picked yet.</div>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($canEdit): ?>
    <script>
    (function () {
      const CASCADE_URL = <?= json_encode($cascadeUrl) ?>;
      const LEVELS      = <?= json_encode($levels) ?>;
      const form        = document.getElementById('epc-link-form');
      const linkBtn     = document.getElementById('link-btn');
      const variantHid  = document.getElementById('picked-variant-id');
      const pathLabel   = document.getElementById('picked-path');
      const selects     = {};
      const labels      = {};

      LEVELS.forEach(function (lvl) {
        selects[lvl] = form.querySelector('select[data-level="' + lvl + '"]');
      });

      function clearFrom(idx) {
        for (let i = idx; i < LEVELS.length; i++) {
          const lvl = LEVELS[i];
          const sel = selects[lvl];
          sel.innerHTML = '<option value="">&mdash;</option>';
          sel.disabled  = (i !== 0);
          delete labels[lvl];
        }
        updatePicked();
      }

      function updatePicked() {
        const variantSel = selects[LEVELS[LEVELS.length - 1]];
        const variantId  = variantSel.value;
        variantHid.value = variantId;
        linkBtn.disabled = !variantId;
        if (!variantId) {
          pathLabel.textContent = 'No variant picked yet.';
          return;
        }
        const parts = LEVELS.map(function (l) { return labels[l]; }).filter(Boolean);
        pathLabel.innerHTML = '<i class="bi bi-check-circle text-success"></i> ' +
          parts.map(escapeHtml).join(' / ');
      }

      function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
          return ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c];
        });
      }

      function loadInto(parentLevel, parentId, targetIdx) {
        const targetLvl = LEVELS[targetIdx];
        const sel       = selects[targetLvl];
        sel.innerHTML   = '<option value="">Loading&hellip;</option>';
        sel.disabled    = true;

        const url = new URL(CASCADE_URL, window.location.origin);
        url.searchParams.set('parent_level', parentLevel);
        if (parentId !== null) url.searchParams.set('parent_id', parentId);

        fetch(url.toString(), { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            sel.innerHTML = '<option value="">&mdash;</option>';
            sel.disabled  = false;
            if (!data.ok || !data.items || data.items.length === 0) return;
            data.items.forEach(function (item) {
              const opt = document.createElement('option');
              opt.value = item.id;
              opt.textContent = item.name;
              opt.dataset.name = item.name;
              sel.appendChild(opt);
            });
          })
          .catch(function () {
            sel.innerHTML = '<option value="">Error</option>';
            sel.disabled  = false;
          });
      }

      LEVELS.forEach(function (lvl, idx) {
        selects[lvl].addEventListener('change', function () {
          const opt = selects[lvl].selectedOptions[0];
          if (opt && opt.value) {
            labels[lvl] = opt.dataset.name || opt.textContent;
          } else {
            delete labels[lvl];
          }
          clearFrom(idx + 1);
          if (opt && opt.value && idx + 1 < LEVELS.length) {
            loadInto(lvl, opt.value, idx + 1);
          }
        });
      });

      loadInto('root', null, 0);
    })();
    </script>
    <?php endif; ?>
  <?php else: ?>
    <p class="text-muted small">Photos and EPC variant links can be attached after the vehicle is created.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

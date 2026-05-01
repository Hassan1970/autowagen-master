<?php
/**
 * Stage 4 — Single-part edit page.
 *
 *   /part_edit.php                              -> create new part (empty)
 *   /part_edit.php?id=42                        -> edit part 42
 *   /part_edit.php?vehicle_id=2&source=stripped -> pre-set vehicle + source
 *   /part_edit.php?supplier_purchase_id=5 -> new part on purchase #5 (seller + docs once)
 *   (legacy: ?tpp_intake_id=  still accepted)
 *
 * Owner / admin / manager / staff can save / upload / link.
 * Viewer is read-only.
 *
 * SKU patterns (auto-suggested, editable):
 *   stripped     -> AWG-<vehstock>-P##  (counter scoped per vehicle)
 *   oem_new      -> OEM-####            (global counter)
 *   replacement  -> REP-####            (global counter)
 *   third_party  -> TPP-####            (global counter)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/epc_helpers.php';
require_once __DIR__ . '/includes/uploads.php';
require_once __DIR__ . '/includes/shop_helpers.php';

$canEdit = user_has_role('owner', 'admin', 'manager', 'staff');

$id     = (int) ($_GET['id']        ?? 0);
$preVeh = (int) ($_GET['vehicle_id'] ?? 0);
$preSrc = (string) ($_GET['source']  ?? '');
$preIntake = (int) ($_GET['supplier_purchase_id'] ?? 0);
if ($preIntake === 0) {
    $preIntake = (int) ($_GET['tpp_intake_id'] ?? 0);
}
$preIntakeSource = (string) ($_GET['source'] ?? '');
$isNew  = $id === 0;
$flash  = ['type' => null, 'msg' => null];

const PART_PHOTO_LIMIT = 5;

// ---------- option lists (kept in sync with parts_admin.php) ----------
$SOURCE_OPTIONS = [
    'stripped'    => 'Stripped (off a vehicle in the yard)',
    'oem_new'     => 'OEM new (genuine, from a dealer)',
    'replacement' => 'Replacement (aftermarket new)',
    'third_party' => 'Third-party (bought-in, supplier or private)',
];
$CONDITION_OPTIONS = [
    'new'   => 'New',
    'good'  => 'Good',
    'fair'  => 'Fair',
    'poor'  => 'Poor',
    'scrap' => 'Scrap',
];
$STATUS_OPTIONS = [
    'on_vehicle' => 'On vehicle (still bolted on)',
    'available'  => 'Available (on the shelf, ready to sell)',
    'reserved'   => 'Reserved (held for a customer)',
    'sold'       => 'Sold',
    'scrapped'   => 'Scrapped (not sellable)',
];

// ---------- defaults for a brand-new part ----------
$part = [
    'id' => 0, 'sku' => '', 'name' => '',
    'source' => $preSrc !== '' && isset($SOURCE_OPTIONS[$preSrc]) ? $preSrc : 'stripped',
    'vehicle_id'       => $preVeh > 0 ? $preVeh : null,
    'supplier_id'      => null,
    'seller_name'      => '',
    'seller_phone'     => '',
    'seller_id_number' => '',
    'condition_grade'  => 'good',
    'status'           => 'available',
    'cost_price'       => '0.00',
    'asking_price'     => '0.00',
    'discount_price'   => '',
    'vat_rate'         => '0.00',
    'qty_on_hand'      => 1,
    'yard_location'    => '',
    'notes'            => '',
    'is_active'        => 1,
    'list_online'      => 0,
    'has_tpp_id_doc'              => 0,
    'tpp_id_doc_path'             => null,
    'has_tpp_proof_of_address'    => 0,
    'tpp_proof_of_address_path'   => null,
    'supplier_purchase_id'         => null,
];

$intakeCtx  = null;
$intakeMode = false;
$intakeIsCompany = false;

// =========================================================================
// SKU helpers
// =========================================================================

/**
 * Suggest the next free SKU for a given source / vehicle combination.
 * Always returns a string. Uniqueness is also enforced by the DB index.
 */
function suggest_sku(PDO $pdo, string $source, ?int $vehicleId = null): string {
    if ($source === 'stripped' && $vehicleId) {
        $row = $pdo->prepare(
            'SELECT stock_code FROM vehicles WHERE id = :id'
        );
        $row->execute([':id' => $vehicleId]);
        $stock = (string) ($row->fetchColumn() ?: '');
        if ($stock === '') {
            return '';
        }
        // $stock is already stored as "AWG-0002", so don't double-prefix.
        $stmt = $pdo->prepare(
            "SELECT sku FROM parts
              WHERE vehicle_id = :vid
                AND source = 'stripped'
                AND sku LIKE :pat
              ORDER BY sku DESC LIMIT 1"
        );
        $stmt->execute([
            ':vid' => $vehicleId,
            ':pat' => $stock . '-P%',
        ]);
        $latest = (string) ($stmt->fetchColumn() ?: '');
        $next   = 1;
        if ($latest !== '' && preg_match('/-P(\d+)$/', $latest, $m)) {
            $next = (int) $m[1] + 1;
        }
        return sprintf('%s-P%02d', $stock, $next);
    }

    $prefix = match ($source) {
        'oem_new'     => 'OEM',
        'replacement' => 'REP',
        'third_party' => 'TPP',
        default       => '',
    };
    if ($prefix === '') return '';

    $stmt = $pdo->prepare(
        "SELECT sku FROM parts
          WHERE source = :src AND sku LIKE :pat
          ORDER BY sku DESC LIMIT 1"
    );
    $stmt->execute([':src' => $source, ':pat' => $prefix . '-%']);
    $latest = (string) ($stmt->fetchColumn() ?: '');
    $next   = 1;
    if ($latest !== '' && preg_match('/-(\d+)$/', $latest, $m)) {
        $next = (int) $m[1] + 1;
    }
    return sprintf('%s-%04d', $prefix, $next);
}

/**
 * Trim and uppercase the SKU; default to suggestion if blank.
 */
function normalise_sku(string $raw): string {
    $raw = strtoupper(trim($raw));
    return preg_replace('/[^A-Z0-9\-]/', '', $raw) ?? '';
}

// =========================================================================
// Load existing part (if editing)
// =========================================================================

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM parts WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        $flash = ['type' => 'danger', 'msg' => 'Part not found (id ' . $id . ').'];
    } else {
        $part   = array_merge($part, array_change_key_case($row, CASE_LOWER));
        $isNew  = false;
    }
}

// Stage 4c: supplier purchase (one buy, many parts)
if (!empty($part['supplier_purchase_id'])) {
    $ti = $pdo->prepare(
        'SELECT sp.*, s.name AS intake_supplier_name
         FROM supplier_purchases sp
         LEFT JOIN suppliers s ON s.id = sp.supplier_id
         WHERE sp.id = :id AND sp.is_active = 1'
    );
    $ti->execute([':id' => (int) $part['supplier_purchase_id']]);
    $ir = $ti->fetch(PDO::FETCH_ASSOC);
    if ($ir) {
        $intakeCtx  = array_change_key_case($ir, CASE_LOWER);
        $intakeIsCompany = !empty($intakeCtx['supplier_id']);
        $intakeMode = true;
    }
} elseif ($isNew && $preIntake > 0) {
    $ti = $pdo->prepare(
        'SELECT sp.*, s.name AS intake_supplier_name
         FROM supplier_purchases sp
         LEFT JOIN suppliers s ON s.id = sp.supplier_id
         WHERE sp.id = :id AND sp.is_active = 1'
    );
    $ti->execute([':id' => $preIntake]);
    $ir = $ti->fetch(PDO::FETCH_ASSOC);
    if ($ir) {
        $intakeCtx  = array_change_key_case($ir, CASE_LOWER);
        $intakeIsCompany = !empty($intakeCtx['supplier_id']);
        $intakeMode = true;
        if ($intakeIsCompany) {
            if ($preIntakeSource !== ''
                && in_array($preIntakeSource, ['oem_new', 'replacement', 'third_party'], true)) {
                $part['source'] = $preIntakeSource;
            } else {
                $part['source'] = 'oem_new';
            }
        } else {
            $part['source'] = 'third_party';
        }
    }
}

$hasListOnline = shop_parts_list_online_ready($pdo);

// =========================================================================
// POST handling
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        http_response_code(403);
        $flash = ['type' => 'danger', 'msg' => 'You do not have permission to change parts.'];
    } elseif (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = ['type' => 'danger', 'msg' => 'Security token invalid. Please reload.'];
    } else {
        $action = $_POST['action'] ?? '';

        try {
            // ---------------- save (create or update) -----------------
            if ($action === 'save') {
                $postPurchaseId = (int) ($_POST['supplier_purchase_id'] ?? $_POST['tpp_intake_id'] ?? 0);
                $fromIntake     = null;
                if ($postPurchaseId > 0) {
                    $qi = $pdo->prepare(
                        'SELECT * FROM supplier_purchases WHERE id = :id AND is_active = 1'
                    );
                    $qi->execute([':id' => $postPurchaseId]);
                    $fromIntake = $qi->fetch(PDO::FETCH_ASSOC);
                    if (!$fromIntake) {
                        throw new RuntimeException('Purchase not found or inactive.');
                    }
                }

                if ($fromIntake) {
                    $fi = array_change_key_case($fromIntake, CASE_LOWER);
                    $purchaseIsCompany = !empty($fi['supplier_id']);
                    if ($purchaseIsCompany) {
                        $source = (string) ($_POST['source'] ?? '');
                        if (!in_array($source, ['oem_new', 'replacement', 'third_party'], true)) {
                            throw new RuntimeException(
                                'For a supplier purchase, pick OEM new, Replacement, or Third-party per part line.'
                            );
                        }
                    } else {
                        $source = 'third_party';
                    }
                } else {
                    $source = (string) ($_POST['source'] ?? '');
                    if (!isset($SOURCE_OPTIONS[$source])) {
                        throw new RuntimeException('Pick a valid source.');
                    }
                }

                $name = trim((string) ($_POST['name'] ?? ''));
                if ($name === '') {
                    throw new RuntimeException('Part name is required.');
                }

                $vehicleId  = (int) ($_POST['vehicle_id'] ?? 0);
                $supplierId = (int) ($_POST['supplier_id'] ?? 0);
                $tppSupId   = (int) ($_POST['tpp_supplier_id'] ?? 0);
                $sellerName = trim((string) ($_POST['seller_name'] ?? ''));
                $sellerPh   = trim((string) ($_POST['seller_phone'] ?? ''));
                $sellerId   = trim((string) ($_POST['seller_id_number'] ?? ''));

                // Source-specific link rules.
                if ($fromIntake) {
                    $fi = array_change_key_case($fromIntake, CASE_LOWER);
                    $vehicleId = 0;
                    $supFromI  = (int) ($fi['supplier_id'] ?? 0);
                    if ($supFromI > 0) {
                        $supplierId = $supFromI;
                        $sellerName = $sellerPh = $sellerId = '';
                    } else {
                        $supplierId = 0;
                        $sellerName = trim((string) ($fi['seller_name'] ?? ''));
                        $sellerPh   = trim((string) ($fi['seller_phone'] ?? ''));
                        $sellerId   = trim((string) ($fi['seller_id_number'] ?? ''));
                    }
                } elseif ($source === 'stripped') {
                    if ($vehicleId <= 0) {
                        throw new RuntimeException('Stripped parts must be linked to a vehicle.');
                    }
                    $supplierId = 0;
                    $sellerName = $sellerPh = $sellerId = '';
                } elseif ($source === 'oem_new' || $source === 'replacement') {
                    $vehicleId  = 0;
                    $sellerName = $sellerPh = $sellerId = '';
                } else {
                    // third_party: supplier OR private individual (whichever tab they used)
                    $vehicleId = 0;
                    if ($tppSupId > 0) {
                        $supplierId = $tppSupId;
                        $sellerName = $sellerPh = $sellerId = '';
                    } elseif ($sellerName !== '') {
                        $supplierId = 0;
                    } else {
                        $supplierId = 0;
                    }
                }

                $cond = (string) ($_POST['condition_grade'] ?? 'good');
                if (!isset($CONDITION_OPTIONS[$cond])) {
                    throw new RuntimeException('Pick a valid condition.');
                }
                $status = (string) ($_POST['status'] ?? 'available');
                if (!isset($STATUS_OPTIONS[$status])) {
                    throw new RuntimeException('Pick a valid status.');
                }

                $cost     = (float) ($_POST['cost_price']     ?? 0);
                $asking   = (float) ($_POST['asking_price']   ?? 0);
                $discRaw  = trim((string) ($_POST['discount_price'] ?? ''));
                $discount = $discRaw === '' ? null : (float) $discRaw;
                $vatRate  = (float) ($_POST['vat_rate']       ?? 0);
                $qty      = max(0, (int) ($_POST['qty_on_hand'] ?? 1));
                $yard     = trim((string) ($_POST['yard_location'] ?? ''));
                $notes    = trim((string) ($_POST['notes']         ?? ''));
                $active   = !empty($_POST['is_active']) ? 1 : 0;
                $listOnline = 0;
                if ($hasListOnline) {
                    $listOnline = !empty($_POST['list_online']) ? 1 : 0;
                }

                // SKU: if user blanked the field, auto-suggest one.
                $sku = normalise_sku((string) ($_POST['sku'] ?? ''));
                if ($sku === '') {
                    $sku = suggest_sku($pdo, $source, $vehicleId > 0 ? $vehicleId : null);
                    if ($sku === '') {
                        throw new RuntimeException('Could not auto-suggest an SKU. Please type one or pick a vehicle first.');
                    }
                }

                // Pre-flight: SKU collision check (also enforced by DB unique index).
                $colStmt = $pdo->prepare(
                    'SELECT id FROM parts WHERE sku = :sku AND id <> :id'
                );
                $colStmt->execute([':sku' => $sku, ':id' => (int) $part['id']]);
                if ($colStmt->fetchColumn()) {
                    throw new RuntimeException('SKU "' . $sku . '" is already used by another part.');
                }

                // ---- Stage 4b: part-level SHGA (not used when linked to Stage 4c purchase) ----
                $tppCompliance = ($source === 'third_party' && !$fromIntake);
                $oldIdPath  = $part['tpp_id_doc_path']          ?? null;
                $oldPoaPath = $part['tpp_proof_of_address_path'] ?? null;

                if ($fromIntake) {
                    if (!empty($oldIdPath)) {
                        delete_uploaded_file($oldIdPath);
                    }
                    if (!empty($oldPoaPath)) {
                        delete_uploaded_file($oldPoaPath);
                    }
                    $tppIdPath   = null;
                    $tppPoaPath  = null;
                    $hasTppIdDoc = 0;
                    $hasTppPoa   = 0;
                } elseif (!$tppCompliance) {
                    if (!empty($oldIdPath)) {
                        delete_uploaded_file($oldIdPath);
                    }
                    if (!empty($oldPoaPath)) {
                        delete_uploaded_file($oldPoaPath);
                    }
                    $tppIdPath   = null;
                    $tppPoaPath  = null;
                    $hasTppIdDoc = 0;
                    $hasTppPoa   = 0;
                } else {
                    $tppIdPath   = $oldIdPath;
                    $tppPoaPath  = $oldPoaPath;
                    $hasTppPoa   = !empty($_POST['has_tpp_proof_of_address']) ? 1 : 0;
                    if (!$hasTppPoa && $oldPoaPath) {
                        delete_uploaded_file($oldPoaPath);
                        $tppPoaPath = null;
                    }
                    $hasTppIdDoc = !empty($tppIdPath) ? 1 : 0;
                }

                $tiiBind = null;
                if ($fromIntake) {
                    $tiiBind = $postPurchaseId;
                } elseif (!empty($part['supplier_purchase_id'])) {
                    $tiiBind = (int) $part['supplier_purchase_id'];
                }

                $bind = [
                    ':sku'    => $sku,
                    ':name'   => $name,
                    ':source' => $source,
                    ':vid'    => $vehicleId  > 0 ? $vehicleId  : null,
                    ':sid'    => $supplierId > 0 ? $supplierId : null,
                    ':snm'    => $sellerName !== '' ? $sellerName : null,
                    ':sph'    => $sellerPh   !== '' ? $sellerPh   : null,
                    ':sid_n'  => $sellerId   !== '' ? $sellerId   : null,
                    ':htid'   => $hasTppIdDoc,
                    ':tidp'   => $tppIdPath,
                    ':htpoa'  => $hasTppPoa,
                    ':tppoa'  => $tppPoaPath,
                    ':cond'   => $cond,
                    ':st'     => $status,
                    ':cp'     => $cost,
                    ':ap'     => $asking,
                    ':dp'     => $discount,
                    ':vr'     => $vatRate,
                    ':qty'    => $qty,
                    ':yd'     => $yard !== '' ? $yard : null,
                    ':nt'     => $notes !== '' ? $notes : null,
                    ':act'    => $active,
                    ':tii'    => $tiiBind,
                ];
                if ($hasListOnline) {
                    $bind[':lon'] = $listOnline;
                }

                $linkedIntake = is_array($fromIntake);
                $applyTppUploads = function (int $pid) use ($pdo, $tppCompliance, $linkedIntake): void {
                    if (!$tppCompliance || $linkedIntake || $pid <= 0) {
                        return;
                    }
                    if (!empty($_FILES['tpp_id_doc_file']['name'])) {
                        $cur = $pdo->prepare('SELECT tpp_id_doc_path FROM parts WHERE id = :id');
                        $cur->execute([':id' => $pid]);
                        $prev = $cur->fetchColumn();
                        if ($prev) {
                            delete_uploaded_file((string) $prev);
                        }
                        $rel = save_uploaded_part_compliance_doc(
                            $_FILES['tpp_id_doc_file'] ?? [],
                            $pid,
                            'tpp_id_doc'
                        );
                        $pdo->prepare(
                            'UPDATE parts SET tpp_id_doc_path = :p, has_tpp_id_doc = 1 WHERE id = :id'
                        )->execute([':p' => $rel, ':id' => $pid]);
                    }
                    if (!empty($_FILES['tpp_proof_of_address_file']['name'])) {
                        $cur = $pdo->prepare(
                            'SELECT tpp_proof_of_address_path FROM parts WHERE id = :id'
                        );
                        $cur->execute([':id' => $pid]);
                        $prev = $cur->fetchColumn();
                        if ($prev) {
                            delete_uploaded_file((string) $prev);
                        }
                        $rel = save_uploaded_part_compliance_doc(
                            $_FILES['tpp_proof_of_address_file'] ?? [],
                            $pid,
                            'tpp_proof_of_address'
                        );
                        $pdo->prepare(
                            'UPDATE parts SET tpp_proof_of_address_path = :p,
                                 has_tpp_proof_of_address = 1
                               WHERE id = :id'
                        )->execute([':p' => $rel, ':id' => $pid]);
                    }
                };

                if ($part['id'] > 0) {
                    $sql = 'UPDATE parts SET
                              sku = :sku, name = :name, source = :source,
                              vehicle_id = :vid, supplier_id = :sid,
                              seller_name = :snm, seller_phone = :sph,
                              seller_id_number = :sid_n,
                              supplier_purchase_id = :tii,
                              has_tpp_id_doc = :htid,
                              tpp_id_doc_path = :tidp,
                              has_tpp_proof_of_address = :htpoa,
                              tpp_proof_of_address_path = :tppoa,
                              condition_grade = :cond, status = :st,
                              cost_price = :cp, asking_price = :ap,
                              discount_price = :dp, vat_rate = :vr,
                              qty_on_hand = :qty,
                              yard_location = :yd, notes = :nt,
                              is_active = :act';
                    if ($hasListOnline) {
                        $sql .= ', list_online = :lon';
                    }
                    $sql .= ' WHERE id = :pid';
                    $bind[':pid'] = (int) $part['id'];
                    $pdo->prepare($sql)->execute($bind);
                    $applyTppUploads((int) $part['id']);
                    $flash = ['type' => 'success', 'msg' => 'Part updated.'];
                } else {
                    $colList = 'sku, name, source,
                               vehicle_id, supplier_id,
                               seller_name, seller_phone, seller_id_number,
                               supplier_purchase_id,
                               has_tpp_id_doc, tpp_id_doc_path,
                               has_tpp_proof_of_address, tpp_proof_of_address_path,
                               condition_grade, status,
                               cost_price, asking_price, discount_price, vat_rate,
                               qty_on_hand, yard_location, notes, is_active';
                    $valList = ':sku, :name, :source,
                               :vid, :sid,
                               :snm, :sph, :sid_n,
                               :tii,
                               :htid, :tidp, :htpoa, :tppoa,
                               :cond, :st,
                               :cp, :ap, :dp, :vr,
                               :qty, :yd, :nt, :act';
                    if ($hasListOnline) {
                        $colList .= ', list_online';
                        $valList .= ', :lon';
                    }
                    $sql = 'INSERT INTO parts
                              (' . $colList . ', created_by)
                            VALUES
                              (' . $valList . ', :cb)';
                    $bind[':cb'] = $_SESSION['user_id'] ?? null;
                    $pdo->prepare($sql)->execute($bind);
                    $newId = (int) $pdo->lastInsertId();
                    $applyTppUploads($newId);
                    header('Location: ' . APP_URL . '/part_edit.php?id=' . $newId . '&saved=1');
                    exit;
                }

                // Reload fresh row.
                $stmt = $pdo->prepare('SELECT * FROM parts WHERE id = :id');
                $stmt->execute([':id' => $part['id']]);
                $f = $stmt->fetch();
                $part = array_merge($part, $f ? array_change_key_case($f, CASE_LOWER) : []);
            }

            // ---------------- add photo -----------------
            elseif ($action === 'add_photo' && $part['id'] > 0) {
                $count = (int) $pdo->query(
                    'SELECT COUNT(*) FROM part_photos WHERE part_id = ' . (int) $part['id']
                )->fetchColumn();
                if ($count >= PART_PHOTO_LIMIT) {
                    throw new RuntimeException('Photo limit reached (' . PART_PHOTO_LIMIT . ').');
                }
                $rel = save_uploaded_part_photo($_FILES['photo_file'] ?? [], (int) $part['id']);
                $caption = trim((string) ($_POST['caption'] ?? '')) ?: null;
                $stmt = $pdo->prepare(
                    'INSERT INTO part_photos (part_id, path, caption, sort_order)
                     VALUES (:pid, :pa, :cap, :so)'
                );
                $stmt->execute([
                    ':pid' => (int) $part['id'],
                    ':pa'  => $rel,
                    ':cap' => $caption,
                    ':so'  => $count,
                ]);
                $flash = ['type' => 'success', 'msg' => 'Photo added.'];
            }

            // ---------------- delete photo -----------------
            elseif ($action === 'delete_photo' && $part['id'] > 0) {
                $photoId = (int) ($_POST['photo_id'] ?? 0);
                if ($photoId <= 0) {
                    throw new RuntimeException('Bad photo id.');
                }
                $stmt = $pdo->prepare(
                    'SELECT path FROM part_photos WHERE id = :pid AND part_id = :part'
                );
                $stmt->execute([':pid' => $photoId, ':part' => (int) $part['id']]);
                $path = $stmt->fetchColumn();
                if ($path) {
                    delete_uploaded_file($path);
                    $pdo->prepare('DELETE FROM part_photos WHERE id = :pid')
                        ->execute([':pid' => $photoId]);
                    $flash = ['type' => 'success', 'msg' => 'Photo removed.'];
                }
            }

            // ---------------- delete TPP compliance doc -----------------
            elseif ($action === 'delete_tpp_doc' && $part['id'] > 0) {
                $which = (string) ($_POST['which'] ?? '');
                if ($which === 'tpp_id_doc') {
                    $stmt = $pdo->prepare(
                        'SELECT tpp_id_doc_path FROM parts WHERE id = :id'
                    );
                    $stmt->execute([':id' => (int) $part['id']]);
                    $path = $stmt->fetchColumn();
                    if ($path) {
                        delete_uploaded_file((string) $path);
                    }
                    $pdo->prepare(
                        'UPDATE parts SET tpp_id_doc_path = NULL, has_tpp_id_doc = 0
                         WHERE id = :id'
                    )->execute([':id' => (int) $part['id']]);
                    $flash = ['type' => 'success', 'msg' => 'Seller ID scan removed.'];
                } elseif ($which === 'tpp_proof_of_address') {
                    $stmt = $pdo->prepare(
                        'SELECT tpp_proof_of_address_path FROM parts WHERE id = :id'
                    );
                    $stmt->execute([':id' => (int) $part['id']]);
                    $path = $stmt->fetchColumn();
                    if ($path) {
                        delete_uploaded_file((string) $path);
                    }
                    $pdo->prepare(
                        'UPDATE parts SET tpp_proof_of_address_path = NULL,
                             has_tpp_proof_of_address = 0
                         WHERE id = :id'
                    )->execute([':id' => (int) $part['id']]);
                    $flash = ['type' => 'success', 'msg' => 'Proof of address removed.'];
                } else {
                    throw new RuntimeException('Unknown document type.');
                }
            }

            // ---------------- link EPC variant -----------------
            elseif ($action === 'link_epc' && $part['id'] > 0) {
                $vid = (int) ($_POST['variant_id'] ?? 0);
                if ($vid <= 0) {
                    throw new RuntimeException('Pick an EPC variant first.');
                }
                $check = $pdo->prepare('SELECT id FROM epc_variants WHERE id = :v');
                $check->execute([':v' => $vid]);
                if (!$check->fetchColumn()) {
                    throw new RuntimeException('EPC variant not found.');
                }
                $stmt = $pdo->prepare(
                    'INSERT IGNORE INTO part_epc_links (part_id, variant_id)
                     VALUES (:pid, :v)'
                );
                $stmt->execute([':pid' => (int) $part['id'], ':v' => $vid]);
                $flash = ['type' => 'success', 'msg' => 'EPC tag added.'];
            }

            // ---------------- unlink EPC variant -----------------
            elseif ($action === 'unlink_epc' && $part['id'] > 0) {
                $vid = (int) ($_POST['variant_id'] ?? 0);
                $pdo->prepare(
                    'DELETE FROM part_epc_links WHERE part_id = :pid AND variant_id = :v'
                )->execute([':pid' => (int) $part['id'], ':v' => $vid]);
                $flash = ['type' => 'success', 'msg' => 'EPC tag removed.'];
            }

        } catch (Throwable $e) {
            $flash = ['type' => 'danger', 'msg' => APP_DEBUG ? $e->getMessage() : 'Save failed: ' . $e->getMessage()];
        }
    }

    // Reload part after any successful POST (except create which redirected).
    if ($part['id'] > 0) {
        $stmt = $pdo->prepare('SELECT * FROM parts WHERE id = :id');
        $stmt->execute([':id' => $part['id']]);
        $row = $stmt->fetch();
        if ($row) {
            $part = array_merge($part, array_change_key_case($row, CASE_LOWER));
        }
    }
}

if (!empty($_GET['saved'])) {
    $flash = ['type' => 'success', 'msg' => 'Part created.'];
}

// =========================================================================
// Auxiliary data for the form
// =========================================================================

$vehicleList = $pdo->query(
    "SELECT id, stock_code, make, model, year
     FROM vehicles
     WHERE is_active = 1
     ORDER BY COALESCE(stock_code, '') ASC, make ASC, model ASC"
)->fetchAll();

$supplierList = $pdo->query(
    "SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name ASC"
)->fetchAll();

$photos = [];
if ($part['id'] > 0) {
    $stmt = $pdo->prepare(
        'SELECT id, path, caption FROM part_photos
         WHERE part_id = :pid
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute([':pid' => (int) $part['id']]);
    $photos = $stmt->fetchAll();
}

$epcLinks = [];
if ($part['id'] > 0) {
    $stmt = $pdo->prepare(
        "SELECT v.id AS variant_id, v.name AS variant_name,
                c.name AS comp, ss.name AS subsys, t.name AS type,
                sc.name AS subcat, ct.name AS cat
         FROM part_epc_links pel
         JOIN epc_variants     v  ON v.id = pel.variant_id
         JOIN epc_components   c  ON c.id = v.component_id
         JOIN epc_subsystems   ss ON ss.id = c.subsystem_id
         JOIN epc_types        t  ON t.id = ss.type_id
         JOIN epc_subcategories sc ON sc.id = t.subcategory_id
         JOIN epc_categories   ct ON ct.id = sc.category_id
         WHERE pel.part_id = :pid
         ORDER BY ct.name, sc.name, t.name, ss.name, c.name, v.name"
    );
    $stmt->execute([':pid' => (int) $part['id']]);
    $epcLinks = $stmt->fetchAll();
}

// Pre-compute the SKU we'd suggest if the user clears the field.
$skuSuggestion = $part['sku'] !== ''
    ? (string) $part['sku']
    : suggest_sku($pdo, (string) $part['source'], $part['vehicle_id'] ? (int) $part['vehicle_id'] : null);

$pageTitle = $isNew ? 'Add part' : 'Edit part: ' . $part['name'];
include __DIR__ . '/includes/header.php';
?>

<div class="container py-3" style="max-width: 980px;">

  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h1 class="h4 mb-1">
        <i class="bi bi-box-seam"></i>
        <?= $isNew ? 'Add part' : 'Edit part' ?>
        <?php if (!$isNew): ?>
          <span class="text-muted small">·
            <span class="font-monospace"><?= e($part['sku']) ?></span>
          </span>
        <?php endif; ?>
      </h1>
      <?php if (!$isNew): ?>
        <div class="text-muted small">
          <?= e($part['name']) ?>
          <?php if (!$part['is_active']): ?>
            <span class="badge bg-secondary ms-2">Inactive</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(APP_URL) ?>/parts_admin.php">
      <i class="bi bi-arrow-left"></i> Back to parts
    </a>
  </div>

  <?php if ($flash['msg']): ?>
    <div class="alert alert-<?= e($flash['type']) ?> py-2"><?= e($flash['msg']) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" id="partForm">
    <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">

    <?php if ($intakeMode && $intakeCtx): ?>
    <input type="hidden" name="supplier_purchase_id" value="<?= (int) $intakeCtx['id'] ?>">
    <div class="card mb-3 border-danger shadow-sm">
      <div class="card-header bg-light py-2">
        <strong><i class="bi bi-boxes"></i> Supplier purchase batch</strong>
        <span class="text-muted small">— who you bought from is saved once; each part line sets only part details</span>
      </div>
      <div class="card-body py-2 small">
        <a href="<?= e(APP_URL) ?>/supplier_purchase_edit.php?id=<?= (int) $intakeCtx['id'] ?>" class="fw-bold">
          Open purchase #<?= (int) $intakeCtx['id'] ?></a>
        <?php if (!empty($intakeCtx['purchase_ref'])): ?>
          · <?= e($intakeCtx['purchase_ref']) ?>
        <?php endif; ?>
        <span class="d-block mt-1 text-muted">
          <?php if (!empty($intakeCtx['supplier_id'])): ?>
            Supplier: <strong><?= e($intakeCtx['intake_supplier_name'] ?? '') ?></strong>
            <span class="d-block">You can set each new line as <strong>OEM new</strong>, <strong>replacement</strong>, or <strong>third-party</strong> with this supplier.</span>
          <?php else: ?>
            Private: <strong><?= e($intakeCtx['seller_name'] ?? '') ?></strong>
            <?php if (!empty($intakeCtx['seller_phone'])): ?> · <?= e($intakeCtx['seller_phone']) ?><?php endif; ?>
            <span class="d-block">Each part line is <strong>third-party</strong> (private seller on file).</span>
          <?php endif; ?>
        </span>
        <?php if (!empty($intakeCtx['tpp_id_doc_path']) || !empty($intakeCtx['tpp_proof_of_address_path'])): ?>
          <span class="d-block mt-1">
            Docs on purchase:
            <?php if (!empty($intakeCtx['tpp_id_doc_path'])): ?>
              <a href="<?= e(uploads_public_url($intakeCtx['tpp_id_doc_path'])) ?>" target="_blank" rel="noopener">ID/CIPC</a>
            <?php endif; ?>
            <?php if (!empty($intakeCtx['tpp_proof_of_address_path'])): ?>
              <?php if (!empty($intakeCtx['tpp_id_doc_path'])): ?> · <?php endif; ?>
              <a href="<?= e(uploads_public_url($intakeCtx['tpp_proof_of_address_path'])) ?>" target="_blank" rel="noopener">Proof of address</a>
            <?php endif; ?>
          </span>
        <?php endif; ?>
        <?php if ($intakeMode && $intakeCtx && !$isNew): ?>
          <div class="d-flex flex-wrap gap-2 mt-3 pt-2 border-top">
            <a class="btn btn-sm btn-danger" href="<?= e(APP_URL) ?>/part_edit.php?supplier_purchase_id=<?= (int) $intakeCtx['id'] ?>">
              <i class="bi bi-plus-lg"></i> Add another part to this purchase
            </a>
            <a class="btn btn-sm btn-outline-dark" href="<?= e(APP_URL) ?>/supplier_purchase_edit.php?id=<?= (int) $intakeCtx['id'] ?>">
              <i class="bi bi-boxes"></i> View purchase &amp; all parts
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ============================ STOCK & IDENTITY ============================ -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header bg-light">
        <strong>1. Stock &amp; identity</strong>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small">Source <span class="text-danger">*</span></label>
            <?php if ($intakeMode && $intakeCtx && $intakeIsCompany): ?>
              <select name="source" id="sourceSelect" class="form-select" required <?= $canEdit ? '' : 'disabled' ?>>
                <option value="oem_new"     <?= $part['source'] === 'oem_new'     ? 'selected' : '' ?>>OEM new (genuine)</option>
                <option value="replacement" <?= $part['source'] === 'replacement' ? 'selected' : '' ?>>Replacement (aftermarket new)</option>
                <option value="third_party" <?= $part['source'] === 'third_party' ? 'selected' : '' ?>>Third-party (this supplier / deal)</option>
              </select>
            <?php elseif ($intakeMode && $intakeCtx && !$intakeIsCompany): ?>
              <input type="hidden" name="source" value="third_party">
              <div class="form-control border bg-light py-2 small mb-0">
                Third-party (private seller on purchase #<?= (int) $intakeCtx['id'] ?>)
              </div>
            <?php else: ?>
            <select name="source" id="sourceSelect" class="form-select" required <?= $canEdit ? '' : 'disabled' ?>>
              <?php foreach ($SOURCE_OPTIONS as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= $part['source'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label small">SKU
              <span class="text-muted">(auto-suggest, edit if needed)</span>
            </label>
            <div class="input-group">
              <input type="text" name="sku" id="skuField"
                     class="form-control font-monospace"
                     value="<?= e($skuSuggestion) ?>"
                     placeholder="will be auto-generated"
                     <?= $canEdit ? '' : 'readonly' ?>>
              <button type="button" class="btn btn-outline-secondary" id="resetSku"
                      title="Reset to suggested SKU">
                <i class="bi bi-arrow-clockwise"></i>
              </button>
            </div>
            <!-- Soft warning when the SKU prefix doesn't match the source. -->
            <!-- Never blocks save; just nudges the user to pick a consistent code. -->
            <div id="awSkuMismatch"
                 class="form-text small text-warning d-none mt-1">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <span id="awSkuMismatchMsg"></span>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label small">Part name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control"
                   value="<?= e($part['name']) ?>"
                   placeholder="e.g. Headlight, left  /  Front bumper  /  Radiator"
                   required <?= $canEdit ? '' : 'readonly' ?>>
          </div>
        </div>
      </div>
    </div>

    <?php if (!$intakeMode): ?>
    <!-- ============================ SOURCE LINK ============================ -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header bg-light">
        <strong>2. Where did this part come from?</strong>
        <span class="text-muted small">— different sources need different details</span>
      </div>
      <div class="card-body">

        <!-- Stripped: vehicle dropdown -->
        <div class="src-block" data-src="stripped">
          <label class="form-label small">Vehicle <span class="text-danger">*</span></label>
          <select name="vehicle_id" id="vehicleSelect" class="form-select" <?= $canEdit ? '' : 'disabled' ?>>
            <option value="0">— pick a vehicle —</option>
            <?php foreach ($vehicleList as $vh): ?>
              <option value="<?= (int) $vh['id'] ?>" <?= (int) $part['vehicle_id'] === (int) $vh['id'] ? 'selected' : '' ?>>
                <?= e($vh['stock_code'] ?: '(no stock code)') ?> ·
                <?= e($vh['make']) ?> <?= e($vh['model']) ?> (<?= (int) $vh['year'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text small">SKU prefix follows the vehicle's stock code (e.g. <code>AWG-0002-P03</code>).</div>
        </div>

        <!-- OEM / Replacement: supplier dropdown (optional) -->
        <div class="src-block" data-src="oem_new replacement">
          <label class="form-label small">Supplier <span class="text-muted">(optional)</span></label>
          <select name="supplier_id" class="form-select" <?= $canEdit ? '' : 'disabled' ?>>
            <option value="0">— none —</option>
            <?php foreach ($supplierList as $sp): ?>
              <option value="<?= (int) $sp['id'] ?>" <?= (int) $part['supplier_id'] === (int) $sp['id'] ? 'selected' : '' ?>>
                <?= e($sp['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text small">Where you bought the new part from. Leave blank if unknown.</div>
        </div>

        <!-- Third-party: tabs (supplier OR private individual) -->
        <?php
          $tppTabSupplier = ($part['source'] === 'third_party' && !empty($part['supplier_id']));
        ?>
        <div class="src-block" data-src="third_party">
          <ul class="nav nav-pills mb-2" role="tablist">
            <li class="nav-item">
              <button class="nav-link <?= $tppTabSupplier ? 'active' : '' ?>" type="button" data-bs-toggle="pill" data-bs-target="#tppSupplier">
                <i class="bi bi-building"></i> From a supplier
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link <?= !$tppTabSupplier ? 'active' : '' ?>" type="button" data-bs-toggle="pill" data-bs-target="#tppIndividual">
                <i class="bi bi-person"></i> From a private individual
              </button>
            </li>
          </ul>
          <div class="tab-content">
            <div class="tab-pane fade <?= $tppTabSupplier ? 'show active' : '' ?>" id="tppSupplier">
              <label class="form-label small">Supplier</label>
              <select name="tpp_supplier_id" class="form-select" <?= $canEdit ? '' : 'disabled' ?>>
                <option value="0">— none —</option>
                <?php foreach ($supplierList as $sp): ?>
                  <option value="<?= (int) $sp['id'] ?>"
                    <?= (int) $part['supplier_id'] === (int) $sp['id'] && $part['source'] === 'third_party' ? 'selected' : '' ?>>
                    <?= e($sp['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text small text-muted mt-1">
                Company or shop on your suppliers list — pick them here. You can still attach
                ID/company and proof-of-address scans in <strong>SHGA compliance</strong> below
                (same fields for a private seller or a company purchase).
              </div>
            </div>
            <div class="tab-pane fade <?= !$tppTabSupplier ? 'show active' : '' ?>" id="tppIndividual">
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label small">Seller's full name</label>
                  <input type="text" name="seller_name" class="form-control"
                         value="<?= e($part['seller_name']) ?>"
                         placeholder="e.g. John Smith" <?= $canEdit ? '' : 'readonly' ?>>
                </div>
                <div class="col-md-3">
                  <label class="form-label small">SA ID number</label>
                  <input type="text" name="seller_id_number" class="form-control"
                         value="<?= e($part['seller_id_number']) ?>"
                         placeholder="13 digits" <?= $canEdit ? '' : 'readonly' ?>>
                </div>
                <div class="col-md-3">
                  <label class="form-label small">Phone</label>
                  <input type="text" name="seller_phone" class="form-control"
                         value="<?= e($part['seller_phone']) ?>"
                         placeholder="e.g. 082..." <?= $canEdit ? '' : 'readonly' ?>>
                </div>
              </div>
            </div>
          </div>

          <!-- File inputs must NOT live inside a hidden .tab-pane — browsers may omit them on submit -->
          <div class="mt-3 pt-3 border-top" id="tppShgaSection">
            <div class="alert alert-light border small mb-2">
              <strong><i class="bi bi-file-earmark-lock"></i> SHGA compliance (Second-Hand Goods Act)</strong>
              — use for <strong>private individuals</strong> (ID + proof of residence) or
              <strong>company / supplier</strong> purchases (e.g. copy of company registration + proof of address, or
              any paperwork you keep for second-hand stock). Max 5 MB, PDF or image.
              <span class="d-block text-muted mt-1">Saves with <em><?= $isNew ? 'Create part' : 'Save changes' ?></em>.</span>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                  <div class="small fw-bold mb-2">ID / CIPC (or main compliance scan)</div>
                  <?php if (!empty($part['tpp_id_doc_path'])): ?>
                    <div class="small text-success mb-2">
                      <i class="bi bi-check-circle"></i> On file
                      <a href="<?= e(uploads_public_url($part['tpp_id_doc_path'])) ?>"
                         target="_blank" rel="noopener" class="ms-1">View</a>
                    </div>
                  <?php else: ?>
                    <div class="small text-warning mb-2">Not uploaded</div>
                  <?php endif; ?>
                  <?php if ($canEdit): ?>
                    <input type="file" name="tpp_id_doc_file" class="form-control form-control-sm"
                           accept=".pdf,image/*">
                    <div class="form-text small">Replaces any previous file on save.</div>
                  <?php endif; ?>
                  <?php if ($canEdit && !empty($part['tpp_id_doc_path']) && (int) $part['id'] > 0): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger mt-2"
                            onclick="if(confirm('Remove this file?')){document.getElementById('delTppIdDoc').submit();}">
                      <i class="bi bi-trash"></i> Remove
                    </button>
                  <?php endif; ?>
                </div>
              </div>
              <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                  <div class="small fw-bold mb-2">Proof of residence (seller or business)</div>
                  <?php if (!empty($part['tpp_proof_of_address_path'])): ?>
                    <div class="small text-success mb-2">
                      <i class="bi bi-check-circle"></i> On file
                      <a href="<?= e(uploads_public_url($part['tpp_proof_of_address_path'])) ?>"
                         target="_blank" rel="noopener" class="ms-1">View</a>
                    </div>
                  <?php else: ?>
                    <div class="small text-warning mb-2">Not uploaded</div>
                  <?php endif; ?>
                  <?php if ($canEdit): ?>
                    <div class="form-check mb-2">
                      <input class="form-check-input" type="checkbox" id="tppHpoa" value="1"
                             name="has_tpp_proof_of_address"
                             <?= !empty($part['has_tpp_proof_of_address']) ? 'checked' : '' ?>>
                      <label class="form-check-label small" for="tppHpoa">
                        I have this document on file (tick after uploading)
                      </label>
                    </div>
                    <input type="file" name="tpp_proof_of_address_file" class="form-control form-control-sm"
                           accept=".pdf,image/*">
                  <?php endif; ?>
                  <?php if ($canEdit && !empty($part['tpp_proof_of_address_path']) && (int) $part['id'] > 0): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger mt-2"
                            onclick="if(confirm('Remove this file?')){document.getElementById('delTppPoa').submit();}">
                      <i class="bi bi-trash"></i> Remove
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
    <?php endif; ?>

    <!-- ============================ CONDITION & STATUS ============================ -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header bg-light">
        <strong>3. Condition &amp; status</strong>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small">Condition</label>
            <select name="condition_grade" class="form-select" <?= $canEdit ? '' : 'disabled' ?>>
              <?php foreach ($CONDITION_OPTIONS as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= $part['condition_grade'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label small">Status</label>
            <select name="status" class="form-select" <?= $canEdit ? '' : 'disabled' ?>>
              <?php foreach ($STATUS_OPTIONS as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= $part['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Qty on hand</label>
            <input type="number" min="0" step="1" name="qty_on_hand" class="form-control"
                   value="<?= e((string) $part['qty_on_hand']) ?>" <?= $canEdit ? '' : 'readonly' ?>>
            <div class="form-text small">Usually 1 for stripped parts.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================ PRICING ============================ -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header bg-light">
        <strong>4. Pricing</strong>
        <span class="text-muted small">— all amounts in Rands</span>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label small">Cost price</label>
            <div class="input-group">
              <span class="input-group-text">R</span>
              <input type="number" min="0" step="0.01" name="cost_price" class="form-control"
                     value="<?= e((string) $part['cost_price']) ?>" <?= $canEdit ? '' : 'readonly' ?>>
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Asking price</label>
            <div class="input-group">
              <span class="input-group-text">R</span>
              <input type="number" min="0" step="0.01" name="asking_price" class="form-control"
                     value="<?= e((string) $part['asking_price']) ?>" <?= $canEdit ? '' : 'readonly' ?>>
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label small">Discount price <span class="text-muted">(optional)</span></label>
            <div class="input-group">
              <span class="input-group-text">R</span>
              <input type="number" min="0" step="0.01" name="discount_price" class="form-control"
                     value="<?= e($part['discount_price'] !== null ? (string) $part['discount_price'] : '') ?>"
                     placeholder="e.g. on sale" <?= $canEdit ? '' : 'readonly' ?>>
            </div>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <a class="small text-muted" data-bs-toggle="collapse" href="#vatBlock" role="button">
              <i class="bi bi-gear"></i> VAT settings (advanced)
            </a>
          </div>
          <div class="col-12 collapse" id="vatBlock">
            <div class="alert alert-light border small mb-0">
              <strong>VAT rate</strong> — leave at <code>0.00</code> if your business is not VAT-registered.
              Set to <code>15.00</code> when you register for VAT.
              <div class="mt-2" style="max-width: 220px;">
                <div class="input-group input-group-sm">
                  <input type="number" min="0" max="100" step="0.01" name="vat_rate" class="form-control"
                         value="<?= e((string) $part['vat_rate']) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                  <span class="input-group-text">%</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================ YARD & NOTES ============================ -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header bg-light">
        <strong>5. Yard location &amp; notes</strong>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small">Yard location</label>
            <input type="text" name="yard_location" class="form-control"
                   value="<?= e($part['yard_location']) ?>"
                   placeholder="e.g. shelf A2, bin 14, bay 3"
                   <?= $canEdit ? '' : 'readonly' ?>>
          </div>
          <div class="col-md-6">
            <label class="form-label small">&nbsp;</label>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="is_active" value="1" id="activeCheck"
                     <?= $part['is_active'] ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
              <label class="form-check-label" for="activeCheck">Active (visible in listings)</label>
            </div>
            <?php if ($hasListOnline): ?>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="list_online" value="1" id="listOnlineCheck"
                       <?= !empty($part['list_online']) ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                <label class="form-check-label" for="listOnlineCheck">List on public website</label>
                <div class="form-text small">Shows on public <code>/shop/</code> when Available with stock.
                  <strong>Buy online:</strong> <strong>OEM new</strong> or <strong>Replacement</strong> parts graded <strong>New</strong>, <strong>Good</strong> or <strong>Fair</strong>. Third-party / stripped / Poor / Scrap use <strong>Enquiry</strong> — customers use Message.</div>
              </div>
            <?php endif; ?>
          </div>
          <div class="col-12">
            <label class="form-label small">Notes</label>
            <textarea name="notes" rows="2" class="form-control"
                      placeholder="defects, history, anything useful..."
                      <?= $canEdit ? '' : 'readonly' ?>><?= e($part['notes']) ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <?php if ($canEdit): ?>
      <div class="d-flex gap-2 mb-2 flex-wrap">
        <button type="submit" class="btn btn-danger">
          <i class="bi bi-check-lg"></i>
          <?= $isNew ? 'Create part' : 'Save changes' ?>
        </button>
        <a class="btn btn-outline-secondary" href="<?= e(
            ($intakeMode && $intakeCtx)
                ? APP_URL . '/supplier_purchase_edit.php?id=' . (int) $intakeCtx['id']
                : APP_URL . '/parts_admin.php'
        ) ?>"><?= ($intakeMode && $intakeCtx) ? 'Back to purchase' : 'Cancel' ?></a>
      </div>
      <?php if ($intakeMode && $intakeCtx && !$isNew): ?>
        <p class="small text-muted mb-4">
          <i class="bi bi-info-circle"></i>
          <strong>Next part:</strong> use <strong>Add another part</strong> right below (photos are optional — skip section 6 if you have no pictures yet).
        </p>
      <?php endif; ?>
    <?php endif; ?>

  </form>

  <?php if ($intakeMode && $intakeCtx && !$isNew): ?>
  <div class="card border-danger shadow-sm mb-3">
    <div class="card-body py-3 d-flex flex-wrap align-items-center gap-2">
      <span class="me-2 small"><strong>Done with this part?</strong> Skip photos if you have none — they’re optional.</span>
      <a class="btn btn-danger" href="<?= e(APP_URL) ?>/part_edit.php?supplier_purchase_id=<?= (int) $intakeCtx['id'] ?>">
        <i class="bi bi-plus-lg"></i> Add another part to this purchase
      </a>
      <a class="btn btn-outline-dark" href="<?= e(APP_URL) ?>/supplier_purchase_edit.php?id=<?= (int) $intakeCtx['id'] ?>">
        <i class="bi bi-list-ul"></i> See all parts in this purchase
      </a>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$isNew && $canEdit && (int) $part['id'] > 0): ?>
    <form method="post" id="delTppIdDoc" class="d-none">
      <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="delete_tpp_doc">
      <input type="hidden" name="which"  value="tpp_id_doc">
    </form>
    <form method="post" id="delTppPoa" class="d-none">
      <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="delete_tpp_doc">
      <input type="hidden" name="which"  value="tpp_proof_of_address">
    </form>
  <?php endif; ?>

  <?php if (!$isNew): // ===== Photos & EPC visible only after first save ===== ?>

    <!-- ============================ PHOTOS ============================ -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <strong>6. Photos</strong>
          <span class="text-muted small fw-normal d-block">Optional — you can add pictures later. Not required to continue.</span>
        </div>
        <span class="small text-muted">
          <?= count($photos) ?> / <?= PART_PHOTO_LIMIT ?> used
        </span>
      </div>
      <div class="card-body">
        <?php if (count($photos) > 0): ?>
          <div class="row g-2 mb-3">
            <?php foreach ($photos as $ph): ?>
              <div class="col-6 col-md-3">
                <div class="position-relative border rounded p-1">
                  <a href="<?= e(uploads_public_url($ph['path'])) ?>" target="_blank">
                    <img src="<?= e(uploads_public_url($ph['path'])) ?>" class="img-fluid rounded"
                         style="height:120px; object-fit:cover; width:100%;">
                  </a>
                  <?php if (!empty($ph['caption'])): ?>
                    <div class="small text-muted mt-1 text-truncate"><?= e($ph['caption']) ?></div>
                  <?php endif; ?>
                  <?php if ($canEdit): ?>
                    <form method="post" class="position-absolute top-0 end-0"
                          onsubmit="return confirm('Delete this photo?');">
                      <input type="hidden" name="csrf"     value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action"   value="delete_photo">
                      <input type="hidden" name="photo_id" value="<?= (int) $ph['id'] ?>">
                      <button class="btn btn-sm btn-danger rounded-circle" type="submit"
                              style="width:28px; height:28px; padding:0;" title="Delete">
                        <i class="bi bi-x"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($canEdit && count($photos) < PART_PHOTO_LIMIT): ?>
          <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
            <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_photo">
            <div class="col-md-5">
              <label class="form-label small">Photo</label>
              <input type="file" name="photo_file" class="form-control" accept="image/*" required>
            </div>
            <div class="col-md-5">
              <label class="form-label small">Caption (optional)</label>
              <input type="text" name="caption" class="form-control" placeholder="e.g. left side, scratch on rim">
            </div>
            <div class="col-md-2 d-grid">
              <button class="btn btn-outline-dark" type="submit">
                <i class="bi bi-cloud-upload"></i> Add
              </button>
            </div>
          </form>
          <div class="form-text small mt-2">JPG / PNG / WEBP, up to 5 MB each.</div>
        <?php elseif ($canEdit): ?>
          <div class="alert alert-warning small mb-0">
            Photo limit reached (<?= PART_PHOTO_LIMIT ?>). Delete one to add another.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ============================ EPC TAGS ============================ -->
    <div class="card mb-4 shadow-sm">
      <div class="card-header bg-light">
        <strong>7. EPC tags</strong>
        <span class="text-muted small">— categorize this part inside the 6-level catalogue</span>
      </div>
      <div class="card-body">
        <?php if (count($epcLinks) > 0): ?>
          <div class="mb-3">
            <?php foreach ($epcLinks as $lk): ?>
              <span class="badge bg-light text-dark border me-1 mb-1 p-2">
                <i class="bi bi-tag"></i>
                <?= e($lk['cat']) ?> &rsaquo; <?= e($lk['subcat']) ?> &rsaquo; <?= e($lk['type']) ?>
                &rsaquo; <?= e($lk['subsys']) ?> &rsaquo; <?= e($lk['comp']) ?>
                &rsaquo; <strong><?= e($lk['variant_name']) ?></strong>
                <?php if ($canEdit): ?>
                  <form method="post" class="d-inline ms-1"
                        onsubmit="return confirm('Remove this EPC tag?');">
                    <input type="hidden" name="csrf"       value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action"     value="unlink_epc">
                    <input type="hidden" name="variant_id" value="<?= (int) $lk['variant_id'] ?>">
                    <button class="btn btn-link btn-sm p-0 text-danger" type="submit" title="Remove">
                      <i class="bi bi-x-circle"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($canEdit): ?>
          <form method="post" class="row g-2 align-items-end">
            <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="link_epc">

            <?php
              // Build the same 6 cascading dropdowns vehicle_edit.php uses.
              $catList = $pdo->query(
                "SELECT id, name FROM epc_categories WHERE is_active = 1 ORDER BY sort_order, name"
              )->fetchAll();
            ?>

            <div class="col-md-2">
              <label class="form-label small">Category</label>
              <select id="epcCat" class="form-select form-select-sm">
                <option value="">—</option>
                <?php foreach ($catList as $c): ?>
                  <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label small">Subcategory</label>
              <select id="epcSubcat" class="form-select form-select-sm" disabled><option>—</option></select>
            </div>
            <div class="col-md-2">
              <label class="form-label small">Type</label>
              <select id="epcType" class="form-select form-select-sm" disabled><option>—</option></select>
            </div>
            <div class="col-md-2">
              <label class="form-label small">Subsystem</label>
              <select id="epcSubsys" class="form-select form-select-sm" disabled><option>—</option></select>
            </div>
            <div class="col-md-2">
              <label class="form-label small">Component</label>
              <select id="epcComp" class="form-select form-select-sm" disabled><option>—</option></select>
            </div>
            <div class="col-md-2">
              <label class="form-label small">Variant</label>
              <select id="epcVariant" name="variant_id" class="form-select form-select-sm" disabled>
                <option value="">—</option>
              </select>
            </div>
            <div class="col-12 text-end">
              <button class="btn btn-sm btn-outline-dark" type="submit" id="addEpcBtn" disabled>
                <i class="bi bi-plus-lg"></i> Add EPC tag
              </button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>

  <?php else: ?>
    <div class="alert alert-info small">
      Photos and EPC tags can be added after the part is created.
    </div>
  <?php endif; ?>

</div>

<!-- ============================ JS: source toggle, SKU reset, EPC cascade ============================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
  const sourceSelect = document.getElementById('sourceSelect');
  const blocks       = document.querySelectorAll('.src-block');
  const skuField     = document.getElementById('skuField');
  const resetSkuBtn  = document.getElementById('resetSku');
  const vehicleSel   = document.getElementById('vehicleSelect');

  function toggleSrcBlocks() {
    if (!sourceSelect) return;
    const v = sourceSelect.value;
    blocks.forEach(b => {
      const allowed = (b.getAttribute('data-src') || '').split(/\s+/);
      b.style.display = allowed.includes(v) ? 'block' : 'none';
    });
  }
  if (sourceSelect) {
    sourceSelect.addEventListener('change', toggleSrcBlocks);
    toggleSrcBlocks();
  }

  // Third-party: inactive tab panes are display:none — some browsers omit
  // file inputs and other fields inside them on submit. Unhide before POST.
  const partForm = document.getElementById('partForm');
  if (partForm) {
    partForm.addEventListener('submit', function() {
      document.querySelectorAll('#tppSupplier, #tppIndividual').forEach(function(pane) {
        pane.classList.add('show', 'active');
        pane.style.setProperty('display', 'block', 'important');
      });
    });
  }

  // SKU reset button: re-suggest based on current source + vehicle.
  function suggestSku() {
    const src = sourceSelect.value;
    const vid = vehicleSel ? parseInt(vehicleSel.value || '0', 10) : 0;
    const params = new URLSearchParams({ action: 'suggest_sku', source: src });
    if (vid > 0) params.set('vehicle_id', String(vid));
    fetch('<?= e(APP_URL) ?>/part_edit.php?' + params.toString(), {
      headers: { 'X-Suggest-Sku': '1' }
    }).catch(() => {});
  }
  resetSkuBtn && resetSkuBtn.addEventListener('click', () => {
    skuField.value = '';
    skuField.placeholder = 'will be auto-generated on save';
    skuField.focus();
    checkSkuPrefix();
  });

  // -------------------------------------------------------------
  // Soft SKU/source consistency check.
  // Mirrors suggest_sku() prefixes: OEM-, REP-, TPP-.
  // Stripped SKUs follow the vehicle stock code (e.g. AWG-0002-P03)
  // so we just flag if a stripped SKU starts with a different
  // source's known prefix.
  // -------------------------------------------------------------
  const SRC_PREFIX = {
    oem_new:     'OEM-',
    replacement: 'REP-',
    third_party: 'TPP-'
  };
  const KNOWN_PREFIXES = ['OEM-', 'REP-', 'TPP-'];
  const warnBox = document.getElementById('awSkuMismatch');
  const warnMsg = document.getElementById('awSkuMismatchMsg');

  function showWarn(msg) {
    if (!warnBox) return;
    warnMsg.textContent = msg;
    warnBox.classList.remove('d-none');
  }
  function hideWarn() {
    if (!warnBox) return;
    warnBox.classList.add('d-none');
  }

  function checkSkuPrefix() {
    if (!sourceSelect || !skuField || !warnBox) return;
    const src = sourceSelect.value;
    const sku = (skuField.value || '').trim().toUpperCase();
    if (sku === '') { hideWarn(); return; }

    const expected = SRC_PREFIX[src];
    if (expected) {
      if (!sku.startsWith(expected)) {
        const wrong = KNOWN_PREFIXES.find(p => sku.startsWith(p));
        if (wrong) {
          showWarn('Heads up: SKU starts with "' + wrong +
            '" but you picked source "' + sourceSelect.options[sourceSelect.selectedIndex].text +
            '". Expected prefix: "' + expected + '". You can save anyway, or click ↻ to auto-suggest a matching code.');
        } else {
          showWarn('Heads up: this source usually uses SKUs starting with "' +
            expected + '". You can save anyway, or click ↻ to auto-suggest one.');
        }
        return;
      }
    } else if (src === 'stripped') {
      const wrong = KNOWN_PREFIXES.find(p => sku.startsWith(p));
      if (wrong) {
        showWarn('Heads up: SKU starts with "' + wrong +
          '" but the source is "Stripped". Stripped SKUs usually start with the vehicle stock code (e.g. AWG-0002-P03). You can save anyway, or click ↻ to auto-suggest one.');
        return;
      }
    }
    hideWarn();
  }

  if (sourceSelect) sourceSelect.addEventListener('change', checkSkuPrefix);
  if (skuField)     skuField.addEventListener('input',     checkSkuPrefix);
  checkSkuPrefix();

  // ---------------- EPC cascade ----------------
  const ids = ['epcCat','epcSubcat','epcType','epcSubsys','epcComp','epcVariant'];
  const sels = ids.map(id => document.getElementById(id));
  const addBtn = document.getElementById('addEpcBtn');
  if (sels[0] && sels.every(Boolean)) {
    function reset(from) {
      for (let i = from; i < sels.length; i++) {
        sels[i].innerHTML = '<option value="">—</option>';
        sels[i].disabled = true;
      }
      if (addBtn) addBtn.disabled = true;
    }
    function fetchChildren(parentLevel, parentId, target) {
      target.innerHTML = '<option value="">loading…</option>';
      target.disabled = true;
      const params = new URLSearchParams({ parent_level: parentLevel, parent_id: parentId });
      fetch('<?= e(APP_URL) ?>/ajax/epc_cascade.php?' + params.toString())
        .then(r => r.json())
        .then(j => {
          const items = (j && j.items) || [];
          target.innerHTML = '<option value="">—</option>';
          items.forEach(it => {
            const o = document.createElement('option');
            o.value = it.id;
            o.textContent = it.name;
            target.appendChild(o);
          });
          target.disabled = items.length === 0;
        })
        .catch(() => {
          target.innerHTML = '<option value="">(error)</option>';
          target.disabled = true;
        });
    }
    // parent_level value used when each dropdown above changes:
    // sel idx 0 (cat) changed     -> fetch children of category   -> parent_level=category
    // sel idx 1 (subcat) changed  -> fetch children of subcat     -> parent_level=subcategory
    // ...
    const parentLevels = ['category','subcategory','type','subsystem','component'];
    sels.slice(0, -1).forEach((sel, idx) => {
      sel.addEventListener('change', () => {
        reset(idx + 1);
        const pid = parseInt(sel.value || '0', 10);
        if (pid > 0) {
          fetchChildren(parentLevels[idx], pid, sels[idx + 1]);
        }
      });
    });
    sels[5].addEventListener('change', () => {
      addBtn.disabled = !sels[5].value;
    });
  }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

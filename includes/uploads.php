<?php
/**
 * Stage 3b — File upload helper.
 *
 * Used by vehicle_edit.php (and Stage 4+ parts pages later) to save
 * scans of legal papers and photos under /uploads/vehicles/<id>/.
 *
 * Rules enforced here:
 *   * Whitelisted extensions only.
 *   * Hard size cap (default 5 MB).
 *   * Random suffix in filename so users can re-upload "logbook.pdf"
 *     a hundred times without collisions.
 *   * Final path stored in DB is RELATIVE to the project root, e.g.
 *     "uploads/vehicles/42/logbook-1635-abcd.pdf".
 *
 * Throws RuntimeException with a friendly message on any failure;
 * caller catches and shows the message in a flash alert.
 */

if (!defined('APP_ROOT')) {
    require_once __DIR__ . '/../config/config.php';
}

const UPLOAD_MAX_BYTES = 5 * 1024 * 1024; // 5 MB

// What's allowed for which "kind" of upload.
const UPLOAD_DOC_EXTS   = ['pdf', 'jpg', 'jpeg', 'png'];
const UPLOAD_PHOTO_EXTS = ['jpg', 'jpeg', 'png', 'webp'];

function uploads_root_dir(): string {
    return APP_ROOT . DIRECTORY_SEPARATOR . 'uploads';
}

function vehicle_uploads_dir(int $vehicleId, ?string $sub = null): string {
    $dir = uploads_root_dir()
         . DIRECTORY_SEPARATOR . 'vehicles'
         . DIRECTORY_SEPARATOR . $vehicleId;
    if ($sub !== null) {
        $dir .= DIRECTORY_SEPARATOR . $sub;
    }
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create upload folder. Check write permissions on /uploads/.');
    }
    return $dir;
}

function uploads_relative_path(string $absolutePath): string {
    $root = APP_ROOT . DIRECTORY_SEPARATOR;
    if (str_starts_with($absolutePath, $root)) {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($absolutePath, strlen($root)));
    }
    return str_replace(DIRECTORY_SEPARATOR, '/', $absolutePath);
}

function uploads_public_url(?string $relativePath): ?string {
    if (!$relativePath) return null;
    return APP_URL . '/' . ltrim($relativePath, '/');
}

/**
 * Validate a single $_FILES entry. Returns sanitized lowercase extension
 * or throws RuntimeException with a friendly error.
 *
 * @param array $file       Single $_FILES['x'] entry
 * @param array $allowedExt Whitelist (lowercase, no dot)
 */
function validate_upload(array $file, array $allowedExt): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(_upload_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
    }
    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new RuntimeException('Upload failed: temporary file missing.');
    }
    if (($file['size'] ?? 0) > UPLOAD_MAX_BYTES) {
        $mb = round(UPLOAD_MAX_BYTES / 1024 / 1024);
        throw new RuntimeException("File is too big (max {$mb} MB).");
    }
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('File type not allowed. Accepted: ' . implode(', ', $allowedExt) . '.');
    }
    return $ext;
}

function _upload_error_message(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too big.',
        UPLOAD_ERR_PARTIAL    => 'Upload was interrupted. Try again.',
        UPLOAD_ERR_NO_FILE    => 'No file was selected.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server has no temp folder configured.',
        UPLOAD_ERR_CANT_WRITE => 'Could not write the file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
        default               => 'Upload failed.',
    };
}

/**
 * Save a legal-paper scan. Returns relative DB path.
 * $kind: 'logbook' | 'receipt' | 'id_copy'
 */
function save_uploaded_doc(array $file, int $vehicleId, string $kind): string {
    $ext = validate_upload($file, UPLOAD_DOC_EXTS);
    $dir = vehicle_uploads_dir($vehicleId, 'docs');
    $kindSlug = preg_replace('/[^a-z0-9_]/i', '_', strtolower($kind));
    $name = sprintf('%s-%d-%s.%s', $kindSlug, time(), bin2hex(random_bytes(3)), $ext);
    $abs  = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $abs)) {
        throw new RuntimeException('Could not save uploaded file.');
    }
    return uploads_relative_path($abs);
}

/**
 * Save a vehicle photo. Returns relative DB path.
 */
function save_uploaded_photo(array $file, int $vehicleId): string {
    $ext = validate_upload($file, UPLOAD_PHOTO_EXTS);
    $dir = vehicle_uploads_dir($vehicleId, 'photos');
    $name = sprintf('photo-%d-%s.%s', time(), bin2hex(random_bytes(3)), $ext);
    $abs  = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $abs)) {
        throw new RuntimeException('Could not save uploaded photo.');
    }
    return uploads_relative_path($abs);
}

// ---------------------------------------------------------------------
// Stage 3d — customer compliance docs (SA ID / CIPC + proof of address).
// Stored under /uploads/customers/<id>/docs/.
// ---------------------------------------------------------------------

function customer_uploads_dir(int $customerId, ?string $sub = null): string {
    $dir = uploads_root_dir()
         . DIRECTORY_SEPARATOR . 'customers'
         . DIRECTORY_SEPARATOR . $customerId;
    if ($sub !== null) {
        $dir .= DIRECTORY_SEPARATOR . $sub;
    }
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create customer upload folder. Check write permissions on /uploads/.');
    }
    return $dir;
}

/**
 * Save a customer compliance doc (ID copy / CIPC certificate / proof of
 * address). Returns relative DB path.
 * $kind: 'id_doc' | 'proof_of_address'
 */
function save_uploaded_customer_doc(array $file, int $customerId, string $kind): string {
    $ext = validate_upload($file, UPLOAD_DOC_EXTS);
    $dir = customer_uploads_dir($customerId, 'docs');
    $kindSlug = preg_replace('/[^a-z0-9_]/i', '_', strtolower($kind));
    $name = sprintf('%s-%d-%s.%s', $kindSlug, time(), bin2hex(random_bytes(3)), $ext);
    $abs  = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $abs)) {
        throw new RuntimeException('Could not save uploaded file.');
    }
    return uploads_relative_path($abs);
}

// ---------------------------------------------------------------------
// Stage 4 — parts uploads (photos only). Stored under /uploads/parts/<id>/.
// ---------------------------------------------------------------------

function part_uploads_dir(int $partId, ?string $sub = null): string {
    $dir = uploads_root_dir()
         . DIRECTORY_SEPARATOR . 'parts'
         . DIRECTORY_SEPARATOR . $partId;
    if ($sub !== null) {
        $dir .= DIRECTORY_SEPARATOR . $sub;
    }
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create part upload folder. Check write permissions on /uploads/.');
    }
    return $dir;
}

/**
 * Save a part photo. Returns relative DB path.
 */
function save_uploaded_part_photo(array $file, int $partId): string {
    $ext  = validate_upload($file, UPLOAD_PHOTO_EXTS);
    $dir  = part_uploads_dir($partId, 'photos');
    $name = sprintf('photo-%d-%s.%s', time(), bin2hex(random_bytes(3)), $ext);
    $abs  = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $abs)) {
        throw new RuntimeException('Could not save uploaded photo.');
    }
    return uploads_relative_path($abs);
}

/**
 * Stage 4b — third-party seller compliance (SHGA). Stored under
 * /uploads/parts/<id>/docs/
 * $kind: 'tpp_id_doc' | 'tpp_proof_of_address'
 */
function save_uploaded_part_compliance_doc(array $file, int $partId, string $kind): string {
    $ext = validate_upload($file, UPLOAD_DOC_EXTS);
    $dir = part_uploads_dir($partId, 'docs');
    $kindSlug = preg_replace('/[^a-z0-9_]/i', '_', strtolower($kind));
    $name = sprintf('%s-%d-%s.%s', $kindSlug, time(), bin2hex(random_bytes(3)), $ext);
    $abs  = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $abs)) {
        throw new RuntimeException('Could not save uploaded file.');
    }
    return uploads_relative_path($abs);
}

// ---------------------------------------------------------------------
// Stage 4c — supplier purchase (batch): one buy + docs, many parts.
// Stored under /uploads/supplier_purchases/<id>/docs/
// ---------------------------------------------------------------------

function supplier_purchase_uploads_dir(int $purchaseId, ?string $sub = null): string {
    $dir = uploads_root_dir()
         . DIRECTORY_SEPARATOR . 'supplier_purchases'
         . DIRECTORY_SEPARATOR . $purchaseId;
    if ($sub !== null) {
        $dir .= DIRECTORY_SEPARATOR . $sub;
    }
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create supplier_purchase upload folder.');
    }
    return $dir;
}

/**
 * $kind: 'tpp_id_doc' | 'tpp_proof_of_address' (DB column names unchanged)
 */
function save_uploaded_supplier_purchase_doc(array $file, int $purchaseId, string $kind): string {
    $ext = validate_upload($file, UPLOAD_DOC_EXTS);
    $dir = supplier_purchase_uploads_dir($purchaseId, 'docs');
    $kindSlug = preg_replace('/[^a-z0-9_]/i', '_', strtolower($kind));
    $name = sprintf('%s-%d-%s.%s', $kindSlug, time(), bin2hex(random_bytes(3)), $ext);
    $abs  = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $abs)) {
        throw new RuntimeException('Could not save uploaded file.');
    }
    return uploads_relative_path($abs);
}

/** @deprecated Use supplier_purchase_uploads_dir() */
function tpp_intake_uploads_dir(int $intakeId, ?string $sub = null): string {
    return supplier_purchase_uploads_dir($intakeId, $sub);
}

/** @deprecated Use save_uploaded_supplier_purchase_doc() */
function save_uploaded_tpp_intake_doc(array $file, int $intakeId, string $kind): string {
    return save_uploaded_supplier_purchase_doc($file, $intakeId, $kind);
}

/**
 * Quietly delete a file referenced by its DB-relative path.
 * Used when an old scan is being replaced or a photo is removed.
 */
function delete_uploaded_file(?string $relativePath): void {
    if (!$relativePath) return;
    $rel = ltrim(str_replace(['..', '\\'], '', $relativePath), '/');
    if (!str_starts_with($rel, 'uploads/')) return;
    $abs = APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (is_file($abs)) {
        @unlink($abs);
    }
}

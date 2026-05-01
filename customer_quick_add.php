<?php
/**
 * Stage 6 — Quick add customer from POS (minimal fields), then return to draft invoice.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$pageTitle = 'Quick add customer';
$canEdit   = user_has_role('owner', 'admin', 'manager', 'staff');

function customers_has_account_columns(PDO $pdo): bool {
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE table_schema = DATABASE() AND table_name = 'customers'
           AND column_name = 'account_customer'"
    )->fetchColumn() > 0;
}

$accountCols = customers_has_account_columns($pdo);
$retInv      = (int) ($_GET['return_invoice'] ?? $_POST['return_invoice'] ?? 0);
$flash       = ['type' => null, 'msg' => null];

if (!$canEdit) {
    http_response_code(403);
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-danger">You cannot add customers.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

function invoice_is_draft(PDO $pdo, int $id): bool {
    if ($id <= 0) {
        return false;
    }
    $st = $pdo->prepare(
        "SELECT status FROM sales_invoices WHERE id = ? AND is_active = 1"
    );
    $st->execute([$id]);
    return $st->fetchColumn() === 'draft';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = ['type' => 'danger', 'msg' => 'Security token invalid. Please try again.'];
    } elseif ($retInv <= 0 || !invoice_is_draft($pdo, $retInv)) {
        $flash = ['type' => 'danger', 'msg' => 'Invalid or non-draft invoice. Open New sale first.'];
    } else {
        $type = ($_POST['type'] ?? 'individual') === 'business' ? 'business' : 'individual';
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $flash = ['type' => 'danger', 'msg' => 'Name is required.'];
        } else {
            $uid   = (int) ($_SESSION['user_id'] ?? 0);
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $ba    = trim((string) ($_POST['billing_address'] ?? ''));
            $notes = trim((string) ($_POST['notes'] ?? ''));
            $ac    = $accountCols && !empty($_POST['account_customer']) ? 1 : 0;
            $limRaw = trim((string) ($_POST['credit_limit_zar'] ?? ''));
            $lim    = null;
            if ($accountCols && $limRaw !== '' && is_numeric($limRaw)) {
                $lim = round((float) $limRaw, 2);
                if ($lim < 0) {
                    $lim = null;
                }
            }
            try {
                if ($accountCols) {
                    $ins = $pdo->prepare(
                        'INSERT INTO customers
                         (type, name, contact_person, phone, email,
                          billing_address, delivery_address, vat_number,
                          sa_id_number, company_reg_number, has_proof_of_address,
                          notes, account_customer, credit_limit_zar, is_active, created_by)
                         VALUES
                         (?, ?, NULL, ?, ?, ?, NULL, NULL, NULL, NULL, 0, ?, ?, ?, 1, ?)'
                    );
                    $ins->execute([
                        $type,
                        $name,
                        $phone !== '' ? $phone : null,
                        $email !== '' ? $email : null,
                        $ba !== '' ? $ba : null,
                        $notes !== '' ? $notes : null,
                        $ac,
                        $lim,
                        $uid > 0 ? $uid : null,
                    ]);
                } else {
                    $ins = $pdo->prepare(
                        'INSERT INTO customers
                         (type, name, contact_person, phone, email,
                          billing_address, delivery_address, vat_number,
                          sa_id_number, company_reg_number, has_proof_of_address,
                          notes, is_active, created_by)
                         VALUES
                         (?, ?, NULL, ?, ?, ?, NULL, NULL, NULL, NULL, 0, ?, 1, ?)'
                    );
                    $ins->execute([
                        $type,
                        $name,
                        $phone !== '' ? $phone : null,
                        $email !== '' ? $email : null,
                        $ba !== '' ? $ba : null,
                        $notes !== '' ? $notes : null,
                        $uid > 0 ? $uid : null,
                    ]);
                }
                $newId = (int) $pdo->lastInsertId();
                header('Location: ' . APP_URL . '/invoice_edit.php?id=' . $retInv . '&apply_customer=' . $newId);
                exit;
            } catch (Throwable $e) {
                $flash = ['type' => 'danger', 'msg' => APP_DEBUG ? $e->getMessage() : 'Could not save customer.'];
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
  .aw-qac-head { background:#0a0a0a !important; color:#fff !important; border-bottom:3px solid #c8102e !important; }
  .aw-qac-head .text-muted { color:#b0b0b0 !important; }
</style>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <?php if (!$accountCols): ?>
      <div class="alert alert-warning small">
        Run <code>sql/06a_customer_account.sql</code> in phpMyAdmin to enable <strong>Account customer</strong>
        and credit limit on this form. You can still add a basic customer without it.
      </div>
    <?php endif; ?>

    <?php if ($flash['msg']): ?>
      <div class="alert alert-<?= e($flash['type'] ?? 'info') ?> py-2"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <?php if ($retInv <= 0 || !invoice_is_draft($pdo, $retInv)): ?>
      <div class="alert alert-danger">
        Open a <strong>draft</strong> invoice first: <strong>POS → New sale</strong>, then use
        <strong>New customer (quick)</strong> from that page.
      </div>
      <a class="btn btn-outline-dark" href="<?= e(APP_URL) ?>/invoice_edit.php?new=1">Start new sale</a>
    <?php else: ?>
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header aw-qac-head">
          <strong><i class="bi bi-person-plus-fill"></i> Quick add customer</strong>
          <div class="small text-muted">Returns to draft invoice #<?= (int) $retInv ?> with this buyer selected.</div>
        </div>
        <div class="card-body">
          <p class="small text-muted">
            For full details, compliance scans (SHGA), and edits later use
            <a href="<?= e(APP_URL) ?>/customers_admin.php">Master data → Customers</a>.
          </p>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="return_invoice" value="<?= (int) $retInv ?>">
            <div class="row g-2">
              <div class="col-md-4">
                <label class="form-label small">Type</label>
                <select name="type" class="form-select form-select-sm">
                  <option value="individual">Individual</option>
                  <option value="business">Business</option>
                </select>
              </div>
              <div class="col-md-8">
                <label class="form-label small">Name *</label>
                <input class="form-control form-control-sm" name="name" required
                       value="<?= e(trim((string) ($_POST['name'] ?? ''))) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small">Phone</label>
                <input class="form-control form-control-sm" name="phone"
                       value="<?= e(trim((string) ($_POST['phone'] ?? ''))) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small">Email</label>
                <input class="form-control form-control-sm" type="email" name="email"
                       value="<?= e(trim((string) ($_POST['email'] ?? ''))) ?>">
              </div>
              <div class="col-12">
                <label class="form-label small">Billing address</label>
                <textarea class="form-control form-control-sm" name="billing_address" rows="2"><?= e(trim((string) ($_POST['billing_address'] ?? ''))) ?></textarea>
              </div>
              <div class="col-12">
                <label class="form-label small">Notes</label>
                <input class="form-control form-control-sm" name="notes"
                       value="<?= e(trim((string) ($_POST['notes'] ?? ''))) ?>">
              </div>
              <?php if ($accountCols): ?>
                <div class="col-12 border-top pt-2 mt-1">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="account_customer" value="1"
                           id="qa-ac" <?= !empty($_POST['account_customer']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="qa-ac">
                      <strong>Account customer</strong> — may use <strong>due date</strong> on invoices (buy now, pay later).
                    </label>
                  </div>
                  <div class="small text-muted mt-1 mb-2">
                    If you set a due date on the invoice, finalizing requires this flag on the customer record.
                  </div>
                  <label class="form-label small">Credit limit (ZAR, optional)</label>
                  <input class="form-control form-control-sm" name="credit_limit_zar"
                         placeholder="e.g. 50000"
                         value="<?= e(trim((string) ($_POST['credit_limit_zar'] ?? ''))) ?>">
                </div>
              <?php endif; ?>
              <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                <button type="submit" class="btn btn-danger"><i class="bi bi-check-lg"></i> Save &amp; return to invoice</button>
                <a class="btn btn-outline-dark" href="<?= e(APP_URL) ?>/invoice_edit.php?id=<?= (int) $retInv ?>">Back to invoice</a>
              </div>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

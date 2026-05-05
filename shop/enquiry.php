<?php
/**
 * Stage 6e — Guest message / enquiry (any part type; staff replies offline).
 */
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/_layout.php';

$base = rtrim((string) APP_URL, '/');
$shopPageTitle = 'Send a message';

$partPrefillId   = max(0, (int) ($_GET['part_id'] ?? 0));
$skuRef          = trim((string) ($_GET['sku'] ?? ''));
$nameHint        = trim((string) ($_GET['name_hint'] ?? ''));
$partRow         = null;

if ($partPrefillId > 0 && shop_tables_ready($pdo)) {
    $pst = $pdo->prepare('SELECT id, sku, name FROM parts WHERE id = ? AND is_active = 1');
    $pst->execute([$partPrefillId]);
    $partRow = $pst->fetch(PDO::FETCH_ASSOC) ?: null;
}

$flash = ['type' => null, 'msg' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!shop_guest_enquiries_ready($pdo)) {
        $flash = ['type' => 'danger', 'msg' => 'Message box is not set up yet. Ask staff to run sql/06e_shop_guest_enquiries.sql in phpMyAdmin.'];
    } elseif (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = ['type' => 'danger', 'msg' => 'Session expired. Try again.'];
    } else {
        $visitor = trim((string) ($_POST['visitor_name'] ?? ''));
        $phone   = trim((string) ($_POST['phone'] ?? ''));
        $email   = trim((string) ($_POST['email'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        $pidPost = max(0, (int) ($_POST['part_id'] ?? 0));

        if ($visitor === '' || $phone === '' || $message === '' || strlen($message) < 8) {
            $flash = ['type' => 'danger', 'msg' => 'Please enter name, phone, and a message (at least a few words).'];
        } else {
            $skuSnap = '';
            $nameSnap = null;
            $partIdInsert = null;
            if ($pidPost > 0) {
                $chk = $pdo->prepare('SELECT id, sku, name FROM parts WHERE id = ? AND is_active = 1');
                $chk->execute([$pidPost]);
                $pr = $chk->fetch(PDO::FETCH_ASSOC);
                if ($pr) {
                    $partIdInsert = (int) $pr['id'];
                    $skuSnap       = (string) $pr['sku'];
                    $nameSnap      = (string) $pr['name'];
                }
            }
            try {
                $ins = $pdo->prepare(
                    'INSERT INTO shop_guest_enquiries
                     (visitor_name, phone, email, message, part_id, sku_ref, part_name_hint)
                     VALUES (?,?,?,?,?,?,?)'
                );
                $ins->execute([
                    $visitor,
                    $phone,
                    $email !== '' ? $email : null,
                    $message,
                    $partIdInsert,
                    $skuSnap !== '' ? $skuSnap : null,
                    $nameSnap,
                ]);
                $flash = ['type' => 'success', 'msg' => 'Thanks — we received your message and will contact you.'];
                $_POST = [];
                $partPrefillId = 0;
                $partRow = null;
            } catch (Throwable $e) {
                $flash = ['type' => 'danger', 'msg' => APP_DEBUG ? $e->getMessage() : 'Could not send. Try again later.'];
            }
        }
    }
}

shop_layout_head($shopPageTitle);
shop_layout_nav($base, shop_cart_count_items());
?>
<div class="container py-4 col-lg-7">
  <h1 class="h3 mb-2">Message <?= e(APP_NAME) ?></h1>
  <p class="text-muted small">Ask about <strong>any</strong> part (third-party, stripped, used). <strong>Add to cart</strong> applies to <strong>OEM new</strong> or <strong>Replacement</strong> parts graded <strong>New</strong>, <strong>Good</strong> or <strong>Fair</strong>.</p>

  <?php if (!shop_guest_enquiries_ready($pdo)): ?>
    <div class="alert alert-warning">Staff must run <code>sql/06e_shop_guest_enquiries.sql</code> in phpMyAdmin before this form works.</div>
  <?php endif; ?>

  <?php if (!empty($flash['msg'])): ?>
    <div class="alert alert-<?= e($flash['type'] ?? 'info') ?>"><?= e((string) $flash['msg']) ?></div>
  <?php endif; ?>

  <form method="post" class="card shadow-sm">
    <div class="card-body">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="part_id" value="<?= $partRow ? (int) $partRow['id'] : (int) $partPrefillId ?>">

      <?php if ($partRow): ?>
        <div class="alert alert-light border mb-3">
          <strong>Regarding part:</strong> <?= e((string) $partRow['name']) ?>
        </div>
      <?php elseif ($skuRef !== '' || $nameHint !== ''): ?>
        <div class="alert alert-light border mb-3 small">
          <?php if ($nameHint !== ''): ?><strong>Part:</strong> <?= e($nameHint) ?><?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label">Your name *</label>
        <input type="text" name="visitor_name" class="form-control" required value="<?= e((string) ($_POST['visitor_name'] ?? '')) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Phone *</label>
        <input type="text" name="phone" class="form-control" required value="<?= e((string) ($_POST['phone'] ?? '')) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= e((string) ($_POST['email'] ?? '')) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Message *</label>
        <textarea name="message" class="form-control" rows="5" required placeholder="What would you like to know?"><?php
          $msgPost = (string) ($_POST['message'] ?? '');
          if ($msgPost !== '') {
              echo e($msgPost);
          } elseif ($nameHint !== '') {
              echo e('I am interested in parts from: ' . $nameHint);
          }
        ?></textarea>
      </div>
      <button type="submit" class="btn btn-danger btn-lg"><i class="bi bi-send"></i> Send message</button>
      <a class="btn btn-link" href="<?= e($base) ?>/shop/">Back to browse</a>
    </div>
  </form>
</div>
<?php shop_layout_foot();

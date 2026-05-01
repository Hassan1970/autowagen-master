<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/_layout.php';

$base = rtrim((string) APP_URL, '/');
$shopPageTitle = 'Checkout';

if (!shop_tables_ready($pdo)) {
    header('Location: ' . $base . '/shop/');
    exit;
}

$flash = ['type' => null, 'msg' => null];
$cart  = shop_cart_get();
$lines = [];

foreach ($cart as $pid => $entry) {
    $pid = (int) $pid;
    $qty = max(1, (int) ($entry['qty'] ?? 1));
    if ($pid <= 0) {
        continue;
    }
    $lines[] = ['part_id' => $pid, 'qty' => $qty];
}

if (!$lines) {
    header('Location: ' . $base . '/shop/cart.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = ['type' => 'danger', 'msg' => 'Session expired. Go back and try again.'];
    } else {
        try {
            $oid = shop_place_order($pdo, $lines, [
                'customer_name'    => (string) ($_POST['customer_name'] ?? ''),
                'email'            => (string) ($_POST['email'] ?? ''),
                'phone'            => (string) ($_POST['phone'] ?? ''),
                'shipping_address' => (string) ($_POST['shipping_address'] ?? ''),
                'notes'            => (string) ($_POST['notes'] ?? ''),
            ]);
            shop_cart_set([]);
            header('Location: ' . $base . '/shop/thanks.php?id=' . $oid);
            exit;
        } catch (Throwable $e) {
            $flash = ['type' => 'danger', 'msg' => APP_DEBUG ? $e->getMessage() : 'Could not complete order. Some items may have sold.'];
        }
    }
}

shop_layout_head($shopPageTitle);
shop_layout_nav($base, shop_cart_count_items());
?>
<div class="container py-4 col-lg-8">
  <h1 class="h3 mb-3">Checkout</h1>
  <p class="text-muted small">Payment is <strong>not</strong> taken on this page yet. Stock is reduced when you place the order; our team will contact you for payment and collection (cash, EFT, or card in store).</p>
  <?php if (!empty($flash['msg'])): ?>
    <div class="alert alert-<?= e($flash['type'] ?? 'danger') ?>"><?= e((string) $flash['msg']) ?></div>
  <?php endif; ?>

  <form method="post" class="card shadow-sm">
    <div class="card-body">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="mb-3">
        <label class="form-label">Your name *</label>
        <input type="text" name="customer_name" class="form-control" required value="<?= e((string) ($_POST['customer_name'] ?? '')) ?>">
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
        <label class="form-label">Delivery / collection address</label>
        <textarea name="shipping_address" class="form-control" rows="3"><?= e((string) ($_POST['shipping_address'] ?? '')) ?></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2"><?= e((string) ($_POST['notes'] ?? '')) ?></textarea>
      </div>
      <button type="submit" class="btn btn-danger btn-lg w-100"><i class="bi bi-check2-circle"></i> Place order</button>
    </div>
  </form>
  <p class="mt-3"><a href="<?= e($base) ?>/shop/cart.php">← Back to cart</a></p>
</div>
<?php shop_layout_foot();

<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/_layout.php';

$base = rtrim((string) APP_URL, '/');
$id   = max(0, (int) ($_GET['id'] ?? 0));

if (!shop_tables_ready($pdo) || $id <= 0) {
    header('Location: ' . $base . '/shop/');
    exit;
}

$st = $pdo->prepare('SELECT * FROM shop_orders WHERE id = ?');
$st->execute([$id]);
$o = $st->fetch(PDO::FETCH_ASSOC);

if (!$o) {
    header('Location: ' . $base . '/shop/');
    exit;
}

$stL = $pdo->prepare('SELECT * FROM shop_order_lines WHERE shop_order_id = ? ORDER BY id ASC');
$stL->execute([$id]);
$L = $stL->fetchAll(PDO::FETCH_ASSOC);

$shopPageTitle = 'Thank you';
shop_layout_head($shopPageTitle);
shop_layout_nav($base, shop_cart_count_items());
?>
<div class="container py-5 col-lg-7">
  <h1 class="h3 text-success"><i class="bi bi-check-circle"></i> Order received</h1>
  <p class="lead">Reference: <strong><?= e((string) $o['order_no']) ?></strong></p>
  <?php if (($o['status'] ?? '') === 'cancelled'): ?>
    <div class="alert alert-warning">This order was cancelled and stock was restored.</div>
  <?php else: ?>
    <p>Stock has been updated in our system. Total: <strong>R <?= number_format((float) $o['total_inc_vat'], 2) ?></strong> (incl. VAT).</p>
  <?php endif; ?>
  <p class="text-muted small">Keep your reference. We will contact you on <strong><?= e((string) $o['phone']) ?></strong> to arrange payment and collection.</p>

  <h2 class="h6 mt-4">Lines</h2>
  <ul class="list-group">
    <?php foreach ($L as $ln): ?>
      <li class="list-group-item d-flex justify-content-between">
        <span><?= (int) $ln['qty'] ?> × <?= e((string) $ln['name_snapshot']) ?></span>
        <span>R <?= number_format((float) $ln['line_total_inc'], 2) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>

  <a class="btn btn-danger mt-4" href="<?= e($base) ?>/shop/">Continue shopping</a>
</div>
<?php shop_layout_foot();

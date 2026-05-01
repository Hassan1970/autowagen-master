<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/_layout.php';

$base = rtrim((string) APP_URL, '/');
$id   = max(0, (int) ($_GET['id'] ?? 0));

if (!shop_tables_ready($pdo) || !shop_parts_list_online_ready($pdo)) {
    header('Location: ' . $base . '/shop/');
    exit;
}

$st = $pdo->prepare(
    'SELECT p.* FROM parts p WHERE p.id = ? AND ' . shop_catalog_base_conditions()
);
$st->execute([$id]);
$p = $st->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    http_response_code(404);
    $shopPageTitle = 'Part not found';
    shop_layout_head($shopPageTitle);
    shop_layout_nav($base, shop_cart_count_items());
    echo '<div class="container py-5"><div class="alert alert-danger">This part is not available online.</div>';
    echo '<a href="' . e($base) . '/shop/" class="btn btn-outline-danger">Back to browse</a></div>';
    shop_layout_foot();
    exit;
}

$p = array_change_key_case($p, CASE_LOWER);
$purchasable = shop_part_purchasable_online($p);
$flash = ['type' => null, 'msg' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$purchasable) {
        $flash = ['type' => 'danger', 'msg' => 'This part is enquiry-only online. Use Message / enquiry to contact us.'];
    } elseif (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = ['type' => 'danger', 'msg' => 'Session expired. Try again.'];
    } else {
        $qty = max(1, (int) ($_POST['qty'] ?? 1));
        if ($qty > (int) $p['qty_on_hand']) {
            $flash = ['type' => 'danger', 'msg' => 'Not enough stock.'];
        } else {
            $cart = shop_cart_get();
            $cur  = max(0, (int) ($cart[$id]['qty'] ?? 0));
            $new  = min((int) $p['qty_on_hand'], $cur + $qty);
            $cart[$id] = ['qty' => $new];
            shop_cart_set($cart);
            $flash = ['type' => 'success', 'msg' => 'Added to cart.'];
        }
    }
}

$phSt = $pdo->prepare(
    'SELECT id, path, caption FROM part_photos WHERE part_id = ? ORDER BY sort_order ASC, id ASC'
);
$phSt->execute([$id]);
$photos = $phSt->fetchAll(PDO::FETCH_ASSOC);

$unit = shop_unit_price_ex_vat($p);
$vr   = round((float) ($p['vat_rate'] ?? 0), 2);
$inc  = round($unit + ($unit * $vr / 100), 2);

$srcLab = shop_filter_source_labels();
$condLab = shop_filter_condition_labels();
$sk = (string) ($p['source'] ?? '');
$ck = (string) ($p['condition_grade'] ?? '');
$srcTxt = $srcLab[$sk] ?? $sk;
$condTxt = $condLab[$ck] ?? $ck;

$enqHref = $base . '/shop/enquiry.php?part_id=' . $id
    . '&name_hint=' . rawurlencode((string) $p['name']);

$shopPageTitle = (string) $p['name'];
shop_layout_head($shopPageTitle);
shop_layout_nav($base, shop_cart_count_items());
?>
<div class="container py-4">
  <?php if (!empty($flash['msg'])): ?>
    <div class="alert alert-<?= e($flash['type'] ?? 'info') ?>"><?= e((string) $flash['msg']) ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-md-6">
      <?php if ($photos): ?>
        <div id="carouselP" class="carousel slide card">
          <div class="carousel-inner">
            <?php foreach ($photos as $i => $ph): ?>
              <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                <img src="<?= e($base . '/' . $ph['path']) ?>" class="d-block w-100" alt="" style="max-height:360px;object-fit:contain;background:#eee;">
              </div>
            <?php endforeach; ?>
          </div>
          <button class="carousel-control-prev" type="button" data-bs-target="#carouselP" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
          <button class="carousel-control-next" type="button" data-bs-target="#carouselP" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
        </div>
      <?php else: ?>
        <div class="bg-light rounded p-5 text-center text-muted">No photos yet</div>
      <?php endif; ?>
    </div>
    <div class="col-md-6">
      <p class="text-muted small mb-1"><?= e($condTxt) ?> · <?= e($srcTxt) ?></p>
      <h1 class="h3"><?= e((string) $p['name']) ?></h1>
      <p class="price-tag h4">R <?= number_format($inc, 2) ?> <span class="text-muted fs-6 fw-normal">incl. VAT</span></p>
      <p class="small">In stock: <strong><?= (int) $p['qty_on_hand'] ?></strong>
        <?php if (!empty($p['yard_location'])): ?>
          · Yard <?= e((string) $p['yard_location']) ?>
        <?php endif; ?>
      </p>

      <?php if ($purchasable): ?>
      <form method="post" class="mt-3">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="row g-2 align-items-end">
          <div class="col-auto">
            <label class="form-label small">Qty</label>
            <input type="number" name="qty" class="form-control" value="1" min="1" max="<?= (int) $p['qty_on_hand'] ?>">
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-danger"><i class="bi bi-cart-plus"></i> Add to cart</button>
          </div>
        </div>
      </form>
      <?php else: ?>
      <div class="alert alert-warning border-warning py-3 mt-3">
        <strong>Enquiry only.</strong> Third-party, stripped-yard, non–New OEM-new, or Scrap-grade lines cannot use online checkout —
        send a message and staff will reply (price, availability, Second-Hand Goods checks).
      </div>
      <p class="mb-2"><a class="btn btn-danger" href="<?= e($enqHref) ?>"><i class="bi bi-chat-dots"></i> Send message about this part</a></p>
      <?php endif; ?>

      <p class="small text-muted mt-3">Online checkout: <strong>OEM new</strong> or <strong>Replacement</strong> parts in <strong>New</strong>, <strong>Good</strong> or <strong>Fair</strong> condition. Other listings are <strong>Enquiry</strong> only — staff replies after a message.
        Other listings are <strong>enquiry only</strong> — we’ll price, confirm stock, and explain collection / ID rules before you pay.</p>
      <?php if ($purchasable): ?>
      <p class="small mb-0"><a class="btn btn-outline-dark btn-sm" href="<?= e($enqHref) ?>"><i class="bi bi-chat-left-text"></i> Message us about this part</a></p>
      <?php endif; ?>
      <div class="mt-2"><a href="<?= e($base) ?>/shop/" class="btn btn-link ps-0">← Browse more</a></div>
    </div>
  </div>
</div>
<?php shop_layout_foot();

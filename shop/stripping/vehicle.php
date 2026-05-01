<?php
/**
 * Single stripping vehicle — full photo gallery (public).
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__) . '/_init.php';
require_once dirname(__DIR__) . '/_layout.php';
require_once dirname(__DIR__, 2) . '/includes/uploads.php';

$base = rtrim((string) APP_URL, '/');
$id   = max(0, (int) ($_GET['id'] ?? 0));

$STATUS_LABEL = [
    'intake'     => 'Intake',
    'stripping'  => 'Being stripped',
    'stripped'   => 'Stripped',
    'shell_sold' => 'Shell sold',
    'scrapped'   => 'Scrapped',
];

if ($id < 1) {
    http_response_code(404);
    $shopPageTitle = 'Not found';
    shop_layout_head($shopPageTitle);
    shop_layout_nav($base, shop_cart_count_items());
    echo '<div class="container py-5"><p class="alert alert-danger">Vehicle not found.</p>';
    echo '<p><a href="' . e($base) . '/shop/stripping/">Back to stripping stock</a></p></div>';
    shop_layout_foot();
    exit;
}

$st = $pdo->prepare(
    'SELECT id, stock_code, make, model, year, vin, plate, colour, mileage, engine_code,
            status, yard_location, notes
     FROM vehicles WHERE id = ? AND is_active = 1 LIMIT 1'
);
$st->execute([$id]);
$veh = $st->fetch(PDO::FETCH_ASSOC);

if (!$veh) {
    http_response_code(404);
    $shopPageTitle = 'Not found';
    shop_layout_head($shopPageTitle);
    shop_layout_nav($base, shop_cart_count_items());
    echo '<div class="container py-5"><p class="alert alert-danger">Vehicle not found.</p>';
    echo '<p><a href="' . e($base) . '/shop/stripping/">Back to stripping stock</a></p></div>';
    shop_layout_foot();
    exit;
}

$ph = $pdo->prepare(
    'SELECT id, file_path, caption FROM vehicle_photos WHERE vehicle_id = ? ORDER BY sort_order ASC, id ASC'
);
$ph->execute([$id]);
$photos = $ph->fetchAll(PDO::FETCH_ASSOC);

$titleBits = array_filter([(string) ($veh['year'] ?? ''), trim((string) ($veh['make'] ?? '')), trim((string) ($veh['model'] ?? ''))]);
$descLine  = trim(implode(' ', $titleBits));
$stockDisp = trim((string) ($veh['stock_code'] ?? ''));
if ($stockDisp === '') {
    $stockDisp = '#' . $id;
}

$shopPageTitle = $stockDisp . ' · ' . ($descLine !== '' ? $descLine : 'Vehicle');

$enquiryHint = trim($stockDisp . ' — ' . ($descLine !== '' ? $descLine : 'stripping vehicle'));
$enquiryUrl  = $base . '/shop/enquiry.php?' . http_build_query(['name_hint' => $enquiryHint]);

$photoUrls = [];
foreach ($photos as $row) {
    $u = uploads_public_url((string) ($row['file_path'] ?? ''));
    if ($u) {
        $photoUrls[] = ['url' => $u, 'caption' => (string) ($row['caption'] ?? '')];
    }
}

/** Stripped parts linked to this vehicle (same yard shell). */
$vehicleParts = [];
try {
    $hasList = function_exists('shop_parts_list_online_ready') && shop_parts_list_online_ready($pdo);
    $cols    = $hasList
        ? 'id, sku, name, condition_grade, status, asking_price, source, list_online, qty_on_hand'
        : 'id, sku, name, condition_grade, status, asking_price, source, qty_on_hand';
    $pst = $pdo->prepare(
        "SELECT {$cols} FROM parts WHERE vehicle_id = ? AND is_active = 1 AND source = 'stripped' ORDER BY sku ASC LIMIT 100"
    );
    $pst->execute([$id]);
    $vehicleParts = $pst->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $vehicleParts = [];
}

shop_layout_head($shopPageTitle);
shop_layout_nav($base, shop_cart_count_items());
?>
<div class="container py-4">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= e($base) ?>/shop/stripping/">Stripping stock</a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= e($stockDisp) ?></li>
    </ol>
  </nav>

  <div class="row g-4">
    <div class="col-lg-7">
      <?php if ($photoUrls): ?>
        <div class="ratio ratio-4x3 bg-light border rounded overflow-hidden mb-2">
          <img id="stripMainImg" src="<?= e($photoUrls[0]['url']) ?>" class="object-fit-cover w-100 h-100" alt="">
        </div>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($photoUrls as $idx => $p): ?>
            <button type="button" class="btn p-0 border <?= $idx === 0 ? 'border-danger border-2' : '' ?> strip-thumb"
                    data-src="<?= e($p['url']) ?>" style="width:72px;height:72px;">
              <img src="<?= e($p['url']) ?>" class="w-100 h-100 object-fit-cover" alt="">
            </button>
          <?php endforeach; ?>
        </div>
        <script>
        document.querySelectorAll('.strip-thumb').forEach(function(btn){
          btn.addEventListener('click', function(){
            var src = btn.getAttribute('data-src');
            var main = document.getElementById('stripMainImg');
            if (main && src) main.src = src;
            document.querySelectorAll('.strip-thumb').forEach(function(b){ b.classList.remove('border-danger','border-2'); });
            btn.classList.add('border-danger','border-2');
          });
        });
        </script>
      <?php else: ?>
        <div class="ratio ratio-4x3 bg-secondary bg-opacity-10 border rounded d-flex align-items-center justify-content-center">
          <div class="text-center text-muted"><i class="bi bi-camera-video-off display-4"></i><p class="mb-0">No photos uploaded yet.</p></div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-lg-5">
      <h1 class="h4"><?= e($descLine !== '' ? $descLine : 'Vehicle #' . $id) ?></h1>
      <p class="font-monospace text-muted mb-2">Stock: <?= e($stockDisp) ?></p>
      <table class="table table-sm table-bordered bg-white">
        <tbody>
          <tr><th style="width:38%">Status</th><td><?= e($STATUS_LABEL[$veh['status'] ?? ''] ?? ($veh['status'] ?? '—')) ?></td></tr>
          <?php if (!empty($veh['colour'])): ?>
            <tr><th>Colour</th><td><?= e((string) $veh['colour']) ?></td></tr>
          <?php endif; ?>
          <?php if ($veh['mileage'] !== null && $veh['mileage'] !== ''): ?>
            <tr><th>Mileage</th><td><?= e((string) $veh['mileage']) ?> km</td></tr>
          <?php endif; ?>
          <?php if (!empty($veh['engine_code'])): ?>
            <tr><th>Engine code</th><td><?= e((string) $veh['engine_code']) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($veh['plate'])): ?>
            <tr><th>Plate</th><td><?= e((string) $veh['plate']) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($veh['vin'])): ?>
            <tr><th>VIN</th><td class="small"><?= e((string) $veh['vin']) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($veh['yard_location'])): ?>
            <tr><th>Yard</th><td><?= e((string) $veh['yard_location']) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <a href="<?= e($enquiryUrl) ?>" class="btn btn-danger btn-lg w-100 mb-2">
        <i class="bi bi-chat-dots"></i> Enquire about parts
      </a>
      <p class="small text-muted">Opens the message form with this vehicle pre-filled in the subject line.</p>

      <?php if (!empty(trim((string) ($veh['notes'] ?? '')))): ?>
        <div class="card mt-3">
          <div class="card-header bg-light small fw-bold">Notes</div>
          <div class="card-body small"><?= nl2br(e(trim((string) $veh['notes']))) ?></div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($vehicleParts): ?>
    <div class="row mt-4">
      <div class="col-12">
        <div class="card border-dark">
          <div class="card-header bg-dark text-white small fw-bold">Parts from this vehicle</div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light"><tr><th>SKU</th><th>Product</th><th>Cond.</th><th>Status</th><th class="text-end">Asking ex VAT</th><th></th></tr></thead>
                <tbody>
                  <?php foreach ($vehicleParts as $pr):
                      $pr = array_change_key_case($pr, CASE_LOWER);
                      $pid = (int) ($pr['id'] ?? 0);
                      $listed = !$hasList ? false : ((int) ($pr['list_online'] ?? 0) === 1);
                      $qtyOk  = (float) ($pr['qty_on_hand'] ?? 0) > 0;
                      $avail  = (($pr['status'] ?? '') === 'available');
                      $showShop = shop_tables_ready($pdo) && shop_parts_list_online_ready($pdo) && $listed && $avail && $qtyOk;
                      ?>
                    <tr>
                      <td class="font-monospace small"><?= e((string) ($pr['sku'] ?? '')) ?></td>
                      <td><?= e((string) ($pr['name'] ?? '')) ?></td>
                      <td><?= e((string) ($pr['condition_grade'] ?? '')) ?></td>
                      <td><?= e((string) ($pr['status'] ?? '')) ?></td>
                      <td class="text-end"><?php $ap = (float) ($pr['asking_price'] ?? 0); ?>R <?= number_format($ap, 2, '.', ',') ?></td>
                      <td class="text-end">
                        <?php if ($showShop): ?>
                          <a class="btn btn-sm btn-outline-danger" href="<?= e($base) ?>/shop/part.php?id=<?= $pid ?>">View online</a>
                        <?php else: ?>
                          <span class="small text-muted">Enquiry only</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="small text-muted px-3 py-2 border-top">Listed parts open the normal parts shop page; others — use <strong>Enquire about parts</strong>.</div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>
<?php
shop_layout_foot();

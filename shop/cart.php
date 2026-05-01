<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/_layout.php';

$base = rtrim((string) APP_URL, '/');
$shopPageTitle = 'Cart';

if (!shop_tables_ready($pdo)) {
    header('Location: ' . $base . '/shop/');
    exit;
}

$flash = ['type' => null, 'msg' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null)) {
    $act = (string) ($_POST['act'] ?? '');
    if ($act === 'clear') {
        shop_cart_set([]);
        $flash = ['type' => 'success', 'msg' => 'Cart cleared.'];
    } elseif ($act === 'update') {
        $cart = shop_cart_get();
        foreach ($_POST['qty'] ?? [] as $pidStr => $qtyRaw) {
            $pid = (int) $pidStr;
            $q   = max(0, (int) $qtyRaw);
            if ($pid <= 0) {
                continue;
            }
            if ($q === 0) {
                unset($cart[$pid]);
            } else {
                $cart[$pid] = ['qty' => $q];
            }
        }
        shop_cart_set($cart);
        $flash = ['type' => 'success', 'msg' => 'Cart updated.'];
    } elseif ($act === 'remove') {
        $pid = (int) ($_POST['part_id'] ?? 0);
        $cart = shop_cart_get();
        unset($cart[$pid]);
        shop_cart_set($cart);
        $flash = ['type' => 'success', 'msg' => 'Removed.'];
    }
}

$cartRaw = shop_cart_get();
$cartClean = [];
$removed = 0;
foreach ($cartRaw as $pidKey => $entry) {
    $pidKey = (int) $pidKey;
    if ($pidKey <= 0) {
        $removed++;
        continue;
    }
    $pst = $pdo->prepare('SELECT * FROM parts WHERE id = ? AND is_active = 1');
    $pst->execute([$pidKey]);
    $chk = $pst->fetch(PDO::FETCH_ASSOC);
    if (!$chk) {
        $removed++;
        continue;
    }
    $chk = array_change_key_case($chk, CASE_LOWER);
    if ((int) ($chk['list_online'] ?? 0) !== 1 || !shop_part_purchasable_online($chk)) {
        $removed++;
        continue;
    }
    $cartClean[$pidKey] = $entry;
}
if ($removed > 0) {
    shop_cart_set($cartClean);
    if (empty($flash['msg'])) {
        $flash = ['type' => 'warning', 'msg' => 'Some items were removed: cart only allows OEM new or Replacement parts in New, Good or Fair condition. Use Message for other lines.'];
    }
}

$cart = shop_cart_get();
$rows = [];
$sumInc = 0.0;

foreach ($cart as $pid => $entry) {
    $pid = (int) $pid;
    $qty = max(1, (int) ($entry['qty'] ?? 1));
    $st  = $pdo->prepare('SELECT * FROM parts WHERE id = ? AND is_active = 1');
    $st->execute([$pid]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        continue;
    }
    $p = array_change_key_case($p, CASE_LOWER);
    if ((int) ($p['list_online'] ?? 0) !== 1 || !shop_part_purchasable_online($p)) {
        continue;
    }
    $qAvail = min($qty, max(0, (int) ($p['qty_on_hand'] ?? 0)));
    if ($qAvail < 1 || ($p['status'] ?? '') !== 'available') {
        continue;
    }
    $unit = shop_unit_price_ex_vat($p);
    $vr   = round((float) ($p['vat_rate'] ?? 0), 2);
    $lineInc = round($qAvail * ($unit + ($unit * $vr / 100)), 2);
    $sumInc += $lineInc;
    $rows[] = [
        'part'     => $p,
        'qty'      => $qAvail,
        'line_inc' => $lineInc,
    ];
}

shop_layout_head($shopPageTitle);
shop_layout_nav($base, shop_cart_count_items());
$formId = 'cartQtyForm';
?>
<div class="container py-4">
  <h1 class="h3 mb-3">Shopping cart</h1>
  <?php if (!empty($flash['msg'])): ?>
    <div class="alert alert-<?= e($flash['type'] ?? 'info') ?> py-2"><?= e((string) $flash['msg']) ?></div>
  <?php endif; ?>

  <?php if (!$rows): ?>
    <p class="text-muted">Your cart is empty.</p>
    <a href="<?= e($base) ?>/shop/" class="btn btn-danger">Browse parts</a>
  <?php else: ?>
    <div class="table-responsive bg-white rounded shadow-sm">
      <table class="table align-middle mb-0">
        <thead class="table-light"><tr><th>Part</th><th class="text-end">Qty</th><th class="text-end">Line</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <?php $p = $r['part']; ?>
            <tr>
              <td>
                <strong><?= e((string) $p['name']) ?></strong>
                <div class="small text-muted">In stock: <?= (int) $p['qty_on_hand'] ?></div>
              </td>
              <td class="text-end" style="max-width:120px;">
                <input type="number" class="form-control form-control-sm text-end ms-auto" form="<?= e($formId) ?>"
                       name="qty[<?= (int) $p['id'] ?>]" value="<?= (int) $r['qty'] ?>" min="0" max="<?= (int) $p['qty_on_hand'] ?>">
              </td>
              <td class="text-end">R <?= number_format($r['line_inc'], 2) ?></td>
              <td class="text-end">
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="act" value="remove">
                  <input type="hidden" name="part_id" value="<?= (int) $p['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <form method="post" id="<?= e($formId) ?>" class="d-flex flex-wrap gap-2 align-items-center mt-3">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="act" value="update">
      <button type="submit" class="btn btn-outline-danger">Update cart</button>
      <p class="small text-muted mb-0">Set qty to <strong>0</strong> and Update to drop a line.</p>
    </form>

    <form method="post" class="mt-2">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <button type="submit" name="act" value="clear" class="btn btn-outline-secondary btn-sm">Clear all</button>
    </form>

    <p class="mt-3 h5">Estimated total (incl. VAT): <span class="price-tag">R <?= number_format($sumInc, 2) ?></span></p>
    <a class="btn btn-danger btn-lg" href="<?= e($base) ?>/shop/checkout.php">Checkout</a>
  <?php endif; ?>
</div>
<?php
shop_layout_foot();

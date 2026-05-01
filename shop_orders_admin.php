<?php
/**
 * Stage 6b — Staff: web shop orders (stock already deducted on checkout).
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/shop_helpers.php';

$pageTitle = 'Web shop orders';
$canEdit   = user_has_role('owner', 'admin', 'manager', 'staff');

if (!shop_tables_ready($pdo)) {
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-warning">Run <code>sql/06b_web_shop.sql</code> in phpMyAdmin, then refresh.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$flash = ['type' => null, 'msg' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = ['type' => 'danger', 'msg' => 'Invalid security token.'];
    } else {
        $act = (string) ($_POST['act'] ?? '');
        $oid = (int) ($_POST['order_id'] ?? 0);
        try {
            if ($act === 'cancel' && $oid > 0) {
                shop_cancel_order($pdo, $oid);
                $flash = ['type' => 'success', 'msg' => 'Order cancelled and stock restored.'];
            } else {
                throw new RuntimeException('Unknown action.');
            }
        } catch (Throwable $e) {
            $flash = ['type' => 'danger', 'msg' => APP_DEBUG ? $e->getMessage() : 'Could not update order.'];
        }
    }
}

$viewId = (int) ($_GET['id'] ?? 0);
if ($viewId > 0) {
    $st = $pdo->prepare('SELECT * FROM shop_orders WHERE id = ?');
    $st->execute([$viewId]);
    $one = $st->fetch(PDO::FETCH_ASSOC);
    if (!$one) {
        http_response_code(404);
        $one = null;
    } else {
        $stL = $pdo->prepare(
            'SELECT sol.*, p.sku AS part_sku_now, p.status AS part_status_now, p.qty_on_hand
             FROM shop_order_lines sol
             LEFT JOIN parts p ON p.id = sol.part_id
             WHERE sol.shop_order_id = ?
             ORDER BY sol.id ASC'
        );
        $stL->execute([$viewId]);
        $lines = $stL->fetchAll(PDO::FETCH_ASSOC);
    }
}

$page     = max(1, (int) ($_GET['page'] ?? 1));
$statusF  = (string) ($_GET['status'] ?? '');
$perPage  = 50;
$offset   = ($page - 1) * $perPage;
$where    = [];
$params   = [];
if ($statusF === 'confirmed' || $statusF === 'cancelled') {
    $where[] = 'o.status = :st';
    $params[':st'] = $statusF;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM shop_orders o {$whereSql}");
$cnt->execute($params);
$total = (int) $cnt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));

$lst = $pdo->prepare(
    "SELECT o.* FROM shop_orders o {$whereSql} ORDER BY o.id DESC LIMIT {$perPage} OFFSET {$offset}"
);
$lst->execute($params);
$rows = $lst->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h1 class="h3 mb-0">Web shop orders</h1>
      <p class="text-muted small mb-0">Guest checkout from <a href="<?= e(APP_URL) ?>/shop/" target="_blank" rel="noopener">public shop</a>. Cancelling restores stock.</p>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-danger" target="_blank" rel="noopener" href="<?= e(APP_URL) ?>/shop/"><i class="bi bi-shop"></i> Open shop</a>
    </div>
  </div>

  <?php if (!empty($flash['msg'])): ?>
    <div class="alert alert-<?= e($flash['type'] ?? 'info') ?> py-2"><?= e((string) $flash['msg']) ?></div>
  <?php endif; ?>

  <?php if (isset($one) && $one): ?>
    <p><a href="<?= e(APP_URL) ?>/shop_orders_admin.php" class="btn btn-sm btn-outline-secondary">← List</a></p>
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center gap-2">
        <strong><?= e((string) $one['order_no']) ?></strong>
        <span class="badge <?= ($one['status'] ?? '') === 'cancelled' ? 'bg-secondary' : 'bg-success' ?>">
          <?= e((string) $one['status']) ?>
        </span>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <p class="mb-1"><strong><?= e((string) $one['customer_name']) ?></strong></p>
            <p class="mb-1 small">Phone: <?= e((string) $one['phone']) ?></p>
            <?php if (($one['email'] ?? '') !== ''): ?>
              <p class="mb-1 small">Email: <?= e((string) $one['email']) ?></p>
            <?php endif; ?>
            <?php if (($one['shipping_address'] ?? '') !== ''): ?>
              <p class="mb-0 small text-muted"><?= nl2br(e((string) $one['shipping_address'])) ?></p>
            <?php endif; ?>
          </div>
          <div class="col-md-6 text-md-end">
            <p class="mb-1">Subtotal (ex VAT): R <?= number_format((float) $one['subtotal_ex_vat'], 2) ?></p>
            <p class="mb-1">VAT: R <?= number_format((float) $one['vat_total'], 2) ?></p>
            <p class="h5 mb-0">Total (incl. VAT): R <?= number_format((float) $one['total_inc_vat'], 2) ?></p>
            <p class="small text-muted mb-0 mt-2"><?= e((string) $one['created_at']) ?></p>
          </div>
        </div>
        <?php if (($one['notes'] ?? '') !== ''): ?>
          <p class="mt-3 small"><strong>Customer notes:</strong> <?= nl2br(e((string) $one['notes'])) ?></p>
        <?php endif; ?>

        <h2 class="h6 mt-4">Lines</h2>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>SKU (snapshot)</th><th>Name</th><th class="text-end">Qty</th><th class="text-end">Total</th><th class="small">Part now</th></tr></thead>
            <tbody>
              <?php foreach ($lines as $ln): ?>
                <tr>
                  <td><?= e((string) $ln['sku_snapshot']) ?></td>
                  <td><?= e((string) $ln['name_snapshot']) ?></td>
                  <td class="text-end"><?= (int) $ln['qty'] ?></td>
                  <td class="text-end">R <?= number_format((float) $ln['line_total_inc'], 2) ?></td>
                  <td class="small text-muted">
                    <?= e((string) ($ln['part_sku_now'] ?? '—')) ?>
                    · <?= e((string) ($ln['part_status_now'] ?? '')) ?>
                    · Qty <?= (int) ($ln['qty_on_hand'] ?? 0) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($canEdit && ($one['status'] ?? '') === 'confirmed'): ?>
          <form method="post" class="mt-3 border-top pt-3" onsubmit="return confirm('Cancel this order and put stock back?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="act" value="cancel">
            <input type="hidden" name="order_id" value="<?= (int) $one['id'] ?>">
            <button type="submit" class="btn btn-outline-danger">Cancel order &amp; restore stock</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php elseif (isset($one) && !$one): ?>
    <div class="alert alert-danger">Order not found.</div>
  <?php else: ?>

    <form method="get" class="row g-2 align-items-end mb-3">
      <div class="col-auto">
        <label class="form-label small mb-0">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <option value="confirmed" <?= $statusF === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
          <option value="cancelled" <?= $statusF === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-danger">Filter</button>
      </div>
    </form>

    <div class="table-responsive bg-white rounded shadow-sm">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Order</th><th>When</th><th>Customer</th><th>Phone</th><th class="text-end">Total</th><th>Status</th><th class="text-end">—</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= e((string) $r['order_no']) ?></td>
              <td class="small"><?= e((string) $r['created_at']) ?></td>
              <td><?= e((string) $r['customer_name']) ?></td>
              <td><?= e((string) $r['phone']) ?></td>
              <td class="text-end">R <?= number_format((float) $r['total_inc_vat'], 2) ?></td>
              <td><span class="badge <?= ($r['status'] ?? '') === 'cancelled' ? 'bg-secondary' : 'bg-success' ?>"><?= e((string) $r['status']) ?></span></td>
              <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?id=<?= (int) $r['id'] ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-muted py-4 text-center">No orders yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="mt-3">
        <ul class="pagination">
          <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
              <a class="page-link" href="?<?= http_build_query(array_filter(['status' => $statusF ?: null, 'page' => $i > 1 ? $i : null])) ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php';

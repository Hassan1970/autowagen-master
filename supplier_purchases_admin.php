<?php
/**
 * Stage 4c — List supplier purchases (batches: one buy, many parts).
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$rows = $pdo->query(
    "SELECT sp.id, sp.purchase_ref, sp.created_at,
            sp.supplier_id, s.name AS supplier_name,
            sp.seller_name,
            (SELECT COUNT(*) FROM parts p
              WHERE p.supplier_purchase_id = sp.id AND p.is_active = 1) AS part_count
     FROM supplier_purchases sp
     LEFT JOIN suppliers s ON s.id = sp.supplier_id
     WHERE sp.is_active = 1
     ORDER BY sp.id DESC
     LIMIT 100"
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Supplier purchases';
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-truck"></i> Supplier purchases <span class="text-muted small">(batch)</span></h1>
    <a class="btn btn-sm btn-danger" href="<?= e(APP_URL) ?>/supplier_purchase_edit.php">
      <i class="bi bi-plus-lg"></i> New purchase
    </a>
  </div>
  <p class="text-muted small mb-0">
    One purchase from a <strong>registered supplier</strong> or a <strong>private seller</strong>.
    Add many parts (OEM, replacement, or third-party for supplier buys) without re-typing the seller each time.
  </p>

  <div class="card border-0 shadow-sm mt-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Reference</th>
            <th>Supplier / seller</th>
            <th class="text-end">Parts</th>
            <th>Created</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No purchases yet. <a href="<?= e(APP_URL) ?>/supplier_purchase_edit.php">Create one</a>.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td class="font-monospace"><?= (int) $r['id'] ?></td>
              <td><?= e($r['purchase_ref'] ?: '—') ?></td>
              <td>
                <?php if (!empty($r['supplier_id'])): ?>
                  <span class="badge bg-light text-dark border"><?= e($r['supplier_name']) ?></span>
                <?php else: ?>
                  <i class="bi bi-person"></i> <?= e($r['seller_name'] ?: '—') ?>
                <?php endif; ?>
              </td>
              <td class="text-end"><?= (int) $r['part_count'] ?></td>
              <td class="small text-muted"><?= e(substr((string) $r['created_at'], 0, 16)) ?></td>
              <td>
                <a class="btn btn-sm btn-outline-dark" href="<?= e(APP_URL) ?>/supplier_purchase_edit.php?id=<?= (int) $r['id'] ?>">Open</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

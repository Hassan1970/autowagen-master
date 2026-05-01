<?php
/**
 * Stage 6e — Staff: guest messages from public shop (any part query).
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/shop_helpers.php';

$pageTitle = 'Web shop messages';
$canEdit   = user_has_role('owner', 'admin', 'manager', 'staff');

if (!shop_guest_enquiries_ready($pdo)) {
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container py-4"><div class="alert alert-warning">';
    echo 'Run <code>sql/06e_shop_guest_enquiries.sql</code> in phpMyAdmin (<code>autowagen_master</code>), then refresh.';
    echo '</div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$flash = ['type' => null, 'msg' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = ['type' => 'danger', 'msg' => 'Invalid security token.'];
    } else {
        $act = (string) ($_POST['act'] ?? '');
        $eid = (int) ($_POST['enquiry_id'] ?? 0);
        try {
            if ($act === 'mark_read' && $eid > 0) {
                $pdo->prepare('UPDATE shop_guest_enquiries SET is_read = 1 WHERE id = ?')->execute([$eid]);
                $flash = ['type' => 'success', 'msg' => 'Marked read.'];
            } elseif ($act === 'mark_unread' && $eid > 0) {
                $pdo->prepare('UPDATE shop_guest_enquiries SET is_read = 0 WHERE id = ?')->execute([$eid]);
                $flash = ['type' => 'success', 'msg' => 'Marked unread.'];
            } else {
                throw new RuntimeException('Unknown action.');
            }
        } catch (Throwable $e) {
            $flash = ['type' => 'danger', 'msg' => APP_DEBUG ? $e->getMessage() : 'Could not update.'];
        }
    }
}

$viewId = (int) ($_GET['id'] ?? 0);
$one = null;
if ($viewId > 0) {
    $st = $pdo->prepare(
        'SELECT e.*, p.sku AS part_sku_live, p.name AS part_name_live
         FROM shop_guest_enquiries e
         LEFT JOIN parts p ON p.id = e.part_id
         WHERE e.id = ?'
    );
    $st->execute([$viewId]);
    $one = $st->fetch(PDO::FETCH_ASSOC);
    if (!$one) {
        $one = false;
    }
}

$unreadOnly = (string) ($_GET['unread'] ?? '') === '1';
$page       = max(1, (int) ($_GET['page'] ?? 1));
$perPage    = 50;
$offset     = ($page - 1) * $perPage;
$whereSql   = $unreadOnly ? 'WHERE e.is_read = 0' : '';
$cnt        = $pdo->prepare("SELECT COUNT(*) FROM shop_guest_enquiries e {$whereSql}");
$cnt->execute();
$total      = (int) $cnt->fetchColumn();
$pages      = max(1, (int) ceil($total / $perPage));

$lst = $pdo->prepare(
    "SELECT e.id, e.visitor_name, e.phone, e.sku_ref, e.part_name_hint, e.is_read, e.created_at, p.sku AS part_sku
     FROM shop_guest_enquiries e
     LEFT JOIN parts p ON p.id = e.part_id
     {$whereSql}
     ORDER BY e.id DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$lst->execute();
$rows = $lst->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
      <h1 class="h3 mb-0">Web shop messages</h1>
      <p class="text-muted small mb-0">Enquiries from <a href="<?= e(APP_URL) ?>/shop/enquiry.php" target="_blank" rel="noopener">public Message</a>
        (stripped / third-party / general questions).</p>
    </div>
    <a class="btn btn-outline-danger btn-sm" target="_blank" rel="noopener" href="<?= e(APP_URL) ?>/shop/"><i class="bi bi-shop"></i> Open shop</a>
  </div>

  <?php if (!empty($flash['msg'])): ?>
    <div class="alert alert-<?= e($flash['type'] ?? 'info') ?> py-2"><?= e((string) $flash['msg']) ?></div>
  <?php endif; ?>

  <?php if ($one === false): ?>
    <div class="alert alert-danger">Message not found.</div>
    <p><a href="<?= e(APP_URL) ?>/shop_enquiries_admin.php" class="btn btn-sm btn-outline-secondary">← List</a></p>
  <?php elseif ($one): ?>
    <p><a href="<?= e(APP_URL) ?>/shop_enquiries_admin.php" class="btn btn-sm btn-outline-secondary">← List</a></p>
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><strong>#<?= (int) $one['id'] ?></strong> · <?= e((string) $one['visitor_name']) ?></span>
        <span><?php if (empty($one['is_read'])): ?>
          <span class="badge bg-danger">Unread</span>
        <?php else: ?>
          <span class="badge bg-secondary">Read</span>
        <?php endif; ?></span>
      </div>
      <div class="card-body">
        <p class="mb-1 small text-muted"><?= e((string) $one['created_at']) ?></p>
        <p class="mb-1"><strong>Phone:</strong> <?= e((string) $one['phone']) ?></p>
        <?php if (($one['email'] ?? '') !== ''): ?>
          <p class="mb-1"><strong>Email:</strong> <?= e((string) $one['email']) ?></p>
        <?php endif; ?>
        <?php if (!empty($one['part_id'])): ?>
          <p class="mb-1">
            <strong>Part:</strong>
            <?= e((string) ($one['sku_ref'] ?? $one['part_sku_live'] ?? '')) ?>
            <?php if (!empty($one['part_name_hint']) || !empty($one['part_name_live'])): ?>
              — <?= e((string) ($one['part_name_live'] ?: $one['part_name_hint'])) ?>
            <?php endif; ?>
            <a class="btn btn-outline-primary btn-sm ms-1" href="<?= e(APP_URL) ?>/part_edit.php?id=<?= (int) $one['part_id'] ?>">Edit part</a>
          </p>
        <?php elseif (($one['sku_ref'] ?? '') !== ''): ?>
          <p class="mb-1"><strong>SKU ref:</strong> <?= e((string) $one['sku_ref']) ?></p>
        <?php endif; ?>
        <hr>
        <div class="mb-0"><?= nl2br(e((string) $one['message'])) ?></div>
        <?php if ($canEdit): ?>
          <div class="mt-3 pt-3 border-top d-flex gap-2">
            <?php if (empty($one['is_read'])): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="act" value="mark_read">
                <input type="hidden" name="enquiry_id" value="<?= (int) $one['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger">Mark read</button>
              </form>
            <?php else: ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="act" value="mark_unread">
                <input type="hidden" name="enquiry_id" value="<?= (int) $one['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Mark unread</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
      <form method="get" class="row g-2 align-items-center">
        <div class="col-auto">
          <select name="unread" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All messages</option>
            <option value="1" <?= $unreadOnly ? 'selected' : '' ?>>Unread only</option>
          </select>
        </div>
      </form>
    </div>

    <div class="table-responsive bg-white rounded shadow-sm">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th><th>When</th><th>From</th><th>Phone</th><th>Part / SKU</th><th></th><th class="text-end">—</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr class="<?= empty($r['is_read']) ? 'table-warning' : '' ?>">
              <td><?= (int) $r['id'] ?></td>
              <td class="small"><?= e((string) $r['created_at']) ?></td>
              <td><?= e((string) $r['visitor_name']) ?></td>
              <td><?= e((string) $r['phone']) ?></td>
              <td class="small"><?= e((string) ($r['sku_ref'] ?? $r['part_sku'] ?? '—')) ?></td>
              <td><?php if (empty($r['is_read'])): ?><span class="badge bg-danger">Unread</span><?php endif; ?></td>
              <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?id=<?= (int) $r['id'] ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-muted py-4 text-center">No messages yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="mt-3">
        <ul class="pagination">
          <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
              <a class="page-link" href="?<?= http_build_query(array_filter(['unread' => $unreadOnly ? '1' : null, 'page' => $i > 1 ? $i : null])) ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php';

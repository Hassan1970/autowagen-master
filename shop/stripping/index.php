<?php
/**
 * Public stripping-stock catalogue — vehicles available / in progress for dismantling.
 * Separate from parts shop (`/shop/`). No login.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__) . '/_init.php';
require_once dirname(__DIR__) . '/_layout.php';
require_once dirname(__DIR__, 2) . '/includes/uploads.php';

$base = rtrim((string) APP_URL, '/');
$shopPageTitle = 'Stripping vehicles';

/** Same lifecycle labels as staff vehicle_edit (subset shown on web). */
$STRIP_STATUS_LABEL = [
    'intake'     => 'Intake',
    'stripping'  => 'Being stripped',
    'stripped'   => 'Stripped',
    'shell_sold' => 'Shell sold',
    'scrapped'   => 'Scrapped',
];

$perPage = 24;
$page    = max(1, (int) ($_GET['p'] ?? 1));
$offset  = ($page - 1) * $perPage;

$q      = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

$allowedStatus = ['', 'intake', 'stripping', 'stripped', 'shell_sold', 'scrapped'];
if (!in_array($status, $allowedStatus, true)) {
    $status = '';
}

$where  = ['v.is_active = 1'];
$params = [];

if ($status !== '') {
    $where[]          = 'v.status = ?';
    $params[]         = $status;
} else {
    // Default browse: everything except dead rows (optional — show all active)
    $where[] = '1=1';
}

if ($q !== '') {
    $where[]  = '(v.stock_code LIKE ? OR v.make LIKE ? OR v.model LIKE ? OR CONCAT_WS(" ", v.make, v.model, IFNULL(v.year,"")) LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles v WHERE {$whereSql}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$listStmt = $pdo->prepare(
    "SELECT v.id, v.stock_code, v.make, v.model, v.year, v.colour, v.status, v.yard_location,
            (SELECT vp.file_path FROM vehicle_photos vp WHERE vp.vehicle_id = v.id
             ORDER BY vp.sort_order ASC, vp.id ASC LIMIT 1) AS thumb_path
     FROM vehicles v
     WHERE {$whereSql}
     ORDER BY v.id DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$pages = (int) ceil(max(1, $total) / $perPage);

function stripping_desc_line(array $v): string {
    $bits = array_filter([(string) ($v['year'] ?? ''), trim((string) ($v['make'] ?? '')), trim((string) ($v['model'] ?? ''))]);
    return trim(implode(' ', $bits));
}

shop_layout_head($shopPageTitle);
shop_layout_nav($base, shop_cart_count_items());
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h1 class="h3 mb-0">Stripping stock</h1>
      <p class="text-muted small mb-0">Wreck / project vehicles — click a photo for the full gallery. (Not the spares catalogue — see <a href="<?= e($base) ?>/shop/">Parts shop</a>.)</p>
    </div>
  </div>

  <form class="card card-body mb-4" method="get" action="">
    <div class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label">Search stock code or vehicle</label>
        <input type="search" name="q" class="form-control" placeholder="e.g. AWG-0002 or Toyota Hilux" value="<?= e($q) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="" <?= $status === '' ? 'selected' : '' ?>>All statuses</option>
          <?php foreach ($STRIP_STATUS_LABEL as $k => $lab): ?>
            <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-primary w-100">Apply filters</button>
      </div>
    </div>
  </form>

  <?php if ($total > 0): ?>
    <p class="small text-muted">Showing <?= (int) ($offset + 1) ?>–<?= (int) ($offset + count($rows)) ?> of <?= (int) $total ?> vehicles</p>
  <?php endif; ?>

  <?php if (!$rows): ?>
    <div class="alert alert-light border">No vehicles match. Try clearing the search or pick <strong>All statuses</strong>.</div>
  <?php else: ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3">
      <?php foreach ($rows as $v):
          $vid   = (int) $v['id'];
          $desc  = stripping_desc_line($v);
          $stock = trim((string) ($v['stock_code'] ?? ''));
          if ($stock === '') {
              $stock = '#' . $vid;
          }
          $thumb = !empty($v['thumb_path']) ? uploads_public_url((string) $v['thumb_path']) : null;
          $stLab = $STRIP_STATUS_LABEL[$v['status'] ?? ''] ?? ucfirst((string) ($v['status'] ?? ''));
          $href  = $base . '/shop/stripping/vehicle.php?id=' . $vid;
          ?>
        <div class="col">
          <a href="<?= e($href) ?>" class="text-decoration-none text-dark">
            <div class="card h-100 card-product shadow-sm">
              <?php if ($thumb): ?>
                <img src="<?= e($thumb) ?>" class="card-img-top" alt="" loading="lazy">
              <?php else: ?>
                <div class="card-img-top d-flex align-items-center justify-content-center bg-secondary bg-opacity-10" style="height:180px;">
                  <i class="bi bi-image display-4 text-secondary"></i>
                </div>
              <?php endif; ?>
              <div class="card-body">
                <div class="small text-muted font-monospace">Stock: <?= e($stock) ?></div>
                <div class="fw-bold"><?= e($desc !== '' ? $desc : 'Vehicle #' . $vid) ?></div>
                <?php if (!empty($v['yard_location'])): ?>
                  <div class="small text-muted">Yard: <?= e((string) $v['yard_location']) ?></div>
                <?php endif; ?>
                <span class="badge bg-dark mt-2"><?= e($stLab) ?></span>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($pages > 1): ?>
    <nav class="mt-4" aria-label="Stripping pages">
      <ul class="pagination justify-content-center">
        <?php
        $qs = $_GET;
        for ($i = 1; $i <= $pages; $i++):
            $qs['p'] = (string) $i;
            $url = $base . '/shop/stripping/index.php?' . http_build_query($qs);
            ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= e($url) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>
<?php
shop_layout_foot();

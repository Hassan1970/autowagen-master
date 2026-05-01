<?php
/**
 * Stage 6b — Public shop catalogue (same `parts` stock as POS).
 */
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/_layout.php';

$shopPageTitle = 'Browse spares';
$base = rtrim((string) APP_URL, '/');

if (!shop_tables_ready($pdo) || !shop_parts_list_online_ready($pdo)) {
    shop_layout_head('Shop setup');
    shop_layout_nav($base, 0);
    echo '<div class="container py-5"><div class="alert alert-warning">';
    echo '<strong>Web shop tables missing.</strong> In phpMyAdmin on <code>autowagen_master</code>, run ';
    echo '<code>sql/06b_web_shop.sql</code>, then refresh.';
    echo '</div></div>';
    shop_layout_foot();
    exit;
}

$q     = trim((string) ($_GET['q'] ?? ''));
$page  = max(1, (int) ($_GET['page'] ?? 1));
$per   = 24;
$off   = ($page - 1) * $per;

$fCond = shop_catalog_normalize_filter(
    isset($_GET['cond']) && is_scalar($_GET['cond']) ? (string) $_GET['cond'] : '',
    shop_catalog_condition_whitelist()
);
$fSrc = shop_catalog_normalize_filter(
    isset($_GET['src']) && is_scalar($_GET['src']) ? (string) $_GET['src'] : '',
    shop_catalog_source_whitelist()
);

$where = [shop_catalog_base_conditions()];
$params = [];
if ($fCond !== '') {
    $where[] = 'p.condition_grade = :fcond';
    $params[':fcond'] = $fCond;
}
if ($fSrc !== '') {
    $where[] = 'p.source = :fsrc';
    $params[':fsrc'] = $fSrc;
}
if ($q !== '') {
    $where[] = '(p.sku LIKE :q OR p.name LIKE :q OR p.yard_location LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$condLabels = shop_filter_condition_labels();
$srcLabels  = shop_filter_source_labels();

$shopBrowseQuery = static function (string $qStr, string $cStr, string $sStr, ?int $pg): string {
    return http_build_query(
        array_filter(
            [
                'q'    => $qStr !== '' ? $qStr : null,
                'cond' => $cStr !== '' ? $cStr : null,
                'src'  => $sStr !== '' ? $sStr : null,
                'page' => ($pg ?? 1) > 1 ? $pg : null,
            ],
            static fn ($v): bool => $v !== null && $v !== ''
        )
    );
};
$cnt = $pdo->prepare("SELECT COUNT(*) FROM parts p {$whereSql}");
$cnt->execute($params);
$total = (int) $cnt->fetchColumn();
$pages = max(1, (int) ceil($total / $per));

$sql = "SELECT p.id, p.sku, p.name, p.source, p.condition_grade, p.asking_price, p.discount_price,
               p.vat_rate, p.qty_on_hand,
               (SELECT path FROM part_photos WHERE part_id = p.id ORDER BY sort_order ASC, id ASC LIMIT 1) AS thumb
        FROM parts p
        {$whereSql}
        ORDER BY p.updated_at DESC, p.sku ASC
        LIMIT " . (int) $per . " OFFSET " . (int) $off;

$st = $pdo->prepare($sql);
$st->execute($params);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

shop_layout_head($shopPageTitle);
shop_layout_nav($base, shop_cart_count_items());
?>
<div class="container py-4">
  <div class="row mb-3 align-items-end">
    <div class="col-lg-12">
      <h1 class="h3 mb-1">Spares for sale</h1>
      <p class="text-muted small mb-0">
        <strong>Cart checkout:</strong> <strong>OEM new</strong> or <strong>Replacement</strong> parts graded <strong>New</strong>, <strong>Good</strong> or <strong>Fair</strong>. Other listings are <strong>Enquiry</strong> only.
        <strong>Third-party / stripped</strong> — browse here, then <a href="<?= e($base) ?>/shop/enquiry.php">send a message</a>.
        Filters below; staff use <strong>List on public website</strong> in Inventory to publish.
      </p>
    </div>
  </div>
  <form method="get" class="card shadow-sm mb-4">
    <div class="card-body py-3">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label small mb-0">Search</label>
          <input type="search" name="q" class="form-control" placeholder="Search by name…" value="<?= e($q) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-0">Condition</label>
          <select name="cond" class="form-select">
            <option value="">All conditions</option>
            <?php foreach ($condLabels as $ck => $cl): ?>
              <option value="<?= e($ck) ?>" <?= $fCond === $ck ? 'selected' : '' ?>><?= e($cl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-0">Source</label>
          <select name="src" class="form-select">
            <option value="">All sources</option>
            <?php foreach ($srcLabels as $sk => $sl): ?>
              <option value="<?= e($sk) ?>" <?= $fSrc === $sk ? 'selected' : '' ?>><?= e($sl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-danger"><i class="bi bi-funnel"></i> Apply filters</button>
          <a class="btn btn-outline-secondary" href="<?= e($base) ?>/shop/">Clear</a>
        </div>
      </div>
    </div>
  </form>

  <?php if (!$items): ?>
    <div class="alert alert-light border">No parts match your search or filters. Staff: set <strong>Available</strong>, <strong>Qty on hand</strong> ≥ 1, tick <strong>List on public website</strong> in <strong>Inventory → Edit part</strong>, then save.</div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($items as $it): ?>
        <?php
        $it = array_change_key_case($it, CASE_LOWER);
        $pid = (int) $it['id'];
        $unit = shop_unit_price_ex_vat($it);
        $vr   = round((float) ($it['vat_rate'] ?? 0), 2);
        $inc  = round($unit + ($unit * $vr / 100), 2);
        $img  = !empty($it['thumb']) ? ($base . '/' . $it['thumb']) : null;
        $cKey = (string) ($it['condition_grade'] ?? '');
        $sKey = (string) ($it['source'] ?? '');
        $cLb  = $condLabels[$cKey] ?? $cKey;
        $sLb  = $srcLabels[$sKey] ?? $sKey;
        $canBuy = shop_part_purchasable_online($it);
        ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="card h-100 shadow-sm card-product">
            <?php if ($img): ?>
              <img class="card-img-top" src="<?= e($img) ?>" alt="">
            <?php else: ?>
              <div class="card-img-top d-flex align-items-center justify-content-center text-muted small">No photo</div>
            <?php endif; ?>
            <div class="card-body d-flex flex-column">
              <div class="mb-1">
                <?php if ($canBuy): ?>
                  <span class="badge bg-success small">Buy online</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark small border border-dark">Enquiry</span>
                <?php endif; ?>
                <span class="badge bg-secondary text-uppercase small"><?= e($cLb) ?></span>
                <span class="badge bg-dark small"><?= e($sLb) ?></span>
              </div>
              <h2 class="h6 card-title"><?= e((string) $it['name']) ?></h2>
              <p class="mb-1 price-tag">R <?= number_format($inc, 2) ?> <span class="text-muted fw-normal small">incl. VAT</span></p>
              <p class="small mb-2"><span class="badge bg-dark">In stock: <?= (int) $it['qty_on_hand'] ?></span></p>
              <a class="btn btn-outline-danger btn-sm mt-auto" href="<?= e($base) ?>/shop/part.php?id=<?= $pid ?>"><?= $canBuy ? 'View / add' : 'View / message' ?></a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="mt-4">
        <ul class="pagination justify-content-center">
          <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
              <a class="page-link" href="?<?= e($shopBrowseQuery($q, $fCond, $fSrc, $i > 1 ? $i : null)) ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php
shop_layout_foot();

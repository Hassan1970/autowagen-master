<?php
/**
 * Public shop — browse parts by EPC category + optional submenu `line`.
 * Engine: line = parts | complete | heads. Gearbox (transmission-driveline): parts | complete.
 * Lanes use keywords in `parts.name` — see shop_helpers.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/_layout.php';

$base = rtrim((string) APP_URL, '/');
$slugIn = trim((string) ($_GET['slug'] ?? ''));

if (!shop_tables_ready($pdo) || !shop_parts_list_online_ready($pdo)) {
    shop_layout_head('Shop setup');
    shop_layout_nav($base, 0);
    echo '<div class="container py-5"><div class="alert alert-warning">';
    echo '<strong>Web shop tables missing.</strong> Run <code>sql/06b_web_shop.sql</code>, then refresh.';
    echo '</div></div>';
    shop_layout_foot();
    exit;
}

$catInfo = shop_resolve_epc_category($pdo, $slugIn);
if ($catInfo === null) {
    shop_layout_head('Not found');
    shop_layout_nav($base, shop_cart_count_items());
    echo '<div class="container py-5"><div class="alert alert-secondary">';
    echo 'Category not found. <a href="' . e($base) . '/shop/">Browse all parts</a>';
    echo '</div></div>';
    shop_layout_foot();
    exit;
}

$catSlug = $catInfo['slug'];
$catName = $catInfo['name'];
$sub     = shop_category_submenu_context($catSlug, (string) ($_GET['line'] ?? ''), $catName);
$subLine = $sub['key'];
$lineSql = $sub['sql'];
$lineTitle = $sub['title'];
$subFamily = $sub['family'];

$q     = trim((string) ($_GET['q'] ?? ''));
$make  = trim((string) ($_GET['make'] ?? ''));
$model = trim((string) ($_GET['model'] ?? ''));
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

$where = [shop_catalog_base_conditions(), 'efv.category_slug = :cat_slug'];
$params = [':cat_slug' => $catSlug];

if ($lineSql !== null) {
    $where[] = $lineSql;
}

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

$vehicleFilter = ($make !== '' || $model !== '');
if ($vehicleFilter) {
    $where[] = 'veh.id IS NOT NULL AND veh.is_active = 1';
    if ($make !== '') {
        $where[] = 'veh.make LIKE :vmake';
        $params[':vmake'] = '%' . $make . '%';
    }
    if ($model !== '') {
        $where[] = 'veh.model LIKE :vmodel';
        $params[':vmodel'] = '%' . $model . '%';
    }
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$fromSql = "
    FROM parts p
    INNER JOIN part_epc_links pel ON pel.part_id = p.id
    INNER JOIN epc_full_view efv ON efv.variant_id = pel.variant_id
    LEFT JOIN vehicles veh ON veh.id = p.vehicle_id
    {$whereSql}
";

$condLabels = shop_filter_condition_labels();
$srcLabels  = shop_filter_source_labels();

$shopCatQuery = static function (
    string $slug,
    string $lineStr,
    string $qStr,
    string $mk,
    string $mdl,
    string $cStr,
    string $sStr,
    ?int $pg
): string {
    return http_build_query(
        array_filter(
            [
                'slug' => $slug,
                'line' => $lineStr !== '' ? $lineStr : null,
                'q'    => $qStr !== '' ? $qStr : null,
                'make' => $mk !== '' ? $mk : null,
                'model'=> $mdl !== '' ? $mdl : null,
                'cond' => $cStr !== '' ? $cStr : null,
                'src'  => $sStr !== '' ? $sStr : null,
                'page' => ($pg ?? 1) > 1 ? $pg : null,
            ],
            static fn ($v): bool => $v !== null && $v !== ''
        )
    );
};

try {
    $cnt = $pdo->prepare("SELECT COUNT(DISTINCT p.id) {$fromSql}");
    $cnt->execute($params);
    $total = (int) $cnt->fetchColumn();
} catch (Throwable $e) {
    shop_layout_head('Shop error');
    shop_layout_nav($base, shop_cart_count_items());
    echo '<div class="container py-5"><div class="alert alert-danger">';
    echo '<strong>Catalog error.</strong> Ensure <code>epc_full_view</code> exists (run Stage 2 SQL / recreate the view on hosting). ';
    echo '<a href="' . e($base) . '/shop/">Browse all parts</a>';
    echo '</div></div>';
    shop_layout_foot();
    exit;
}

$pages = max(1, (int) ceil($total / $per));

$sql = "SELECT DISTINCT p.id, p.sku, p.name, p.source, p.condition_grade, p.asking_price, p.discount_price,
               p.vat_rate, p.qty_on_hand, p.updated_at,
               (SELECT path FROM part_photos WHERE part_id = p.id ORDER BY sort_order ASC, id ASC LIMIT 1) AS thumb
        {$fromSql}
        ORDER BY p.updated_at DESC, p.sku ASC
        LIMIT " . (int) $per . " OFFSET " . (int) $off;

$st = $pdo->prepare($sql);
$st->execute($params);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

$shopPageTitle = $lineTitle;
shop_layout_head($shopPageTitle);
shop_layout_nav($base, shop_cart_count_items());

$hasActiveFilters = ($q !== '' || $vehicleFilter || $fCond !== '' || $fSrc !== '');
$clearCatUrl = $base . '/shop/category.php?slug=' . rawurlencode($catSlug)
    . ($subLine !== '' ? '&line=' . rawurlencode($subLine) : '');
?>
<div class="container py-4">
  <div class="row mb-3 align-items-end">
    <div class="col-lg-12">
      <h1 class="h3 mb-1"><?= e($lineTitle) ?></h1>
      <p class="text-muted small mb-0">
        Listed parts are tagged under <strong><?= e($catName) ?></strong> in the EPC catalogue.
        Same web rules as <a href="<?= e($base) ?>/shop/">Browse parts</a>.
        <?php if ($subFamily === 'engine' && $subLine !== ''): ?>
          <strong>How sorting works:</strong> listings are grouped by <strong>words in the part title</strong> (e.g. <em>Complete engine</em>, <em>Cylinder head</em>). Use clear names in Inventory.
        <?php elseif ($subFamily === 'gearbox' && $subLine !== ''): ?>
          <strong>How sorting works:</strong> whole units — titles with <em>Complete gearbox</em> / <em>Gearbox assembly</em>; <strong>loose parts</strong> — e.g. <em>Bell housing</em>, <em>Centre / center casing</em>, <em>Gears</em> / <em>Gear set</em> (avoid “complete gearbox” in the title).
        <?php endif; ?>
        <?php if ($vehicleFilter): ?>
          Filtered by vehicle <strong>make</strong> / <strong>model</strong> for parts linked to a vehicle.
        <?php elseif ($catSlug === 'engine' || $catSlug === 'transmission-driveline'): ?>
          Optional <strong>Make</strong> / <strong>Model</strong> narrow listings linked to yard vehicles.
        <?php else: ?>
          Optional <strong>Make</strong> and <strong>Model</strong> narrow to parts from matching yard vehicles.
        <?php endif; ?>
      </p>
    </div>
  </div>
  <form method="get" class="card shadow-sm mb-4">
    <input type="hidden" name="slug" value="<?= e($catSlug) ?>">
    <?php if ($subLine !== ''): ?>
      <input type="hidden" name="line" value="<?= e($subLine) ?>">
    <?php endif; ?>
    <div class="card-body py-3">
      <div class="row g-2 align-items-end">
        <div class="col-md-2">
          <label class="form-label small mb-0">Search</label>
          <input type="search" name="q" class="form-control" placeholder="Search by name…" value="<?= e($q) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-0">Vehicle make</label>
          <input type="text" name="make" class="form-control" placeholder="e.g. Toyota" value="<?= e($make) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-0">Vehicle model</label>
          <input type="text" name="model" class="form-control" placeholder="e.g. Hilux" value="<?= e($model) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-0">Condition</label>
          <select name="cond" class="form-select">
            <option value="">All conditions</option>
            <?php foreach ($condLabels as $ck => $cl): ?>
              <option value="<?= e($ck) ?>" <?= $fCond === $ck ? 'selected' : '' ?>><?= e($cl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small mb-0">Source</label>
          <select name="src" class="form-select">
            <option value="">All sources</option>
            <?php foreach ($srcLabels as $sk => $sl): ?>
              <option value="<?= e($sk) ?>" <?= $fSrc === $sk ? 'selected' : '' ?>><?= e($sl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-danger"><i class="bi bi-funnel"></i> Apply</button>
          <a class="btn btn-outline-secondary" href="<?= e($base) ?>/shop/category.php?slug=<?= e(urlencode($catSlug)) ?><?= $subLine !== '' ? '&line=' . e(urlencode($subLine)) : '' ?>">Clear</a>
        </div>
      </div>
    </div>
  </form>

  <?php if (!$items): ?>
    <?php
    $showEngineGearPlaceholder = ($total === 0 && !$hasActiveFilters && ($subFamily === 'engine' || $subFamily === 'gearbox'));
    $phIcon    = 'bi-gear-wide-connected';
    $phHeading = 'Listings — coming soon';
    $phBody    = '';
    $phEpcHint = 'the right EPC category';
    if ($showEngineGearPlaceholder) {
        if ($subFamily === 'engine') {
            $phEpcHint = '<strong>Engine</strong>';
            $phHeading = 'Engine listings — coming soon';
            $phBody    = 'There are no engine-tagged parts here yet.';
            if ($subLine === 'parts') {
                $phHeading = 'Loose engine parts — coming soon';
                $phBody    = 'No loose engine parts match this lane yet. Titles should <strong>not</strong> use the phrases reserved for assemblies (e.g. “Complete engine”, “Cylinder head”).';
            } elseif ($subLine === 'complete') {
                $phIcon    = 'bi-box-seam';
                $phHeading = 'Complete engines / sub-units — coming soon';
                $phBody    = 'List full engines with titles containing e.g. <strong>Complete engine</strong>, <strong>long block</strong>, <strong>sub unit</strong>.';
            } elseif ($subLine === 'heads') {
                $phIcon    = 'bi-grid-1x2';
                $phHeading = 'Cylinder heads / bare — coming soon';
                $phBody    = 'Use titles containing <strong>Cylinder head</strong>, <strong>cyl head</strong>, or <strong>bare head</strong>.';
            }
        } else {
            $phIcon    = 'bi-gear-fill';
            $phEpcHint = '<strong>Transmission &amp; driveline</strong>';
            $phHeading = 'Gearbox & transmission listings — coming soon';
            $phBody    = 'There are no parts tagged under Transmission &amp; driveline yet.';
            if ($subLine === 'parts') {
                $phHeading = 'Gearbox parts — coming soon';
                $phBody    = 'Loose gearbox parts use this lane — e.g. <strong>Bell housing</strong>, <strong>Centre / center casing</strong>, <strong>Gears</strong>. Do <strong>not</strong> use whole-unit phrases (<strong>Complete gearbox</strong>, <strong>Gearbox assembly</strong>) in the title.';
            } elseif ($subLine === 'complete') {
                $phIcon    = 'bi-box-seam';
                $phHeading = 'Complete gearboxes — coming soon';
                $phBody    = 'Use titles containing e.g. <strong>Complete gearbox</strong>, <strong>gearbox assembly</strong>, <strong>complete automatic gearbox</strong>.';
            }
        }
    }
    ?>
    <?php if ($showEngineGearPlaceholder): ?>
      <div class="card border-2 border-secondary shadow-sm overflow-hidden mb-4">
        <div class="row g-0">
          <div class="col-md-5 bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center py-5">
            <div class="text-center text-secondary px-3">
              <i class="bi <?= e($phIcon) ?> display-1 d-block mb-2" aria-hidden="true"></i>
              <span class="small text-uppercase fw-bold" style="letter-spacing:.15em;">Placeholder</span>
            </div>
          </div>
          <div class="col-md-7">
            <div class="card-body py-4">
              <h2 class="h5 text-dark"><?= e($phHeading) ?></h2>
              <p class="text-muted mb-3"><?= $phBody ?></p>
              <ul class="small text-muted mb-4">
                <li><strong>Inventory → Edit part</strong> — link to an EPC path under <?= $phEpcHint ?>, tick <strong>List on public website</strong>.</li>
                <li>Customers see photos, <strong>in stock</strong> count, and price — not internal stock codes.</li>
              </ul>
              <a class="btn btn-danger" href="<?= e($base) ?>/shop/">Browse all parts</a>
              <a class="btn btn-outline-dark ms-2" href="<?= e($base) ?>/shop/stripping/">Stripping stock</a>
            </div>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert-light border">No parts match<?= $hasActiveFilters ? ' your filters' : '' ?>.
        <a href="<?= e($clearCatUrl) ?>">Reset this view</a> or
        <a href="<?= e($base) ?>/shop/">browse all parts</a>.
      </div>
    <?php endif; ?>
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
              <a class="page-link" href="?<?= e($shopCatQuery($catSlug, $subLine, $q, $make, $model, $fCond, $fSrc, $i > 1 ? $i : null)) ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php
shop_layout_foot();

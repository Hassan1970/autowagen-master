<?php
/**
 * EPC full hierarchy — read-only reference for staff / clients.
 * Six levels: Category → Subcategory → Type → Subsystem → Component → Variant.
 * Data from epc_* tables (active rows only); one row per variant leaf.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$pageTitle = 'EPC full tree (reference)';
include __DIR__ . '/includes/header.php';

$sql = <<<'SQL'
SELECT
  cat.id              AS category_id,
  cat.name            AS category_name,
  cat.sort_order      AS cat_sort,
  sc.id               AS subcategory_id,
  sc.name             AS subcategory_name,
  sc.sort_order       AS sc_sort,
  t.id                AS type_id,
  t.name              AS type_name,
  t.sort_order        AS ty_sort,
  s.id                AS subsystem_id,
  s.name              AS subsystem_name,
  s.sort_order        AS ss_sort,
  c.id                AS component_id,
  c.name              AS component_name,
  c.sort_order        AS co_sort,
  v.id                AS variant_id,
  v.name              AS variant_name,
  v.sort_order        AS va_sort
FROM       epc_variants      v
INNER JOIN epc_components    c  ON c.id   = v.component_id  AND c.is_active  = 1
INNER JOIN epc_subsystems    s  ON s.id   = c.subsystem_id  AND s.is_active  = 1
INNER JOIN epc_types         t  ON t.id   = s.type_id       AND t.is_active  = 1
INNER JOIN epc_subcategories sc ON sc.id  = t.subcategory_id AND sc.is_active = 1
INNER JOIN epc_categories    cat ON cat.id = sc.category_id  AND cat.is_active = 1
WHERE v.is_active = 1
ORDER BY
  cat.sort_order, cat.id,
  sc.sort_order, sc.id,
  t.sort_order, t.id,
  s.sort_order, s.id,
  c.sort_order, c.id,
  v.sort_order, v.id
SQL;

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

/* ---- Build nested tree (order preserved by first appearance = ORDER BY above) ---- */
$tree = [];
foreach ($rows as $r) {
    $cId  = (int) $r['category_id'];
    $scId = (int) $r['subcategory_id'];
    $tyId = (int) $r['type_id'];
    $ssId = (int) $r['subsystem_id'];
    $coId = (int) $r['component_id'];
    $vaId = (int) $r['variant_id'];

    if (!isset($tree[$cId])) {
        $tree[$cId] = ['name' => $r['category_name'], 'children' => []];
    }
    if (!isset($tree[$cId]['children'][$scId])) {
        $tree[$cId]['children'][$scId] = ['name' => $r['subcategory_name'], 'children' => []];
    }
    if (!isset($tree[$cId]['children'][$scId]['children'][$tyId])) {
        $tree[$cId]['children'][$scId]['children'][$tyId] = ['name' => $r['type_name'], 'children' => []];
    }
    if (!isset($tree[$cId]['children'][$scId]['children'][$tyId]['children'][$ssId])) {
        $tree[$cId]['children'][$scId]['children'][$tyId]['children'][$ssId] = ['name' => $r['subsystem_name'], 'children' => []];
    }
    if (!isset($tree[$cId]['children'][$scId]['children'][$tyId]['children'][$ssId]['children'][$coId])) {
        $tree[$cId]['children'][$scId]['children'][$tyId]['children'][$ssId]['children'][$coId] = ['name' => $r['component_name'], 'children' => []];
    }
    $tree[$cId]['children'][$scId]['children'][$tyId]['children'][$ssId]['children'][$coId]['children'][$vaId] = $r['variant_name'];
}

$leafCount = count($rows);
$catCount  = count($tree);
?>

<style>
  .epc-tree-wrap { max-width: 1100px; }
  .epc-tree-legend .badge { font-weight: 600; }
  .epc-tree-controls .btn { margin: 0 .25rem .25rem 0; }
  .epc-node {
    cursor: pointer;
    margin: 2px 0;
    padding: 4px 6px;
    border-radius: 4px;
    font-size: 0.95rem;
    user-select: none;
  }
  .epc-node:hover { background: #fff5f6; }
  .epc-children { margin-left: 1.25rem; display: none; border-left: 1px dashed #ddd; padding-left: 8px; }
  .lvl-1 { color: #0d6efd; font-weight: 600; }
  .lvl-2 { color: #c9a000; font-weight: 600; }
  .lvl-3 { color: #198754; font-weight: 600; }
  .lvl-4 { color: #fd7e14; font-weight: 600; }
  .lvl-5 { color: #495057; font-weight: 600; }
  .lvl-6 { color: #20c997; font-weight: 500; }
  .epc-leaf {
    margin: 2px 0 2px 1.25rem;
    padding: 2px 6px;
    font-size: 0.9rem;
    color: #333;
    border-left: 2px solid #e9ecef;
    padding-left: 8px;
  }
  .epc-muted { font-size: 0.8rem; color: #6c757d; }
  @media print {
    .no-print, .navbar, footer, .epc-tree-controls { display: none !important; }
    .epc-children { display: block !important; }
    body { background: #fff; }
  }
</style>

<div class="container-fluid epc-tree-wrap py-3">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
      <h1 class="h4 mb-1"><i class="bi bi-diagram-3-fill"></i> EPC full tree (reference)</h1>
      <p class="text-muted small mb-2">
        Click a row to expand or collapse. Use the same labels in
        <a href="<?= e(APP_URL) ?>/epc_browse.php">Browse tree</a> to tag parts.
      </p>
      <div class="epc-tree-legend no-print small mb-2">
        <span class="badge text-bg-primary me-1">Level 1 — Category</span>
        <span class="badge text-bg-warning text-dark me-1">Level 2 — Subcategory</span>
        <span class="badge text-bg-success me-1">Level 3 — Type</span>
        <span class="badge text-bg-warning me-1" style="background:#fd7e14!important;color:#fff!important">Level 4 — Subsystem</span>
        <span class="badge text-bg-secondary me-1">Level 5 — Component</span>
        <span class="badge text-bg-info text-dark">Level 6 — Variant</span>
      </div>
      <p class="epc-muted mb-0"><?= (int) $catCount ?> categories · <?= (int) $leafCount ?> variant paths (rows)</p>
    </div>
    <div class="epc-tree-controls no-print">
      <button type="button" class="btn btn-outline-secondary btn-sm" id="epc-expand-all"><i class="bi bi-arrows-expand"></i> Expand all</button>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="epc-collapse-all"><i class="bi bi-arrows-collapse"></i> Collapse all</button>
      <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print / PDF</button>
      <a class="btn btn-sm btn-outline-danger" href="<?= e(APP_URL) ?>/epc_browse.php"><i class="bi bi-eye"></i> Browse</a>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <?php if ($leafCount === 0): ?>
        <p class="text-muted mb-0">No active variant rows found. Run EPC SQL seeds and confirm <code>epc_variants</code> has data.</p>
      <?php else: ?>
        <div id="epc-tree-root">
          <?php
          foreach ($tree as $catNode):
              ?>
            <div class="epc-node lvl-1" data-epc-toggle>▶ 🔵 [CATEGORY] <?= e($catNode['name']) ?></div>
            <div class="epc-children">
              <?php foreach ($catNode['children'] as $scNode): ?>
                <div class="epc-node lvl-2" data-epc-toggle>▶ 🟡 [SUBCATEGORY] <?= e($scNode['name']) ?></div>
                <div class="epc-children">
                  <?php foreach ($scNode['children'] as $tyNode): ?>
                    <div class="epc-node lvl-3" data-epc-toggle>▶ 🟢 [TYPE] <?= e($tyNode['name']) ?></div>
                    <div class="epc-children">
                      <?php foreach ($tyNode['children'] as $ssNode): ?>
                        <div class="epc-node lvl-4" data-epc-toggle>▶ 🟠 [SUBSYSTEM] <?= e($ssNode['name']) ?></div>
                        <div class="epc-children">
                          <?php foreach ($ssNode['children'] as $coNode): ?>
                            <div class="epc-node lvl-5" data-epc-toggle>▶ ⚪ [COMPONENT] <?= e($coNode['name']) ?></div>
                            <div class="epc-children">
                              <?php foreach ($coNode['children'] as $vaName): ?>
                                <div class="epc-leaf lvl-6">• 🟢 [VARIANT] <?= e($vaName) ?></div>
                              <?php endforeach; ?>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function () {
  function toggleNext(node) {
    var next = node.nextElementSibling;
    if (!next || !next.classList.contains('epc-children')) return;
    var open = next.style.display === 'block';
    next.style.display = open ? 'none' : 'block';
    node.textContent = node.textContent.replace(/^▶|^▼/, open ? '▶' : '▼');
  }

  document.querySelectorAll('[data-epc-toggle]').forEach(function (node) {
    node.addEventListener('click', function (ev) {
      ev.preventDefault();
      toggleNext(node);
    });
  });

  document.getElementById('epc-expand-all')?.addEventListener('click', function () {
    document.querySelectorAll('.epc-children').forEach(function (el) { el.style.display = 'block'; });
    document.querySelectorAll('[data-epc-toggle]').forEach(function (n) {
      n.textContent = n.textContent.replace(/^▶/, '▼');
    });
  });

  document.getElementById('epc-collapse-all')?.addEventListener('click', function () {
    document.querySelectorAll('.epc-children').forEach(function (el) { el.style.display = 'none'; });
    document.querySelectorAll('[data-epc-toggle]').forEach(function (n) {
      n.textContent = n.textContent.replace(/^▼/, '▶');
    });
  });

  // Show level-1 children on load; mark arrow expanded
  document.querySelectorAll('.lvl-1').forEach(function (cat) {
    var next = cat.nextElementSibling;
    if (next && next.classList.contains('epc-children')) {
      next.style.display = 'block';
      cat.textContent = cat.textContent.replace(/^▶/, '▼');
    }
  });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

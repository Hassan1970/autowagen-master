<?php
/**
 * EPC drill-down browser.
 * Six columns left-to-right: Category > Subcategory > Type > Subsystem
 *                            > Component > Variant.
 * Click any node to load its children into the next column.
 *
 * Read-only. Any logged-in user may view.
 * (Stage 6 will add a customer-facing public version.)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/epc_helpers.php';

$pageTitle = 'EPC Browser';
include __DIR__ . '/includes/header.php';

$levels       = array_keys(EPC_LEVELS);
$cascadeUrl   = e(APP_URL) . '/ajax/epc_cascade.php';
$adminAllowed = user_has_role('owner', 'admin');
?>

<style>
  .epc-board       { display:flex; gap:.75rem; overflow-x:auto; padding-bottom:1rem; }
  .epc-col         { flex:0 0 220px; background:#fff; border:1px solid #e2e2e2;
                     border-radius:.5rem; box-shadow:0 1px 2px rgba(0,0,0,.04);
                     display:flex; flex-direction:column; min-height:360px; }
  .epc-col header  { padding:.5rem .75rem; border-bottom:1px solid #eee;
                     font-size:.75rem; text-transform:uppercase;
                     letter-spacing:.08em; color:#666; background:#fafafa;
                     border-radius:.5rem .5rem 0 0; }
  .epc-col .body   { flex:1; overflow-y:auto; }
  .epc-item        { display:block; padding:.5rem .75rem; border-bottom:1px solid #f1f1f1;
                     cursor:pointer; color:#222; text-decoration:none; font-size:.9rem; }
  .epc-item:hover  { background:#fff5f6; color:#c8102e; }
  .epc-item.active { background:#c8102e; color:#fff; font-weight:600; }
  .epc-empty,
  .epc-loading,
  .epc-err         { padding:.75rem; font-size:.85rem; color:#888; }
  .epc-err         { color:#b00020; }
  .epc-leaf-note   { padding:.75rem; font-size:.85rem; color:#666; font-style:italic; }
  .epc-crumbs      { font-size:.95rem; }
  .epc-crumbs .sep { color:#bbb; margin:0 .35rem; }
</style>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-1"><i class="bi bi-diagram-3"></i> EPC Browser</h1>
      <div class="epc-crumbs text-muted" id="epc-breadcrumb">
        <span class="text-muted">Pick a category to start.</span>
      </div>
    </div>
    <div>
      <?php if ($adminAllowed): ?>
        <a class="btn btn-sm btn-outline-dark" href="<?= e(APP_URL) ?>/epc_admin.php">
          <i class="bi bi-pencil-square"></i> Manage tree
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="epc-board" id="epc-board">
    <?php foreach ($levels as $i => $lvl):
            $meta = EPC_LEVELS[$lvl]; ?>
      <div class="epc-col" data-level="<?= e($lvl) ?>" data-index="<?= $i ?>">
        <header><?= e($meta['plural']) ?></header>
        <div class="body" data-role="list">
          <?php if ($i === 0): ?>
            <div class="epc-loading">Loading...</div>
          <?php else: ?>
            <div class="epc-empty">&mdash;</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
(function () {
  const CASCADE_URL = <?= json_encode($cascadeUrl) ?>;
  const LEVELS      = <?= json_encode($levels) ?>;
  const board       = document.getElementById('epc-board');
  const breadcrumb  = document.getElementById('epc-breadcrumb');
  const selection   = {};

  function colByLevel(level) {
    return board.querySelector('.epc-col[data-level="' + level + '"]');
  }

  function clearFrom(levelIndex) {
    for (let i = levelIndex; i < LEVELS.length; i++) {
      const lvl = LEVELS[i];
      const col = colByLevel(lvl);
      const list = col.querySelector('[data-role="list"]');
      list.innerHTML = '<div class="epc-empty">&mdash;</div>';
      delete selection[lvl];
    }
    renderBreadcrumb();
  }

  function renderBreadcrumb() {
    const parts = [];
    LEVELS.forEach(function (lvl) {
      if (selection[lvl]) {
        parts.push('<span>' + escapeHtml(selection[lvl].name) + '</span>');
      }
    });
    breadcrumb.innerHTML = parts.length
      ? parts.join('<span class="sep">/</span>')
      : '<span class="text-muted">Pick a category to start.</span>';
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c];
    });
  }

  function loadInto(parentLevel, parentId, targetLevelIndex) {
    const targetLevel = LEVELS[targetLevelIndex];
    const col  = colByLevel(targetLevel);
    const list = col.querySelector('[data-role="list"]');
    list.innerHTML = '<div class="epc-loading">Loading...</div>';

    const url = new URL(CASCADE_URL, window.location.origin);
    url.searchParams.set('parent_level', parentLevel);
    if (parentId !== null) url.searchParams.set('parent_id', parentId);

    fetch(url.toString(), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) {
          list.innerHTML = '<div class="epc-err">' + escapeHtml(data.error || 'Error') + '</div>';
          return;
        }
        if (!data.items || data.items.length === 0) {
          list.innerHTML = '<div class="epc-leaf-note">No ' + targetLevel + 's yet.</div>';
          return;
        }
        list.innerHTML = '';
        data.items.forEach(function (item) {
          const a = document.createElement('a');
          a.className = 'epc-item';
          a.href = '#';
          a.dataset.id = item.id;
          a.dataset.name = item.name;
          a.textContent = item.name;
          a.addEventListener('click', function (ev) {
            ev.preventDefault();
            list.querySelectorAll('.epc-item.active').forEach(function (x) { x.classList.remove('active'); });
            a.classList.add('active');
            selection[targetLevel] = { id: item.id, name: item.name };
            clearFrom(targetLevelIndex + 1);
            const childIdx = targetLevelIndex + 1;
            if (childIdx < LEVELS.length) {
              loadInto(targetLevel, item.id, childIdx);
            }
            renderBreadcrumb();
          });
          list.appendChild(a);
        });
      })
      .catch(function (err) {
        list.innerHTML = '<div class="epc-err">Network error: ' + escapeHtml(err.message) + '</div>';
      });
  }

  loadInto('root', null, 0);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

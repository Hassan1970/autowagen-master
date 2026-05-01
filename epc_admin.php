<?php
/**
 * EPC tree manager. Owner / admin only.
 * Same six-column drill-down as epc_browse.php, with controls under each
 * column to add / rename / reorder / activate-deactivate nodes.
 *
 * All mutating actions are POSTed to this same file with a CSRF token.
 * Slugs are auto-generated from the name and are unique within a parent.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/epc_helpers.php';

if (!user_has_role('owner', 'admin')) {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    include __DIR__ . '/includes/header.php';
    echo '<div class="container"><div class="alert alert-danger">'
       . 'You do not have permission to manage the EPC tree. '
       . 'Owner or admin role required.</div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$flash = ['type' => null, 'msg' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = ['type' => 'danger', 'msg' => 'Security token invalid. Please reload the page and try again.'];
    } else {
        $action    = $_POST['action']      ?? '';
        $levelName = $_POST['level']       ?? '';
        $level     = epc_level($levelName);

        if (!$level) {
            $flash = ['type' => 'danger', 'msg' => 'Unknown EPC level.'];
        } else {
            try {
                switch ($action) {

                    case 'add': {
                        $name     = trim((string) ($_POST['name'] ?? ''));
                        $parentId = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
                        if ($name === '') {
                            throw new RuntimeException('Name is required.');
                        }
                        if ($level['parent_col'] !== null && $parentId <= 0) {
                            throw new RuntimeException('Pick a parent ' . $level['parent'] . ' first.');
                        }
                        $slug = epc_slugify($name);
                        $sort = epc_next_sort($pdo, $levelName, $parentId ?: null);

                        if ($level['parent_col'] === null) {
                            $sql = "INSERT INTO `{$level['table']}` (name, slug, sort_order)
                                    VALUES (:name, :slug, :sort)";
                            $params = [':name' => $name, ':slug' => $slug, ':sort' => $sort];
                        } else {
                            $sql = "INSERT INTO `{$level['table']}`
                                    (`{$level['parent_col']}`, name, slug, sort_order)
                                    VALUES (:pid, :name, :slug, :sort)";
                            $params = [
                                ':pid' => $parentId,
                                ':name' => $name, ':slug' => $slug, ':sort' => $sort,
                            ];
                        }
                        $pdo->prepare($sql)->execute($params);
                        $flash = ['type' => 'success', 'msg' => 'Added ' . $level['label'] . ': ' . $name];
                        break;
                    }

                    case 'rename': {
                        $id   = (int) ($_POST['id']   ?? 0);
                        $name = trim((string) ($_POST['name'] ?? ''));
                        if ($id <= 0 || $name === '') {
                            throw new RuntimeException('Missing id or name.');
                        }
                        $sql = "UPDATE `{$level['table']}`
                                SET name = :name, slug = :slug
                                WHERE id = :id";
                        $pdo->prepare($sql)->execute([
                            ':name' => $name,
                            ':slug' => epc_slugify($name),
                            ':id'   => $id,
                        ]);
                        $flash = ['type' => 'success', 'msg' => 'Renamed to: ' . $name];
                        break;
                    }

                    case 'toggle_active': {
                        $id = (int) ($_POST['id'] ?? 0);
                        if ($id <= 0) {
                            throw new RuntimeException('Missing id.');
                        }
                        $sql = "UPDATE `{$level['table']}`
                                SET is_active = 1 - is_active
                                WHERE id = :id";
                        $pdo->prepare($sql)->execute([':id' => $id]);
                        $flash = ['type' => 'success', 'msg' => 'Active state toggled.'];
                        break;
                    }

                    case 'move': {
                        $id   = (int) ($_POST['id']  ?? 0);
                        $dir  = ($_POST['dir'] ?? '') === 'up' ? 'up' : 'down';
                        if ($id <= 0) {
                            throw new RuntimeException('Missing id.');
                        }
                        // Find the row, find its neighbour, swap sort_order.
                        $row = $pdo->prepare(
                            "SELECT id, sort_order"
                            . ($level['parent_col'] ? ", `{$level['parent_col']}` AS pid" : ", 0 AS pid")
                            . " FROM `{$level['table']}` WHERE id = :id"
                        );
                        $row->execute([':id' => $id]);
                        $cur = $row->fetch();
                        if (!$cur) {
                            throw new RuntimeException('Row not found.');
                        }
                        $cmp = $dir === 'up' ? '<' : '>';
                        $ord = $dir === 'up' ? 'DESC' : 'ASC';
                        $whereParent = $level['parent_col']
                            ? "`{$level['parent_col']}` = :pid AND "
                            : '';
                        $stmt = $pdo->prepare(
                            "SELECT id, sort_order FROM `{$level['table']}`
                             WHERE {$whereParent}sort_order {$cmp} :s
                             ORDER BY sort_order {$ord} LIMIT 1"
                        );
                        $params = [':s' => (int) $cur['sort_order']];
                        if ($level['parent_col']) {
                            $params[':pid'] = (int) $cur['pid'];
                        }
                        $stmt->execute($params);
                        $nb = $stmt->fetch();
                        if (!$nb) {
                            $flash = ['type' => 'info', 'msg' => 'Already at the ' . ($dir === 'up' ? 'top' : 'bottom') . '.'];
                            break;
                        }
                        $pdo->beginTransaction();
                        $upd = $pdo->prepare("UPDATE `{$level['table']}` SET sort_order = :s WHERE id = :id");
                        $upd->execute([':s' => (int) $nb['sort_order'],  ':id' => (int) $cur['id']]);
                        $upd->execute([':s' => (int) $cur['sort_order'], ':id' => (int) $nb['id']]);
                        $pdo->commit();
                        $flash = ['type' => 'success', 'msg' => 'Moved ' . $dir . '.'];
                        break;
                    }

                    default:
                        throw new RuntimeException('Unknown action.');
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $msg = $e->getMessage();
                if (stripos($msg, 'duplicate') !== false || $e->getCode() === '23000') {
                    $msg = 'A node with that name already exists under this parent.';
                } elseif (!APP_DEBUG) {
                    $msg = 'Database error.';
                }
                $flash = ['type' => 'danger', 'msg' => $msg];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $flash = ['type' => 'danger', 'msg' => $e->getMessage()];
            }
        }
    }
}

$pageTitle = 'EPC Manager';
include __DIR__ . '/includes/header.php';

$levels     = array_keys(EPC_LEVELS);
$cascadeUrl = e(APP_URL) . '/ajax/epc_cascade.php';
$selfUrl    = e(APP_URL) . '/epc_admin.php';
?>

<style>
  .epc-board       { display:flex; gap:.75rem; overflow-x:auto; padding-bottom:1rem; }
  .epc-col         { flex:0 0 260px; background:#fff; border:1px solid #e2e2e2;
                     border-radius:.5rem; box-shadow:0 1px 2px rgba(0,0,0,.04);
                     display:flex; flex-direction:column; min-height:420px; }
  .epc-col header  { padding:.5rem .75rem; border-bottom:1px solid #eee;
                     font-size:.75rem; text-transform:uppercase;
                     letter-spacing:.08em; color:#666; background:#fafafa;
                     border-radius:.5rem .5rem 0 0;
                     display:flex; justify-content:space-between; align-items:center; }
  .epc-col .body   { flex:1; overflow-y:auto; }
  .epc-col footer  { padding:.5rem .75rem; border-top:1px solid #eee; background:#fafafa;
                     border-radius:0 0 .5rem .5rem; }
  .epc-item        { display:flex; align-items:center; padding:.4rem .5rem .4rem .75rem;
                     border-bottom:1px solid #f1f1f1; cursor:pointer; color:#222;
                     text-decoration:none; font-size:.9rem; gap:.25rem; }
  .epc-item .lbl   { flex:1; }
  .epc-item .acts  { opacity:0; transition:opacity .15s; display:flex; gap:.15rem; }
  .epc-item:hover                  { background:#fff5f6; color:#c8102e; }
  .epc-item:hover .acts            { opacity:1; }
  .epc-item.active                 { background:#c8102e; color:#fff; font-weight:600; }
  .epc-item.active .acts           { opacity:1; }
  .epc-item.active .acts .btn      { color:#fff; border-color:rgba(255,255,255,.5); }
  .epc-item.inactive               { color:#aaa; font-style:italic; }
  .epc-item.inactive.active        { color:#fff; }
  .epc-item .btn                   { --bs-btn-padding-y:.05rem; --bs-btn-padding-x:.35rem;
                                     --bs-btn-font-size:.7rem; }
  .epc-empty, .epc-loading, .epc-err, .epc-leaf-note {
                                     padding:.75rem; font-size:.85rem; color:#888; }
  .epc-err         { color:#b00020; }
  .epc-leaf-note   { font-style:italic; color:#666; }
  .epc-crumbs      { font-size:.95rem; }
  .epc-crumbs .sep { color:#bbb; margin:0 .35rem; }
</style>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-1"><i class="bi bi-diagram-3-fill"></i> EPC Manager</h1>
      <div class="epc-crumbs text-muted" id="epc-breadcrumb">
        <span class="text-muted">Pick a category to start.</span>
      </div>
    </div>
    <div>
      <a class="btn btn-sm btn-outline-dark" href="<?= e(APP_URL) ?>/epc_browse.php">
        <i class="bi bi-eye"></i> Browse view
      </a>
    </div>
  </div>

  <?php if ($flash['msg']): ?>
    <div class="alert alert-<?= e($flash['type']) ?> py-2"><?= e($flash['msg']) ?></div>
  <?php endif; ?>

  <div class="epc-board" id="epc-board">
    <?php foreach ($levels as $i => $lvl):
            $meta = EPC_LEVELS[$lvl]; ?>
      <div class="epc-col" data-level="<?= e($lvl) ?>" data-index="<?= $i ?>">
        <header>
          <span><?= e($meta['plural']) ?></span>
          <span class="text-muted small" data-role="count">&mdash;</span>
        </header>
        <div class="body" data-role="list">
          <?php if ($i === 0): ?>
            <div class="epc-loading">Loading...</div>
          <?php else: ?>
            <div class="epc-empty">&mdash;</div>
          <?php endif; ?>
        </div>
        <footer>
          <form method="post" action="<?= $selfUrl ?>" class="d-flex gap-1" data-role="add-form">
            <input type="hidden" name="csrf"      value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action"    value="add">
            <input type="hidden" name="level"     value="<?= e($lvl) ?>">
            <input type="hidden" name="parent_id" value="" data-role="parent-id">
            <input type="text"   name="name"      class="form-control form-control-sm"
                   placeholder="New <?= e(strtolower($meta['label'])) ?>"
                   <?= $i === 0 ? '' : 'disabled' ?>
                   data-role="add-name" required>
            <button type="submit" class="btn btn-sm btn-danger"
                    <?= $i === 0 ? '' : 'disabled' ?>
                    data-role="add-btn"
                    title="Add">
              <i class="bi bi-plus-lg"></i>
            </button>
          </form>
        </footer>
      </div>
    <?php endforeach; ?>
  </div>

  <p class="text-muted small mt-2 mb-0">
    Hover a row for rename / move / activate-deactivate. Inactive nodes show greyed.
    Deleting a node also deletes everything beneath it (cascade), so we hide that
    button on purpose &mdash; deactivate instead.
  </p>
</div>

<!-- Tiny inline form templates posted on demand by the JS below. -->
<form id="epc-action-form" method="post" action="<?= $selfUrl ?>" style="display:none;">
  <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="">
  <input type="hidden" name="level"  value="">
  <input type="hidden" name="id"     value="">
  <input type="hidden" name="dir"    value="">
  <input type="hidden" name="name"   value="">
</form>

<script>
(function () {
  const CASCADE_URL = <?= json_encode($cascadeUrl . '?include_inactive=1') ?>;
  const LEVELS      = <?= json_encode($levels) ?>;
  const board       = document.getElementById('epc-board');
  const breadcrumb  = document.getElementById('epc-breadcrumb');
  const actionForm  = document.getElementById('epc-action-form');
  const selection   = {};

  function colByLevel(level) {
    return board.querySelector('.epc-col[data-level="' + level + '"]');
  }

  function setAddEnabled(level, parentId) {
    const col   = colByLevel(level);
    const form  = col.querySelector('[data-role="add-form"]');
    const pid   = form.querySelector('[data-role="parent-id"]');
    const name  = form.querySelector('[data-role="add-name"]');
    const btn   = form.querySelector('[data-role="add-btn"]');
    pid.value   = parentId == null ? '' : parentId;
    const ok    = (level === LEVELS[0]) || (parentId != null);
    name.disabled = !ok;
    btn.disabled  = !ok;
    if (!ok) name.value = '';
  }

  function clearFrom(levelIndex) {
    for (let i = levelIndex; i < LEVELS.length; i++) {
      const lvl  = LEVELS[i];
      const col  = colByLevel(lvl);
      const list = col.querySelector('[data-role="list"]');
      list.innerHTML = '<div class="epc-empty">&mdash;</div>';
      col.querySelector('[data-role="count"]').textContent = '—';
      delete selection[lvl];
      setAddEnabled(lvl, null);
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

  function submitAction(fields) {
    Object.keys(fields).forEach(function (k) {
      const el = actionForm.querySelector('[name="' + k + '"]');
      if (el) el.value = fields[k] == null ? '' : fields[k];
    });
    actionForm.submit();
  }

  function loadInto(parentLevel, parentId, targetLevelIndex) {
    const targetLevel = LEVELS[targetLevelIndex];
    const col   = colByLevel(targetLevel);
    const list  = col.querySelector('[data-role="list"]');
    const count = col.querySelector('[data-role="count"]');
    list.innerHTML = '<div class="epc-loading">Loading...</div>';

    const url = new URL(CASCADE_URL, window.location.origin);
    url.searchParams.set('parent_level', parentLevel);
    if (parentId !== null) url.searchParams.set('parent_id', parentId);

    fetch(url.toString(), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) {
          list.innerHTML = '<div class="epc-err">' + escapeHtml(data.error || 'Error') + '</div>';
          count.textContent = '!';
          return;
        }
        if (!data.items || data.items.length === 0) {
          list.innerHTML = '<div class="epc-leaf-note">Empty &mdash; add one below.</div>';
          count.textContent = '0';
          return;
        }
        count.textContent = data.items.length;
        list.innerHTML = '';
        data.items.forEach(function (item) {
          const row = document.createElement('a');
          row.className = 'epc-item' + (item.is_active ? '' : ' inactive');
          row.href = '#';
          row.dataset.id   = item.id;
          row.dataset.name = item.name;

          const lbl = document.createElement('span');
          lbl.className   = 'lbl';
          lbl.textContent = item.name;
          row.appendChild(lbl);

          const acts = document.createElement('span');
          acts.className = 'acts';
          acts.innerHTML =
              '<button type="button" class="btn btn-outline-light" title="Move up"   data-act="up">'    + '<i class="bi bi-arrow-up"></i></button>'
            + '<button type="button" class="btn btn-outline-light" title="Move down" data-act="down">'  + '<i class="bi bi-arrow-down"></i></button>'
            + '<button type="button" class="btn btn-outline-light" title="Rename"    data-act="ren">'   + '<i class="bi bi-pencil"></i></button>'
            + '<button type="button" class="btn btn-outline-light" title="Toggle active" data-act="tog">'+ '<i class="bi bi-power"></i></button>';
          row.appendChild(acts);

          // Click on the label area = drill in.
          lbl.addEventListener('click', function (ev) {
            ev.preventDefault();
            list.querySelectorAll('.epc-item.active').forEach(function (x) { x.classList.remove('active'); });
            row.classList.add('active');
            selection[targetLevel] = { id: item.id, name: item.name };
            clearFrom(targetLevelIndex + 1);
            const childIdx = targetLevelIndex + 1;
            if (childIdx < LEVELS.length) {
              setAddEnabled(LEVELS[childIdx], item.id);
              loadInto(targetLevel, item.id, childIdx);
            }
            renderBreadcrumb();
          });

          // Action buttons.
          acts.addEventListener('click', function (ev) {
            const btn = ev.target.closest('button[data-act]');
            if (!btn) return;
            ev.preventDefault();
            ev.stopPropagation();
            const act = btn.dataset.act;
            if (act === 'up' || act === 'down') {
              submitAction({ action: 'move', level: targetLevel, id: item.id, dir: act });
            } else if (act === 'ren') {
              const next = window.prompt('Rename "' + item.name + '" to:', item.name);
              if (next == null) return;
              const trimmed = next.trim();
              if (trimmed === '' || trimmed === item.name) return;
              submitAction({ action: 'rename', level: targetLevel, id: item.id, name: trimmed });
            } else if (act === 'tog') {
              const verb = item.is_active ? 'deactivate' : 'activate';
              if (!window.confirm('Are you sure you want to ' + verb + ' "' + item.name + '"?')) return;
              submitAction({ action: 'toggle_active', level: targetLevel, id: item.id });
            }
          });

          list.appendChild(row);
        });
      })
      .catch(function (err) {
        list.innerHTML = '<div class="epc-err">Network error: ' + escapeHtml(err.message) + '</div>';
      });
  }

  // Disable child columns initially.
  for (let i = 1; i < LEVELS.length; i++) {
    setAddEnabled(LEVELS[i], null);
  }
  loadInto('root', null, 0);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

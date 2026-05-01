<?php
/**
 * Stage 3 — Suppliers list / search / paginate / add+edit (modal).
 *
 * Owner / admin / manager can add, edit and toggle active.
 * Staff / viewer is read-only (no Add button, no toggle).
 *
 * Same modal pattern as customers_admin.php so the screens feel
 * consistent.
 *
 * Filter: search across name / contact / phone / email.
 * Pagination: 50 rows / page (Stage-3 convention).
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$canEdit = user_has_role('owner', 'admin', 'manager');

$flash    = ['type' => null, 'msg' => null];
$editRow  = null;
$openModal = false;

// ---------- POST handling ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        http_response_code(403);
        $flash = ['type' => 'danger', 'msg' => 'You do not have permission to change suppliers.'];
    } elseif (!csrf_check($_POST['csrf'] ?? null)) {
        $flash = ['type' => 'danger', 'msg' => 'Security token invalid. Please reload the page.'];
    } else {
        $action = $_POST['action'] ?? '';
        $id     = (int) ($_POST['id'] ?? 0);
        try {
            if ($action === 'toggle_active' && $id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE suppliers SET is_active = 1 - is_active WHERE id = :id'
                );
                $stmt->execute([':id' => $id]);
                $flash = ['type' => 'success', 'msg' => 'Supplier active state toggled.'];
            } elseif ($action === 'save') {
                $data = [
                    'name'           => trim((string) ($_POST['name']           ?? '')),
                    'contact_person' => trim((string) ($_POST['contact_person'] ?? '')),
                    'phone'          => trim((string) ($_POST['phone']          ?? '')),
                    'email'          => trim((string) ($_POST['email']          ?? '')),
                    'address'        => trim((string) ($_POST['address']        ?? '')),
                    'payment_terms_days' => (int) ($_POST['payment_terms_days'] ?? 30),
                    'notes'          => trim((string) ($_POST['notes']          ?? '')),
                    'is_active'      => !empty($_POST['is_active']) ? 1 : 0,
                ];
                if ($data['name'] === '') throw new RuntimeException('Name is required.');
                if ($data['payment_terms_days'] < 0)   $data['payment_terms_days'] = 0;
                if ($data['payment_terms_days'] > 365) $data['payment_terms_days'] = 365;

                $params = [
                    ':name'    => $data['name'],
                    ':cp'      => $data['contact_person'] !== '' ? $data['contact_person'] : null,
                    ':phone'   => $data['phone']          !== '' ? $data['phone']          : null,
                    ':email'   => $data['email']          !== '' ? $data['email']          : null,
                    ':address' => $data['address']        !== '' ? $data['address']        : null,
                    ':ptd'     => $data['payment_terms_days'],
                    ':notes'   => $data['notes']          !== '' ? $data['notes']          : null,
                    ':active'  => $data['is_active'],
                ];
                if ($id > 0) {
                    $params[':id'] = $id;
                    $stmt = $pdo->prepare(
                        'UPDATE suppliers SET
                           name=:name, contact_person=:cp, phone=:phone, email=:email,
                           address=:address, payment_terms_days=:ptd, notes=:notes,
                           is_active=:active
                         WHERE id=:id'
                    );
                    $stmt->execute($params);
                    $flash = ['type' => 'success', 'msg' => 'Saved supplier: ' . $data['name']];
                } else {
                    $params[':uid'] = (int) ($_SESSION['user_id'] ?? 0);
                    $stmt = $pdo->prepare(
                        'INSERT INTO suppliers
                         (name, contact_person, phone, email, address,
                          payment_terms_days, notes, is_active, created_by)
                         VALUES
                         (:name, :cp, :phone, :email, :address,
                          :ptd, :notes, :active, :uid)'
                    );
                    $stmt->execute($params);
                    $flash = ['type' => 'success', 'msg' => 'Added supplier: ' . $data['name']];
                }
            } else {
                throw new RuntimeException('Unknown action.');
            }
        } catch (Throwable $e) {
            $flash = ['type' => 'danger', 'msg' => APP_DEBUG ? $e->getMessage() : 'Database error.'];
            $editRow = $_POST + ['id' => $id];
            $openModal = true;
        }
    }
}

// ---------- Edit-link handler (?edit=N) ----------
if (!$openModal && !empty($_GET['edit'])) {
    $eid = (int) $_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE id = :id');
    $stmt->execute([':id' => $eid]);
    $row = $stmt->fetch();
    if ($row) {
        $editRow   = $row;
        $openModal = true;
    }
}

// ---------- Search + pagination ----------
$q          = trim((string) ($_GET['q']      ?? ''));
$showInact  = !empty($_GET['inactive']);
$page       = max(1, (int) ($_GET['page']    ?? 1));
$perPage    = 50;
$offset     = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($q !== '') {
    $where[] = '(name LIKE :q OR contact_person LIKE :q OR phone LIKE :q OR email LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if (!$showInact) {
    $where[] = 'is_active = 1';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM suppliers {$whereSql}");
$totalStmt->execute($params);
$total = (int) $totalStmt->fetchColumn();

$listStmt = $pdo->prepare(
    "SELECT id, name, contact_person, phone, email, payment_terms_days, is_active
       FROM suppliers {$whereSql}
       ORDER BY is_active DESC, name ASC
       LIMIT {$perPage} OFFSET {$offset}"
);
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

$pageTitle = 'Suppliers';
include __DIR__ . '/includes/header.php';

$mv = [
    'id'                 => 0,
    'name'               => '',
    'contact_person'     => '',
    'phone'              => '',
    'email'              => '',
    'address'            => '',
    'payment_terms_days' => 30,
    'notes'              => '',
    'is_active'          => 1,
];
if ($editRow) {
    $mv = array_merge($mv, $editRow);
}
?>

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-1"><i class="bi bi-truck-flatbed"></i> Suppliers</h1>
      <div class="text-muted small">
        <?= number_format($total) ?> result<?= $total === 1 ? '' : 's' ?>
        <?php if ($q !== ''): ?>for "<strong><?= e($q) ?></strong>"<?php endif; ?>
        <?php if ($showInact): ?> &middot; including inactive<?php endif; ?>
      </div>
    </div>
    <div>
      <?php if ($canEdit): ?>
        <button type="button" class="btn btn-sm btn-danger"
                data-bs-toggle="modal" data-bs-target="#supplierModal"
                onclick="document.getElementById('sm-title').textContent='Add supplier';
                         document.getElementById('sm-form').reset();
                         document.querySelector('#supplierModal [name=id]').value='';
                         document.querySelector('#supplierModal [name=is_active]').checked=true;
                         document.querySelector('#supplierModal [name=payment_terms_days]').value=30;">
          <i class="bi bi-plus-lg"></i> Add supplier
        </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($flash['msg']): ?>
    <div class="alert alert-<?= e($flash['type']) ?> py-2"><?= e($flash['msg']) ?></div>
  <?php endif; ?>

  <form method="get" class="row g-2 mb-3">
    <div class="col-md-6">
      <input type="text" name="q" value="<?= e($q) ?>"
             class="form-control form-control-sm"
             placeholder="Search name / contact / phone / email&hellip;">
    </div>
    <div class="col-md-3">
      <div class="form-check form-check-inline mt-1">
        <input class="form-check-input" type="checkbox" id="inactive" name="inactive" value="1"
               <?= $showInact ? 'checked' : '' ?>>
        <label class="form-check-label small" for="inactive">Show inactive</label>
      </div>
    </div>
    <div class="col-md-3 text-end">
      <button class="btn btn-sm btn-outline-dark" type="submit">
        <i class="bi bi-search"></i> Search
      </button>
      <?php if ($q !== '' || $showInact): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(APP_URL) ?>/suppliers_admin.php">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Name</th>
            <th>Contact</th>
            <th>Phone</th>
            <th>Email</th>
            <th class="text-end">Payment terms</th>
            <th>Status</th>
            <?php if ($canEdit): ?><th class="text-end">Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr>
              <td colspan="<?= $canEdit ? 7 : 6 ?>" class="text-center text-muted py-4">
                No suppliers match.
              </td>
            </tr>
          <?php else: foreach ($rows as $s): ?>
            <tr class="<?= $s['is_active'] ? '' : 'text-muted' ?>">
              <td><strong><?= e($s['name']) ?></strong></td>
              <td><?= e($s['contact_person']) ?></td>
              <td><?= e($s['phone']) ?></td>
              <td><?= e($s['email']) ?></td>
              <td class="text-end"><?= (int) $s['payment_terms_days'] ?> days</td>
              <td>
                <?php if ($s['is_active']): ?>
                  <span class="badge bg-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Inactive</span>
                <?php endif; ?>
              </td>
              <?php if ($canEdit): ?>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-dark"
                     href="<?= e(APP_URL) ?>/suppliers_admin.php?edit=<?= (int) $s['id'] ?>"
                     title="Edit">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <form method="post" class="d-inline"
                        onsubmit="return confirm('<?= $s['is_active'] ? 'Deactivate' : 'Activate' ?> this supplier?');">
                    <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="id"     value="<?= (int) $s['id'] ?>">
                    <button class="btn btn-sm btn-outline-secondary" type="submit"
                            title="<?= $s['is_active'] ? 'Deactivate' : 'Activate' ?>">
                      <i class="bi bi-power"></i>
                    </button>
                  </form>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($totalPages > 1): ?>
    <nav class="mt-3">
      <ul class="pagination pagination-sm">
        <?php
          $base = '?' . http_build_query(array_filter([
            'q'        => $q !== '' ? $q : null,
            'inactive' => $showInact ? 1 : null,
          ]));
          $sep  = $base === '?' ? '' : '&';
          for ($p = 1; $p <= $totalPages; $p++):
        ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= e($base . $sep) ?>page=<?= $p ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<?php if ($canEdit): ?>
<!-- Add / Edit supplier modal -->
<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" id="sm-form">
        <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id"     value="<?= (int) $mv['id'] ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="sm-title">
            <?= (int) $mv['id'] > 0 ? 'Edit supplier' : 'Add supplier' ?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-9">
              <label class="form-label small">Name *</label>
              <input class="form-control form-control-sm" name="name" required
                     value="<?= e($mv['name']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small">Payment terms (days)</label>
              <input class="form-control form-control-sm" name="payment_terms_days"
                     type="number" min="0" max="365"
                     value="<?= (int) $mv['payment_terms_days'] ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small">Contact person</label>
              <input class="form-control form-control-sm" name="contact_person"
                     value="<?= e($mv['contact_person']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small">Phone</label>
              <input class="form-control form-control-sm" name="phone"
                     value="<?= e($mv['phone']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small">Email</label>
              <input class="form-control form-control-sm" name="email" type="email"
                     value="<?= e($mv['email']) ?>">
            </div>

            <div class="col-12">
              <label class="form-label small">Address</label>
              <textarea class="form-control form-control-sm" name="address" rows="2"><?= e($mv['address']) ?></textarea>
            </div>

            <div class="col-12">
              <label class="form-label small">Notes</label>
              <input class="form-control form-control-sm" name="notes"
                     value="<?= e($mv['notes']) ?>">
            </div>

            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="sm-active"
                       name="is_active" value="1" <?= $mv['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="sm-active">Active (visible in lists)</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <a class="btn btn-link" href="<?= e(APP_URL) ?>/suppliers_admin.php">Cancel</a>
          <button class="btn btn-danger" type="submit">
            <i class="bi bi-save"></i> Save
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($openModal): ?>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const m = new bootstrap.Modal(document.getElementById('supplierModal'));
    document.getElementById('sm-title').textContent =
      <?= (int) $mv['id'] > 0 ? "'Edit supplier'" : "'Add supplier'" ?>;
    m.show();
  });
</script>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

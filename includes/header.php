<?php
/**
 * Top navigation + page <head>. Include after auth_check.php.
 * Stage 1 ships a minimal nav. Real menu items get wired in stages 2-6.
 */

if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config/config.php';
}

$user = function_exists('current_user') ? current_user() : [
    'username'  => $_SESSION['username']  ?? '',
    'full_name' => $_SESSION['full_name'] ?? '',
    'role'      => $_SESSION['role']      ?? '',
];

$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  body { background:#f5f5f5; }
  .navbar-brand { font-weight:700; letter-spacing:.12em; color:#c8102e !important; }
  .navbar-aw { background:#0a0a0a; border-bottom:3px solid #c8102e; }
  .navbar-aw .nav-link, .navbar-aw .navbar-text { color:#e9e9e9 !important; }
  .navbar-aw .nav-link:hover { color:#c8102e !important; }
  .badge-role { background:#c8102e; }

  /* Forms & card sections — red / black / white (aligned with nav) */
  main .card {
    border: 1px solid rgba(10,10,10,.12);
    border-radius: 0.375rem;
    box-shadow: 0 0.08rem 0.35rem rgba(10,10,10,.07);
  }
  main .card-header.bg-light {
    background: #0a0a0a !important;
    color: #fff !important;
    border-bottom: 3px solid #c8102e !important;
    font-weight: 600;
    font-size: 0.9rem;
    letter-spacing: 0.03em;
  }
  main .card-header.bg-light .text-muted {
    color: rgba(255,255,255,.72) !important;
  }
  /* In-card section titles (e.g. vehicle_edit) — same strip as card-header */
  main .card-body > h2.h6.text-uppercase.text-muted {
    color: #fff !important;
    background: #0a0a0a;
    margin: -1rem -1rem 1rem -1rem;
    padding: 0.65rem 1rem;
    border-bottom: 3px solid #c8102e;
    font-weight: 600;
    letter-spacing: 0.03em;
  }
  main .card-body > h2.h6.text-uppercase.text-muted .bi {
    opacity: 0.9;
  }
  main .form-label {
    font-weight: 500;
    color: #0a0a0a;
  }
  main .form-control,
  main .form-select {
    border-color: rgba(10,10,10,.22);
  }
  main .form-control:focus,
  main .form-select:focus {
    border-color: #c8102e;
    box-shadow: 0 0 0 0.2rem rgba(200, 16, 46, 0.22);
  }
  .modal-header {
    background: #0a0a0a !important;
    color: #fff !important;
    border-bottom: 3px solid #c8102e !important;
  }
  .modal-header .modal-title { font-weight: 600; letter-spacing: .03em; }
  .modal-header .btn-close {
    filter: invert(1) grayscale(1);
    opacity: 0.85;
  }
  .btn-primary {
    --bs-btn-bg: #c8102e;
    --bs-btn-border-color: #c8102e;
    --bs-btn-hover-bg: #a00d25;
    --bs-btn-hover-border-color: #a00d25;
    --bs-btn-active-bg: #8f0b21;
    --bs-btn-active-border-color: #8f0b21;
    --bs-btn-focus-shadow-rgb: 200, 16, 46;
  }
  .btn-danger {
    --bs-btn-bg: #c8102e;
    --bs-btn-border-color: #c8102e;
    --bs-btn-hover-bg: #a00d25;
    --bs-btn-hover-border-color: #a00d25;
    --bs-btn-active-bg: #8f0b21;
    --bs-btn-active-border-color: #8f0b21;
  }
  .btn-outline-primary {
    --bs-btn-color: #c8102e;
    --bs-btn-border-color: #c8102e;
    --bs-btn-hover-bg: #c8102e;
    --bs-btn-hover-border-color: #c8102e;
    --bs-btn-hover-color: #fff;
    --bs-btn-active-bg: #a00d25;
    --bs-btn-active-border-color: #a00d25;
    --bs-btn-active-color: #fff;
  }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-aw px-3">
  <a class="navbar-brand" href="<?= e(APP_URL) ?>/main_dashboard.php">
    <i class="bi bi-truck-front"></i> AUTOWAGEN
  </a>

  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse" id="mainNav">
    <ul class="navbar-nav me-auto">
      <li class="nav-item">
        <a class="nav-link" href="<?= e(APP_URL) ?>/main_dashboard.php">
          <i class="bi bi-speedometer2"></i> Dashboard
        </a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
          <i class="bi bi-diagram-3"></i> EPC
        </a>
        <ul class="dropdown-menu">
          <li>
            <a class="dropdown-item" href="<?= e(APP_URL) ?>/epc_browse.php">
              <i class="bi bi-eye"></i> Browse tree
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="<?= e(APP_URL) ?>/epc_full_tree.php">
              <i class="bi bi-list-nested"></i> Full tree (reference)
            </a>
          </li>
          <?php if (function_exists('user_has_role') && user_has_role('owner', 'admin')): ?>
            <li>
              <a class="dropdown-item" href="<?= e(APP_URL) ?>/epc_admin.php">
                <i class="bi bi-pencil-square"></i> Manage tree
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
          <i class="bi bi-people"></i> Master data
        </a>
        <ul class="dropdown-menu">
          <li>
            <a class="dropdown-item" href="<?= e(APP_URL) ?>/vehicles_admin.php">
              <i class="bi bi-truck"></i> Vehicles
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="<?= e(APP_URL) ?>/customers_admin.php">
              <i class="bi bi-people-fill"></i> Customers
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="<?= e(APP_URL) ?>/suppliers_admin.php">
              <i class="bi bi-truck-flatbed"></i> Suppliers
            </a>
          </li>
        </ul>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
          <i class="bi bi-box-seam"></i> Inventory
        </a>
        <ul class="dropdown-menu">
          <li>
            <a class="dropdown-item" href="<?= e(APP_URL) ?>/parts_admin.php">
              <i class="bi bi-list-ul"></i> All parts
            </a>
          </li>
          <li>
            <a class="dropdown-item" target="_blank" rel="noopener" href="<?= e(APP_URL) ?>/shop/">
              <i class="bi bi-shop"></i> Public shop (website)
            </a>
          </li>
          <li>
            <a class="dropdown-item" target="_blank" rel="noopener" href="<?= e(APP_URL) ?>/shop/stripping/">
              <i class="bi bi-truck-flatbed"></i> Stripping stock (website)
            </a>
          </li>
          <?php if (function_exists('user_has_role') && user_has_role('owner', 'admin', 'manager', 'staff')): ?>
            <li>
              <a class="dropdown-item" href="<?= e(APP_URL) ?>/part_edit.php">
                <i class="bi bi-plus-lg"></i> Add part
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item" href="<?= e(APP_URL) ?>/supplier_purchases_admin.php">
                <i class="bi bi-collection"></i> Supplier purchases
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="<?= e(APP_URL) ?>/supplier_purchase_edit.php">
                <i class="bi bi-plus-square-dotted"></i> New supplier purchase
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
          <i class="bi bi-graph-up-arrow"></i> Reports
        </a>
        <ul class="dropdown-menu">
          <li><span class="dropdown-header">Money owed</span></li>
          <?php if (function_exists('user_has_role') && user_has_role('owner', 'admin', 'manager', 'staff')): ?>
            <li>
              <a class="dropdown-item" href="<?= e(APP_URL) ?>/supplier_ap_report.php">
                <i class="bi bi-wallet2"></i> Accounts payable (owed)
              </a>
            </li>
          <?php endif; ?>
          <li>
            <a class="dropdown-item" href="<?= e(APP_URL) ?>/customer_ar_report.php">
              <i class="bi bi-cash-stack"></i> Accounts receivable (owed)
            </a>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li><span class="dropdown-header">Sales &amp; customers</span></li>
          <li>
            <a class="dropdown-item" href="<?= e(APP_URL) ?>/invoices_admin.php">
              <i class="bi bi-receipt"></i> Sales invoices
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="<?= e(APP_URL) ?>/sales_summary_report.php">
              <i class="bi bi-bar-chart-line"></i> Sales summary (period)
            </a>
          </li>
          <?php if (function_exists('user_has_role') && user_has_role('owner', 'admin', 'manager', 'staff')): ?>
            <li>
              <a class="dropdown-item" href="<?= e(APP_URL) ?>/credit_notes_admin.php">
                <i class="bi bi-arrow-counterclockwise"></i> Credit notes
              </a>
            </li>
          <?php endif; ?>
          <li>
            <a class="dropdown-item" href="<?= e(APP_URL) ?>/customers_admin.php">
              <i class="bi bi-file-earmark-text"></i> Customer statements (Customers list)
            </a>
          </li>
          <?php if (function_exists('user_has_role') && user_has_role('owner', 'admin', 'manager', 'staff')): ?>
            <li><hr class="dropdown-divider"></li>
            <li><span class="dropdown-header">Web shop</span></li>
            <li>
              <a class="dropdown-item" href="<?= e(APP_URL) ?>/shop_orders_admin.php">
                <i class="bi bi-bag-check"></i> Web shop orders
              </a>
            </li>
            <li>
              <a class="dropdown-item" href="<?= e(APP_URL) ?>/shop_enquiries_admin.php">
                <i class="bi bi-chat-dots"></i> Web shop messages (guest enquiries)
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
          <i class="bi bi-cart-check"></i> POS
        </a>
        <ul class="dropdown-menu">
          <li>
            <a class="dropdown-item" href="<?= e(APP_URL) ?>/invoices_admin.php">
              <i class="bi bi-receipt"></i> Sales invoices
            </a>
          </li>
          <?php if (function_exists('user_has_role') && user_has_role('owner', 'admin', 'manager', 'staff')): ?>
            <li>
              <a class="dropdown-item" href="<?= e(APP_URL) ?>/invoice_edit.php?new=1">
                <i class="bi bi-plus-lg"></i> New sale
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </li>
    </ul>

    <ul class="navbar-nav">
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
          <i class="bi bi-person-circle"></i>
          <?= e($user['full_name'] ?: $user['username']) ?>
          <span class="badge badge-role ms-1"><?= e($user['role']) ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><span class="dropdown-item-text small text-muted">
            Signed in as <strong><?= e($user['username']) ?></strong>
          </span></li>
          <li><hr class="dropdown-divider"></li>
          <?php if (function_exists('user_has_role') && user_has_role('owner', 'admin')): ?>
            <li>
              <a class="dropdown-item" href="<?= e(APP_URL) ?>/backups_admin.php">
                <i class="bi bi-archive"></i> Site backup (ZIP)
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
          <?php endif; ?>
          <li><a class="dropdown-item" href="<?= e(APP_URL) ?>/auth/logout.php">
            <i class="bi bi-box-arrow-right"></i> Logout
          </a></li>
        </ul>
      </li>
    </ul>
  </div>
</nav>

<main class="container-fluid py-4">

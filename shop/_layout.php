<?php
declare(strict_types=1);

/** @var string $shopPageTitle */
$shopPageTitle = $shopPageTitle ?? 'Spares';

if (!defined('APP_URL')) {
    require_once dirname(__DIR__) . '/config/config.php';
}
$base = rtrim((string) APP_URL, '/');

function shop_layout_head(string $title): void {
    $full = e($title) . ' · ' . e(APP_NAME);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . $full . '</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">';
    echo '<style>
      body{background:#f5f5f5;}
      .shop-nav{background:#0a0a0a;border-bottom:3px solid #c8102e;}
      .shop-brand{color:#c8102e !important;font-weight:700;letter-spacing:.12em;}
      .shop-nav .nav-link{color:#e9e9e9 !important;}
      .shop-nav .nav-link:hover{color:#c8102e !important;}
      .shop-nav .dropdown-menu-dark .dropdown-item.active,.shop-nav .dropdown-menu-dark .dropdown-item:active{background:#c8102e;}
      .card-product{border:1px solid rgba(10,10,10,.12);overflow:hidden;}
      .card-product .card-img-top{height:180px;object-fit:cover;background:#eaeaea;}
      .price-tag{color:#c8102e;font-weight:700;}
    </style></head><body>';
}

function shop_layout_nav(string $base, int $cartItems): void {
    echo '<nav class="navbar navbar-expand-lg shop-nav navbar-dark">';
    echo '<div class="container">';
    echo '<a class="navbar-brand shop-brand" href="' . e($base) . '/shop/"><i class="bi bi-truck-front"></i> ' . e(APP_NAME) . '</a>';
    echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#shopNav">';
    echo '<span class="navbar-toggler-icon"></span></button>';
    echo '<div class="collapse navbar-collapse" id="shopNav"><ul class="navbar-nav ms-auto">';
    echo '<li class="nav-item"><a class="nav-link" href="' . e($base) . '/shop/">Browse parts</a></li>';
    echo '<li class="nav-item dropdown">';
    echo '<a class="nav-link dropdown-toggle" href="#" id="shopEngDd" role="button" data-bs-toggle="dropdown" aria-expanded="false">Engines</a>';
    echo '<ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" aria-labelledby="shopEngDd">';
    echo '<li><h6 class="dropdown-header text-secondary">Engine category</h6></li>';
    echo '<li><a class="dropdown-item" href="' . e($base) . '/shop/category.php?slug=engine">All engine listings</a></li>';
    echo '<li><hr class="dropdown-divider"></li>';
    echo '<li><a class="dropdown-item" href="' . e($base) . '/shop/category.php?slug=engine&line=parts">Engine parts <span class="text-secondary small">(loose)</span></a></li>';
    echo '<li><a class="dropdown-item" href="' . e($base) . '/shop/category.php?slug=engine&line=complete">Complete engines / sub-units</a></li>';
    echo '<li><a class="dropdown-item" href="' . e($base) . '/shop/category.php?slug=engine&line=heads">Cylinder heads / bare</a></li>';
    echo '</ul></li>';
    echo '<li class="nav-item dropdown">';
    echo '<a class="nav-link dropdown-toggle" href="#" id="shopGbDd" role="button" data-bs-toggle="dropdown" aria-expanded="false">Gearbox</a>';
    echo '<ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" aria-labelledby="shopGbDd">';
    echo '<li><h6 class="dropdown-header text-secondary">Transmission &amp; driveline</h6></li>';
    echo '<li><a class="dropdown-item" href="' . e($base) . '/shop/category.php?slug=transmission-driveline">All gearbox listings</a></li>';
    echo '<li><hr class="dropdown-divider"></li>';
    echo '<li><a class="dropdown-item" href="' . e($base) . '/shop/category.php?slug=transmission-driveline&line=complete">Complete gearbox</a></li>';
    echo '<li><a class="dropdown-item" href="' . e($base) . '/shop/category.php?slug=transmission-driveline&line=parts">Gearbox parts <span class="text-secondary small">(loose)</span></a></li>';
    echo '</ul></li>';
    echo '<li class="nav-item"><a class="nav-link" href="' . e($base) . '/shop/stripping/">Stripping stock</a></li>';
    echo '<li class="nav-item"><a class="nav-link" href="' . e($base) . '/shop/enquiry.php">';
    echo '<i class="bi bi-chat-dots"></i> Message';
    echo '</a></li>';
    echo '<li class="nav-item"><a class="nav-link" href="' . e($base) . '/shop/cart.php">';
    echo '<i class="bi bi-cart3"></i> Cart';
    if ($cartItems > 0) {
        echo ' <span class="badge bg-danger">' . (int) $cartItems . '</span>';
    }
    echo '</a></li>';
    echo '<li class="nav-item"><a class="nav-link" href="' . e($base) . '/auth/login.php">Staff login</a></li>';
    echo '</ul></div></div></nav>';
}

function shop_layout_foot(): void {
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
    echo '</body></html>';
}

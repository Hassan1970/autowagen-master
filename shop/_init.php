<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/shop_helpers.php';

if (!function_exists('e')) {
    function e(?string $v): string {
        return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!defined('SHOP_CART_KEY')) {
    define('SHOP_CART_KEY', 'shop_cart_v1');
}

/** @return array<int,array{qty:int}> part_id => [qty] */
function shop_cart_get(): array {
    return $_SESSION[SHOP_CART_KEY] ?? [];
}

function shop_cart_set(array $c): void {
    $_SESSION[SHOP_CART_KEY] = $c;
}

function shop_cart_count_items(): int {
    $n = 0;
    foreach (shop_cart_get() as $row) {
        $n += max(1, (int) ($row['qty'] ?? 1));
    }
    return $n;
}

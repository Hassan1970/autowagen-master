<?php
/**
 * Stage 6b — Public web shop helpers (inventory-linked).
 *
 * Staff tick **list_online** per part for the public catalogue. Condition / source
 * can be narrowed on the browse page via GET filters (see `shop/`).
 */

declare(strict_types=1);

function shop_tables_ready(PDO $pdo): bool {
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE table_schema = DATABASE() AND table_name = 'shop_orders'"
    )->fetchColumn() > 0;
}

function shop_parts_list_online_ready(PDO $pdo): bool {
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE table_schema = DATABASE() AND table_name = 'parts'
           AND column_name = 'list_online'"
    )->fetchColumn() > 0;
}

/**
 * True when this inventory row may complete **web checkout** (pay later).
 * Third-party take-ins and stripped yard parts are **browse + enquiry only**.
 * **OEM new** and **Replacement** (aftermarket): condition must be
 * **New**, **Good**, or **Fair**. Poor and Scrap stay enquiry-only.
 *
 * @param array<string,mixed> $p
 */
function shop_part_purchasable_online(array $p): bool {
    $p   = array_change_key_case($p, CASE_LOWER);
    $src = (string) ($p['source'] ?? '');
    $cg  = (string) ($p['condition_grade'] ?? '');
    $checkoutGrades = ['new', 'good', 'fair'];
    if (!in_array($cg, $checkoutGrades, true)) {
        return false;
    }
    return $src === 'oem_new' || $src === 'replacement';
}

/**
 * Listed on site but not allowed through guest checkout (staff follows up via enquiry).
 *
 * @param array<string,mixed> $p
 */
function shop_part_enquiry_only(array $p): bool {
    return !shop_part_purchasable_online($p);
}

/**
 * @deprecated Prefer shop_part_purchasable_online().
 * @param array<string,mixed> $p
 */
function shop_part_eligible_for_internet_sale(array $p): bool {
    return shop_part_purchasable_online($p);
}
/** Condition values allowed in catalogue filters / URLs (aligned with parts.condition_grade). */
function shop_catalog_condition_whitelist(): array {
    return ['new', 'good', 'fair', 'poor', 'scrap'];
}

/** Source values allowed in catalogue filters (aligned with parts.source). */
function shop_catalog_source_whitelist(): array {
    return ['stripped', 'oem_new', 'replacement', 'third_party'];
}

/**
 * Validates a single-value filter against whitelists — returns normalized key or ''.
 */
function shop_catalog_normalize_filter(?string $value, array $allowed): string {
    $v = is_string($value) ? trim($value) : '';
    return in_array($v, $allowed, true) ? $v : '';
}

function shop_unit_price_ex_vat(array $p): float {
    $p   = array_change_key_case($p, CASE_LOWER);
    $ask = round((float) ($p['asking_price'] ?? 0), 2);
    $dr  = $p['discount_price'] ?? null;
    if ($dr !== null && $dr !== '' && is_numeric($dr)) {
        $disc = round((float) $dr, 2);
        if ($disc > 0 && $disc < $ask) {
            return $disc;
        }
    }
    return $ask;
}

function shop_part_public_url(int $partId): string {
    return rtrim((string) (defined('APP_URL') ? APP_URL : ''), '/') . '/shop/part.php?id=' . $partId;
}

/**
 * SQL fragment (AND conditions) for catalogue listings — base only.
 * Narrow with optional condition / source filters in `shop/index.php`.
 */
function shop_catalog_base_conditions(): string {
    return "p.is_active = 1 AND p.status = 'available' AND p.qty_on_hand > 0
            AND p.list_online = 1";
}

/**
 * Optional split of Engine category on the public shop (by part name keywords).
 * Staff: use clear titles e.g. "Complete engine — …", "Cylinder head — …", "Radiator …" for loose parts.
 *
 * @return 'parts'|'complete'|'heads'|''
 */
function shop_normalize_engine_line(string $catSlug, string $line): string {
    if ($catSlug !== 'engine') {
        return '';
    }
    $line = strtolower(trim($line));
    if (in_array($line, ['parts', 'complete', 'heads'], true)) {
        return $line;
    }
    return '';
}

/**
 * SQL condition on `p.name` for `shop_normalize_engine_line()` (no bound params).
 */
function shop_engine_line_sql_condition(string $engineLine): ?string {
    if (!in_array($engineLine, ['parts', 'complete', 'heads'], true)) {
        return null;
    }
    $complete = '(LOWER(p.name) LIKE \'%complete engine%\' OR LOWER(p.name) LIKE \'%long block%\' OR LOWER(p.name) LIKE \'%long motor%\''
        . ' OR LOWER(p.name) LIKE \'%full engine%\' OR LOWER(p.name) LIKE \'%engine assembly%\''
        . ' OR LOWER(p.name) LIKE \'%sub unit%\' OR LOWER(p.name) LIKE \'%sub-unit%\')';
    $heads = '(LOWER(p.name) LIKE \'%cylinder head%\' OR LOWER(p.name) LIKE \'%cyl head%\''
        . ' OR LOWER(p.name) LIKE \'%bare head%\' OR LOWER(p.name) LIKE \'%head bare%\')';
    return match ($engineLine) {
        'complete' => $complete,
        'heads'    => $heads,
        'parts'    => 'NOT (' . $complete . ' OR ' . $heads . ')',
        default    => null,
    };
}

/**
 * Gearbox / transmission submenu on the public shop (`slug=transmission-driveline`).
 *
 * @return 'parts'|'complete'|''
 */
function shop_normalize_gearbox_line(string $catSlug, string $line): string {
    if ($catSlug !== 'transmission-driveline') {
        return '';
    }
    $line = strtolower(trim($line));
    return in_array($line, ['parts', 'complete'], true) ? $line : '';
}

/**
 * SQL on `p.name` for gearbox / transmission lanes (keyword split; staff should use clear titles).
 * Loose lane examples: Bell housing, Centre/center casing, Gears, selector, shaft — avoid
 * "complete gearbox" / "gearbox assembly" unless listing a whole unit (complete lane).
 */
function shop_gearbox_line_sql_condition(string $gbLine): ?string {
    if (!in_array($gbLine, ['parts', 'complete'], true)) {
        return null;
    }
    $complete = '(LOWER(p.name) LIKE \'%complete gearbox%\' OR LOWER(p.name) LIKE \'%gearbox complete%\''
        . ' OR LOWER(p.name) LIKE \'%complete transmission%\' OR LOWER(p.name) LIKE \'%transmission complete%\''
        . ' OR LOWER(p.name) LIKE \'%gearbox assembly%\' OR LOWER(p.name) LIKE \'%transmission assembly%\''
        . ' OR LOWER(p.name) LIKE \'%full gearbox%\' OR LOWER(p.name) LIKE \'%full transmission%\''
        . ' OR LOWER(p.name) LIKE \'%complete manual gearbox%\' OR LOWER(p.name) LIKE \'%complete automatic gearbox%\''
        . ' OR LOWER(p.name) LIKE \'%complete auto gearbox%\' OR LOWER(p.name) LIKE \'%gearbox — complete%\''
        . ' OR LOWER(p.name) LIKE \'%gearbox - complete%\')';
    return match ($gbLine) {
        'complete' => $complete,
        'parts'    => 'NOT (' . $complete . ')',
        default    => null,
    };
}

/**
 * Engine / gearbox submenu: normalized key, SQL fragment, page title, family for placeholder copy.
 *
 * @return array{key:string,sql:?string,title:string,family:?string} family is 'engine'|'gearbox'|null
 */
function shop_category_submenu_context(string $catSlug, string $lineRaw, string $catName): array {
    $eng = shop_normalize_engine_line($catSlug, $lineRaw);
    if ($eng !== '') {
        $title = match ($eng) {
            'parts'    => 'Engine parts (loose)',
            'complete' => 'Complete engines / sub-units',
            'heads'    => 'Cylinder heads / bare',
            default    => $catName . ' parts',
        };
        return [
            'key'    => $eng,
            'sql'    => shop_engine_line_sql_condition($eng),
            'title'  => $title,
            'family' => 'engine',
        ];
    }
    $gb = shop_normalize_gearbox_line($catSlug, $lineRaw);
    if ($gb !== '') {
        $title = match ($gb) {
            'parts'    => 'Gearbox parts (loose)',
            'complete' => 'Complete gearboxes',
            default    => $catName . ' parts',
        };
        return [
            'key'    => $gb,
            'sql'    => shop_gearbox_line_sql_condition($gb),
            'title'  => $title,
            'family' => 'gearbox',
        ];
    }
    return [
        'key'    => '',
        'sql'    => null,
        'title'  => $catName . ' parts',
        'family' => null,
    ];
}

/**
 * Active EPC category by slug (for public shop Category browse).
 *
 * @return array{name:string,slug:string}|null
 */
function shop_resolve_epc_category(PDO $pdo, string $slug): ?array {
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT name, slug FROM epc_categories WHERE slug = ? AND is_active = 1 LIMIT 1'
    );
    $st->execute([$slug]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return ['name' => (string) $row['name'], 'slug' => (string) $row['slug']];
}

function shop_next_order_no(PDO $pdo): string {
    $y = (new DateTime('now', new DateTimeZone('Africa/Johannesburg')))->format('Y');
    $prefix = 'WEB-' . $y . '-';
    $st     = $pdo->prepare(
        "SELECT order_no FROM shop_orders WHERE order_no LIKE ? ORDER BY id DESC LIMIT 1"
    );
    $st->execute([$prefix . '%']);
    $last = $st->fetchColumn();
    $n    = 1;
    if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
        $n = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
}

/**
 * Deduct stock for one part line (same semantics as POS finalize).
 */
function shop_apply_part_sale(PDO $pdo, int $partId, int $qty): void {
    $pst = $pdo->prepare(
        "SELECT id, qty_on_hand, status, is_active, sku FROM parts WHERE id = ? FOR UPDATE"
    );
    $pst->execute([$partId]);
    $part = $pst->fetch(PDO::FETCH_ASSOC);
    if (!$part || (int) $part['is_active'] === 0) {
        throw new RuntimeException('Part #' . $partId . ' no longer available.');
    }
    if (($part['status'] ?? '') !== 'available') {
        throw new RuntimeException('Part ' . ($part['sku'] ?? (string) $partId) . ' is not Available.');
    }
    $oh = (int) $part['qty_on_hand'];
    if ($qty > $oh) {
        throw new RuntimeException('Insufficient stock for part ' . ($part['sku'] ?? (string) $partId) . '.');
    }
    $noh = $oh - $qty;
    if ($noh <= 0) {
        $upP = $pdo->prepare("UPDATE parts SET qty_on_hand = 0, status = 'sold' WHERE id = ?");
        $upP->execute([$partId]);
    } else {
        $upP = $pdo->prepare('UPDATE parts SET qty_on_hand = ? WHERE id = ?');
        $upP->execute([$noh, $partId]);
    }
}

/**
 * Restore stock when an order is cancelled (inverse of sale).
 */
function shop_restore_part_sale(PDO $pdo, int $partId, int $qty): void {
    $pst = $pdo->prepare('SELECT id, qty_on_hand, status FROM parts WHERE id = ? FOR UPDATE');
    $pst->execute([$partId]);
    $part = $pst->fetch(PDO::FETCH_ASSOC);
    if (!$part) {
        return;
    }
    $oh  = (int) $part['qty_on_hand'];
    $new = $oh + $qty;
    $st  = (string) ($part['status'] ?? 'available');
    if ($st === 'sold' || $new > 0) {
        $st = 'available';
    }
    $pdo->prepare('UPDATE parts SET qty_on_hand = ?, status = ? WHERE id = ?')
        ->execute([$new, $st, $partId]);
}

/**
 * @param array<int,array{part_id:int,qty:int}> $lines
 * @param array<string,string|null> $customer name, email, phone, shipping_address, notes
 */
function shop_place_order(PDO $pdo, array $lines, array $customer): int {
    if (!$lines) {
        throw new RuntimeException('Cart is empty.');
    }
    $name = trim((string) ($customer['customer_name'] ?? ''));
    $ph   = trim((string) ($customer['phone'] ?? ''));
    if ($name === '' || $ph === '') {
        throw new RuntimeException('Name and phone are required.');
    }
    $pdo->beginTransaction();
    try {
        $computed = [];
        foreach ($lines as $ln) {
            $pid = (int) ($ln['part_id'] ?? 0);
            $qty = max(1, (int) ($ln['qty'] ?? 1));
            if ($pid <= 0) {
                throw new RuntimeException('Invalid cart line.');
            }
            $pst = $pdo->prepare('SELECT * FROM parts WHERE id = ? FOR UPDATE');
            $pst->execute([$pid]);
            $row = $pst->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('Part no longer exists.');
            }
            $row = array_change_key_case($row, CASE_LOWER);
            if ((int) ($row['is_active'] ?? 0) !== 1) {
                throw new RuntimeException('Part ' . ($row['sku'] ?? '') . ' is not available.');
            }
            if ((int) ($row['list_online'] ?? 0) !== 1) {
                throw new RuntimeException('Part ' . ($row['sku'] ?? '') . ' is not listed online.');
            }
            if (($row['status'] ?? '') !== 'available' || (int) ($row['qty_on_hand'] ?? 0) < $qty) {
                throw new RuntimeException('Part ' . ($row['sku'] ?? '') . ' is out of stock or not available.');
            }
            if (!shop_part_purchasable_online($row)) {
                throw new RuntimeException(
                    'Cart contains an enquiry-only part (' . ($row['sku'] ?? '')
                    . '). Online checkout is for OEM new or Replacement parts in New, Good or Fair condition only — remove other lines or use Message / enquiry.'
                );
            }
            $unit = shop_unit_price_ex_vat($row);
            if ($unit < 0) {
                throw new RuntimeException('Invalid price for ' . ($row['sku'] ?? '') . '.');
            }
            $vr  = round((float) ($row['vat_rate'] ?? 0), 2);
            $sub = round($qty * $unit, 2);
            $vat = round($sub * ($vr / 100), 2);
            $tot = round($sub + $vat, 2);
            $computed[] = [
                'part_id'           => $pid,
                'sku_snapshot'      => (string) $row['sku'],
                'name_snapshot'     => (string) $row['name'],
                'qty'               => $qty,
                'unit_price_ex_vat' => $unit,
                'vat_rate'          => $vr,
                'line_subtotal_ex'  => $sub,
                'line_vat'          => $vat,
                'line_total_inc'    => $tot,
            ];
        }

        $sumEx = 0.0;
        $sumV  = 0.0;
        $sumI  = 0.0;
        foreach ($computed as $c) {
            $sumEx += $c['line_subtotal_ex'];
            $sumV  += $c['line_vat'];
            $sumI  += $c['line_total_inc'];
        }
        $sumEx = round($sumEx, 2);
        $sumV  = round($sumV, 2);
        $sumI  = round($sumI, 2);

        $ordNo = shop_next_order_no($pdo);
        $insO  = $pdo->prepare(
            'INSERT INTO shop_orders
             (order_no, status, customer_name, email, phone, shipping_address, notes,
              subtotal_ex_vat, vat_total, total_inc_vat)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $insO->execute([
            $ordNo,
            'confirmed',
            $name,
            trim((string) ($customer['email'] ?? '')) ?: null,
            $ph,
            trim((string) ($customer['shipping_address'] ?? '')) ?: null,
            trim((string) ($customer['notes'] ?? '')) ?: null,
            $sumEx,
            $sumV,
            $sumI,
        ]);
        $oid = (int) $pdo->lastInsertId();

        $insL = $pdo->prepare(
            'INSERT INTO shop_order_lines
             (shop_order_id, part_id, sku_snapshot, name_snapshot, qty,
              unit_price_ex_vat, vat_rate, line_subtotal_ex, line_vat, line_total_inc)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        foreach ($computed as $c) {
            $insL->execute([
                $oid,
                $c['part_id'],
                $c['sku_snapshot'],
                $c['name_snapshot'],
                $c['qty'],
                $c['unit_price_ex_vat'],
                $c['vat_rate'],
                $c['line_subtotal_ex'],
                $c['line_vat'],
                $c['line_total_inc'],
            ]);
            shop_apply_part_sale($pdo, $c['part_id'], $c['qty']);
        }

        $pdo->commit();
        return $oid;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function shop_cancel_order(PDO $pdo, int $orderId): void {
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT id, status FROM shop_orders WHERE id = ? FOR UPDATE');
        $st->execute([$orderId]);
        $o = $st->fetch(PDO::FETCH_ASSOC);
        if (!$o) {
            $pdo->rollBack();
            throw new RuntimeException('Order not found.');
        }
        if (($o['status'] ?? '') === 'cancelled') {
            $pdo->commit();
            return;
        }
        $lines = $pdo->prepare('SELECT part_id, qty FROM shop_order_lines WHERE shop_order_id = ?');
        $lines->execute([$orderId]);
        $rows = $lines->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            shop_restore_part_sale($pdo, (int) $r['part_id'], (int) $r['qty']);
        }
        $pdo->prepare("UPDATE shop_orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Human labels for shop filter UI (keep in sync with `part_edit.php` option lists). */
function shop_filter_source_labels(): array {
    return [
        'stripped'    => 'Stripped',
        'oem_new'     => 'OEM new',
        'replacement' => 'Replacement',
        'third_party' => 'Third party',
    ];
}

function shop_filter_condition_labels(): array {
    return [
        'new'   => 'New',
        'good'  => 'Good',
        'fair'  => 'Fair',
        'poor'  => 'Poor',
        'scrap' => 'Scrap',
    ];
}

/** After `sql/06e_shop_guest_enquiries.sql` on the database. */
function shop_guest_enquiries_ready(PDO $pdo): bool {
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE table_schema = DATABASE() AND table_name = 'shop_guest_enquiries'"
    )->fetchColumn() > 0;
}

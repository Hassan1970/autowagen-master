<?php
/**
 * JSON parts search for the POS "Select item..." modal on invoice_edit.php.
 *
 *   GET ajax/parts_search.php?q=front
 *
 * Returns up to 50 matching parts that are
 *   - is_active = 1
 *   - status    = 'available'
 *   - qty_on_hand > 0
 * matched (LIKE) against name OR sku OR vehicle make / model / year.
 *
 * Response shape:
 *   { "ok": true,
 *     "items": [
 *       { "id": 12, "sku": "AW202", "name": "Front Brake Pad",
 *         "source": "stripped", "source_label": "STRIP",
 *         "vehicle": "TOYOTA HILUX 2018",
 *         "asking_price": 750.00, "vat_rate": 15.00, "qty_on_hand": 4 },
 *       ...
 *     ],
 *     "count": 8 }
 *
 *   { "ok": false, "error": "human readable" }
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

function ps_fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));

// Map DB enum -> short label shown in the UI badge.
// Kept here so the front-end stays simple and only needs to colour by source.
$labelMap = [
    'stripped'    => 'STRIP',
    'oem_new'     => 'OEM',
    'third_party' => 'THIRD PARTY',
    'replacement' => 'REPLACEMENT',
];

$where  = ["p.is_active = 1", "p.status = 'available'", "p.qty_on_hand > 0"];
$params = [];

if ($q !== '') {
    /* One placeholder name = one bind. Re-using :q five times triggers HY093 on native PDO. */
    $where[] = '(p.name LIKE :q1 OR p.sku LIKE :q2 OR v.make LIKE :q3 OR v.model LIKE :q4 OR CAST(v.year AS CHAR) LIKE :q5)';
    $needle = '%' . $q . '%';
    $params[':q1'] = $needle;
    $params[':q2'] = $needle;
    $params[':q3'] = $needle;
    $params[':q4'] = $needle;
    $params[':q5'] = $needle;
}

$sql = "
    SELECT p.id, p.sku, p.name, p.source,
           p.asking_price, p.vat_rate, p.qty_on_hand,
           v.make AS v_make, v.model AS v_model, v.year AS v_year
      FROM parts p
      LEFT JOIN vehicles v ON v.id = p.vehicle_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY p.name ASC, p.sku ASC
     LIMIT 50
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    ps_fail(APP_DEBUG ? $e->getMessage() : 'Database error.', 500);
}

$items = [];
foreach ($rows as $r) {
    $vehicle = '';
    if (!empty($r['v_make']) || !empty($r['v_model']) || !empty($r['v_year'])) {
        $vehicle = trim(
            strtoupper((string) $r['v_make'])
            . ' ' . strtoupper((string) $r['v_model'])
            . ' ' . (string) $r['v_year']
        );
        $vehicle = preg_replace('/\s+/', ' ', $vehicle);
    }
    $items[] = [
        'id'           => (int) $r['id'],
        'sku'          => (string) $r['sku'],
        'name'         => (string) $r['name'],
        'source'       => (string) $r['source'],
        'source_label' => $labelMap[$r['source']] ?? strtoupper((string) $r['source']),
        'vehicle'      => $vehicle,
        'asking_price' => round((float) $r['asking_price'], 2),
        'vat_rate'     => round((float) $r['vat_rate'], 2),
        'qty_on_hand'  => (int) $r['qty_on_hand'],
    ];
}

echo json_encode([
    'ok'    => true,
    'items' => $items,
    'count' => count($items),
]);

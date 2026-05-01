<?php
/**
 * Single JSON endpoint that returns the children of any node in the
 * 6-level EPC tree.
 *
 *   GET ajax/epc_cascade.php?parent_level=root
 *       -> list of categories
 *
 *   GET ajax/epc_cascade.php?parent_level=category&parent_id=1
 *       -> subcategories of category #1
 *
 *   GET ajax/epc_cascade.php?parent_level=subcategory&parent_id=4
 *       -> types of subcategory #4
 *   ... and so on down to: parent_level=component  -> variants
 *
 * Optional: &include_inactive=1  (admin page sets this so deactivated
 * nodes still appear in the picker).
 *
 * Response shape:
 *   { "ok": true,  "level": "subcategory",
 *     "items": [ { "id": 1, "name": "...", "slug": "...",
 *                  "sort_order": 10, "is_active": 1 }, ... ] }
 *   { "ok": false, "error": "human readable message" }
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/epc_helpers.php';

header('Content-Type: application/json; charset=utf-8');

function json_fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

$parentLevel     = $_GET['parent_level']     ?? 'root';
$parentId        = isset($_GET['parent_id']) ? (int) $_GET['parent_id'] : 0;
$includeInactive = !empty($_GET['include_inactive']);

if ($parentLevel === 'root') {
    $childLevelName = 'category';
} else {
    if (!epc_level($parentLevel)) {
        json_fail("Unknown parent_level: {$parentLevel}");
    }
    $childLevelName = epc_child_level($parentLevel);
    if ($childLevelName === null) {
        echo json_encode(['ok' => true, 'level' => null, 'items' => []]);
        exit;
    }
    if ($parentId <= 0) {
        json_fail('parent_id is required when parent_level is not "root".');
    }
}

$child = epc_level($childLevelName);

$sql = "SELECT id, name, slug, sort_order, is_active FROM `{$child['table']}`";
$params = [];
$where  = [];

if ($child['parent_col'] !== null) {
    $where[]            = "`{$child['parent_col']}` = :pid";
    $params[':pid']     = $parentId;
}
if (!$includeInactive) {
    $where[] = '`is_active` = 1';
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY sort_order ASC, name ASC';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    json_fail(
        APP_DEBUG ? $e->getMessage() : 'Database error.',
        500
    );
}

foreach ($rows as &$r) {
    $r['id']         = (int) $r['id'];
    $r['sort_order'] = (int) $r['sort_order'];
    $r['is_active']  = (int) $r['is_active'];
}
unset($r);

echo json_encode([
    'ok'    => true,
    'level' => $childLevelName,
    'items' => $rows,
]);

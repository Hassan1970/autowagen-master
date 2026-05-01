<?php
/**
 * Shared EPC helpers.
 *   - EPC_LEVELS           : ordered list of the six tree levels with metadata
 *   - epc_level($name)     : look up a single level (or null)
 *   - epc_child_level($n)  : the level *below* $n (or null if leaf/invalid)
 *   - epc_slugify($text)   : URL-safe slug
 *   - epc_next_sort($pdo, $level, $parentId)
 *                          : suggested sort_order for a new node
 *
 * The level metadata is the single source of truth used by both
 * ajax/epc_cascade.php and epc_admin.php.
 */

const EPC_LEVELS = [
    'category' => [
        'label'        => 'Category',
        'plural'       => 'Categories',
        'table'        => 'epc_categories',
        'parent'       => null,
        'parent_col'   => null,
    ],
    'subcategory' => [
        'label'        => 'Subcategory',
        'plural'       => 'Subcategories',
        'table'        => 'epc_subcategories',
        'parent'       => 'category',
        'parent_col'   => 'category_id',
    ],
    'type' => [
        'label'        => 'Type',
        'plural'       => 'Types',
        'table'        => 'epc_types',
        'parent'       => 'subcategory',
        'parent_col'   => 'subcategory_id',
    ],
    'subsystem' => [
        'label'        => 'Subsystem',
        'plural'       => 'Subsystems',
        'table'        => 'epc_subsystems',
        'parent'       => 'type',
        'parent_col'   => 'type_id',
    ],
    'component' => [
        'label'        => 'Component',
        'plural'       => 'Components',
        'table'        => 'epc_components',
        'parent'       => 'subsystem',
        'parent_col'   => 'subsystem_id',
    ],
    'variant' => [
        'label'        => 'Variant',
        'plural'       => 'Variants',
        'table'        => 'epc_variants',
        'parent'       => 'component',
        'parent_col'   => 'component_id',
    ],
];

function epc_level(string $name): ?array {
    return EPC_LEVELS[$name] ?? null;
}

function epc_child_level(string $name): ?string {
    $names = array_keys(EPC_LEVELS);
    $i = array_search($name, $names, true);
    if ($i === false || $i === count($names) - 1) {
        return null;
    }
    return $names[$i + 1];
}

function epc_slugify(string $text): string {
    $text = trim($text);
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    if (function_exists('iconv')) {
        $converted = @iconv('utf-8', 'us-ascii//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    $text = strtolower($text);
    $text = preg_replace('~[^a-z0-9-]+~', '', $text);
    $text = preg_replace('~-+~', '-', $text);
    $text = trim($text, '-');
    return $text === '' ? 'item' : substr($text, 0, 180);
}

function epc_next_sort(PDO $pdo, string $levelName, ?int $parentId): int {
    $level = epc_level($levelName);
    if (!$level) {
        return 10;
    }
    $sql = "SELECT COALESCE(MAX(sort_order), 0) FROM `{$level['table']}`";
    $params = [];
    if ($level['parent_col'] !== null) {
        $sql .= " WHERE `{$level['parent_col']}` = :pid";
        $params[':pid'] = (int) $parentId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return ((int) $stmt->fetchColumn()) + 10;
}

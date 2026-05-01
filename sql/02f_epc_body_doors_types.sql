-- =====================================================================
-- Autowagen Master — EPC — Body → Doors & tailgate — real Level 3 Types
--
-- ChatGPT-style part families become our **Type** (level 3) under:
--   Category **body** → Subcategory **doors-tailgate** (from 02c).
-- Under each Type we add a short spine: Parts grouping → All items →
-- OEM, Aftermarket, Used / take-off, Scrap (same idea as 02d + 02e).
--
-- You may still see **General** under Doors (from 02d); deactivate it in
-- epc_admin if you only want the list below.
--
-- Run after: 02b, 02c, 02d (02e optional). Re-run safe (upsert / NOT EXISTS).
-- =====================================================================

-- --- Level 3: Types (your “L3” list) --------------------------------
INSERT INTO `epc_types` (`subcategory_id`, `name`, `slug`, `sort_order`)
SELECT sc.`id`, 'Door shell', 'door-shell', 20
FROM `epc_subcategories` sc
INNER JOIN `epc_categories` cat ON cat.`id` = sc.`category_id`
WHERE cat.`slug` = 'body' AND sc.`slug` = 'doors-tailgate' AND sc.`is_active` = 1
LIMIT 1
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `sort_order` = VALUES(`sort_order`);

INSERT INTO `epc_types` (`subcategory_id`, `name`, `slug`, `sort_order`)
SELECT sc.`id`, 'Door handle (inner / outer)', 'door-handle', 30
FROM `epc_subcategories` sc
INNER JOIN `epc_categories` cat ON cat.`id` = sc.`category_id`
WHERE cat.`slug` = 'body' AND sc.`slug` = 'doors-tailgate' AND sc.`is_active` = 1
LIMIT 1
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `sort_order` = VALUES(`sort_order`);

INSERT INTO `epc_types` (`subcategory_id`, `name`, `slug`, `sort_order`)
SELECT sc.`id`, 'Door lock', 'door-lock', 40
FROM `epc_subcategories` sc
INNER JOIN `epc_categories` cat ON cat.`id` = sc.`category_id`
WHERE cat.`slug` = 'body' AND sc.`slug` = 'doors-tailgate' AND sc.`is_active` = 1
LIMIT 1
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `sort_order` = VALUES(`sort_order`);

INSERT INTO `epc_types` (`subcategory_id`, `name`, `slug`, `sort_order`)
SELECT sc.`id`, 'Window regulator', 'window-regulator', 50
FROM `epc_subcategories` sc
INNER JOIN `epc_categories` cat ON cat.`id` = sc.`category_id`
WHERE cat.`slug` = 'body' AND sc.`slug` = 'doors-tailgate' AND sc.`is_active` = 1
LIMIT 1
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `sort_order` = VALUES(`sort_order`);

INSERT INTO `epc_types` (`subcategory_id`, `name`, `slug`, `sort_order`)
SELECT sc.`id`, 'Door glass', 'door-glass', 60
FROM `epc_subcategories` sc
INNER JOIN `epc_categories` cat ON cat.`id` = sc.`category_id`
WHERE cat.`slug` = 'body' AND sc.`slug` = 'doors-tailgate' AND sc.`is_active` = 1
LIMIT 1
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `sort_order` = VALUES(`sort_order`);

INSERT INTO `epc_types` (`subcategory_id`, `name`, `slug`, `sort_order`)
SELECT sc.`id`, 'Door hinges', 'door-hinges', 70
FROM `epc_subcategories` sc
INNER JOIN `epc_categories` cat ON cat.`id` = sc.`category_id`
WHERE cat.`slug` = 'body' AND sc.`slug` = 'doors-tailgate' AND sc.`is_active` = 1
LIMIT 1
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `sort_order` = VALUES(`sort_order`);

INSERT INTO `epc_types` (`subcategory_id`, `name`, `slug`, `sort_order`)
SELECT sc.`id`, 'Door seal', 'door-seal', 80
FROM `epc_subcategories` sc
INNER JOIN `epc_categories` cat ON cat.`id` = sc.`category_id`
WHERE cat.`slug` = 'body' AND sc.`slug` = 'doors-tailgate' AND sc.`is_active` = 1
LIMIT 1
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `sort_order` = VALUES(`sort_order`);

-- --- Levels 4–6: spine for these types only -------------------------
INSERT INTO `epc_subsystems` (`type_id`, `name`, `slug`, `sort_order`)
SELECT t.`id`, 'Parts grouping', 'parts-group', 10
FROM `epc_types` t
INNER JOIN `epc_subcategories` sc ON sc.`id` = t.`subcategory_id`
INNER JOIN `epc_categories` cat ON cat.`id` = sc.`category_id`
WHERE cat.`slug` = 'body' AND sc.`slug` = 'doors-tailgate'
  AND t.`slug` IN (
    'door-shell', 'door-handle', 'door-lock', 'window-regulator',
    'door-glass', 'door-hinges', 'door-seal'
  )
  AND t.`is_active` = 1
  AND NOT EXISTS (
    SELECT 1 FROM `epc_subsystems` s
    WHERE s.`type_id` = t.`id` AND s.`is_active` = 1
  );

INSERT INTO `epc_components` (`subsystem_id`, `name`, `slug`, `sort_order`)
SELECT s.`id`, 'All items', 'all-items', 10
FROM `epc_subsystems` s
INNER JOIN `epc_types` t ON t.`id` = s.`type_id`
INNER JOIN `epc_subcategories` sc ON sc.`id` = t.`subcategory_id`
INNER JOIN `epc_categories` cat ON cat.`id` = sc.`category_id`
WHERE cat.`slug` = 'body' AND sc.`slug` = 'doors-tailgate'
  AND t.`slug` IN (
    'door-shell', 'door-handle', 'door-lock', 'window-regulator',
    'door-glass', 'door-hinges', 'door-seal'
  )
  AND s.`is_active` = 1
  AND NOT EXISTS (
    SELECT 1 FROM `epc_components` c2
    WHERE c2.`subsystem_id` = s.`id` AND c2.`is_active` = 1
  );

INSERT INTO `epc_variants` (`component_id`, `name`, `slug`, `sort_order`)
SELECT c.`id`, 'OEM', 'oem', 10
FROM `epc_components` c
INNER JOIN `epc_subsystems` s ON s.`id` = c.`subsystem_id`
INNER JOIN `epc_types` t ON t.`id` = s.`type_id`
INNER JOIN `epc_subcategories` sc ON sc.`id` = t.`subcategory_id`
INNER JOIN `epc_categories` cat ON cat.`id` = sc.`category_id`
WHERE cat.`slug` = 'body' AND sc.`slug` = 'doors-tailgate'
  AND t.`slug` IN (
    'door-shell', 'door-handle', 'door-lock', 'window-regulator',
    'door-glass', 'door-hinges', 'door-seal'
  )
  AND c.`slug` = 'all-items'
  AND c.`is_active` = 1
  AND NOT EXISTS (
    SELECT 1 FROM `epc_variants` v WHERE v.`component_id` = c.`id` AND v.`is_active` = 1
  );

INSERT INTO `epc_variants` (`component_id`, `name`, `slug`, `sort_order`)
SELECT c.`id`, 'Aftermarket', 'aftermarket', 20
FROM `epc_components` c
INNER JOIN `epc_subsystems` s ON s.`id` = c.`subsystem_id`
INNER JOIN `epc_types` t ON t.`id` = s.`type_id`
INNER JOIN `epc_subcategories` sc ON sc.`id` = t.`subcategory_id`
INNER JOIN `epc_categories` cat ON cat.`id` = sc.`category_id`
WHERE cat.`slug` = 'body' AND sc.`slug` = 'doors-tailgate'
  AND t.`slug` IN (
    'door-shell', 'door-handle', 'door-lock', 'window-regulator',
    'door-glass', 'door-hinges', 'door-seal'
  )
  AND c.`slug` = 'all-items'
  AND EXISTS (
    SELECT 1 FROM `epc_variants` v
    WHERE v.`component_id` = c.`id` AND v.`slug` = 'oem'               AND v.`is_active` = 1
  )
  AND NOT EXISTS (
    SELECT 1 FROM `epc_variants` v2
    WHERE v2.`component_id` = c.`id` AND v2.`slug` = 'aftermarket' AND v2.`is_active` = 1
  );

INSERT INTO `epc_variants` (`component_id`, `name`, `slug`, `sort_order`)
SELECT c.`id`, 'Used / take-off', 'used-take-off', 30
FROM `epc_components` c
INNER JOIN `epc_subsystems` s ON s.`id` = c.`subsystem_id`
INNER JOIN `epc_types` t ON t.`id` = s.`type_id`
INNER JOIN `epc_subcategories` sc ON sc.`id` = t.`subcategory_id`
INNER JOIN `epc_categories` cat ON cat.`id` = sc.`category_id`
WHERE cat.`slug` = 'body' AND sc.`slug` = 'doors-tailgate'
  AND t.`slug` IN (
    'door-shell', 'door-handle', 'door-lock', 'window-regulator',
    'door-glass', 'door-hinges', 'door-seal'
  )
  AND c.`slug` = 'all-items'
  AND EXISTS (
    SELECT 1 FROM `epc_variants` v
    WHERE v.`component_id` = c.`id` AND v.`slug` = 'aftermarket'       AND v.`is_active` = 1
  )
  AND NOT EXISTS (
    SELECT 1 FROM `epc_variants` v2
    WHERE v2.`component_id` = c.`id` AND v2.`slug` = 'used-take-off' AND v2.`is_active` = 1
  );

INSERT INTO `epc_variants` (`component_id`, `name`, `slug`, `sort_order`)
SELECT c.`id`, 'Scrap', 'scrap', 40
FROM `epc_components` c
INNER JOIN `epc_subsystems` s ON s.`id` = c.`subsystem_id`
INNER JOIN `epc_types` t ON t.`id` = s.`type_id`
INNER JOIN `epc_subcategories` sc ON sc.`id` = t.`subcategory_id`
INNER JOIN `epc_categories` cat ON cat.`id` = sc.`category_id`
WHERE cat.`slug` = 'body' AND sc.`slug` = 'doors-tailgate'
  AND t.`slug` IN (
    'door-shell', 'door-handle', 'door-lock', 'window-regulator',
    'door-glass', 'door-hinges', 'door-seal'
  )
  AND c.`slug` = 'all-items'
  AND EXISTS (
    SELECT 1 FROM `epc_variants` v
    WHERE v.`component_id` = c.`id` AND v.`slug` = 'used-take-off'       AND v.`is_active` = 1
  )
  AND NOT EXISTS (
    SELECT 1 FROM `epc_variants` v2
    WHERE v2.`component_id` = c.`id` AND v2.`slug` = 'scrap'             AND v2.`is_active` = 1
  );

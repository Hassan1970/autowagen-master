-- =====================================================================
-- EPC integrity check — READ ONLY (no INSERT/UPDATE/DELETE).
-- Run on database `autowagen_master` → SQL tab → Go.
--
-- How to read results:
--   • **count / summary** rows = quick health snapshot.
--   • **problem** sections: **0 rows** = good. **Any rows** = fix (missing spine / orphan).
--
-- After full expansion (02b→02c→02d→02g→02h→02e optional), ballpark:
--   categories 12 | subcategories ~76 | types ~400+ | subsystems ~ types
--   (seed tree adds a few extra subsystems under Petrol/Brakes).
-- =====================================================================

-- --- A) Core row counts (active rows only where is_active exists) -----
SELECT 'A1) epc_categories active' AS `metric`, COUNT(*) AS `n` FROM `epc_categories` WHERE `is_active` = 1;
SELECT 'A2) epc_subcategories active' AS `metric`, COUNT(*) AS `n` FROM `epc_subcategories` WHERE `is_active` = 1;
SELECT 'A3) epc_types active' AS `metric`, COUNT(*) AS `n` FROM `epc_types` WHERE `is_active` = 1;
SELECT 'A4) epc_subsystems active' AS `metric`, COUNT(*) AS `n` FROM `epc_subsystems` WHERE `is_active` = 1;
SELECT 'A5) epc_components active' AS `metric`, COUNT(*) AS `n` FROM `epc_components` WHERE `is_active` = 1;
SELECT 'A6) epc_variants active' AS `metric`, COUNT(*) AS `n` FROM `epc_variants` WHERE `is_active` = 1;

-- --- B) Foreign orphans (should return **no rows**) --------------------
SELECT 'B1) PROBLEM: epc_types with missing subcategory' AS `issue`, t.`id`, t.`slug`
FROM `epc_types` t
LEFT JOIN `epc_subcategories` sc ON sc.`id` = t.`subcategory_id`
WHERE sc.`id` IS NULL;

SELECT 'B2) PROBLEM: epc_subsystems with missing type' AS `issue`, s.`id`, s.`slug`
FROM `epc_subsystems` s
LEFT JOIN `epc_types` ty ON ty.`id` = s.`type_id`
WHERE ty.`id` IS NULL;

SELECT 'B3) PROBLEM: epc_components with missing subsystem' AS `issue`, c.`id`, c.`slug`
FROM `epc_components` c
LEFT JOIN `epc_subsystems` s ON s.`id` = c.`subsystem_id`
WHERE s.`id` IS NULL;

SELECT 'B4) PROBLEM: epc_variants with missing component' AS `issue`, v.`id`, v.`slug`
FROM `epc_variants` v
LEFT JOIN `epc_components` c ON c.`id` = v.`component_id`
WHERE c.`id` IS NULL;

SELECT 'B5) PROBLEM: epc_subcategories with missing category' AS `issue`, sc.`id`, sc.`slug`
FROM `epc_subcategories` sc
LEFT JOIN `epc_categories` c ON c.`id` = sc.`category_id`
WHERE c.`id` IS NULL;

-- --- C) Hierarchy gaps (should return **no rows** after 02d + 02g + 02h)-
SELECT 'C1) PROBLEM: subcategory has zero active types' AS `issue`,
       c.`slug` AS `category`, sc.`slug` AS `subcategory`, sc.`name`
FROM `epc_subcategories` sc
INNER JOIN `epc_categories` c ON c.`id` = sc.`category_id`
WHERE sc.`is_active` = 1 AND c.`is_active` = 1
  AND NOT EXISTS (
    SELECT 1 FROM `epc_types` t
    WHERE t.`subcategory_id` = sc.`id` AND t.`is_active` = 1
  );

SELECT 'C2) PROBLEM: type has zero active subsystems' AS `issue`,
       c.`slug` AS `category`, sc.`slug` AS `subcategory`, t.`slug` AS `type_slug`, t.`name`
FROM `epc_types` t
INNER JOIN `epc_subcategories` sc ON sc.`id` = t.`subcategory_id`
INNER JOIN `epc_categories` c ON c.`id` = sc.`category_id`
WHERE t.`is_active` = 1
  AND NOT EXISTS (
    SELECT 1 FROM `epc_subsystems` s
    WHERE s.`type_id` = t.`id` AND s.`is_active` = 1
  );

SELECT 'C3) PROBLEM: subsystem has zero active components' AS `issue`,
       s.`id` AS `subsystem_id`, s.`slug` AS `subsystem_slug`, s.`name`
FROM `epc_subsystems` s
WHERE s.`is_active` = 1
  AND NOT EXISTS (
    SELECT 1 FROM `epc_components` c
    WHERE c.`subsystem_id` = s.`id` AND c.`is_active` = 1
  );

SELECT 'C4) PROBLEM: component has zero active variants' AS `issue`,
       c.`id` AS `component_id`, c.`slug` AS `component_slug`, c.`name`
FROM `epc_components` c
WHERE c.`is_active` = 1
  AND NOT EXISTS (
    SELECT 1 FROM `epc_variants` v
    WHERE v.`component_id` = c.`id` AND v.`is_active` = 1
  );

-- --- D) View compiles (fails loudly if schema broken) -----------------
SELECT 'D1) epc_full_view sample (OK if returns 1 row, n<=5)' AS `metric`, COUNT(*) AS `n`
FROM ( SELECT 1 FROM `epc_full_view` LIMIT 5 ) x;

-- =====================================================================
-- EPC progress check — READ ONLY (no changes). Run on `autowagen_master`.
-- Compare results to the “what to expect” comments below.
-- =====================================================================

-- Step 0 (Stage 2 original): `02_epc.sql` — expect at least 3 categories
--   (engine, brakes, body) before 02b; after 02b expect **12** categories.
SELECT '1) Active categories (expect 12 after Step 1 / 02b)' AS `check`,
       COUNT(*) AS `count`
FROM `epc_categories`
WHERE `is_active` = 1;

SELECT `sort_order`, `slug`, `name`
FROM `epc_categories`
WHERE `is_active` = 1
ORDER BY `sort_order`;

-- Step 2: `02c_epc_subcategories_step2.sql` adds **72** rows if all 12 parents exist.
-- Original seed has **4** subcategories (petrol, diesel, hydraulic, exterior).
-- So after 02c you expect about **76** active subcategories (4 + 72).
SELECT '2) Active subcategories (expect ~76 after Step 2 / 02c; ~4 if only seed)' AS `check`,
       COUNT(*) AS `count`
FROM `epc_subcategories`
WHERE `is_active` = 1;

-- Subcategory count per major category (spot missing parents / partial 02c).
SELECT c.`sort_order`, c.`slug` AS `category_slug`, COUNT(sc.`id`) AS `subcategories_here`
FROM `epc_categories` c
LEFT JOIN `epc_subcategories` sc
  ON sc.`category_id` = c.`id` AND sc.`is_active` = 1
WHERE c.`is_active` = 1
GROUP BY c.`id`, c.`sort_order`, c.`slug`
ORDER BY c.`sort_order`;

-- Levels 3–6: After `02d_epc_spine_step3.sql`, counts jump (many Types / Subsystems /
-- Components / Variants). Before Step 3, typical seed-only: types ≈ 3, subsystems ≈ 3,
-- components ≈ 3, variants ≈ 4.
SELECT '3) epc_types (≈3 seed only; many more after Step 3 / 02d)' AS `check`, COUNT(*) AS `count` FROM `epc_types` WHERE `is_active` = 1;
SELECT '4) epc_subsystems (same note)' AS `check`, COUNT(*) AS `count` FROM `epc_subsystems` WHERE `is_active` = 1;
SELECT '5) epc_components (same note)' AS `check`, COUNT(*) AS `count` FROM `epc_components` WHERE `is_active` = 1;
SELECT '6) epc_variants (same note)' AS `check`, COUNT(*) AS `count` FROM `epc_variants` WHERE `is_active` = 1;

-- =====================================================================
-- Autowagen Master — EPC Step 3 — Levels 3–6 “spine” (fill empty branches)
--
-- After Step 2 you have many **subcategories** but most have no Types yet.
-- This script adds the **minimum** depth so **every leaf path** can reach a
-- variant in the browser / cascade — without deleting your seed tree.
--
-- Rules (idempotent, safe to re-run):
--   • Subcategory with **no** Type → add one Type: **General** (slug `general`)
--   • Type with **no** Subsystem → add **Parts grouping** (`parts-group`)
--   • Subsystem with **no** Component → add **All items** (`all-items`)
--   • Component with **no** Variant → add **OEM** then **Aftermarket**
--
-- Existing seed paths (e.g. Petrol → Inline 4 → Cooling → Radiator) are
-- **skipped** because they already have children.
--
-- Run on `autowagen_master` after: `02_epc.sql` + `02b` + `02c`.
-- =====================================================================

-- Level 3 — Types
INSERT INTO `epc_types` (`subcategory_id`, `name`, `slug`, `sort_order`)
SELECT sc.`id`, 'General', 'general', 10
FROM `epc_subcategories` sc
WHERE sc.`is_active` = 1
  AND NOT EXISTS (
    SELECT 1 FROM `epc_types` t
    WHERE t.`subcategory_id` = sc.`id` AND t.`is_active` = 1
  );

-- Level 4 — Subsystems
INSERT INTO `epc_subsystems` (`type_id`, `name`, `slug`, `sort_order`)
SELECT t.`id`, 'Parts grouping', 'parts-group', 10
FROM `epc_types` t
WHERE t.`is_active` = 1
  AND NOT EXISTS (
    SELECT 1 FROM `epc_subsystems` s
    WHERE s.`type_id` = t.`id` AND s.`is_active` = 1
  );

-- Level 5 — Components
INSERT INTO `epc_components` (`subsystem_id`, `name`, `slug`, `sort_order`)
SELECT s.`id`, 'All items', 'all-items', 10
FROM `epc_subsystems` s
WHERE s.`is_active` = 1
  AND NOT EXISTS (
    SELECT 1 FROM `epc_components` c
    WHERE c.`subsystem_id` = s.`id` AND c.`is_active` = 1
  );

-- Level 6 — Variants (OEM first)
INSERT INTO `epc_variants` (`component_id`, `name`, `slug`, `sort_order`)
SELECT c.`id`, 'OEM', 'oem', 10
FROM `epc_components` c
WHERE c.`is_active` = 1
  AND NOT EXISTS (
    SELECT 1 FROM `epc_variants` v
    WHERE v.`component_id` = c.`id` AND v.`is_active` = 1
  );

-- Aftermarket where OEM exists but Aftermarket not yet
INSERT INTO `epc_variants` (`component_id`, `name`, `slug`, `sort_order`)
SELECT c.`id`, 'Aftermarket', 'aftermarket', 20
FROM `epc_components` c
WHERE c.`is_active` = 1
  AND EXISTS (
    SELECT 1 FROM `epc_variants` v
    WHERE v.`component_id` = c.`id` AND v.`slug` = 'oem' AND v.`is_active` = 1
  )
  AND NOT EXISTS (
    SELECT 1 FROM `epc_variants` v2
    WHERE v2.`component_id` = c.`id` AND v2.`slug` = 'aftermarket' AND v2.`is_active` = 1
  );

-- Expect many new rows; exact counts depend on how many branches were empty.
-- You can add real Types/Components later in epc_admin — this is a starter spine.
-- Optional Step 4: `02e_epc_variants_dismantler.sql` — Used / Scrap on `all-items` only.

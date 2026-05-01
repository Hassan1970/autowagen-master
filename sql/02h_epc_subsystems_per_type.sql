-- =====================================================================
-- Autowagen Master — EPC — Every **Type** has a **Subsystem**
--
-- 1) **Gap-fill:** any active `epc_types` row with **no** subsystem gets
--    **Parts grouping** (`parts-group`) — same rule as `02d_epc_spine_step3.sql`.
-- 2) **Readable names:** every subsystem with slug **`parts-group`** gets its
--    **display name** set to: `<Type name> — parts` so the tree is not just
--    the generic words “Parts grouping” everywhere.
-- 3) **Spine below:** new subsystems get **All items** + **OEM** + **Aftermarket**
--    if still missing (copied from `02d` so this file is one shot).
--
-- Run after: `02g` (and `02d` at least once). Safe to re-run.
-- Optional next: `02e_epc_variants_dismantler.sql` for Used / Scrap on `all-items`.
-- =====================================================================

-- --- A) Subsystem for every type that still has none ----------------
INSERT INTO `epc_subsystems` (`type_id`, `name`, `slug`, `sort_order`)
SELECT t.`id`, 'Parts grouping', 'parts-group', 10
FROM `epc_types` t
WHERE t.`is_active` = 1
  AND NOT EXISTS (
    SELECT 1 FROM `epc_subsystems` s
    WHERE s.`type_id` = t.`id` AND s.`is_active` = 1
  );

-- --- B) Name each `parts-group` after its parent Type -----------------
UPDATE `epc_subsystems` s
INNER JOIN `epc_types` t ON t.`id` = s.`type_id` AND t.`is_active` = 1
SET s.`name` = CONCAT(t.`name`, ' — parts')
WHERE s.`slug` = 'parts-group'
  AND s.`is_active` = 1;

-- --- C) Components / Variants under any subsystem still empty --------
INSERT INTO `epc_components` (`subsystem_id`, `name`, `slug`, `sort_order`)
SELECT s.`id`, 'All items', 'all-items', 10
FROM `epc_subsystems` s
WHERE s.`is_active` = 1
  AND NOT EXISTS (
    SELECT 1 FROM `epc_components` c
    WHERE c.`subsystem_id` = s.`id` AND c.`is_active` = 1
  );

INSERT INTO `epc_variants` (`component_id`, `name`, `slug`, `sort_order`)
SELECT c.`id`, 'OEM', 'oem', 10
FROM `epc_components` c
WHERE c.`is_active` = 1
  AND NOT EXISTS (
    SELECT 1 FROM `epc_variants` v
    WHERE v.`component_id` = c.`id` AND v.`is_active` = 1
  );

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

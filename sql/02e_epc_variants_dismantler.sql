-- =====================================================================
-- Autowagen Master — EPC Step 4 — Variants for dismantlers (optional)
--
-- Spine rows (Step 3) end with OEM + Aftermarket on **All items** components.
-- This adds **Used / take-off** and **Scrap** only where:
--   • component slug = `all-items` (placeholder from 02d), and
--   • Aftermarket already exists, and
--   • the new slug is not already present.
--
-- Seed components (e.g. Radiator, Brake pad) are **not** touched.
-- Safe to re-run.
-- Run after: `02d_epc_spine_step3.sql`
-- =====================================================================

INSERT INTO `epc_variants` (`component_id`, `name`, `slug`, `sort_order`)
SELECT c.`id`, 'Used / take-off', 'used-take-off', 30
FROM `epc_components` c
WHERE c.`is_active` = 1
  AND c.`slug` = 'all-items'
  AND EXISTS (
    SELECT 1 FROM `epc_variants` v
    WHERE v.`component_id` = c.`id` AND v.`slug` = 'aftermarket' AND v.`is_active` = 1
  )
  AND NOT EXISTS (
    SELECT 1 FROM `epc_variants` v2
    WHERE v2.`component_id` = c.`id` AND v2.`slug` = 'used-take-off' AND v2.`is_active` = 1
  );

INSERT INTO `epc_variants` (`component_id`, `name`, `slug`, `sort_order`)
SELECT c.`id`, 'Scrap', 'scrap', 40
FROM `epc_components` c
WHERE c.`is_active` = 1
  AND c.`slug` = 'all-items'
  AND EXISTS (
    SELECT 1 FROM `epc_variants` v
    WHERE v.`component_id` = c.`id` AND v.`slug` = 'used-take-off' AND v.`is_active` = 1
  )
  AND NOT EXISTS (
    SELECT 1 FROM `epc_variants` v2
    WHERE v2.`component_id` = c.`id` AND v2.`slug` = 'scrap' AND v2.`is_active` = 1
  );

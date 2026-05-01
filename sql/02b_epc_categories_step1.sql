-- =====================================================================
-- Autowagen Master — EPC Step 1 — Level 1 categories only
--
-- Adds the agreed “first batch” of major categories and sets sort_order
-- so the list matches hassan’s order (Engine → … → Accessories).
-- Existing seed rows: engine, brakes, body — kept, only re-numbered.
--
-- Run in phpMyAdmin on `autowagen_master` after `sql/02_epc.sql`.
--
-- **Re-run behaviour:** Uses ON DUPLICATE KEY UPDATE so a second run **updates**
-- name/sort_order without “Duplicate entry” warnings. If you still see warnings
-- from an old `INSERT IGNORE` script — that only means those rows **were already
-- created** (e.g. you ran Step 1 successfully before). That is OK; your data is
-- not broken.
--
-- Use **backticks** around table/column names (`epc_categories`), not quotes.
-- =====================================================================

UPDATE `epc_categories` SET `sort_order` = 10 WHERE `slug` = 'engine'   AND `is_active` = 1;
UPDATE `epc_categories` SET `sort_order` = 30 WHERE `slug` = 'brakes'   AND `is_active` = 1;
UPDATE `epc_categories` SET `sort_order` = 90 WHERE `slug` = 'body'     AND `is_active` = 1;

INSERT INTO `epc_categories` (`name`, `slug`, `sort_order`) VALUES
  ('Transmission & driveline',      'transmission-driveline',      20),
  ('Suspension & steering',         'suspension-steering',          40),
  ('Electrical & electronics',      'electrical-electronics',       50),
  ('Fuel system',                   'fuel-system',                  60),
  ('Cooling & HVAC',                'cooling-hvac',                 70),
  ('Exhaust & emissions',           'exhaust-emissions',            80),
  ('Interior',                      'interior',                    100),
  ('Wheels & tyres',                'wheels-tyres',                110),
  ('Accessories & consumables',     'accessories-consumables',     120)
ON DUPLICATE KEY UPDATE
  `name`       = VALUES(`name`),
  `sort_order` = VALUES(`sort_order`);

-- After run: expect 12 active categories, sorted by sort_order.
-- Next step: `sql/02c_epc_subcategories_step2.sql` (Level 2).
-- =====================================================================
-- Autowagen Master  -  Stage 4b  -  Third-party part compliance (SHGA)
--
-- Adds four columns on `parts` for seller ID scan + proof of address when
-- source = third_party and the part was bought from a private individual
-- (supplier_id IS NULL). Formal supplier purchases do not use these fields.
--
-- Run once in phpMyAdmin against `autowagen_master`.
-- Safe to re-run: INFORMATION_SCHEMA-guarded ALTERs.
-- Requires `sql/04_inventory.sql` (parts table).
-- =====================================================================


-- 1. has_tpp_id_doc
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parts'
    AND COLUMN_NAME = 'has_tpp_id_doc');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `parts` ADD COLUMN `has_tpp_id_doc` TINYINT(1) NOT NULL DEFAULT 0 AFTER `seller_id_number`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- 2. tpp_id_doc_path
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parts'
    AND COLUMN_NAME = 'tpp_id_doc_path');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `parts` ADD COLUMN `tpp_id_doc_path` VARCHAR(255) DEFAULT NULL AFTER `has_tpp_id_doc`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- 3. has_tpp_proof_of_address
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parts'
    AND COLUMN_NAME = 'has_tpp_proof_of_address');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `parts` ADD COLUMN `has_tpp_proof_of_address` TINYINT(1) NOT NULL DEFAULT 0 AFTER `tpp_id_doc_path`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- 4. tpp_proof_of_address_path
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parts'
    AND COLUMN_NAME = 'tpp_proof_of_address_path');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `parts` ADD COLUMN `tpp_proof_of_address_path` VARCHAR(255) DEFAULT NULL AFTER `has_tpp_proof_of_address`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =====================================================================
-- Done. PHP stores files under uploads/parts/<id>/docs/
-- =====================================================================

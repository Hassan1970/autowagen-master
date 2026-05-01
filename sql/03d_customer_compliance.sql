-- =====================================================================
-- Autowagen Master  -  Stage 3d  -  Customer compliance docs
--
-- Adds five new columns on `customers` so that any buyer of stripped /
-- second-hand parts can have their identity + proof-of-address paperwork
-- on file, as required by the South African Second-Hand Goods Act.
--
--   * sa_id_number          VARCHAR(40)  - private buyer's SA ID / passport
--   * company_reg_number    VARCHAR(40)  - business buyer's CIPC number
--   * id_doc_path           VARCHAR(255) - scan of the ID / CIPC certificate
--   * has_proof_of_address  TINYINT(1)   - 0 / 1 toggle
--   * proof_of_address_path VARCHAR(255) - scan of utility bill / bank stmt
--
-- These fields are OPTIONAL on the customer record. The Stage 5 POS will
-- enforce them at sale time only when the invoice contains a stripped or
-- used part.
--
-- Run this once in phpMyAdmin against the `autowagen_master` database.
-- Safe to re-run: every statement is INFORMATION_SCHEMA-guarded.
-- Compatibility: MySQL 5.7+, MySQL 8+, MariaDB 10.x.
-- Requires Stage 3 (the `customers` table) to have run first.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. sa_id_number VARCHAR(40)  AFTER vat_number
-- ---------------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
    AND COLUMN_NAME = 'sa_id_number');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `customers` ADD COLUMN `sa_id_number` VARCHAR(40) DEFAULT NULL AFTER `vat_number`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 2. company_reg_number VARCHAR(40)  AFTER sa_id_number
-- ---------------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
    AND COLUMN_NAME = 'company_reg_number');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `customers` ADD COLUMN `company_reg_number` VARCHAR(40) DEFAULT NULL AFTER `sa_id_number`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 3. id_doc_path VARCHAR(255)  AFTER company_reg_number
-- ---------------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
    AND COLUMN_NAME = 'id_doc_path');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `customers` ADD COLUMN `id_doc_path` VARCHAR(255) DEFAULT NULL AFTER `company_reg_number`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 4. has_proof_of_address TINYINT(1)  AFTER id_doc_path
-- ---------------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
    AND COLUMN_NAME = 'has_proof_of_address');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `customers` ADD COLUMN `has_proof_of_address` TINYINT(1) NOT NULL DEFAULT 0 AFTER `id_doc_path`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 5. proof_of_address_path VARCHAR(255)  AFTER has_proof_of_address
-- ---------------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
    AND COLUMN_NAME = 'proof_of_address_path');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `customers` ADD COLUMN `proof_of_address_path` VARCHAR(255) DEFAULT NULL AFTER `has_proof_of_address`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 6. Index on sa_id_number for fast lookup at POS time
-- ---------------------------------------------------------------------
SET @ix_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
    AND INDEX_NAME = 'idx_customers_sa_id');
SET @sql := IF(@ix_exists = 0,
  'CREATE INDEX `idx_customers_sa_id` ON `customers` (`sa_id_number`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 7. Index on company_reg_number for fast lookup at POS time
-- ---------------------------------------------------------------------
SET @ix_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
    AND INDEX_NAME = 'idx_customers_company_reg');
SET @sql := IF(@ix_exists = 0,
  'CREATE INDEX `idx_customers_company_reg` ON `customers` (`company_reg_number`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =====================================================================
-- Done.  `customers` now has 5 new compliance columns + 2 indexes.
-- customers_admin.php picks them up automatically once redeployed.
-- =====================================================================

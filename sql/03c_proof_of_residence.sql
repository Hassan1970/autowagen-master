-- =====================================================================
-- Autowagen Master  -  Stage 3c  -  Add Seller's Proof of Residence
--
-- Adds a 4th legal-paper slot so a private vehicle purchase can also
-- store the seller's proof of residence (utility bill / bank statement).
-- This complements the SA Second-Hand Goods Act paper trail already
-- captured in Stage 3b (logbook / receipt / SA ID copy).
--
-- Two new columns on `vehicles`:
--   * has_proof_of_residence  TINYINT(1)   0 / 1
--   * proof_of_residence_path VARCHAR(255) /uploads/vehicles/<id>/docs/...
--
-- Run this once in phpMyAdmin against the `autowagen_master` database.
-- Safe to re-run: every statement is INFORMATION_SCHEMA-guarded.
-- Compatibility: MySQL 5.7+, MySQL 8+, MariaDB 10.x.
-- Requires Stage 3b to have run first.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. has_proof_of_residence TINYINT(1)  AFTER seller_id_copy_path
-- ---------------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMADATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'has_proof_of_residence');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `has_proof_of_residence` TINYINT(1) NOT NULL DEFAULT 0 AFTER `seller_id_copy_path`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 2. proof_of_residence_path VARCHAR(255)  AFTER has_proof_of_residence
-- ---------------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'proof_of_residence_path');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `proof_of_residence_path` VARCHAR(255) DEFAULT NULL AFTER `has_proof_of_residence`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =====================================================================
-- Done.  vehicle_edit.php now shows a 4th LEGAL-PAPERS card and
-- vehicles_admin.php papers badge is x/4 instead of x/3.
-- =====================================================================

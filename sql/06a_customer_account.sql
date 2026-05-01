-- =====================================================================
-- Autowagen Master  -  Stage 6a  -  Account customers (on-account / AR)
--
--   * account_customer   TINYINT(1)  - 1 = may buy on account (due date
--                                       on invoices is allowed after SQL runs)
--   * credit_limit_zar   DECIMAL(12,2) NULL - optional soft ceiling (info
--                                       only until enforced in code later)
--
-- Run once in phpMyAdmin on `autowagen_master` after Stage 3 / 5.
-- Idempotent: INFORMATION_SCHEMA-guarded. MySQL 5.7+, 8+, MariaDB 10.x.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. account_customer  AFTER notes
-- ---------------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
    AND COLUMN_NAME = 'account_customer');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `customers` ADD COLUMN `account_customer` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notes`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 2. credit_limit_zar  AFTER account_customer
-- ---------------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
    AND COLUMN_NAME = 'credit_limit_zar');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `customers` ADD COLUMN `credit_limit_zar` DECIMAL(12,2) DEFAULT NULL AFTER `account_customer`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

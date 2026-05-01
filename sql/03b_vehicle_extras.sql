-- =====================================================================
-- Autowagen Master  -  Stage 3b  -  Extra vehicle fields (strip & sell)
--
-- Adds the columns needed for a South African vehicle dismantler:
--   * Stock code (AWG-XXXX, unique)
--   * Status / transmission / fuel type / body type
--   * Acquisition (supplier / private seller, SA ID number, purchase price)
--   * Legal papers tracking (log book, seller's receipt, seller ID copy)
--   * Yard location
-- Plus a new table `vehicle_photos` (max 7 enforced in PHP).
--
-- Run this once in phpMyAdmin against the `autowagen_master` database.
-- Safe to re-run: every statement is guarded against duplicate work.
-- Requires Stage 3 (`vehicles`, `suppliers`, `users`) to already exist.
--
-- Compatibility: works on MySQL 5.7+, MySQL 8+, and MariaDB 10.x.
-- We avoid `ADD COLUMN IF NOT EXISTS` (MariaDB-only) and use
-- INFORMATION_SCHEMA existence checks via prepared statements instead.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Extra columns on `vehicles` (idempotent)
-- ---------------------------------------------------------------------

-- 1.1  stock_code VARCHAR(20) NULL  AFTER id
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'stock_code');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `stock_code` VARCHAR(20) DEFAULT NULL AFTER `id`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.2  status ENUM  AFTER mileage
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'status');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `status` ENUM(''intake'',''stripping'',''stripped'',''scrapped'',''shell_sold'') NOT NULL DEFAULT ''intake'' AFTER `mileage`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.3  transmission ENUM  AFTER status
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'transmission');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `transmission` ENUM(''manual'',''automatic'',''cvt'',''semi_auto'',''unknown'') NOT NULL DEFAULT ''unknown'' AFTER `status`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.4  fuel_type ENUM  AFTER transmission
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'fuel_type');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `fuel_type` ENUM(''petrol'',''diesel'',''hybrid'',''electric'',''lpg'',''unknown'') NOT NULL DEFAULT ''unknown'' AFTER `transmission`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.5  body_type VARCHAR(40)  AFTER fuel_type
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'body_type');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `body_type` VARCHAR(40) DEFAULT NULL AFTER `fuel_type`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.6  supplier_id INT UNSIGNED  AFTER body_type
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'supplier_id');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `supplier_id` INT UNSIGNED DEFAULT NULL AFTER `body_type`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.7  seller_name VARCHAR(150)  AFTER supplier_id
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'seller_name');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `seller_name` VARCHAR(150) DEFAULT NULL AFTER `supplier_id`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.8  seller_id_number VARCHAR(20)  AFTER seller_name
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'seller_id_number');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `seller_id_number` VARCHAR(20) DEFAULT NULL AFTER `seller_name`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.9  seller_phone VARCHAR(30)  AFTER seller_id_number
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'seller_phone');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `seller_phone` VARCHAR(30) DEFAULT NULL AFTER `seller_id_number`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.10 purchase_price DECIMAL(12,2)  AFTER seller_phone
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'purchase_price');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `purchase_price` DECIMAL(12,2) DEFAULT NULL AFTER `seller_phone`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.11 date_acquired DATE  AFTER purchase_price
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'date_acquired');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `date_acquired` DATE DEFAULT NULL AFTER `purchase_price`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.12 purchase_notes TEXT  AFTER date_acquired
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'purchase_notes');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `purchase_notes` TEXT NULL AFTER `date_acquired`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.13 has_logbook TINYINT(1)  AFTER purchase_notes
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'has_logbook');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `has_logbook` TINYINT(1) NOT NULL DEFAULT 0 AFTER `purchase_notes`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.14 logbook_path VARCHAR(255)  AFTER has_logbook
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'logbook_path');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `logbook_path` VARCHAR(255) DEFAULT NULL AFTER `has_logbook`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.15 has_sellers_receipt TINYINT(1)  AFTER logbook_path
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'has_sellers_receipt');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `has_sellers_receipt` TINYINT(1) NOT NULL DEFAULT 0 AFTER `logbook_path`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.16 sellers_receipt_path VARCHAR(255)  AFTER has_sellers_receipt
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'sellers_receipt_path');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `sellers_receipt_path` VARCHAR(255) DEFAULT NULL AFTER `has_sellers_receipt`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.17 has_seller_id_copy TINYINT(1)  AFTER sellers_receipt_path
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'has_seller_id_copy');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `has_seller_id_copy` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sellers_receipt_path`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.18 seller_id_copy_path VARCHAR(255)  AFTER has_seller_id_copy
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'seller_id_copy_path');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `seller_id_copy_path` VARCHAR(255) DEFAULT NULL AFTER `has_seller_id_copy`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1.19 yard_location VARCHAR(80)  AFTER seller_id_copy_path
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND COLUMN_NAME = 'yard_location');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `vehicles` ADD COLUMN `yard_location` VARCHAR(80) DEFAULT NULL AFTER `seller_id_copy_path`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 2. Indexes & foreign key (idempotent via INFORMATION_SCHEMA checks)
-- ---------------------------------------------------------------------

-- Unique index on stock_code (multiple NULLs are allowed in a UNIQUE
-- index, which is what we want — old rows can have NULL until edited).
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND INDEX_NAME   = 'uniq_vehicles_stock_code');
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE `vehicles` ADD UNIQUE INDEX `uniq_vehicles_stock_code` (`stock_code`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index on status for fast filtering of the vehicles list page.
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND INDEX_NAME   = 'idx_vehicles_status');
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE `vehicles` ADD INDEX `idx_vehicles_status` (`status`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Foreign key: vehicles.supplier_id -> suppliers.id
-- (ON DELETE SET NULL: if a supplier row is ever hard-deleted, the
-- vehicle keeps existing with no supplier link.)
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'
    AND CONSTRAINT_NAME   = 'fk_vehicles_supplier');
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE `vehicles` ADD CONSTRAINT `fk_vehicles_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 3. New table: vehicle_photos (max 7 enforced in PHP)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vehicle_photos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vehicle_id` INT UNSIGNED NOT NULL,
  `file_path`  VARCHAR(255) NOT NULL,
  `caption`    VARCHAR(120) DEFAULT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vehicle_photos_vehicle` (`vehicle_id`, `sort_order`),
  CONSTRAINT `fk_vehicle_photos_vehicle`
    FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_vehicle_photos_user`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Done.  Files are stored under /uploads/vehicles/<vehicle_id>/ on disk;
-- only the relative path is saved in the DB.
-- =====================================================================

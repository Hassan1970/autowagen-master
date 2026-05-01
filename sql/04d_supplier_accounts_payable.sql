-- =====================================================================
-- Autowagen Master — Stage 4d — Supplier accounts payable (ZAR)
--
-- One supplier_purchases row = one batch; bill_amount / bill_date / due_date
-- record what you owe. supplier_purchase_payments = part / full payments.
-- Works for registered suppliers and private sellers (no supplier_id).
--
-- Run in phpMyAdmin on `autowagen_master` after `04c_supplier_purchases.sql`.
-- Idempotent: guarded ALTERs + CREATE IF NOT EXISTS.
-- =====================================================================

-- supplier_purchases: bill fields
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'supplier_purchases' AND COLUMN_NAME = 'bill_amount');
SET @s := IF(@c = 0,
  'ALTER TABLE `supplier_purchases` ADD COLUMN `bill_amount` DECIMAL(12,2) NULL DEFAULT NULL AFTER `notes`',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'supplier_purchases' AND COLUMN_NAME = 'bill_date');
SET @s := IF(@c = 0,
  'ALTER TABLE `supplier_purchases` ADD COLUMN `bill_date` DATE NULL DEFAULT NULL AFTER `bill_amount`',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'supplier_purchases' AND COLUMN_NAME = 'due_date');
SET @s := IF(@c = 0,
  'ALTER TABLE `supplier_purchases` ADD COLUMN `due_date` DATE NULL DEFAULT NULL AFTER `bill_date`',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `supplier_purchase_payments` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_purchase_id`  INT UNSIGNED NOT NULL,
  `amount`                 DECIMAL(12,2) NOT NULL,
  `paid_at`                DATE NOT NULL,
  `payment_method`         VARCHAR(20)  NOT NULL DEFAULT 'eft',
  `reference_note`         VARCHAR(255)  DEFAULT NULL,
  `notes`                  TEXT          DEFAULT NULL,
  `is_active`              TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`             INT UNSIGNED DEFAULT NULL,
  `created_at`             TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sppay_purchase` (`supplier_purchase_id`),
  KEY `idx_sppay_paid_at`  (`paid_at`),
  KEY `idx_sppay_active`   (`is_active`),
  CONSTRAINT `fk_sppay_purchase`
    FOREIGN KEY (`supplier_purchase_id`) REFERENCES `supplier_purchases`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sppay_user`
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT = 'Part/full payments to suppliers or private sellers (ZAR)';

-- =====================================================================
-- payment_method: eft | cash | card | other
-- =====================================================================

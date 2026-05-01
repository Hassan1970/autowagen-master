-- =====================================================================
-- Autowagen Master  -  Stage 6b  -  Public web shop (linked inventory)
--
--   * parts.list_online     TINYINT(1) — staff ticks “List on website”
--   * shop_orders           guest checkout header + totals
--   * shop_order_lines      snapshot lines + part_id FK
--
-- Stock is reduced when checkout completes (same rules as POS finalize
-- for qty / sold). Only parts eligible for internet sale appear in the
-- catalog (see shop_helpers.php): NEW condition, NOT stripped.
--
-- Run once in phpMyAdmin on `autowagen_master` after Stage 4 (+ 06a optional).
-- Idempotent column add. MySQL 5.7+, 8+, MariaDB 10.x.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. list_online on parts (after is_active in canonical 04_inventory)
-- ---------------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parts'
    AND COLUMN_NAME = 'list_online');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `parts` ADD COLUMN `list_online` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------------
-- 2. shop_orders
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_orders` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no`         VARCHAR(24)  NOT NULL,
  `status`           ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  `customer_name`    VARCHAR(120) NOT NULL,
  `email`            VARCHAR(120) DEFAULT NULL,
  `phone`            VARCHAR(40)  NOT NULL,
  `shipping_address` TEXT         NULL,
  `notes`            TEXT         NULL,
  `subtotal_ex_vat`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `vat_total`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_inc_vat`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_shop_order_no` (`order_no`),
  KEY `idx_shop_orders_status` (`status`),
  KEY `idx_shop_orders_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. shop_order_lines
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_order_lines` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_order_id`      INT UNSIGNED NOT NULL,
  `part_id`            INT UNSIGNED NOT NULL,
  `sku_snapshot`       VARCHAR(40)  NOT NULL,
  `name_snapshot`      VARCHAR(120) NOT NULL,
  `qty`                INT          NOT NULL,
  `unit_price_ex_vat`  DECIMAL(12,2) NOT NULL,
  `vat_rate`           DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `line_subtotal_ex`   DECIMAL(12,2) NOT NULL,
  `line_vat`           DECIMAL(12,2) NOT NULL,
  `line_total_inc`     DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_solid_order` (`shop_order_id`),
  KEY `idx_solid_part` (`part_id`),
  CONSTRAINT `fk_solid_order`
    FOREIGN KEY (`shop_order_id`) REFERENCES `shop_orders`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_solid_part`
    FOREIGN KEY (`part_id`) REFERENCES `parts`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


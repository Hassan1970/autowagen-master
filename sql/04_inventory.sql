-- =====================================================================
-- Autowagen Master  -  Stage 4  -  Inventory & Parts
--
-- Three new tables for the parts inventory system:
--   * parts            - main inventory row (every sellable item)
--   * part_photos      - up to 5 photos per part (cap enforced in PHP)
--   * part_epc_links   - many-to-many tag of parts to EPC variants
--
-- Run this once in phpMyAdmin against the `autowagen_master` database.
-- Safe to re-run: every CREATE uses IF NOT EXISTS.
-- Requires Stages 1-3 (users, vehicles, suppliers, epc_variants).
--
-- Compatibility: MySQL 5.7+, MySQL 8+, MariaDB 10.x.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1.  parts  -  one row per sellable item
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `parts` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku`              VARCHAR(40)  NOT NULL,
  `name`             VARCHAR(120) NOT NULL,
  `source`           ENUM('stripped','oem_new','replacement','third_party')
                     NOT NULL,
  `vehicle_id`       INT UNSIGNED NULL,
  `supplier_id`      INT UNSIGNED NULL,
  `seller_name`      VARCHAR(120) NULL,
  `seller_phone`     VARCHAR(40)  NULL,
  `seller_id_number` VARCHAR(40)  NULL,
  `condition_grade`  ENUM('new','good','fair','poor','scrap')
                     NOT NULL DEFAULT 'good',
  `status`           ENUM('on_vehicle','available','reserved','sold','scrapped')
                     NOT NULL DEFAULT 'available',
  `cost_price`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `asking_price`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_price`   DECIMAL(12,2) NULL,
  `vat_rate`         DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `qty_on_hand`      INT NOT NULL DEFAULT 1,
  `yard_location`    VARCHAR(60)  NULL,
  `notes`            TEXT NULL,
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`       INT UNSIGNED NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_parts_sku` (`sku`),
  KEY `idx_parts_status`   (`status`),
  KEY `idx_parts_source`   (`source`),
  KEY `idx_parts_vehicle`  (`vehicle_id`),
  KEY `idx_parts_supplier` (`supplier_id`),
  KEY `idx_parts_active`   (`is_active`),
  CONSTRAINT `fk_parts_vehicle`
    FOREIGN KEY (`vehicle_id`)  REFERENCES `vehicles`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_parts_supplier`
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_parts_user`
    FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2.  part_photos  -  gallery (max 5 per part, enforced in PHP)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `part_photos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `part_id`    INT UNSIGNED NOT NULL,
  `path`       VARCHAR(255) NOT NULL,
  `caption`    VARCHAR(120) NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_part_photos_part` (`part_id`),
  CONSTRAINT `fk_part_photos_part`
    FOREIGN KEY (`part_id`) REFERENCES `parts`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3.  part_epc_links  -  many-to-many to the 6-level EPC tree (variants)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `part_epc_links` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `part_id`    INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_part_variant` (`part_id`, `variant_id`),
  KEY `idx_pel_variant` (`variant_id`),
  CONSTRAINT `fk_pel_part`
    FOREIGN KEY (`part_id`)    REFERENCES `parts`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pel_variant`
    FOREIGN KEY (`variant_id`) REFERENCES `epc_variants`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- Done.  Three tables: parts, part_photos, part_epc_links.
-- Stage 4 PHP pages can now read & write inventory rows.
-- =====================================================================

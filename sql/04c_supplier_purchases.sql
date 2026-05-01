-- =====================================================================
-- Autowagen Master — Stage 4c — Supplier purchase (batch)
--
-- One row in `supplier_purchases` = one buy from a registered supplier
-- OR a private individual (SHGA scans once for private where needed).
-- Many `parts` rows link via `parts.supplier_purchase_id` — add only
-- part fields each time. Company purchases: each line can be OEM new,
-- replacement, or third-party. Private purchases: each line is third-party.
--
-- Run in phpMyAdmin against `autowagen_master`.
-- Requires: `sql/04_inventory.sql` + `sql/04b_part_tpp_compliance.sql`.
-- Idempotent: CREATE IF NOT EXISTS + guarded ALTER.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `supplier_purchases` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_id`      INT UNSIGNED NULL,
  `seller_name`      VARCHAR(120) NULL,
  `seller_phone`     VARCHAR(40)  NULL,
  `seller_id_number` VARCHAR(40)  NULL,
  `has_tpp_id_doc`              TINYINT(1)   NOT NULL DEFAULT 0,
  `tpp_id_doc_path`             VARCHAR(255) NULL,
  `has_tpp_proof_of_address`    TINYINT(1)   NOT NULL DEFAULT 0,
  `tpp_proof_of_address_path`   VARCHAR(255) NULL,
  `purchase_ref`     VARCHAR(120) NULL,
  `notes`            TEXT NULL,
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`       INT UNSIGNED NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_supplier_purchases_supplier` (`supplier_id`),
  KEY `idx_supplier_purchases_active` (`is_active`),
  CONSTRAINT `fk_supplier_purchases_supplier`
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_supplier_purchases_user`
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- parts.supplier_purchase_id (nullable FK)
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parts'
    AND COLUMN_NAME = 'supplier_purchase_id');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `parts` ADD COLUMN `supplier_purchase_id` INT UNSIGNED NULL AFTER `tpp_proof_of_address_path`',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'parts'
    AND CONSTRAINT_NAME = 'fk_parts_supplier_purchase');
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE `parts` ADD CONSTRAINT `fk_parts_supplier_purchase`
    FOREIGN KEY (`supplier_purchase_id`) REFERENCES `supplier_purchases`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ix_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parts'
    AND INDEX_NAME = 'idx_parts_supplier_purchase');
SET @sql := IF(@ix_exists = 0,
  'CREATE INDEX `idx_parts_supplier_purchase` ON `parts` (`supplier_purchase_id`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- PHP uploads: uploads/supplier_purchases/<id>/docs/
-- =====================================================================

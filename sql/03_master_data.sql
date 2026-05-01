-- =====================================================================
-- Autowagen Master  -  Stage 3  -  Master data
-- Three core tables the business owns / talks to:
--   vehicles, customers, suppliers
-- Plus one many-to-many join:
--   vehicle_epc_links  (which Stage-2 EPC variants apply to a vehicle)
--
-- Run this once in phpMyAdmin against the autowagen_master database.
-- Safe to re-run: every statement uses CREATE TABLE IF NOT EXISTS.
-- Requires Stage 1 (`users`) and Stage 2 (`epc_variants`) already in place.
-- =====================================================================

-- ---------------------------------------------------------------------
-- VEHICLES
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `make`         VARCHAR(80)  NOT NULL,
  `model`        VARCHAR(80)  NOT NULL,
  `year`         SMALLINT UNSIGNED DEFAULT NULL,
  `vin`          VARCHAR(32)  DEFAULT NULL,
  `engine_code`  VARCHAR(40)  DEFAULT NULL,
  `plate`        VARCHAR(20)  DEFAULT NULL,
  `colour`       VARCHAR(40)  DEFAULT NULL,
  `mileage`      INT UNSIGNED DEFAULT NULL,
  `notes`        TEXT         DEFAULT NULL,
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`   INT UNSIGNED DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  -- VIN and plate are unique WHEN PRESENT.
  -- MySQL allows multiple NULLs in a UNIQUE index, which is exactly what
  -- we want here (most stripped/junkyard vehicles arrive with no plate).
  UNIQUE KEY `uniq_vehicles_vin`   (`vin`),
  UNIQUE KEY `uniq_vehicles_plate` (`plate`),
  KEY `idx_vehicles_make_model`    (`make`, `model`),
  KEY `idx_vehicles_active`        (`is_active`),
  CONSTRAINT `fk_vehicles_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CUSTOMERS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`             ENUM('individual','business') NOT NULL DEFAULT 'individual',
  `name`             VARCHAR(150) NOT NULL,
  `contact_person`   VARCHAR(150) DEFAULT NULL,
  `phone`            VARCHAR(40)  DEFAULT NULL,
  `email`            VARCHAR(150) DEFAULT NULL,
  `billing_address`  TEXT         DEFAULT NULL,
  `delivery_address` TEXT         DEFAULT NULL,
  `vat_number`       VARCHAR(50)  DEFAULT NULL,
  `notes`            TEXT         DEFAULT NULL,
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`       INT UNSIGNED DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customers_type`   (`type`),
  KEY `idx_customers_name`   (`name`),
  KEY `idx_customers_active` (`is_active`),
  CONSTRAINT `fk_customers_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- SUPPLIERS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`               VARCHAR(150) NOT NULL,
  `contact_person`     VARCHAR(150) DEFAULT NULL,
  `phone`              VARCHAR(40)  DEFAULT NULL,
  `email`              VARCHAR(150) DEFAULT NULL,
  `address`            TEXT         DEFAULT NULL,
  `payment_terms_days` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  `notes`              TEXT         DEFAULT NULL,
  `is_active`          TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`         INT UNSIGNED DEFAULT NULL,
  `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_suppliers_name`   (`name`),
  KEY `idx_suppliers_active` (`is_active`),
  CONSTRAINT `fk_suppliers_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- VEHICLE_EPC_LINKS  -  many-to-many: which EPC variants apply to a vehicle.
-- Composite PK guarantees each pair appears at most once.
-- Cascading deletes keep the join table clean if a variant or vehicle
-- IS ever hard-deleted (the UI only soft-deletes vehicles, but the EPC
-- tree allows real deletes via cascade from epc_categories).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vehicle_epc_links` (
  `vehicle_id` INT UNSIGNED NOT NULL,
  `variant_id` INT UNSIGNED NOT NULL,
  `note`       VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`vehicle_id`, `variant_id`),
  KEY `idx_vel_variant` (`variant_id`),
  CONSTRAINT `fk_vel_vehicle`
    FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_vel_variant`
    FOREIGN KEY (`variant_id`) REFERENCES `epc_variants` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_vel_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

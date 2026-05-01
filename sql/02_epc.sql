-- =====================================================================
-- Autowagen Master  -  Stage 2  -  EPC (Electronic Parts Catalogue)
-- One canonical 6-level tree:
--   Category > Subcategory > Type > Subsystem > Component > Variant
--
-- Run this once in phpMyAdmin against the autowagen_master database.
-- Safe to re-run: every statement uses CREATE TABLE / INSERT IGNORE.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Level 1  -  Categories  (root)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `epc_categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150) NOT NULL,
  `slug`       VARCHAR(180) NOT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_epc_categories_slug` (`slug`),
  KEY `idx_epc_categories_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Level 2  -  Subcategories
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `epc_subcategories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `slug`        VARCHAR(180) NOT NULL,
  `sort_order`  INT          NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_epc_subcategories_slug` (`category_id`, `slug`),
  KEY `idx_epc_subcategories_sort` (`category_id`, `sort_order`),
  CONSTRAINT `fk_epc_subcat_cat`
    FOREIGN KEY (`category_id`) REFERENCES `epc_categories` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Level 3  -  Types
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `epc_types` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subcategory_id` INT UNSIGNED NOT NULL,
  `name`           VARCHAR(150) NOT NULL,
  `slug`           VARCHAR(180) NOT NULL,
  `sort_order`     INT          NOT NULL DEFAULT 0,
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_epc_types_slug` (`subcategory_id`, `slug`),
  KEY `idx_epc_types_sort` (`subcategory_id`, `sort_order`),
  CONSTRAINT `fk_epc_types_subcat`
    FOREIGN KEY (`subcategory_id`) REFERENCES `epc_subcategories` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Level 4  -  Subsystems
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `epc_subsystems` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_id`    INT UNSIGNED NOT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `slug`       VARCHAR(180) NOT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_epc_subsystems_slug` (`type_id`, `slug`),
  KEY `idx_epc_subsystems_sort` (`type_id`, `sort_order`),
  CONSTRAINT `fk_epc_subsys_type`
    FOREIGN KEY (`type_id`) REFERENCES `epc_types` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Level 5  -  Components
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `epc_components` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subsystem_id` INT UNSIGNED NOT NULL,
  `name`         VARCHAR(150) NOT NULL,
  `slug`         VARCHAR(180) NOT NULL,
  `sort_order`   INT          NOT NULL DEFAULT 0,
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_epc_components_slug` (`subsystem_id`, `slug`),
  KEY `idx_epc_components_sort` (`subsystem_id`, `sort_order`),
  CONSTRAINT `fk_epc_comp_subsys`
    FOREIGN KEY (`subsystem_id`) REFERENCES `epc_subsystems` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Level 6  -  Variants  (leaf)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `epc_variants` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `component_id` INT UNSIGNED NOT NULL,
  `name`         VARCHAR(150) NOT NULL,
  `slug`         VARCHAR(180) NOT NULL,
  `sort_order`   INT          NOT NULL DEFAULT 0,
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_epc_variants_slug` (`component_id`, `slug`),
  KEY `idx_epc_variants_sort` (`component_id`, `sort_order`),
  CONSTRAINT `fk_epc_var_comp`
    FOREIGN KEY (`component_id`) REFERENCES `epc_components` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Flat lookup view  -  one row per variant with the full path
-- Stage 4 (Inventory) and Stage 6 (Reports) will use this.
-- DROP + CREATE so re-running picks up any column changes safely.
-- ---------------------------------------------------------------------
DROP VIEW IF EXISTS `epc_full_view`;
CREATE VIEW `epc_full_view` AS
SELECT
  v.id              AS variant_id,
  v.name            AS variant_name,
  v.slug            AS variant_slug,
  v.is_active       AS variant_active,
  c.id              AS component_id,
  c.name            AS component_name,
  c.slug            AS component_slug,
  s.id              AS subsystem_id,
  s.name            AS subsystem_name,
  s.slug            AS subsystem_slug,
  t.id              AS type_id,
  t.name            AS type_name,
  t.slug            AS type_slug,
  sc.id             AS subcategory_id,
  sc.name           AS subcategory_name,
  sc.slug           AS subcategory_slug,
  cat.id            AS category_id,
  cat.name          AS category_name,
  cat.slug          AS category_slug,
  CONCAT_WS(' / ',
    cat.name, sc.name, t.name, s.name, c.name, v.name
  )                 AS full_path
FROM       epc_variants      v
INNER JOIN epc_components    c   ON c.id   = v.component_id
INNER JOIN epc_subsystems    s   ON s.id   = c.subsystem_id
INNER JOIN epc_types         t   ON t.id   = s.type_id
INNER JOIN epc_subcategories sc  ON sc.id  = t.subcategory_id
INNER JOIN epc_categories    cat ON cat.id = sc.category_id;

-- =====================================================================
-- Seed data  -  small example tree so the browser isn't empty.
-- INSERT IGNORE = safe to re-run; existing rows won't be duplicated.
-- =====================================================================

INSERT IGNORE INTO `epc_categories` (`id`, `name`, `slug`, `sort_order`) VALUES
  (1, 'Engine',  'engine',  10),
  (2, 'Brakes',  'brakes',  20),
  (3, 'Body',    'body',    30);

INSERT IGNORE INTO `epc_subcategories` (`id`, `category_id`, `name`, `slug`, `sort_order`) VALUES
  (1, 1, 'Petrol',     'petrol',     10),
  (2, 1, 'Diesel',     'diesel',     20),
  (3, 2, 'Hydraulic',  'hydraulic',  10),
  (4, 3, 'Exterior',   'exterior',   10);

INSERT IGNORE INTO `epc_types` (`id`, `subcategory_id`, `name`, `slug`, `sort_order`) VALUES
  (1, 1, 'Inline 4',  'inline-4',  10),
  (2, 1, 'V6',        'v6',        20),
  (3, 3, 'Disc',      'disc',      10);

INSERT IGNORE INTO `epc_subsystems` (`id`, `type_id`, `name`, `slug`, `sort_order`) VALUES
  (1, 1, 'Cooling',     'cooling',     10),
  (2, 1, 'Lubrication', 'lubrication', 20),
  (3, 3, 'Front Axle',  'front-axle',  10);

INSERT IGNORE INTO `epc_components` (`id`, `subsystem_id`, `name`, `slug`, `sort_order`) VALUES
  (1, 1, 'Radiator',    'radiator',    10),
  (2, 1, 'Water Pump',  'water-pump',  20),
  (3, 3, 'Brake Pad',   'brake-pad',   10);

INSERT IGNORE INTO `epc_variants` (`id`, `component_id`, `name`, `slug`, `sort_order`) VALUES
  (1, 1, 'OEM',         'oem',         10),
  (2, 1, 'Aftermarket', 'aftermarket', 20),
  (3, 3, 'Standard',    'standard',    10),
  (4, 3, 'Performance', 'performance', 20);

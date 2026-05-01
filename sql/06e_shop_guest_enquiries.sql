-- =====================================================================
-- Stage 6e — Public shop guest enquiries (messages about any listed part)
--
-- Run once in phpMyAdmin on `autowagen_master` after 06b_web_shop.sql.
-- Idempotent CREATE TABLE.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `shop_guest_enquiries` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `visitor_name`    VARCHAR(120) NOT NULL,
  `phone`           VARCHAR(40)  NOT NULL,
  `email`           VARCHAR(160) DEFAULT NULL,
  `message`         TEXT         NOT NULL,
  `part_id`         INT UNSIGNED DEFAULT NULL,
  `sku_ref`         VARCHAR(48)  DEFAULT NULL,
  `part_name_hint`  VARCHAR(160) DEFAULT NULL,
  `is_read`         TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sge_created` (`created_at`),
  KEY `idx_sge_unread` (`is_read`, `created_at`),
  KEY `idx_sge_part` (`part_id`),
  CONSTRAINT `fk_sge_part`
    FOREIGN KEY (`part_id`) REFERENCES `parts` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Autowagen Master — Credit notes / customer returns (Stage 7)
--
-- Links to original **final** POS invoice (INV-YYYY-NNNNN). On finalize:
-- restores stock on part-linked lines (always). Adjustment types (both reduce **net due** everywhere):
--   ar_reduction — AR/statement “AR cr.” column aggregates; reduces net balance with cash_refund similarly
--   cash_refund — same net balance effect + payout fields; “Refund cn.” column aggregates
--
-- Run once in phpMyAdmin after Stage 5 (`sales_invoices`, lines, payments).
-- Safe re-run: CREATE TABLE IF NOT EXISTS only.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `sales_credit_notes` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `credit_no`         VARCHAR(32)    DEFAULT NULL,
  `invoice_id`        INT UNSIGNED   NOT NULL,
  `customer_id`       INT UNSIGNED   DEFAULT NULL,
  `status`            ENUM('draft','final','void') NOT NULL DEFAULT 'draft',
  `credit_date`       DATE           NOT NULL,
  `adjustment_type`   ENUM('ar_reduction','cash_refund') NOT NULL DEFAULT 'ar_reduction',
  `refund_paid_at`    DATE           DEFAULT NULL,
  `refund_method`     VARCHAR(20)    DEFAULT NULL,
  `refund_reference_note` VARCHAR(255) DEFAULT NULL,
  `subtotal_ex_vat`   DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `vat_total`         DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `total_inc_vat`     DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `notes`             TEXT           DEFAULT NULL,
  `finalized_at`      TIMESTAMP      NULL DEFAULT NULL,
  `is_active`         TINYINT(1)     NOT NULL DEFAULT 1,
  `created_by`        INT UNSIGNED   DEFAULT NULL,
  `created_at`        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sales_credit_note_no` (`credit_no`),
  KEY `idx_scn_invoice` (`invoice_id`),
  KEY `idx_scn_status` (`status`),
  KEY `idx_scn_credit_date` (`credit_date`),
  KEY `idx_scn_active` (`is_active`),
  CONSTRAINT `fk_scn_invoice`
    FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_scn_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_scn_user`
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Customer credit notes linked to POS invoice';


CREATE TABLE IF NOT EXISTS `sales_credit_note_lines` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `credit_note_id`      INT UNSIGNED NOT NULL,
  `invoice_line_id`     INT UNSIGNED NOT NULL,
  `part_id`             INT UNSIGNED DEFAULT NULL,
  `line_description`    VARCHAR(255) NOT NULL,
  `qty`                 INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price_ex_vat`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `vat_rate`            DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `line_subtotal_ex`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `line_vat`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `line_total_inc`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `sort_order`          INT           NOT NULL DEFAULT 0,
  `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_scnl_cn_line` (`credit_note_id`, `invoice_line_id`),
  KEY `idx_scnl_cn` (`credit_note_id`),
  KEY `idx_scnl_invline` (`invoice_line_id`),
  KEY `idx_scnl_part` (`part_id`),
  CONSTRAINT `fk_scnl_credit_note`
    FOREIGN KEY (`credit_note_id`) REFERENCES `sales_credit_notes`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_scnl_invoice_line`
    FOREIGN KEY (`invoice_line_id`) REFERENCES `sales_invoice_lines`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_scnl_part`
    FOREIGN KEY (`part_id`) REFERENCES `parts`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Credit lines tied to invoice lines; qty returned to stock';

-- =====================================================================

-- =====================================================================
-- Autowagen Master — Stage 5 — POS / sales invoices (ZAR)
--
-- sales_invoices + lines + customer payments (accounts receivable balance
-- = invoice total_inc_vat − sum(active payments)).
--
-- Run once in phpMyAdmin on `autowagen_master` after Stage 4 tables exist.
-- Safe to re-run: CREATE TABLE IF NOT EXISTS only.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `sales_invoices` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_no`       VARCHAR(32)   DEFAULT NULL,
  `customer_id`      INT UNSIGNED  DEFAULT NULL,
  `status`           ENUM('draft','final','void') NOT NULL DEFAULT 'draft',
  `invoice_date`     DATE         NOT NULL,
  `due_date`         DATE         DEFAULT NULL,
  `subtotal_ex_vat`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `vat_total`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_inc_vat`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes`            TEXT          DEFAULT NULL,
  `finalized_at`     TIMESTAMP     NULL DEFAULT NULL,
  `is_active`        TINYINT(1)    NOT NULL DEFAULT 1,
  `created_by`       INT UNSIGNED  DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sales_invoice_no` (`invoice_no`),
  KEY `idx_sales_inv_customer` (`customer_id`),
  KEY `idx_sales_inv_status` (`status`),
  KEY `idx_sales_inv_date` (`invoice_date`),
  KEY `idx_sales_inv_active` (`is_active`),
  CONSTRAINT `fk_sales_inv_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sales_inv_user`
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT = 'Customer invoices (POS)';


CREATE TABLE IF NOT EXISTS `sales_invoice_lines` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id`          INT UNSIGNED NOT NULL,
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
  KEY `idx_sil_invoice` (`invoice_id`),
  KEY `idx_sil_part` (`part_id`),
  CONSTRAINT `fk_sil_invoice`
    FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sil_part`
    FOREIGN KEY (`part_id`) REFERENCES `parts`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT = 'Invoice line items (part-linked or manual)';


CREATE TABLE IF NOT EXISTS `sales_invoice_payments` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id`       INT UNSIGNED NOT NULL,
  `amount`           DECIMAL(12,2) NOT NULL,
  `paid_at`          DATE         NOT NULL,
  `payment_method`   VARCHAR(20)  NOT NULL DEFAULT 'cash',
  `reference_note`   VARCHAR(255) DEFAULT NULL,
  `notes`            TEXT         DEFAULT NULL,
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`       INT UNSIGNED DEFAULT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sipay_invoice` (`invoice_id`),
  KEY `idx_sipay_paid` (`paid_at`),
  KEY `idx_sipay_active` (`is_active`),
  CONSTRAINT `fk_sipay_invoice`
    FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sipay_user`
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT = 'Payments received against sales invoices (ZAR)';

-- =====================================================================
-- payment_method values: cash | eft | card | other
-- Draft invoices: invoice_no NULL. On finalize, assign INV-YYYY-NNNNN.
-- =====================================================================

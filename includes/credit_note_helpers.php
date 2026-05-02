<?php
/**
 * Credit notes helpers — POS returns linked to original invoice (Stage 7).
 */

declare(strict_types=1);

function cn_tables_ready(PDO $pdo): bool {
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.`TABLES`
         WHERE table_schema = DATABASE() AND table_name = 'sales_credit_notes'"
    )->fetchColumn() > 0;
}

/** Sum finalized credit totals for this invoice (ZAR incl. VAT). */
function cn_finalized_total_for_invoice(PDO $pdo, int $invoiceId): float {
    if (!cn_tables_ready($pdo)) {
        return 0.0;
    }
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(total_inc_vat), 0)
         FROM sales_credit_notes
         WHERE invoice_id = ? AND status = 'final' AND is_active = 1"
    );
    $st->execute([$invoiceId]);
    return round((float) $st->fetchColumn(), 2);
}

/** Sum finalized AR-reduction credits only (ZAR incl. VAT). */
function cn_finalized_ar_reduction_total_for_invoice(PDO $pdo, int $invoiceId): float {
    if (!cn_tables_ready($pdo)) {
        return 0.0;
    }
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(total_inc_vat), 0)
         FROM sales_credit_notes
         WHERE invoice_id = ? AND status = 'final' AND is_active = 1
           AND adjustment_type = 'ar_reduction'"
    );
    $st->execute([$invoiceId]);
    return round((float) $st->fetchColumn(), 2);
}

/** Sum finalized cash-refund credits only (ZAR incl. VAT). */
function cn_finalized_cash_refund_total_for_invoice(PDO $pdo, int $invoiceId): float {
    if (!cn_tables_ready($pdo)) {
        return 0.0;
    }
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(total_inc_vat), 0)
         FROM sales_credit_notes
         WHERE invoice_id = ? AND status = 'final' AND is_active = 1
           AND adjustment_type = 'cash_refund'"
    );
    $st->execute([$invoiceId]);
    return round((float) $st->fetchColumn(), 2);
}

/** Qty already returned on finalized credit notes for one invoice line. */
function cn_qty_finalized_for_invoice_line(PDO $pdo, int $invoiceLineId, ?int $excludeCreditNoteId = null): int {
    $sql =
        'SELECT COALESCE(SUM(cnl.qty), 0)
         FROM sales_credit_note_lines cnl
         INNER JOIN sales_credit_notes cn ON cn.id = cnl.credit_note_id AND cn.status = \'final\' AND cn.is_active = 1
         WHERE cnl.invoice_line_id = ?';
    $params = [$invoiceLineId];
    if ($excludeCreditNoteId !== null && $excludeCreditNoteId > 0) {
        $sql .= ' AND cn.id <> ?';
        $params[] = $excludeCreditNoteId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return (int) $st->fetchColumn();
}

function cn_next_credit_no(PDO $pdo): string {
    $y      = (new DateTime('now', new DateTimeZone('Africa/Johannesburg')))->format('Y');
    $prefix = 'CN-' . $y . '-';
    if (!cn_tables_ready($pdo)) {
        return $prefix . '00001';
    }
    $st = $pdo->prepare(
        'SELECT credit_no FROM sales_credit_notes
         WHERE credit_no IS NOT NULL AND credit_no LIKE ?
         ORDER BY id DESC LIMIT 1'
    );
    $st->execute([$prefix . '%']);
    $last = $st->fetchColumn();
    $n    = 1;
    if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
        $n = (int) $m[1] + 1;
    }

    return $prefix . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
}

function cn_recompute_totals(PDO $pdo, int $creditNoteId): void {
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(line_subtotal_ex),0), COALESCE(SUM(line_vat),0), COALESCE(SUM(line_total_inc),0)
         FROM sales_credit_note_lines WHERE credit_note_id = ?'
    );
    $st->execute([$creditNoteId]);
    $x = $st->fetch(PDO::FETCH_NUM);
    $u = $pdo->prepare(
        'UPDATE sales_credit_notes SET subtotal_ex_vat = ?, vat_total = ?, total_inc_vat = ? WHERE id = ?'
    );
    $u->execute([(float) $x[0], (float) $x[1], (float) $x[2], $creditNoteId]);
}

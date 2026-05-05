<?php
/**
 * Stage 5 — Create / edit invoice (draft → final), lines, payments, SHGA check.
 * Stage 6 — Account customer required when due date set (after sql/06a…).
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/credit_note_helpers.php';

$pageTitle = 'Invoice';

$canEdit = user_has_role('owner', 'admin', 'manager', 'staff');
$uid     = (int) ($_SESSION['user_id'] ?? 0);

function pos_tables_ready(PDO $pdo): bool {
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE table_schema = DATABASE() AND table_name = 'sales_invoices'"
    )->fetchColumn() > 0;
}

function pos_recompute_totals(PDO $pdo, int $invoiceId): void {
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(line_subtotal_ex),0), COALESCE(SUM(line_vat),0), COALESCE(SUM(line_total_inc),0)
         FROM sales_invoice_lines WHERE invoice_id = ?'
    );
    $st->execute([$invoiceId]);
    $x = $st->fetch(PDO::FETCH_NUM);
    $u = $pdo->prepare(
        'UPDATE sales_invoices SET subtotal_ex_vat = ?, vat_total = ?, total_inc_vat = ? WHERE id = ?'
    );
    $u->execute([(float) $x[0], (float) $x[1], (float) $x[2], $invoiceId]);
}

function pos_next_invoice_no(PDO $pdo): string {
    $y = (new DateTime('now', new DateTimeZone('Africa/Johannesburg')))->format('Y');
    $prefix = 'INV-' . $y . '-';
    $st     = $pdo->prepare(
        'SELECT invoice_no FROM sales_invoices
         WHERE invoice_no IS NOT NULL AND invoice_no LIKE ?
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

function pos_part_requires_shga_buyer_docs(array $p): bool {
    $p = array_change_key_case($p, CASE_LOWER);
    if (($p['source'] ?? '') === 'stripped') {
        return true;
    }
    return (($p['condition_grade'] ?? 'good') !== 'new');
}

function pos_customer_buyer_compliance_ok(array $c): bool {
    $c = array_change_key_case($c, CASE_LOWER);
    return !empty($c['id_doc_path']) && !empty($c['proof_of_address_path']);
}

function customers_account_columns_ready(PDO $pdo): bool {
    return (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE table_schema = DATABASE() AND table_name = 'customers'
           AND column_name = 'account_customer'"
    )->fetchColumn() > 0;
}

if (!pos_tables_ready($pdo)) {
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-warning"><strong>Stage 5 tables missing.</strong> Run '
        . '<code>sql/05_pos.sql</code> in phpMyAdmin, then refresh.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$todayJhb = (new DateTime('now', new DateTimeZone('Africa/Johannesburg')))->format('Y-m-d');

if ($canEdit && !empty($_GET['new']) && (int) $_GET['new'] === 1) {
    $st = $pdo->prepare(
        'INSERT INTO sales_invoices (invoice_date, status, created_by) VALUES (?, \'draft\', ?)'
    );
    $st->execute([$todayJhb, $uid > 0 ? $uid : null]);
    header('Location: ' . APP_URL . '/invoice_edit.php?id=' . (int) $pdo->lastInsertId());
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . APP_URL . '/invoices_admin.php');
    exit;
}

if ($canEdit && customers_account_columns_ready($pdo) && isset($_GET['apply_customer'])) {
    $nid = (int) $_GET['apply_customer'];
    if ($nid > 0) {
        $chk = $pdo->prepare('SELECT id FROM customers WHERE id = ? AND is_active = 1');
        $chk->execute([$nid]);
        if ($chk->fetch()) {
            $st = $pdo->prepare('SELECT status FROM sales_invoices WHERE id = ? AND is_active = 1');
            $st->execute([$id]);
            if ($st->fetchColumn() === 'draft') {
                $pdo->prepare(
                    'UPDATE sales_invoices SET customer_id = ? WHERE id = ? AND status = \'draft\''
                )->execute([$nid, $id]);
                $_SESSION['invoice_flash'] = [
                    'type' => 'success',
                    'msg'  => 'Customer linked to this draft.',
                ];
            }
        }
        header('Location: ' . APP_URL . '/invoice_edit.php?id=' . $id);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $_SESSION['invoice_flash'] = ['type' => 'danger', 'msg' => 'Security token invalid.'];
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'save_draft') {
                $chkInv = $pdo->prepare('SELECT status FROM sales_invoices WHERE id = ? AND is_active = 1');
                $chkInv->execute([$id]);
                $stInv = $chkInv->fetchColumn();
                if ($stInv !== 'draft') {
                    throw new RuntimeException('Only drafts can be updated this way.');
                }
                $cid = (int) ($_POST['customer_id'] ?? 0);
                $cid = $cid > 0 ? $cid : null;
                $idt = trim((string) ($_POST['invoice_date'] ?? ''));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $idt)) {
                    $idt = $todayJhb;
                }
                $dueRaw = trim((string) ($_POST['due_date'] ?? ''));
                $due    = ($dueRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueRaw)) ? $dueRaw : null;
                $notes  = trim((string) ($_POST['notes'] ?? ''));

                if ($cid !== null) {
                    $chk = $pdo->prepare('SELECT id FROM customers WHERE id = ? AND is_active = 1');
                    $chk->execute([$cid]);
                    if (!$chk->fetch()) {
                        throw new RuntimeException('Customer not found or inactive.');
                    }
                }

                $up = $pdo->prepare(
                    'UPDATE sales_invoices SET customer_id = ?, invoice_date = ?, due_date = ?, notes = ? WHERE id = ? AND status = \'draft\''
                );
                $up->execute([$cid, $idt, $due, $notes === '' ? null : $notes, $id]);
                $_SESSION['invoice_flash'] = ['type' => 'success', 'msg' => 'Draft saved.'];
            } elseif ($action === 'add_line_part') {
                $chkInv = $pdo->prepare('SELECT status FROM sales_invoices WHERE id = ? AND is_active = 1');
                $chkInv->execute([$id]);
                if ($chkInv->fetchColumn() !== 'draft') {
                    throw new RuntimeException('Only drafts accept new lines.');
                }
                $pid = (int) ($_POST['part_id'] ?? 0);
                $qty = max(1, (int) ($_POST['part_qty'] ?? 1));
                if ($pid <= 0) {
                    throw new RuntimeException('Enter a part ID (from All parts list).');
                }
                $pdo->beginTransaction();
                $pst = $pdo->prepare('SELECT * FROM parts WHERE id = ? AND is_active = 1 FOR UPDATE');
                $pst->execute([$pid]);
                $part = $pst->fetch(PDO::FETCH_ASSOC);
                if (!$part) {
                    $pdo->rollBack();
                    throw new RuntimeException('Part not found.');
                }
                $part = array_change_key_case($part, CASE_LOWER);
                if (($part['status'] ?? '') !== 'available') {
                    $pdo->rollBack();
                    throw new RuntimeException('Part must be status Available (currently: ' . ($part['status'] ?? '') . ').');
                }
                if ($qty > (int) $part['qty_on_hand']) {
                    $pdo->rollBack();
                    throw new RuntimeException('Quantity exceeds stock on hand (' . (int) $part['qty_on_hand'] . ').');
                }
                // Default to the part's asking price; allow the staff member
                // to override at line-add time (e.g. negotiated discount).
                $unit = round((float) $part['asking_price'], 2);
                $unitOverrideRaw = $_POST['unit_price_override'] ?? '';
                if ($unitOverrideRaw !== '' && is_numeric($unitOverrideRaw)) {
                    $unitOverride = round((float) $unitOverrideRaw, 2);
                    if ($unitOverride >= 0) {
                        $unit = $unitOverride;
                    }
                }
                $vr   = round((float) $part['vat_rate'], 2);
                $sub  = round($qty * $unit, 2);
                $vat  = round($sub * ($vr / 100), 2);
                $tot  = round($sub + $vat, 2);
                $desc = $part['name'] . ' · ' . $part['sku'];
                $mx   = (int) $pdo->query(
                    'SELECT COALESCE(MAX(sort_order),0) FROM sales_invoice_lines WHERE invoice_id = ' . (int) $id
                )->fetchColumn();
                $ins = $pdo->prepare(
                    'INSERT INTO sales_invoice_lines
                     (invoice_id, part_id, line_description, qty, unit_price_ex_vat, vat_rate,
                      line_subtotal_ex, line_vat, line_total_inc, sort_order)
                     VALUES (?,?,?,?,?,?,?,?,?,?)'
                );
                $ins->execute([$id, $pid, $desc, $qty, $unit, $vr, $sub, $vat, $tot, $mx + 1]);
                pos_recompute_totals($pdo, $id);
                $pdo->commit();
                $_SESSION['invoice_flash'] = ['type' => 'success', 'msg' => 'Part line added.'];
            } elseif ($action === 'add_line_manual') {
                $chkInv = $pdo->prepare('SELECT status FROM sales_invoices WHERE id = ? AND is_active = 1');
                $chkInv->execute([$id]);
                if ($chkInv->fetchColumn() !== 'draft') {
                    throw new RuntimeException('Only drafts accept new lines.');
                }
                $desc = trim((string) ($_POST['manual_desc'] ?? ''));
                if ($desc === '') {
                    throw new RuntimeException('Enter a description for the line.');
                }
                $qty = max(1, (int) ($_POST['manual_qty'] ?? 1));
                $upx = trim((string) ($_POST['manual_unit'] ?? ''));
                $unit = round((float) preg_replace('/[^\d.]/', '', $upx), 2);
                $vr = round((float) ($_POST['manual_vat'] ?? 0), 2);
                if ($vr < 0 || $vr > 100) {
                    $vr = 0;
                }
                $sub = round($qty * $unit, 2);
                $vat = round($sub * ($vr / 100), 2);
                $tot = round($sub + $vat, 2);
                $mx  = (int) $pdo->query(
                    'SELECT COALESCE(MAX(sort_order),0) FROM sales_invoice_lines WHERE invoice_id = ' . (int) $id
                )->fetchColumn();
                $ins = $pdo->prepare(
                    'INSERT INTO sales_invoice_lines
                     (invoice_id, part_id, line_description, qty, unit_price_ex_vat, vat_rate,
                      line_subtotal_ex, line_vat, line_total_inc, sort_order)
                     VALUES (?,NULL,?,?,?,?,?,?,?,?)'
                );
                $ins->execute([$id, $desc, $qty, $unit, $vr, $sub, $vat, $tot, $mx + 1]);
                pos_recompute_totals($pdo, $id);
                $_SESSION['invoice_flash'] = ['type' => 'success', 'msg' => 'Manual line added.'];
            } elseif ($action === 'remove_line') {
                $chkInv = $pdo->prepare('SELECT status FROM sales_invoices WHERE id = ? AND is_active = 1');
                $chkInv->execute([$id]);
                if ($chkInv->fetchColumn() !== 'draft') {
                    throw new RuntimeException('Only drafts can remove lines.');
                }
                $lid = (int) ($_POST['line_id'] ?? 0);
                $d   = $pdo->prepare('DELETE FROM sales_invoice_lines WHERE id = ? AND invoice_id = ?');
                $d->execute([$lid, $id]);
                pos_recompute_totals($pdo, $id);
                $_SESSION['invoice_flash'] = ['type' => 'success', 'msg' => 'Line removed.'];
            } elseif ($action === 'update_line_price') {
                // Edit a single line's unit price (ex VAT) while still in draft.
                // Recomputes that line's subtotal / VAT / total, then refreshes
                // the invoice grand totals.
                $chkInv = $pdo->prepare('SELECT status FROM sales_invoices WHERE id = ? AND is_active = 1');
                $chkInv->execute([$id]);
                if ($chkInv->fetchColumn() !== 'draft') {
                    throw new RuntimeException('Only drafts can edit prices.');
                }
                $lid     = (int) ($_POST['line_id'] ?? 0);
                $newRaw  = $_POST['unit_price_ex_vat'] ?? '';
                if ($lid <= 0 || !is_numeric($newRaw)) {
                    throw new RuntimeException('Bad line id or price.');
                }
                $newUnit = round((float) $newRaw, 2);
                if ($newUnit < 0) {
                    throw new RuntimeException('Price cannot be negative.');
                }
                $cur = $pdo->prepare(
                    'SELECT qty, vat_rate FROM sales_invoice_lines WHERE id = ? AND invoice_id = ?'
                );
                $cur->execute([$lid, $id]);
                $row = $cur->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    throw new RuntimeException('Line not found.');
                }
                $qty = (int) $row['qty'];
                $vr  = round((float) $row['vat_rate'], 2);
                $sub = round($qty * $newUnit, 2);
                $vat = round($sub * ($vr / 100), 2);
                $tot = round($sub + $vat, 2);
                $up = $pdo->prepare(
                    'UPDATE sales_invoice_lines
                        SET unit_price_ex_vat = ?, line_subtotal_ex = ?, line_vat = ?, line_total_inc = ?
                      WHERE id = ? AND invoice_id = ?'
                );
                $up->execute([$newUnit, $sub, $vat, $tot, $lid, $id]);
                pos_recompute_totals($pdo, $id);
                $_SESSION['invoice_flash'] = ['type' => 'success', 'msg' => 'Line price updated.'];
            } elseif ($action === 'finalize') {
                $pdo->beginTransaction();
                $invSt2 = $pdo->prepare('SELECT * FROM sales_invoices WHERE id = ? FOR UPDATE');
                $invSt2->execute([$id]);
                $rowNow = $invSt2->fetch(PDO::FETCH_ASSOC);
                if (!$rowNow || $rowNow['status'] !== 'draft') {
                    $pdo->rollBack();
                    throw new RuntimeException('Invoice is not a draft.');
                }
                $custId = !empty($rowNow['customer_id']) ? (int) $rowNow['customer_id'] : null;
                if ($custId === null || $custId <= 0) {
                    $pdo->rollBack();
                    throw new RuntimeException('Select a customer (save draft) before finalizing.');
                }
                $lineSt2 = $pdo->prepare(
                    'SELECT sil.*, p.source, p.condition_grade, p.status AS pstatus, p.qty_on_hand, p.sku, p.name, p.is_active AS pis_active
                     FROM sales_invoice_lines sil
                     LEFT JOIN parts p ON p.id = sil.part_id
                     WHERE sil.invoice_id = ?'
                );
                $lineSt2->execute([$id]);
                $lrows = $lineSt2->fetchAll(PDO::FETCH_ASSOC);
                if (!$lrows) {
                    $pdo->rollBack();
                    throw new RuntimeException('Add at least one line before finalizing.');
                }

                $cst = $pdo->prepare('SELECT * FROM customers WHERE id = ? AND is_active = 1');
                $cst->execute([$custId]);
                $custRow = $cst->fetch(PDO::FETCH_ASSOC);
                if (!$custRow) {
                    $pdo->rollBack();
                    throw new RuntimeException('Customer not found.');
                }
                $custRow = array_change_key_case($custRow, CASE_LOWER);

                $needShga = false;
                foreach ($lrows as $lr) {
                    if (empty($lr['part_id'])) {
                        continue;
                    }
                    if (pos_part_requires_shga_buyer_docs($lr)) {
                        $needShga = true;
                        break;
                    }
                }
                if ($needShga && !pos_customer_buyer_compliance_ok($custRow)) {
                    $pdo->rollBack();
                    throw new RuntimeException(
                        'Second-hand / stripped lines require customer ID copy and proof of address on file '
                        . '(Customers → edit → compliance docs).'
                    );
                }

                $dueRaw = $rowNow['due_date'] ?? null;
                if (customers_account_columns_ready($pdo)
                    && $dueRaw !== null && $dueRaw !== '' && $dueRaw !== '0000-00-00'
                ) {
                    if ((int) ($custRow['account_customer'] ?? 0) !== 1) {
                        $pdo->rollBack();
                        throw new RuntimeException(
                            'Due date is set (on-account sale). Mark this buyer as an account customer '
                            . '(Master data → Customers → edit → Account customer), or clear the due date for cash sales.'
                        );
                    }
                }

                foreach ($lrows as $lr) {
                    if (empty($lr['part_id'])) {
                        continue;
                    }
                    $pid = (int) $lr['part_id'];
                    $qty = (int) $lr['qty'];
                    $pst = $pdo->prepare(
                        'SELECT id, qty_on_hand, status, is_active, sku FROM parts WHERE id = ? FOR UPDATE'
                    );
                    $pst->execute([$pid]);
                    $part = $pst->fetch(PDO::FETCH_ASSOC);
                    if (!$part || (int) $part['is_active'] === 0) {
                        $pdo->rollBack();
                        throw new RuntimeException('Part #' . $pid . ' no longer available.');
                    }
                    if (($part['status'] ?? '') !== 'available') {
                        $pdo->rollBack();
                        throw new RuntimeException('Part ' . ($part['sku'] ?? (string) $pid) . ' is not Available.');
                    }
                    $oh = (int) $part['qty_on_hand'];
                    if ($qty > $oh) {
                        $pdo->rollBack();
                        throw new RuntimeException('Insufficient stock for part ' . ($part['sku'] ?? (string) $pid) . '.');
                    }
                    $noh = $oh - $qty;
                    if ($noh <= 0) {
                        $upP = $pdo->prepare(
                            "UPDATE parts SET qty_on_hand = 0, status = 'sold' WHERE id = ?"
                        );
                        $upP->execute([$pid]);
                    } else {
                        $upP = $pdo->prepare('UPDATE parts SET qty_on_hand = ? WHERE id = ?');
                        $upP->execute([$noh, $pid]);
                    }
                }

                pos_recompute_totals($pdo, $id);
                $invNo = pos_next_invoice_no($pdo);
                $fin = $pdo->prepare(
                    "UPDATE sales_invoices SET status = 'final', invoice_no = ?, finalized_at = NOW() WHERE id = ?"
                );
                $fin->execute([$invNo, $id]);
                $pdo->commit();
                $_SESSION['invoice_flash'] = [
                    'type' => 'success',
                    'msg'  => 'Invoice finalized: ' . $invNo,
                ];
            } elseif ($action === 'add_payment') {
                $amtRaw = trim((string) ($_POST['pay_amount'] ?? ''));
                $amt    = round((float) preg_replace('/[^\d.]/', '', $amtRaw), 2);
                if ($amt <= 0) {
                    throw new RuntimeException('Enter a payment amount.');
                }
                $paidAt = trim((string) ($_POST['pay_paid_at'] ?? ''));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidAt)) {
                    $paidAt = $todayJhb;
                }
                $method = strtolower(trim((string) ($_POST['pay_method'] ?? 'cash')));
                if (!in_array($method, ['cash', 'eft', 'card', 'other'], true)) {
                    $method = 'cash';
                }
                $ref  = trim((string) ($_POST['pay_ref'] ?? ''));
                $note = trim((string) ($_POST['pay_note'] ?? ''));

                $pdo->beginTransaction();
                $st0 = $pdo->prepare(
                    'SELECT total_inc_vat FROM sales_invoices WHERE id = ? AND status = \'final\' FOR UPDATE'
                );
                $st0->execute([$id]);
                $trow = $st0->fetch(PDO::FETCH_ASSOC);
                if (!$trow) {
                    $pdo->rollBack();
                    throw new RuntimeException('Invoice is not final.');
                }
                $credOnInv = cn_tables_ready($pdo) ? cn_finalized_total_for_invoice($pdo, $id) : 0.0;
                $pst = $pdo->prepare(
                    'SELECT COALESCE(SUM(amount),0) FROM sales_invoice_payments
                     WHERE invoice_id = ? AND is_active = 1'
                );
                $pst->execute([$id]);
                $already = (float) $pst->fetchColumn();
                $rem     = (float) $trow['total_inc_vat'] - $credOnInv - $already;
                if ($amt > round($rem, 2) + 0.005) {
                    $pdo->rollBack();
                    throw new RuntimeException(
                        'Payment exceeds balance. Remaining: R ' . number_format($rem, 2)
                    );
                }
                $ins = $pdo->prepare(
                    'INSERT INTO sales_invoice_payments
                     (invoice_id, amount, paid_at, payment_method, reference_note, notes, created_by)
                     VALUES (?,?,?,?,?,?,?)'
                );
                $ins->execute([
                    $id,
                    $amt,
                    $paidAt,
                    $method,
                    $ref === '' ? null : $ref,
                    $note === '' ? null : $note,
                    $uid > 0 ? $uid : null,
                ]);
                $pdo->commit();
                $_SESSION['invoice_flash'] = ['type' => 'success', 'msg' => 'Payment recorded.'];
            } elseif ($action === 'toggle_payment') {
                $chkInv = $pdo->prepare('SELECT status FROM sales_invoices WHERE id = ? AND is_active = 1');
                $chkInv->execute([$id]);
                if ($chkInv->fetchColumn() !== 'final') {
                    throw new RuntimeException('Payments only on final invoices.');
                }
                $payId = (int) ($_POST['payment_id'] ?? 0);
                $pdo->prepare(
                    'UPDATE sales_invoice_payments SET is_active = 0 WHERE id = ? AND invoice_id = ?'
                )->execute([$payId, $id]);
                $_SESSION['invoice_flash'] = ['type' => 'success', 'msg' => 'Payment line removed.'];
            } elseif ($action === 'void_invoice') {
                $pdo->prepare("UPDATE sales_invoices SET status = 'void' WHERE id = ? AND status = 'final'")
                    ->execute([$id]);
                $_SESSION['invoice_flash'] = [
                    'type' => 'warning',
                    'msg'  => 'Invoice voided. Stock is NOT auto-reversed — correct parts manually if needed.',
                ];
            } else {
                throw new RuntimeException('Unknown action.');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['invoice_flash'] = ['type' => 'danger', 'msg' => $e->getMessage()];
        }
    }
    header('Location: ' . APP_URL . '/invoice_edit.php?id=' . $id);
    exit;
}

$flash = $_SESSION['invoice_flash'] ?? ['type' => null, 'msg' => null];
unset($_SESSION['invoice_flash']);

$invSt = $pdo->prepare('SELECT * FROM sales_invoices WHERE id = ? AND is_active = 1');
$invSt->execute([$id]);
$inv = $invSt->fetch(PDO::FETCH_ASSOC);
if (!$inv) {
    http_response_code(404);
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-danger">Invoice not found.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
$inv = array_change_key_case($inv, CASE_LOWER);

$lineSt = $pdo->prepare(
    'SELECT sil.*, p.sku, p.source, p.condition_grade, p.status AS part_status
     FROM sales_invoice_lines sil
     LEFT JOIN parts p ON p.id = sil.part_id
     WHERE sil.invoice_id = ?
     ORDER BY sil.sort_order ASC, sil.id ASC'
);
$lineSt->execute([$id]);
$lines = $lineSt->fetchAll(PDO::FETCH_ASSOC);

$payRows = [];
if (($inv['status'] ?? '') === 'final') {
    $pst = $pdo->prepare(
        'SELECT * FROM sales_invoice_payments
         WHERE invoice_id = ? AND is_active = 1
         ORDER BY paid_at DESC, id DESC'
    );
    $pst->execute([$id]);
    $payRows = $pst->fetchAll(PDO::FETCH_ASSOC);
}

$paidSum = 0.0;
foreach ($payRows as $pr) {
    $paidSum += (float) $pr['amount'];
}
$credFinalSum = cn_tables_ready($pdo) ? cn_finalized_total_for_invoice($pdo, $id) : 0.0;
$credArSum    = cn_tables_ready($pdo) ? cn_finalized_ar_reduction_total_for_invoice($pdo, $id) : 0.0;
$credRefundSum = cn_tables_ready($pdo) ? cn_finalized_cash_refund_total_for_invoice($pdo, $id) : 0.0;
$balanceNum   = (float) $inv['total_inc_vat'] - $credFinalSum - $paidSum;

if (customers_account_columns_ready($pdo)) {
    $customers = $pdo->query(
        'SELECT id, name, account_customer FROM customers WHERE is_active = 1 ORDER BY name ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
} else {
    $customers = $pdo->query(
        'SELECT id, name FROM customers WHERE is_active = 1 ORDER BY name ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
}

$isDraft = ($inv['status'] ?? '') === 'draft';
$isFinal = ($inv['status'] ?? '') === 'final';
$isVoid  = ($inv['status'] ?? '') === 'void';

$heading = $inv['invoice_no']
    ? ('Invoice ' . $inv['invoice_no'])
    : ('Draft invoice #' . $id);

require_once __DIR__ . '/includes/header.php';

// =====================================================================
// Letterhead (printed at the top of the PDF / printed page).
//
//   - If assets/invoice-logo.png exists  -> built letterhead: one row with
//     phone on the LEFT, logo in the CENTRE, address on the RIGHT (light
//     background, dark text). Falls back to full PNG if logo file missing.
//   - Otherwise falls back to the old assets/invoice-letterhead.png
//     full banner (logo + cell + address baked into one image).
//
// To edit the cell number / address, change the constants below.
// =====================================================================
$letterheadRel = 'assets/invoice-letterhead.png';
$letterheadAbs = APP_ROOT . '/' . $letterheadRel;
$letterheadUrl = rtrim(APP_URL, '/') . '/' . $letterheadRel;
$letterheadOk  = is_file($letterheadAbs);

$logoRel = 'assets/invoice-logo.png';
$logoAbs = APP_ROOT . '/' . $logoRel;
$logoUrl = rtrim(APP_URL, '/') . '/' . $logoRel;
$logoOk  = is_file($logoAbs);

// Letterhead text. Edit these to change the printed contact details.
$letterheadCellLabel = 'Cell / WhatsApp';
$letterheadCellValue = '079 018 8097';
$letterheadAddress   = [
    '12 Sirdar Road, corner of Bacus Road',
    'Clairwood',
    'Durban South, KZN',
];
?>

<style id="aw-invoice-print-style">
  /* On screen the letterhead banner is visible (preview). Print rules tighten fonts. */
  .aw-invoice-shell {
    background: transparent;
    margin: 0;
    padding-bottom: 1rem;
  }
  .aw-invoice-letterhead-wrap {
    width: 100vw;
    max-width: 100vw;
    position: relative;
    left: 50%;
    right: 50%;
    margin-left: -50vw;
    margin-right: -50vw;
    margin-bottom: 0.75rem;
    padding: 0;
    line-height: 0;
    background: #fff;
    overflow: hidden;
    /* Visible on screen = live preview of the printed/PDF top strip. */
    display: block;
  }
  .aw-invoice-letterhead-img {
    width: 100%;
    height: auto;
    display: block;
    object-fit: contain;
    object-position: center top;
    vertical-align: top;
  }
  /* Single banner row: phone LEFT · logo CENTRE · address RIGHT (light strip). */
  .aw-letterhead-banner {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    background: #fff;
    color: #212529;
    padding: 8px 12px 10px;
    line-height: 1.25;
    box-sizing: border-box;
    width: 100%;
    border-bottom: 1px solid #dee2e6;
  }
  .aw-letterhead-banner .aw-letterhead-cell {
    flex: 0 1 30%;
    min-width: 0;
    text-align: left;
  }
  .aw-letterhead-banner .aw-letterhead-center {
    flex: 1 1 auto;
    min-width: 0;
    text-align: center;
    line-height: 0;
  }
  .aw-letterhead-banner .aw-letterhead-center img {
    max-height: 52px;
    width: auto;
    max-width: 100%;
    display: inline-block;
    vertical-align: middle;
    object-fit: contain;
  }
  .aw-letterhead-banner .aw-letterhead-addr {
    flex: 0 1 32%;
    min-width: 0;
    text-align: right;
    font-size: 6.5pt;
    line-height: 1.3;
    color: #212529;
  }
  .aw-letterhead-banner .aw-lh-label {
    display: block;
    font-size: 5.5pt;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 1px;
  }
  .aw-letterhead-banner .aw-lh-value {
    font-weight: 700;
    font-size: 7.5pt;
    word-break: break-word;
  }
  .aw-letterhead-banner .aw-letterhead-addr > div {
    font-size: 6.5pt;
    word-break: break-word;
  }
  .aw-invoice-print-body {
    padding: 0;
    max-width: none;
    margin: 0;
  }
  /* Running total next to “Lines” — easy to see while adding parts */
  .aw-running-total-incl {
    line-height: 1.15;
    letter-spacing: 0.02em;
  }
  /* Slightly larger type on monitor so the banner is easy to check;
     print/PDF still uses the tighter sizes in @media print. */
  @media screen {
    .aw-letterhead-banner {
      padding: 10px 14px 12px;
    }
    .aw-letterhead-banner .aw-lh-label {
      font-size: 0.62rem;
    }
    .aw-letterhead-banner .aw-lh-value {
      font-size: 0.95rem;
    }
    .aw-letterhead-banner .aw-letterhead-addr,
    .aw-letterhead-banner .aw-letterhead-addr > div {
      font-size: 0.72rem;
    }
    .aw-letterhead-banner .aw-letterhead-center img {
      max-height: 56px;
    }
  }
  .aw-invoice-doc-title {
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #dee2e6;
  }
  @media print {
    @page { margin: 0; }
    html, body { background: #fff !important; }
    body > nav.navbar { display: none !important; }
    body > main.container-fluid {
      padding: 0 !important;
      max-width: none !important;
      margin: 0 !important;
    }
    .aw-invoice-shell {
      margin: 0 !important;
      padding: 0 !important;
    }
    .aw-invoice-letterhead-wrap {
      display: block !important;
      width: 100% !important;
      max-width: none !important;
      left: auto !important;
      right: auto !important;
      margin-left: 0 !important;
      margin-right: 0 !important;
      height: auto !important;
      break-inside: avoid;
      page-break-inside: avoid;
      background: #fff !important;
    }
    .aw-invoice-letterhead-wrap .aw-letterhead-banner {
      width: 100% !important;
      max-width: none !important;
      box-sizing: border-box !important;
      flex-wrap: nowrap !important;
      padding: 6px 10px 8px !important;
      gap: 8px !important;
      background: #fff !important;
      background-color: #fff !important;
      color: #212529 !important;
      border-bottom: 1px solid #dee2e6 !important;
    }
    .aw-invoice-letterhead-wrap .aw-letterhead-banner .aw-letterhead-cell {
      flex: 0 1 28% !important;
    }
    .aw-invoice-letterhead-wrap .aw-letterhead-banner .aw-letterhead-center img {
      max-height: 14mm !important;
      width: auto !important;
      max-width: 100% !important;
      display: inline-block !important;
      object-fit: contain !important;
    }
    .aw-invoice-letterhead-wrap .aw-letterhead-banner .aw-letterhead-addr {
      flex: 0 1 30% !important;
      font-size: 6pt !important;
      color: #212529 !important;
    }
    .aw-invoice-letterhead-wrap .aw-letterhead-banner .aw-lh-label {
      font-size: 5pt !important;
      color: #6c757d !important;
    }
    .aw-invoice-letterhead-wrap .aw-letterhead-banner .aw-lh-value {
      font-size: 7pt !important;
      color: #212529 !important;
    }
    .aw-invoice-letterhead-wrap .aw-letterhead-banner .aw-letterhead-addr > div {
      font-size: 6pt !important;
      color: #212529 !important;
    }
    .aw-invoice-letterhead-img {
      width: 100% !important;
      max-width: 100% !important;
      height: auto !important;
      object-fit: contain !important;
      object-position: center top !important;
    }
    .aw-invoice-print-body {
      max-width: none !important;
      padding: 8mm 10mm 10mm !important;
    }
    /* Flat cards for the printed page (was applied on-screen too — now print-only) */
    .aw-invoice-print-body .card {
      box-shadow: none !important;
      border-radius: 0 !important;
      border: 1px solid #dee2e6 !important;
    }
    .aw-invoice-print-body .card-header {
      border-radius: 0 !important;
    }
    /* Screen uses dark section headers; print = light for readability & ink */
    .aw-invoice-print-body .card-header.bg-light {
      background: #f8f9fa !important;
      color: #212529 !important;
      border-bottom: 1px solid #dee2e6 !important;
      letter-spacing: normal;
    }
    .aw-invoice-print-body .card-header.bg-light .text-muted {
      color: #6c757d !important;
    }
    .no-print, .no-print * { display: none !important; }
    footer, footer * { display: none !important; }
    a[href]:after { content: none !important; }
  }
</style>

<div class="aw-invoice-shell">

<?php if ($logoOk): ?>
<div class="aw-invoice-letterhead-wrap">
  <div class="aw-letterhead-banner">
    <div class="aw-letterhead-cell">
      <span class="aw-lh-label"><?= e($letterheadCellLabel) ?></span>
      <span class="aw-lh-value"><?= e($letterheadCellValue) ?></span>
    </div>
    <div class="aw-letterhead-center">
      <img src="<?= e($logoUrl) ?>" alt="AUTO WAGEN">
    </div>
    <div class="aw-letterhead-addr">
      <?php foreach ($letterheadAddress as $line): ?>
        <div><?= e($line) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php elseif ($letterheadOk): ?>
<div class="aw-invoice-letterhead-wrap">
  <img src="<?= e($letterheadUrl) ?>"
       alt="AUTO WAGEN — NEW AND USED SPARES"
       class="aw-invoice-letterhead-img"
       width="1920"
       height="360">
</div>
<?php endif; ?>

<div class="aw-invoice-print-body">
<div class="aw-invoice-doc-title">
  <h1 class="h5 mb-1"><?= e($heading) ?></h1>
  <?php if ($isVoid): ?>
    <p class="small text-danger mb-0">
      Void<?= !empty($inv['invoice_no']) ? ' · ' . e((string) $inv['invoice_no']) : '' ?>
      · <?= e((string) $inv['invoice_date']) ?>
    </p>
  <?php elseif (!empty($inv['invoice_no'])): ?>
    <p class="small text-muted mb-0">Tax invoice · <?= e((string) $inv['invoice_date']) ?></p>
  <?php elseif ($isDraft): ?>
    <p class="small text-muted mb-0">Draft · <?= e((string) ($inv['invoice_date'] ?? $todayJhb)) ?></p>
  <?php endif; ?>
</div>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3 no-print">
  <p class="text-muted small mb-0">
    <?php if ($isDraft): ?>Add lines, pick customer, then <strong>Finalize</strong>. Parts must be <strong>Available</strong>.<?php endif; ?>
    <?php if ($isFinal): ?>Use <strong>Print</strong> for a paper/PDF copy. Record payments below.<?php endif; ?>
    <?php if ($isVoid): ?>This invoice is <strong>void</strong>.<?php endif; ?>
  </p>
  <a class="btn btn-sm btn-outline-secondary" href="<?= e(APP_URL) ?>/invoices_admin.php">All invoices</a>
</div>

<?php if (!empty($flash['msg'])): ?>
  <div class="alert alert-<?= e($flash['type'] ?? 'info') ?> py-2 no-print"><?= e($flash['msg']) ?></div>
<?php endif; ?>

<div class="row g-3">
  <div class="<?php echo ($isDraft && $canEdit) ? 'col-lg-8' : 'col-lg-12'; ?>">

    <?php if ($isDraft && $canEdit): ?>
    <form method="post" class="card shadow-sm mb-3 no-print">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_draft">
      <div class="card-header bg-light"><strong>Customer &amp; dates</strong></div>
      <div class="card-body row g-2">
        <div class="col-md-6">
          <label class="form-label small">Customer</label>
          <select name="customer_id" class="form-select">
            <option value="0">— select —</option>
            <?php foreach ($customers as $c):
              $acct = isset($c['account_customer']) && (int) $c['account_customer'] === 1;
              $lbl  = $c['name'] . ($acct ? ' · Account' : '');
              ?>
              <option value="<?= (int) $c['id'] ?>" <?= (int) ($inv['customer_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                <?= e($lbl) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="mt-1">
            <a class="small" href="<?= e(APP_URL) ?>/customer_quick_add.php?return_invoice=<?= (int) $id ?>">
              <i class="bi bi-person-plus"></i> New customer (quick)
            </a>
            <span class="text-muted small"> — full record &amp; SHGA scans: Master data → Customers.</span>
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label small">Invoice date</label>
          <input type="date" name="invoice_date" class="form-control" required
                 value="<?= e((string) ($inv['invoice_date'] ?? $todayJhb)) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label small">Due date <span class="text-muted">(on account)</span></label>
          <input type="date" name="due_date" class="form-control"
                 value="<?= !empty($inv['due_date']) && $inv['due_date'] !== '0000-00-00' ? e((string) $inv['due_date']) : '' ?>">
          <?php if (customers_account_columns_ready($pdo)): ?>
            <div class="form-text small">Finalize requires an <strong>account customer</strong> if this date is set.</div>
          <?php endif; ?>
        </div>
        <div class="col-12">
          <label class="form-label small">Notes</label>
          <textarea name="notes" class="form-control" rows="2"><?= e((string) ($inv['notes'] ?? '')) ?></textarea>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-danger"><i class="bi bi-check-lg"></i> Save draft</button>
        </div>
      </div>
    </form>
    <?php elseif ($isDraft && !$canEdit): ?>
      <div class="alert alert-light border">Read-only: you cannot edit this draft.</div>
    <?php else: ?>
      <div class="card shadow-sm mb-3">
        <div class="card-body small">
          <strong>Customer:</strong>
          <?php
          $cn = '—';
          foreach ($customers as $c) {
              if ((int) $c['id'] === (int) ($inv['customer_id'] ?? 0)) {
                  $cn = $c['name'] . ((isset($c['account_customer']) && (int) $c['account_customer'] === 1) ? ' · Account' : '');
                  break;
              }
          }
          echo e($cn);
          ?>
          · <strong>Date:</strong> <?= e((string) $inv['invoice_date']) ?>
          <?php if (!empty($inv['due_date']) && $inv['due_date'] !== '0000-00-00'): ?>
            · <strong>Due:</strong> <?= e((string) $inv['due_date']) ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
        <strong>Lines</strong>
        <span class="badge bg-secondary aw-running-total-incl fs-4 fw-bold px-4 py-2">R <?= number_format((float) $inv['total_inc_vat'], 2) ?> incl. VAT</span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead class="table-light small">
            <tr>
              <th>Description</th>
              <th class="text-end">Qty</th>
              <th class="text-end">Ex VAT</th>
              <th class="text-end">VAT</th>
              <th class="text-end">Total</th>
              <?php if ($isDraft && $canEdit): ?><th class="no-print"></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (!$lines): ?>
              <tr><td colspan="<?= ($isDraft && $canEdit) ? '6' : '5' ?>" class="text-muted">No lines yet.</td></tr>
            <?php else: ?>
              <?php foreach ($lines as $ln): ?>
                <tr>
                  <td>
                    <?= e($ln['line_description']) ?>
                    <?php if (!empty($ln['part_id']) && pos_part_requires_shga_buyer_docs($ln)): ?>
                      <span class="badge bg-warning text-dark ms-1">SHGA</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end"><?= (int) $ln['qty'] ?></td>
                  <td class="text-end">R <?= number_format((float) $ln['line_subtotal_ex'], 2) ?></td>
                  <td class="text-end">R <?= number_format((float) $ln['line_vat'], 2) ?></td>
                  <td class="text-end">R <?= number_format((float) $ln['line_total_inc'], 2) ?></td>
                  <?php if ($isDraft && $canEdit): ?>
                    <td class="no-print" style="white-space:nowrap;">
                      <form method="post" class="d-inline aw-edit-price-form">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_line_price">
                        <input type="hidden" name="line_id" value="<?= (int) $ln['id'] ?>">
                        <input type="hidden" name="unit_price_ex_vat" value="">
                        <button type="button" class="btn btn-sm btn-outline-secondary aw-edit-price-btn"
                                data-current="<?= number_format((float) $ln['unit_price_ex_vat'], 2, '.', '') ?>"
                                title="Edit unit price">
                          <i class="bi bi-pencil"></i>
                        </button>
                      </form>
                      <form method="post" class="d-inline" onsubmit="return confirm('Remove this line?');">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="remove_line">
                        <input type="hidden" name="line_id" value="<?= (int) $ln['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">×</button>
                      </form>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($isDraft && $canEdit): ?>
    <div class="row g-3 mb-3 no-print">
      <div class="col-md-6">
        <div class="card shadow-sm h-100">
          <div class="card-header bg-light small"><strong>Add part</strong> (search by name / SKU / vehicle)</div>
          <div class="card-body">
            <form method="post" class="row g-2" id="aw-add-part-form">
              <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="add_line_part">
              <input type="hidden" name="part_id" id="aw-pick-part-id" value="" required>

              <div class="col-12">
                <button type="button" class="btn btn-outline-secondary w-100 text-start"
                        id="aw-pick-trigger"
                        data-bs-toggle="modal" data-bs-target="#partSearchModal">
                  <i class="bi bi-search me-1"></i>
                  <span id="aw-pick-label" class="text-muted">Select item&hellip;</span>
                </button>
              </div>

              <div class="col-12 d-none small text-muted" id="aw-pick-summary"></div>

              <div class="col-4 d-none" id="aw-pick-qty-wrap">
                <label class="form-label small mb-1">Qty</label>
                <input type="number" name="part_qty" id="aw-pick-qty"
                       class="form-control form-control-sm" min="1" value="1">
              </div>
              <div class="col-4 d-none" id="aw-pick-price-wrap">
                <label class="form-label small mb-1">Price ex VAT</label>
                <input type="number" name="unit_price_override" id="aw-pick-price"
                       class="form-control form-control-sm" min="0" step="0.01" value="">
              </div>
              <div class="col-4 d-none" id="aw-pick-add-wrap">
                <label class="form-label small mb-1">&nbsp;</label>
                <button type="submit" class="btn btn-danger btn-sm w-100">
                  <i class="bi bi-plus-lg"></i> Add to invoice
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card shadow-sm h-100">
          <div class="card-header bg-light small"><strong>Manual line</strong> (labour, fee, etc.)</div>
          <div class="card-body">
            <form method="post" class="row g-2 small">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="add_line_manual">
              <div class="col-12">
                <input type="text" name="manual_desc" class="form-control form-control-sm" placeholder="Description" required>
              </div>
              <div class="col-4">
                <input type="number" name="manual_qty" class="form-control form-control-sm" min="1" value="1">
              </div>
              <div class="col-4">
                <input type="text" name="manual_unit" class="form-control form-control-sm" placeholder="Price ex VAT">
              </div>
              <div class="col-4">
                <input type="number" name="manual_vat" class="form-control form-control-sm" min="0" max="100" step="0.01" value="0" placeholder="VAT %">
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-sm btn-outline-primary">Add manual line</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <form method="post" class="mb-4 no-print" onsubmit="return confirm('Finalize? Stock will be reduced and parts marked sold when quantity reaches zero.');">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="finalize">
      <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-lock"></i> Finalize invoice</button>
    </form>
    <?php endif; ?>

    <?php if ($isVoid): ?>
    <div class="alert alert-secondary">Void invoice — no further payments. Correct stock in <strong>All parts</strong> if this was a mistake.</div>
    <?php endif; ?>

    <?php if ($isFinal): ?>
    <div class="card shadow-sm border-primary mb-3" id="payments">
      <div class="card-header bg-light d-flex justify-content-between flex-wrap">
        <strong><i class="bi bi-cash-stack"></i> Payments (ZAR)</strong>
        <span class="small">
          <?php if ($credFinalSum > 0.005): ?>
            Credits (final):
            <?php if ($credArSum > 0.005 && $credRefundSum > 0.005): ?>
              AR <strong class="text-danger">R <?= number_format($credArSum, 2) ?></strong>
              · Refund <strong class="text-danger">R <?= number_format($credRefundSum, 2) ?></strong>
              · Total <strong class="text-danger">R <?= number_format($credFinalSum, 2) ?></strong>
            <?php else: ?>
              <strong class="text-danger">R <?= number_format($credFinalSum, 2) ?></strong>
              <?php if ($credArSum > 0.005): ?>
                <span class="text-muted">(AR reduction)</span>
              <?php elseif ($credRefundSum > 0.005): ?>
                <span class="text-muted">(cash refund)</span>
              <?php endif; ?>
            <?php endif; ?>
             ·
          <?php endif; ?>
          Remaining: <strong class="text-danger">R <?= number_format(max(0, $balanceNum), 2) ?></strong>
          · Paid: R <?= number_format($paidSum, 2) ?>
        </span>
      </div>
      <div class="card-body">
        <?php if ($payRows): ?>
          <table class="table table-sm mb-3">
            <thead class="table-light small"><tr><th>Date</th><th class="text-end">Amount</th><th>Method</th><th>Ref</th><th class="no-print"></th></tr></thead>
            <tbody>
              <?php foreach ($payRows as $pr): ?>
                <tr>
                  <td><?= e($pr['paid_at']) ?></td>
                  <td class="text-end">R <?= number_format((float) $pr['amount'], 2) ?></td>
                  <td class="text-capitalize small"><?= e($pr['payment_method']) ?></td>
                  <td class="small text-muted"><?= e($pr['reference_note'] ?? '') ?></td>
                  <td class="no-print">
                    <?php if ($canEdit): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Remove this payment?');">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="toggle_payment">
                      <input type="hidden" name="payment_id" value="<?= (int) $pr['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">×</button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="text-muted small mb-3">No payments yet.</p>
        <?php endif; ?>

        <?php if ($canEdit && $balanceNum > 0.005): ?>
        <form method="post" class="row g-2 align-items-end small no-print">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="add_payment">
          <div class="col-md-2">
            <label class="form-label">Amount</label>
            <input name="pay_amount" class="form-control" required inputmode="decimal">
          </div>
          <div class="col-md-2">
            <label class="form-label">Paid on</label>
            <input type="date" name="pay_paid_at" class="form-control" value="<?= e($todayJhb) ?>" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Method</label>
            <select name="pay_method" class="form-select">
              <option value="cash">Cash</option>
              <option value="eft">EFT</option>
              <option value="card">Card</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Ref (optional)</label>
            <input name="pay_ref" class="form-control" placeholder="Bank ref">
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary">Add payment</button>
          </div>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3 no-print">
      <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
      <?php if ($canEdit && cn_tables_ready($pdo)): ?>
        <a class="btn btn-outline-danger" href="<?= e(APP_URL) ?>/credit_note_edit.php?new=1&amp;invoice_id=<?= (int) $id ?>">
          <i class="bi bi-arrow-counterclockwise"></i> New credit note
        </a>
      <?php endif; ?>
      <?php if ($canEdit): ?>
      <form method="post" class="d-inline" onsubmit="return confirm('Void this invoice? Stock is NOT returned automatically.');">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="void_invoice">
        <button type="submit" class="btn btn-outline-dark">Void invoice</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>

  <?php if ($isDraft && $canEdit): ?>
  <div class="col-lg-4 no-print">
    <div class="card shadow-sm border-warning">
      <div class="card-header bg-warning bg-opacity-25 small"><strong>SHGA</strong></div>
      <div class="card-body small">
        <p class="mb-2">If any line is <strong>stripped</strong> or <strong>not new</strong> condition, the customer must have <strong>ID doc + proof of address</strong> on file.</p>
        <?php
        $awCustFromInv = 'invoice_edit.php?id=' . (int) $id;
        $awCustListHref = rtrim(APP_URL, '/') . '/customers_admin.php?' . http_build_query(['return' => $awCustFromInv]);
        ?>
        <a href="<?= e($awCustListHref) ?>" class="btn btn-sm btn-outline-danger">Open customers</a>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
</div>
</div>
<!-- end .aw-invoice-shell -->

<?php if ($isDraft && $canEdit): ?>
<!-- ============================================================
     POS "Select item..." search modal.
     Triggered by the button in the Add part card; results come
     from ajax/parts_search.php (status=available, qty>0 only).
     ============================================================ -->
<div class="modal fade" id="partSearchModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title"><i class="bi bi-search"></i> Search Item</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div class="p-3 border-bottom">
          <input type="text" id="aw-search-input"
                 class="form-control form-control-lg"
                 placeholder="Type a part name, SKU, or vehicle (e.g. brake, hilux, AW202)…"
                 autocomplete="off">
          <div class="small mt-2 aw-src-legend d-flex flex-wrap align-items-center gap-3">
            <?php
            $awPartsList = rtrim(APP_URL, '/') . '/parts_admin.php';
            $awPartsReturn = 'invoice_edit.php?id=' . (int) $id;
            $awSrcLegend = [
                ['stripped',    'Stripped',     '#fd7e14'],
                ['third_party', 'Third Party',  '#198754'],
                ['oem_new',     'OEM',          '#0d6efd'],
                ['replacement', 'Replacement', '#6f42c1'],
            ];
            foreach ($awSrcLegend as $leg):
                $href = $awPartsList . '?' . http_build_query([
                    'source' => $leg[0],
                    'return' => $awPartsReturn,
                ]);
                ?>
            <a href="<?= e($href) ?>"
               class="aw-src-key-link text-decoration-none"
               style="color:<?= e($leg[2]) ?>;"
               title="All parts (<?= e($leg[1]) ?> only). A blue bar on that page has Back to invoice.">&bull; <?= e($leg[1]) ?></a>
            <?php endforeach; ?>
          </div>
          <p class="small text-muted mb-0 mt-2">Tip: coloured links open <strong>All parts</strong> filtered by source — use <strong>Add to invoice</strong> on each row (needs status <strong>Available</strong>). Or type above to search Available stock in this modal.</p>
        </div>
        <div id="aw-search-status" class="text-muted small px-3 py-2 border-bottom"></div>
        <div id="aw-search-results"></div>
      </div>
    </div>
  </div>
</div>

<style>
  .aw-search-row {
    display: flex; align-items: flex-start;
    padding: .65rem .9rem;
    border-bottom: 1px solid #f1f1f1;
    cursor: pointer;
    background: #fff;
  }
  .aw-search-row:hover { background: #fff5f6; }
  .aw-search-row .name { font-weight: 600; }
  .aw-search-row .sub  { color: #666; font-size: .82rem; }
  .aw-search-row .right { text-align: right; white-space: nowrap; margin-left: 1rem; }
  .aw-search-row .qty   { font-size: .8rem; color: #666; }
  .aw-search-row .price { font-weight: 700; }
  .aw-src-key-link {
    cursor: pointer;
    border-radius: .2rem;
    padding: .1rem .2rem;
    transition: background-color .12s ease, opacity .12s ease;
    white-space: nowrap;
  }
  .aw-src-key-link:hover {
    text-decoration: underline !important;
    background-color: rgba(0, 0, 0, 0.06);
    opacity: 0.9;
  }
  .aw-src-key-link:focus-visible {
    outline: 2px solid #c8102e;
    outline-offset: 2px;
  }
</style>

<script>
(function () {
  const SEARCH_URL = <?= json_encode(APP_URL . '/ajax/parts_search.php') ?>;
  const SOURCE_COLORS = {
    stripped:    '#fd7e14',
    oem_new:     '#0d6efd',
    third_party: '#198754',
    replacement: '#6f42c1'
  };

  const modalEl  = document.getElementById('partSearchModal');
  const input    = document.getElementById('aw-search-input');
  const results  = document.getElementById('aw-search-results');
  const status   = document.getElementById('aw-search-status');

  // Add-part form fields (filled when user picks a part)
  const idHid    = document.getElementById('aw-pick-part-id');
  const trigger  = document.getElementById('aw-pick-trigger');
  const trigLbl  = document.getElementById('aw-pick-label');
  const summary  = document.getElementById('aw-pick-summary');
  const qtyWrap  = document.getElementById('aw-pick-qty-wrap');
  const qtyInput = document.getElementById('aw-pick-qty');
  const priceWrap= document.getElementById('aw-pick-price-wrap');
  const priceInp = document.getElementById('aw-pick-price');
  const addWrap  = document.getElementById('aw-pick-add-wrap');

  let lastQ = null;
  let timer = null;

  function escHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
      ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c]);
  }

  function runSearch(q) {
    if (q === lastQ) return;
    lastQ = q;
    status.textContent = 'Searching…';
    fetch(SEARCH_URL + (q ? ('?q=' + encodeURIComponent(q)) : ''),
          { credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => {
        if (!d.ok) {
          results.innerHTML = '';
          status.textContent = 'Error: ' + (d.error || 'unknown');
          return;
        }
        if (!d.items.length) {
          results.innerHTML = '';
          status.textContent = q ? 'No matches.' : 'No available parts in stock.';
          return;
        }
        status.textContent = d.items.length + (d.items.length === 50
          ? ' (showing top 50 — refine your search)'
          : ' result' + (d.items.length === 1 ? '' : 's'));
        results.innerHTML = d.items.map(renderRow).join('');
        results.querySelectorAll('.aw-search-row').forEach(el => {
          el.addEventListener('click', () => pickPart(el.dataset));
        });
      })
      .catch(err => {
        results.innerHTML = '';
        status.textContent = 'Network error: ' + err.message;
      });
  }

  function renderRow(it) {
    const colour = SOURCE_COLORS[it.source] || '#6c757d';
    return ''
      + '<div class="aw-search-row"'
      +      ' data-id="'      + escHtml(it.id)             + '"'
      +      ' data-name="'    + escHtml(it.name)           + '"'
      +      ' data-sku="'     + escHtml(it.sku)            + '"'
      +      ' data-source="'  + escHtml(it.source)         + '"'
      +      ' data-label="'   + escHtml(it.source_label)   + '"'
      +      ' data-vehicle="' + escHtml(it.vehicle)        + '"'
      +      ' data-price="'   + escHtml(it.asking_price)   + '"'
      +      ' data-qty="'     + escHtml(it.qty_on_hand)    + '">'
      +   '<div class="flex-grow-1">'
      +     '<div class="name" style="color:' + colour + ';">'
      +       '[' + escHtml(it.source_label) + '] ' + escHtml(it.name)
      +     '</div>'
      +     '<div class="sub">'
      +       (it.vehicle ? escHtml(it.vehicle) + ' &middot; ' : '')
      +       'Code: ' + escHtml(it.sku)
      +     '</div>'
      +   '</div>'
      +   '<div class="right">'
      +     '<div class="qty">Qty: ' + escHtml(it.qty_on_hand) + '</div>'
      +     '<div class="price">R ' + Number(it.asking_price).toFixed(2) + '</div>'
      +   '</div>'
      + '</div>';
  }

  function pickPart(d) {
    idHid.value = d.id;

    trigLbl.textContent = '[' + d.label + '] ' + d.name + ' · Code: ' + d.sku;
    trigger.classList.remove('btn-outline-secondary');
    trigger.classList.add('btn-outline-success');
    trigLbl.classList.remove('text-muted');

    summary.classList.remove('d-none');
    summary.innerHTML = (d.vehicle ? escHtml(d.vehicle) + ' &middot; ' : '')
                     + 'In stock: ' + escHtml(d.qty);

    qtyWrap.classList.remove('d-none');
    priceWrap.classList.remove('d-none');
    addWrap.classList.remove('d-none');

    qtyInput.value = 1;
    qtyInput.max   = d.qty;
    priceInp.value = Number(d.price).toFixed(2);

    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
  }

  modalEl.addEventListener('shown.bs.modal', () => {
    input.value = '';
    lastQ = null;
    setTimeout(() => input.focus(), 50);
    runSearch('');
  });

  input.addEventListener('input', () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => runSearch(input.value.trim()), 220);
  });

  // ----- Edit-price pencils on existing draft lines -----
  document.querySelectorAll('.aw-edit-price-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const form = this.closest('form');
      const cur  = parseFloat(this.dataset.current || '0');
      const next = window.prompt('New unit price ex VAT (in Rand):',
                                 cur.toFixed(2));
      if (next === null) return;
      const trimmed = String(next).trim();
      if (trimmed === '' || isNaN(parseFloat(trimmed)) || parseFloat(trimmed) < 0) {
        alert('Please enter a valid non-negative number.');
        return;
      }
      form.querySelector('input[name=unit_price_ex_vat]').value =
        parseFloat(trimmed).toFixed(2);
      form.submit();
    });
  });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php';

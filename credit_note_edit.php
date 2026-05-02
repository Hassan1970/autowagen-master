<?php
/**
 * Stage 7 — Credit note draft → finalize (linked to final POS invoice).
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/credit_note_helpers.php';

$pageTitle = 'Credit note';
$canEdit   = user_has_role('owner', 'admin', 'manager', 'staff');
$uid       = (int) ($_SESSION['user_id'] ?? 0);

if (!cn_tables_ready($pdo)) {
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-warning"><strong>SQL required.</strong> Run <code>sql/07_credit_notes.sql</code> in phpMyAdmin, then reload.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$todayJhb = (new DateTime('now', new DateTimeZone('Africa/Johannesburg')))->format('Y-m-d');

$id = (int) ($_GET['id'] ?? $_POST['cn_id'] ?? 0);

if ($id <= 0 && $canEdit && isset($_GET['new']) && (int) $_GET['new'] === 1) {
    $invId = (int) ($_GET['invoice_id'] ?? 0);
    if ($invId <= 0) {
        header('Location: ' . APP_URL . '/credit_notes_admin.php#create');
        exit;
    }
    $chk = $pdo->prepare(
        'SELECT id, customer_id, status FROM sales_invoices WHERE id = ? AND is_active = 1'
    );
    $chk->execute([$invId]);
    $invR = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$invR || ($invR['status'] ?? '') !== 'final') {
        $_SESSION['cn_flash'] = ['type' => 'danger', 'msg' => 'Only a finalized invoice can have a credit note.'];
        header('Location: ' . APP_URL . '/credit_notes_admin.php#create');
        exit;
    }
    $ins = $pdo->prepare(
        'INSERT INTO sales_credit_notes (invoice_id, customer_id, status, credit_date, adjustment_type, created_by)
         VALUES (?, ?, \'draft\', ?, \'ar_reduction\', ?)'
    );
    $ins->execute([
        $invId,
        !empty($invR['customer_id']) ? (int) $invR['customer_id'] : null,
        $todayJhb,
        $uid > 0 ? $uid : null,
    ]);
    header('Location: ' . APP_URL . '/credit_note_edit.php?id=' . (int) $pdo->lastInsertId());
    exit;
}

if ($id <= 0) {
    header('Location: ' . APP_URL . '/credit_notes_admin.php');
    exit;
}

$flashOut = $_SESSION['cn_flash'] ?? null;
unset($_SESSION['cn_flash']);

/**
 * Replace CN lines from POST qty map; validates against invoice line originals.
 *
 * @param array<string,int|string> $qtyMap keyed by invoice line id -> return qty (int-ish)
 *
 * @return array{errors:array<int,string>,builtLines:bool}
 */
function cn_rebuild_lines_from_post(
    PDO $pdo,
    int $creditNoteId,
    int $invoiceId,
    array $qtyMap
): array {
    $errors       = [];
    $builtSomething = false;
    $st           = $pdo->prepare(
        'SELECT sil.* FROM sales_invoice_lines sil
         INNER JOIN sales_invoices si ON si.id = sil.invoice_id AND si.id = ?
         ORDER BY sil.sort_order ASC, sil.id ASC'
    );
    $st->execute([$invoiceId]);
    $invLines = $st->fetchAll(PDO::FETCH_ASSOC);

    $pdo->prepare('DELETE FROM sales_credit_note_lines WHERE credit_note_id = ?')->execute([$creditNoteId]);
    $so = 0;
    foreach ($invLines as $il) {
        $lid     = (int) $il['id'];
        $origQty = max(1, (int) $il['qty']);
        $key     = (string) $lid;
        $want    = 0;
        if (isset($qtyMap[$key])) {
            $want = (int) $qtyMap[$key];
        } elseif (isset($qtyMap[$lid])) {
            $want = (int) $qtyMap[$lid];
        }
        $want = max(0, $want);
        if ($want === 0) {
            continue;
        }
        if ($want > $origQty) {
            $errors[] = 'Return qty exceeds invoice qty on line #' . $lid . '.';
            continue;
        }
        $already = cn_qty_finalized_for_invoice_line($pdo, $lid, $creditNoteId);
        if ($want + $already > $origQty) {
            $errors[] = 'Line #' . $lid . ': only ' . ($origQty - $already) . ' left to return (already credited ' . $already . ').';
            continue;
        }
        $f        = $want / $origQty;
        $sub      = round((float) $il['line_subtotal_ex'] * $f, 2);
        $vat      = round((float) $il['line_vat'] * $f, 2);
        $tot      = round((float) $il['line_total_inc'] * $f, 2);
        $ins      = $pdo->prepare(
            'INSERT INTO sales_credit_note_lines
             (credit_note_id, invoice_line_id, part_id, line_description, qty, unit_price_ex_vat, vat_rate,
              line_subtotal_ex, line_vat, line_total_inc, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $ins->execute([
            $creditNoteId,
            $lid,
            !empty($il['part_id']) ? (int) $il['part_id'] : null,
            (string) $il['line_description'],
            $want,
            (float) $il['unit_price_ex_vat'],
            (float) $il['vat_rate'],
            $sub,
            $vat,
            $tot,
            ++$so,
        ]);
        $builtSomething = true;
    }

    return ['errors' => $errors, 'builtLines' => $builtSomething];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    $postId = (int) ($_POST['cn_id'] ?? 0);
    if ($postId !== $id || !csrf_check($_POST['csrf'] ?? null)) {
        $_SESSION['cn_flash'] = ['type' => 'danger', 'msg' => 'Invalid submit.'];
        header('Location: ' . APP_URL . '/credit_note_edit.php?id=' . $id);
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save_draft' || $action === 'finalize') {
            $pdo->beginTransaction();
            try {
                $stRow = $pdo->prepare(
                    'SELECT * FROM sales_credit_notes WHERE id = ? AND is_active = 1 FOR UPDATE'
                );
                $stRow->execute([$id]);
                $rowCn = $stRow->fetch(PDO::FETCH_ASSOC);
            if (!$rowCn) {
                throw new RuntimeException('Credit note missing.');
            }
            if (($rowCn['status'] ?? '') !== 'draft') {
                throw new RuntimeException('Only drafts can change.');
            }
            $credDate       = trim((string) ($_POST['credit_date'] ?? ''));
            $adj           = strtolower(trim((string) ($_POST['adjustment_type'] ?? 'ar_reduction')));
            if (!in_array($adj, ['ar_reduction', 'cash_refund'], true)) {
                $adj = 'ar_reduction';
            }
            $notes         = trim((string) ($_POST['notes'] ?? ''));
            $refAt         = trim((string) ($_POST['refund_paid_at'] ?? ''));
            $refMethod     = strtolower(trim((string) ($_POST['refund_method'] ?? '')));
            $refNoteRef    = trim((string) ($_POST['refund_reference_note'] ?? ''));

            if ($credDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $credDate)) {
                throw new RuntimeException('Enter a credit date.');
            }

            $refAtDb = null;
            $methDb  = null;
            $refDb   = $refNoteRef === '' ? null : $refNoteRef;
            if ($adj === 'cash_refund') {
                if ($refAt === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $refAt)) {
                    throw new RuntimeException('Cash refund: enter refund paid date.');
                }
                if (!in_array($refMethod, ['cash', 'eft', 'card', 'other'], true)) {
                    throw new RuntimeException('Cash refund: pick payment method.');
                }
                $refAtDb = $refAt;
                $methDb  = $refMethod;
            }

            $qtyMap       = isset($_POST['rqty']) && is_array($_POST['rqty']) ? $_POST['rqty'] : [];
            $rebuild      = cn_rebuild_lines_from_post($pdo, $id, (int) $rowCn['invoice_id'], $qtyMap);
            if ($rebuild['errors']) {
                throw new RuntimeException(implode(' ', $rebuild['errors']));
            }
            if (!$rebuild['builtLines']) {
                throw new RuntimeException('Set at least one return quantity.');
            }

            cn_recompute_totals($pdo, $id);

            $up = $pdo->prepare(
                'UPDATE sales_credit_notes SET
                     credit_date = ?, adjustment_type = ?, notes = ?,
                     refund_paid_at = ?, refund_method = ?, refund_reference_note = ?
                   WHERE id = ? AND status = \'draft\''
            );
            $up->execute([
                $credDate,
                $adj,
                $notes === '' ? null : $notes,
                $refAtDb,
                $methDb,
                $refDb,
                $id,
            ]);

            if ($action === 'save_draft') {
                $pdo->commit();
                $_SESSION['cn_flash'] = ['type' => 'success', 'msg' => 'Draft saved.'];
                header('Location: ' . APP_URL . '/credit_note_edit.php?id=' . $id);
                exit;
            }

            $totSt = $pdo->prepare(
                'SELECT total_inc_vat, invoice_id FROM sales_credit_notes WHERE id = ?'
            );
            $totSt->execute([$id]);
            $fresh = $totSt->fetch(PDO::FETCH_ASSOC);
            $invId = (int) $fresh['invoice_id'];

            $stInv = $pdo->prepare(
                'SELECT total_inc_vat FROM sales_invoices WHERE id = ? FOR UPDATE'
            );
            $stInv->execute([$invId]);
            $invTot = (float) $stInv->fetchColumn();

            $stCred = $pdo->prepare(
                'SELECT COALESCE(SUM(total_inc_vat),0)
                 FROM sales_credit_notes WHERE invoice_id = ? AND status = \'final\' AND id <> ? AND is_active = 1'
            );
            $stCred->execute([$invId, $id]);
            $prevCred = round((float) $stCred->fetchColumn(), 2);
            $thisTot  = round((float) $fresh['total_inc_vat'], 2);

            if ($thisTot <= 0) {
                throw new RuntimeException('Credit total must be positive.');
            }
            if ($thisTot > round($invTot - $prevCred, 2) + 0.015) {
                throw new RuntimeException(
                    'Credit exceeds remaining invoice face value '
                    . '(invoice R ' . number_format($invTot, 2) . ' − prior credits R '
                    . number_format($prevCred, 2) . ').'
                );
            }

            $linesSt = $pdo->prepare(
                'SELECT cnl.* FROM sales_credit_note_lines cnl WHERE cnl.credit_note_id = ?'
            );
            $linesSt->execute([$id]);
            foreach ($linesSt->fetchAll(PDO::FETCH_ASSOC) as $ln) {
                if (empty($ln['part_id'])) {
                    continue;
                }
                $pid = (int) $ln['part_id'];
                $rq  = (int) $ln['qty'];
                if ($rq < 1) {
                    continue;
                }
                $pst = $pdo->prepare(
                    'SELECT id, qty_on_hand, status, is_active FROM parts WHERE id = ? FOR UPDATE'
                );
                $pst->execute([$pid]);
                $part = $pst->fetch(PDO::FETCH_ASSOC);
                if (!$part || (int) $part['is_active'] === 0) {
                    throw new RuntimeException('Part #' . $pid . ' inactive — restore blocked.');
                }
                $newOh = (int) $part['qty_on_hand'] + $rq;
                $stNew = ($part['status'] ?? '') === 'sold' && $newOh > 0 ? 'available' : ($part['status'] ?? 'available');
                $pdo->prepare('UPDATE parts SET qty_on_hand = ?, status = ? WHERE id = ?')
                    ->execute([$newOh, $stNew, $pid]);
            }

            $cno = cn_next_credit_no($pdo);
            $fin = $pdo->prepare(
                "UPDATE sales_credit_notes SET
                   status = 'final', credit_no = ?, finalized_at = NOW()
                   WHERE id = ? AND status = 'draft'"
            );
            $fin->execute([$cno, $id]);

            $pdo->commit();

            $_SESSION['cn_flash'] = ['type' => 'success', 'msg' => 'Credit note finalized: ' . $cno];
            header('Location: ' . APP_URL . '/credit_note_edit.php?id=' . $id);
            exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
    } catch (Throwable $e) {
        $_SESSION['cn_flash'] = ['type' => 'danger', 'msg' => $e->getMessage()];
        header('Location: ' . APP_URL . '/credit_note_edit.php?id=' . $id);
        exit;
    }
}

$cnSt = $pdo->prepare(
    'SELECT cn.*
     FROM sales_credit_notes cn
     WHERE cn.id = ? AND cn.is_active = 1'
);
$cnSt->execute([$id]);
$credit = $cnSt->fetch(PDO::FETCH_ASSOC);
if (!$credit) {
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-danger">Credit note not found.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
$credit       = array_change_key_case($credit, CASE_LOWER);
$isDraft      = ($credit['status'] ?? '') === 'draft';

$invSt        = $pdo->prepare(
    'SELECT i.*, c.name AS customer_name
     FROM sales_invoices i
     LEFT JOIN customers c ON c.id = i.customer_id
     WHERE i.id = ?'
);
$invSt->execute([(int) $credit['invoice_id']]);
$inv          = $invSt->fetch(PDO::FETCH_ASSOC);
$invoiceNo    = (string) ($inv['invoice_no'] ?? '#' . ($inv['id'] ?? '?'));

if (!$inv || ($inv['status'] ?? '') !== 'final') {
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-danger">Linked invoice missing or not final.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$lineSt       = $pdo->prepare(
    'SELECT sil.*, p.sku
     FROM sales_invoice_lines sil
     LEFT JOIN parts p ON p.id = sil.part_id
     WHERE sil.invoice_id = ?
     ORDER BY sil.sort_order ASC, sil.id ASC'
);
$lineSt->execute([(int) $inv['id']]);
$invLinesAll  = $lineSt->fetchAll(PDO::FETCH_ASSOC);

$cnLineSt = $pdo->prepare(
    'SELECT * FROM sales_credit_note_lines WHERE credit_note_id = ? ORDER BY sort_order ASC, id ASC'
);
$cnLineSt->execute([$id]);
$cnLines  = $cnLineSt->fetchAll(PDO::FETCH_ASSOC);

$prevCredTot = cn_finalized_total_for_invoice($pdo, (int) $inv['id']);
$invFace     = (float) $inv['total_inc_vat'];
$maxMoreCred = max(0, round($invFace - $prevCredTot, 2));

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap gap-2 mb-3 align-items-start">
  <div>
    <h1 class="h4 mb-1">
      <i class="bi bi-arrow-counterclockwise"></i>
      <?= $credit['credit_no']
          ? '<span class="font-monospace">' . e((string) $credit['credit_no']) . '</span>'
          : 'Credit note (draft)'
      ?>
    </h1>
    <p class="text-muted small mb-0">
      Linked invoice <strong class="font-monospace"><?= e($invoiceNo) ?></strong>
      <?= !empty($inv['customer_name']) ? ' · ' . e((string) $inv['customer_name']) : '' ?>
      · Invoice total incl. VAT <strong>R <?= number_format((float) $inv['total_inc_vat'], 2) ?></strong>
      · Finalized credits on this INV: <strong>R <?= number_format($prevCredTot, 2) ?></strong>
    </p>
  </div>
  <div class="d-flex flex-wrap gap-1">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(APP_URL) ?>/credit_notes_admin.php">All credit notes</a>
    <a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL) ?>/invoice_edit.php?id=<?= (int) $inv['id'] ?>">Open invoice</a>
  </div>
</div>

<?php if (!empty($flashOut['msg'])): ?>
  <div class="alert alert-<?= e((string) ($flashOut['type'] ?? 'info')) ?> py-2"><?= e((string) $flashOut['msg']) ?></div>
<?php endif; ?>

<?php if ($isDraft): ?>
<div class="alert alert-light border small mb-3">
  <strong>Return rules:</strong> Part lines restore <strong>stock</strong> on finalize (<strong>sold → available</strong> when qty on hand rises).
  Totals capped so all credits against this INV never exceed invoice value.
</div>

<?php if (!$canEdit): ?>
  <div class="alert alert-secondary">Viewer — read-only (draft).</div>
  <div class="card shadow-sm mb-3 border-danger border-top border-5">
    <div class="card-header bg-dark text-white"><strong>Header</strong></div>
    <div class="card-body row small mb-0">
      <div class="col-md-4"><strong>Credit date:</strong> <?= e((string) ($credit['credit_date'] ?? '')) ?></div>
      <div class="col-md-8"><strong>Type:</strong> <?= (($credit['adjustment_type'] ?? '') === 'cash_refund') ? 'Cash refund' : 'AR reduction' ?></div>
    </div>
  </div>
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-light"><strong>Returns from <?= e($invoiceNo) ?></strong></div>
    <div class="table-responsive">
      <table class="table mb-0 align-middle small">
        <thead class="table-light">
          <tr>
            <th>Line</th>
            <th class="text-end">Qty</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cnLines as $cl): ?>
            <tr>
              <td><?= e((string) $cl['line_description']) ?></td>
              <td class="text-end"><?= (int) $cl['qty'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php else: ?>

<form method="post" class="mb-3">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="cn_id" value="<?= (int) $id ?>">

<div class="card shadow-sm mb-3 border-danger border-top border-5">
  <div class="card-header bg-dark text-white"><strong>Header</strong></div>
  <div class="card-body row g-2">
    <div class="col-md-3">
      <label class="form-label small">Credit date</label>
      <input type="date" name="credit_date" class="form-control" required
             value="<?= e((string) ($credit['credit_date'] ?? $todayJhb)) ?>">
    </div>
    <div class="col-md-5">
      <label class="form-label small">Adjustment</label>
      <select name="adjustment_type" id="adjustment_type" class="form-select">
        <option value="ar_reduction" <?= (($credit['adjustment_type'] ?? '') === 'cash_refund') ? '' : 'selected' ?>>
          AR reduction (reduce buyer balance vs this invoice)</option>
        <option value="cash_refund" <?= (($credit['adjustment_type'] ?? '') === 'cash_refund') ? 'selected' : '' ?>>
          Cash refund (money returned — settle dates/method)</option>
      </select>
    </div>
    <div class="col-md-4" id="refund-fields"
         style="<?= (($credit['adjustment_type'] ?? '') === 'cash_refund') ? '' : 'display:none;' ?>">
      <div class="row g-2">
        <div class="col-6">
          <label class="form-label small">Refund paid date</label>
          <input type="date" name="refund_paid_at" class="form-control"
                 value="<?= e((string) ($credit['refund_paid_at'] ?? '') ?: '') ?>">
        </div>
        <div class="col-6">
          <label class="form-label small">Method</label>
          <select name="refund_method" class="form-select">
            <option value="">—</option>
            <?php foreach (['cash' => 'Cash', 'eft' => 'EFT', 'card' => 'Card', 'other' => 'Other'] as $mk => $ml): ?>
              <option value="<?= e($mk) ?>" <?= (($credit['refund_method'] ?? '') === $mk) ? 'selected' : '' ?>><?= e($ml) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label small">Reference / slip no.</label>
          <input type="text" name="refund_reference_note" class="form-control" maxlength="255"
                 value="<?= e((string) ($credit['refund_reference_note'] ?? '')) ?>">
        </div>
      </div>
    </div>
    <div class="col-12">
      <label class="form-label small">Notes (internal)</label>
      <textarea name="notes" class="form-control" rows="2"><?= e((string) ($credit['notes'] ?? '')) ?></textarea>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-header bg-light"><strong>Return quantities — from <?= e($invoiceNo) ?></strong></div>
  <div class="table-responsive">
    <table class="table mb-0 align-middle small">
      <thead class="table-light">
        <tr>
          <th>Line</th>
          <th>SKU</th>
          <th class="text-end">Inv qty</th>
          <th class="text-end">Still returnable</th>
          <th class="text-end" style="width:7rem;">Return now</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($invLinesAll as $il): ?>
          <?php
            $lid   = (int) $il['id'];
            $iq    = (int) $il['qty'];
            $avail = $iq - cn_qty_finalized_for_invoice_line($pdo, $lid, $id);

            $curRet = '';
            foreach ($cnLines as $cl) {
                if ((int) $cl['invoice_line_id'] === $lid) {
                    $curRet = (string) (int) $cl['qty'];
                    break;
                }
            }

            ?>
          <tr <?= $avail <= 0 ? 'class="table-secondary"' : '' ?>>
            <td><?= e((string) $il['line_description']) ?></td>
            <td class="font-monospace"><?= !empty($il['sku']) ? e((string) $il['sku']) : '—' ?></td>
            <td class="text-end"><?= $iq ?></td>
            <td class="text-end"><?= max(0, $avail) ?></td>
            <td class="text-end">
              <input type="number" min="0" max="<?= max(0, $avail) ?>" class="form-control form-control-sm text-end"
                     name="rqty[<?= $lid ?>]" <?= $avail <= 0 ? 'readonly value="0"' : 'value="' . e($curRet !== '' ? $curRet : '0') . '"' ?>>
              <?php if (!empty($il['part_id'])): ?>
                <div class="form-text mb-0">Restores stock</div>
              <?php else: ?>
                <div class="form-text mb-0">Manual line</div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex flex-wrap gap-2">
    <button type="submit" name="action" value="save_draft" class="btn btn-outline-danger">Save draft</button>
    <button type="submit" name="action" value="finalize"
            id="finalize-cn-btn"
            class="btn btn-danger">Finalize credit note</button>
  </div>
</div>
</form>

<p class="text-muted small">Draft totals incl. VAT: <strong>R <?= number_format((float) ($credit['total_inc_vat'] ?? 0), 2) ?></strong>.
  Max credit this note may reach (invoice − other finalized credits): <strong>R <?= number_format($maxMoreCred, 2) ?></strong>.</p>

<script>
(function () {
   var sel = document.getElementById('adjustment_type');
   var box = document.getElementById('refund-fields');
   if (!sel || !box) return;
   function t() {
     box.style.display = (sel.value === 'cash_refund') ? '' : 'none';
   }
   sel.addEventListener('change', t); t();
})();
(function () {
  var btn = document.getElementById('finalize-cn-btn');
  if (!btn) return;
  btn.addEventListener('click', function (e) {
    var adj = document.getElementById('adjustment_type');
    var ar = !adj || adj.value !== 'cash_refund';
    var msg = ar
      ? 'Finalize credit note?\nPart lines will go back to stock.\nThis amount counts as an AR reduction in credit splits on the AR report and statement (remaining balance still subtracts all credits).'
      : 'Finalize credit note?\nPart lines will go back to stock.\nThis amount counts as a cash refund in those splits — record refund date/method. Remaining balance on the invoice still subtracts all credits.';
    if (!window.confirm(msg)) e.preventDefault();
  });
})();
</script>

<?php endif; /* canEdit vs viewer draft */ ?>

<?php else: /* final read-only */ ?>
<div class="card shadow-sm mb-3">
  <div class="card-header bg-success text-white">Finalized credit note</div>
  <div class="card-body row small mb-2">
    <div class="col-md-4"><strong>Date:</strong> <?= e((string) $credit['credit_date']) ?></div>
    <div class="col-md-4"><strong>Type:</strong> <?= (($credit['adjustment_type'] ?? '') === 'cash_refund')
        ? 'Cash refund' : 'AR reduction'
    ?></div>
    <div class="col-md-4"><strong>Total incl. VAT:</strong> R <?= number_format((float) $credit['total_inc_vat'], 2) ?></div>
    <?php if (($credit['adjustment_type'] ?? '') === 'cash_refund'): ?>
      <div class="col-md-4"><strong>Refund paid:</strong> <?= e((string) ($credit['refund_paid_at'] ?? '—')) ?></div>
      <div class="col-md-4"><strong>Method:</strong> <?= e((string) ($credit['refund_method'] ?? '—')) ?></div>
      <div class="col-md-4"><strong>Ref:</strong> <?= e((string) ($credit['refund_reference_note'] ?? '—')) ?></div>
    <?php endif; ?>
    <?php if (!empty($credit['notes'])): ?>
      <div class="col-12"><strong>Notes:</strong> <?= e((string) $credit['notes']) ?></div>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table mb-0 small">
      <thead class="table-light"><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Line incl. VAT</th></tr></thead>
      <tbody>
        <?php foreach ($cnLines as $ln): ?>
          <tr>
            <td><?= e((string) $ln['line_description']) ?></td>
            <td class="text-end"><?= (int) $ln['qty'] ?></td>
            <td class="text-end">R <?= number_format((float) $ln['line_total_inc'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

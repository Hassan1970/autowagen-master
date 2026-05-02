# After Stage 7 — mini-roadmap (not scheduled)

**Resume tomorrow:** project diary = **`CLAUDE.md`** (especially **§10** + **§9** session log) · new-chat paste = **`HOW_TO_START_NEW_CHAT.md`** Step 2–3.

Use this list when you say **go** on the next build block. **Stage 7 credit notes** stay as implemented; **no new SQL** required for the AR/cash **display split** (already in code).

---

## Done (2026-05-01 UTC+2 session) — accounting clarity

- **Locked rule — net due:** `invoice total − payments − all finalized credits` (both **AR reduction** and **cash refund** types). Used for payment caps, open-invoice filters, **Remaining** on the invoice, statement **Balance**, AR **Balance** column.
- **Locked rule — reporting split:** AR report and customer statement show separate columns for **AR cr.** (sum of credits with `adjustment_type = ar_reduction`) vs **Refund** / **Refund cn.** (cash_refund). Helps bookkeeping without changing net due.
- **Files:** `includes/credit_note_helpers.php`, `customer_ar_report.php`, `customer_statement.php`, `invoice_edit.php`, `docs/credit_notes_ar_vs_cash_refund_print.html`.

---

## Supplier returns / AP credits

- **Goal:** Match customer credit notes on the **buying** side: return to supplier, credit note or AP adjustment, link to `supplier_purchases` / parts.
- **Needs:** Schema + UI + report; decision on **stock** (return to shelf vs write-off) and **bill vs payment** offsets.
- **Status:** MVP remains **manual** (edit part, purchase bill/payments). **Defer** until prioritized.

---

## SMTP / outbound email

- **Goal:** Send statements or AR reminders from the server (not only `mailto:`).
- **Needs:** `secrets.local.php` SMTP settings, small mailer helper, log table for sends, **06a**/customer email already on record.
- **Status:** Not built; tie to **Stage 5 BUILD BRIEF** in `CLAUDE.md` when you lock reminder rules.

---

## Online payments (shop)

- **Goal:** PayFast/Stripe (or similar) on `shop/checkout.php`.
- **Needs:** Host secrets, callback URLs, reconcile to `shop_orders`; legal/3-D Secure; **not** in current MVP.
- **Status:** Defer.

---

## Alternate accountant rule — cash refunds excluded from “net due” (explicit **go** + spec only)

Not implemented. Means headline **Balance / Remaining / AR chased** would use `invoice − payments − AR_reduction credits` only; **cash_refund** CNs tracked but excluded from that headline so receivables and cash payouts diverge unless you ship **dual-balance UX**, **negative customer balance / credit wallet**, and rules for **overpayment** & **paid-in-full + refund**.

**Detailed written spec for your accountant:** `docs/credit_notes_ar_vs_cash_refund_print.html` → **§7**.

---

## Optional later

- **Sales summary:** finalized **credit-note** totals + list by **credit date** are on **`sales_summary_report.php`** when **`sql/07_credit_notes.sql`** is applied — no further P&amp;L-style line detail planned unless you request it.
- Training PNGs for `docs/manual_screenshots/` and `complete_system_manual.html` figures.
- **Ops docs:** printable **`docs/database_update_backup_guide_print.html`** (replace DB vs incremental SQL) · **`docs/add_users_staff_guide_print.html`** (new app users via `users` table).

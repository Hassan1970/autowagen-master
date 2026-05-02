# After Stage 7 — mini-roadmap (not scheduled)

**Resume tomorrow:** **`HOW_TO_START_NEW_CHAT.md`** → **STEP 2** paste into new chat · project diary = **`CLAUDE.md`** (**§10** Pause + **§9** log) · rollout ladder at **top** of this file + **`docs/rollout_execution_order_print.html`**.

Use this list when you say **go** on the next build block. **Stage 7 credit notes** stay as implemented; **no new SQL** required for the AR/cash **display split** (already in code).

**Printable ladder (same order):** **`docs/rollout_execution_order_print.html`** → browser → **Print → PDF**.

---

## Recommended execution order (Live → test → backlog → test → polish)

Do **not** skip tests between phases. Finish one **phase** before starting the next.

### Phase A — Live / ops (get hosting correct)

1. On your **PC**, confirm the code you want is saved and pushed: **`git status`** clean, **`git push`** done (see **`docs/git_laragon_terminal_start_to_finish_print.html`**).
2. Copy **program files** to the live host the way you always do (**FTP**, **cPanel File Manager**, or **`git pull`** on the server if your host allows it). At minimum, if the live menu is old: upload **`includes/header.php`**, **`main_dashboard.php`**, and any other files your session changed.
3. On the **server**, ensure **`config/secrets.live.php`** exists and has the **live database** credentials (never commit real secrets to GitHub). Copy from **`config/secrets.live.php.example`** if needed.
4. On the host **phpMyAdmin**: run any **`sql/*.sql`** files that this **live** database is still missing — same order as **`CLAUDE.md`** section 10 (e.g. **`05`**, **`06a`**, **`06b`**, **`06e`**, **`07`** as needed).
5. Open the **live** site in the browser → **login** → check **Reports** dropdown and open **one** report. If something errors, fix **files** or **SQL** before Phase B.

### Phase B — Test (prove live / daily use)

1. **Login** · **Dashboard** loads.
2. **POS:** open **Sales invoices** or **New sale** — one **draft** is enough; cancel or leave draft.
3. **Reports:** open **Accounts receivable** and **Sales summary** — no white screen.
4. **Public shop:** open **`/shop/`** on the **live** URL (no login) — page loads.
5. Optional: **Credit notes** (if **`07`** ran on live DB): **Reports → Credit notes** opens.
6. Write **PASS** or issues in **`CLAUDE.md`** section 9 (one dated line).

### Phase C — New code (backlog — pick **one** block per project)

Only after **A + B** are OK (or you consciously test only on Laragon first).

| Order | Build | See below |
|-------|--------|-----------|
| 1 (often first) | **SMTP** / server email | § **SMTP / outbound email** |
| 2 | **Supplier returns / AP credits** | § **Supplier returns / AP credits** |
| 3 | **PayFast / Stripe** on shop | § **Online payments (shop)** |
| 4 | **Alternate accountant net-due rule** | § **Alternate accountant rule** — explicit **go** only |

Implement on **Laragon** first (`http://localhost/autowagen-master/`), then repeat **Phase A** deploy + **Phase D** tests on live.

### Phase D — Test (after each backlog delivery)

1. On **localhost**: exercise only the screens that changed (e.g. new mail button, new AP screen).
2. **`git commit`** + **`git push`**.
3. Deploy to **live** (Phase A steps 2–4 as needed).
4. Repeat **Phase B** smoke checks plus **specific** checks for the new feature.
5. **`CLAUDE.md`** §9 + **`docs/CHANGELOG.md`** / **`CHANGELOG.html`** (Recorder + time).

### Phase E — Polish / docs

1. **`docs/TRAINING_SCREENSHOTS.md`** — add real PNGs under **`docs/manual_screenshots/`** for **`complete_system_manual.html`** figures.
2. Re-print **`docs/complete_system_manual.html`** → PDF for clients when screenshots exist.
3. Optional: dated **`docs/md_backups/YYYY-MM-DD/`** copy of **`CLAUDE.md`**, **`ROADMAP.md`**, **`HOW_TO_START_NEW_CHAT.md`**.
4. Ops handouts already in **`docs/client_training_index.html`** (database update, add users, Git, shutdown checklist).

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

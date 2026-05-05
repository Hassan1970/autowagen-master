# CLAUDE.md — Autowagen Master project memory

> **READ THIS FILE FIRST. EVERY SESSION. NO EXCEPTIONS.**
>
> This is the single source of truth for what's built, what works, and what to
> do next. If a chat ever gets confused, read this top-to-bottom before
> touching anything. After every meaningful action, **update the
> "Live state" and "Session log" sections** with an accurate timestamp.

---

## 1. Project overview

**Autowagen Master** is a clean rebuild of the legacy `autowagengit` PHP
project. Old project stays untouched as read-only reference. Built in
6 numbered stages on top of MySQL via phpMyAdmin (Laragon stack on Windows).

- Stack: **PHP 8 + MySQL + Bootstrap 5 + Bootstrap Icons** (CDN), no Composer.
- Server: **Laragon** at `http://localhost/autowagen-master/`.
- Database: **`autowagen_master`** (phpMyAdmin).
- Editor: **Cursor IDE** on Windows (PowerShell shell).
- Timezone (PHP + this file): **Africa/Johannesburg (UTC+2)**.
- The user (**hassan**, role `owner`) is **new to coding** — every
  instruction must be numbered, plain English, and tell them exactly
  what to click or type.

---

## 2. Live state — UPDATE AFTER EVERY ACTION

> Format: `Stage N — Title:  STATUS  (last verified YYYY-MM-DD HH:MM TZ)`
>
> **Last overnight handoff:** **2026-05-02 (end of day) UTC+2** — **hassan** closed session. **When back:** **`HOW_TO_START_NEW_CHAT.md`** STEP **2** paste into new chat + read **`CLAUDE.md`** §**10** (**Pause — end of day**). **Next:** rollout **Phase A** (live deploy / `secrets.live.php` / host SQL) → **Phase B** test — **`docs/BACKLOG_POST_STAGE7.md`** or **`docs/rollout_execution_order_print.html`**.

- **Stage 1 — Foundation (auth, layout, config):** ✅ DONE & TESTED
  *(verified 2026-04-26, see Stage 1 deliverables below)*
  - **`auth/login.php` lockout (2026-05-02):** up to **6** wrong passwords per **lowercased username + IP** in a rolling **15-minute** window (`LOGIN_MAX_FAILED_IN_WINDOW`); next submit shows lockout until the window moves; **successful login** deletes prior **failed** rows for that username+IP; dev/test: phpMyAdmin **`DELETE FROM user_login_attempts;`** on `autowagen_master` if needed.
- **Stage 2 — EPC parts tree (6-level catalogue):** ✅ DONE & TESTED
  *(verified 2026-04-26)*  
  - **2026-04-28 — optional catalogue expansion (hassan):** `02b` 12 categories,
    `02c`, `02d` spine, optional `02e` dismantler variants, optional `02f` Body→Doors,
    optional **`02g`**, **`02h`** (subsystem per type), optional **`02e`**;
    `02_audit`, **`02_verify_epc_integrity`** + `README_EPC_EXPANSION.txt`.
- **Stage 3 — Master data (vehicles / customers / suppliers / EPC links):**
  ✅ **DONE & TESTED** *(last verified 2026-04-27 13:21 UTC+2 — vehicles,
  customers, suppliers, EPC links; Test 4: supplier **HASSAN NIZAMIE** on
  `suppliers_admin.php`.)*
  - SQL `sql/03_master_data.sql` ran successfully *(2026-04-26 17:47 UTC+2)*.
  - Tables confirmed: `vehicles`, `customers`, `suppliers`, `vehicle_epc_links`.
  - ✅ Test 1 PASSED — Master-data nav loads *(2026-04-26 18:09 UTC+2)*.
  - ✅ Test 2 PASSED — basic vehicle created (VW md) *(2026-04-26 18:09 UTC+2)*.
  - ✅ **Test 2b PASSED** — `sql/03b_vehicle_extras.sql` ran cleanly
    *(2026-04-27 07:18 UTC+2)*. All 19 new columns verified on
    `vehicles` table. New `vehicle_photos` table created. Blocker
    lifted — `vehicles_admin.php` and `vehicle_edit.php` now load.
  - ✅ Business type confirmed: **option 1 — strip & sell parts**
    (vehicle dismantler, SA legal compliance) *(2026-04-27 06:14 UTC+2)*.
  - ✅ Stage 3b extra vehicle fields agreed: stock code (`AWG-XXXX`),
    status, transmission, fuel type, body type, supplier dropdown,
    private-seller name + SA ID + phone, purchase price, date acquired,
    purchase notes, three legal-paper scans (logbook / receipt /
    seller-ID copy), yard location, and up to 7 photos
    *(2026-04-27 06:35 UTC+2)*.
  - ✅ Stage 3b code written *(2026-04-27 ~06:40 UTC+2)*:
    - `sql/03b_vehicle_extras.sql` (additive ALTER TABLE + new
      `vehicle_photos` table, idempotent FK / index adds).
    - `includes/uploads.php` (file-upload helper, 5 MB cap, ext whitelist).
    - `vehicle_edit.php` rewritten — multipart form, 5 sections,
      file uploads + photo gallery + EPC linker.
    - `vehicles_admin.php` rewritten — list shows stock code, status
      badge, papers badge (3/3), yard; new status filter dropdown.
    - `uploads/.htaccess` (deny PHP execution in uploads dir).
    - `.gitignore` updated to keep `uploads/.htaccess` tracked.
  - ✅ Old blocker resolved (2026-04-27 07:18 UTC+2): MariaDB-only
    syntax in original `sql/03b_vehicle_extras.sql` rewritten to
    MySQL-compatible INFORMATION_SCHEMA-guarded prepared statements.
    SQL re-ran cleanly. All 19 columns present.
  - ✅ **Test 2c partial** *(2026-04-27 07:23 UTC+2)*: `vehicle_edit.php`
    new-vehicle form opened cleanly, all 5 sections render correctly
    (STOCK & IDENTITY / STATUS & SPECS / ACQUISITION / LEGAL PAPERS /
    YARD & GENERAL NOTES). Confirmed via 3 screenshots.
  - ✅ **Test 2c Part A PASSED (2026-04-27 08:11 UTC+2)** — hassan
    re-filled the Toyota form with UNIQUE values (VIN `TOY1234567`,
    plate `ND 1111`, stock code `0002`, year `2001`), clicked
    Create vehicle. Save succeeded. `vehicles_admin.php` screenshot
    confirms the list now shows BOTH vehicles: VW md (legacy, no
    stock code, papers 0/3) and **AWG-0002 TOYOTA HILUX** (white,
    2001, "Being stripped" badge, red Papers 3/3 badge, yard
    `bay 3`, plate `ND 1111`, VIN `TOY1234567`, 12,345 km, Active).
    All Stage 3b list columns rendering correctly.
  - ✅ **First paper upload tested (2026-04-27 ~08:25 UTC+2)** —
    hassan uploaded a picture into the Logbook slot, clicked
    Save changes, got "Saved" green banner, "View current scan"
    link appeared. Confirms doc upload pipeline works.
  - ✅ **Stage 3c shipped (2026-04-27 08:35 UTC+2)** — added a
    4th legal-paper slot **Seller's proof of residence** for
    fuller SA Second-Hand Goods Act compliance. Files written:
    - `sql/03c_proof_of_residence.sql` (2 new columns,
      idempotent INFORMATION_SCHEMA-guarded ALTERs).
    - `vehicle_edit.php` updated — defaults, INSERT/UPDATE
      payloads, file upload handler for `proof_residence_file`,
      delete_doc map, papers-complete = 4, $docRows array
      (4 entries), card width col-md-6 col-lg-3 (4 across on
      desktop, 2x2 on tablet).
    - `vehicles_admin.php` updated — SELECT includes new col,
      papers count /4, badge text 4/4, missing-papers tooltip
      mentions proof of residence.
    - Lints clean. **SQL NOT YET RUN.**
  - ⛔ **NEW BLOCKER (NOT broken — just needs the SQL run):**
    until hassan runs `sql/03c_proof_of_residence.sql` in
    phpMyAdmin, both `vehicles_admin.php` AND `vehicle_edit.php`
    will throw `PDOException Column not found: 'has_proof_of_residence'`.
    Same pattern as Stage 3b's blocker yesterday.
  - ⏭️ **NEXT STEP — run the new SQL, then resume Test 2c Part B**:
    open Toyota in `vehicle_edit.php`, confirm a 4th card
    "Seller's proof of residence" now appears in LEGAL PAPERS
    section (cards are now col-md-6 col-lg-3 — 4 across on
    desktop), upload 1 photo + delete 1 photo (already partly
    tested — log book upload worked), then close out 2c-B.
  - ✅ **Stage 3d shipped & tested (2026-04-27 12:22 UTC+2)** — added
    SA Second-Hand Goods Act compliance docs to `customers` table.
    Files written:
    - `sql/03d_customer_compliance.sql` (5 new columns: sa_id_number,
      company_reg_number, id_doc_path, has_proof_of_address,
      proof_of_address_path; +2 indexes; INFORMATION_SCHEMA-guarded).
      Ran cleanly in phpMyAdmin (2026-04-27 11:38 UTC+2).
    - `includes/uploads.php` — added `customer_uploads_dir()` and
      `save_uploaded_customer_doc()` helpers.
    - `customers_admin.php` rewritten — multipart modal (SA ID for
      individuals / CIPC for businesses, type-driven via JS),
      two upload cards (ID copy + proof of address), `Docs N/2`
      badge column, new compliance filter dropdown, pencil-edit
      preserves modal title `Edit customer #N`.
  - ✅ **Test 3 PASSED (2026-04-27 12:22 UTC+2)** — hassan created
    customer #3 (john smith, individual, SA ID 8501015800090), then
    uploaded both ID copy + proof of address. List shows green
    `Docs 2/2 ✓` badge. Two intermediate Save-button bugs fixed
    same session: (a) Save button hidden because the form wrapped
    the whole modal-content (broke Bootstrap scrollable flex), and
    (b) some browsers dropped multipart file uploads when the only
    submit was outside the form via `form="cm-form"` HTML attr.
    Fix: real submit lives inside the form (visually hidden), red
    footer Save is `type="button"` and `.click()`s the inner submit.
  - ✅ **Test 4 PASSED (2026-04-27 13:21 UTC+2)** — hassan added supplier
    **HASSAN NIZAMIE** (contact, phone `0815358539`, email
    `hassannizamie@gmail.com`, payment terms 30 days). Green banner
    *Added supplier: HASSAN NIZAMIE*; list shows 1 row Active. Screenshot
    `Screenshot_2026-04-27_132055-...png`.
- **Stage 4 — Inventory & parts (+ 4b TPP, 4c batch purchases, 4d AP):** ✅
  **DONE & TESTED** *(core list/edit verified 2026-04-27 10:30 UTC+2; 4b/4c/4d
  shipped and exercised same period — run `04b` → `04c` → `04d` on any DB
  that does not have those objects yet)*
  - SQL `sql/04_inventory.sql` ran successfully. 3 new tables
    confirmed: `parts`, `part_photos`, `part_epc_links`.
  - `parts_admin.php` (list / search / source+status+vehicle filters /
    pagination) loads cleanly with empty state.
  - `part_edit.php` (full multipart form, 7 cards, source-aware
    show/hide, SKU auto-suggest) saves a new part successfully.
  - Auto-SKU works: stripped headlight on Toyota saved as
    `AWG-0002-P01` (after a same-session double-prefix bugfix).
  - Photo upload tested: 3 photos uploaded to the headlight part,
    saved under `uploads/parts/<part_id>/photos/`.
  - `vehicle_edit.php` "PARTS STRIPPED FROM THIS VEHICLE" card
    appears on the Toyota's page, listing AWG-0002-P01.
  - Inventory nav item live in `includes/header.php` (dropdown:
    All parts / Add part).
  - All lints clean.
  - ✅ **Stage 4b (TPP compliance)** — code shipped *(2026-04-27)*:
    - `sql/04b_part_tpp_compliance.sql` — 4 columns on `parts`:
      `has_tpp_id_doc`, `tpp_id_doc_path`, `has_tpp_proof_of_address`,
      `tpp_proof_of_address_path` (private third-party only;
      cleared when source ≠ `third_party` or supplier is selected).
    - `includes/uploads.php` — `save_uploaded_part_compliance_doc()`
      → `uploads/parts/<id>/docs/`.
    - `part_edit.php` — SHGA cards under **From a private individual**;
      tab auto-select supplier vs private; delete-doc actions.
    - `parts_admin.php` — **TPP docs** column (`—` / `N/A` / `Docs 0–2/2`).
    - ⛔ **Run `sql/04b_part_tpp_compliance.sql` in phpMyAdmin first** or
      parts pages throw *unknown column*.
  - ✅ **Stage 4c (supplier batch / one purchase, many parts)** — code shipped
    *(2026-04-27)*:
    - `sql/04c_supplier_purchases.sql` — table `supplier_purchases`,
      `parts.supplier_purchase_id` (nullable FK). **Run after 04 + 04b.**
    - `supplier_purchases_admin.php`, `supplier_purchase_edit.php` — create purchase,
      upload SHGA once, list parts, **Add part to this purchase**
      → `part_edit.php?supplier_purchase_id=N` (legacy `tpp_intake_id` GET supported).
    - `part_edit.php` — batch mode: seller + part-level TPP section hidden when linked;
      compliance inherited from purchase row.
    - `parts_admin.php` — JOIN purchase for **TPP docs** badge; **From** → **Purchase #N**.
    - `includes/uploads.php` — `supplier_purchase_uploads_dir()` → `uploads/supplier_purchases/<id>/docs/`.
    - `includes/header.php` — Inventory → **Supplier purchases** / **New supplier purchase**.
    - ⛔ **Run `sql/04c_supplier_purchases.sql`** before testing 4c UI.
  - ✅ **Stage 4d (accounts payable — supplier & private)** — shipped *(2026-04-27)*:
    - `sql/04d_supplier_accounts_payable.sql` — `supplier_purchases.bill_amount`,
      `bill_date`, `due_date` (ZAR); table `supplier_purchase_payments`.
    - `supplier_purchase_edit.php` — bill fields + payment list / add / soft-remove.
    - `supplier_ap_report.php` — outstanding balances, part-paid vs unpaid, summary by owed party.
    - `includes/header.php` — **Reports** → **Accounts payable (owed)** (consolidated 2026-05-01; was under Inventory).
    - ⛔ **Run `sql/04d_supplier_accounts_payable.sql`** in phpMyAdmin if not already run.
  - ✅ **4d / supplier purchase flow re-checked (2026-04-28 UTC+2):** hassan
    confirmed **All parts** shows **Purchase #** links; **Reports → Accounts
    payable (owed)** (`supplier_ap_report.php`) loads with balances; **Purchase
    #2** on `supplier_purchase_edit.php` — bill vs part **Asking** clarified;
    **Save purchase** (header) vs **Payments → Add** (payment lines) clarified.
    Client/staff printable HTML (open in browser → Print → PDF):
    `docs/manual_supplier_purchase_screen.html` (A–G quick sheet),
    `docs/supplier_purchase_screen_full_guide.html` (full section explainer).
- **Stage 5 — POS:** 🟡 **CODE SHIPPED + key UX tested** *(2026-04-28 UTC+2 — hassan said **go**;
  ⛔ run `sql/05_pos.sql` in phpMyAdmin before **POS** on a **new** database only.)*
  - ✅ **This dev DB** *(2026-04-28 UTC+2, phpMyAdmin Structure screenshot):* `sales_invoices` / `sales_invoice_lines` / `sales_invoice_payments` **exist** — sample counts **14 / 9 / 1** rows → **`05_pos.sql` already applied** here.
  - Tables: `sales_invoices`, `sales_invoice_lines`, `sales_invoice_payments`.
  - Pages: `invoices_admin.php` (list), `invoice_edit.php` (draft → **Finalize**, SHGA guard,
    stock/sold on parts, payments = customer **AR** balance). Nav: **POS** dropdown + **Reports** (invoices list duplicate).
  - **2026-04-28 follow-ups (same stage, no new SQL):** **`ajax/parts_search.php`** —
    authenticated JSON search; invoice **Select item…** modal (type / SKU / vehicle),
    click row → qty + **price ex VAT** (override), **Add to invoice**; draft lines —
    pencil **edit unit price** (`update_line_price`). **Letterhead:** built from
    **`assets/invoice-logo.png`** + PHP variables (cell left, address right, logo centre);
    full black bar on print/PDF via **`print-color-adjust: exact`**; banner also visible
    on screen as live preview. Fallback: full **`assets/invoice-letterhead.png`** if logo file
    missing. **`part_edit.php`** — soft JS warning when SKU prefix does not match **source**
    (e.g. `OEM-` vs Replacement). Optional CLI helpers: **`tools/trim_letterhead.php`**,
    **`tools/extract_logo.php`** (GD). Windows: save banner as **`invoice-letterhead.png`**
    only — avoid **`*.png.png`** double extension.
  - **Invoice → parts list → back:** Modal legend links (Stripped / Third Party / OEM / Replacement)
    open **`parts_admin.php`** with **`return=invoice_edit.php?id=N`** (whitelisted). Top blue bar
    **Back to invoice**; filters / pagination / **Clear** keep `return`. On a **draft**, the parts table
    gains an **Invoice** column: **Qty** + **Add to invoice** (`add_line_part`, same rules as search modal:
    **Available** + `qty_on_hand`). ✅ **hassan verified back bar** *(2026-04-28 UTC+2)*. Links without `return`
    (e.g. nav **All parts**) do not show the bar or column.
  - **Handoff (2026-04-28 UTC+2):** If a chat **jumped topics**, read **§10 "Resume handoff"** — sums up returns backlog (defer), training PNGs, customer-docs UX; **no half-finished code**; next steps are **§10 "Suggested next session"**.
  - **Not in this MVP:** email/WhatsApp reminders (see §10 AR + reminders), quotes vs invoices split,
    full credit limits. On-account = use **Due date** + record partial **Payments** until Remaining = 0.
- **Stage 6 — Reports / shop / polish:** ✅ **DONE & TESTED** *(code complete 2026-05-01 UTC+2; sales summary + credits block shipped 2026-05-02 UTC+2)* — **6a / AR / statements / shop / stripping / enquiries / sales summary** on disk:
  - **Nav (2026-05-01 UTC+2):** top bar **Reports** dropdown — AP (staff+), AR (all logged-in), **Sales invoices** (all), Customers shortcut for statements, **Web shop orders** / **messages** (staff+) · removed duplicate links from **Inventory** / **POS** (`includes/header.php`); **`docs/complete_system_manual.html`**, **`docs/reports_staff_guide_print.html`**, supplier/Git printouts, **`CLAUDE.md` §10**, **`ROADMAP.md`** paths updated.
  - ✅ **`sql/06a_customer_account.sql`** applied on hassan’s `autowagen_master` *(verified 2026-04-29 UTC+2 — `customers.account_customer` / `credit_limit_zar`; AR + customer modal).*
  - **`customer_ar_report.php`** — **Reports → Accounts receivable (owed)** (visible to all logged-in roles). Balances = final invoices − payments − **finalized credits** (**net due** includes both **AR reduction** and **cash refund** types when **`07`** applied). Extra columns **AR cr.** / **Refund** show the **`adjustment_type`** split · **Overdue** vs **As at** · **Print / PDF** · note: **not** supplier AP (that is **Reports → Accounts payable (owed)** · staff roles).
  - **`sales_summary_report.php`** — **Reports → Sales summary (period)** *(2026-05-01 · CN block 2026-05-02)*: POS turnover by **invoice date** vs payments by **paid date** · finalized **credit notes** in range by **credit date** (when **`07`** applied) — totals + AR vs cash-refund split + list · top customers · print/PDF · **no new SQL**.
  - **MySQL 8 / strict:** AR SQL avoids invalid date literal `'0000-00-00'` (use `1900-01-01` + `IS NOT NULL` in SQL; PHP `ar_due_is_meaningful()` on statement/AR pages).
  - **`customer_statement.php`** — printable **customer account statement**; **WhatsApp** (`wa.me` text summary; attach PDF from Print); **Email** (`mailto:` opens PC mail client). Links: **Statement** on AR report + next to name on **Customers** list.
  - **`customer_quick_add.php`** — draft invoice → **New customer (quick)**; **`apply_customer=`** redirect.
  - **`customers_admin.php`** — Account block (dark/red), list **Account** column + filter **Account customers**; **Statement** link.
  - **`invoice_edit.php`** — quick-add; finalize requires **account customer** if **due date** set (after **`06a`**).
  - **UI polish (2026-04-28 UTC+2):** global **red / black / white** form styling in `includes/header.php` (section headers, modals, fields, primary buttons); **`invoice_edit.php`** print/PDF keeps light grey card headers.
  - **Stage 6b — Public web shop (2026-04-28 code; `06b` SQL ✅ hassan 2026-04-29 UTC+2):** `parts.list_online`, `shop_orders`, `shop_order_lines`; `shop/*.php`; `shop_orders_admin.php`; **Inventory** → **Public shop (website)** · **Reports** → **Web shop orders** (nav updated 2026-05-01). **New + not stripped** only on web (SHGA). Payment gateway **not** in MVP — staff contact buyer after order.
  - **Stage 6b item A — smoke-test (see §10):** **`06b` ran** — full **numbered clicks** live under **`CLAUDE.md` §10** → **Suggested next session → A** (“Detailed numbered steps”). hassan executes in browser → log **PASS** in §9 when **WEB-…** order + stock check OK.
  - **Stage 6e — Public shop guest enquiries (2026-04-29 code, recovered from deleted chat):**
    `sql/06e_shop_guest_enquiries.sql` (`shop_guest_enquiries` table, idempotent),
    `shop/enquiry.php` (public message form — works for any part type),
    `shop_enquiries_admin.php` (staff inbox; **Reports → Web shop messages**), helper
    `shop_guest_enquiries_ready()` in `includes/shop_helpers.php`.
    ⛔ **Run `sql/06e_shop_guest_enquiries.sql`** in phpMyAdmin before
    using the message form / admin inbox.
  - **Stage 6 — Web checkout rule (UPDATED 2026-04-29 15:37 UTC+2):**
    `shop_part_purchasable_online()` now allows **OEM new OR Replacement**
    in **New / Good / Fair** condition (previously: OEM new = New only,
    Replacement = any except Scrap). **Poor** and **Scrap** stay
    enquiry-only. All on-screen wording in `part_edit.php`,
    `parts_admin.php`, `shop/index.php`, `shop/part.php`, `shop/cart.php`,
    `shop/enquiry.php`, and the cart error in `shop_helpers.php`
    were updated to match. No DB change.
  - **Stage 6f — Public stripping-stock catalogue (2026-04-29 code):**
    New URLs **outside** the parts grid: **`/shop/stripping/`** (filter by
    stock code / text + status; card grid uses first `vehicle_photos`
    thumb), **`/shop/stripping/vehicle.php?id=N`** (full gallery +
    specs + **Enquire about parts** → `enquiry.php?name_hint=…`).
    Staff: **Inventory → Stripping stock (website)** (`includes/header.php`).
    **`shop/_layout.php`** nav adds **Stripping stock**. Uses existing
    `vehicles` + `vehicle_photos` — **no new SQL**.
  - **Deferred (not Stage 6 — see `docs/BACKLOG_POST_STAGE7.md`):** SMTP server email, PayFast/Stripe on checkout, supplier AP return tooling, richer dashboards, legacy data migration scripts.

- **Stage 7 — Credit notes (linked returns):** ✅ **DONE & TESTED** *(hassan Test B PASS 2026-05-02 UTC+2)* — **`sql/07_credit_notes.sql`** on **`autowagen_master`** · **Reports → Credit notes** / **New credit note** · draft→finalize verified.
  - **Tables:** `sales_credit_notes`, `sales_credit_note_lines` — always tied to a **final** `sales_invoices` row; **`CN-YYYY-NNNNN`** on finalize.
  - **Files:** **`includes/credit_note_helpers.php`**, **`credit_notes_admin.php`**, **`credit_note_edit.php`**; **`invoice_edit.php`** — remaining = total − **all finalized** credits − payments (split summary when mixed types); **`customer_statement.php`** — **AR cr.** / **Refund cn.** columns + net **Balance**; **`includes/header.php`** — Reports → Credit notes.
  - **Rules:** restore **stock** for returned **part** lines on finalize; **AR reduction** vs **cash refund** (refund date/method for cash path); capped so credits do not exceed invoice face value net of earlier finals.
  - **Accounting display (with `07`):** **Net due** subtracts **all** finalized credits. **AR** + **statement** also show how much credit was **AR reduction** vs **cash refund** (same net). Handout: **`docs/credit_notes_ar_vs_cash_refund_print.html`**. Post–Stage 7 backlog: **`docs/BACKLOG_POST_STAGE7.md`**.
  - **Print handout:** **`docs/credit_notes_system_guide_print.html`** · **`docs/credit_notes_ar_vs_cash_refund_print.html`** (locked net + split columns) · both indexed in **`docs/client_training_index.html`**.
  - **Next for hassan:** on a **new PC/DB**, run **`07`** after **`05`**; otherwise optional deeper tests (void CN, multi-CN cap) as needed.

**Owner account:** username `hassan`, role `owner` in `users` table.

---

## 3. Files on disk (canonical list)

```
autowagen-master/
├─ CLAUDE.md                    ← this file (read first)
├─ ROADMAP.md                   ← long-form stage plan (reference)
├─ HOW_TO_START_NEW_CHAT.md     ← handoff prompt for new sessions
├─ docs/
│  ├─ client_training_index.html            ← give clients: links to all staff guides (open → Print PDF)
│  ├─ complete_system_manual.html           ← **full** manual: staff app + POS + web shop + AR + backups; figures `full-01`…`full-36`
│  ├─ client_install_print.html              ← install Autowagen on a client PC (Chrome/Edge → Print → PDF)
│  ├─ TRAINING_SCREENSHOTS.md                 ← how to attach real screen PNGs to the HTML guides
│  ├─ md_backups/                             ← optional snapshots of CLAUDE/ROADMAP/HOW_TO + TRAINING_SCREENSHOTS (see README)
│  ├─ manual_screenshots/                     ← optional PNGs for training (see TRAINING_SCREENSHOTS.md)
│  ├─ manual_supplier_purchase_screen.html   ← A–G training sheet (print → PDF)
│  ├─ supplier_purchase_screen_full_guide.html ← supplier purchase What/Why/When/How
│  ├─ invoice_screen_full_guide.html        ← Stage 5 invoice / POS (detailed zones S/P + figure placeholders; print → PDF)
│  ├─ pos_invoice_marketing_walkthrough_print.html ← POS brochure: figures A–L + marketing-pos-*.png → Print PDF
│  ├─ git_github_handout_print.html          ← Session handout (hosting + GitHub; Print → PDF)
│  ├─ git_laragon_terminal_start_to_finish_print.html ← Laragon terminal: cd → status → add → commit -m → push + PAT (Print → PDF)
│  ├─ CHANGELOG.md                           ← dated changes Markdown (pair with CHANGELOG.html; newest first)
│  ├─ CHANGELOG.html                         ← HTML mirror · Print → PDF
│  ├─ developer_quick_sheet_print.html       ← laminate: Git + triple-changelog reminders (Print → PDF)
│  ├─ add_users_staff_guide_print.html       ← add staff users / roles / phpMyAdmin / password hash (Print → PDF)
│  ├─ live_db_password_rotation_secrets_print.html ← live host: rotate MySQL password + secrets.live.php (Print → PDF)
│  ├─ database_update_backup_guide_print.html ← full DB replace vs incremental SQL / keep data (Print → PDF)
│  ├─ session_pause_handoff_print.html          ← before PC shutdown: Git · §9 · optional md_backup · Export DB (Print → PDF)
│  ├─ reports_staff_guide_print.html         ← AP / AR / statement / web orders — steps + illustration samples (PDF)
│  ├─ ar_report_and_customer_statement_explained_print.html ← AR report + statement: every section explained (PDF)
│  ├─ customer_ar_report_explained_abcdef_print.html ← AR report zones A–F + sample figures (Print → PDF)
│  ├─ supplier_ap_report_explained_print.html ← AP owed report: sections A–D + Print/PDF deploy note (Print → PDF)
│  ├─ credit_notes_system_guide_print.html    ← Stage 7 · credits · invoice/AR/statement · viewer vs shop → Print PDF
│  ├─ credit_notes_ar_vs_cash_refund_print.html ← AR vs cash (locked net + split columns) → Print PDF
│  ├─ rollout_execution_order_print.html      ← Live → test → backlog → test → polish (Print → PDF)
│  ├─ CURSOR_NEW_CHAT_MASTER_PROMPT.md          ← full “paste into new Cursor chat” project brain-dump
│  ├─ BACKLOG_POST_STAGE7.md                  ← rollout ladder at top · supplier returns · SMTP · shop payments
│  └─ SESSION_2026-05-04_HANDOFF.md           ← dated session diary · deploy checklist · resume · defer Phase C until client talk (copy also in md_backups/2026-05-04/)
├─ .gitignore
├─ index.php                    ← auth-aware redirect
├─ main_dashboard.php
├─ backups_admin.php            ← owner/admin: ZIP site backup (dated filename); downloads via this page only
├─ epc_browse.php               ← Stage 2 — public 6-col drill-down
├─ epc_full_tree.php             ← Stage 2 — printable 6-level EPC reference (expand/collapse)
├─ epc_admin.php                ← Stage 2 — owner/admin manager
├─ vehicles_admin.php           ← Stage 3 / 3b list (stock, status, papers)
├─ vehicle_edit.php             ← Stage 3 / 3b edit (17 fields + photos + EPC
                                  + Stage 4 stripped-parts card)
├─ customers_admin.php          ← Stage 3 / 3d / **6a** (compliance docs +
│                                 SA ID/CIPC + Docs N/2; account customer + credit limit after **`06a`**)
├─ customer_quick_add.php        ← Stage 6 — minimal new customer from POS → back to draft invoice
├─ customer_ar_report.php        ← Stage 6 — AR: who owes us (balances by customer; **includes credits** after **`07`**)
├─ sales_summary_report.php      ← Stage 6 — sales summary (invoice vs payments dates · finalized credits by credit_date after `07`)
├─ customer_statement.php        ← Stage 6 (+7) — statement; **AR cr.** / **Refund cn.** + net **Balance** when **`07`** applied
├─ credit_notes_admin.php      ← Stage 7 — list / start credit note from invoice
├─ credit_note_edit.php          ← Stage 7 — draft → finalize (stock restore, CN- number)
├─ invoice_edit.php             ← Stage 5–6 — POS invoice: draft / finalize / payments;
│                                 part-search modal + line price edit; **due date = account customer** (`06a`);
│                                 built HTML letterhead
├─ supplier_ap_report.php       ← Stage 4d accounts payable (owed)
├─ parts_admin.php              ← Stage 4 + 4b list (TPP Docs + purchase link);
│                                 optional `return=` from invoice modal → **Back to invoice** bar
├─ part_edit.php                ← Stage 4 + 4b/4c (TPP + purchase batch); 4d = bill on purchase page;
│                                 soft SKU/source prefix warning (JS); **6b `list_online`** after **`06b`**
├─ shop/                        ← Stage 6b/6e — **public** catalogue (no login): `index.php`, `part.php`, `cart.php`, `checkout.php`, `thanks.php`, `enquiry.php`, **`stripping/index.php` + `stripping/vehicle.php`** (wreck / stripping vehicles — photos from `vehicle_photos`)
├─ shop_orders_admin.php         ← Stage 6b — staff: web orders + cancel → restore stock
├─ shop_enquiries_admin.php      ← Stage 6e — staff: guest messages inbox (any part type)
├─ supplier_purchases_admin.php ← Stage 4c purchase list
├─ supplier_purchase_edit.php  ← Stage 4c/4d batch + bill + payments
├─ tpp_intakes_admin.php        ← redirects → supplier_purchases_admin
├─ tpp_intake_edit.php         ← redirects → supplier_purchase_edit
├─ ajax/
│  ├─ epc_cascade.php           ← Stage 2 JSON cascade endpoint
│  └─ parts_search.php          ← Stage 5 JSON parts search (invoice modal)
├─ assets/
│  ├─ invoice-letterhead.png     ← fallback full banner if logo file missing
│  └─ invoice-logo.png          ← logo strip for built HTML letterhead (print + screen)
├─ tools/                        ← optional one-off helpers (Laragon PHP CLI + GD)
│  ├─ trim_letterhead.php       ← crop empty vertical margins from banner PNG
│  └─ extract_logo.php          ← extract logo row from banner → invoice-logo.png
├─ auth/
│  ├─ login.php
│  └─ logout.php
├─ config/
│  ├─ env.php
│  ├─ config.php                ← single source of truth (PDO, csrf, e())
│  ├─ secrets.local.php         ← gitignored
│  └─ secrets.live.php.example
├─ includes/
│  ├─ auth_check.php            ← current_user(), user_has_role()
│  ├─ epc_helpers.php           ← EPC_LEVELS, slugify, next_sort
│  ├─ uploads.php               ← Stage 3b file-upload helper
│  ├─ credit_note_helpers.php   ← Stage 7 — CN table check, totals, next CN no., line qty helpers
│  ├─ header.php               ← Bootstrap nav (**Reports** dropdown 2026-05: AP · AR · invoices · statements shortcut · web orders/messages · etc.)
│  └─ footer.php
├─ uploads/                     ← gitignored (except .htaccess)
│  ├─ .htaccess                 ← denies PHP execution in uploads
│  ├─ vehicles/<id>/            ← created on demand (docs/, photos/)
│  ├─ customers/<id>/docs/      ← Stage 3d compliance scans
│  ├─ parts/<id>/photos/        ← Stage 4 part photos
│  ├─ parts/<id>/docs/         ← Stage 4b TPP compliance scans (per-part)
│  └─ supplier_purchases/<id>/docs/  ← Stage 4c purchase scans (if used)
├─ backups/                     ← ZIP site backups (gitignored except .htaccess); HTTP denied — use backups_admin.php
│  └─ .htaccess                 ← Require all denied (no direct download)
└─ sql/
   ├─ 01_auth.sql               ← Stage 1  (run)
   ├─ 02_epc.sql                ← Stage 2  (run)
   ├─ 02_audit_epc_steps.sql      ← READ ONLY: quick counts
   ├─ 02_verify_epc_integrity.sql ← READ ONLY: orphans + spine gap detection
   ├─ 02b_epc_categories_step1.sql ← EPC Level-1 expansion (optional; hassan step-by-step)
   ├─ 02c_epc_subcategories_step2.sql ← EPC Level-2 subcategories (all 12 categories)
   ├─ README_EPC_EXPANSION.txt ← order … → 02g → 02h → optional 02e
   ├─ 02d_epc_spine_step3.sql    ← EPC Levels 3–6 filler (General → … → OEM/Aftermarket)
   ├─ 02e_epc_variants_dismantler.sql ← optional Used/Scrap on spine “all-items” only
   ├─ 02f_epc_body_doors_types.sql  ← Body → Doors: real Types (shell, lock, glass, …)
   ├─ 02g_epc_types_all_subcategories.sql ← Types on every subcategory (~5 each); then re-run 02d, 02e
   ├─ 02h_epc_subsystems_per_type.sql   ← Every Type → Subsystem (+ “Type name — parts”); spine gaps
   ├─ 03_master_data.sql        ← Stage 3  (run 2026-04-26 17:47 UTC+2)
   ├─ 03b_vehicle_extras.sql    ← Stage 3b (run 2026-04-27 07:18 UTC+2)
   ├─ 03c_proof_of_residence.sql ← Stage 3c (run 2026-04-27 08:45 UTC+2)
   ├─ 03d_customer_compliance.sql ← Stage 3d (run 2026-04-27 11:38 UTC+2)
   ├─ 04_inventory.sql              ← Stage 4  (run 2026-04-27 09:45 UTC+2)
   ├─ 04b_part_tpp_compliance.sql   ← Stage 4b (run when ready)
   ├─ 04c_supplier_purchases.sql  ← Stage 4c
   ├─ 04c_tpp_intake.sql         ← stub only — run 04c_supplier_purchases.sql
   ├─ 04d_supplier_accounts_payable.sql ← Stage 4d AP (run after 4c)
   ├─ 05_pos.sql                  ← Stage 5 POS (invoices + lines + payments; run when ready)
   ├─ 06a_customer_account.sql    ← Stage 6a (`customers` account flags + optional credit limit)
   ├─ 06b_web_shop.sql            ← Stage 6b (`parts.list_online`, `shop_orders`, `shop_order_lines`)
   ├─ 06e_shop_guest_enquiries.sql ← Stage 6e (`shop_guest_enquiries`; run after 06b)
   └─ 07_credit_notes.sql         ← Stage 7 (`sales_credit_notes`, `sales_credit_note_lines`; run after 05)
```

---

## 4. Database tables in `autowagen_master`

Stage 1: `users`, `user_login_attempts`
Stage 2: `epc_categories`, `epc_subcategories`, `epc_types`,
`epc_subsystems`, `epc_components`, `epc_variants`, view `epc_full_view`
Stage 3: `vehicles`, `customers`, `suppliers`, `vehicle_epc_links`
Stage 3b (after `sql/03b_vehicle_extras.sql` is run): `vehicle_photos`
plus 17 new columns on `vehicles` (stock_code, status, transmission,
fuel_type, body_type, supplier_id FK, seller_name, seller_id_number,
seller_phone, purchase_price, date_acquired, purchase_notes,
has_logbook + logbook_path, has_sellers_receipt + sellers_receipt_path,
has_seller_id_copy + seller_id_copy_path, yard_location).
Stage 4 (after `sql/04_inventory.sql` is run): `parts`, `part_photos`,
`part_epc_links`. The `parts` table covers all 4 sources
(stripped / oem_new / replacement / third_party), with
auto-generated SKUs (per-vehicle counter for stripped, global per
source for the rest), 5-state lifecycle, 5 condition grades,
and a VAT-ready `vat_rate` column (default 0.00).

Stage 4b (after `sql/04b_part_tpp_compliance.sql` is run): 4 more
columns on `parts` — `has_tpp_id_doc`, `tpp_id_doc_path`,
`has_tpp_proof_of_address`, `tpp_proof_of_address_path` — for
**third-party private seller** buys (SHGA seller ID + proof of
residence). Cleared when the part is linked to a supplier or is not
`third_party`.

Stage 4c (after `sql/04c_supplier_purchases.sql` is run): table
`supplier_purchases` (seller/supplier + SHGA columns) and nullable
`parts.supplier_purchase_id` linking many parts to one purchase.

Stage 4d (after `sql/04d_supplier_accounts_payable.sql` is run): on
`supplier_purchases` — `bill_amount`, `bill_date`, `due_date` (ZAR bill);
table `supplier_purchase_payments` for part/full payments (soft-deactivate
with `is_active`). Balances = bill − sum(payments). Applies to
registered suppliers and private purchases.

Stage 3d (after `sql/03d_customer_compliance.sql` is run): 5 more
columns on `customers` — `sa_id_number`, `company_reg_number`,
`id_doc_path`, `has_proof_of_address`, `proof_of_address_path` — plus
two indexes (`idx_customers_sa_id`, `idx_customers_company_reg`).
These are the SA Second-Hand Goods Act paper trail for buyers; Stage 5
POS **enforces** ID doc + proof of address on file when an invoice line
links to a **stripped** part or a part whose **condition** is not **new**.

Stage 5 (after `sql/05_pos.sql` is run): `sales_invoices` (draft / final / void;
`invoice_no` assigned on finalize as `INV-YYYY-NNNNN`; optional `due_date` for
on-account); `sales_invoice_lines` (optional `part_id`, qty, VAT snapshot);
`sales_invoice_payments` (cash / eft / card / other; soft-remove via `is_active`).
**Net balance** for an invoice (used on invoice screen, AR, statement when credit tables exist):
`total_inc_vat` − sum(**final** `sales_credit_notes` for that invoice — **both** adjustment types) − sum(active payments).
**Reporting split** (does not change net): **`customer_ar_report.php`** / **`customer_statement.php`** sum **AR reduction** vs **cash refund** credits separately for display (`adjustment_type` on `sales_credit_notes`).
(Before **`07_credit_notes.sql`**, treat credit sum as zero.)

Stage 6a (after `sql/06a_customer_account.sql` is run): on `customers` —
`account_customer` (TINYINT, default 0) and optional `credit_limit_zar` (DECIMAL, nullable).
POS **finalize** requires `account_customer = 1` when the invoice has a **due date** set (on-account).
**Accounts receivable** report: `customer_ar_report.php` (balances per customer).

Stage 6b (after `sql/06b_web_shop.sql` is run): on `parts` — `list_online` (TINYINT, default 0).
Tables `shop_orders` (guest checkout, `order_no` like `WEB-YYYY-NNNNN`) and `shop_order_lines` (part snapshots + VAT).
Checkout **reduces `qty_on_hand`** / marks **sold** like POS finalize. **Cancel order** in `shop_orders_admin.php` restores stock.
Public catalogue: `shop/index.php` (no login). Only **New** + **not `stripped`** parts are eligible for listing (SHGA alignment).

Stage 6e (after `sql/06e_shop_guest_enquiries.sql`): table `shop_guest_enquiries` — public `shop/enquiry.php` + staff **`shop_enquiries_admin.php`**.

Stage 7 (after `sql/07_credit_notes.sql`): tables **`sales_credit_notes`**, **`sales_credit_note_lines`** — credit notes **`draft`/`final`**, **`credit_no`** like **`CN-YYYY-NNNNN`**, always FK to a **final** **`sales_invoices`** row; lines reference **`sales_invoice_lines`** and optional **`parts`** for stock restore on finalize. **`adjustment_type`**: `ar_reduction` | `cash_refund` (with optional refund date/method/ref fields).

Stage 3c (after `sql/03c_proof_of_residence.sql` is run): on `vehicles` — `has_proof_of_residence`, `proof_of_residence_path`; legal-paper slots **4/4**.

---

## 5. Conventions — apply to every new page

- Every page starts with: `require_once __DIR__ . '/config/config.php';`
  then (if private): `require_once __DIR__ . '/includes/auth_check.php';`
- Use `$pdo` prepared statements **only** — never interpolate user input.
- Output via `e($value)` (htmlspecialchars wrapper).
- Every form: include `<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">`
  and check with `csrf_check($_POST['csrf'] ?? '')` before mutation.
- Bootstrap 5 + Bootstrap Icons via CDN. Brand colour `#c8102e`,
  dark nav `#0a0a0a`. Every page extends `includes/header.php` +
  `includes/footer.php`.
- SQL files are **additive only** (`CREATE TABLE IF NOT EXISTS`) — re-runs
  must be safe.
- Soft delete only (`is_active = 0`). Never hard-DELETE master rows from
  the UI.
- New table needs `created_by INT UNSIGNED NULL` FK to `users.id`,
  set on insert from `$_SESSION['user_id']`.
- Pagination: 50 rows per page, inline helper per page.

---

## 6. Hard rules — NEVER do these

- ❌ Never modify the old `c:\laragon\www\autowagengit\` folder or the
  `autowagen` database. Both are read-only reference.
- ❌ Never hard-DELETE from `vehicles`, `customers`, `suppliers`, `users`
  in UI code — soft-delete only.
- ❌ Never put credentials in `config/config.php` or any committed file.
  All secrets live in `config/secrets.local.php` (gitignored).
- ❌ Never assume hassan knows command-line / git / phpMyAdmin shortcuts.
  Always give numbered, click-by-click steps.
- ❌ Never invent file or table state. If unsure, **read the file or
  query the DB before claiming anything is done**.

---

## 7. Update protocol — keep this file alive

After **any** of these events, append a row to the Session log
(section 9) **and** edit section 2 "Live state" so it matches reality:

1. A SQL file is run (record date, time, file, result).
2. A new PHP file is written or an existing one modified.
3. A test passes or fails (record which test, the outcome).
4. A stage flips status (NOT STARTED → IN PROGRESS → DONE & TESTED).
5. The user reports an error or a fix is shipped.
6. Any decision is made (e.g. "keep EPC seed", "skip feature X").

**Human changelog (pair):** Also append **the same dated entry** (newest at top)
to **`docs/CHANGELOG.md`** and **`docs/CHANGELOG.html`** with **Recorder name**
and **UTC+2 timestamp** — staff and git history can skim these without reading
§9 verbatim. Agents still maintain §9 in **this** file as the authoritative
running memory.

**Timestamp format:** `YYYY-MM-DD HH:MM TZ` (use UTC+2 / Johannesburg).
For today's stamps, use the date shown in the chat header. **Never
guess** a timestamp — if uncertain, write `(time unknown)` and ask.

If the file approaches ~200 lines, **prune** the Session log
(keep last 20 entries, archive the rest into `docs/SESSION_ARCHIVE.md`).

---

## 8. Common commands & URLs

- App home: `http://localhost/autowagen-master/`
- Login page: `http://localhost/autowagen-master/auth/login.php`
  (**Lockout:** six failed passwords for the **same username + IP** within **15 minutes** — then wait or clear `user_login_attempts` in phpMyAdmin; see `auth/login.php`.)
- phpMyAdmin: `http://localhost/phpmyadmin` → DB `autowagen_master`
- Run a SQL file: phpMyAdmin → click DB → **SQL** tab → paste contents
  of `sql/NN_*.sql` → click **Go**.
- New chat handoff: **`HOW_TO_START_NEW_CHAT.md`** (STEP **2** grey box) + read **`CLAUDE.md`** §**10**. Rollout order: **`docs/BACKLOG_POST_STAGE7.md`** top / **`docs/rollout_execution_order_print.html`**.
- **Site backup (ZIP):** `backups_admin.php` (owner or admin only) — **Dashboard** card or your **name** menu (top right).
- **Client PC install (PDF):** open `docs/client_install_print.html` in browser → **Print** → **Save as PDF**.
- **Staff manual + web shop (PDF):** open **`docs/complete_system_manual.html`** → **Ctrl+P** → **Save as PDF** (screenshots optional).
- **Money reports for clients:** **`docs/reports_staff_guide_print.html`** (AP · AR · customer statement · web shop orders · sample layouts).
- **Change log (browse / PDF):** **`docs/CHANGELOG.html`** or **`docs/CHANGELOG.md`** (Markdown is easiest for Git “go back” diffs).
- **Add staff users (roles, phpMyAdmin, password hash):** **`docs/add_users_staff_guide_print.html`** → open in **Chrome/Edge** → **Ctrl+P** → Save as PDF (**Pages: All**, clear text selection first — Cursor-only print may clip pages).
- **Database updates (full replace vs keep data):** **`docs/database_update_backup_guide_print.html`** — same PDF workflow as above.
- **Live hosting — rotate MySQL password / `secrets.live.php` (after leaked creds):** **`docs/live_db_password_rotation_secrets_print.html`** — Print → PDF.
- **Pause before PC shutdown:** **`docs/session_pause_handoff_print.html`** — Git · §9 · optional snapshot · DB export.
- **Git terminal — start to finish (PDF):** **`docs/git_laragon_terminal_start_to_finish_print.html`** — Laragon/Cursor terminal → **`git push`** + PAT troubleshooting.

---

## 9. Session log — append-only, newest at top

> Each entry: `YYYY-MM-DD HH:MM TZ — short description (who, what, result)`.

- **2026-05-04 (time unknown) UTC+2** — **Master new-chat prompt:** **`docs/CURSOR_NEW_CHAT_MASTER_PROMPT.md`** (paste-everything brain-dump for future sessions) · **`HOW_TO`** pointer · **`CLAUDE.md`** §10 quick table · **`CHANGELOG`**. **Recorder:** Hassan (Cursor).

- **2026-05-04 (time unknown) UTC+2** — **MD snapshot + session handoff:** **`docs/md_backups/2026-05-04/`** (CLAUDE · ROADMAP · HOW_TO · TRAINING_SCREENSHOTS · CHANGELOG · **`SESSION_2026-05-04_HANDOFF.md`**) · **`docs/md_backups/README.md`** latest · **`HOW_TO`** pointer · **`BACKLOG_POST_STAGE7.md`** Phase C = client-agree first · Recorder: Hassan (Cursor).

- **2026-05-04 (time unknown) UTC+2** — **AR printable A–F:** **`docs/customer_ar_report_explained_abcdef_print.html`** (zones A–F + mock tables → PDF); **`docs/client_training_index.html`** card · **`reports_staff_guide_print.html`** §2 · **`CLAUDE.md`** §3 · **`CHANGELOG`**. **Recorder:** Hassan (Cursor).

- **2026-05-04 (time unknown) UTC+2** — **AP report:** **`docs/supplier_ap_report_explained_print.html`** (A–D + deploy); toolbar **What A–D means** → handout; **`docs/client_training_index.html`** / **`reports_staff_guide_print.html`** · **`CHANGELOG`** · **`supplier_ap_report.php`**. **Recorder:** Hassan (Cursor).

- **2026-05-04 (time unknown) UTC+2** — **`supplier_ap_report.php`:** **Print / PDF** button + print CSS (hide nav/footer/filters/`Open` column; A4); on-screen **How to read this report** (**A–D**). **Recorder:** Hassan (Cursor).

- **2026-05-02 (time unknown) UTC+2** — **Print handout:** **`docs/live_db_password_rotation_secrets_print.html`** (live MySQL password + **`secrets.live.php`** server-only; GitHub purge link) · **`docs/client_training_index.html`** · **`CLAUDE.md`** §3/table · **`TRAINING_SCREENSHOTS`** · **`CHANGELOG`**. **Recorder:** Hassan (Cursor).

- **2026-05-02 (time unknown) UTC+2** — **Login lockout — MD docs synced:** **`CLAUDE.md`** §2 Stage 1 + §8 · **`ROADMAP.md`** Stage 1 · **`HOW_TO_START_NEW_CHAT.md`** (troubleshooting) · **`docs/TRAINING_SCREENSHOTS.md`** · **`CHANGELOG`** / **`CHANGELOG.html`** (deduped older “IP-only” row; added doc-sync bullets). **Recorder:** Hassan (Cursor).

- **2026-05-02 (time unknown) UTC+2** — **`auth/login.php` lockout:** **6** failed attempts / **15 min** per **lowercased username + IP**; **`LOGIN_MAX_FAILED_IN_WINDOW`**; success **clears** prior failures for that pair; **`CHANGELOG`**. **Recorder:** Hassan (Cursor).

- **2026-05-02 (time unknown) UTC+2** — **POS marketing PNGs filled in:** Copied hassan’s screenshots → **`docs/manual_screenshots/marketing-pos-a-…` through `…-l-…`**, enabled `<img>` in **`pos_invoice_marketing_walkthrough_print.html`**, **`MARKETING_POS_SOURCES.md`** lookup table. **Recorder:** Hassan (Cursor).

- **2026-05-02 (time unknown) UTC+2** — **Marketing POS PDF scaffold:** **`docs/pos_invoice_marketing_walkthrough_print.html`** — figures **A–L** + **`marketing-pos-*.png`** names · **`docs/client_training_index.html`** · **`docs/TRAINING_SCREENSHOTS.md`** · **`CLAUDE.md`** §3/UI table. **Recorder:** Hassan (Cursor).

- **2026-05-02 (time unknown) UTC+2** — **`customers_admin.php` modal fix:** Wrapped **`modal-content`** in **one `<form>`**; **`Save`** = native **`type="submit"`** (footer inside form; removes flaky **`.click()`** on **`visually-hidden`** submit); **Cancel / Close** = **`data-bs-dismiss="modal"`** (no full-page link = no confuse with submit). **Recorder:** Hassan (Cursor).

- **2026-05-02 (time unknown) UTC+2** — **`customers_admin.php`** + **`invoice_edit.php`:** Draft POS workflow — **`Open customers`** (SHGA card) passes whitelisted **`return=invoice_edit.php?id=N`**; customers list shows **Back to invoice** bar; **`return`** preserved in search/pagination/edit/toggle/delete/save; modal footer **Back to invoice** + **Close** keeps query; after **Save** / **delete doc**, modal does **not** auto-re-open when **`return`** is set (avoid “stuck” in modal). **Recorder:** Hassan (Cursor).

- **2026-05-02 (end of day) UTC+2** — **Session pause — hassan:** closing for the day. **When I return:** paste **`HOW_TO_START_NEW_CHAT.md`** STEP **2** into a new chat; read **`CLAUDE.md`** §**10** (**Pause — end of day**). **Next:** rollout **Phase A → B** (**`docs/BACKLOG_POST_STAGE7.md`** top / **`docs/rollout_execution_order_print.html`**). **Before PC off (optional):** **`HOW_TO`** “Before you shut down” · **`docs/session_pause_handoff_print.html`**. Repo pushed this session unless local edits pending.

- **2026-05-02 (time unknown) UTC+2** — **Rollout ladder (hassan):** **`docs/BACKLOG_POST_STAGE7.md`** — phases **A–E** (Live/Ops → test → one backlog block → test → polish/docs); **`docs/rollout_execution_order_print.html`** · **`docs/client_training_index.html`** card · **`CLAUDE.md`** §3 + §10 table · **`CHANGELOG`**.

- **2026-05-02 (time unknown) UTC+2** — **Stage 6 closure:** **`sales_summary_report.php`** — finalized **credit notes** in chosen period by **`credit_date`** (totals · AR reduction vs cash refund · table · links to **`credit_note_edit.php`**) when **`cn_tables_ready`** · **`CLAUDE.md`** §2 Stage **6** marked **DONE & TESTED** · deferred SMTP/PayFast/supplier AP → **`BACKLOG_POST_STAGE7.md`** · **`ROADMAP.md`** Stage 6 bullet · **`reports_staff_guide_print.html`** · **`complete_system_manual.html`** · **`sales_summary_report_client_print.html`** · **`main_dashboard.php`** copy · **`CHANGELOG`**.

- **2026-05-02 (time unknown) UTC+2** — **Training PDF:** **`docs/git_laragon_terminal_start_to_finish_print.html`** — Laragon/Cursor terminal → **`cd`** → **`git status`** / **`git add .`** / **`git commit -m`** / **`git push`** · GitHub PAT · Credential Manager · “everything up-to-date” · Vim escape · **`docs/client_training_index.html`** · **`CHANGELOG`**.

- **2026-05-02 (time unknown) UTC+2** — **Shutdown handoff pack:** **`docs/session_pause_handoff_print.html`** · **`HOW_TO_START_NEW_CHAT.md`** “Before you shut down” · **`CLAUDE.md`** §10 handoff block · **`docs/client_training_index.html`** · **`docs/md_backups/README.md`** numbered steps · **`ROADMAP.md`** · **`docs/TRAINING_SCREENSHOTS.md`**.

- **2026-05-02 (time unknown) UTC+2** — **Markdown sync:** **`CLAUDE.md`** §8 + §10 quick-docs table · **`HOW_TO_START_NEW_CHAT.md`** · **`ROADMAP.md`** · **`docs/TRAINING_SCREENSHOTS.md`** · **`docs/md_backups/README.md`** — pointers to **`add_users_staff_guide_print.html`** & **`database_update_backup_guide_print.html`** + browser PDF tip.

- **2026-05-02 (time unknown) UTC+2** — **Training PDF:** **`docs/database_update_backup_guide_print.html`** — full DB replace vs incremental **`sql/*.sql`** (keep customers/data); **`docs/client_training_index.html`** card · **`CLAUDE.md`** §3.

- **2026-05-01 (end of day) UTC+2** — **Session pause — hassan:** stopping for today. **Tomorrow:** open **`CLAUDE.md` §10** + paste from **`HOW_TO_START_NEW_CHAT.md`** Step 2. **Likely next actions (pick one):** (1) **Live deploy** — upload **`includes/header.php`** + **`main_dashboard.php`** if **Reports** missing on **`ahnwebdesigners.co.za`**. (2) Optional **web shop** smoke-test **`CLAUDE.md` §10 → A**. (3) **Backlog** — **`docs/BACKLOG_POST_STAGE7.md`** (supplier returns, SMTP, PayFast; **alternate** net-due rule = §7 **`docs/credit_notes_ar_vs_cash_refund_print.html`** — explicit go only). (4) **View-only user:** phpMyAdmin **`users.role`** = **`viewer`** (same login page). No code left half-finished this session.

- **2026-05-01 (time unknown) UTC+2** — **Training PDF:** **`docs/ar_report_and_customer_statement_explained_print.html`** — AR screen + customer statement explained (A1–A7 · B1–B7) · **`docs/client_training_index.html`** card · **`CLAUDE.md`** §3 + §10 table row.

- **2026-05-01 (time unknown) UTC+2** — **Credit notes — AR/cash reporting split:** helpers `cn_finalized_ar_reduction_total_for_invoice` / `cn_finalized_cash_refund_total_for_invoice`; **Net due** unchanged; **AR report** + **statement** columns **AR cr.** / **Refund**; **`invoice_edit.php`** credits split · **`credit_note_edit.php`** confirm text · **`docs/BACKLOG_POST_STAGE7.md`** · **`docs/credit_notes_ar_vs_cash_refund_print.html`** (incl. §7 alternate rule) · **`ROADMAP`** · **`sales_summary_report.php`** footnote · **`CHANGELOG`**.

- **2026-05-01 (time unknown) UTC+2** — **`main_dashboard.php`:** **Stages 1–7 live** badge · **What’s next** + note that **Stages 1–7** are **done** and the bottom row is **not** Stage 7 · **Future backlog** row uses badge **planned** (supplier returns / SMTP / PayFast). **Live site:** old hosting may lack **Reports** until **`includes/header.php`** is uploaded.


- **2026-05-02 (time unknown) UTC+2** — **Test B PASS — Credit notes (§10 suggested next session B):** hassan — **`sql/07_credit_notes.sql`** applied on **`autowagen_master`** (tables visible in phpMyAdmin); **Reports → Credit notes** / **New credit note** from a **final** invoice; **draft → finalize** smoke-test **PASS** (stock restore + **CN-…** + balances on invoice / AR / statement as coded).

- **2026-05-02 (time unknown) UTC+2** — **Read `CLAUDE.md` & continue:** §10 **synced** — **Stage 7** credit notes reflected in SQL steps **8–9** (`06e`, `07`), UI table row, **Suggested next session B** (credit smoke-test), **Resume handoff** + **backlog** wording (customer CN shipped; supplier returns still manual). Prior break snapshot remains **`docs/md_backups/2026-05-01/`**.

- **2026-05-01 (time unknown) UTC+2** — **Taking a break:** **Markdown snapshot** copied to **`docs/md_backups/2026-05-01/`** (`CLAUDE.md`, `ROADMAP.md`, `HOW_TO_START_NEW_CHAT.md`, **`docs/TRAINING_SCREENSHOTS.md`**, **`docs/CHANGELOG.md`**). **`docs/md_backups/README.md`** → latest **2026-05-01**. **Memory updated:** **`CLAUDE.md`** §2 **Stage 7 — Credit notes** (code shipped; ⛔ **`07_credit_notes.sql`**); §3/§4 paths + **`credit_notes_system_guide_print.html`**; AR/statement wording. **`ROADMAP.md`** / **`HOW_TO`** touch-ups. **Resume:** run **`sql/07_credit_notes.sql`** if needed → smoke-test credit note → **`CLAUDE.md` §10**.

- **2026-05-01 (time unknown) UTC+2** — **Client Sales summary PDF handout:** **`docs/sales_summary_report_client_print.html`** (purpose · turnover vs payments · numbered clicks · illustrative mock · Print→PDF) · **`docs/client_training_index.html`** card · **`CHANGELOG`**.

- **2026-05-01 (time unknown) UTC+2** — **`sales_summary_report.php`:** **Reports → Sales summary (period)** — POS-only read-only aggregates (invoice date range: final/draft/void totals, payments by paid date, top customers, line mix, 200-row list, Print/PDF). **`includes/header.php`** nav link · **`docs/complete_system_manual.html`** §9 pointers · **`docs/reports_staff_guide_print.html`** table row. **No new SQL.** **Stage 7 credit notes:** see §2 ( **`07_credit_notes.sql`** + guides); sales summary does not yet analyse CN lines separately.

- **2026-05-01 (time unknown) UTC+2** — **`docs/session_report_reports_menu_print.html`:** printable session report (Reports nav · **no SQL** for that work · viewer login via `users.role` · repo doc map · **one** deploy/verify next step) for hassan **Print → PDF**.

- **2026-05-01 (time unknown) UTC+2** — **Reports top menu (`includes/header.php`):** **`Reports`** dropdown — Accounts payable · Accounts receivable · Sales invoices · Customer statements shortcut (Customers list) · Web shop orders & messages (staff+) · deduped out of Inventory/POS; manuals + **`docs/reports_staff_guide_print.html`** + **`CLAUDE.md` §10** + **`ROADMAP.md`** aligned. **No SQL / no secrets change.**

- **2026-05-02 (time unknown) UTC+2** — **`CLAUDE.md` §10:** pinned **06e vs web messages** warning, **MVP not-built** (returns/SMTP/auto-deploy), expanded **“Quick — what open next”** doc table + changelog/laminate rows under UI table.

- **2026-05-01 (follow-up) UTC+2** — **`docs/developer_quick_sheet_print.html`:** one‑page laminatable Git + changelog (triple‑write) cheat sheet · linked from **`docs/client_training_index.html`**.

- **2026-05-01 ~12:00 UTC+2** — **CHANGELOG pair + full manual expanded:** **`docs/CHANGELOG.md`** / **`docs/CHANGELOG.html`** (Recorder + UTC+2; newest-first; protocol mirrors **`CLAUDE.md` §7**). **`docs/complete_system_manual.html`** — TOC §§10–16: public shop guest flow, staff list/orders (`Inventory → Web shop orders`), stripping catalogue, **`Inventory → Web shop messages`**, AR/statements/backups pointers; roles moved to §16; optional **`full-31`…`full-36`** appendix. **`docs/TRAINING_SCREENSHOTS.md`**, **`docs/client_training_index.html`** synced. **`CLAUDE.md` §8** changelog URLs.

- **2026-05-01 UTC+2** — **`docs/git_github_handout_print.html`:** printable **full session** recap (CLAUDE/§10, Laragon URLs, hosting vs GitHub vs live server, shop/06e reminders, Git/GitHub setup + daily `add`/`commit`/`push`, PowerShell+Vim troubleshooting, reboot/new-chat behaviour, printing PDF tip; browser → Print → PDF).

- **2026-04-30 UTC+2 (session end)** — **Printable handoff sheet:** `docs/HANDOFF_2026-04-30_PRINT.html` (browser → Print → PDF); **markdown snapshot** refreshed → `docs/md_backups/2026-04-30/`. **Come-back prompt** = read `CLAUDE.md` §10 first (same text in that HTML §1).

- **2026-04-30 UTC+2** — ✅ **Web shop smoke-test PASS** (hassan). **Public Gearbox menu** shipped: **`shop/_layout.php`** dropdown (All gearbox / Complete / Parts loose) → **`category.php?slug=transmission-driveline`** + **`line=`**; **`includes/shop_helpers.php`** — `shop_normalize_gearbox_line`, `shop_gearbox_line_sql_condition`, `shop_category_submenu_context`; **`shop/category.php`** uses shared submenu + gearbox placeholders. Mock: **`docs/mockups/shop-gearbox-nav-mockup.html`**. **No new SQL** — uses EPC category **`transmission-driveline`** (from **`02b`**); staff must tag parts there + clear part titles for lanes.

- **2026-04-30 (PC restart) UTC+2** — **Markdown snapshot refreshed:** same four files re-copied → **`docs/md_backups/2026-04-30/`** (hassan restart).

- **2026-04-30 ~12:00 UTC+2** — **Markdown snapshot:** copied the four canonical docs → **`docs/md_backups/2026-04-30/`**; **`docs/md_backups/README.md`** “Latest snapshot” = `2026-04-30`. **carry-on:** new chat still uses repo-root **`CLAUDE.md`** first; snapshots are a dated safety copy.

- **2026-04-29 ~16:50 UTC+2** — **Markdown snapshot:** copied `CLAUDE.md`, `ROADMAP.md`, `HOW_TO_START_NEW_CHAT.md`, `docs/TRAINING_SCREENSHOTS.md` → **`docs/md_backups/2026-04-29/`**; updated **`docs/md_backups/README.md`** “Latest snapshot”.

- **2026-04-29 (time unknown) UTC+2** — **Public stripping-stock pages:**
  `shop/stripping/index.php` (filters + grid), `shop/stripping/vehicle.php`
  (gallery + enquire link); **`shop/_layout.php`** + **`includes/header.php`**
  links; **`shop/enquiry.php`** prefills message when `name_hint` from vehicle.
  No SQL — uses `vehicles` + `vehicle_photos`.

- **2026-04-29 15:37 UTC+2** — **Web checkout rule widened** at hassan's
  request: OEM new AND Replacement are now buyable online for
  conditions New / Good / Fair (was OEM new = New only). One code
  change in `includes/shop_helpers.php` (`shop_part_purchasable_online`)
  + cart error wording; six on-screen copy updates in `part_edit.php`,
  `parts_admin.php`, `shop/index.php`, `shop/part.php`, `shop/cart.php`,
  `shop/enquiry.php`. Lints clean. **No SQL.** Effect on hassan's
  current data: OEM-0003 (starter v, Good) and OEM-0004 (bonnet, Good)
  will now show "Web" badge after a browser refresh.

- **2026-04-29 ~15:20 UTC+2** — **Recovered from deleted chat** (transcript
  `300f7b93-…jsonl` still on disk after Cursor UI delete). Logged
  Stage 6e (guest enquiries: `sql/06e_shop_guest_enquiries.sql`,
  `shop/enquiry.php`, `shop_enquiries_admin.php`) into §2 and §3.
  Files were created by the deleted chat but never written into
  `CLAUDE.md`. ⛔ Reminder: hassan still needs to run `06e` SQL.

- **2026-04-29 UTC+2** — ✅ **hassan ran `sql/06b_web_shop.sql`** in phpMyAdmin on `autowagen_master` — `shop_orders` / `shop_order_lines` + `parts.list_online` available; smoke-test: **List on website** on a **New, non-stripped** part → **`/shop/`**.

- **2026-04-28 UTC+2** — **Stage 6b — public web shop (MVP):** `sql/06b_web_shop.sql`, `includes/shop_helpers.php`, `shop/index.php` + `part.php` + `cart.php` + `checkout.php` + `thanks.php`, `shop_orders_admin.php`; `part_edit.php` / `parts_admin.php` **`list_online` + Web column**; **`includes/header.php`** — Inventory → **Public shop** / **Web shop orders**. Guest checkout reduces **`parts`** stock like POS; staff **Cancel order** restores stock. **Online = New + non-stripped only** (SHGA). No payment gateway in this MVP.

- **2026-04-28 UTC+2** — **Global form / section styling:** `includes/header.php` — black section bars + red accent on `card-header.bg-light`, `modal-header`, in-card `h2` titles (`vehicle_edit`), stronger labels + red focus ring on fields, `navbar-dark` for mobile toggler, brand-aligned `btn-primary` / `btn-danger` / `btn-outline-primary`; `invoice_edit.php` print CSS restores light headers on paper/PDF.

- **2026-04-29 UTC+2** — **`docs/client_install_print.html`:** install-on-client-PC guide (Laragon, SQL order, secrets, migration); **Print → Save as PDF**.

- **2026-04-28 UTC+2** — **`backups_admin.php` + `backups/.htaccess`:** owner/admin **Site backup (ZIP)** — filename `autowagen_backup_YYYY-MM-DD_HHMMSS.zip`; excludes `backups/` and `.git/`; dashboard + user menu links; `.gitignore` tracks `backups/.htaccess` only.

- **2026-04-28 UTC+2** — **Docs:** synced **Stage 6** bullets in `CLAUDE.md`; **ROADMAP.md** Stage 6 “started” line; **HOW_TO_START_NEW_CHAT.md** (+ `05_pos` / `06a`, **md_backups** pointer); new **`docs/md_backups/README.md`** + snapshot **`docs/md_backups/2026-04-28/`** (CLAUDE, ROADMAP, HOW_TO, TRAINING_SCREENSHOTS).

- **2026-04-28 UTC+2** — **`customer_statement.php`:** printable account statement per customer; **WhatsApp** (`wa.me` + truncated text), **Email** (`mailto:`); links from **AR** + **Customers** list.

- **2026-04-28 UTC+2** — **`customer_ar_report.php` fix (MySQL 8 / strict):** removed SQL literals `'0000-00-00'` (error 1525 on `prepare`); use `due_date >= '1900-01-01'` + `IS NOT NULL`; PHP helper `ar_due_is_meaningful()` for display/overdue rows.

- **2026-04-28 UTC+2** — **`customer_ar_report.php`:** overdue-by-due-date (vs **As at**),
  **Overdue only** filter, **Overdue** column by customer, invoice table with days overdue + **Print / PDF**;
  print hides nav/footer/actions; brand red/black title block.

- **2026-04-28 UTC+2** — **Stage 6a (AR + account customers) coded:** `sql/06a_customer_account.sql`;
  `customer_ar_report.php`, `customer_quick_add.php`; `customers_admin.php` (account block, list, filter);
  `invoice_edit.php` (`apply_customer`, finalize vs due date); **Reports → Accounts receivable (owed)**.
  ⛔ Run **`06a`** in phpMyAdmin before account UI / due-date finalize rule / AR “account only” filter.

- **2026-04-29 (time unknown) UTC+2** — **`main_dashboard.php`:** “What’s next” roadmap synced — Stages **4** and **5** **done**;
  badge **Stages 1–5 live · Stage 6 next** (replaced stale “Stage 3 active”).

- **2026-04-29 (time unknown) UTC+2** — **`epc_full_tree.php`** + **EPC → Full tree (reference)** in `includes/header.php`:
  read-only printable 6-level list (expand/collapse) from active `epc_*` + variants.

- **2026-04-28 UTC+2** — **`sql/02_verify_epc_integrity.sql`:** Read-only EPC health script
  (counts, FK orphans, missing types/subsystems/components/variants, `epc_full_view` smoke).

- **2026-04-28 UTC+2** — **`sql/02h_epc_subsystems_per_type.sql`:** Ensures each **Type** has a **Subsystem**
  (gap-fill `parts-group`); **renames** those rows to **`<Type name> — parts`**; completes missing
  **All items** / **OEM** / **Aftermarket** under new subsystems.

- **2026-04-28 UTC+2** — **Fix `02g` MySQL #1267 collation:** `CREATE TEMPORARY TABLE epc_type_seed`
  now uses **utf8mb4_unicode_ci** on string columns (matches `02_epc.sql`); avoids illegal mix with
  **utf8mb4_0900_ai_ci** on joins in phpMyAdmin (MySQL 8).

- **2026-04-28 UTC+2** — **`sql/02g_epc_types_all_subcategories.sql`:** Temp-table seed adds **~5 Types per subcategory**
  across all 12 categories (incl. Petrol/Diesel/Hydraulic/Exterior); doors slugs match **`02f`**. **After 02g, re-run `02d` + optional `02e`.**

- **2026-04-28 UTC+2** — **`sql/02f_epc_body_doors_types.sql`:** Real **Level 3 Types** under
  **Body → Doors & tailgate** (door shell, handle, lock, regulator, glass, hinges, seal) + spine to
  variants; clarifies ChatGPT-style lists vs generic **General** from `02d`.

- **2026-04-28 UTC+2** — **EPC Step 4 (optional):** `sql/02e_epc_variants_dismantler.sql`
  adds **Used / take-off** + **Scrap** variants only on **All items** (`all-items`) components
  from Step 3; seed components unchanged. `sql/README_EPC_EXPANSION.txt` lists full run order.

- **2026-04-28 UTC+2** — **EPC Step 3 (`02d_epc_spine_step3.sql`):** Adds a **minimum depth spine**
  for empty branches only: subcategory with no type → **General** → **Parts grouping** → **All items**
  → **OEM** + **Aftermarket**; seed paths (e.g. Petrol → Inline 4 → Cooling) unchanged. Idempotent.
  **hassan:** run in phpMyAdmin after 02c.

- **2026-04-28 UTC+2** — **EPC Step 2 (Level 2, all 12 categories):** **`sql/02c_epc_subcategories_step2.sql`**
  — ~72 new `epc_subcategories` rows via **`INSERT IGNORE`…`SELECT` on parent category `slug`** (no hard-coded category IDs). Keeps seed subs (Petrol, Diesel, Hydraulic, Exterior). **hassan:** run in phpMyAdmin after **02b**. Next: Step 3 = Types / subsystems / components / variants (by agreement).

- **2026-04-28 UTC+2** — **EPC Step 1 (Level 1 only):** New file **`sql/02b_epc_categories_step1.sql`**
  — adds nine `epc_categories` (Transmission & driveline → Accessories & consumables), updates
  `sort_order` on existing **Engine / Brakes / Body** so the 12-item list order matches; **`INSERT IGNORE`**
  safe on re-run. **hassan:** paste in phpMyAdmin when ready. **Next EPC step:** Level 2 subcategories
  (by agreement). Twelve categories = **first batch**; more top-level groups can be added later.

- **2026-04-28 UTC+2** — ✅ **DB check (hassan screenshot):** phpMyAdmin **`autowagen_master`** shows **Stage 5**
  tables present and in use: **`sales_invoices`** (14 rows), **`sales_invoice_lines`** (9),
  **`sales_invoice_payments`** (1). **No need to re-run `05_pos.sql`** on this database. Returns/credit-note
  tables still absent — expected (**deferred**).

- **2026-04-28 (time unknown) UTC+2** — **Handoff / pause:** hassan **diverged** in-session (returns Q&A,
  supplier vs customer returns, training PNGs/404, customer modal "No file chosen" UX, SHGA/finalize reminders).
  **No in-flight code change** left open. **MDs updated** with **§10 "Resume handoff"** so the next chat
  starts at one clear place. **Next:** smoke-test POS end-to-end or optional manual screenshots — see §10.

- **2026-04-28 (time unknown) UTC+2** — **`parts_admin.php` → draft invoice:** When opened with
  `return=invoice_edit.php?id=N` and invoice is **draft**, new **Invoice** column: qty + **Add to invoice**
  (`POST add_line_part` to `invoice_edit.php`, same rules as modal — **Available** + stock). Non-available
  rows show status hint. Non-draft invoice shows warning banner.

- **2026-04-28 (time unknown) UTC+2** — **Decision (hassan):** **Supplier returns** (extra/wrong goods, credits from supplier) — **defer** to later build; MVP stays manual (parts + purchase bill/payments). Logged under §10 backlog. Same era: customer returns/credit notes also backlog.

- **2026-04-28 (time unknown) UTC+2** — **`docs/complete_system_manual.html`** — single **full** client manual (login → EPC → master data → inventory → purchases/AP → POS), **30 figure** PNG slots (`full-01`…`full-30`), appendix checklist; **`client_training_index.html`** links it first.

- **2026-04-28 (time unknown) UTC+2** — **Training docs:** Expanded `docs/invoice_screen_full_guide.html` (zones **S** modal, **P** parts list + **Add to invoice**, **B** running total note, figure placeholders + commented `<img>` paths under `docs/manual_screenshots/`). New **`docs/TRAINING_SCREENSHOTS.md`** — explains AI cannot capture Laragon screens; hassan/staff add PNGs via Win+Shift+S; **`docs/manual_screenshots/.gitkeep`**. Updated **`CLAUDE.md`** §3, **`ROADMAP.md`**, **`HOW_TO_START_NEW_CHAT.md`**.

- **2026-04-28 (time unknown) UTC+2** — ✅ **hassan TEST: invoice → parts → Back to invoice** — Confirmed
  the **Select item…** modal source links and the **`parts_admin.php`** blue **Back to invoice** bar
  work end-to-end; filter/search does not drop `return`. (`CLAUDE.md` / `ROADMAP.md` / `HOW_TO_START_NEW_CHAT.md`
  updated this pass.)

- **2026-04-28 (time unknown) UTC+2** — **POS → parts list return UX (code):** `invoice_edit.php` Search Item
  modal legend links now pass `return=invoice_edit.php?id=N`. `parts_admin.php` shows a blue
  **Back to invoice** bar when `return` is whitelisted; hidden field + pagination + **Clear**
  preserve `return`. Tooltip + tip text updated. Icon class `bi-arrow-left`.

- **2026-04-28 (time unknown) UTC+2** — **Stage 5 invoice / POS polish (code):**
  `ajax/parts_search.php` (auth’d JSON: name, SKU, vehicle, available stock only);
  `invoice_edit.php` — **Select item…** modal, **`unit_price_override`** on part lines,
  draft **`update_line_price`** (pencil), built letterhead (phone L / logo C / address R),
  **`print-color-adjust: exact`** for black bar on Print/PDF, on-screen banner preview;
  fixed stray `<?php` after `header.php` (parse error). **`part_edit.php`** — soft
  **SKU vs source** prefix warning. Helpers: `tools/trim_letterhead.php`,
  `tools/extract_logo.php`. **Asset note:** use exact filename **`invoice-letterhead.png`**
  only — Windows “double `.png`” (`invoice-letterhead.png.png`) breaks detection. **hassan: letterhead Print/PDF OK.**

- **2026-04-28 (time unknown) UTC+2** — *(earlier same day)* First pass: letterhead as one PNG +
  `invoice_edit.php` print CSS; later replaced by **`invoice-logo.png` + HTML banner** + contact
  vars + **`print-color-adjust: exact`** (see newer log entry above).

- **2026-04-28 (time unknown) UTC+2** — ✅ **Stage 5 POS MVP coded** (hassan: **go**).
  Files: `sql/05_pos.sql`, `invoices_admin.php`, `invoice_edit.php`; `includes/header.php`
  **POS** menu. Features: draft sale, part lines + manual lines, SHGA check
  (stripped or non-new condition → customer docs on file), finalize → invoice number,
  reduce `qty_on_hand` / mark **sold**, payments + remaining balance, void without
  stock reversal. **⛔ hassan: run `05_pos.sql` in phpMyAdmin then smoke-test New sale.**

- **2026-04-28 (time unknown) UTC+2** — **Stage 5 BUILD BRIEF — draft subsection**
  **AR + reminders** added under **§10** (customers on account: prerequisites
  invoices/payments/balance, email SMTP + log, WhatsApp **v1** = `wa.me` helper,
  **v2** = Cloud API TBD; four **hassan confirms** decision lines left as **TBD**).
  No application code.

- **2026-04-28 (time unknown) UTC+2** — **Supplier purchase UX + client manual
  HTML (no PHP/SQL changes).** hassan: phpMyAdmin / parts list / **Accounts
  payable (owed)** / Purchase **#2** walkthrough; agent explained sections and
  **Save purchase** vs **Payments → Add**. Wrote `docs/manual_supplier_purchase_screen.html`
  and `docs/supplier_purchase_screen_full_guide.html` (print to PDF from Chrome).
  **Next:** Stage **5** = lock **BUILD BRIEF** (planning Q&A) then hassan says **go**
  to code POS; optional: add a real payment line on Purchase #2 and confirm AP
  **Remaining** drops.

- **2026-04-27 (time unknown) UTC+2** — ⏸ **Handoff for later:** hassan
  stopping for the day. Refreshed **section 10** (pause state, SQL order 04 →
  04b → 04c → 04d, UI map for purchases / AP, next = Stage 5 plan or smoke-test),
  **ROADMAP.md** (current state + SQL list + Stage 4 sub-stages), and
  **`HOW_TO_START_NEW_CHAT.md`** (resume note). No new application code
  in this pass — docs only.

- **Clarification (same era as entries below, not a new event):** **Stage 4c**
  ships as **`supplier_purchases`** + **`sql/04c_supplier_purchases.sql`**.
  Session-log lines below that say **`tpp_intakes` / `04c_tpp_intake.sql`**
  describe an **earlier name**; behaviour is the same feature under the
  new table/file names, with **`04c_tpp_intake.sql`** left as a **stub** only.

- **2026-04-27 (time unknown) UTC+2** — ✅ **Stage 4d (AP) built:** `sql/04d_supplier_accounts_payable.sql`,
  `supplier_ap_report.php`, bill + payments on `supplier_purchase_edit.php`, nav link. ZAR; one payment
  row per purchase; split EFT = two lines with same ref. SQL run on dev DB.

- **2026-04-27 (time unknown) UTC+2** — **Stage 4c (TPP batch) completed +
  nav + `parts_admin` fix.** One **`tpp_intakes`** row = seller + SHGA
  uploads once; many **`parts`** via `tpp_intake_id`. New files:
  `sql/04c_tpp_intake.sql`, `tpp_intakes_admin.php`, `tpp_intake_edit.php`;
  `part_edit.php` intake mode; `parts_admin` JOIN + **From** → Purchase #N
  link; `includes/uploads.php` intake helpers; **Inventory** menu → TPP
  purchases / New TPP purchase. **hassan: run `04c` in phpMyAdmin before use.**

- **2026-04-27 (time unknown) UTC+2** — **Stage 4b UX fix (TPP uploads).**
  Root cause: compliance **file inputs lived inside a hidden Bootstrap
  tab pane** — many browsers do not POST `multipart` file fields from
  `display:none` tabs. **Fix:** moved SHGA uploads **below** the
  supplier/private tabs (still in section 2); added `submit` handler to
  unhide both TPP tab panes so supplier/seller fields always POST.
  **Company TPP:** same doc slots + `parts_admin` **TPP docs** badge now
  applies to **all** third-party rows (not only private). Files:
  `part_edit.php`, `parts_admin.php`.

- **2026-04-27 (time unknown) UTC+2** — ✅ **Stage 4b SHIPPED (TPP SHGA
  compliance).** Wrote `sql/04b_part_tpp_compliance.sql` (4 cols on
  `parts`), `save_uploaded_part_compliance_doc()` in
  `includes/uploads.php`, `part_edit.php` private-seller tab + delete
  flows, `parts_admin.php` **TPP docs** column. **SQL not run in this
  session** — hassan must paste `04b` in phpMyAdmin before testing.
  Next: path B (stretch OEM/REP/TPP) then C (Stage 5 plan).

- **2026-04-27 13:21 UTC+2** — ✅ **Test 4 PASSED — STAGE 3 → DONE & TESTED.**
  hassan added supplier **HASSAN NIZAMIE** via `suppliers_admin.php`
  (screenshot 13:20). Green *Added supplier* alert; table shows 1
  supplier, Active. Section 2 + section 10 updated. Next: optional
  Stage 4b (TPP compliance docs) or Stage 5 design.

- **2026-04-27 (time unknown) UTC+2** — **Planning / direction.**
  hassan: (1) **Path A** — do Test 4 next (add one supplier). (2)
  **B add-on** — third-party parts must get **compliance docs**
  (SHGA alignment; not limited to text fields). Logged as backlog
  under Stage 4 in section 2. (3) **Path C** — asked if Stage 5 is
  *last*. **Answer: no** — **Stage 6** (reports / shop / polish) is
  after POS. Agreement: **update `CLAUDE.md` at each meaningful
  step** (session log + live state + section 10 as needed). No code
  in this entry.

- **2026-04-27 12:22 UTC+2** — ✅ **STAGE 3d DONE & TESTED + Test 3
  PASSED.** hassan picked option B (Stage 3d — customer compliance
  docs for SA Second-Hand Goods Act). YES on Q2 (separate SA ID for
  individuals / CIPC for businesses). Build sequence:
    1. Wrote `sql/03d_customer_compliance.sql` (5 columns + 2 indexes,
       INFORMATION_SCHEMA-guarded ALTERs). Ran cleanly in phpMyAdmin
       at ~11:38 UTC+2; he sent a screenshot showing all 5 columns
       on the `customers` Structure tab.
    2. Extended `includes/uploads.php` with `customer_uploads_dir()`
       + `save_uploaded_customer_doc()` (mirror of vehicle helpers,
       saves to `uploads/customers/<id>/docs/`).
    3. Rewrote `customers_admin.php` (~750 lines) — multipart modal
       with type-driven SA ID / CIPC switch (cm-individual-only and
       cm-business-only CSS classes, JS toggleable), two upload
       cards (ID copy / proof of address), `Docs 0/2` / `Docs 1/2`
       (yellow) / `Docs 2/2` (green) badge column, new compliance
       filter dropdown, modal title shows `Edit customer #N`.
    4. Mid-test bugs (both fixed in same session):
       - **Save button hidden:** original modal had the form wrap
         the whole modal-content, which broke Bootstrap's
         scrollable-modal flex layout — footer (with Save) fell
         off the bottom of the screen. Fix: form lives inside
         modal-body, footer is a sibling.
       - **Files not uploading:** Save button outside the form
         using `form="cm-form"` HTML5 attribute caused some
         browsers to drop multipart file fields. Fix: a real
         `<button type="submit">` lives inside the form
         (visually hidden), red footer button is `type="button"`
         and `.click()`s the inner submit. Also added
         `array_change_key_case` normaliser so any PDO casing
         quirks don't break `$mv['id_doc_path']` checks.
    5. Smoke test: hassan re-edited customer #3, picked an SA ID
       copy file, ticked "I have this on file", picked a proof
       of address file, hit Save. Both cards turned green
       `On file` with `View current scan` links. The customers
       list now shows customer #3 with green `Docs 2/2 ✓` badge.
  Lints clean throughout. No remaining blockers for Stage 3 master-
  data save flow. Test 4 (add a supplier) is the last residual
  Stage 3 task and unblocked.

- **2026-04-27 10:30 UTC+2** — ✅ **STAGE 4 DONE & TESTED.** hassan
  was unable to switch to a fresh chat (UI confusion) so the build
  happened in the same session. From 09:45 to 10:30:
    1. Wrote `sql/04_inventory.sql` — 3 tables (parts, part_photos,
       part_epc_links), all FKs in place, ran cleanly in phpMyAdmin
       at ~09:45 (after one false start where hassan ran 03c again
       by mistake — corrected and re-run).
    2. Wrote `parts_admin.php` (list / source+status+vehicle filters,
       pagination 50 / page) — loaded green, empty state.
    3. Updated `includes/header.php` to replace the greyed-out
       "Inventory (Stage 4)" placeholder with a real dropdown
       (All parts / Add part).
    4. Wrote `part_edit.php` (~620 lines, 7 cards, multipart form,
       conditional source-link block via JS, SKU auto-suggest with
       per-vehicle counter for stripped + per-source global counter
       for OEM/REP/TPP, photo gallery max 5, full EPC cascade
       picker writing to `part_epc_links`).
    5. Extended `includes/uploads.php` with `part_uploads_dir()` +
       `save_uploaded_part_photo()` (mirrors vehicle helpers).
    6. Updated `vehicle_edit.php` to add the "PARTS STRIPPED FROM
       THIS VEHICLE" card between Photos and EPC variants. Card
       loads from a fresh `parts WHERE vehicle_id=? AND
       source='stripped'` query, shows summary line + table.
    7. Mid-test bugfix: SKU was auto-generating as `AWG-AWG-0002-P01`
       (double prefix) because vehicles store the stock_code WITH
       the AWG- prefix already. Fixed `suggest_sku()` and 3 other
       places that prepended `'AWG-'` redundantly. hassan edited the
       wrong-SKU part by hand to `AWG-0002-P01`.
    8. Smoke test: hassan created the headlight (AWG-0002-P01,
       Good, On vehicle, R 849.99, yard A2), uploaded 3 photos, and
       confirmed the new card on the Toyota's page lists it.
  Lints clean throughout. NO REMAINING BLOCKERS for Stage 4.
- **2026-04-27 09:00 UTC+2** — 🟢 Stage 4 spec FINALISED. hassan
  picked option B (plan-before-build). After 5 multiple-choice
  questions, all design decisions are locked. He requested
  fresh-chat handoff for the actual build. This chat updated
  CLAUDE.md sections 2, 9, 10 + ROADMAP.md. **NO CODE WRITTEN.**
  Final decisions:
    Q1 SKU format        → vehicle-prefixed for stripped (AWG-XXXX-P##),
                           source-prefixed for the rest
                           (OEM-####, REP-####, TPP-####)
    Q2 Conditions        → 5 simple: New / Good / Fair / Poor / Scrap
    Q3 Sources           → 4 origins: Stripped / OEM new /
                           Replacement (aftermarket new) / Third-party
                           (bought-in, supplier OR private individual)
    Q4 Statuses          → 5 lifecycle: On vehicle / Available /
                           Reserved / Sold / Scrapped
    Q5 VAT               → NOT VAT-registered for now; schema must
                           store `vat_rate` column (DEFAULT 0.00) so
                           VAT can be flipped on later without rebuild.
  See section 10 for the full schema + page list + build order.
- **2026-04-27 08:54 UTC+2** — ✅ Test 5 PASSED (unintentionally).
  hassan opened the Toyota's edit page after the SQL ran, scrolled
  past LEGAL PAPERS straight to LINKED EPC VARIANTS, and added a
  link via the cascade picker. Screenshot shows one row in the
  links table: **Engine / Petrol / Inline 4 / Cooling / Radiator /
  Aftermarket** (linked at 08:47:15). He then asked "where's the
  table of stripped parts?" — confused EPC tagging (Stage 3) with
  inventory (Stage 4, not built yet). Agent explained the
  difference (catalog tag vs physical sellable part with price/
  photo/SKU). hassan picked **option B** — pause testing, plan
  Stage 4 BEFORE coding. He also flagged a critical Stage 4
  field requirement: needs a free-text **"Part Name"** field
  (what they call the part themselves, e.g. "Front Radiator
  Hilux", separate from the EPC tag).
  ⏭️ Stage 4 design discussion in progress. NO CODE until he
  signs off on the field list.
- **2026-04-27 08:45 UTC+2** — ✅ Stage 3c SQL PASSED. After two
  false starts (hassan refreshed the broken edit page instead
  of going to phpMyAdmin), agent walked him through the SQL run
  in 6 numbered steps. He pasted `sql/03c_proof_of_residence.sql`
  in phpMyAdmin → SQL tab and clicked Go. Screenshots show
  every prepared-statement block returning green
  "MySQL returned an empty result set (i.e. zero rows)" — the
  expected success for DDL via PREPARE/EXECUTE. Both new
  columns confirmed via the SQL block contents:
  `ADD COLUMN has_proof_of_residence TINYINT(1) NOT NULL DEFAULT 0`
  and `ADD COLUMN proof_of_residence_path VARCHAR(255) DEFAULT NULL`.
  Harmless deprecation warning #1681 about Integer display width
  ignored (cosmetic). DB blocker lifted. Next = refresh Toyota
  edit page, tick Seller's proof-of-residence box, upload a file,
  Save changes.
- **2026-04-27 08:35 UTC+2** — ✅ Stage 3c code shipped. hassan
  picked option A — add a 4th legal-paper slot for "Seller's
  proof of residence" (utility bill / bank statement). Files
  written: `sql/03c_proof_of_residence.sql` (2 columns,
  INFORMATION_SCHEMA-guarded prepared statements — same
  idempotent pattern as 3b), `vehicle_edit.php` (defaults,
  POST handler reads `has_proof_of_residence`, INSERT + UPDATE
  payloads include new col, file-upload handler for
  `proof_residence_file`, delete_doc map, papers-complete = 4,
  $docRows array gets 4th entry, card width changed from
  col-md-4 to col-md-6 col-lg-3 so 4 cards fit cleanly),
  `vehicles_admin.php` (SELECT, papers count /4, 4/4 badge,
  missing-papers tooltip). Lints clean. SQL NOT YET RUN.
  Color palette confirmed by hassan: red / black / white —
  matches existing brand (#c8102e + #0a0a0a). Old project
  full-dark UI sample is reference only, not a redesign brief.
- **2026-04-27 ~08:25 UTC+2** — ✅ Doc-upload pipeline tested.
  hassan uploaded a picture into the Logbook slot on the
  Toyota's edit page, clicked Save changes, got green "Saved"
  flash, "View current scan" link appeared. Confirms file
  upload + checkbox auto-tick + DB path persistence works.
  He then asked whether re-uploading deletes the previous
  file (yes — confirmed in code) AND whether we can add a
  4th slot for proof of residence (he picked option A —
  see next entry).
- **2026-04-27 08:11 UTC+2** — ✅ Test 2c Part A PASSED. hassan
  re-opened `vehicle_edit.php`, used unique VIN `TOY1234567` +
  plate `ND 1111` + stock code `0002` (year 2001 — minor typo
  vs. plan's 2002, no impact). Save succeeded. Screenshot of
  `vehicles_admin.php` shows both vehicles in the list with
  all Stage 3b columns rendering: AWG-0002, "Being stripped"
  badge, red Papers 3/3 badge, yard `bay 3`, 12,345 km, Active.
  Toyota plate ND 1111, VIN TOY1234567 confirmed unique. hassan
  then asked whether he needs to fill the EPC catalogue now.
  Answer: no — Stage 2 seed (Engine/Brakes/Body) is enough
  for a quick Test 5 link, no need for a full catalogue ever
  (he grows it over time as new parts come in). Next concrete
  step = Test 2c Part B (open Toyota, upload 1 paper + 1 photo).
- **2026-04-27 08:01 UTC+2** — hassan asked to switch to a fresh chat.
  Current chat updated CLAUDE.md to the precise resume point. Next
  agent must NOT write new code. Resume = walk hassan through
  re-filling the Toyota form on `vehicle_edit.php` using a UNIQUE
  VIN and plate (existing VW md owns VIN `1234567890` and plate
  `ND 0000`). Suggest VIN `TOY1234567` and plate `ND 1111` (or any
  free strings). Once Create vehicle goes green, scroll back up to
  show him the now-revealed Choose-file buttons, the new PHOTOS
  card, and the LINK EPC PARTS card. Then walk through one paper
  upload + one photo upload + delete. Then continue Tests 3/4/5.
- **2026-04-27 07:26 UTC+2** — Test 2c partial PASS / soft fail.
  hassan opened `vehicle_edit.php` (new vehicle), filled all 5
  sections (TOYOTA hilux 2002 bakkie, status `stripping`, fuel
  petrol, mileage 12345, private seller hassan SA-ID
  1234567890123, price 1234, papers all ticked, yard `bay 3`),
  clicked Create vehicle. Save FAILED with red banner *"Stock
  code, VIN or plate is already used by another vehicle"*. Root
  cause: re-used VW md's VIN `1234567890` + plate `ND 0000`.
  phpMyAdmin confirms vehicles table still has only the 1 VW row.
  Duplicate-key guard working as designed. Form bounced back
  blank with auto-suggested stock code AWG-0001 (which is also
  reserved). User confused that upload sections weren't showing —
  that's expected (intentional, requires saved vehicle ID).
- **2026-04-27 07:23 UTC+2** — Test 2c partial. `vehicle_edit.php`
  new-vehicle form opened, all 5 sections render correctly per
  screenshots. Form helper text confirms "Upload becomes available
  after the first save" + "Photos and EPC variant links can be
  attached after the vehicle is created" — intentional UX.
- **2026-04-27 07:18 UTC+2** — Test 2b PASSED. After SQL fix,
  hassan re-pasted `sql/03b_vehicle_extras.sql` in phpMyAdmin and
  clicked Go. Result: green "MySQL returned an empty result set"
  (success indicator for prepared-statement DDL). Verified via
  screenshots: `vehicle_photos` table now appears in left sidebar;
  `vehicles` Structure tab shows all 19 new columns
  (stock_code, status, transmission, fuel_type, body_type,
  supplier_id, seller_name, seller_id_number, seller_phone,
  purchase_price, date_acquired, purchase_notes, has_logbook,
  logbook_path, has_sellers_receipt, sellers_receipt_path,
  has_seller_id_copy, seller_id_copy_path, yard_location). DB
  blocker fully lifted. Moving to Test 2c (vehicle form re-test).
- **2026-04-27 06:51 UTC+2** — hassan ran `sql/03b_vehicle_extras.sql`
  in phpMyAdmin. Got `#1064 syntax error near 'IF NOT EXISTS' at
  line 26`. Root cause: original file used MariaDB-only
  `ADD COLUMN IF NOT EXISTS` syntax but Laragon ships **MySQL**
  (not MariaDB), which rejects it. Fix shipped the same chat:
  rewrote all 19 column adds in `sql/03b_vehicle_extras.sql` to use
  the same INFORMATION_SCHEMA-guarded `PREPARE/EXECUTE` pattern that
  was already used for indexes/FKs further down the file. Now
  compatible with MySQL 5.7+, MySQL 8+, and MariaDB 10.x. File is
  still idempotent. hassan needs to re-paste & re-run it.
- **2026-04-27 06:30 UTC+2** — hassan asked to switch to a fresh chat.
  Current chat updating .md files and handing off. Next agent must
  start by running `sql/03b_vehicle_extras.sql` (not by writing more
  code). hassan got stuck on Step 1 ("I can't open phpMyAdmin / I
  don't know the URL") — next agent must walk him through opening
  `http://localhost/phpmyadmin` first.
- **2026-04-27 06:28 UTC+2** — hassan loaded `vehicles_admin.php`
  before running `sql/03b_vehicle_extras.sql`. Got
  `PDOException: SQLSTATE[42S22]: Column not found: 'stock_code'`.
  Expected behaviour — new PHP queries new columns. Resolution:
  run the SQL. Code unchanged.
- **2026-04-27 06:42 UTC+2** — Stage 3b code shipped. Wrote
  `sql/03b_vehicle_extras.sql` (17 ALTER TABLE adds + new
  `vehicle_photos` table + idempotent FK/index adds via
  INFORMATION_SCHEMA checks), `includes/uploads.php` (5 MB cap,
  ext whitelist), rewrote `vehicle_edit.php` (multipart form, 5
  cards, file uploads, photo gallery, EPC linker preserved),
  rewrote `vehicles_admin.php` (stock code, status badge, papers
  3/3 badge, yard column, status filter), created `uploads/.htaccess`
  (deny PHP execution), updated `.gitignore`. Lints clean.
- **2026-04-27 06:35 UTC+2** — Schema decision finalised: 17 extra
  fields on `vehicles` + new `vehicle_photos` table. Stock code
  format AWG-XXXX (UNIQUE). hassan approved field list verbatim
  (clicked "Go — build it exactly as listed" after asking for
  plain-English explanations).
- **2026-04-27 06:14 UTC+2** — hassan answered: business type **1
  (strip & sell parts)**. Provided detailed SA legal requirements
  (log book, seller's receipt, seller SA ID copy), 7-photo cap,
  and stock-code format AWG-XXXX.
- **2026-04-26 18:42 UTC+2** — hassan stopping for the day. Session
  paused mid-Stage-3 testing. Awaiting his answer to "what kind of
  business are you running?" (decides extra vehicle fields). When
  he's back, resume at section 10 below.
- **2026-04-26 18:09 UTC+2** — Test 2 PASSED. hassan created vehicle
  `VW md, 2004, WHITE, plate ND 0000, mileage 120000` via
  `vehicle_edit.php`. He then requested MORE fields on the vehicle
  form. Agent asked which business type (1–5) before changing schema.
- **2026-04-26 18:08 UTC+2** — Test 1 PASSED. Top nav shows
  `Master data → Vehicles / Customers / Suppliers`. Vehicles list
  page loaded with "Add vehicle" button. Confirmed via screenshot.
- **2026-04-26 17:56 UTC+2** — Created `CLAUDE.md` as the project's
  go-to memory file. Researched current best-practice structure
  (Anthropic Claude Code docs + 2026 community guides). Updated
  `HOW_TO_START_NEW_CHAT.md` to point new chats at `CLAUDE.md` first.
- **2026-04-26 17:47 UTC+2** — hassan ran `sql/03_master_data.sql` in
  phpMyAdmin. Result: green success + harmless "Table already exists"
  notes (CREATE TABLE IF NOT EXISTS as designed). Tables `vehicles`,
  `customers`, `suppliers`, `vehicle_epc_links` confirmed visible
  in left sidebar of phpMyAdmin via screenshot.
- **2026-04-26 ~16:00 UTC+2** — Stage 3 code (vehicles_admin,
  customers_admin, suppliers_admin, vehicle_edit, sql/03_master_data.sql)
  reported written by previous chat; verified all 5 files present on disk.
- **2026-04-26 ~14:00 UTC+2** — User chose to **keep** EPC seed data
  as sandbox (Stage 2 housekeeping closed).
- **earlier** — Stage 1 (auth) and Stage 2 (EPC tree) built and
  tested. Owner account `hassan` exists. See `ROADMAP.md` for the
  full deliverables list.

---

## 10. What to do RIGHT NOW (next concrete step)

### Handoff — PC shutdown / end of day (always)

Before switching off: **`HOW_TO_START_NEW_CHAT.md`** → **“Before you shut down the PC”** — Git **`commit`** + **`push`**, append **§9 Session log** (one line + what’s next), optional **`docs/md_backups/YYYY-MM-DD/`** copy and phpMyAdmin **Export**. **Print:** **`docs/session_pause_handoff_print.html`**. Next session: read **§10** then **`HOW_TO_START_NEW_CHAT.md`** Step **2** paste into a **new chat**.

### Pause — end of day (hassan — latest)

**Stopped for today.** When you come back:

1. Open **`HOW_TO_START_NEW_CHAT.md`** → **STEP 2** → select the whole **grey box** → copy → **new Cursor chat** → paste → **Enter**.
2. Or paste the shorter **“overnight”** block under Step 2 in that file (says you are back and continue rollout).
3. The assistant will read **`CLAUDE.md`** (especially **section 10** and **section 2**). **Your next build order** is already written at the **top** of **`docs/BACKLOG_POST_STAGE7.md`**: **Phase A** Live/Ops → **Phase B** test → later **Phase C** backlog → **Phase D** test → **Phase E** polish. Printable: **`docs/rollout_execution_order_print.html`**.

**No code left half-finished this session** (last pushes: rollout ladder docs + Stage 6 sales-summary credits block).

### Pause — 2026-05-01 end of day (archived note)

Older pause text — see **Pause — end of day (latest)** above. Rollout ladder + **`HOW_TO`** STEP 2 updated **2026-05-02**.

### Where we are

- **Resume handoff (2026-04-28 UTC+2, amended 2026-05-01)** — If you **stopped mid-chat** or **jumped topics**, read this once:
  **Early session note:** **customer credit notes** are now **Stage 7 — code shipped** (see §2); run **`sql/07_credit_notes.sql`** before using them. **Supplier returns** remain **not built**; MVP = manual AP / part edits.
  **Docs/PDF:** `404` on `manual_screenshots/*.png` means the file was not saved yet — follow
  **`docs/TRAINING_SCREENSHOTS.md`**. **Customer modal** still shows "No file chosen" after save — normal;
  **On file** + **View current scan** mean the upload worked. **SHGA finalize:** same customer on the
  invoice must have **ID copy + proof of address** on file. **`Add to invoice`** on parts list =
  **Available** + **qty > 0** (source e.g. stripped vs OEM does not block; **Sold** / **On vehicle** do).
  **Nothing was left half-implemented in code** — next work is **your choice** from the numbered list below.

- **Stages 1–5** — Stage **5 POS** code is on disk. On **hassan’s current `autowagen_master` DB**, **`05_pos.sql` is already applied** *(confirmed 2026-04-28 UTC+2 — phpMyAdmin screenshot: `sales_invoices` 14 rows, lines 9, payments 1).* On a **new empty** database, **⛔ run `sql/05_pos.sql`**, then **POS → New sale**.
- **Invoice letterhead:** Built in **`invoice_edit.php`** (search PHP for `$letterheadCell` / `$letterheadAddress`). Images: **`assets/invoice-logo.png`** (preferred), else **`assets/invoice-letterhead.png`**. Print uses **`print-color-adjust: exact`** so the black bar appears in PDF; if Edge still drops backgrounds, **Print → More settings → Background graphics** ON.
- **Staff training (POS):** **`docs/invoice_screen_full_guide.html`** — open in browser, **Print → PDF**; optional screenshots per **`docs/TRAINING_SCREENSHOTS.md`** + **`docs/manual_screenshots/`**.
- **Staff training (whole system):** **`docs/complete_system_manual.html`** — one document login → POS; **Print → PDF**; figures **`full-01`…`full-30`**; index **`docs/client_training_index.html`**.
- **2026-04-28:** Stage **4d** UI + client HTML guides done earlier; POS MVP + search modal + letterhead polish same era.
- **Stage 6** (reports, polish) still **after** Stage 5 polish — not the final stage.
- **`06b` + item A (2026-04-29 UTC+2):** hassan ran **`06b_web_shop.sql`** on `autowagen_master`. **Smoke-test** = **§10** → **Suggested next session** → **A**, including **“Detailed numbered steps (staff + guest)”** below — run those clicks and log **PASS** or an issue in **§9**.

**Is Stage 5 the “last” thing?** No — **Stage 6** follows (reports, optional shop, polish).

### New machine or empty database (run in order)

In phpMyAdmin on **`autowagen_master`**, run each file’s contents once
(**SQL** tab → paste → **Go**), in this order, **skipping** any already applied:

1. `sql/04_inventory.sql` — base `parts` / `part_photos` / `part_epc_links`  
2. `sql/04b_part_tpp_compliance.sql` — TPP compliance columns on `parts`  
3. `sql/04c_supplier_purchases.sql` — `supplier_purchases` + `parts.supplier_purchase_id`  
4. `sql/04d_supplier_accounts_payable.sql` — bill fields + `supplier_purchase_payments`  
5. `sql/05_pos.sql` — `sales_invoices`, `sales_invoice_lines`, `sales_invoice_payments`  
6. `sql/06a_customer_account.sql` — `customers.account_customer`, `credit_limit_zar` (Stage 6a AR / on-account)
7. `sql/06b_web_shop.sql` — `parts.list_online`, `shop_orders`, `shop_order_lines` (public web shop)
8. `sql/06e_shop_guest_enquiries.sql` — `shop_guest_enquiries` (**after `06b`** · guest enquiries + **Reports → Web shop messages**)
9. `sql/07_credit_notes.sql` — `sales_credit_notes`, `sales_credit_note_lines` (**after `05`** · **Reports → Credit notes**)

If **`parts_admin`** or purchase screens show **unknown table/column** errors,
the matching **04\*** script above was not run. If **POS** pages show **missing
table**, run **`05_pos.sql`**. If **`Account customer`** UI / columns are missing,
run **`06a_customer_account.sql`**. If **`list_online` / shop** errors, run **`06b_web_shop.sql`**. If **guest enquiry** or **Web shop messages** errors, run **`06e`**. If **credit note** pages error on missing tables, run **`07`** (after **`05`**).

**Old URLs** `tpp_intakes_*.php` / `tpp_intake_*.php` **301-redirect** to
**`supplier_purchases_*.php`**.

### Where things live in the UI

| What | How to open |
|------|-------------|
| **This session report (Print → PDF)** | **`docs/session_report_reports_menu_print.html`** — Reports nav, SQL impact, viewer login, doc map, single next-step path |
| **New sale / invoices** | **POS** → **New sale** or **Sales invoices** → `invoice_edit.php` / `invoices_admin.php` (same list also under **Reports → Sales invoices**). |
| **New customer during a sale** | On a **draft** invoice, under Customer: **New customer (quick)** → `customer_quick_add.php` → returns with buyer selected; full record + SHGA scans: **Master data → Customers**. |
| **Sales summary (period, POS)** | **Reports** → **Sales summary (period)** → `sales_summary_report.php` (**`05_pos.sql`** · finalized **credit notes** block needs **`07_credit_notes.sql`** · **no extra SQL**) |
| **Who owes us (AR)** | **Reports** → **Accounts receivable (owed)** → `customer_ar_report.php` (needs **`05_pos.sql`**; optional **`06a`** for “account only” filter + `invoice_edit` due-date rule). |
| **Customer account statement** | `customer_statement.php?id=N` — **Statement** link on AR report / next to name on **Customers** list; or **Reports → Customer statements (Customers list)**. **Print / PDF**; **WhatsApp** (text summary); **Email** (`mailto:`). Attach PDF from Print for full copy. |
| **Site backup (ZIP)** | **Dashboard** → **Open backup**, or your **name** (top right) → **Site backup (ZIP)** → `backups_admin.php` (**owner / admin**). Files: `autowagen_backup_YYYY-MM-DD_HHMMSS.zip`. Database not included — export SQL separately in phpMyAdmin if needed. |
| **Add part to invoice (search)** | On a **draft** invoice: **Select item…** opens modal; type to search; click row; set qty & **Price ex VAT**; **Add to invoice**. |
| **Supplier batch purchase** (one purchase, many parts; company or private) | **Inventory** → **Supplier purchases** or **New supplier purchase** |
| **Bill + payments (ZAR, AP)** | Open a purchase → **`supplier_purchase_edit.php`**: set **Bill (ZAR)** / dates → add rows under **Payments (ZAR)** (one row per payment; split EFT = two rows, same ref in **notes**). |
| **Who you owe (report)** | **Reports** → **Accounts payable (owed)** → `supplier_ap_report.php` (**owner / admin / manager / staff**). |
| **Parts list** | **Inventory** → **All parts** (`parts_admin.php` defensively avoids a fatal JOIN if `supplier_purchases` is missing — run **4c** for full **Purchase #** links). |
| **Public spares shop** | **`/shop/`** (e.g. `http://localhost/autowagen-master/shop/`) — no login; **New + non-stripped** parts with **List on website**. Staff: **Reports** → **Web shop orders** → `shop_orders_admin.php`. |
| **Parts from invoice (browse & return)** | Draft invoice → **Select item…** → coloured **Stripped** / **Third Party** / **OEM** / **Replacement** links → **`parts_admin.php`** with blue **Back to invoice**; per-row **Add to invoice** (qty + POST) for **Available** parts with stock. |
| **Printable POS manual** | **`docs/invoice_screen_full_guide.html`** (+ optional PNGs per **`docs/TRAINING_SCREENSHOTS.md`**) |
| **POS marketing / brochure PDF (A–L figures)** | **`docs/pos_invoice_marketing_walkthrough_print.html`** |
| **Printable full-system manual** | **`docs/complete_system_manual.html`** — staff + POS + web shop + AR/backups — figures **`full-01`…`full-36`** (optional PNGs); entry **`docs/client_training_index.html`** |
| **Credit notes (returns)** | **Reports → Credit notes** (`credit_notes_admin.php`) · **New credit note** · **`docs/credit_notes_system_guide_print.html`** + **`docs/credit_notes_ar_vs_cash_refund_print.html`** → Print PDF |
| **Sales summary — client PDF briefing** | **`docs/sales_summary_report_client_print.html`** — what the report measures, how to run it, sample mock screen · **Print → PDF** for clients/partners |
| **Changelog + timestamp discipline** | **`docs/CHANGELOG.md`** (Markdown); **`docs/CHANGELOG.html`** (Print/PDF mirror); laminate **`docs/developer_quick_sheet_print.html`** |

### Pinned reminders — reopen anytime (hassan)

- **Guest enquiries / Web shop messages:** If **`sql/06e_shop_guest_enquiries.sql`** was **never** run on **that** database, the guest enquiry form and **`Reports → Web shop messages`** may **error** until phpMyAdmin runs **`06e`** once (`06b` ≠ `06e`).
- **Not built in MVP:** **Supplier** returns / AP credit automation (**manual** workaround). **SMTP** outbound email & auto reminders; **no magic deploy** — **GitHub** backs up **code** only; **live hosting** still needs **FTP/upload** or server **`git pull`** (+ host MySQL + server secrets).

### Quick — what path to open next (`docs/` on disk)

| You want… | Open |
|-----------|------|
| **Full “how to use Autowagen” manual** | **`docs/complete_system_manual.html`** → Print PDF optional |
| **Menu / links to all guides** | **`docs/client_training_index.html`** |
| POS invoice screen only | **`docs/invoice_screen_full_guide.html`** |
| POS story for brochures (A–L screenshots) | **`docs/pos_invoice_marketing_walkthrough_print.html`** |
| Supplier purchases | **`docs/manual_supplier_purchase_screen.html`** or **`supplier_purchase_screen_full_guide.html`** |
| New PC install | **`docs/client_install_print.html`** |
| Git workflow + triple changelog (Markdown/HTML/CLAUDE §9) | **`docs/developer_quick_sheet_print.html`** (+ **`CHANGELOG.md`**) |
| **Git terminal — Laragon → GitHub start to finish** | **`docs/git_laragon_terminal_start_to_finish_print.html`** |
| **AR report & statement — section-by-section PDF** | **`docs/ar_report_and_customer_statement_explained_print.html`** — Alerts, filters, every column · statement toolbar & table |
| **AR report — zones A–F (sample layout) PDF** | **`docs/customer_ar_report_explained_abcdef_print.html`** — Title/help/filters/totals/tables explained with illustration |
| **Supplier AP report — sections A–D (PDF)** | **`docs/supplier_ap_report_explained_print.html`** — Payable vs receivable · deploy note for **Print / PDF** button |
| **Add staff users (viewer/staff · phpMyAdmin · hashes)** | **`docs/add_users_staff_guide_print.html`** |
| **Live site: rotate MySQL password after leaked credentials** | **`docs/live_db_password_rotation_secrets_print.html`** — cPanel → `secrets.live.php` on server only |
| **SQL / DB: full replace vs incremental (keep customer data)** | **`docs/database_update_backup_guide_print.html`** |
| **Pause / shutdown — resume next session** | **`docs/session_pause_handoff_print.html`** · **`HOW_TO_START_NEW_CHAT.md`** (before shutdown block) |
| **Rollout order (Live → test → backlog → polish)** | **`docs/rollout_execution_order_print.html`** · top of **`docs/BACKLOG_POST_STAGE7.md`** |
| **Paste-everything prompt (full project context for a new chat)** | **`docs/CURSOR_NEW_CHAT_MASTER_PROMPT.md`** — long handoff; **`CLAUDE.md`** still wins if conflict |
| AI/project diary & “what next” | Repo root **`CLAUDE.md`** · especially **§10** |

### Suggested next session (pick one)

**B — Credit notes smoke-test (Stage 7 · run `07` first)**

1. Open **`http://localhost/phpmyadmin`** → click database **`autowagen_master`** → **SQL** tab.  
2. Open project file **`sql/07_credit_notes.sql`** in Notepad/Cursor, copy all, paste into phpMyAdmin, click **Go**. Expect green success (no “table doesn’t exist” errors on refresh).  
3. In the app: **Reports → Credit notes** (or open any **final** invoice → **New credit note**).  
4. Pick return quantities, **Save draft**, then **Finalize** on a test line; confirm **CN-…** number, **stock** on the part increased, and **invoice** **Remaining** / **AR** / **Statement** show the credit.  
5. Log **PASS** in **`CLAUDE.md` §9** when satisfied.

**A — Web shop smoke-test (`06b` already ran on hassan’s DB)**

**Quick summary:** (1) Staff: create or edit a part so **Source** is **not** Stripped, **Condition** = **New**, **Status** = **Available**, **Qty on hand** ≥ 1, tick **List on public website**, **Save**. (2) Open the public **`/shop/`**, add to cart → **Checkout** → **Place order**. (3) Staff: **Inventory → All parts** (stock/status) + **Reports → Web shop orders** (**WEB-…**). *(Optional)* **Cancel order** on `shop_orders_admin.php` → stock restores.

**Detailed numbered steps (staff + guest)**

**Why “New” + “not Stripped”?** The website only lists parts that match SA Second-Hand Goods rules in code: **`shop_helpers.php`** — **new condition** and **source ≠ stripped**. A stripped headlight (**AWG-…‑Pxx**) cannot go on the web; use **Inventory → Add part** with **OEM new**, **Replacement**, or **Third-party**, or edit an eligible row.

**Part A — List a part (logged in)**

1. Open your browser and go to: `http://localhost/autowagen-master/auth/login.php`
2. Log in as usual (owner account **`hassan`**).
3. In the black top navigation bar, click **Inventory**.
4. In the dropdown, click **Add part**.

   *If you prefer to reuse an existing part:* **Inventory → All parts** → **skip rows where Web = N/A** (cannot go online — wrong source/condition). Click the row’s dark **pencil** button → go to section **“5. Yard location & notes”** → step **13**.

5. In **Section 1** (part identity): type a **Part name** you will recognise later (example: **`Web shop test bulb`**).
6. Set **Source** to **OEM new**, **Replacement**, or **Third party** — **do not** choose **Stripped from vehicle**.
7. Fill in **SKU** if empty (or leave blank briefly so save can suggest one — if the form requires it, type any unique SKU, for example **`OEM-09999`**).
8. Set **Condition grade** dropdown to **New** (must be **New** or the tick below will not stay on the website).
9. Set **Lifecycle status** to **Available**.
10. Set **Qty on hand** to at least **1** (try **2** if you want to see stock drop but not zero).
11. Set **Selling price — asking (ex VAT)** to something simple (example **`100`**).
12. Scroll down to card **“5. Yard location & notes”**.
13. Put a tick in the box labelled **“List on public website”** (hint text explains **New** + **not stripped**).
14. Scroll to the bottom and click the red button **Create part** (new part) or **Save changes** (existing part).
15. Wait for the green success message.

**Part B — Public shop as guest (catalogue has no login)**

16. Open the shop in **either** way: (**a**) press **Ctrl+T**, type **`http://localhost/autowagen-master/shop/`**, Enter **or** (**b**) stay in your staff tab → **Inventory** → **Public shop (website)** — that opens **`/shop/`** in a **new tab** automatically.
17. You should see **“Spares for sale”**. Use **Search SKU or name** or scroll until you see **your part title** from step 5.
18. Click that part until the **detail** page loads (address bar ends with **`/shop/part.php?id=…`**).
19. Under **Qty**, leave **1** (or increase if you deliberately set qty on hand **>** 1 in step 10).
20. Click the red **Add to cart** button (expect a green **Added to cart** banner).
21. Open the cart: click **Cart** (with trolley icon) in the grey shop bar **or** go to **`http://localhost/autowagen-master/shop/cart.php`**.
22. Check the table shows **SKU**, **name**, **Qty**, and a line total.
23. Click the large red **Checkout** button.
24. On **Checkout**, type **Your name** * and **Phone** * (required fields).
25. *(Optional.)* Email, **Delivery / collection address**, **Notes**.
26. Click the bottom red **Place order** button.
27. You should land on **Thanks** with an order number like **`WEB-2026-00001`** (digits may differ) — **write it down** for step 30.

**Part C — Confirm in the staff app**

28. Switch to your **logged-in staff** browser tab (or log in again at **`/auth/login.php`** if you closed it).
29. Click **Reports** → **Web shop orders**.
30. Find the row whose **Order** column matches **`WEB-…`** from step 27. On the far right, click **Open**.
31. On the order detail page (lines table + totals), scroll down until you see the red-bordered **Cancel order & restore stock** button — *(optional test)* click it → **OK** in the confirmation popup → you should see a green **Order cancelled and stock restored** message.
32. Click **Inventory** → **All parts** and locate your test part — compare **Qty on hand** and **Lifecycle status** to what you **expect** (**Sold** only if qty went to **0**). If you did step **31**, qty should rise again and status **Available**.

**If you want the single “what next” answer:** On **this PC’s `autowagen_master`**, **skip running `05_pos.sql`** — tables are already there *(2026-04-28 screenshot).* Do a **POS → New sale** practice run when you feel up to it, or **optional** training PDF PNGs (`TRAINING_SCREENSHOTS.md`). Later: **AR brief** or **Stage 6**.

1. **Run `sql/05_pos.sql`** *(only on a **new** database that has no `sales_invoices` table)*, then smoke-test **POS → New sale** (draft, customer, **Available** part line, **Finalize**, payment if on account).
2. **AR + reminders** — still **not coded**; fill **Stage 5 BUILD BRIEF** below when ready.
3. **Backlog (not scheduled):** **Supplier returns** (credits / AP adjustments) — handle manually; **customer** credit notes beyond current **Stage 7** scope (e.g. split AR vs cash-refund maths only for AR reports — product decision pending).

### Stage 5 BUILD BRIEF (draft — not locked)

> Fill in **hassan confirms** lines; replace **TBD** when decided. Core POS topics
> (invoice vs quote, SHGA at sale, VAT, etc.) still go here as they are agreed.

#### AR + reminders (customers **on account** — money they owe **you**)

- **Distinction:** **Supplier AP** (Stage 4d) = you owe suppliers. **AR / on account**
  = customers owe you after a sale; needs **Stage 5** data: invoices (or account sales),
  **payment lines**, **balance** (total − payments), **due date** or terms per invoice/customer.
- **Data we need before reminders work:** Customer **email** + **mobile** on file
  (extend `customers` if missing); invoice totals; payments; computed **amount owed**;
  optional **credit limit** / “may buy on account” flag — **hassan confirms:** TBD.
- **Email reminders (proposed):**
  - **v1:** Screen = list customers with **positive balance owed** (filters: overdue, due soon, min amount);
    **Send reminder** per customer or small batch; body = template with merge fields
    (`{name}`, `{balance}`, `{due_date}`, `{invoice refs}`).
  - **Send path:** SMTP from `secrets.local.php` (no secrets in repo); log each send
    (who, when, channel, template) to avoid duplicate nagging and for audit.
  - **v2 (optional):** Scheduled job (e.g. Windows Task Scheduler → PHP CLI) for
    nightly “overdue” pass — **hassan confirms:** TBD (yes / later / no).
- **WhatsApp (proposed):**
  - **v1:** No Meta API — UI offers **copy text** + **`https://wa.me/27…?text=…`**
    link so staff opens WhatsApp with a prefilled message (quick, compliant enough for internal use).
  - **v2 (optional):** WhatsApp Business **Cloud API** + approved templates for
    fully automated outbound — **hassan confirms:** TBD (defer / plan budget).
- **Four decisions — hassan confirms (replace TBD):**
  1. **Credit / due date:** Fixed shop-wide terms (e.g. 30 days), **or** per-invoice
     due date, **or** per-customer default — **TBD**.
  2. **Reminder schedule:** e.g. 3 days before due, on due date, 7 / 14 / 30 days
     overdue — **TBD** (which triggers, how often max).
  3. **Channels at go-live:** Email only **or** email + **wa.me** helper — **TBD**.
  4. **Opt-in:** Record that customer agreed to account messages (email/WhatsApp) — **TBD**
     (required field yes/no; where stored).

**Status:** POS **MVP** shipped **2026-04-28 UTC+2**. **AR / email / WhatsApp
reminders** — not built yet; lock this block before that work.

### After the next work block

- Append **section 9** (session log) with what ran on the DB and what was tested.  
- Update **section 2** if a stage flips or first-run SQL is recorded.

### Protocol — after file / SQL / test changes, update this file

- Append a session-log entry (section 9) with the result.
- If a stage status changed, update section 2.
- If files were created, update section 3.
- If new tables, update section 4.

### Hard rules for the next agent

- ❌ Don't touch legacy `autowagengit/` or `autowagen` DB.
- ✅ Use the existing patterns (PDO prepared, CSRF, e(), soft-delete,
  Bootstrap 5 cards, `#c8102e` red, `#0a0a0a` nav).
- ✅ For any new SQL: additive + idempotent + INFORMATION_SCHEMA-guarded
  for ALTER (mirror the pattern in `sql/03b_vehicle_extras.sql`).
- ✅ For new uploads: extend `includes/uploads.php`, don't reinvent.
- ✅ When putting a `<form enctype="multipart/form-data">` inside a
  Bootstrap modal, **the real submit button must live INSIDE the
  form** (visually hidden is fine). The visible footer button is
  `type="button"` and `.click()`s the inner one. Otherwise some
  browsers drop file inputs. (This is the bug we just fixed in
  `customers_admin.php` — see Stage 3d session log for details.)

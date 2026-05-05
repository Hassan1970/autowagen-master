# Detailed AI build prompt — Autowagen Master (full project specification)

**Purpose:** Give an AI coding agent enough **structure, rules, and stage order** to **implement or reproduce** this application. It does **not** replace reading **`CLAUDE.md`** and **`ROADMAP.md`** in the repo — those stay the live diary. **If this prompt conflicts with the repo, the repo wins.**

**Audience:** Another AI, or a senior developer, building the system from a blank folder + empty MySQL database.

**Print PDF (this file + live `CLAUDE.md`):** open **`docs/BUILD_WHOLE_PROJECT_AND_CLAUDE_print.html`** in Chrome/Edge → **Ctrl+P** → **Save as PDF** (large document). **Refresh** that HTML after edits: run **`php tools/generate_build_claude_combined_print.php`** or **`powershell -ExecutionPolicy Bypass -File tools/generate_build_claude_combined_print.ps1`** from the project folder.

---

## 1. Mission and constraints

### 1.1 What this is

- **Autowagen Master** is a **clean rebuild** of a legacy PHP app for a **South African vehicle dismantler / parts seller** (strip & sell parts — SA legal compliance matters).
- **Legacy reference only:** `c:\laragon\www\autowagengit\` and MySQL database **`autowagen`** — **never modify, never migrate blindly**. Use only as read-only comparison.
- **New workspace:** `autowagen-master\` (or equivalent path). New database: **`autowagen_master`** (local Laragon/phpMyAdmin or hosted MySQL).

### 1.2 Hard rules (never break)

1. **No Composer** — plain PHP 8 includes only.
2. **PDO only**, **prepared statements** for all dynamic SQL. Never concatenate user input into queries.
3. **Secrets:** no passwords in committed files. **`config/secrets.local.php`** (gitignored) for dev; **`config/secrets.live.php`** on server only for production — never commit real credentials.
4. **Output** via `e()` (htmlspecialchars). **CSRF** on every mutating form: hidden `csrf` field + `csrf_check()` on POST.
5. **Soft delete** on master entities (`is_active = 0`). **Do not** hard-delete vehicles, customers, suppliers, users from UI.
6. **`uploads/`** — deny PHP execution (`.htaccess`); uploads via helpers in `includes/uploads.php` (size cap ~5 MB, extension whitelist).
7. **`created_by`** — `INT UNSIGNED NULL` FK to `users.id` on new business tables; set from `$_SESSION['user_id']` on insert.
8. **Timezone:** `Africa/Johannesburg` (UTC+2) in PHP and project docs.
9. **UI:** Bootstrap 5 + Bootstrap Icons (CDN). Brand **`#c8102e`**, nav **`#0a0a0a`**.
10. **Pagination:** 50 rows per list page unless specified otherwise.

### 1.3 Stack summary

| Layer | Choice |
|--------|--------|
| Runtime | PHP 8 |
| Database | MySQL (MariaDB-compatible patterns for DDL use INFORMATION_SCHEMA guards where needed) |
| HTTP | Apache (e.g. Laragon on Windows) |
| Frontend | Bootstrap 5, no build step |
| Auth | PHP sessions, password_hash, role-based `user_has_role()` |

---

## 2. Staged implementation order (do not skip)

Build and **apply SQL + smoke-test** each stage before the next. SQL files live in **`sql/`**, are **additive** (`CREATE TABLE IF NOT EXISTS`; ALTERs idempotent where used).

### Stage 1 — Foundation

**Goal:** Config, PDO, CSRF, `e()`, login/logout, layout shell, dashboard redirect.

**SQL:** `01_auth.sql` → tables **`users`**, **`user_login_attempts`**.

**Behaviour:**

- `config/config.php` loads env + secrets, exposes `$pdo`, helpers `csrf_token()`, `csrf_check()`, `e()`.
- `auth/login.php`: CSRF, session regenerate on success. **Lockout:** configurable max failed attempts per **lowercased username + client IP** in a rolling window (e.g. 6 failures / 15 minutes); log attempts in `user_login_attempts`; **successful login removes failed rows** for that pair.
- `includes/auth_check.php`: `current_user()`, `user_has_role()` for `owner` / `admin` / `manager` / `staff` / `viewer`.
- `includes/header.php` + `includes/footer.php`: shared nav; iterate roles for menu visibility.
- `index.php`: auth-aware redirect. `main_dashboard.php`: post-login landing.

**Deliverables checklist:** Login works; unauthorized pages redirect; no secrets in git.

---

### Stage 2 — EPC (six-level parts catalogue tree)

**Goal:** Canonical hierarchy for tagging parts and vehicles.

**Levels:** **Category → Subcategory → Type → Subsystem → Component → Variant**

**SQL:** `02_epc.sql` → `epc_categories`, `epc_subcategories`, `epc_types`, `epc_subsystems`, `epc_components`, `epc_variants`, view **`epc_full_view`**. Rules: parent FKs with `ON DELETE CASCADE` where appropriate; **`UNIQUE (parent_id, slug)`** per level; `is_active`, `sort_order`, timestamps.

**PHP:**

- `includes/epc_helpers.php` — level metadata, `epc_slugify`, `epc_next_sort`.
- `ajax/epc_cascade.php` — authenticated JSON cascade for dropdowns (optional `include_inactive` for admin).
- `epc_browse.php` — 6-column drill-down for any logged-in user.
- `epc_admin.php` — owner/admin: CRUD-ish (add, rename, reorder, toggle active) with CSRF.
- Optional: `epc_full_tree.php` — printable reference.

**Optional expansion (separate SQL files, run in project-documented order):** `02b` … `02h`, `README_EPC_EXPANSION.txt` — expands categories/spine; re-verify with read-only scripts `02_verify_epc_integrity.sql`.

---

### Stage 3 — Master data (vehicles, customers, suppliers)

**SQL:** `03_master_data.sql` → `vehicles`, `customers`, `suppliers`, **`vehicle_epc_links`** (composite PK vehicle_id + variant_id). Unique indexes on vehicle VIN/plate when present. Soft-delete.

**SQL (3b):** `03b_vehicle_extras.sql` — extended vehicle fields for dismantler: `stock_code` (e.g. **AWG-XXXX**), status, transmission, fuel, body, supplier FK, private seller fields, purchase info, **legal document flags + paths** (logbook, receipt, seller ID), yard location; table **`vehicle_photos`**.

**SQL (3c):** `03c_proof_of_residence.sql` — 4th paper: seller proof of residence.

**SQL (3d):** `03d_customer_compliance.sql` — customer **SA ID / CIPC**, `id_doc_path`, proof of address — **Second-Hand Goods Act** paper trail for **buyers**.

**PHP:**

- `vehicles_admin.php`, `vehicle_edit.php` (multipart uploads, photo gallery, EPC linker).
- `customers_admin.php`, `suppliers_admin.php`.
- Extend `includes/uploads.php` for vehicle, customer, part paths (see later stages).

**Rule:** Stage 5 POS will **enforce** customer ID + proof-of-address on file when selling **stripped** parts or **non-new** condition parts.

---

### Stage 4 — Inventory (parts)

**SQL:** `04_inventory.sql` → **`parts`**, **`part_photos`**, **`part_epc_links`**.

**Part model:**

| Dimension | Values |
|-----------|--------|
| **source** | `stripped`, `oem_new`, `replacement`, `third_party` |
| **condition** | `new`, `good`, `fair`, `poor`, `scrap` |
| **status** | `on_vehicle`, `available`, `reserved`, `sold`, `scrapped` |
| **SKU** | Stripped: `AWG-<vehicle_stock>-P##`; OEM/REP/TPP: `OEM-####`, `REP-####`, `TPP-####` with per-scope counters |
| **VAT** | Column `vat_rate` default `0.00` (VAT not registered yet but schema-ready) |

**SQL (4b):** `04b_part_tpp_compliance.sql` — TPP private-seller compliance doc columns on `parts`.

**SQL (4c):** `04c_supplier_purchases.sql` → **`supplier_purchases`**, nullable **`parts.supplier_purchase_id`** — one purchase, many parts. Legacy URL names may redirect to `supplier_purchases_*`.

**SQL (4d):** `04d_supplier_accounts_payable.sql` — bill fields on purchase + **`supplier_purchase_payments`**; **`supplier_ap_report.php`** — **Accounts payable (owed)**.

**PHP:** `parts_admin.php`, `part_edit.php`; purchase admin/edit; vehicle page shows stripped-parts summary.

---

### Stage 5 — POS (sales invoices)

**SQL:** `05_pos.sql` → **`sales_invoices`** (draft/final/void, `invoice_no` **INV-YYYY-NNNNN** on finalize), **`sales_invoice_lines`** (optional `part_id`, qty, VAT snapshot), **`sales_invoice_payments`**.

**Behaviour:**

- Draft invoice: add lines (parts from **`ajax/parts_search.php`** modal + manual lines); **line-level price override** on part lines in draft.
- **Finalize:** assign invoice number; reduce **`qty_on_hand`** / mark sold for parts; enforce **SHGA** (customer docs) when lines require it.
- **Payments:** cash/EFT/card/other; soft-deactivate payment rows; show remaining balance.
- **Void:** defined behaviour without silent stock corruption (per implementation).
- **Print:** HTML letterhead (`assets/invoice-logo.png` or fallback banner), **`print-color-adjust: exact`** for black bar in PDF.
- **Parts list return path:** `parts_admin.php?return=invoice_edit.php?id=N` with **Back to invoice** bar when whitelisted.

---

### Stage 6 — Reports, AR, public shop, stripping catalogue, enquiries

**SQL (6a):** `06a_customer_account.sql` — **`customers.account_customer`**, optional **`credit_limit_zar`**. **Finalize** with **due date** requires account customer.

**SQL (6b):** `06b_web_shop.sql` — **`parts.list_online`**, **`shop_orders`**, **`shop_order_lines`**. Guest checkout reduces stock like POS; staff cancel restores.

**SQL (6e):** `06e_shop_guest_enquiries.sql` — **`shop_guest_enquiries`**.

**PHP (high level):**

- `customer_ar_report.php`, `customer_statement.php`, `sales_summary_report.php`.
- `customer_quick_add.php` from draft invoice.
- **`includes/shop_helpers.php`** — e.g. **`shop_part_purchasable_online()`**: only **non-stripped** parts; **OEM new** or **Replacement** in **New / Good / Fair** (not Poor/Scrap for buy-online — enquiry instead); rules must match UI copy across `part_edit`, `parts_admin`, shop pages.
- Public **`shop/`** — index, part, cart, checkout, thanks, `enquiry.php`; **`shop/stripping/`** — vehicles being stripped (uses `vehicles` + `vehicle_photos`, no extra SQL).
- `shop_orders_admin.php`, `shop_enquiries_admin.php`.
- Nav: consolidate **Reports** (AP, AR, invoices, statements link, web orders, messages, sales summary, credit notes after Stage 7).

**Not in MVP:** payment gateway, SMTP bulk email, automated WhatsApp API.

---

### Stage 7 — Credit notes (customer returns)

**SQL:** `07_credit_notes.sql` (after `05`) → **`sales_credit_notes`**, **`sales_credit_note_lines`** — always linked to a **final** invoice; **`credit_no`** **CN-YYYY-NNNNN** on finalize; **`adjustment_type`**: `ar_reduction` vs `cash_refund`.

**Behaviour:**

- Draft → finalize: **restore stock** for returned part lines; cap credits vs invoice and prior finalized credits.
- **Net due** on invoice/reporting subtracts **all** finalized credits; AR/statement can **split display** AR reduction vs cash refund without changing net.

**PHP:** `includes/credit_note_helpers.php`, `credit_notes_admin.php`, `credit_note_edit.php`; integrate **`invoice_edit.php`**, **`customer_ar_report.php`**, **`customer_statement.php`**.

---

## 3. Canonical SQL run order (new empty database)

Run in phpMyAdmin (or mysql CLI) **in order**, skipping files already applied:

1. `01_auth.sql`
2. `02_epc.sql` (+ optional `02b` → `02c` → `02d` → optional `02e` → optional `02f` → `02g` → `02h` per `README_EPC_EXPANSION.txt`)
3. `03_master_data.sql` → `03b` → `03c` → `03d`
4. `04_inventory.sql` → `04b` → `04c` → `04d`
5. `05_pos.sql`
6. `06a_customer_account.sql`
7. `06b_web_shop.sql`
8. `06e_shop_guest_enquiries.sql` (after 6b)
9. `07_credit_notes.sql` (after 5)

---

## 4. Accounting and reporting semantics (must stay consistent)

- **Accounts receivable (AR):** money **customers owe you** — `customer_ar_report.php`. Not the same as AP.
- **Accounts payable (AP):** money **you owe suppliers / sellers** — `supplier_ap_report.php`.
- **Sales summary:** turnover by **invoice date** vs cash by **payment date**; optional **credit notes in range by credit date** when Stage 7 tables exist.
- **Customer statement:** printable; `wa.me` + `mailto:` helpers for staff (not automated SMTP).

---

## 5. Repository and documentation hygiene

- After meaningful code/SQL/docs changes: prepend **`docs/CHANGELOG.md`** + **`docs/CHANGELOG.html`**; append one line to **`CLAUDE.md`** section 9 Session log; update **`CLAUDE.md`** section 2 if stage status changes.
- Training index: **`docs/client_training_index.html`** — links printable HTML guides (Print → PDF).

---

## 6. Explicitly out of scope (post-MVP / backlog)

Do **not** treat these as required for a first complete build unless the product owner re-opens them:

- Supplier **return** credits / AP automation
- **SMTP** reminders; **PayFast/Stripe** on shop checkout
- Full **legacy data import** from old DB (plan separate migration after schema proven)
- **Quotes** split from invoices; advanced **credit limits** automation

See **`docs/BACKLOG_POST_STAGE7.md`**.

---

## 7. Acceptance testing (minimum per stage)

| Stage | Minimum smoke test |
|-------|---------------------|
| 1 | Login, logout, lockout behaviour, dashboard |
| 2 | Browse tree to deepest variant; admin add/toggle node |
| 3 | Create vehicle with stock code + upload one doc; create customer with 2/2 compliance docs |
| 4 | Create stripped part + OEM part; SKU format; photo upload; optional purchase batch + AP line |
| 5 | Draft invoice, part from search, finalize, stock decreases; print PDF |
| 6 | Account customer + due date rule; list part online; guest order; order appears in admin; optional cancel restores |
| 7 | Credit note from final invoice; finalize restores stock; AR/invoice remaining match rules |

---

## 8. How to use this document with an AI agent

1. **Attach** this file **plus** repo **`CLAUDE.md`** (or zip the whole `autowagen-master` folder).
2. Instruction example: *“Implement Stage N following BUILD_WHOLE_PROJECT_AI_PROMPT.md; use prepared statements and project conventions; do not modify the legacy `autowagengit` folder.”*
3. For **full greenfield rebuild**, say: *“Build Stages 1 through 7 in order; after each stage list files created and SQL applied; stop for my confirmation before the next stage.”*

---

*File: `docs/BUILD_WHOLE_PROJECT_AI_PROMPT.md` — Autowagen Master. Keep in sync when architecture or stage boundaries change.*

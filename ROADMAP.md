# Autowagen Master — Project Roadmap

> This file is the source of truth for what we're building.
> If a new chat ever loses context, just say "Read ROADMAP.md and continue".

## Current state  (last updated: 2026-05-02 UTC+2 — end-of-day handoff: HOW_TO STEP 2 + CLAUDE §10 Pause)

**Resume:** **`HOW_TO_START_NEW_CHAT.md`** → **STEP 2** → paste into **new chat**. **`CLAUDE.md`** §**10** (**Pause — end of day**) · rollout **`docs/BACKLOG_POST_STAGE7.md`** Phase **A→B** first. **Before PC off:** same file → **Before you shut down the PC** · **`docs/session_pause_handoff_print.html`**. **Stage 7 — Credit notes:** ✅ **DONE & TESTED** (hassan **Test B PASS** · **`sql/07_credit_notes.sql`** on **`autowagen_master`**). **Print notes:** **`docs/credit_notes_system_guide_print.html`** (system) · **`docs/credit_notes_ar_vs_cash_refund_print.html`** (locked **net due** + **AR cr. / Refund** split on AR & statement). **Owner / IT handouts:** **`docs/add_users_staff_guide_print.html`** (staff users · phpMyAdmin · bcrypt hash) · **`docs/database_update_backup_guide_print.html`** (full DB replace vs incremental migrations · keep customer data). Indexed in **`docs/client_training_index.html`**. **Markdown snapshots:** **`docs/md_backups/`** — optional dated folders; **`README.md`** explains.

**Near-term backlog:** see **`docs/BACKLOG_POST_STAGE7.md`** (supplier returns, SMTP, shop payments). **Credit note AR/cash split (display + locked net rule)** — ✅ in app *(2026-05-01 UTC+2)* — `customer_ar_report.php`, `customer_statement.php`, `invoice_edit.php`, `includes/credit_note_helpers.php`, `docs/credit_notes_ar_vs_cash_refund_print.html`.

---

- ✅ **Stage 0 — Setup:** folder + empty DB created
- ✅ **Stage 1 — Foundation:** auth system live, owner account exists, login/logout tested
- ✅ **Stage 2 — EPC:** 6-level tree live (browser + manager + cascade JSON + view); seed data verified
- ✅ **Stage 3 — Master data:** DONE & TESTED (sub-stages 3 + 3b + 3c + 3d; Tests 1–5 including
  supplier Test 4 — e.g. **HASSAN NIZAMIE** on `suppliers_admin.php`). All SQL applied.
- ✅ **Stage 4 — Inventory & Parts (+ 4b / 4c / 4d):** DONE & TESTED in dev.
  Core: `04_inventory.sql`; TPP seller compliance columns: `04b_part_tpp_compliance.sql`;
  one **supplier purchase** → many parts: `04c_supplier_purchases.sql` (`supplier_purchases`,
  `parts.supplier_purchase_id`); **accounts payable** (bill + payments + owed report):
  `04d_supplier_accounts_payable.sql`. UI: `parts_admin.php`, `part_edit.php`,
  `supplier_purchases_admin.php`, `supplier_purchase_edit.php`, `supplier_ap_report.php`,
  Inventory nav (parts, purchases, AP). On a **new** PC/DB, run **04 → 04b → 04c → 04d** in order
  (see `CLAUDE.md` section 10).
- 🟡 **Stage 5 — POS:** MVP on disk — invoices, lines, payments (`sql/05_pos.sql` if DB not yet migrated).
  **2026-04-28:** `ajax/parts_search.php` + invoice **Select item…** modal; draft line price edit;
  built HTML letterhead (`assets/invoice-logo.png` + PHP contact vars); print black bar via
  `print-color-adjust`. **`part_edit.php`** SKU/source prefix warning. **Browse parts from invoice:**
  modal coloured links → `parts_admin.php` with **`return=invoice_edit.php?id=N`** → blue **Back to invoice**
  bar (filters/pagination keep it). ✅ **hassan confirmed return flow works** *(2026-04-28 UTC+2)*.
  See `CLAUDE.md` §2–§3, §10 UI table.
  **Staff manuals:** `docs/invoice_screen_full_guide.html` (print → PDF) + `docs/TRAINING_SCREENSHOTS.md` (how to add real screenshots); supplier guides in same `docs/` folder.
  **Full system (one PDF):** `docs/complete_system_manual.html` — login through POS with ~30 screenshot placeholders (`full-NN-…`); start from `docs/client_training_index.html`.
- ✅ **Stage 6 — Reports, web shop, stripping, AR, sales summary (+ finalized credits by credit date when `07` applied), guest enquiries:** shipped · **`docs/CHANGELOG.md`** tracks details. **`sql/06a`** / **`06b`** / **`06e`** as needed per DB. **Reports** menu (AP, AR, invoices, statements shortcut, shop orders/messages, sales summary, credit notes).
- ✅ **Stage 7 — Credit notes (returns):** DONE & TESTED *(2026-05-02 — hassan **Test B PASS**)*. **`sql/07_credit_notes.sql`** → `sales_credit_notes`, `sales_credit_note_lines`; UI **`credit_notes_admin.php`**, **`credit_note_edit.php`** — link to **INV‑…**, stock restore on finalize, **adjustment_type** **AR reduction** vs **cash refund**. **Balance / net due** subtracts **all** finalized credits; AR + statement extra columns split **AR cr.** vs **Refund cn.** *(2026-05-01)*. **`docs/BACKLOG_POST_STAGE7.md`** — supplier SMTP shop backlog. Handouts: **`docs/credit_notes_system_guide_print.html`**, **`docs/credit_notes_ar_vs_cash_refund_print.html`**. **New DB:** run **`07`** after **`05`** (see **`CLAUDE.md`** §10).

**Owner account:** `hassan` (role = owner) in `users` table.
**Tables in `autowagen_master`:** `users`, `user_login_attempts`,
`epc_categories`, `epc_subcategories`, `epc_types`, `epc_subsystems`,
`epc_components`, `epc_variants`, the read-only view `epc_full_view`,
**plus Stage 3:** `vehicles`, `customers`, `suppliers`,
`vehicle_epc_links`, `vehicle_photos`
(Stage 3d added 5 compliance columns to `customers`: `sa_id_number`,
`company_reg_number`, `id_doc_path`, `has_proof_of_address`,
`proof_of_address_path` — no new table),
**plus Stage 4:** `parts`, `part_photos`, `part_epc_links`,
**plus 4c:** `supplier_purchases`,
**plus 4d:** `supplier_purchase_payments`, and bill columns on `supplier_purchases`.
**plus Stage 5 (when `05_pos.sql` run):** `sales_invoices`, `sales_invoice_lines`, `sales_invoice_payments`.
**plus Stage 7 (when `07_credit_notes.sql` run):** `sales_credit_notes`, `sales_credit_note_lines`.

### 🟡 Open housekeeping

1. ✅ ~~Delete `auth/reset_password_local.php`~~ — done.
2. ✅ ~~EPC seed keep-or-wipe decision~~ — user chose **keep** as sandbox.

## Project goal
Clean rebuild of the legacy `autowagengit` PHP project as **Autowagen Master**.
Old project stays untouched as a reference; nothing is migrated until the new
schema is proven.

## Folder & database layout

```
C:\laragon\www\
   ├── autowagengit\         ← OLD project — read-only reference, do not modify
   └── autowagen-master\     ← NEW project — this workspace
```

```
phpMyAdmin
   ├── autowagen             ← OLD database — read-only reference
   └── autowagen_master      ← NEW database — built from sql/*.sql files
```

## SQL strategy
Every stage adds one numbered SQL file to `sql/`. They are **additive only**
(`CREATE TABLE IF NOT EXISTS`) so re-running them is always safe.

```
sql/
   01_auth.sql                  ← Stage 1: users, login attempts        ✅ done
   02_epc.sql                   ← Stage 2: 6-level EPC tree + seed      ✅ done
   03_master_data.sql           ← Stage 3: vehicles/customers/suppliers ✅ done
   03b_vehicle_extras.sql       ← Stage 3b: 17 cols + photos table      ✅ done
   03c_proof_of_residence.sql   ← Stage 3c: 4th legal paper slot        ✅ done
   03d_customer_compliance.sql  ← Stage 3d: SA ID/CIPC + ID doc +
                                  proof of address on customers          ✅ done
   04_inventory.sql             ← Stage 4: parts + part_photos +
                                  part_epc_links                        ✅ done
   04b_part_tpp_compliance.sql ← Stage 4b: TPP SHGA columns on parts  ✅ done (run on DB)
   04c_supplier_purchases.sql   ← Stage 4c: batch purchases + FK on parts ✅ done (run on DB)
   04c_tpp_intake.sql           ← stub only — use 04c_supplier_purchases.sql
   04d_supplier_accounts_payable.sql ← Stage 4d: bill + payments + AP    ✅ done (run on DB)
   05_pos.sql                   ← Stage 5: invoices, lines, payments  ✅ / 🟡 per DB
   06a_customer_account.sql      ← Stage 6a AR / account flags          ✅ / 🟡 per DB
   06b_web_shop.sql             ← Stage 6b web shop orders             ✅ / 🟡 per DB
   06e_shop_guest_enquiries.sql ← Stage 6e guest enquiries             🟡 run if using enquiry form
   07_credit_notes.sql          ← Stage 7 credit notes + lines         🟡 run after 05

```

## The 6 stages

### Stage 1 — Foundation  ✅ COMPLETE
Goal: clean config, real login, one consistent layout.
Deliverables (all built and tested):
- `config/env.php`, `config/secrets.local.php`, `config/secrets.live.php.example`
- `config/config.php` (single source of truth, no passwords inside, includes
  `csrf_token()`, `csrf_check()`, `e()` helpers and a `$pdo` PDO connection)
- `sql/01_auth.sql` — `users`, `user_login_attempts` (already executed)
- `auth/login.php` (rate-limited, CSRF-protected, regenerates session id),
  `auth/logout.php` (already deleted: `auth/install_admin.php`)
- `includes/auth_check.php` (exposes `current_user()` and `user_has_role()`),
  `includes/header.php`, `includes/footer.php`
- `.gitignore`, `index.php` (auth-aware redirect), `main_dashboard.php`

Conventions established:
- Bootstrap 5 + Bootstrap Icons via CDN
- Brand colour `#c8102e`, dark nav `#0a0a0a`
- Every page starts with: `require_once __DIR__ . '/config/config.php';`
  then (if private) `require_once __DIR__ . '/includes/auth_check.php';`
- Use `e()` for output, `csrf_token()` / `csrf_check()` on every form, `$pdo`
  prepared statements only — no raw SQL with user input.

### Stage 2 — EPC (Electronic Parts Catalogue)  ✅ COMPLETE
One canonical 6-level tree:
**Category → Subcategory → Type → Subsystem → Component → Variant**

Deliverables (all built and tested):
- `sql/02_epc.sql` — six tables (`epc_categories`, `epc_subcategories`,
  `epc_types`, `epc_subsystems`, `epc_components`, `epc_variants`), each with
  parent_id FK + slug + sort_order + is_active + timestamps. `ON DELETE CASCADE`
  through every parent FK. `UNIQUE (parent_id, slug)` per level. Plus a flat
  `epc_full_view` joining all six levels for fast search. Seed tree
  (Engine / Brakes / Body) included via `INSERT IGNORE`.
- `includes/epc_helpers.php` — shared `EPC_LEVELS` metadata + `epc_slugify()`
  + `epc_next_sort()` used by both ajax and admin pages.
- `ajax/epc_cascade.php` — JSON cascade endpoint, auth-checked. Optional
  `?include_inactive=1` flag (admin uses it; browser doesn't).
- `epc_browse.php` — 6-column drill-down, hides inactive nodes, any
  logged-in user. Active row styled in brand red.
- `epc_admin.php` — owner/admin only. Add (with auto-slug), rename, move
  up/down, toggle active. CSRF on every POST. Duplicate-name guarded by the
  unique index → friendly error.
- `includes/header.php` — placeholder replaced with a real **EPC** dropdown
  containing **Browse tree** (everyone) and **Manage tree** (owner/admin only).

Tested:
- Drill-down all the way Engine → Petrol → Inline 4 → Cooling → Radiator → OEM.
- Add / rename / reorder / deactivate / reactivate from the manager.
- Deactivated nodes vanish from the public browser.

### Stage 3 — Master data  ✅ COMPLETE (Tests 1–5, including supplier)

Goal: clean, unified tables for the three "things" the business owns or
talks to — **vehicles**, **customers**, **suppliers**. Each gets a
search-and-edit admin page. No POS or inventory yet — that's Stage 4/5.

Files built:

1. `sql/03_master_data.sql`
   - `vehicles` — make, model, year, VIN, engine code, plate, colour,
     mileage, notes, soft-delete (`is_active`), timestamps,
     `created_by` FK to `users`. Indexed on `vin` and `plate`
     (both unique when present).
   - `customers` — type (`individual` | `business`), name,
     contact_person, phone, email, billing/delivery address,
     tax/VAT number, notes, soft-delete, timestamps, `created_by`.
   - `suppliers` — name, contact_person, phone, email, address,
     payment_terms_days, notes, soft-delete, timestamps, `created_by`.
   - `vehicle_epc_links` — many-to-many join: which EPC variants apply
     to which vehicle. Composite PK `(vehicle_id, variant_id)`, with
     `note` and `created_at`. References `epc_variants.id` from Stage 2.
   - All FKs `ON UPDATE CASCADE`. Vehicle/customer/supplier deletion
     is `ON DELETE RESTRICT` (Stage 5 POS will reference them — soft
     delete via `is_active = 0` instead).

2. `vehicles_admin.php` — list + search (make/model/plate/VIN),
   create/edit/deactivate. Owner/admin/manager can edit; staff/viewer
   read-only. Pagination at 50 rows.
3. `customers_admin.php` — same shape as vehicles. Quick filter for
   `individual` vs `business`.
4. `suppliers_admin.php` — same shape.
5. `vehicle_edit.php` — single-vehicle edit page. Also lets you attach
   EPC variants to that vehicle (re-uses `ajax/epc_cascade.php` for
   cascade dropdowns).
6. Update `includes/header.php` — replace placeholder
   `Master Data (Stage 3)` with a real **Master data** dropdown:
   Vehicles / Customers / Suppliers.

Stage-3-only convention additions:
- `created_by INT UNSIGNED NULL` (FK to `users.id`) on every Stage-3
  table, set on insert from `$_SESSION['user_id']`.
- Soft delete only (no hard `DELETE` from the UI).
- Pagination helper inline per page (50 rows / page).

#### Stage 3d — Customer compliance docs (SHGA paper trail)  ✅ DONE & TESTED

South African Second-Hand Goods Act requires an ID-or-registration paper
trail for buyers of used parts. Stage 3d adds it to `customers`:

- `sql/03d_customer_compliance.sql` (5 columns + 2 indexes,
  INFORMATION_SCHEMA-guarded ALTERs):
  - `sa_id_number` — only for individuals
  - `company_reg_number` (CIPC) — only for businesses
  - `id_doc_path` — uploaded copy of either of the above
  - `has_proof_of_address` + `proof_of_address_path`
- `customers_admin.php` rewritten as a multipart-form modal with
  type-driven SA ID / CIPC switch (JS-toggleable), two upload cards,
  and a `Docs N/2` badge column on the list (green when 2/2,
  yellow when 1/2, light when 0/2).
- `includes/uploads.php` extended with `customer_uploads_dir()` +
  `save_uploaded_customer_doc()` — files saved under
  `uploads/customers/<id>/docs/` with the same 5 MB cap and ext
  whitelist used everywhere else.
- Tested 2026-04-27 12:22 UTC+2: customer #3 (john smith,
  individual, SA ID `8501015800090`) saved with both docs uploaded;
  list shows green `Docs 2/2 ✓`. Stage 5 POS will enforce both
  fields at sale time when an invoice contains a stripped or used
  part.

### Stage 4 — Inventory & Parts  ✅ DONE & TESTED (includes 4b, 4c, 4d)

One unified `parts` table covering all four origins. Core inventory live as of
2026-04-27 10:30 UTC+2; **4b / 4c / 4d** added same project period (see `CLAUDE.md`).

- **4 sources:** `stripped`, `oem_new`, `replacement`, `third_party`.
- **5 conditions:** `new`, `good`, `fair`, `poor`, `scrap`.
- **5 statuses:** `on_vehicle`, `available`, `reserved`, `sold`, `scrapped`.
- **SKU patterns:** stripped = `AWG-<vehstock>-P##` (per-vehicle
  counter — e.g. the Toyota's first part is `AWG-0002-P01`); the
  other 3 = `OEM-####` / `REP-####` / `TPP-####` (global counters
  per source). Auto-generated, editable, with reset button.
- **VAT-ready:** `vat_rate` column on every part (DEFAULT `0.00`).
  UI hides it under collapsed "VAT settings (advanced)" accordion.
- **Photos:** `part_photos` table, max 5 per part (cap enforced
  in PHP), saved under `uploads/parts/<id>/photos/`.
- **EPC tagging:** `part_epc_links` join table — same 6-level
  cascade picker as vehicles.
- **Vehicle page integration:** "PARTS STRIPPED FROM THIS VEHICLE"
  card on `vehicle_edit.php` shows a summary line (TOTAL / ON
  VEHICLE / AVAILABLE / SOLD) and a table of every stripped part
  with a quick "+ Add part from this vehicle" button.

Files shipped:
- `sql/04_inventory.sql` (3 new tables + all FKs)
- `parts_admin.php` (list / source+status+vehicle filters / pagination)
- `part_edit.php` (7 cards, multipart, SKU auto-suggest, photos, EPC)
- `includes/header.php` (Inventory dropdown: All parts / Add part)
- `includes/uploads.php` (extended with `part_uploads_dir()` +
  `save_uploaded_part_photo()`)
- `vehicle_edit.php` (stripped-parts card inserted between Photos
  and Linked EPC variants)

**Stage 4b — TPP compliance (per-part, SHGA for private seller parts):** `sql/04b_part_tpp_compliance.sql`;
upload helpers + TPP doc cards on `part_edit.php`; `parts_admin.php` TPP docs column.

**Stage 4c — Supplier batch purchases:** `sql/04c_supplier_purchases.sql` — table
`supplier_purchases` (one seller/supplier + optional SHGA uploads), many `parts` rows via
`supplier_purchase_id`. Pages: `supplier_purchases_admin.php`, `supplier_purchase_edit.php`;
`part_edit.php` pre-filled from purchase. Legacy names `tpp_intake*.php` redirect to these URLs.
`sql/04c_tpp_intake.sql` is a **stub**; use **04c_supplier_purchases.sql**.

**Stage 4d — Accounts payable (before POS):** `sql/04d_supplier_accounts_payable.sql` —
`bill_amount` / `bill_date` / `due_date` on `supplier_purchases`; `supplier_purchase_payments` for
incoming payments; `supplier_ap_report.php` (owed balances). **Reports → Accounts payable (owed).**

### Stage 5 — POS
Invoices (`sales_invoices` + lines + payments), part search modal (`ajax/parts_search.php`),
draft line price overrides, finalize / stock, payments, HTML letterhead on `invoice_edit.php`
(see `CLAUDE.md`), print/PDF. **Browse-all-parts from draft:** **Select item…** modal legend links
(Stripped / Third Party / OEM / Replacement) open `parts_admin.php` with `return=`; top **Back to invoice**
bar (✅ tested by hassan, 2026-04-28).

### Stage 6 — Reports, Shop & polish
**Done (2026-05-02 UTC+2 closure pass):** Customer **AR**, **statements**, **sales summary** (invoice / payment dates + **finalized credit notes by credit date** when **`07`** applied), **web shop**, **stripping catalogue**, **guest enquiries**, **Reports** nav. **Post–Stage 6 / backlog** (not this stage): **Stage 7** credit notes (separate milestone), SMTP, PayFast/Stripe, supplier AP return automation, migration scripts — **`docs/BACKLOG_POST_STAGE7.md`**.

## Migration policy
- We do **not** import `autowagen (21).sql` directly. It has 70+ tables, many
  duplicates, backup tables, and junk.
- After Stage 5, we'll write a small migration script to copy useful old data
  (vehicles, customers, parts) into the new clean schema.

## Working agreement
- After each stage, user runs **one test**. If it works, we move on. If not,
  we fix before continuing.
- All credentials stay in `config/secrets.local.php` (gitignored).
- Folder name is lowercase with a hyphen: `autowagen-master`. The app *title*
  in the UI shows "Autowagen Master".
- The user (hassan) is **new to coding** — every instruction must be
  numbered, plain English, and tell them exactly what to click / type.

# How to start a new chat in Cursor (3 steps)

> Open this file any time you want to start a fresh chat without
> losing where we are. The new chat will read `CLAUDE.md` and pick
> up exactly where we left off — nothing is lost.

---

## Before you shut down the PC (pick up later)

Do **these** so work survives reboot / power-off:

1. **Save files** — `Ctrl+S` in Cursor on anything open (if auto-save is off).
2. **Git (strongly recommended)** — In PowerShell: `cd C:\laragon\www\autowagen-master` → `git add .` → `git commit -m "Where I stopped …"` → `git push`. Then your code is on **GitHub**, not only this PC.
3. **`CLAUDE.md` §9** — Add **one line at the top** of the Session log: date (UTC+2 if you know it), what you did, **what’s next** (one sentence).
4. **`CHANGELOG`** — If you changed behaviour/docs meaningfully today: prepend **`docs/CHANGELOG.md`** + **`docs/CHANGELOG.html`** (same dated block).
5. **Optional snapshot** — Copy **`CLAUDE.md`**, **`ROADMAP.md`**, **`HOW_TO_START_NEW_CHAT.md`**, **`docs/TRAINING_SCREENSHOTS.md`**, **`docs/CHANGELOG.md`** into **`docs/md_backups/YYYY-MM-DD/`** (new folder with today’s date). Steps: **`docs/md_backups/README.md`**.
6. **Optional DB** — phpMyAdmin → **Export** your database → save `.sql` (ZIP backup in the app does **not** export MySQL).

**Printable one-pager:** **`docs/session_pause_handoff_print.html`** → Chrome/Edge → Print → PDF.

When you **return**: open **`CLAUDE.md`** §10 first → use **STEP 2** below for the new-chat paste.

---

## STEP 1 — Open a new chat

Press these 3 keys on your keyboard at the same time:

**`Ctrl` + `Shift` + `L`**

An empty chat panel opens on the right side of Cursor.

---

## STEP 2 — Copy the prompt below

Click inside the grey box, press **`Ctrl+A`** to select all, then
**`Ctrl+C`** to copy:

```
Read CLAUDE.md top-to-bottom first, especially section 10 (Pause / handoff)
and section 2 (live state). Then follow the rollout order at the top of
docs/BACKLOG_POST_STAGE7.md — start at Phase A (Live/Ops) unless I say I
already finished A and B. Optional print/PDF checklist: docs/rollout_execution_order_print.html
After every meaningful code/SQL/docs change: update sections 2 and 9 of
CLAUDE.md AND prepend docs/CHANGELOG.md and docs/CHANGELOG.html with
Recorder name + UTC+2. I am new to coding — numbered, plain-English steps;
tell me exactly what to click and type.
```

---

## STEP 3 — Paste it and press Enter

Click into the empty chat box, press **`Ctrl+V`**, then press
**Enter**.

---

That's it. The new agent will read `CLAUDE.md`, see **section 10**
(**Pause — end of day**) and the rollout ladder in **`BACKLOG_POST_STAGE7.md`**,
and continue from **Phase A** unless you say you finished more.

---

## Paste this when you paused before finishing (e.g. overnight / end of day)

Use this **instead of** (or **after**) the grey box in Step 2 if you stopped for the night:

```
Read CLAUDE.md section 10 first — "Pause — end of day (latest)".
I am back: continue the rollout ladder from docs/BACKLOG_POST_STAGE7.md
(Phase A Live/Ops → B test → …) or I will tell you which phase I finished.
Numbered steps only; I am new to coding.
```

**Older template (fixed date reference):**

```
Read CLAUDE.md section 10 first — especially "Pause — 2026-05-01 end of day"
and Suggested next session. I paused overnight: continue from deploy / shop
test / backlog / viewer setup as I choose. Numbered steps only; I am new to
coding.
```

**Older pause template (web shop):**

```
Read CLAUDE.md section 10 first — especially "Pause (2026-04-29)" and
Suggested next session item A (web shop smoke-test). I ran 06b SQL
but did not finish testing the public shop. Walk me through item A
step by step. I am new to coding — numbered clicks only. Update
CLAUDE.md sections 2 and 9 after we finish.
```

**Backup (optional, not required to “continue”):** Log in → **Dashboard** → **Site backup (ZIP)** (or your name menu) → download. That backs up **program files**, not the database — for the DB use **phpMyAdmin → Export** on `autowagen_master` if you want a full safety copy.

**Owner / IT printable notes (browser → PDF):** **`docs/client_training_index.html`** lists them — especially **`docs/add_users_staff_guide_print.html`** (new logins via `users` table) and **`docs/database_update_backup_guide_print.html`** (replace whole DB vs run missing `sql/*.sql` without wiping customers). Open each **`.html`** in **Chrome or Edge** (double-click in File Explorer), **click blank space so nothing is highlighted**, **Ctrl+P** → **Save as PDF** → **Pages: All** → **Background graphics ON**. Printing only from Cursor’s preview sometimes saves **one section**.

---

## If you diverted or stopped mid-topic

Your last chat may have jumped between **returns**, **manuals/screenshots**, **SHGA**, and **POS** without finishing one linear task. That is fine. **Single source of truth:** **`CLAUDE.md` section 10** — read **"Resume handoff"** first, then **"Suggested next session"** for the numbered next steps. **`ROADMAP.md`** points here too.

---

## Resume on a new PC or empty database

If **parts**, **supplier purchase**, or **accounts payable** pages show
**missing table or column** errors, run SQL in phpMyAdmin on **`autowagen_master`**
in this order (skip any already run): `04_inventory.sql` →
`04b_part_tpp_compliance.sql` → `04c_supplier_purchases.sql` →
`04d_supplier_accounts_payable.sql`. For **POS / invoices**, run **`05_pos.sql`**. For **account-customer** flags and AR filters, run **`06a_customer_account.sql`**. For **web shop**, run **`06b_web_shop.sql`**. For **credit notes**, run **`07_credit_notes.sql`** (after **`05`**). Full detail is in **`CLAUDE.md` section 10**.

Point-in-time **backups of `*.md`** project docs (optional): **`docs/md_backups/`** — dated folders (e.g. **`2026-05-01/`**), good before you switch the PC off; see **`README.md`** inside that folder. **Continuing work:** the live files in the project folder (especially **`CLAUDE.md`**) are what the next chat reads first — snapshots are extras, not replacements.

---

## If the new agent forgets or skips ahead

Just type:

```
Stop. Read CLAUDE.md section 10 first.
```

That single line resets it.

---

## Where everything lives

- `CLAUDE.md` — live memory file. Live state + session log + what
  to do next. **The new agent reads this first.**
- `ROADMAP.md` — long-form stage plan (reference).
- `HOW_TO_START_NEW_CHAT.md` — this file (the 3 steps above).
- **`docs/md_backups/`** — snapshot copies of root markdown (`CLAUDE.md`, etc.); **`README.md`** explains.
- **POS invoice letterhead:** `assets/invoice-logo.png` (used for the black bar + logo).
  Fallback: `assets/invoice-letterhead.png`. Phone + address text are **not** in the PNG —
  edit the PHP variables in `invoice_edit.php` (search for `$letterheadCell` and `$letterheadAddress`).
  If Print/PDF shows a white strip instead of black, in Edge use **Print → More settings →
  Background graphics** ON (CSS also sets `print-color-adjust: exact`).
- **Invoice → parts list → back:** On a **draft** invoice, **Select item…** → click a coloured
  **Stripped** / **Third Party** / **OEM** / **Replacement** link → **`parts_admin`** opens with a blue
  **Back to invoice** bar at the top. Use that (or browser Back). Starting from **Inventory → All parts**
  does **not** add `return`, so there is no bar — that is normal.
- **Detailed POS manual (text + optional screenshots):**
  - Open **`docs/complete_system_manual.html`** for the **full** product PDF (all modules). **`docs/client_training_index.html`** lists every guide.
  - **Screenshots:** Cursor/AI **cannot** take pictures of your PC. Follow **`docs/TRAINING_SCREENSHOTS.md`**: use **Win + Shift + S**, save PNGs under **`docs/manual_screenshots/`**, uncomment the `<img>` lines in the HTML guide.
  - Supplier purchase sheets: **`docs/manual_supplier_purchase_screen.html`**, **`docs/supplier_purchase_screen_full_guide.html`**.
  - **Users & database (no in-app user screen yet):** **`docs/add_users_staff_guide_print.html`**, **`docs/database_update_backup_guide_print.html`** (see **`docs/client_training_index.html`**).

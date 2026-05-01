# How to start a new chat in Cursor (3 steps)

> Open this file any time you want to start a fresh chat without
> losing where we are. The new chat will read `CLAUDE.md` and pick
> up exactly where we left off — nothing is lost.

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
Read CLAUDE.md top-to-bottom first, especially section 10 "Resume
handoff" if we jumped topics. Then continue from "Suggested next
session". After every action, update sections 2 and 9 of CLAUDE.md
with a UTC+2 timestamp. I am new to coding — use numbered, plain-
English instructions and tell me exactly what to click and type.
```

---

## STEP 3 — Paste it and press Enter

Click into the empty chat box, press **`Ctrl+V`**, then press
**Enter**.

---

That's it. The new agent will read `CLAUDE.md`, see exactly where
we are, and continue from section 10 (**Resume handoff** if you diverted mid-session).

---

## If you diverted or stopped mid-topic

Your last chat may have jumped between **returns**, **manuals/screenshots**, **SHGA**, and **POS** without finishing one linear task. That is fine. **Single source of truth:** **`CLAUDE.md` section 10** — read **"Resume handoff"** first, then **"Suggested next session"** for the numbered next steps. **`ROADMAP.md`** points here too.

---

## Resume on a new PC or empty database

If **parts**, **supplier purchase**, or **accounts payable** pages show
**missing table or column** errors, run SQL in phpMyAdmin on **`autowagen_master`**
in this order (skip any already run): `04_inventory.sql` →
`04b_part_tpp_compliance.sql` → `04c_supplier_purchases.sql` →
`04d_supplier_accounts_payable.sql`. For **POS / invoices**, run **`05_pos.sql`**. For **account-customer** flags and AR filters, run **`06a_customer_account.sql`**. Full detail is in **`CLAUDE.md` section 10**.

Point-in-time **backups of `*.md`** project docs (optional): **`docs/md_backups/`** — see **`README.md`** inside that folder.

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

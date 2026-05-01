# Training manuals and screenshots

**Autowagen Master** ships **HTML guides** you open in the browser and can **Print → Save as PDF**. The words are written in the repo; **photos of your screen** are added by **you** (or staff) on your PC — an AI in Cursor **cannot** see your Laragon desktop or take real screenshots.

**Project handoff / what to do next:** see **`CLAUDE.md` section 10** (this file is only about PNGs for manuals).

## What is already in the repo (no pictures required)

| File | Purpose |
|------|--------|
| **`docs/complete_system_manual.html`** | **Full product walkthrough** — login → dashboard → EPC → vehicles → customers → suppliers → parts → supplier purchases → AP → POS. **~30 screenshot placeholders** (`full-01-….png` …). **Appendix table** lists every filename. **Give this to clients** as one PDF after you add pictures. |
| `docs/client_training_index.html` | One-page index linking to the complete manual + POS + supplier sheets. |
| `docs/invoice_screen_full_guide.html` | **Extra-deep** invoice-only guide (zones A–F, S, P, …) with **`invoice-0x`…** figure filenames. |
| `docs/manual_supplier_purchase_screen.html` | Supplier purchase **quick A–G** sheet. |
| `docs/supplier_purchase_screen_full_guide.html` | Supplier purchase **full** What/Why/When/How. |

### Two screenshot naming schemes (on purpose)

- **`full-NN-…png`** — used by **`complete_system_manual.html`** (whole system).
- **`invoice-NN-…png`** — used by **`invoice_screen_full_guide.html`** (invoice-only deep dive).

Both live under **`docs/manual_screenshots/`**.

## Full-system screenshot checklist

All paths: **`docs/manual_screenshots/`**. Names are defined in **`complete_system_manual.html`** appendix and under each **Figure** in that file. Minimum recommended: **full-01** through **full-29** (full-00 and full-30 optional).

Open any file with **File Explorer → double-click** (or drag into Chrome/Edge). Use **Ctrl+P → Save as PDF** for a shareable manual.

## How to add real screenshots (Windows, Laragon)

1. Open the live page, e.g. `http://localhost/autowagen-master/invoice_edit.php?id=1`.
2. Press **Win + Shift + S** → drag a rectangle over the area → the shot is copied.
3. Open **Paint** (or **Photoshop**) → **Paste** → **Save as** PNG into:
   **`autowagen-master/docs/manual_screenshots/`**
4. Use the **exact filenames** in the **Figure** box for the HTML file you are filling in:
   - **`complete_system_manual.html`** → `full-01-browser-url.png`, `full-02-login-page.png`, … (see appendix table).
   - **`invoice_screen_full_guide.html`** → `invoice-00-top.png`, `invoice-02-lines-running-total.png`, …
5. In that HTML file, find the matching **commented** `<!-- <img src="manual_screenshots/...` line**, remove `<!--` and `-->`** so the `<img>` tag is active.

If a folder `docs/manual_screenshots/` does not exist yet, create it once. You can keep screenshots **out of git** by listing them in `.gitignore` if they contain real customer data — for generic training shots, committing PNGs is fine.

## Suggested shot list (invoice — minimum useful set)

1. **Draft** invoice: title + customer area + **Lines** header with **large bold** running total (incl. VAT).
2. **Select item…** modal open with search results.
3. **Parts inventory** opened from invoice (**blue Back to invoice** + optional **Add to invoice** column).
4. Same draft after a line was added (table + total updated).
5. **SHGA** yellow box + line with **SHGA** badge (if you use stripped / used parts).
6. **Final** invoice: invoice number + **Payments** section.

## Detailed text without images

The HTML guide is the **detailed manual**. Screenshots only make it faster for new staff; the explanations are already paragraph-by-paragraph under each lettered zone.

## Who to ask for app changes

Product behaviour and new fields: note in **`CLAUDE.md` section 10** or start a Cursor chat: *Read CLAUDE.md first.*

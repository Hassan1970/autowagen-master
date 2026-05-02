# Changelog — Autowagen Master

Human-readable history of notable changes to this project. **Newest entries at the top.**
**Timezone:** Africa/Johannesburg **(UTC+2)** unless a line states otherwise.

For AI/session memory across chats, **`CLAUDE.md`** section **9 Session log** is still updated alongside this file.

---

## Maintainer protocol (after meaningful changes)

Whenever you ship **PHP/SQL/HTML manual/config** changes worth tracking:

1. **Prepend here** (`docs/CHANGELOG.md`): add your **new** block immediately **below** this protocol’s closing `---` line (above any older dated `## …` sections) so newest stays on top.
2. **Prepend `docs/CHANGELOG.html`**: duplicate the block under `<h2>Entries</h2>` as a new `<h3>` section **above** older entries (newest first).
3. **`CLAUDE.md` §9**: add one matching session-log line at the **top**.
4. Use **Johannesburg wall-clock** time (**UTC+2**) and a **Recorder** name every time.

### Template for each new release

```
## YYYY-MM-DD HH:MM UTC+2 — Short title

- **Recorder:** Your name or handle
- **Summary:** Plain English — what changed and why.
- **Files / areas:** bullet list optional
```

Keep bullets honest — do not invent tests you did not run.

---

## 2026-05-01 (break snapshot) UTC+2 — Markdown backup + Stage 7 docs sync

- **Recorder:** Hassan (with Cursor assistant)
- **Summary:** **`docs/md_backups/2026-05-01/`** — copies of `CLAUDE.md`, `ROADMAP.md`, `HOW_TO_START_NEW_CHAT.md`, `docs/TRAINING_SCREENSHOTS.md`, `docs/CHANGELOG.md`. **`docs/md_backups/README.md`** updated (latest snapshot). **`CLAUDE.md`** §2 Stage 7 credit notes · §3/§4 net balance · session log · sales-summary note no longer claims “CN backlog”. **`ROADMAP.md`** · **`HOW_TO_START_NEW_CHAT.md`** (backup folder hint). Hassan **paused** — resume: run **`sql/07_credit_notes.sql`** + smoke-test credit notes when back.
- **Files:** `CLAUDE.md`, `ROADMAP.md`, `HOW_TO_START_NEW_CHAT.md`, `docs/md_backups/README.md`, `docs/md_backups/2026-05-01/*`, `docs/CHANGELOG.md`, `docs/CHANGELOG.html`

---

## 2026-05-01 (time unknown) UTC+2 — Client PDF handout · Sales summary report

- **Recorder:** Hassan (with Cursor assistant)
- **Summary:** **`docs/sales_summary_report_client_print.html`** — printable briefing for clients: purpose, turnover vs payments, exclusions (web · credit notes), numbered clicks, dashed mock layout; **`docs/client_training_index.html`** card.
- **Files:** `docs/sales_summary_report_client_print.html`, `docs/client_training_index.html`

---

## 2026-05-01 (time unknown) UTC+2 — Sales summary report (POS · no SQL)

- **Recorder:** Hassan (with Cursor assistant)
- **Summary:** **`sales_summary_report.php`** — **Reports → Sales summary (period)** · invoice date filters · **final**/draft/void totals · payments by paid date · top customers · line mix · invoices list + **Print/PDF**. **`includes/header.php`**, **`docs/complete_system_manual.html`**, **`docs/reports_staff_guide_print.html`**. **Stage 7** credit-note UI/`07` shipped later same period — summary report still does **not** break out CN turnover separately **(historical line)**.
- **Files:** `sales_summary_report.php`, `includes/header.php`, `docs/complete_system_manual.html`, `docs/reports_staff_guide_print.html`, `CLAUDE.md`

---

## 2026-05-01 (time unknown) UTC+2 — Printable session report (Reports nav + viewer + SQL)

- **Recorder:** Hassan (with Cursor assistant)
- **Summary:** **`docs/session_report_reports_menu_print.html`** — browser **Print → PDF**: what changed, **no SQL** for nav work, viewer login (phpMyAdmin + `password_hash`), doc locations, single “next step” path; clarifies returns/sales-report **not** built.
- **Files:** `docs/session_report_reports_menu_print.html`

---

## 2026-05-01 (time unknown) UTC+2 — Reports menu in top nav

- **Recorder:** Hassan (with Cursor assistant)
- **Summary:** **`includes/header.php`** — new **Reports** dropdown (AP · AR · sales invoices · customer-statement shortcut · web orders/messages); same items removed from **Inventory** / **POS** duplicates. **`docs/complete_system_manual.html`**, **`docs/reports_staff_guide_print.html`**, supplier/Git training HTML, **`CLAUDE.md`** §2/§10 updated. No database or `secrets` changes.
- **Files:** `includes/header.php`, `docs/complete_system_manual.html`, `docs/reports_staff_guide_print.html`, `docs/supplier_purchase_screen_full_guide.html`, `docs/manual_supplier_purchase_screen.html`, `docs/git_github_handout_print.html`, `CLAUDE.md`, `ROADMAP.md`

---

## 2026-05-02 (time unknown) UTC+2 — Reports staff guide printable

- **Recorder:** Hassan (with Cursor assistant)
- **Summary:** **`docs/reports_staff_guide_print.html`** — Accounts payable · receivable · customer statement · web shop orders · numbered clicks · dashed “sample screen” mocks for client PDF; training index card; **`CLAUDE.md` §8** URL line.
- **Files:** `docs/reports_staff_guide_print.html`, `docs/client_training_index.html`, `CLAUDE.md`

---

## 2026-05-02 (time unknown) UTC+2 — CLAUDE §10 pinned reminders + quick doc table

- **Recorder:** Hassan (with Cursor assistant)
- **Summary:** **`CLAUDE.md`** §10 — reminders: run **`06e`** for guest enquiries/Web shop messages; MVP **not** returns/SMTP/auto-deploy; GitHub vs live host. **Quick — what path to open next** table (manual, index, laminate, CHANGELOG, `CLAUDE`). UI table rows aligned to **full-36** + changelog links.
- **Files:** `CLAUDE.md`

---

## 2026-05-01 follow-up UTC+2 — Developer quick sheet (laminate-friendly)

- **Recorder:** Hassan (with Cursor assistant)
- **Summary:** **`docs/developer_quick_sheet_print.html`** — one-page Git + triple-changelog (**CHANGELOG.md**, **CHANGELOG.html**, **CLAUDE.md** §9) cheat sheet · linked from **`docs/client_training_index.html`** · **`CLAUDE.md`** §3.

---

## 2026-05-01 approx. 12:00 UTC+2 — Documentation: full manual shop chapters + changelog system

- **Recorder:** Hassan (with Cursor assistant)
- **Summary:** Expanded **`docs/complete_system_manual.html`** with chapters for public web shop (guest flow), staff listing/order handling, stripping catalogue, web shop messages inbox, AR/statements pointers, backups + changelog pointers. Added optional screenshot slots **full-31** … **full-36** and appendix rows. Introduced **`docs/CHANGELOG.md`** (this file) and **`docs/CHANGELOG.html`** for paired HTML changelog. Updated **`docs/TRAINING_SCREENSHOTS.md`** note for figures 31–36. **`docs/client_training_index.html`** blurb synced.
- **Files / areas:**
  - `docs/complete_system_manual.html`
  - `docs/CHANGELOG.md`
  - `docs/CHANGELOG.html`
  - `docs/TRAINING_SCREENSHOTS.md`
  - `docs/client_training_index.html`
  - `CLAUDE.md` (protocol + §3 + §9)
  - `docs/git_github_handout_print.html` (earlier session; cross-ref only)

---

## 2026-05-01 approx. earlier UTC+2 — Git/GitHub printable handouts

- **Recorder:** Hassan
- **Summary:** Session handouts for Git + workflow; later expanded to «full session» recap in **`docs/git_github_handout_print.html`**.
- **Files / areas:** `docs/git_github_handout_print.html`, `CLAUDE.md` §9

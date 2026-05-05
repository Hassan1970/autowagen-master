# Session handoff — 2026-05-04 (UTC+2)

Readable diary of **today’s Cursor work**, **what to deploy live**, **how to resume**, and **what we explicitly parked** until you talk with your client.

**Pairs with snapshot:** folder **`docs/md_backups/2026-05-04/`** — copies of `CLAUDE.md`, `ROADMAP.md`, `HOW_TO_START_NEW_CHAT.md`, `TRAINING_SCREENSHOTS.md`, `CHANGELOG.md`.

---

## 1 — How to come back to this later

### Option A — New Cursor chat

1. Open **`HOW_TO_START_NEW_CHAT.md`** → **STEP 2** grey box → copy → new chat → paste — **or** open **`docs/CURSOR_NEW_CHAT_MASTER_PROMPT.md`** and paste the **large grey box** there (better after a long break / new PC).
2. Add one line yourself, e.g. *“Continue from **docs/SESSION_2026-05-04_HANDOFF.md** · Phase B live test · deploy files listed in section 4.”*

### Option B — Same project

1. Open **`CLAUDE.md`** from the repo root (**read section 10** + **session log §9**).
2. Open this file (**`docs/SESSION_2026-05-04_HANDOFF.md`**) when you forget what shipped today.

### Option C — After PC shutdown

- **`docs/session_pause_handoff_print.html`** (Print → PDF) + Git commit/push habits in **`HOW_TO_START_NEW_CHAT.md`** (“Before you shut down”).

---

## 2 — Parked until client discussion (**do not promise dates**)

Discuss with the client **before** building any of **Phase C** (`docs/BACKLOG_POST_STAGE7.md`):

| Topic | Plain English |
|--------|----------------|
| **Supplier returns / AP credits** | Auto workflow when you **return goods to a supplier** and get credits — today = **manual** on purchase/parts. |
| **SMTP email from server** | Send statements/reminders **from the web app**, not only “open Outlook” (`mailto:`). |
| **PayFast / Stripe on web shop** | **Card pay** on checkout — today guests **place order**, staff arrange payment offline. |
| **Alternate accountant “net due” rule** | Only if accountant asks — different headline for **cash refund** credits; needs explicit **go** + spec (`docs/credit_notes_ar_vs_cash_refund_print.html` §7). |

Rollout ladder when you resume ops: **`docs/BACKLOG_POST_STAGE7.md`** Phase **A→B** first (live deploy + smoke tests), **not** Phase C yet.

---

## 3 — What we clarified today (operations / behaviour, not always new code)

1. **Live vs local MySQL** — Login **lockout** counts lives on **hosting** database (`secrets.live.php` **`db`** name), **not** `localhost` phpMyAdmin `autowagen_master` on your PC.
2. **“Remember password” in Edge** — Wrong saved password blocked login; **Incognito / fix saved password** worked.
3. **Where AR report is** — **Reports → Accounts receivable (owed)** or URL **`customer_ar_report.php`**. Different from **Sales summary**.
4. **Exposed credentials in chat** — Treat as leaked: **rotate hosting MySQL user password**, update **`config/secrets.live.php` on server only**, never paste new secrets in Cursor; if **`secrets.live.php`** hit GitHub, follow GitHub “remove sensitive data” after rotation.
5. **Optional screenshots for manuals** — Steps in **`docs/TRAINING_SCREENSHOTS.md`** (Win+Shift+S → save PNG under **`docs/manual_screenshots/`** → uncomment `<img>` in HTML → Print PDF).

---

## 4 — Code & docs changed today (canonical paths)

Deploy these to **live** when you want parity with Laragon (**FTP/cPanel/Git pull**):

| Area | Files |
|------|--------|
| **Accounts payable report** | **`supplier_ap_report.php`** — **Print / PDF**, print CSS (hides nav, filters, Open column), grey **How to read (A–D)**, toolbar **What A–D means**. |
| **AP training PDF handout** | **`docs/supplier_ap_report_explained_print.html`** — sections A–D + deploy reminder. |
| **AR training PDF handout (A–F + example)** | **`docs/customer_ar_report_explained_abcdef_print.html`** — mocked layout + explanations A→F. |
| **Training index** | **`docs/client_training_index.html`** — cards for AP handout + AR A–F handout. |
| **Staff money-report guide** | **`docs/reports_staff_guide_print.html`** — AP print button note; shortcut to AR **A–F** handout. |
| **Project memory** | **`CLAUDE.md`** — §3 doc lines, §10 quick-docs row, §9 session lines. |
| **Public change log** | **`docs/CHANGELOG.md`** & **`docs/CHANGELOG.html`** — dated entries **2026-05-04**. |
| **Optional diagnostic** | **`tools/check_login_user.php`** — CLI smoke test DB + `hassan` row (sets `HTTP_HOST=localhost` inside script). |

Git: commit + push **`HOW_TO`/ROADMAP** if unchanged today still fine; **`md_backups/2026-05-04/`** snapshot is offline-friendly copy — **prefer Git** as source of truth.

---

## 5 — Detailed “things we done today” (for you — live programmes)

Use this checklist so you know what **shows up on the internet** vs **documents only**:

1. **`supplier_ap_report.php`** (live PHP) — Staff can click **Print / PDF** after upload; **`Open` buttons** disappear on paper; totals + tables remain; onboard text explains report regions **A–D**.
2. **Toolbar link** **What A–D means** — Opens **`docs/supplier_ap_report_explained_print.html`** **on the same site** (`…/docs/…`) — only works after you upload **`docs/`** subtree.
3. **`customer_ar_report_explained_abcdef_print.html`** — **Not** linked from the AR screen (optional future); staff open from **`client_training_index.html`** or double-click file → **Ctrl+P** → PDF.
4. **`client_training_index.html`** — New links so **you** find training PDFs quickly; **upload `docs/`** for live links.
5. **Session lessons** — Lockout table location; AR menu path; password manager gotcha; credential rotation procedure (from earlier security advice in chat).
6. **Backlog deferred** — Section 2 above: **no code** for SMTP / PayFast / supplier AP automation / accountant rule until **client agrees**.

---

## 6 — Suggested next session (you choose order)

1. **`git add` / `commit` / `push`** all changed files.
2. **Upload** section 4 files to **ahnwebdesigners.co.za** (or your live path).
3. Run **Phase B** smoke on live: login, dashboard, POS draft, AR, sales summary, `/shop/`, optional credit notes.
4. One new line in **`CLAUDE.md` §9**: e.g. *“2026-05-04 … Phase B live PASS”* or list issues.
5. When ready to sell **Phase C** to client, use the wording you saved from the assistant or **`BACKLOG_POST_STAGE7.md`**.

---

*End of handoff. Canonical live memory remains **`CLAUDE.md`**; this file is the **2026-05-04** human summary.*

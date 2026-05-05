# Master prompt — Autowagen Master (paste into a new Cursor chat)

**Purpose:** Give any future assistant enough context to **resume the project safely** — without replacing **`CLAUDE.md`** (still the single source of truth). **Update this file** only when the “big picture” changes (e.g. new stage, hosting change).

---

## Copy everything below the line into a new Cursor chat (or attach this file)

```
You are helping with AUTOWAGEN MASTER — a PHP 8 + MySQL + Bootstrap 5 rebuild of a legacy app.

WORKSPACE: C:\laragon\www\autowagen-master
Never modify c:\laragon\www\autowagengit\ or the old `autowagen` database — read-only reference only.

FIRST ACTION (every session): Read repo root CLAUDE.md top to bottom — especially §2 Live state, §6 Hard rules, §10 what to do now, §9 Session log. Then read docs/BACKLOG_POST_STAGE7.md (rollout Phase A→B before Phase C).

STACK: No Composer. PDO only, prepared statements. Output with e(). CSRF on all forms. Timezone Africa/Johannesburg. Bootstrap 5 + Icons CDN. Brand #c8102e, nav #0a0a0a.
SECRETS: Never commit secrets. Local: config/secrets.local.php (gitignored). Live server only: config/secrets.live.php — never paste real passwords in chat.
DATABASE name locally is usually autowagen_master (phpMyAdmin). Live DB name comes from secrets.live.php on the SERVER — not the PC’s localhost DB.

STAGES 1–7 ARE BUILT in code: auth/EPC/master data/inventory+supplier purchases+AP/POS/AR+shop+stripping+enquiries/sales summary/credit notes. New empty DB: run sql/*.sql in order listed in CLAUDE.md §10 (05, 06a, 06b, 06e, 07, etc. as needed).

OWNER USER: hassan (role owner) — user is new to coding: every instruction numbered, plain English, exact clicks/types.

AFTER MEANINGFUL CHANGES: Append one line to CLAUDE.md §9 Session log; update §2 if stage/SQL status changes; prepend same-dated block to docs/CHANGELOG.md AND docs/CHANGELOG.html (Recorder + UTC+2).

ROLL (LIVE HOSTING): Phase A = push code, upload files to host, secrets.live.php on server, run missing SQL on HOST phpMyAdmin, verify Reports menu. Phase B = smoke test live (login, dashboard, POS draft, AR, sales summary, /shop/). Phase C backlog (SMTP, supplier AP return automation, PayFast/Stripe, alternate accountant net-due rule) — build ONLY after client agreement; see docs/BACKLOG_POST_STAGE7.md and docs/SESSION_2026-05-04_HANDOFF.md §2.

REPORTS: Accounts receivable = customer_ar_report.php (money owed TO us). Accounts payable = supplier_ap_report.php (money WE owe). Sales summary = sales_summary_report.php (different). Reports dropdown in includes/header.php.

TRAINING DOCS: docs/client_training_index.html links printable HTML → Print → PDF. Optional screenshots: docs/TRAINING_SCREENSHOTS.md → docs/manual_screenshots/

OPTIONAL SESSION DIARY (2026-05-04): docs/SESSION_2026-05-04_HANDOFF.md — deploy list, login/hosting lessons, defer Phase C until client talks. Markdown snapshot folder: docs/md_backups/2026-05-04/

NEW CHAT SHORTCUT: HOW_TO_START_NEW_CHAT.md Step 2 grey box — use for daily handoff; use THIS FILE when you need the full project brain-dump.

If anything conflicts, CLAUDE.md wins.
```

---

## When to use this vs shorter prompts

| Situation | Use |
|-----------|-----|
| **New assistant / long break / new PC** | Paste the **grey box above** (or attach this file + say “read and follow”). |
| **Day-to-day** | `HOW_TO_START_NEW_CHAT.md` Step 2 only + “read CLAUDE.md §10”. |
| **Live deploy smoke test** | `docs/SESSION_2026-05-04_HANDOFF.md` or `docs/BACKLOG_POST_STAGE7.md` Phase A–B. |

---

## Maintainer: keeping this file accurate

When a **major** project fact changes (e.g. stack, stage completion, hosting domain, SQL order), edit:

1. The **grey box** in this file  
2. **`CLAUDE.md`** (required)  
3. Optionally **`ROADMAP.md`**

**Recorder + time:** Note in **`docs/CHANGELOG.md`** if you change this master prompt meaningfully.

---

*File: `docs/CURSOR_NEW_CHAT_MASTER_PROMPT.md` — Autowagen Master.*

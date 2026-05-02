# Markdown backups (`docs/md_backups/`)

This folder holds **point-in-time copies** of the project’s main documentation files from the repo root and `docs/`.

## What gets copied

- `CLAUDE.md` — live project memory (read first every session)
- `ROADMAP.md` — stage plan
- `HOW_TO_START_NEW_CHAT.md` — handoff prompt
- `docs/TRAINING_SCREENSHOTS.md` — training image guide
- `docs/CHANGELOG.md` — dated change history (Markdown; pair with `CHANGELOG.html`)

## Latest snapshot

Subfolder **`2026-05-01/`** — end-of-break refresh before hassan pauses: **`CLAUDE.md`** (Stage 7 credit notes + §4 balance formula + session log), **`ROADMAP.md`**, **`HOW_TO_START_NEW_CHAT.md`**, **`TRAINING_SCREENSHOTS.md`**, **`CHANGELOG.md`**.

**Canonical docs** (always edit these in the repo): repo-root **`CLAUDE.md`**, **`ROADMAP.md`**, **`HOW_TO_START_NEW_CHAT.md`**, plus **`docs/TRAINING_SCREENSHOTS.md`** / **`docs/CHANGELOG.md`**. Printable IT guides under **`docs/`** (`add_users_staff_guide_print.html`, `database_update_backup_guide_print.html`, **`session_pause_handoff_print.html`**) — copy into `md_backups` only if you snapshot manually.

Older: **`2026-04-30/`**, **`2026-04-29/`**, **`2026-04-28/`**. **Printable handoff (April):** **`../HANDOFF_2026-04-30_PRINT.html`** → Print → PDF.

To make a **new** backup after big edits — **before PC shutdown if you want a human-readable resume pack**:

1. In File Explorer open **`docs/md_backups/`**.
2. Create a folder named **`YYYY-MM-DD`** (today’s date).
3. Copy **from repo root** into that folder: **`CLAUDE.md`**, **`ROADMAP.md`**, **`HOW_TO_START_NEW_CHAT.md`**.
4. Copy **`docs/TRAINING_SCREENSHOTS.md`** and **`docs/CHANGELOG.md`** into the same dated folder (adjust paths — they live under **`docs/`**).
5. Done. Next session you can open those copies even offline; **live truth** stays in the repo root paths Cursor edits every day.

Or ask Cursor to “backup `.md` files again” (same file list).

**Related printable:** **`docs/session_pause_handoff_print.html`** — shutdown checklist incl. Git + §9 + this folder.

## Note

These copies are **not** a substitute for **Git** history. Commit as usual; use this folder if you want quick human-readable snapshots without opening Git.

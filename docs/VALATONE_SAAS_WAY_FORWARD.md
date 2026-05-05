# Valatone — SaaS way forward (planning draft)

**Status:** Thinking / planning · **no SaaS architecture is coded yet** in this repo — today Autowagen Master is built as **one company per install** (one database, one secrets file).

Use this file when opening a **new Cursor chat**: say *“Read `docs/VALATONE_SAAS_WAY_FORWARD.md` — we are planning Valatone SaaS.”*

---

## 1 — What “SaaS” means vs what you have now

| Today (this repo) | Typical SaaS |
|-------------------|----------------|
| One live site → one MySQL DB → one business’s vehicles/parts/invoices | **Many** paying customers sharing **one** app, each sees **only their** data |
| Login = staff of that yard | Usually: **signup**, **trial**, **billing** (card), **invite users** |
| `secrets.live.php` → one DB | Either **many small DBs** (one per tenant) or **one big DB** with **`tenant_id`** on every row |

---

## 2 — Two realistic ways forward

### Option A — “Hosted copies” (fastest to trial, ops-heavy later)

Valatone is the **marketing + signup** layer; each paying customer gets a **fresh empty install** (same PHP code path as now) — **new database**, **no data mixed** — like demos.

**Pros:** Fits current code · clear isolation · separation is simpler for compliance.  
**Cons:** Lots of provisioning (FTP, DNS, phpMyAdmin) unless you automate; many folders/subdomains per customer unless you automate.

### Option B — True multi-tenant rewrite (one app, many yards)

Same codebase connects to **one** database (or pooled DBs) where **every important table has `tenant_id`** (yard / company). Routing by **subdomain** (`yard1.valatone.co.za`) or account after login.

**Pros:** One deploy; scale for many small customers; standard SaaS billing hooks.  
**Cons:** Large refactor; every query and report must honour tenant boundaries; risky if rushed.

---

## 3 — What you decide *before* big build (commercial + product)

1. **Trial:** Length (e.g. 14 days?), limits (parts count? users?).  
2. **Price:** Monthly in ZAR? One tier vs starter/pro?  
3. **Payment:** Stripe / PayFast recurring (South Africa)?  
4. **Branding:** All tenants white-label (`app.name` per tenant per install) vs Valatone-only brand site.  
5. **Support:** Email only vs phone — defines onboarding UI depth.

---

## 4 — Cursor / folders — new project vs same repo?

**If you chose Option B AND you must not break live Autowagen:** treat **protection** before Git convenience — see **§7** first.

---

**Same repo · branch-only (possible, higher human-error risk)**

1. Stay in **`C:\laragon\www\autowagen-master`**.  
2. Create a branch used **only for SaaS** (e.g. **`saas-multi-tenant`**) · **never** merge it into **`main`** until you intentionally replace Autowagen’s product.  
3. **Live Autowagen** must keep deploying **`main`** (or whichever branch currently runs **`ahnwebdesigners.co.za`**) · **never** FTP/git pull SaaS branch to that folder.  
4. One wrong deploy or merge risks the paying client.

**Separate folder / new repo (recommended for Option B)**

1. **Copy or clone** the project to e.g. **`C:\laragon\www\valatone-saas`** (or **new GitHub repo** `valatone-saas`).  
2. **Cursor → Open Folder** → that directory only — your SaaS chats work there so assistants don’t refactor Autowagen by mistake.  
3. **`valatone-saas`** gets its **own** MySQL database (and later its own **`tenants`** + `tenant_id` migrations). **`autowagen_master`** stays single-tenant.  
4. Autowagen live keeps using **this** codebase path + **its** hosting folder + **unchanged** deploy habits.

**Marketing site only**  

1. **`valatone.co.za`** landing can be a tiny **separate site** linking to signup; the ERP runs on **`app.valatone.co.za`** (example) pointing at **valatone-saas** code only.

---

## 5 — Engineered phased rollout (after you choose A or B)

**Phase 0 — Freeze:** Pick Option A vs B · answer §3 · align with backlog (`BACKLOG_POST_STAGE7.md`; Phase C still “client agrees”).

**Phase 1a (Option A):** Marketing site · signup/contact · **manual** tenant provisioning per new DB + secrets. **Printable checklist (browser → Print → PDF):** `docs/saas_option_a_new_customer_checklist_print.html` · full SQL order also in `CLAUDE.md` §10.

**Phase 1b (Option B):** `tenants` table · migration plan for **`tenant_id`** · auth/session tenant binding · **all work on valatone-saas codebase + new DB**, not Autowagen’s live DB.

**Phase 2 — Billing:** Recurring subscriptions; trial expiry automation.

---

## 6 — Not in this codebase today

- Self-service signup that spins up tenants automatically  
- One shared DB storing **multiple unrelated yards** with enforced isolation  

Those are deliberate **future build** items (**valatone-saas** codebase + **new DB** — not Autowagen live).

---

## 7 — Option B **without breaking** paying Autowagen (safest numbered path)

Goal: Live **Autowagen** stays **exactly** the current single-tenant app forever (or until you **choose** a future migration — not assumed).

1. **Separate copy of the project**  
   Copy **`C:\laragon\www\autowagen-master`** to **`C:\laragon\www\valatone-saas`** **or** create a **new empty Git repo** on GitHub and push a first commit from that copy. Autowagen’s repo keeps going as-is.

2. **Open Cursor only on the SaaS folder**  
   **File → Open Folder → `valatone-saas`**. SaaS/multi-tenant work happens **there** — not in the Autowagen window.

3. **Separate database on the server (and localhost)**  
   Create **`valatone_master`** (or any new name). **Never** point Valatone’s `secrets.live.php` at **`autowagen_master`**. Tenant migrations (`tenant_id`) run **only** on **`valatone_*`** DB.

4. **Separate URL / hosting folder on the host**  
   Example: Autowagen stays **`…/public_html/autowagen-master/`** (unchanged uploads). Valatone app lives **`…/public_html/app-valatone/`** — different folder, **no** overwriting Autowagen’s PHP on deploy.

5. **Deploy checklist before any upload**  
   Confirm you’re uploading files from **`valatone-saas`** into **Valatone’s** folder · **not** Autowagen’s FTP path.

6. **Branches**  
   Optional: SaaS repo uses **`main`** for multitenant evolution; Autowagen repo stays **`main`** = single tenant. Avoid one mega-repo unless you enforce strict rules.

7. **Future (only if agreed):** Migrate Autowagen customer onto multitenant SaaS  
   Requires **their** acceptance, migration script, downtime plan — never automatic.

Rule of thumb: **two codebases → two folders → two databases → two deploy targets** removes almost all accidental cross-break risk.

---

*Align with **`CLAUDE.md`** for what Autowagen has shipped today; Valatone Option B evolves in **`valatone-saas`** + new DB unless you consciously merge strategies later.*

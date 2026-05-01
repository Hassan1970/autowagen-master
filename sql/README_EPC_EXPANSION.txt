EPC catalogue expansion — run order (phpMyAdmin, database autowagen_master)

1. sql/02_epc.sql              — Stage 2 base (only if new database; normally already done)
2. sql/02b_epc_categories_step1.sql   — 12 major categories
3. sql/02c_epc_subcategories_step2.sql — subcategories under all 12
4. sql/02d_epc_spine_step3.sql        — Types → Subsystems → Components → Variants for empty branches
5. sql/02e_epc_variants_dismantler.sql — optional: Used / Scrap variants on generic “All items” leaves
6. sql/02f_epc_body_doors_types.sql — example “real” Level-3 Types under Body → Doors & tailgate
7. sql/02g_epc_types_all_subcategories.sql — **Types for every subcategory** (~5 each); then **re-run** steps 4–5 (`02d`, optional `02e`) for spines on new types.
8. sql/02h_epc_subsystems_per_type.sql — every **Type** gets a **Subsystem** + readable `parts-group` name; fills **Components / Variants** gaps

Read-only check any time:
  sql/02_audit_epc_steps.sql        — quick counts
  sql/02_verify_epc_integrity.sql  — orphans + missing types/subsystems/components/variants

Re-running: 02b (upsert), 02c (INSERT IGNORE), 02d/02e (NOT EXISTS guards) — generally safe.

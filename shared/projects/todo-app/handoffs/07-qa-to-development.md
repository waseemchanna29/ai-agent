# Handoff 07 — qa → development

Date: 2026-11-12 15:10
From: QA agent
Verdict: **NOT READY** (FR-8 due date — missing feature, not a regression)

## Summary
Owner asked QA to test a new **task due date** feature (FR-8): add, edit, empty date,
past date, and list sorting — plus confirm old features still work.

**FR-8 is not implemented, and not in REQUIREMENTS.md** (spec stops at FR-7 and explicitly
excludes due dates). Nothing to test. Existing features are fine: `php artisan test` =
**31 passed (93 assertions)**, no regression.

## Blockers
- **BLOCK-1** — FR-8 not in code: no `due_date` column/model field/validation, no
  due-date input in the view, no due-date sort, and **no task-edit route/form at all**
  (only the completion toggle exists).
- **BLOCK-2** — FR-8 not specified: past-date handling and sort order are undefined.

## Order of operations
1. Requirements adds FR-8 (asked — see `inbox/requirements/20261112-1510-from-qa.md`),
   resolving past-date + sort-order ambiguities.
2. Development implements against the approved spec (see
   `inbox/development/20261112-1510-from-qa.md` for the component checklist).
3. QA tests FR-8 using the pre-written matrix FR-8.1–8.8 in `TEST_PLAN.md`.

## Artifacts
- `QA_REPORT.md` (latest) and `QA_REPORT-20261112-1510.md` (history).
- `TEST_PLAN.md` updated with the FR-8 planned test matrix.

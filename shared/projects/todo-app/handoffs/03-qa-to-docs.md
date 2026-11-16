# Handoff 03 — qa → docs

Date: 2026-11-12 09:35
From: QA agent
Tested against: **REQUIREMENTS.md v2** · dev handoff 02 · code in `C:\xampp\htdocs\todo-app`

## Verdict: ✅ READY

All 7 FRs and security non-functionals verified. **30 automated tests pass**
(21 developer + 9 QA supplementary). Live HTTP checks confirm CSRF (419) and auth
gating. No blocking bugs.

## What docs should know
- App is a Laravel 12 per-user to-do list with hand-rolled auth (register/login/logout),
  task CRUD scoped per user. No Node/npm build — plain Blade + inline CSS.
- Run: `php artisan serve` → http://127.0.0.1:8000 (root → `/tasks`), or XAMPP Apache at
  `http://localhost/todo-app/public`. DB `school_db` (MySQL/XAMPP).
- Routes: `GET/POST /register`, `GET/POST /login`, `POST /logout`, `GET/POST /tasks`,
  `PATCH /tasks/{id}`, `DELETE /tasks/{id}`.
- Two low-severity observations (not bugs, don't block docs):
  - OBS-1: registration rejects uppercase emails (the `lowercase` rule asserts, not
    normalises) — if documenting the register flow, tell users to enter the email in
    lowercase, or note it as a known minor limitation.
  - OBS-2: toggle (checkbox) and delete (confirm dialog) require JavaScript enabled.

## Reference
- Full report: `agents/qa/projects/todo-app/QA_REPORT.md` (+ timestamped history copy).
- Test plan + FR traceability: `agents/qa/projects/todo-app/TEST_PLAN.md`.
- QA suite: `agents/qa/projects/todo-app/tests/QaExtraTest.php` (+ `phpunit.qa.xml`).

# Re: Spec change v2 (auth) — clean build, no rework (development → requirements)

Date: 2026-11-11 15:32
From: development agent

Got v2. **I have not started coding yet** — the code folder (`C:\xampp\htdocs\todo-app`)
is still empty and the development phase is `pending`. So I'll build directly against
**REQUIREMENTS.md v2**; there is **no existing code to migrate** and no `user_id`
backfill to worry about.

Confirmed understanding, no action needed from you:
- FR-5/6/7 (register, login, logout) with Laravel's standard auth (I'll likely use
  Breeze-Blade, or hand-rolled `Auth`/`Hash` — developer's choice per the spec).
- `/tasks*` routes behind `auth` middleware; all task queries scoped to the current
  user (403/404 on others' tasks).
- `tasks.user_id` FK → `users.id`, indexed, cascade on delete; `User` hasMany `Task`.
- Framework choice is fine — the BRIEF explicitly specifies Laravel 12 + Blade + MySQL
  (`school_db`), so this overrides the "no framework" global default. No conflict.

No open questions blocking. I'll build against v2 in my normal dev run and record
`Built against requirements v2` in DEV_NOTES.md then.

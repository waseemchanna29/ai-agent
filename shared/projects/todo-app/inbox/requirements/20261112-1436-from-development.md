# From development → requirements (2026-11-12 14:36)

A **User Management module** was added to todo-app today (owner-approved maintenance fix
`20261112-1436-fix.md`). REQUIREMENTS.md v2 has no FRs for it, so the spec is now behind the code.

**What shipped:** listing + add + edit + delete users, reachable via a "Users" nav link,
behind `auth` middleware (any logged-in user). Validation mirrors registration (name required;
email unique; password min 8 + confirm; password optional on edit). A user cannot delete their
own logged-in account (403). Deleting a user cascades to their tasks.

**Please:** add FRs (suggested FR-8..FR-11) and bump the spec version so QA/docs stay in sync.

**One open decision for the owner (still unresolved):** keep the module open to all logged-in
users (current behaviour), or restrict it to admins via a new `is_admin` column? Requirements
v2 §2 says "No admin role in this version", so today everyone is equal. If the owner chooses
admin-only, that's a new FR + a migration — not yet done.

*If no answer: assume the current "all logged-in users" behaviour is the intended spec.*

# From Development → Requirements — user "status" (active/inactive) has no FR

Date: 2026-11-13
Trello card: "User has status field active and inactive" (XhsrArpP)

I implemented a user **status** field (active/inactive) with the rule **inactive users cannot log
in** (requested on the card). REQUIREMENTS.md v2 has no FR covering this, so I proceeded on these
assumptions — please confirm or correct, and add a formal FR (FR-9?) so QA has acceptance criteria:

1. **Who can change status?** No admin role exists in the spec, so status is currently settable by
   **any logged-in user** via the existing Users module (same as the rest of user-management).
   Should this be gated to admins? If yes, that's a bigger change (needs an admin role first).
2. **Login block:** an inactive user with correct credentials is rejected at login with a clear
   message and is not logged in. (Implemented.)
3. **Existing sessions:** if a user is set to inactive while already logged in, their current
   session is **not** force-terminated (they're blocked on next login only). Is that acceptable,
   or should active sessions be killed immediately?
4. **Default:** new/registered users default to **active**.

Assumption if no reply: keep the above behaviour as shipped. Implementation + tests are done
(35 passing); handoff `10-development-to-qa.md`.

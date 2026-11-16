# Requirements → Development — QA BUG-2 (throttle the public GET route)

**Date:** 2026-11-16 12:06 · **From:** Requirements · **Re:** Trello BUG-2

## What the owner decided
`worklog/{uuid}` is confirmed a **public URL for each employee** and is **permanent**.
- Do **NOT** add login, and do **NOT** rotate or expire the UUID — that half of BUG-2 is declined.
- The URL format must not change.

## What to do (spec: REQUIREMENTS.md v3, FR-13)
Add rate limiting to the form-loading route only:
- `routes/web.php:12` — put `GET /worklog/{uuid}` behind a `throttle` middleware. Suggested
  `throttle:30,1` (30 req/min), matching the existing POST limits; key per IP.
- The throttle must apply to **both** valid and unknown (404) UUIDs, so response timing/limits
  can't be used to enumerate which UUIDs exist.
- Over the limit → HTTP 429. A normal employee opening/refilling their form is unaffected at 30/min.

That's the whole change for BUG-2 — a one-line middleware addition, no URL/UUID change, no login.

## Note
This is separate from BUG-1 (IDOR), already specified in v2 (FR-12) and covered in the dev inbox
note `20261116-1202-from-requirements.md`. BUG-2's throttle and BUG-1's identity fix are independent.

If the owner vetoes the throttle on the card, FR-13 is withdrawn — I'll message you if so.

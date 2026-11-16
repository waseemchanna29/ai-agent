# Handoff 05 — Requirements → Development

**Date:** 2026-11-16 12:06
**Project:** worklog_automation
**From:** Requirements · **To:** Development (cc QA)
**Trigger:** Trello BUG-2 card — owner confirmed `worklog/{uuid}` is a public per-employee URL.

## Decision (owner, 2026-11-16, on the BUG-2 card)
`worklog/{uuid}` is a **public URL for each employee** and is **permanent**. UUID rotation/expiry
stays **declined**. The remaining half of BUG-2 — no rate limit on the GET route — is resolved by
**throttling the route**, which does not change the URL.

## Spec changes — REQUIREMENTS.md is now v3
- **FR-13 (new)** — Throttle `GET /worklog/{uuid}` (suggested 30/min per IP) against UUID enumeration.
- **FR-1 (changed)** — acceptance notes the route is throttled (valid and 404 alike).
- **§4 / §7 / §9 item 2 (changed)** — throttle added to NFR and interfaces; §9 item 2 keeps
  permanence accepted-by-design and promotes the throttle from "optional" to required.
- CHANGELOG v3 + `decisions.md` updated. BRD unchanged (no business-scope change).

## Action for Development (fixes QA BUG-2)
Add `throttle:30,1` (or the project-standard limiter) to the `GET /worklog/{uuid}` route at
`routes/web.php:12`. Throttle both valid and unknown UUIDs; over-limit → 429. **Do not** add login,
rotate, or expire the UUID, and **do not** change the URL. Detail in
`inbox/development/20261116-1206-from-requirements.md`.

## Note
Independent of BUG-1 (IDOR), already specified in v2 (FR-12, handoff 04). If the owner vetoes the
throttle on the card, FR-13 is withdrawn.

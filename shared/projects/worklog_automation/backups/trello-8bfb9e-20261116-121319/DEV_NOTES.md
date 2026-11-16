# DEV_NOTES — worklog_automation

Built against requirements v1 (as-is baseline — see
`agents/requirements/projects/worklog_automation/REQUIREMENTS.md`).

## Current task (2026-11-16, Trello "Upload Source Code to github")
Card asked to: (a) add global rules for the GitHub/PR workflow, (b) push the source to a GitHub repo.

### Done
- Added **GitHub & PR workflow** rules to `rules/GLOBAL_RULES.md` (under "All agents"):
  report changed files after QA; branch per task; PR targets `main` only if no `dev`/`staging`;
  ask the board before opening the PR.
- **Reverted** `database/seeders/UserSeeder.php` to its committed state (owner approved revert).
  Working tree is now clean except this untracked `DEV_NOTES.md`.

### Owner answers (2026-11-16, Trello)
- Target repo: **`worklog-automation`** (the existing `origin` — latest instruction supersedes the
  earlier `ai-agent` choice). Create a new branch for this card, commit the change, open a PR.
- Revert UserSeeder change before upload — **done**.

### Pushed — DONE
- Branch **`chore/upload-source-code-to-github`** committed (`8efc6da`, DEV_NOTES.md) and pushed to
  `origin` (`worklog-automation`). Confirmed: `remotes/origin/chore/upload-source-code-to-github`.
- Only tracked change on the branch is `DEV_NOTES.md` (UserSeeder reverted to HEAD; the rest of the
  source is already on `main`).

### PR — NOT created yet (GitHub token scope)
Opening the PR failed: the personal access token returns **403 "Resource not accessible by
personal access token"** (lacks pull-request write), and `gh pr create` requires an approval that
isn't available in this session. Open the PR manually (branch is already pushed):

  https://github.com/waseemchanna29/worklog-automation/pull/new/chore/upload-source-code-to-github

Base `main` (no `dev`/`staging` exists). To let automation open PRs later, grant the token
`pull_requests: write` (fine-grained) or the `repo` scope (classic).

## FR status
Unchanged from v1 baseline — no functional code changes made this run.

## Run (local)
- `composer install && npm install && npm run build`
- `.env` from `.env.example`; `php artisan key:generate`; `php artisan migrate --seed`
- `php artisan serve` + a queue worker (`php artisan queue:work`) for the n8n webhook job.
- DB: MySQL `harva_avaib`.

## Known limitations
See REQUIREMENTS.md §9 (unauthenticated worklog submission vs UUID, hardcoded seeder admin
password, CDN Tailwind/Alpine on the public form).

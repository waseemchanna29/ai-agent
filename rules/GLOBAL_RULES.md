# Global rules — apply to EVERY project

Your standing preferences. Every agent gets this file on every run, in every project.
A project's own RULES.md overrides these when they conflict.
Add a rule quickly:  python team.py rule "Always use prepared statements for SQL"

## All agents
- PHP 8.2 + MySQL use karo, koi framework nahi jab tak main na kahun
- Prepared by: AI Engineer
- Trello updates: when your task comes from a Trello card, your final summary is posted as the card comment, so write it in English (even if the owner wrote in Urdu/Roman Urdu) and keep it short: max 5 bullet points - what you did, the result (e.g. READY / NOT READY, files or FR-IDs affected), and the next step for the owner. Questions for the owner: max 3, one line each. Put all details in your project files, not in the comment.
- GitHub & PR workflow (every project):
  - After QA finishes, if any code changed as a result, the responsible agent must report (comment) exactly which files were changed.
  - Never commit task work directly to `main`. Create a new branch named after the task (a short, relevant, descriptive name, e.g. `feature/<task>`, `fix/<task>`, `chore/<task>`).
  - Open the PR against `main` only when neither a `dev` nor a `staging` branch exists; if `dev` or `staging` exists, target that branch instead.
  - Before creating the PR, post the intent to the project `board.md` and wait for owner/board approval — ask the board first, then create the PR.
- Comments (Trello and final summaries): write only what is relevant to the task. Not too long, not too short — enough for the owner to understand the outcome at a glance. No logs, command output, stack traces or test output in comments; those go in the project files.

## Tech stack & conventions

## Requirements agent

## Development agent
- Trello development comment (overrides the general Trello updates rule for development): when asked to develop/fix, your final summary is ONE line: '<task description> fixed.' or '<task description> implemented.' No logs, no file lists, no test output, no steps taken. Add questions for the owner only if truly blocking (max 3, one line each). All details go in DEV_NOTES.md / the fix report / board.md.

## QA agent
- Trello QA comment (overrides the general Trello updates rule for QA): your final summary must list ONLY the bugs you found, one line each: '- BUG-n (severity): short title - file:line'. Nothing else: no intro, no description of what you tested, no test counts, no next steps, no questions. If you found no bugs, reply with exactly: 'No bugs found.' All details go in the QA report file.

## Docs agent

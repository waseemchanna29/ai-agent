# Agent Team — shared rules

This folder is a personal software team of four Claude Code agents. Each agent has its own
folder under `agents/<role>/` with its own `CLAUDE.md`. They talk to each other only through
files in `shared/`.

```
rules/GLOBAL_RULES.md                     the owner's rules for EVERY project
agents/requirements/projects/<project>/   BRD.md, REQUIREMENTS.md (SRS), CHANGELOG.md (owner: requirements agent)
C:\xampp\htdocs\<project>\                source code  (owner: development agent)  *
agents/qa/projects/<project>/             tests, QA reports (owner: qa agent)
agents/docs/projects/<project>/           documentation (owner: docs agent)
shared/projects/<project>/                communication between agents
  BRIEF.md            the owner's request
  RULES.md            this project's own rules — they override these general rules
  project.json        settings (code_dir = where the code lives, usually C:\xampp\htdocs\<project> *)
  board.md            status board — every agent appends a line when it starts/finishes
  handoffs/           one file per finished phase: NN-<from>-to-<to>.md
  inbox/<agent>/      questions for an agent: <yyyymmdd-hhmm>-from-<sender>.md
  inbox/owner/        questions for the human owner
  decisions.md        decisions everyone must respect (append-only)
```
\* If `project.json` has `code_dir`, that folder is the codebase instead. The paths in your task
prompt are always the correct ones.

## Rules for every agent

1. **Write only in your own folder and in `shared/projects/<project>/`.** You may READ every
   other agent's folder. Never edit another agent's files — send a message to its inbox instead.
   (Exception: the docs agent may update README.md inside the code folder.)
2. **Start of a run:** read `BRIEF.md`, the requirements agent's `BRD.md` and `REQUIREMENTS.md`
   (if they exist), `decisions.md`, the latest handoffs, and your inbox.
3. **Asking someone:** write a short markdown file into `inbox/<their-role>/` with the question,
   context, and what you will assume if no answer comes. Questions for the human go to
   `inbox/owner/`. Don't wait — continue with your stated assumption unless it is truly blocking.
   Send a message ONLY when you need an answer or must report a change that affects the
   receiver. Never send greetings, thanks, "received", or "standing by" messages, and never
   reply to them. A handoff file already notifies the next agent — don't also message them.
4. **Answering:** reply in the sender's inbox only if they asked something. Don't move or delete
   inbox files — the orchestrator archives them into `done/`.
5. **Commands:** your shell starts in your own project folder. Run one command per call, use
   paths instead of `cd x && …` chains (e.g. `python -m pytest <path-to-tests>`). Some commands
   are not permitted; if one is denied, don't retry variations — note it in your report.
6. **Paths:** use the exact absolute folder paths given in your task prompt. Never create
   folders outside your own folder and `shared/`.
7. **End of a run:** write your handoff file in `handoffs/`, append one line to `board.md`
   (`- <date> <role>: <status> — <one-line summary>`), and finish with a short summary for the owner.
8. The owner writes English, Urdu or Roman Urdu/Hindi. Project files are in English; talk to the
   owner in the language they used.
9. Never commit secrets. Use `.env.example` for configuration keys.
10. **Rules:** you receive the owner's global rules and this project's RULES.md in your
    instructions; the project rules win if they conflict. When the owner states a lasting rule in
    feedback ("always …", "never …", "from now on …"):
    - "for all projects" / "har project me" → append it as a bullet under the right section of
      `rules/GLOBAL_RULES.md` (path in your instructions);
    - otherwise → append it to this project's `RULES.md` under the right section.
    Tell the owner where you saved it, so they never have to repeat it.

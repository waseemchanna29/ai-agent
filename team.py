"""Agent team orchestrator — runs each role as a separate Claude Code session
(`claude -p`) inside its own folder, using your Claude subscription.

    python team.py new <project> "<brief>"      create + run full pipeline
    python team.py run <project> [--from ROLE] [--only ROLE]
    python team.py ask <role> <project> "<task>"
    python team.py route <project>              let agents answer each other's inbox
    python team.py fix <project> [--rounds N]   development <-> qa loop
    python team.py open <role> <project>        interactive Claude Code session as that agent
    python team.py rules <project>              open the project's own RULES.md
    python team.py adopt <folder|path>          register existing code; requirements agent writes BRD + SRS
    python team.py rule "<text>" [-p project] [--for development]   add a rule once (global or per project)
    python team.py dev <project|folder|path> "<issue>"   test -> proposal -> your OK -> fix (backup/undo)
    python team.py status [project]

New projects keep their code in C:\\xampp\\htdocs\\<project> (TEAM_CODE_ROOT) by default.
"""
from __future__ import annotations

import argparse
import json
import os
import re
import shutil
import subprocess
import sys
import uuid
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parent
AGENTS_DIR = ROOT / "agents"
SHARED_DIR = ROOT / "shared" / "projects"
PIPELINE = ["requirements", "development", "qa", "docs"]
GLOBAL_RULES = ROOT / "rules" / "GLOBAL_RULES.md"


def _load_dotenv(path: Path) -> None:
    """Minimal .env loader (KEY=VALUE lines). Real environment variables win."""
    if not path.exists():
        return
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if line and not line.startswith("#") and "=" in line:
            k, v = line.split("=", 1)
            os.environ.setdefault(k.strip(), v.split(" #")[0].strip().strip('"').strip("'"))


_load_dotenv(ROOT / ".env")

# ------------------------------------------------------------------ settings
MODEL = os.getenv("TEAM_MODEL", "")             # "" = your Claude Code default; or opus / sonnet / fable
# Per-agent model override: TEAM_MODEL_DOCS, TEAM_MODEL_QA, ... (docs defaults to sonnet:
# writing docs from finished code doesn't need the biggest model, and it saves subscription usage).
ROLE_MODEL_DEFAULTS = {"docs": "sonnet"}


def model_for(role: str) -> str:
    return os.getenv(f"TEAM_MODEL_{role.upper()}") or ROLE_MODEL_DEFAULTS.get(role) or MODEL
EFFORT = os.getenv("TEAM_EFFORT", "high")       # low | medium | high | xhigh | max
# acceptEdits = file edits auto-approved, shell limited to ALLOWED_BASH below.
# auto        = Claude Code's auto mode classifier decides (fewer denials).
PERMISSION_MODE = os.getenv("TEAM_PERMISSION_MODE", "acceptEdits")
APPROVAL_GATES = os.getenv("TEAM_APPROVAL_GATES", "true").lower() == "true"
# New projects put their code in <CODE_ROOT>/<project> (your XAMPP htdocs by default).
# Set TEAM_CODE_ROOT= (empty) to keep code inside agents/development/projects/ instead.
CODE_ROOT = os.getenv("TEAM_CODE_ROOT", str(ROOT.parent))


def _shell(*prefixes: str) -> list[str]:
    # Windows Claude Code has both a Bash and a PowerShell tool; allow the same commands in both.
    return [f"{tool}({p} *)" for p in prefixes for tool in ("Bash", "PowerShell")]


_DEV_CMDS = _shell(
    "python", "py", "pip", "pytest", "uvicorn", "npm", "npx", "node", "php", "composer",
    "git status", "git diff", "git log", "git init", "git add", "mkdir", "ls", "cat",
    "cd", "New-Item", "Get-ChildItem", "Get-Content", "curl", "Invoke-WebRequest",
)
# TEAM_GIT_PUSH=true: the developer may branch, commit, push and open PRs on GitHub, using
# GITHUB_TOKEN from .env through github_helper.py. The token itself never reaches the agent.
GIT_PUSH = os.getenv("TEAM_GIT_PUSH", "false").lower() == "true"
GITHUB_HELPER = ROOT / "github_helper.py"
_GIT_WRITE_CMDS = _shell(
    "git checkout", "git switch", "git branch", "git commit", "git push", "git fetch", "git pull",
    "git remote", "git rev-parse", "git stash", "git rm", "git mv", "git restore", "git show",
    f"python {GITHUB_HELPER.as_posix()}",
)
# Never: force-push or push straight to main/master (team rule: branch + PR).
_NO_PUSH = [f"git push {x}" for x in (
    "--force", "-f", "--force-with-lease", "--mirror", "--delete", "-d",
    "origin main", "origin master", "origin HEAD:main", "origin HEAD:master", "origin main:main",
    "-u origin main", "-u origin master", "--set-upstream origin main", "--set-upstream origin master",
)]
_GIT_DENY = _shell(*_NO_PUSH) + [f"{tool}({c})" for c in _NO_PUSH for tool in ("Bash", "PowerShell")]
ALLOWED_TOOLS = {
    "requirements": ["WebSearch", "WebFetch", *_shell("ls", "Get-ChildItem")],
    "development": _DEV_CMDS + (_GIT_WRITE_CMDS if GIT_PUSH else []),
    "qa": _DEV_CMDS,
    "docs": _shell("ls", "Get-ChildItem", "mkdir"),
}
TITLES = {"requirements": "Requirements Analyst", "development": "Developer",
          "qa": "QA Engineer", "docs": "Technical Writer"}

# ------------------------------------------------------------------ console helpers
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")
C = {"cyan": "\033[36m", "green": "\033[32m", "yellow": "\033[33m", "red": "\033[31m",
     "dim": "\033[2m", "bold": "\033[1m", "magenta": "\033[35m", "end": "\033[0m"}
if os.name == "nt":
    os.system("")  # enables ANSI colours in Windows terminals


def say(text: str, color: str = "") -> None:
    print(f"{C.get(color, '')}{text}{C['end'] if color else ''}", flush=True)


def now(fmt: str = "%Y-%m-%d %H:%M") -> str:
    return datetime.now().strftime(fmt)


def posix_rule(p: Path) -> str:
    """Absolute path in Claude Code permission-rule form: C:\\a\\b -> //c/a/b"""
    s = p.resolve().as_posix()
    if len(s) > 1 and s[1] == ":":
        s = "/" + s[0].lower() + s[2:]
    return "/" + s


# ------------------------------------------------------------------ project
class Project:
    def __init__(self, name: str):
        self.name = name
        self.shared = SHARED_DIR / name
        self.meta_file = self.shared / "project.json"

    def exists(self) -> bool:
        return self.meta_file.exists()

    def meta(self) -> dict:
        return json.loads(self.meta_file.read_text(encoding="utf-8"))

    def save(self, meta: dict) -> None:
        self.meta_file.write_text(json.dumps(meta, indent=2, ensure_ascii=False), encoding="utf-8")

    def update(self, **kw) -> None:
        m = self.meta()
        for k, v in kw.items():
            if isinstance(v, dict) and isinstance(m.get(k), dict):
                m[k].update(v)
            else:
                m[k] = v
        self.save(m)

    def agent_dir(self, role: str) -> Path:
        return AGENTS_DIR / role / "projects" / self.name

    def code_dir(self) -> Path:
        cd = self.meta().get("code_dir")
        return Path(cd) if cd else self.agent_dir("development")

    def inbox(self, role: str) -> list[Path]:
        d = self.shared / "inbox" / role
        return sorted(p for p in d.glob("*.md")) if d.exists() else []

    def board(self, line: str) -> None:
        with (self.shared / "board.md").open("a", encoding="utf-8") as f:
            f.write(f"- {now()} {line}\n")


RULES_TEMPLATE = """# Project rules — {name}

These rules apply to THIS project only and override the general team rules where they differ.
Every agent receives this file in its instructions on every run. Edit it any time.

## All agents
- (e.g. Language of UI text: English / Urdu)
- (e.g. Client name, domain, deadline)

## Tech stack & conventions
- (e.g. PHP 8.2 + Laravel 11, MySQL via XAMPP, Blade + Tailwind)
- (e.g. PSR-12, snake_case DB columns, no ORMs other than Eloquent)

## Requirements agent
- (e.g. always include an admin role; ask before adding payment features)

## Development agent
- (e.g. database name: myapp_db, user root, no password — local only)
- (e.g. never modify files in /legacy; put new code under app/Modules)

## QA agent
- (e.g. use PHPUnit; test against http://localhost/{name})

## Docs agent
- (e.g. user guide in Urdu; API docs in English)
"""


GLOBAL_RULES_TEMPLATE = """# Global rules — apply to EVERY project

Your standing preferences. Every agent gets this file on every run, in every project.
A project's own RULES.md overrides these when they conflict.
Add a rule quickly:  python team.py rule "Always use prepared statements for SQL"

## All agents

## Tech stack & conventions

## Requirements agent

## Development agent

## QA agent

## Docs agent
"""


def ensure_global_rules() -> None:
    if not GLOBAL_RULES.exists():
        GLOBAL_RULES.parent.mkdir(parents=True, exist_ok=True)
        GLOBAL_RULES.write_text(GLOBAL_RULES_TEMPLATE, encoding="utf-8")


SECTIONS = {"all": "All agents", "stack": "Tech stack & conventions", "requirements": "Requirements agent",
            "development": "Development agent", "qa": "QA agent", "docs": "Docs agent"}


def add_rule(path: Path, text: str, section: str) -> None:
    """Append '- text' at the end of '## <section>' (section created if missing)."""
    header = f"## {SECTIONS[section]}"
    lines = path.read_text(encoding="utf-8").splitlines()
    if header not in lines:
        lines += ["", header]
    i = lines.index(header) + 1
    while i < len(lines) and not lines[i].startswith("## "):
        i += 1
    while i > 0 and not lines[i - 1].strip():  # insert right after the section's last line
        i -= 1
    lines.insert(i, f"- {text.strip()}")
    path.write_text("\n".join(lines) + "\n", encoding="utf-8")
    say(f"Added to {path} under '{header}':\n  - {text.strip()}", "green")


def resolve_code_dir(name: str, code_dir: str | None, in_team: bool, confirm: bool = True) -> Path | None:
    if code_dir:
        p = Path(code_dir).resolve()
    elif CODE_ROOT and not in_team:
        p = (Path(CODE_ROOT) / name).resolve()
    else:
        return None
    if p == ROOT or ROOT in p.parents:
        sys.exit(f"Code folder {p} is inside the team folder; choose another --code-dir or name.")
    if confirm and p.exists() and any(p.iterdir()):
        say(f"⚠ {p} already exists and has files — the developer agent will work on that code.", "yellow")
        if ask("  Continue? [y/n]", "yn", "n") != "y":
            sys.exit("Cancelled.")
    return p


def create_project(name: str, brief: str, code_dir: str | None, in_team: bool = False,
                   rules_file: str | None = None, confirm: bool = True) -> Project:
    proj = Project(name)
    if proj.exists():
        sys.exit(f"Project '{name}' already exists.")
    code = resolve_code_dir(name, code_dir, in_team, confirm)
    existing = bool(code and code.exists() and any(code.iterdir()))
    for sub in ["handoffs", *[f"inbox/{r}/done" for r in PIPELINE + ['owner']]]:
        (proj.shared / sub).mkdir(parents=True, exist_ok=True)
    for role in PIPELINE:
        if not (role == "development" and code):
            proj.agent_dir(role).mkdir(parents=True, exist_ok=True)
    if code:
        code.mkdir(parents=True, exist_ok=True)
    rules = Path(rules_file).read_text(encoding="utf-8") if rules_file else RULES_TEMPLATE.format(name=name)
    (proj.shared / "RULES.md").write_text(rules, encoding="utf-8")
    (proj.shared / "BRIEF.md").write_text(f"# Brief — {name}\n\n{brief.strip()}\n", encoding="utf-8")
    (proj.shared / "decisions.md").write_text("# Decisions (append-only)\n\n", encoding="utf-8")
    (proj.shared / "board.md").write_text(f"# Status board — {name}\n\n", encoding="utf-8")
    proj.save({
        "name": name, "created": now(),
        "code_dir": str(code) if code else None,
        "existing_code": existing,
        "phases": {r: "pending" for r in PIPELINE},
        "sessions": {},
    })
    proj.board("owner: created project")
    return proj


# ------------------------------------------------------------------ running one agent
def context_block(proj: Project, role: str) -> str:
    paths = "\n".join(f"- {r} folder: {proj.agent_dir(r) if r != 'development' else proj.code_dir()}"
                      for r in PIPELINE)
    return (
        f"Project: **{proj.name}**  (you are the {role} agent)\n"
        f"- shared folder: {proj.shared}\n{paths}\n"
        f"- project rules (already in your instructions): {proj.shared / 'RULES.md'}\n"
        f"- your inbox: {proj.shared / 'inbox' / role}\n"
        f"- owner inbox (questions for the human): {proj.shared / 'inbox' / 'owner'}\n"
        f"- handoffs so far: {len(list((proj.shared / 'handoffs').glob('*.md')))}\n"
        f"Today: {now()}\n"
    )


def system_extra(proj: Project, role: str) -> str:
    """Instructions added to the agent's system prompt on EVERY run (also resumes):
    the project's own RULES.md, plus the team + role rules when the agent runs outside
    the team folder (CLAUDE.md auto-discovery only covers the team folder)."""
    parts = []
    if role == "development" and proj.meta().get("code_dir"):
        parts += [(ROOT / "CLAUDE.md").read_text(encoding="utf-8"),
                  (AGENTS_DIR / role / "CLAUDE.md").read_text(encoding="utf-8")]
    ensure_global_rules()
    parts.append(
        f"# OWNER'S GLOBAL RULES (from {GLOBAL_RULES}) — apply to every project\n"
        f"Follow the 'All agents' section and the section for your role ({role}).\n\n"
        + GLOBAL_RULES.read_text(encoding="utf-8")
    )
    rules = proj.shared / "RULES.md"
    if rules.exists():
        parts.append(
            f"# PROJECT RULES for '{proj.name}' (from {rules})\n"
            "These rules are specific to this project and take priority over general team rules. "
            f"Follow the 'All agents' section and the section for your role ({role}).\n\n"
            + rules.read_text(encoding="utf-8")
        )
    if role == "development" and GIT_PUSH:
        parts.append(
            "# GIT & GITHUB (enabled by the owner)\n"
            "You may create a task branch, commit, push it, and open a pull request — no human approval "
            "prompt is needed. GitHub login is automatic: git gets the owner's token through a credential "
            "helper. Never ask for, print, store or put a token in a URL or remote.\n"
            "- Never commit or push to main/master; never force-push (these are blocked).\n"
            "- Your shell already starts in the code folder: run git plainly (`git rm <file>`), never "
            "`cd ... &&` or `git -C <path>` — those forms are denied.\n"
            "- An owner's request on the card/task to push or open a PR IS the board approval for it.\n"
            "- Steps: `git checkout -b <type>/<task>` → `git add <files>` → `git commit -m \"...\"` → "
            "`git push -u origin <branch>`.\n"
            f"- Open the PR with: `python {GITHUB_HELPER.as_posix()} pr --title \"...\" --body \"...\"` "
            "(run in the code folder; it targets dev or staging if one exists, else main, and prints the "
            "PR link). Use --body-file <file> for a long body. `gh` is not installed.\n"
            "- Report the branch name and the PR link in your summary."
        )
    return "\n\n---\n\n".join(parts)


def workdir(proj: Project, role: str) -> Path:
    """Where the agent runs. Writable = this folder + shared/projects/<p>; everything
    else is read-only. Running inside agents/<role>/projects/<p> means Claude Code
    loads agents/<role>/CLAUDE.md and the root CLAUDE.md automatically (parent dirs)."""
    d = proj.code_dir() if role == "development" else proj.agent_dir(role)
    d.mkdir(parents=True, exist_ok=True)
    return d


def build_cmd(proj: Project, role: str, prompt: str, session: str, resume: bool,
              read_only_code: bool = False) -> list[str]:
    claude = shutil.which("claude") or "claude"
    code = proj.code_dir()
    external = bool(proj.meta().get("code_dir"))
    cmd = [claude, "-p", prompt, "--output-format", "stream-json", "--verbose",
           "--permission-mode", PERMISSION_MODE, "--effort", EFFORT,
           "--add-dir", str(proj.shared)]
    if model_for(role):
        cmd += ["--model", model_for(role)]
    cmd += ["--resume", session] if resume else ["--session-id", session]
    cmd += ["--append-system-prompt", system_extra(proj, role)]

    # Reads: the whole team folder (+ external code). Writes: only working dirs (cwd + shared).
    allow = [f"Read({posix_rule(ROOT)}/**)", f"Edit({posix_rule(GLOBAL_RULES)})"]
    if external:
        allow.append(f"Read({posix_rule(code)}/**)")
    if role == "docs":
        allow.append(f"Edit({posix_rule(code)}/README.md)")  # docs may maintain the code README
    if PERMISSION_MODE != "auto":
        allow += ALLOWED_TOOLS[role]
    cmd += ["--allowedTools", *allow]
    # Hard write-protection: never edit another agent's folder, even by mistake.
    # (docs is exempt for the code folder so its README rule can apply; other files there
    # are still outside its working dirs and therefore not writable in -p mode.)
    deny = [f"Edit({posix_rule(AGENTS_DIR / other)}/**)" for other in PIPELINE
            if other != role and not (role == "docs" and other == "development")]
    if external and role not in ("development", "docs"):
        deny.append(f"Edit({posix_rule(code)}/**)")
    if read_only_code:
        # Investigation step: the agent may run tests but cannot change project files.
        deny.append(f"Edit({posix_rule(code)}/**)")
    # Secrets file: no agent reads it (tokens reach git only through github_helper.py).
    deny.append(f"Read({posix_rule(ROOT / '.env')})")
    if role == "development":
        deny += _GIT_DENY
    cmd += ["--disallowedTools", *deny]
    return cmd


def run_claude(proj: Project, role: str, prompt: str, session: str | None = None,
               read_only_code: bool = False) -> tuple[str, str, bool]:
    """Run one headless Claude Code turn. Returns (final_text, session_id, ok)."""
    resume = session is not None
    session = session or str(uuid.uuid4())
    cwd = workdir(proj, role)
    cmd = build_cmd(proj, role, prompt, session, resume, read_only_code)
    say(f"\n━━ {TITLES[role]} · {proj.name} ━━", "cyan")

    result, ok, cost = "", False, None
    proc = subprocess.Popen(cmd, cwd=cwd, stdin=subprocess.DEVNULL,  # never steal the owner's keyboard input
                            stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                            text=True, encoding="utf-8", errors="replace", env=child_env())
    try:
        for line in proc.stdout:
            try:
                ev = json.loads(line)
            except json.JSONDecodeError:
                continue
            t = ev.get("type")
            if t == "assistant":
                for b in ev.get("message", {}).get("content", []):
                    if b.get("type") == "text" and b.get("text", "").strip():
                        say(b["text"].strip())
                    elif b.get("type") == "tool_use":
                        say(f"  → {b.get('name')} {_brief(b.get('input'))}", "dim")
            elif t == "result":
                result = ev.get("result") or ""
                ok = not ev.get("is_error")
                cost = ev.get("total_cost_usd")
                session = ev.get("session_id", session)
                for d in ev.get("permission_denials") or []:
                    say(f"  ✗ denied: {d.get('tool_name')} {_brief(d.get('tool_input'))}", "yellow")
        proc.wait()
    except KeyboardInterrupt:
        proc.terminate()
        say("\nInterrupted.", "yellow")
        return "", session, False
    err = proc.stderr.read().strip()
    if proc.returncode and err:
        say(err[-1500:], "red")
    if cost is not None:
        say(f"  (session {session[:8]} · equivalent cost ${cost:.2f})", "dim")
    if not ok and "limit" in result.lower():
        say("⏳ Claude subscription usage limit reached. Nothing is lost — run the same command again "
            "after the reset time shown above. (Tip: TEAM_MODEL=sonnet uses less of your limit.)", "yellow")
    proj.update(sessions={role: session})
    return result, session, ok


def child_env() -> dict:
    """If team.py is launched from inside a Claude Code / Desktop session, don't
    leak that host session's variables into the agents — they use your own CLI login."""
    env = dict(os.environ)
    inside_host = "CLAUDE_CODE_ENTRYPOINT" in env or "CLAUDECODE" in env
    for k in list(env):
        if k == "CLAUDE_CODE_OAUTH_TOKEN":  # your own token from .env / `claude setup-token`
            continue
        if k == "CLAUDECODE" or k == "CLAUDE_PID" or k.startswith(("CLAUDE_CODE_", "CLAUDE_AGENT_SDK_")):
            env.pop(k)
        elif inside_host and k == "ANTHROPIC_BASE_URL":
            env.pop(k)
    env.pop("GITHUB_TOKEN", None)   # the agent never sees it; git asks github_helper.py instead
    env.pop("GH_TOKEN", None)
    if GIT_PUSH:
        # Extra git config for the agent's git only (nothing is written to your .gitconfig):
        # for github.com, drop other credential helpers (no login pop-ups) and use ours.
        py = Path(sys.executable).as_posix()
        helper = f'!"{py}" "{GITHUB_HELPER.as_posix()}" credential'
        n = int(env.get("GIT_CONFIG_COUNT", "0") or 0)
        for i, (k, v) in enumerate([("credential.https://github.com.helper", ""),
                                    ("credential.https://github.com.helper", helper)], start=n):
            env[f"GIT_CONFIG_KEY_{i}"], env[f"GIT_CONFIG_VALUE_{i}"] = k, v
        env["GIT_CONFIG_COUNT"] = str(n + 2)
        env["GIT_TERMINAL_PROMPT"] = "0"
        env["GCM_INTERACTIVE"] = "never"
    return env


def _brief(inp) -> str:
    if not isinstance(inp, dict):
        return ""
    for k in ("file_path", "path", "command", "pattern", "url", "query"):
        if k in inp:
            return str(inp[k])[:110]
    return ""


# ------------------------------------------------------------------ phases + gates
DEFAULT_TASK = {
    "requirements": "Read the brief and produce REQUIREMENTS.md (or ask the owner questions first).",
    "development": "Implement the requirements (fix the latest QA bugs first if a QA report exists).",
    "qa": "Test the current implementation against the requirements and write the QA report.",
    "docs": "Write the full documentation set for what was built.",
}


DISCOVERY_TASK = (
    "This is an EXISTING codebase with no documentation from us yet. Follow the 'Existing codebase "
    "(first time)' section of your role: study the code and write BRD.md and REQUIREMENTS.md (v1 = the "
    "system as it is today), plus questions for the owner about anything the code cannot tell you."
)


def has_spec(proj: Project) -> bool:
    return (proj.agent_dir("requirements") / "REQUIREMENTS.md").exists()


def default_task(proj: Project, role: str) -> str:
    if role != "requirements":
        return DEFAULT_TASK[role]
    m = proj.meta()
    if not has_spec(proj):
        if m.get("existing_code"):
            return (DISCOVERY_TASK + " Then, if BRIEF.md asks for changes or new features, apply them "
                    "as v2 using the 'Every change after v1' process.")
        return DEFAULT_TASK["requirements"]
    return ("REQUIREMENTS.md already exists. Read BRIEF.md and any new owner requests; if they ask for "
            "changes, apply them with the 'Every change after v1' process. Otherwise confirm the spec is current.")


def ensure_spec(proj: Project) -> None:
    """Existing project touched for the first time: offer to document it before working on it."""
    if has_spec(proj) or not proj.meta().get("existing_code"):
        return
    say(f"\n'{proj.name}' has no BRD / SRS yet. The requirements agent can study the code and write them,"
        " so every agent understands the project before changing it.", "magenta")
    if ask("  Document the project first? [y/n]", "yn", "y") == "y":
        run_phase(proj, "requirements", DISCOVERY_TASK)


def run_phase(proj: Project, role: str, task: str | None = None, gate: bool = True) -> bool:
    proj.update(phases={role: "running"})
    proj.board(f"{role}: started")
    owner_seen = set(proj.inbox("owner"))
    prompt = context_block(proj, role) + "\nTask: " + (task or default_task(proj, role))
    session = None
    while True:
        summary, session, ok = run_claude(proj, role, prompt, session)
        if not ok:
            proj.update(phases={role: "failed"})
            say(f"{TITLES[role]} did not finish. Resume: python team.py run {proj.name} --from {role}", "red")
            return False
        if not gate or not APPROVAL_GATES:
            break
        new_q = [p for p in proj.inbox("owner") if p not in owner_seen]
        for q in new_q:
            say(f"\n📨 Question for you ({q.name}):", "magenta")
            say(q.read_text(encoding="utf-8"))
        say(f"\n▶ Approval gate — {TITLES[role]}", "bold")
        choice = ask("  [a] approve & continue   [f] feedback / answer   [s] stop", "afs",
                     default="f" if new_q else "a")
        if choice == "a":
            break
        if choice == "s":
            proj.update(phases={role: "paused"})
            proj.board(f"{role}: paused by owner")
            return False
        feedback = read_multiline("  Type your feedback / answers (empty line to send):")
        for q in new_q:
            q.rename(q.parent / "done" / q.name)
        owner_seen = set(proj.inbox("owner"))
        prompt = f"Owner's reply:\n{feedback}\n\nContinue your task with this input." if feedback else "Continue."
    proj.update(phases={role: "done"})
    proj.board(f"{role}: approved")
    export_pdfs(proj, role)
    return True


PDF_SOURCES = {  # which Markdown files of which agent become client PDFs
    "requirements": ("Requirements Analyst", ["BRD.md", "REQUIREMENTS.md"]),
    "docs": ("Technical Writer", None),  # None = every .md in the docs folder
}


def rule_value(proj: Project, key: str) -> str | None:
    """A 'Key: value' rule line, e.g. '- Prepared by: AI Engineer'. Project RULES.md wins over global."""
    for f in (proj.shared / "RULES.md", GLOBAL_RULES):
        if f.exists():
            m = re.search(rf"^\s*[-*]?\s*{re.escape(key)}\s*:\s*(.+?)\s*$", f.read_text(encoding="utf-8"), re.M | re.I)
            if m:
                return m.group(1).strip().strip('"\'`')
    return None


def export_pdfs(proj: Project, role: str) -> None:
    if role not in PDF_SOURCES:
        return
    import pdf_export
    author, names = PDF_SOURCES[role]
    author = rule_value(proj, "Prepared by") or f"{author} (AI agent team)"
    folder = proj.agent_dir(role)
    files = [folder / n for n in names] if names else sorted(folder.glob("*.md"))
    files = [f for f in files if f.exists()]
    if not files:
        return
    say(f"Creating PDFs for {TITLES[role]} …", "dim")
    for f in files:
        try:
            pdf = pdf_export.to_pdf(f, folder / "pdf", project=proj.name, prepared_by=author)
            say(f"  📄 {pdf}", "green")
        except Exception as e:  # a PDF problem must never break the pipeline
            say(f"  PDF failed for {f.name}: {e}", "yellow")


def route(proj: Project, max_rounds: int = 3) -> None:
    """Let agents answer messages in their inboxes until all inboxes are empty."""
    for _ in range(max_rounds):
        pending = [r for r in PIPELINE if proj.inbox(r)]
        if not pending:
            say("All agent inboxes are empty.", "green")
            return
        for role in pending:
            msgs = proj.inbox(role)
            listing = "\n".join(f"- {m.name}" for m in msgs)
            prompt = context_block(proj, role) + (
                f"\nTask: ONLY process these inbox messages, nothing else:\n{listing}\n"
                "- If a message asks a question or requests a change, answer it in the sender's inbox "
                "and update your own files if the answer changes them.\n"
                "- If it is FYI / an acknowledgment, do not reply.\n"
                "- Do NOT start or continue your normal phase work, and do not move the inbox files "
                "(the orchestrator archives them)."
            )
            _, _, ok = run_claude(proj, role, prompt)
            if ok:
                done = proj.shared / "inbox" / role / "done"
                done.mkdir(parents=True, exist_ok=True)
                for m in msgs:
                    if m.exists():
                        m.replace(done / m.name)
                proj.board(f"{role}: answered {len(msgs)} inbox message(s)")
                export_pdfs(proj, role)  # answers may have changed the spec / docs
    say("Stopped after max rounds; some messages may still be pending.", "yellow")


def run_pipeline(proj: Project, start: str | None = None, only: str | None = None) -> None:
    phases = [only] if only else PIPELINE[PIPELINE.index(start) if start else 0:]
    for role in phases:
        if not run_phase(proj, role):
            return
        if any(proj.inbox(r) for r in PIPELINE):
            say("Agents left messages for each other — routing inboxes…", "dim")
            route(proj)
    say(f"\n✔ Pipeline finished for {proj.name}. Shared folder: {proj.shared}", "green")


def fix_loop(proj: Project, rounds: int) -> None:
    for i in range(rounds):
        say(f"\n=== Fix round {i + 1}/{rounds} ===", "bold")
        if not run_phase(proj, "development", "Fix every bug in the latest QA_REPORT.md, re-run tests, update DEV_NOTES.md and write a handoff to QA."):
            return
        if not run_phase(proj, "qa", "Re-test after the developer's fixes; write a new QA report."):
            return
        report = proj.agent_dir("qa") / "QA_REPORT.md"
        if report.exists() and "NOT READY" not in report.read_text(encoding="utf-8").upper():
            say("QA verdict: READY", "green")
            return


# ------------------------------------------------------------------ dev: investigate -> approve -> fix
BACKUP_IGNORE = {".git", "node_modules", "vendor", "__pycache__", ".pytest_cache", ".venv", "venv",
                 ".idea", ".vscode", "storage", "cache", ".next", "dist", "build"}


def find_or_register(target: str) -> Project:
    """Accept a registered project name, a folder name in htdocs, or any path.
    Unknown folders are registered automatically (no pipeline is run)."""
    if Project(target).exists():
        return Project(target)
    path = Path(target)
    if not path.is_absolute():
        path = Path(CODE_ROOT or ROOT.parent) / target
    path = path.resolve()
    if not path.is_dir():
        sys.exit(f"Not a registered project and no folder at {path}")
    if SHARED_DIR.exists():
        for d in SHARED_DIR.iterdir():
            mf = d / "project.json"
            if mf.exists() and json.loads(mf.read_text(encoding="utf-8")).get("code_dir") == str(path):
                return Project(d.name)
    name = path.name
    if Project(name).exists():
        sys.exit(f"A different project is already called '{name}'. Use that name or rename the folder.")
    say(f"Registering existing codebase {path} as project '{name}'", "dim")
    proj = create_project(name, f"Existing codebase at {path}, registered for maintenance and fixes.",
                          str(path), confirm=False)
    return proj


def _ignore(_dir, names):
    return [n for n in names if n in BACKUP_IGNORE]


def diff_against(code: Path, backup: Path) -> tuple[list[str], list[str], list[str]]:
    """(changed, added, deleted) relative paths, ignoring BACKUP_IGNORE folders."""
    def files(root: Path) -> dict[str, Path]:
        out = {}
        for p in root.rglob("*"):
            rel = p.relative_to(root)
            if p.is_file() and not any(part in BACKUP_IGNORE for part in rel.parts):
                out[rel.as_posix()] = p
        return out
    now_f, old_f = files(code), files(backup)
    changed = [r for r in now_f if r in old_f and now_f[r].read_bytes() != old_f[r].read_bytes()]
    added = [r for r in now_f if r not in old_f]
    deleted = [r for r in old_f if r not in now_f]
    return sorted(changed), sorted(added), sorted(deleted)


def restore(code: Path, backup: Path) -> None:
    changed, added, deleted = diff_against(code, backup)
    for r in changed + deleted:
        (code / r).parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(backup / r, code / r)
    for r in added:
        (code / r).unlink()
    say(f"Restored {len(changed) + len(deleted)} file(s), removed {len(added)} new file(s).", "green")


def dev_fix(proj: Project, issue: str) -> None:
    code = proj.code_dir()
    stamp = now("%Y%m%d-%H%M")
    fixes = proj.shared / "fixes"
    fixes.mkdir(parents=True, exist_ok=True)
    report = fixes / f"{stamp}-fix.md"
    in_htdocs = code.parent == Path(CODE_ROOT or ROOT.parent).resolve()
    url = f"http://localhost/{code.name}/" if in_htdocs else "(no local URL known)"

    # Step 1 - investigate & test, read-only.
    prompt = context_block(proj, "development") + f"""
Task (MAINTENANCE FIX - step 1 of 2: investigate only):
The owner says: {issue}

Codebase: {code}
Local URL (XAMPP): {url}

1. Read the relevant code. Reproduce / test the problem: run existing tests, `php -l` on relevant
   PHP files, `curl` the local URL, or run the script - whatever fits.
2. You CANNOT modify project files in this step. Do not try.
3. Write your proposal to {report} with these sections:
   ## Problem          (what the owner reported, in your words)
   ## How I tested     (exact commands and the relevant output)
   ## Root cause       (file:line and why)
   ## Proposed changes (per file: what changes, with short before/after snippets)
   ## Risk             (what else could be affected)
   ## How I will verify after the fix
4. Then stop and give the owner a 3-5 line summary in their language.
If you find no problem, say so in the report and explain what you checked."""
    summary, session, ok = run_claude(proj, "development", prompt, read_only_code=True)
    while True:
        if not ok:
            say("Investigation did not finish.", "red")
            return
        if report.exists():
            say(f"\n---- Proposal: {report} ----", "magenta")
            say(report.read_text(encoding="utf-8"))
        say("\n▶ Apply this fix?", "bold")
        choice = ask("  [a] approve & apply   [f] feedback / ask for a different fix   [s] stop", "afs", "a")
        if choice == "s":
            proj.board(f"development: fix proposal {report.name} not approved")
            say(f"Nothing was changed. Proposal kept at {report}", "yellow")
            return
        if choice == "a":
            break
        fb = read_multiline("  Your feedback (empty line to send):")
        summary, session, ok = run_claude(
            proj, "development",
            f"Owner feedback on your proposal:\n{fb}\n\nInvestigate further if needed and rewrite {report}. "
            "Still do NOT modify project files.", session, read_only_code=True)

    # Step 2 - backup, then apply.
    backup = proj.shared / "backups" / stamp
    say(f"Backing up {code} -> {backup} ...", "dim")
    shutil.copytree(code, backup, ignore=_ignore)
    proj.board(f"development: applying approved fix ({report.name}), backup {backup.name}")
    apply_prompt = (f"The owner APPROVED your proposal in {report}. Now apply exactly those changes in "
                    f"{code}. Then run the verification you described. Append a section "
                    "`## Result` to the report: files changed, test commands + output, and anything you "
                    "could not verify. If you need to go beyond the approved change, stop and explain instead.")
    while True:
        summary, session, ok = run_claude(proj, "development", apply_prompt, session)
        changed, added, deleted = diff_against(code, backup)
        say("\nFiles changed in the project:", "bold")
        for r in changed:
            say(f"  M {r}", "yellow")
        for r in added:
            say(f"  A {r}", "green")
        for r in deleted:
            say(f"  D {r}", "red")
        if not (changed or added or deleted):
            say("  (none)", "dim")
        choice = ask("  [a] accept   [f] more changes   [u] undo everything (restore backup)", "afu", "a")
        if choice == "a":
            proj.board(f"development: fix accepted ({report.name})")
            say(f"Done. Report: {report}\nBackup kept at: {backup}", "green")
            return
        if choice == "u":
            restore(code, backup)
            proj.board(f"development: fix undone ({report.name})")
            return
        fb = read_multiline("  What else should change? (empty line to send):")
        apply_prompt = (f"Owner feedback after your fix:\n{fb}\n\n"
                        "Make these changes, re-verify, and update the `## Result` section.")


# ------------------------------------------------------------------ input helpers
def ask(prompt: str, choices: str, default: str) -> str:
    while True:
        try:
            v = input(f"{prompt} ({default}): ").lstrip("﻿").strip().lower() or default
        except EOFError:  # no more input (e.g. piped): never auto-approve, stop safely
            return "s" if "s" in choices else ("n" if "n" in choices else default)
        if v in choices:
            return v


def read_multiline(prompt: str) -> str:
    print(prompt)
    lines = []
    while True:
        try:
            line = input()
        except EOFError:
            break
        if not line.strip():
            break
        lines.append(line)
    return "\n".join(lines).strip()


# ------------------------------------------------------------------ CLI
def need(name: str) -> Project:
    """Registered project name, or a folder in htdocs / a path (registered on first use)."""
    if Project(name).exists():
        return Project(name)
    path = Path(name) if Path(name).is_absolute() else Path(CODE_ROOT or ROOT.parent) / name
    if path.is_dir():
        return find_or_register(str(path))
    sys.exit(f"No project or folder '{name}'. New project:  python team.py new {name} \"<brief>\"")


def main() -> None:
    ap = argparse.ArgumentParser(description="Personal Claude Code agent team")
    sub = ap.add_subparsers(dest="cmd", required=True)

    s = sub.add_parser("new"); s.add_argument("project"); s.add_argument("brief", nargs="?", default="")
    s.add_argument("--code-dir", help=f"Code folder (default: {CODE_ROOT or 'agents/development/projects'}\\<project>)")
    s.add_argument("--in-team", action="store_true", help="Keep code inside agents/development/projects/<project>")
    s.add_argument("--rules-file", help="Start RULES.md from this file (e.g. a Laravel rules template)")
    s.add_argument("--brief-file"); s.add_argument("--no-run", action="store_true")
    s = sub.add_parser("rules", help="Open the global rules, or one project's RULES.md")
    s.add_argument("project", nargs="?")
    s = sub.add_parser("rule", help="Add one rule: global by default, or --project for one project")
    s.add_argument("text")
    s.add_argument("--project", "-p")
    s.add_argument("--for", dest="section", choices=list(SECTIONS), default="all",
                   help="Which section / agent the rule is for (default: all)")
    s = sub.add_parser("adopt", help="Register an existing codebase and let the requirements agent document it")
    s.add_argument("target", help="Folder name in htdocs, or full path")
    s = sub.add_parser("run"); s.add_argument("project")
    s.add_argument("--from", dest="start", choices=PIPELINE); s.add_argument("--only", choices=PIPELINE)
    s = sub.add_parser("ask"); s.add_argument("role", choices=PIPELINE); s.add_argument("project"); s.add_argument("task")
    s = sub.add_parser("route"); s.add_argument("project")
    s = sub.add_parser("fix"); s.add_argument("project"); s.add_argument("--rounds", type=int, default=2)
    s = sub.add_parser("dev", help="Developer goes into a project, tests, proposes a fix, applies it after your OK")
    s.add_argument("target", help="Project name, folder name in htdocs, or full path")
    s.add_argument("issue", help="What to check / what is broken")
    s = sub.add_parser("open"); s.add_argument("role", choices=PIPELINE); s.add_argument("project")
    s = sub.add_parser("status"); s.add_argument("project", nargs="?")
    s = sub.add_parser("pdf", help="(Re)create the PDFs: BRD, SRS and all docs"); s.add_argument("project")
    a = ap.parse_args()

    if a.cmd == "new":
        brief = Path(a.brief_file).read_text(encoding="utf-8") if a.brief_file else a.brief
        brief = brief or input("Describe what you want built:\n> ")
        proj = create_project(a.project, brief, a.code_dir, a.in_team, a.rules_file)
        say(f"Created {proj.shared}\n  code:  {proj.code_dir()}\n  rules: {proj.shared / 'RULES.md'}", "green")
        if not a.no_run and not a.rules_file and APPROVAL_GATES:
            say("Edit the project rules now if you want (RULES.md), then press Enter to start…", "magenta")
            input()
        if not a.no_run:
            run_pipeline(proj)
    elif a.cmd == "rules":
        ensure_global_rules()
        path = need(a.project).shared / "RULES.md" if a.project else GLOBAL_RULES
        say(str(path))
        os.startfile(path) if os.name == "nt" else subprocess.call([os.getenv("EDITOR", "nano"), str(path)])
    elif a.cmd == "rule":
        ensure_global_rules()
        add_rule(need(a.project).shared / "RULES.md" if a.project else GLOBAL_RULES, a.text, a.section)
    elif a.cmd == "adopt":
        proj = find_or_register(a.target)
        if has_spec(proj) and ask("  Already documented. Re-study the code anyway? [y/n]", "yn", "n") != "y":
            return
        proj.update(existing_code=True)
        run_phase(proj, "requirements", DISCOVERY_TASK)
    elif a.cmd == "run":
        run_pipeline(need(a.project), a.start, a.only)
    elif a.cmd == "ask":
        proj = need(a.project)
        if a.role != "requirements":
            ensure_spec(proj)
        run_phase(proj, a.role, a.task)
    elif a.cmd == "route":
        route(need(a.project))
    elif a.cmd == "dev":
        proj = find_or_register(a.target)
        ensure_spec(proj)
        dev_fix(proj, a.issue)
    elif a.cmd == "fix":
        fix_loop(need(a.project), a.rounds)
    elif a.cmd == "open":
        proj = need(a.project)
        cmd = build_cmd(proj, a.role, context_block(proj, a.role), "", False)
        cmd = [c for c in cmd if c not in ("-p", "--verbose")]
        # interactive: drop -p/stream-json/session flags; resume the agent's last session if any
        i = cmd.index("--output-format"); del cmd[i:i + 2]
        i = cmd.index("--session-id"); del cmd[i:i + 2]
        last = proj.meta()["sessions"].get(a.role)
        if last:
            cmd[1:2] = ["--resume", last]  # replaces the context prompt
        subprocess.call(cmd, cwd=workdir(proj, a.role), env=child_env())
    elif a.cmd == "pdf":
        proj = need(a.project)
        for role in PDF_SOURCES:
            export_pdfs(proj, role)
    elif a.cmd == "status":
        projects = [need(a.project)] if a.project else [Project(d.name) for d in sorted(SHARED_DIR.iterdir()) if (d / "project.json").exists()]
        if not projects:
            say("No projects yet.  python team.py new <name> \"<brief>\"")
        for p in projects:
            m = p.meta()
            say(f"\n{m['name']}", "bold")
            for r in PIPELINE:
                inbox = len(p.inbox(r))
                say(f"  {TITLES[r]:<22} {m['phases'].get(r, '-'):<9}" + (f" 📨 {inbox}" if inbox else ""))
            if p.inbox("owner"):
                say(f"  Owner inbox: {len(p.inbox('owner'))} question(s) waiting", "magenta")


if __name__ == "__main__":
    main()

"""Runs the role agents in order with an approval gate after each phase.
State lives in <project>/.agentic/state.json so you can stop and resume."""
from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

from rich.console import Console
from rich.markdown import Markdown
from rich.panel import Panel
from rich.prompt import Prompt

from . import config
from .agents import PIPELINE, ROLES
from .llm import AgentStopped, run_agent

console = Console()


# ---------------------------------------------------------------- project state
class Project:
    def __init__(self, root: Path):
        self.root = root.resolve()
        self.meta_dir = self.root / ".agentic"
        self.state_file = self.meta_dir / "state.json"

    @property
    def name(self) -> str:
        return self.root.name

    def exists(self) -> bool:
        return self.state_file.exists()

    def load(self) -> dict:
        return json.loads(self.state_file.read_text(encoding="utf-8"))

    def save(self, state: dict) -> None:
        self.meta_dir.mkdir(parents=True, exist_ok=True)
        self.state_file.write_text(json.dumps(state, indent=2, ensure_ascii=False), encoding="utf-8")

    def set_phase(self, phase: str, status: str, summary: str = "") -> None:
        state = self.load()
        state["phases"][phase] = {"status": status, "summary": summary[:2000], "at": _now()}
        self.save(state)

    def log(self, text: str) -> None:
        self.meta_dir.mkdir(parents=True, exist_ok=True)
        with (self.meta_dir / "log.md").open("a", encoding="utf-8") as f:
            f.write(f"\n## {_now()}\n{text}\n")


def _now() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M UTC")


def resolve_project(name_or_path: str) -> Project:
    p = Path(name_or_path)
    if p.is_absolute() or p.exists():
        return Project(p)
    return Project(config.WORKSPACE_DIR / name_or_path)


def create_project(name_or_path: str, brief: str) -> Project:
    proj = resolve_project(name_or_path)
    proj.root.mkdir(parents=True, exist_ok=True)
    (proj.root / "docs").mkdir(exist_ok=True)
    (proj.root / "docs" / "BRIEF.md").write_text(f"# Brief\n\n{brief.strip()}\n", encoding="utf-8")
    proj.save({
        "name": proj.name,
        "created": _now(),
        "brief": brief.strip(),
        "phases": {k: {"status": "pending", "summary": "", "at": ""} for k in PIPELINE},
    })
    return proj


# ---------------------------------------------------------------- phases
def run_phase(proj: Project, role_key: str, instruction: str | None = None, gate: bool = True) -> bool:
    """Run one role with a feedback loop. Returns True if approved/done,
    False if the owner chose to stop."""
    role = ROLES[role_key]
    kickoff = instruction or (
        f"Start your work on project '{proj.name}'. Your deliverable is {role.output}."
    )
    messages: list = [{"role": "user", "content": kickoff}]
    proj.set_phase(role_key, "running")

    while True:
        try:
            summary = run_agent(
                system=role.system, messages=messages, tool_names=role.tools,
                project_root=proj.root, label=role.title,
            )
        except AgentStopped as e:
            console.print(f"[red]{role.title} stopped: {e}[/red]")
            proj.set_phase(role_key, "failed", str(e))
            return False
        except KeyboardInterrupt:
            console.print("\n[yellow]Interrupted.[/yellow]")
            proj.set_phase(role_key, "paused")
            return False

        proj.log(f"### {role.title}\n{summary}")
        console.print(Panel(Markdown(summary or "(no summary)"), title=f"{role.title} — summary", border_style="green"))

        if not gate or not config.APPROVAL_GATES:
            proj.set_phase(role_key, "done", summary)
            return True

        produced = (proj.root / role.output).exists()
        hint = "" if produced else f"  [yellow]({role.output} not written yet — agent may be waiting on your answers)[/yellow]"
        console.print(f"\n[bold]Approval gate: {role.title}[/bold]{hint}")
        choice = Prompt.ask(
            "  [a]pprove & continue  /  [f]eedback or answer  /  [s]top here",
            choices=["a", "f", "s"], default="a" if produced else "f",
        )
        if choice == "a":
            proj.set_phase(role_key, "done", summary)
            return True
        if choice == "s":
            proj.set_phase(role_key, "paused", summary)
            return False
        feedback = _multiline("  Your feedback / answers (empty line to send):")
        if not feedback:
            continue
        messages.append({"role": "user", "content": feedback})
        proj.log(f"### Owner feedback to {role.title}\n{feedback}")


def run_pipeline(proj: Project, start_at: str | None = None, only: str | None = None) -> None:
    phases = [only] if only else PIPELINE[PIPELINE.index(start_at) if start_at else 0:]
    for key in phases:
        if not run_phase(proj, key):
            console.print(f"[yellow]Stopped at '{key}'. Resume with:[/yellow] python main.py run {proj.name} --from {key}")
            return
    console.print(Panel(f"All phases complete for [bold]{proj.name}[/bold].\nFolder: {proj.root}", border_style="green"))


def qa_fix_loop(proj: Project, rounds: int = 2) -> None:
    """Developer fixes what QA reported, QA re-tests. Repeats `rounds` times."""
    for i in range(rounds):
        console.rule(f"Fix round {i + 1}/{rounds}")
        if not run_phase(proj, "developer", "Read docs/QA_REPORT.md and fix every bug it lists, then re-run the tests and update docs/DEV_NOTES.md."):
            return
        if not run_phase(proj, "qa", "Re-test the project after the developer's fixes and rewrite docs/QA_REPORT.md."):
            return
        report = proj.root / "docs" / "QA_REPORT.md"
        if report.exists() and "NOT READY" not in report.read_text(encoding="utf-8").upper():
            console.print("[green]QA verdict: READY[/green]")
            return


def _multiline(prompt: str) -> str:
    console.print(prompt)
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

"""Command line entry point.  Run `python main.py --help`."""
from __future__ import annotations

import argparse
import os
import sys
from pathlib import Path

from rich.console import Console
from rich.table import Table

from . import config
from .agents import PIPELINE, ROLES
from .pipeline import create_project, qa_fix_loop, resolve_project, run_phase, run_pipeline

console = Console()


def _require(name: str):
    proj = resolve_project(name)
    if not proj.exists():
        console.print(f"[red]No project '{name}'. Create it with:[/red] python main.py new {name} \"<brief>\"")
        sys.exit(1)
    return proj


def cmd_new(a):
    brief = Path(a.brief_file).read_text(encoding="utf-8") if a.brief_file else a.brief
    if not brief:
        brief = input("Describe what you want built:\n> ")
    proj = create_project(a.project, brief)
    console.print(f"[green]Project created:[/green] {proj.root}")
    if not a.no_run:
        run_pipeline(proj)


def cmd_run(a):
    run_pipeline(_require(a.project), start_at=a.start, only=a.only)


def cmd_ask(a):
    proj = _require(a.project)
    run_phase(proj, a.role, a.instruction)


def cmd_fix(a):
    qa_fix_loop(_require(a.project), rounds=a.rounds)


def cmd_status(a):
    if a.project:
        proj = _require(a.project)
        st = proj.load()
        t = Table(title=f"{st['name']}  ({proj.root})")
        t.add_column("Phase"); t.add_column("Status"); t.add_column("Updated")
        for k in PIPELINE:
            p = st["phases"].get(k, {})
            t.add_row(ROLES[k].title, p.get("status", "-"), p.get("at", ""))
        console.print(t)
        return
    t = Table(title=f"Projects in {config.WORKSPACE_DIR}")
    t.add_column("Project"); t.add_column("Progress")
    if config.WORKSPACE_DIR.exists():
        for d in sorted(config.WORKSPACE_DIR.iterdir()):
            proj = resolve_project(str(d))
            if proj.exists():
                ph = proj.load()["phases"]
                t.add_row(d.name, " ".join(f"{k}:{ph[k]['status']}" for k in PIPELINE if k in ph))
    console.print(t)


def main(argv=None):
    p = argparse.ArgumentParser(
        prog="agentic",
        description="Personal AI dev team: requirements -> development -> QA -> documentation.",
    )
    sub = p.add_subparsers(dest="cmd", required=True)

    s = sub.add_parser("new", help="Create a project from a brief and run the full pipeline")
    s.add_argument("project", help="Project name (created under workspace/) or a path to an existing folder")
    s.add_argument("brief", nargs="?", default="", help="What you want built")
    s.add_argument("--brief-file", help="Read the brief from a file instead")
    s.add_argument("--no-run", action="store_true", help="Only create the project")
    s.set_defaults(fn=cmd_new)

    s = sub.add_parser("run", help="Run / resume the pipeline")
    s.add_argument("project")
    s.add_argument("--from", dest="start", choices=PIPELINE, help="Start at this phase")
    s.add_argument("--only", choices=PIPELINE, help="Run just one phase")
    s.set_defaults(fn=cmd_run)

    s = sub.add_parser("ask", help="Give one agent an ad-hoc task (e.g. fix a bug, add a feature)")
    s.add_argument("role", choices=list(ROLES))
    s.add_argument("project")
    s.add_argument("instruction")
    s.set_defaults(fn=cmd_ask)

    s = sub.add_parser("fix", help="Developer fixes QA bugs, QA re-tests (repeat)")
    s.add_argument("project")
    s.add_argument("--rounds", type=int, default=2)
    s.set_defaults(fn=cmd_fix)

    s = sub.add_parser("status", help="Show all projects, or one project's phases")
    s.add_argument("project", nargs="?")
    s.set_defaults(fn=cmd_status)

    a = p.parse_args(argv)
    if a.cmd not in ("status",) and not (os.getenv("ANTHROPIC_API_KEY") or os.getenv("ANTHROPIC_AUTH_TOKEN")):
        console.print("[yellow]ANTHROPIC_API_KEY is not set. Add it to .env (see .env.example).[/yellow]")
    config.WORKSPACE_DIR.mkdir(parents=True, exist_ok=True)
    a.fn(a)

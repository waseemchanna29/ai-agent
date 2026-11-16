"""Tools the agents can call. Every path is confined to the project root, and
shell commands need your confirmation unless AGENTIC_COMMAND_MODE=auto."""
from __future__ import annotations

import fnmatch
import subprocess
from pathlib import Path
from typing import Any, Callable

from rich.console import Console
from rich.prompt import Confirm

from . import config

console = Console()

SKIP_DIRS = {".git", "node_modules", "__pycache__", ".venv", "venv", "vendor", ".agentic"}
MAX_READ_CHARS = 200_000


class ToolError(Exception):
    pass


def _safe_path(root: Path, rel: str) -> Path:
    """Resolve a model-supplied path and refuse anything outside the project."""
    p = (root / rel).resolve()
    if p != root and root not in p.parents:
        raise ToolError(f"Path '{rel}' is outside the project folder.")
    return p


def _str(inp: dict, key: str, required: bool = True, default: str = "") -> str:
    val = inp.get(key, default)
    if required and key not in inp:
        raise ToolError(f"Missing required field '{key}'.")
    if not isinstance(val, str):
        raise ToolError(f"Field '{key}' must be a string.")
    return val


# ---------------------------------------------------------------- tool impls
def list_files(root: Path, inp: dict) -> str:
    base = _safe_path(root, _str(inp, "path", required=False, default="."))
    if not base.exists():
        return "(folder does not exist yet)"
    out = []
    for p in sorted(base.rglob("*")):
        if any(part in SKIP_DIRS for part in p.relative_to(root).parts):
            continue
        rel = p.relative_to(root).as_posix()
        out.append(rel + ("/" if p.is_dir() else ""))
        if len(out) >= 500:
            out.append("... (truncated at 500 entries)")
            break
    return "\n".join(out) or "(empty)"


def read_file(root: Path, inp: dict) -> str:
    p = _safe_path(root, _str(inp, "path"))
    if not p.is_file():
        raise ToolError(f"File not found: {inp['path']}")
    text = p.read_text(encoding="utf-8", errors="replace")
    if len(text) > MAX_READ_CHARS:
        raise ToolError(
            f"File is {len(text)} chars; too large to read whole. Use search_files to find the part you need."
        )
    return text


def write_file(root: Path, inp: dict) -> str:
    p = _safe_path(root, _str(inp, "path"))
    content = _str(inp, "content")
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(content, encoding="utf-8")
    console.print(f"  [green]wrote[/green] {p.relative_to(root).as_posix()} ({len(content)} chars)")
    return f"Wrote {len(content)} chars to {inp['path']}"


def edit_file(root: Path, inp: dict) -> str:
    p = _safe_path(root, _str(inp, "path"))
    old, new = _str(inp, "old_text"), _str(inp, "new_text")
    if not p.is_file():
        raise ToolError(f"File not found: {inp['path']}")
    text = p.read_text(encoding="utf-8")
    count = text.count(old)
    if count == 0:
        raise ToolError("old_text not found in file. Read the file again and copy the exact text.")
    if count > 1:
        raise ToolError(f"old_text appears {count} times; include more surrounding lines so it is unique.")
    p.write_text(text.replace(old, new, 1), encoding="utf-8")
    console.print(f"  [yellow]edited[/yellow] {p.relative_to(root).as_posix()}")
    return f"Edited {inp['path']}"


def search_files(root: Path, inp: dict) -> str:
    needle = _str(inp, "query").lower()
    pattern = _str(inp, "glob", required=False, default="*")
    hits = []
    for p in root.rglob("*"):
        if not p.is_file() or any(part in SKIP_DIRS for part in p.relative_to(root).parts):
            continue
        if not fnmatch.fnmatch(p.name, pattern):
            continue
        try:
            for i, line in enumerate(p.read_text(encoding="utf-8", errors="ignore").splitlines(), 1):
                if needle in line.lower():
                    hits.append(f"{p.relative_to(root).as_posix()}:{i}: {line.strip()[:200]}")
                    if len(hits) >= 200:
                        return "\n".join(hits) + "\n... (truncated)"
        except OSError:
            continue
    return "\n".join(hits) or "No matches."


def run_command(root: Path, inp: dict) -> str:
    cmd = _str(inp, "command")
    reason = _str(inp, "reason", required=False)
    console.print(f"\n  [bold magenta]$ {cmd}[/bold magenta]  [dim]{reason}[/dim]")
    if config.COMMAND_MODE != "auto" and not Confirm.ask("  Run this command?", default=True):
        return "User declined to run this command. Continue without it or suggest an alternative."
    try:
        r = subprocess.run(
            cmd, shell=True, cwd=root, capture_output=True, text=True,
            timeout=config.COMMAND_TIMEOUT, encoding="utf-8", errors="replace",
        )
    except subprocess.TimeoutExpired:
        raise ToolError(f"Command timed out after {config.COMMAND_TIMEOUT}s.")
    out = (r.stdout or "") + (("\n[stderr]\n" + r.stderr) if r.stderr else "")
    if len(out) > 30_000:
        out = out[:15_000] + "\n... (output truncated) ...\n" + out[-15_000:]
    console.print(f"  [dim]exit code {r.returncode}[/dim]")
    return f"exit code: {r.returncode}\n{out}"


# ---------------------------------------------------------------- schemas
def _schema(props: dict, required: list[str]) -> dict:
    return {"type": "object", "properties": props, "required": required, "additionalProperties": False}


_S = {"type": "string"}

TOOL_DEFS: dict[str, tuple[dict, Callable[[Path, dict], str]]] = {
    "list_files": ({
        "name": "list_files",
        "description": "List files and folders (recursively) inside the project. Path is relative to the project root.",
        "input_schema": _schema({"path": {**_S, "description": "Folder to list, default '.'"}}, []),
    }, list_files),
    "read_file": ({
        "name": "read_file",
        "description": "Read a UTF-8 text file from the project.",
        "input_schema": _schema({"path": _S}, ["path"]),
    }, read_file),
    "write_file": ({
        "name": "write_file",
        "description": "Create or fully overwrite a file in the project. Parent folders are created automatically.",
        "input_schema": _schema({"path": _S, "content": _S}, ["path", "content"]),
    }, write_file),
    "edit_file": ({
        "name": "edit_file",
        "description": "Replace one exact, unique snippet in an existing file. Prefer this over write_file for small changes.",
        "input_schema": _schema({"path": _S, "old_text": _S, "new_text": _S}, ["path", "old_text", "new_text"]),
    }, edit_file),
    "search_files": ({
        "name": "search_files",
        "description": "Case-insensitive text search across project files. Optional glob filters file names, e.g. '*.py'.",
        "input_schema": _schema({"query": _S, "glob": _S}, ["query"]),
    }, search_files),
    "run_command": ({
        "name": "run_command",
        "description": "Run a shell command in the project root (install deps, run tests, linters, build). "
                       "The user may decline. Avoid long-running servers; they time out.",
        "input_schema": _schema({"command": _S, "reason": {**_S, "description": "One line on why"}}, ["command"]),
    }, run_command),
}


def tool_schemas(names: list[str]) -> list[dict]:
    # Stable order keeps the prompt cache prefix identical between calls.
    # eager_input_streaming streams big file contents as they are generated;
    # we validate inputs ourselves in each tool (see _str).
    return [{**TOOL_DEFS[n][0], "eager_input_streaming": True} for n in names]


def execute(root: Path, name: str, inp: Any) -> tuple[str, bool]:
    """Run a tool. Returns (result_text, is_error)."""
    if name not in TOOL_DEFS:
        return f"Unknown tool '{name}'.", True
    if not isinstance(inp, dict):
        return "Tool input must be a JSON object.", True
    try:
        return TOOL_DEFS[name][1](root, inp), False
    except ToolError as e:
        return f"Error: {e}", True
    except Exception as e:  # keep the loop alive; let the model recover
        return f"Error: {type(e).__name__}: {e}", True

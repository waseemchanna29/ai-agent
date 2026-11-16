"""The agent loop: send messages to Claude, run the tools it asks for, repeat
until it finishes. One function, used by every role agent."""
from __future__ import annotations

from pathlib import Path

import anthropic
from rich.console import Console

from . import config, tools

console = Console()
_client: anthropic.Anthropic | None = None


def client() -> anthropic.Anthropic:
    global _client
    if _client is None:
        # Reads ANTHROPIC_API_KEY (or an `ant auth login` profile) from the environment.
        _client = anthropic.Anthropic()
    return _client


class AgentStopped(Exception):
    """Raised when the model refuses or the loop cannot continue."""


def run_agent(
    *,
    system: str,
    messages: list,
    tool_names: list[str],
    project_root: Path,
    label: str,
) -> str:
    """Run the tool loop until Claude ends its turn. `messages` is mutated in
    place (full assistant content is appended, so history stays valid for the
    next call). Returns the final text the agent wrote."""
    tool_defs = tools.tool_schemas(tool_names)
    json_retries = 0

    for turn in range(config.MAX_TURNS):
        console.print(f"\n[bold cyan]── {label} (step {turn + 1}) ──[/bold cyan]")
        try:
            with client().beta.messages.stream(
                model=config.MODEL,
                max_tokens=config.MAX_TOKENS,
                system=system,
                tools=tool_defs,
                messages=messages,
                thinking={"type": "adaptive", "display": "summarized"},
                output_config={"effort": config.EFFORT},
                cache_control={"type": "ephemeral"},
                # If a safety classifier declines, the API retries on a fallback model.
                betas=["server-side-fallback-2026-07-01"],
                fallbacks="default",
            ) as stream:
                for event in stream:
                    if event.type == "text":
                        console.print(event.text, end="", markup=False, highlight=False)
                response = stream.get_final_message()
            json_retries = 0
        except ValueError:
            # Tool input JSON that could not be parsed at all; re-issue the turn.
            json_retries += 1
            if json_retries > 2:
                raise AgentStopped("Model produced unparseable tool input three times in a row.")
            console.print("[red]Malformed tool input, retrying turn...[/red]")
            continue

        console.print()
        messages.append({"role": "assistant", "content": response.content})
        _log_usage(response)

        if response.stop_reason == "refusal":
            detail = getattr(response, "stop_details", None)
            raise AgentStopped(f"Request declined: {getattr(detail, 'explanation', '') or 'no detail'}")

        if response.stop_reason == "pause_turn":
            continue

        tool_uses = [b for b in response.content if b.type == "tool_use"]

        if response.stop_reason == "max_tokens":
            if tool_uses:
                # The tool input may be truncated - don't execute it.
                messages.append({"role": "user", "content": [
                    {"type": "tool_result", "tool_use_id": t.id, "is_error": True,
                     "content": "Output hit max_tokens; this call was truncated and NOT run. "
                                "Split large files into smaller writes."}
                    for t in tool_uses
                ]})
                continue
            messages.append({"role": "user", "content": "Continue exactly where you stopped."})
            continue

        if not tool_uses:
            return _final_text(response)

        # Execute every requested tool, then return ALL results in one user message.
        results = []
        for t in tool_uses:
            console.print(f"  [blue]→ {t.name}[/blue] {_short(t.input)}")
            out, is_err = tools.execute(project_root, t.name, t.input)
            if is_err:
                console.print(f"  [red]{out[:300]}[/red]")
            results.append({"type": "tool_result", "tool_use_id": t.id, "content": out, "is_error": is_err})
        messages.append({"role": "user", "content": results})

    raise AgentStopped(f"Reached MAX_TURNS={config.MAX_TURNS} without finishing.")


def _final_text(response) -> str:
    return "\n".join(b.text for b in response.content if b.type == "text").strip()


def _short(inp) -> str:
    if isinstance(inp, dict):
        return ", ".join(f"{k}={str(v)[:60]!r}" for k, v in inp.items() if k != "content")
    return str(inp)[:80]


def _log_usage(response) -> None:
    u = response.usage
    console.print(
        f"[dim]tokens in={u.input_tokens} out={u.output_tokens} "
        f"cache_read={getattr(u, 'cache_read_input_tokens', 0) or 0} model={response.model}[/dim]"
    )

"""Central configuration. Everything can be overridden from .env so the same
code runs on your laptop and later on a server."""
from __future__ import annotations

import os
from pathlib import Path

from dotenv import load_dotenv

BASE_DIR = Path(__file__).resolve().parent.parent
load_dotenv(BASE_DIR / ".env")

# Model + effort. Opus 5.5 defaults to "medium" effort, so set it explicitly.
MODEL = os.getenv("AGENTIC_MODEL", "claude-opus-5-5")
EFFORT = os.getenv("AGENTIC_EFFORT", "high")  # low | medium | high | xhigh | max
MAX_TOKENS = int(os.getenv("AGENTIC_MAX_TOKENS", "64000"))
MAX_TURNS = int(os.getenv("AGENTIC_MAX_TURNS", "60"))  # tool-loop safety cap per phase

# Where every project lives. Agents can only touch files inside a project folder.
WORKSPACE_DIR = Path(os.getenv("AGENTIC_WORKSPACE", BASE_DIR / "workspace")).resolve()

# Command safety: "ask" = confirm every shell command, "auto" = run without asking
# (only use "auto" inside a disposable container on the server).
COMMAND_MODE = os.getenv("AGENTIC_COMMAND_MODE", "ask")
COMMAND_TIMEOUT = int(os.getenv("AGENTIC_COMMAND_TIMEOUT", "300"))

# Approval gates between phases. "true" = pause for your OK after each phase.
APPROVAL_GATES = os.getenv("AGENTIC_APPROVAL_GATES", "true").lower() == "true"

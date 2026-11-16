import sys

from agentic.cli import main

if __name__ == "__main__":
    # Windows consoles default to cp1252; force UTF-8 so Urdu/emoji output works.
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8")
    main()

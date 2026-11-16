"""Markdown → professional PDF (cover page, page numbers, tables, mermaid diagrams,
version history at the end). Uses the Microsoft Edge / Chrome already on this PC in headless mode.

    python pdf_export.py <file.md> [out_dir]
Used by team.py after the requirements and docs phases, and by `python team.py pdf <project>`.
"""
from __future__ import annotations

import html
import re
import shutil
import subprocess
import sys
import tempfile
from datetime import date
from pathlib import Path

import markdown

HISTORY_HEADING = "Document Version History"
BROWSERS = [
    Path(r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"),
    Path(r"C:\Program Files\Microsoft\Edge\Application\msedge.exe"),
    Path(r"C:\Program Files\Google\Chrome\Application\chrome.exe"),
]
MERMAID_JS = "https://cdn.jsdelivr.net/npm/mermaid@10.9.1/dist/mermaid.min.js"

CSS = """
@page { size: A4; margin: 22mm 18mm 20mm 18mm;
  @top-left { content: var(--doc); font: 8pt 'Segoe UI'; color: #888; }
  @top-right { content: var(--ver); font: 8pt 'Segoe UI'; color: #888; }
  @bottom-center { content: "Page " counter(page) " of " counter(pages); font: 8pt 'Segoe UI'; color: #888; } }
@page :first { @top-left { content: none } @top-right { content: none } @bottom-center { content: none } }
* { box-sizing: border-box; }
body { font: 10.5pt/1.55 'Segoe UI', Arial, sans-serif; color: #1f2933; margin: 0; }
.cover { height: 247mm; display: flex; flex-direction: column; justify-content: center;
  border-left: 8px solid #1d4ed8; padding-left: 14mm; page-break-after: always; }
.cover .project { font-size: 13pt; color: #1d4ed8; letter-spacing: .08em; text-transform: uppercase; }
.cover h1 { font-size: 30pt; margin: 6mm 0 10mm; border: 0; color: #0f172a; }
.cover table { border: 0; width: auto; } .cover td { border: 0; padding: 1.5mm 6mm 1.5mm 0; background: none !important; }
.cover td:first-child { color: #64748b; }
h1 { font-size: 20pt; color: #0f172a; border-bottom: 2px solid #1d4ed8; padding-bottom: 2mm; }
h2 { font-size: 14.5pt; color: #1d4ed8; margin-top: 9mm; page-break-after: avoid; }
h3 { font-size: 12pt; color: #0f172a; page-break-after: avoid; }
table { border-collapse: collapse; width: 100%; margin: 4mm 0; font-size: 9.5pt; page-break-inside: auto; }
th { background: #1d4ed8; color: #fff; text-align: left; }
th, td { border: 1px solid #cbd5e1; padding: 2mm 3mm; vertical-align: top; }
tr { page-break-inside: avoid; } tr:nth-child(even) td { background: #f1f5f9; }
code { font: 9pt Consolas, monospace; background: #f1f5f9; padding: 0 1mm; border-radius: 2px; }
pre { background: #0f172a; color: #e2e8f0; padding: 4mm; border-radius: 4px; overflow: hidden;
  white-space: pre-wrap; word-break: break-word; page-break-inside: avoid; }
pre code { background: none; color: inherit; padding: 0; }
pre.mermaid { background: none; color: inherit; text-align: center; }
blockquote { border-left: 4px solid #93c5fd; margin: 4mm 0; padding: 1mm 4mm; color: #475569; background: #eff6ff; }
.history { page-break-before: always; }
.history td:nth-child(-n+3), .history th:nth-child(-n+3) { white-space: nowrap; }
"""


def doc_title(md: str, fallback: str) -> str:
    m = re.search(r"^#\s+(.+)$", md, re.M)
    return m.group(1).strip() if m else fallback


def split_history(md: str) -> tuple[str, str | None]:
    """(body, history_section_markdown or None)"""
    m = re.search(rf"^##\s+{HISTORY_HEADING}\s*$", md, re.M | re.I)
    if not m:
        return md, None
    return md[:m.start()].rstrip(), md[m.start():]


def latest_version(md: str, history: str | None) -> int:
    nums = []
    if history:
        nums = [int(n) for n in re.findall(r"^\|\s*(?:v(?:ersion)?\s*)?(\d+)\s*\|", history, re.M | re.I)]
    if not nums:
        m = re.search(r"^Version:\s*(\d+)", md, re.M)
        nums = [int(m.group(1))] if m else [1]
    return max(nums)


def history_from_changelog(changelog: str, author: str) -> str | None:
    """Build a version-history table from a requirements CHANGELOG.md ('## vN — date' entries)."""
    rows = []
    for m in re.finditer(r"^##\s*v(\d+)\s*[—–-]\s*(\S+)\s*\n(.*?)(?=^##\s*v\d+|\Z)", changelog, re.M | re.S):
        reason, items = "", []
        for ln in (x.strip(" -*") for x in m.group(3).splitlines()):
            if not ln:
                continue
            if ln.lower().startswith("impact"):
                break  # impact notes are internal; stop here
            r = re.search(r"reason:\s*(.+)", ln, re.I)
            if r:
                reason = r.group(1).strip().rstrip(".")
            elif re.match(r"(added|changed|removed)\s*:", ln, re.I):
                items.append(re.sub(r"\s+", " ", ln))
        text = (reason + (". " if reason and items else "") + "; ".join(items)).replace("|", "/")
        if len(text) > 450:
            text = text[:447].rsplit(" ", 1)[0] + " …"
        rows.append((int(m.group(1)), m.group(2), text or "See CHANGELOG"))
    if not rows:
        return None
    rows.sort()
    body = "\n".join(f"| {v} | {d} | {author} | {t} |" for v, d, t in rows)
    return f"## {HISTORY_HEADING}\n\n| Version | Date | Author | Description of changes |\n|---|---|---|---|\n{body}\n"


def normalize(md: str) -> str:
    """Python-Markdown needs a blank line before tables and lists; agents often skip it.
    Also splits inline ' - item - item' glossary-style lists onto their own lines."""
    out, in_code = [], False
    for line in md.splitlines():
        s = line.lstrip()
        if s.startswith("```"):
            in_code = not in_code
        prev = out[-1].strip() if out else ""
        if not in_code and prev:
            starts_table = s.startswith("|") and not prev.startswith("|")
            starts_list = (re.match(r"([-*+]|\d+\.)\s", s) and not re.match(r"([-*+]|\d+\.)\s", prev)
                           and not prev.startswith("#"))
            if starts_table or starts_list:
                out.append("")
        out.append(line)
    return "\n".join(out) + "\n"


def build_html(md_text: str, project: str, prepared_by: str, stem: str,
               changelog: str | None = None) -> tuple[str, int]:
    md_text = normalize(md_text)
    body_md, history = split_history(md_text)
    if history is None and changelog:
        history = history_from_changelog(changelog, prepared_by.split(" (")[0])
    version = latest_version(md_text, history)
    title = doc_title(body_md, stem)
    if history is None:  # every PDF ends with a version history, even if the author forgot it
        history = (f"## {HISTORY_HEADING}\n\n| Version | Date | Author | Description of changes |\n"
                   f"|---|---|---|---|\n| {version} | {date.today():%Y-%m-%d} | {prepared_by} | Initial release |\n")
    # drop the top "# Title" (it's on the cover) and turn ```mermaid blocks into diagrams
    body_md = re.sub(r"^#\s+.+\n", "", body_md, count=1)
    has_mermaid = "```mermaid" in body_md
    body_md = re.sub(r"```mermaid\n(.*?)```",
                     lambda m: f'<pre class="mermaid">{html.escape(m.group(1))}</pre>', body_md, flags=re.S)
    exts = ["tables", "fenced_code", "sane_lists", "attr_list"]
    body = markdown.markdown(body_md, extensions=exts)
    hist = markdown.markdown(history, extensions=exts)
    cover = f"""<section class="cover"><div class="project">{html.escape(project)}</div>
<h1>{html.escape(title)}</h1><table>
<tr><td>Version</td><td><b>{version}</b></td></tr>
<tr><td>Date</td><td>{date.today():%d %B %Y}</td></tr>
<tr><td>Prepared by</td><td>{html.escape(prepared_by)}</td></tr></table></section>"""
    script = (f'<script src="{MERMAID_JS}"></script>'
              '<script>mermaid.initialize({startOnLoad:true, theme:"neutral"});</script>') if has_mermaid else ""
    doc = f"""<!doctype html><html><head><meta charset="utf-8"><title>{html.escape(title)}</title>
<style>{CSS}</style></head><body>{cover}{body}<section class="history">{hist}</section>{script}</body></html>"""
    # CSS custom properties are not allowed in @page content; inline the strings instead
    header = title if project.lower() in title.lower() else f"{project} — {title}"
    doc = doc.replace("content: var(--doc)", f'content: "{_css_str(header)}"')
    doc = doc.replace("content: var(--ver)", f'content: "Version {version}"')
    return doc, version


def _css_str(s: str) -> str:
    return s.replace("\\", "\\\\").replace('"', '\\"')


def browser() -> Path:
    for b in BROWSERS:
        if b.exists():
            return b
    raise FileNotFoundError("Microsoft Edge or Google Chrome is needed to create PDFs.")


def to_pdf(md_file: Path, out_dir: Path, project: str, prepared_by: str) -> Path:
    md_file, out_dir = md_file.resolve(), out_dir.resolve()
    out_dir.mkdir(parents=True, exist_ok=True)
    changelog = md_file.parent / "CHANGELOG.md"
    doc, version = build_html(md_file.read_text(encoding="utf-8"), project, prepared_by, md_file.stem,
                              changelog.read_text(encoding="utf-8")
                              if md_file.stem == "REQUIREMENTS" and changelog.exists() else None)
    pdf = out_dir / f"{md_file.stem}.pdf"
    with tempfile.TemporaryDirectory() as tmp:
        page = Path(tmp) / "doc.html"
        page.write_text(doc, encoding="utf-8")
        subprocess.run([str(browser()), "--headless", "--disable-gpu", "--no-pdf-header-footer",
                        f"--user-data-dir={Path(tmp) / 'profile'}", "--virtual-time-budget=15000",
                        f"--print-to-pdf={pdf}", page.as_uri()],
                       check=True, capture_output=True, timeout=120)
    archive = out_dir / "archive"
    archive.mkdir(exist_ok=True)
    shutil.copy2(pdf, archive / f"{md_file.stem}-v{version}.pdf")
    return pdf


if __name__ == "__main__":
    src = Path(sys.argv[1])
    out = Path(sys.argv[2]) if len(sys.argv) > 2 else src.parent / "pdf"
    print(to_pdf(src, out, project=src.parent.name, prepared_by="Agent Team"))

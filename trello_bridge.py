"""Trello ⇄ agent team bridge, driven by @mentions of a dedicated Trello agent account.
Does not change the agents or team.py — it calls team.py's functions. Setup: TRELLO.md.

How it works
- TRELLO_TOKEN belongs to the *agent account* (e.g. @aiagent). Add that account to each board.
- Mention the agent in a comment, or in the card description, and say what you want:
      @aiagent is card ki requirements likho
      @aiagent dev karo phir qa karo
      @aiagent docs update karo
      @aiagent undo
- The bridge reads the whole card (title, description, checklists, attachments, comments),
  decides which agent(s) to run from your words, runs them, and replies on the card,
  @mentioning you. Mention it again about the same work = feedback; the agent continues its session.

Commands:
    python trello_bridge.py whoami                       which Trello account the token belongs to
    python trello_bridge.py boards                       boards the agent account can see
    python trello_bridge.py link <project> <board-url>   connect a board to a project (registers webhook)
    python trello_bridge.py webhook <project>            re-register after the ngrok URL changed
    python trello_bridge.py unlink <project>             disconnect
    python trello_bridge.py serve                        run the webhook server (keep it running)
"""
from __future__ import annotations

import base64
import hashlib
import hmac
import json
import os
import queue
import re
import shutil
import subprocess
import sys
import threading
import time
import traceback
import urllib.error
import urllib.parse
import urllib.request
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

import team  # loads .env and provides run_claude, export_pdfs, project helpers …

ROOT = Path(__file__).resolve().parent
STATE_FILE = ROOT / "trello" / "state.json"
API = "https://api.trello.com/1"
MAX_COMMENT = 15000  # Trello limit is 16384

KEY = os.getenv("TRELLO_KEY", "")
TOKEN = os.getenv("TRELLO_TOKEN", "")            # token of the AGENT account
SECRET = os.getenv("TRELLO_SECRET", "")          # Power-Up secret, verifies webhook signatures
CALLBACK = os.getenv("TRELLO_CALLBACK_URL", "")  # e.g. https://your-name.ngrok-free.app/trello
PORT = int(os.getenv("TRELLO_BRIDGE_PORT", "8787"))
ROUTER_MODEL = os.getenv("TRELLO_ROUTER_MODEL", "haiku")  # only used when the words are unclear
# When QA (started from Trello) finds bugs, one Trello card is created per bug. The developer does NOT
# start on its own: the owner mentions the agent with "dev" on a bug card to get it fixed.
# (TRELLO_AUTO_FIX_BUGS=true would start the developer automatically — off by the owner's choice.)
AUTO_FIX = os.getenv("TRELLO_AUTO_FIX_BUGS", "false").lower() == "true"
# When QA finds no bugs, move the card to the board's "Done" list.
QA_PASS_TO_DONE = os.getenv("TRELLO_QA_PASS_TO_DONE", "false").lower() == "true"
DONE_LIST = os.getenv("TRELLO_DONE_LIST", "Done")
# When the developer starts on a card, move it to the board's "Doing" list.
DEV_TO_DOING = os.getenv("TRELLO_DEV_TO_DOING", "false").lower() == "true"
DOING_LIST = os.getenv("TRELLO_DOING_LIST", "Doing")

say = team.say
_lock = threading.Lock()

# Words that pick an agent (English + Roman Urdu/Hindi). Order in the comment = run order.
ROLE_WORDS = {
    "requirements": r"requirements?|requirment|srs|brd|spec(?:ification)?|scope|user stor(?:y|ies)|analy[sz]",
    "development": r"dev(?:elop(?:ment|er)?)?|developer|implement(?:ation)?|code|coding|fix|bug ?fix",
    "qa": r"qa|test(?:ing|s)?|verify|check karo|regression",
    "docs": r"docs?|documentation|user ?guide|readme|manual",
}
# Only an explicit undo command (first word) restores code — never a word in the middle of a sentence.
UNDO = re.compile(r"^\s*(undo|revert|rollback)\b", re.I)


# ------------------------------------------------------------------ state
def load_state() -> dict:
    if STATE_FILE.exists():
        return json.loads(STATE_FILE.read_text(encoding="utf-8"))
    return {"me": None, "boards": {}, "cards": {}, "seen": []}


def save_state(st: dict) -> None:
    STATE_FILE.parent.mkdir(parents=True, exist_ok=True)
    st["seen"] = st["seen"][-1000:]
    STATE_FILE.write_text(json.dumps(st, indent=2, ensure_ascii=False), encoding="utf-8")


# ------------------------------------------------------------------ Trello REST
def api(method: str, path: str, **params) -> dict | list:
    """Trello call. Credentials go in the Authorization header, never in the URL."""
    if not (KEY and TOKEN):
        sys.exit("TRELLO_KEY and TRELLO_TOKEN must be set in .env (see TRELLO.md).")
    url, data = f"{API}{path}", None
    if method == "GET":
        if params:
            url += "?" + urllib.parse.urlencode(params)
    else:
        data = urllib.parse.urlencode(params).encode()
    req = urllib.request.Request(url, data=data, method=method, headers={
        "Authorization": f'OAuth oauth_consumer_key="{KEY}", oauth_token="{TOKEN}"',
        "Accept": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            body = r.read().decode()
            return json.loads(body) if body else {}
    except urllib.error.HTTPError as e:
        raise RuntimeError(f"Trello {method} {path} → {e.code}: {e.read().decode()[:300]}") from None


def me() -> dict:
    """The agent account (id + username), cached."""
    st = load_state()
    if not st.get("me"):
        m = api("GET", "/members/me", fields="id,username,fullName")
        st["me"] = {"id": m["id"], "username": m["username"], "fullName": m.get("fullName", "")}
        save_state(st)
    return st["me"]


def comment(card_id: str, text: str, to: str | None = None) -> None:
    text = (f"@{to} " if to else "") + text
    if len(text) > MAX_COMMENT:
        text = text[:MAX_COMMENT - 60] + "\n\n… (truncated — full output in the bridge log)"
    api("POST", f"/cards/{card_id}/actions/comments", text=text)


def board_id_from(ref: str) -> str:
    m = re.search(r"trello\.com/b/([A-Za-z0-9]+)", ref)
    return api("GET", f"/boards/{m.group(1) if m else ref}", fields="id")["id"]


# ------------------------------------------------------------------ setup commands
def cmd_whoami() -> None:
    st = load_state()
    st["me"] = None
    save_state(st)
    m = me()
    say(f"Agent account: @{m['username']} ({m['fullName']}). Mention this name on cards.", "green")


def cmd_boards() -> None:
    for b in api("GET", "/members/me/boards", fields="name,url,closed"):
        if not b.get("closed"):
            print(f"{b['name']:<40} {b['url']}")


def register_webhook(board_id: str, project: str) -> str:
    if not CALLBACK.startswith("https://"):
        sys.exit("Set TRELLO_CALLBACK_URL in .env to your public https URL ending in /trello, and start "
                 "`python trello_bridge.py serve` + ngrok first (Trello checks the URL when registering).")
    return api("POST", "/webhooks", callbackURL=CALLBACK, idModel=board_id,
               description=f"agent-team:{project}")["id"]


def delete_webhook(wid: str | None) -> None:
    if wid:
        try:
            api("DELETE", f"/webhooks/{wid}")
        except RuntimeError as e:
            say(f"  (old webhook not removed: {e})", "dim")


def cmd_link(project: str, board_ref: str) -> None:
    proj = team.need(project)
    m = me()
    bid = board_id_from(board_ref)
    st = load_state()
    delete_webhook(st["boards"].get(bid, {}).get("webhook_id"))
    wid = register_webhook(bid, proj.name)
    st = load_state()
    st["boards"][bid] = {"project": proj.name, "webhook_id": wid}
    save_state(st)
    say(f"Board linked to project '{proj.name}'. Mention @{m['username']} on any card to start.", "green")


def find_board(project: str) -> tuple[str, dict]:
    for bid, b in load_state()["boards"].items():
        if b["project"] == project:
            return bid, b
    sys.exit(f"Project '{project}' is not linked. Run: python trello_bridge.py link {project} <board-url>")


def cmd_webhook(project: str) -> None:
    bid, b = find_board(project)
    delete_webhook(b.get("webhook_id"))
    wid = register_webhook(bid, project)
    st = load_state()
    st["boards"][bid]["webhook_id"] = wid
    save_state(st)
    say(f"Webhook re-registered → {CALLBACK}", "green")


def cmd_unlink(project: str) -> None:
    bid, b = find_board(project)
    delete_webhook(b.get("webhook_id"))
    st = load_state()
    st["boards"].pop(bid, None)
    save_state(st)
    say(f"Unlinked '{project}'.", "green")


# ------------------------------------------------------------------ deciding which agent(s)
def roles_from_words(text: str) -> list[str]:
    """Agents named in the text, in the order they appear."""
    hits = []
    for role, pat in ROLE_WORDS.items():
        m = re.search(rf"(?<![a-z])(?:{pat})(?![a-z])", text, re.I)
        if m:
            hits.append((m.start(), role))
    return [r for _, r in sorted(hits)]


def roles_from_model(text: str, card_text: str) -> list[str]:
    """Ask a small model when the words don't name an agent. Returns [] if still unclear."""
    prompt = (
        "A software owner tagged their AI team on a Trello card. Decide which team member(s) should act, "
        "in order. Members: requirements (BRD/SRS, scope, user stories), development (write or fix code), "
        "qa (test, verify, find bugs), docs (user guide, API docs, PDFs). Reply with ONLY a comma-separated "
        "list of member names in order, or the word unclear.\n\n"
        f"Owner's message:\n{text}\n\nCard:\n{card_text[:3000]}"
    )
    cmd = [shutil.which("claude") or "claude", "-p", prompt, "--model", ROUTER_MODEL,
           "--output-format", "json", "--tools", ""]
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", timeout=120,
                           stdin=subprocess.DEVNULL, env=team.child_env())
        answer = json.loads(r.stdout).get("result", "").lower()
    except Exception as e:
        say(f"  router failed: {e}", "yellow")
        return []
    return [p.strip() for p in answer.split(",") if p.strip() in team.PIPELINE]


# ------------------------------------------------------------------ jobs
jobs: "queue.Queue[dict]" = queue.Queue()

ROLE_TASK = {
    "requirements": ("Update the BRD / SRS for this card. If REQUIREMENTS.md exists, follow 'Every change "
                     "after v1' (new version + CHANGELOG + Document Version History). If information is "
                     "missing, ask your questions in your final answer — the owner replies on the card."),
    "development": ("Implement / fix what this card asks, following the latest REQUIREMENTS.md and the "
                    "project rules. Run the relevant tests. Update DEV_NOTES.md and write a handoff to QA."),
    "qa": ("Test what this card asks against the acceptance criteria and run a regression check of existing "
           "tests. Write the QA report (READY / NOT READY, bugs with file:line). Do not change application code."),
    "docs": ("Update the documentation affected by this card and add one row to each changed document's "
             "Document Version History."),
}


def bugs_file(proj) -> Path:
    return proj.agent_dir("qa") / "trello-bugs.json"


def bug_list_instruction(proj) -> str:
    return (f"\n\nAfter the QA report, you MUST write the file {bugs_file(proj)} in THIS run — the orchestrator "
            "deleted it before this run, so write it again even if you wrote it in an earlier run. Content: a "
            "JSON array of the OPEN bugs found now, or exactly [] if there are none. Each bug: {\"id\": "
            "\"BUG-1\", \"title\": \"short title\", \"severity\": \"high|medium|low\", \"file\": \"path:line\", "
            "\"details\": \"steps to reproduce, expected vs actual\"}. Each bug becomes a Trello card.")


def qa_passed(proj, summary: str, since: float) -> bool:
    """Fallback when QA didn't write the bug file: use this run's QA report verdict, then its summary."""
    report = proj.agent_dir("qa") / "QA_REPORT.md"
    if report.exists() and report.stat().st_mtime >= since:
        for line in report.read_text(encoding="utf-8", errors="ignore").splitlines():
            if "verdict" in line.lower():
                up = line.upper()
                if "NOT READY" in up:
                    return False
                if "READY" in up:
                    return True
    s = summary.lower()
    if re.search(r"not ready|\bbug-\d+\b.*\b(open|fail)", s):
        return False
    return bool(re.search(r"no (open |new )?bugs|0 (open )?bugs|\bready\b", s))


def bug_label(board_id: str) -> str | None:
    st = load_state()
    b = st["boards"].get(board_id, {})
    if b.get("bug_label"):
        return b["bug_label"]
    try:
        lid = next((l["id"] for l in api("GET", f"/boards/{board_id}/labels", fields="name")
                    if (l.get("name") or "").strip().lower() == "bug"), None)
        lid = lid or api("POST", "/labels", name="Bug", color="red", idBoard=board_id)["id"]
    except RuntimeError as e:
        say(f"  could not get/create Bug label: {e}", "yellow")
        return None
    if board_id in st["boards"]:
        st["boards"][board_id]["bug_label"] = lid
        save_state(st)
    return lid


def file_bug_cards(proj, card: dict, who: str) -> list[str]:
    """Turn QA's bug list into Trello cards (one per new bug) and queue the developer on each."""
    f = bugs_file(proj)
    if not f.exists():
        return []
    try:
        bugs = json.loads(f.read_text(encoding="utf-8"))
        assert isinstance(bugs, list)
    except Exception as e:
        say(f"  {f.name} is not a valid JSON list: {e}", "yellow")
        return []
    st = load_state()
    known = st.setdefault("bugs", {}).setdefault(proj.name, {})
    label, lines, me_name = bug_label(card["idBoard"]), [], me()["username"]
    for bug in bugs:
        if not isinstance(bug, dict) or not bug.get("title"):
            continue
        bid, title = str(bug.get("id") or "BUG").strip(), str(bug["title"]).strip()[:150]
        key = re.sub(r"\W+", " ", title.lower()).strip()
        sev = str(bug.get("severity") or "").strip().lower()
        head = f"- {bid}" + (f" ({sev})" if sev else "") + f": {title}"
        if key in known:  # same bug reported again by a re-test
            lines.append(f"{head} → {known[key]['url']} (still open)")
            continue
        desc = (f"**Found by QA** on card [{card['name']}]({card['url']}) — severity: "
                f"{bug.get('severity', 'n/a')}\n\n{bug.get('details', '')}\n\n"
                f"Location: `{bug.get('file', 'n/a')}`\n\n"
                f"To fix: comment `@{me_name} dev fix karo` on this card. To re-test: `@{me_name} qa`.")
        params = {"idList": card["idList"], "name": f"[{bid}] {title}", "desc": desc[:16000], "pos": "top"}
        if label:
            params["idLabels"] = label
        new = api("POST", "/cards", **params)
        known[key] = {"card": new["id"], "url": new.get("shortUrl") or new.get("url", "")}
        save_state(st)
        lines.append(f"{head} → {known[key]['url']}")
        if AUTO_FIX:
            jobs.put({"project": proj.name, "card": new["id"], "by": who,
                      "text": f"dev: fix {bid} - {title}. The bug details are in this card's description."})
    return lines


def move_card(card: dict, card_id: str, list_name: str) -> str:
    """Move the card to the list called `list_name` on its board. Returns a short note for a comment."""
    try:
        lists = api("GET", f"/boards/{card['idBoard']}/lists", filter="open", fields="name")
        target = next((l["id"] for l in lists if l["name"].strip().lower() == list_name.strip().lower()), None)
        if not target:
            say(f"  no '{list_name}' list on this board — card not moved", "yellow")
            return f" (No '{list_name}' list on this board — card not moved.)"
        if target != card.get("idList"):
            api("PUT", f"/cards/{card_id}", idList=target, pos="top")
            card["idList"] = target
        return f" Moved to {list_name}."
    except RuntimeError as e:
        say(f"  could not move card to {list_name}: {e}", "yellow")
        return f" (Could not move the card to {list_name}.)"


def read_card(card_id: str) -> tuple[dict, str]:
    card = api("GET", f"/cards/{card_id}", fields="name,desc,url,idList,idBoard", checklists="all",
               attachments="true", attachment_fields="name,url")
    bot_id = me()["id"]
    acts = api("GET", f"/cards/{card_id}/actions", filter="commentCard", limit=50)
    lines = [f"Trello card: \"{card['name']}\" ({card['url']})", "", card.get("desc") or "(no description)"]
    for cl in card.get("checklists") or []:
        lines += ["", f"Checklist — {cl['name']}:"]
        lines += [f"- [{'x' if i['state'] == 'complete' else ' '}] {i['name']}" for i in cl.get("checkItems", [])]
    if card.get("attachments"):
        lines += ["", "Attachments:"] + [f"- {a['name']}: {a['url']}" for a in card["attachments"]]
    conv = [f"- {'AGENT' if a['idMemberCreator'] == bot_id else '@' + a['memberCreator']['username']}: "
            f"{a['data']['text'][:1500]}" for a in reversed(acts)]
    if conv:
        lines += ["", "Comments (oldest first):"] + conv[-25:]
    return card, "\n".join(lines)


def owner_questions(proj) -> str:
    out = []
    for q in proj.inbox("owner"):
        out.append(q.read_text(encoding="utf-8").strip())
        (q.parent / "done").mkdir(exist_ok=True)
        q.replace(q.parent / "done" / q.name)
    return "\n\n".join(out)


def snapshot(proj, card_id: str) -> str:
    base = proj.shared / "backups" / f"trello-{card_id[-6:]}-{team.now('%Y%m%d-%H%M%S')}"
    dest, n = base, 1
    while dest.exists():  # two runs in the same second
        n += 1
        dest = base.with_name(f"{base.name}-{n}")
    shutil.copytree(proj.code_dir(), dest, ignore=team._ignore)
    return str(dest)


def handle(job: dict) -> None:
    proj = team.need(job["project"])
    card_id, who, text = job["card"], job["by"], job["text"]
    st = load_state()
    cst = st["cards"].setdefault(card_id, {"sessions": {}, "project": proj.name})
    save_state(st)

    if UNDO.search(text) and not roles_from_words(text):
        b = cst.get("backup")
        if b and Path(b).exists():
            team.restore(proj.code_dir(), Path(b))
            comment(card_id, f"Undo complete — the code is back to how it was before the last development "
                             f"run on this card (backup `{Path(b).name}`).", who)
        else:
            comment(card_id, "Nothing to undo — no development has run on this card yet.", who)
        return

    card, card_text = read_card(card_id)
    roles = roles_from_words(text) or roles_from_model(text, card_text)
    if not roles and cst.get("last_role"):
        roles = [cst["last_role"]]  # plain reply → feedback to whoever worked last
    if not roles:
        comment(card_id, "Which agent should take this? Mention me again with one of: "
                         "**requirements**, **dev**, **qa**, **docs** (e.g. `@me dev karo phir qa`).", who)
        return

    if roles != ["qa"]:  # a QA-only run posts just its bug list, nothing else
        comment(card_id, f"On it: {' → '.join(team.TITLES[r] for r in roles)}.", who)
    for i, role in enumerate(roles):
        st = load_state()
        cst = st["cards"][card_id]
        session = cst["sessions"].get(role)
        feedback = session is not None and i == 0 and cst.get("last_role") == role
        if role == "development" and DEV_TO_DOING:
            move_card(card, card_id, DOING_LIST)  # silent: the console logs it if the list is missing
        if role == "development" and not feedback:
            cst["backup"] = snapshot(proj, card_id)
        if feedback:
            prompt = f"New message from @{who} on the Trello card:\n{text}\n\nContinue your work on this card accordingly."
        else:
            prompt = (team.context_block(proj, role) + f"\nTask from Trello (requested by @{who}):\n"
                      f"Their message: {text}\n\n{card_text}\n\nYour part: {ROLE_TASK[role]}")
            session = None
        if role == "qa":
            bugs_file(proj).unlink(missing_ok=True)  # only this run's bugs count
            prompt += bug_list_instruction(proj)
        save_state(st)
        proj.board(f"{role}: started from Trello card {card['name']!r} (@{who})")

        run_started = time.time()
        summary, session, ok = team.run_claude(proj, role, prompt, session)
        st = load_state()
        cst = st["cards"][card_id]
        cst["sessions"][role], cst["last_role"] = session, role
        save_state(st)
        if not ok:
            hint = " Claude usage limit reached — mention me again after the reset." if "limit" in summary.lower() else ""
            comment(card_id, f"**{team.TITLES[role]}** could not finish.{hint}\n\n{summary}", who)
            return

        if role == "qa":
            # QA comment = only the bugs found (one line each, linked to its bug card). Nothing else.
            filed = file_bug_cards(proj, card, who)
            if filed:
                text_ = "**QA bugs:**\n" + "\n".join(filed)
            elif bugs_file(proj).exists() or qa_passed(proj, summary, run_started):
                text_ = "QA: No bugs found." + (move_card(card, card_id, DONE_LIST) if QA_PASS_TO_DONE else "")
            else:  # no bug list and no clear verdict — show QA's own (rule-shortened) summary
                text_ = f"QA: {summary}"
            comment(card_id, text_, who)
            proj.board(f"qa: finished Trello card {card['name']!r} — {len(filed)} bug(s)")
            continue

        parts = [f"**{team.TITLES[role]}** finished.\n\n{summary}"]
        q = owner_questions(proj)
        if q:
            parts.append(f"**Questions for you** — reply by mentioning me:\n\n{q}")
        if role == "development" and cst.get("backup"):
            ch, add, rm = team.diff_against(proj.code_dir(), Path(cst["backup"]))
            files = [f"M {r}" for r in ch] + [f"A {r}" for r in add] + [f"D {r}" for r in rm]
            parts.append("**Files changed:**\n" + ("\n".join(files[:60]) or "(none)")
                         + "\n\nMention me with `undo` to restore the code as it was before this run.")
        if role in team.PDF_SOURCES:
            team.export_pdfs(proj, role)
            parts.append("PDFs updated.")
        if i == len(roles) - 1:
            parts.append("Mention me with feedback to continue, or name the next agent (e.g. `qa karo`).")
        comment(card_id, "\n\n".join(parts), who)
        proj.board(f"{role}: finished Trello card {card['name']!r}")
        if q:
            return  # wait for the owner's answers before running the next agent


def worker() -> None:
    while True:
        job = jobs.get()
        try:
            say(f"\n▶ @{job['by']} on card {job['card'][-6:]} ({job['project']}): {job['text'][:80]!r}", "cyan")
            handle(job)
        except Exception as e:  # never kill the worker
            traceback.print_exc()
            try:
                comment(job["card"], f"Bridge error: {type(e).__name__}: {e}", job.get("by"))
            except Exception:
                pass
        finally:
            jobs.task_done()


# ------------------------------------------------------------------ webhook events → jobs
def mentions_agent(text: str) -> bool:
    return bool(re.search(rf"@{re.escape(me()['username'])}\b", text or "", re.I))


def route_event(payload: dict) -> dict | None:
    action = payload.get("action") or {}
    aid, atype, data = action.get("id"), action.get("type"), action.get("data", {})
    creator = action.get("memberCreator") or {}
    st = load_state()
    with _lock:
        if not aid or aid in st["seen"]:
            return None  # Trello retries deliveries; handle each action once
        st["seen"].append(aid)
        save_state(st)
    if creator.get("id") == me()["id"]:
        return None  # the agent's own comments / edits
    board = st["boards"].get((data.get("board") or {}).get("id") or (payload.get("model") or {}).get("id"))
    card = data.get("card") or {}
    if not board or not card.get("id"):
        return None

    text = None
    if atype == "commentCard":
        text = data.get("text", "")
    elif atype == "updateCard" and "desc" in (data.get("old") or {}):
        new, old = card.get("desc", ""), data["old"]["desc"] or ""
        if mentions_agent(new) and not mentions_agent(old):
            text = new  # the mention was just added to the description
    elif atype == "createCard":
        text = api("GET", f"/cards/{card['id']}", fields="desc").get("desc", "")
    if not text or not mentions_agent(text):
        return None
    clean = re.sub(rf"@{re.escape(me()['username'])}\b", "", text, flags=re.I).strip()
    return {"project": board["project"], "card": card["id"], "by": creator.get("username", "owner"),
            "text": clean or "please work on this card"}


def valid_signature(body: bytes, header: str | None) -> bool:
    if not SECRET:
        return True  # verification disabled (warned at startup)
    digest = hmac.new(SECRET.encode(), body + CALLBACK.encode(), hashlib.sha1).digest()
    return bool(header) and hmac.compare_digest(base64.b64encode(digest).decode(), header)


class Handler(BaseHTTPRequestHandler):
    def _reply(self, code: int) -> None:
        self.send_response(code)
        self.send_header("Content-Length", "0")
        self.end_headers()

    def do_HEAD(self):  # Trello checks the callback URL with HEAD when the webhook is created
        self._reply(200 if self.path.startswith("/trello") else 404)

    def do_GET(self):
        self._reply(200 if self.path.startswith("/trello") else 404)

    def do_POST(self):
        if not self.path.startswith("/trello"):
            return self._reply(404)
        body = self.rfile.read(int(self.headers.get("Content-Length", 0)))
        if not valid_signature(body, self.headers.get("X-Trello-Webhook")):
            say("✗ rejected request with bad signature — check TRELLO_SECRET, and that TRELLO_CALLBACK_URL "
                f"({CALLBACK or 'not set'}) is exactly the URL the webhook was registered with", "red")
            return self._reply(401)
        try:
            job = route_event(json.loads(body or b"{}"))
        except Exception:
            traceback.print_exc()
            job = None
        self._reply(200)  # always ack quickly; the work happens in the worker thread
        if job:
            jobs.put(job)
            say(f"queued job from @{job['by']} ({jobs.qsize()} waiting)", "dim")

    def log_message(self, *args):
        pass


class Server(ThreadingHTTPServer):
    # Windows' SO_REUSEADDR lets a second bridge bind the same port and silently steal Trello's
    # requests. Refuse instead, so only one bridge ever runs.
    allow_reuse_address = False


def cmd_serve() -> None:
    if not CALLBACK.startswith("https://"):
        sys.exit("TRELLO_CALLBACK_URL is not set in .env. Start ngrok, put its https URL + /trello in .env "
                 "(e.g. TRELLO_CALLBACK_URL=https://your-name.ngrok-free.app/trello), then start the bridge.")
    if not SECRET:
        say("⚠ TRELLO_SECRET not set — webhook signatures are NOT verified. Set it before using a public URL.", "yellow")
    m = me()
    if not load_state()["boards"]:
        say("No boards linked yet. With the server + ngrok running: python trello_bridge.py link <project> <board-url>", "yellow")
    try:
        server = Server(("127.0.0.1", PORT), Handler)
    except OSError:
        sys.exit(f"Port {PORT} is already in use — another trello_bridge is probably running. Stop it first "
                 "(only one bridge may run, otherwise Trello's requests go to the wrong one).")
    threading.Thread(target=worker, daemon=True).start()
    say(f"Trello bridge for @{m['username']} on http://127.0.0.1:{PORT}/trello (public: {CALLBACK or 'not set'})", "green")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        say("\nStopped.", "yellow")


def main() -> None:
    a = sys.argv[1:]
    cmds = {"whoami": (cmd_whoami, 0), "boards": (cmd_boards, 0), "link": (cmd_link, 2),
            "webhook": (cmd_webhook, 1), "unlink": (cmd_unlink, 1), "serve": (cmd_serve, 0)}
    if a and a[0] in cmds and len(a) - 1 == cmds[a[0]][1]:
        cmds[a[0]][0](*a[1:])
    else:
        print(__doc__)


if __name__ == "__main__":
    main()

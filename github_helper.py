"""GitHub access for the agents, using the token in .env (GITHUB_TOKEN).

The token never goes into the agent's environment, prompt, git config or remote URL:
  * `credential`  git credential helper — team.py points git at it for github.com only.
  * `pr`          opens a pull request for the current branch (run inside the repo folder).
  * `check`       shows whether the token works and which repo it can reach (never prints it).

Use a fine-grained token limited to the repos the agents may touch, with only
Contents: Read and write + Pull requests: Read and write.
"""
from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent


def token() -> str:
    env = ROOT / ".env"
    if env.exists():
        for line in env.read_text(encoding="utf-8").splitlines():
            k, sep, v = line.strip().partition("=")
            if sep and k.strip() == "GITHUB_TOKEN":
                return v.split(" #")[0].strip().strip('"').strip("'")
    return ""


def credential(action: str) -> None:
    """git credential protocol: answer 'get' for github.com, ignore store/erase."""
    req = dict(l.split("=", 1) for l in sys.stdin.read().splitlines() if "=" in l)
    if action != "get" or req.get("host", "").lower() != "github.com":
        return
    t = token()
    if t:
        print("username=x-access-token")
        print(f"password={t}")


def git(*args: str, cwd: str = ".") -> str:
    return subprocess.run(["git", *args], cwd=cwd, capture_output=True, text=True).stdout.strip()


def repo_slug(cwd: str) -> str:
    url = git("remote", "get-url", "origin", cwd=cwd)
    m = re.search(r"github\.com[:/]([^/]+/[^/]+?)(?:\.git)?/?$", url)
    if not m:
        sys.exit(f"origin is not a GitHub repo: {url or '(no origin)'}")
    return m.group(1)


def api(method: str, path: str, body: dict | None = None):
    t = token()
    if not t:
        sys.exit("GITHUB_TOKEN is not set in .env")
    req = urllib.request.Request(
        f"https://api.github.com{path}", method=method,
        data=json.dumps(body).encode() if body is not None else None,
        headers={"Authorization": f"Bearer {t}", "Accept": "application/vnd.github+json",
                 "X-GitHub-Api-Version": "2022-11-28", "User-Agent": "agent-team"})
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return json.loads(r.read() or b"null")
    except urllib.error.HTTPError as e:
        msg = e.read().decode(errors="replace")[:400]
        if e.code == 404 and method == "GET":
            return None
        sys.exit(f"GitHub {method} {path} → {e.code}: {msg}")


def default_base(slug: str) -> str:
    """Team rule: target dev or staging if one exists, otherwise main."""
    for b in ("dev", "staging"):
        if api("GET", f"/repos/{slug}/branches/{b}"):
            return b
    return (api("GET", f"/repos/{slug}") or {}).get("default_branch", "main")


def pr(a) -> None:
    slug = repo_slug(a.repo)
    head = a.head or git("rev-parse", "--abbrev-ref", "HEAD", cwd=a.repo)
    if head in ("main", "master", "dev", "staging", "HEAD", ""):
        sys.exit(f"Refusing to open a PR from '{head}'. Create a task branch first.")
    base = a.base or default_base(slug)
    owner = slug.split("/")[0]
    existing = api("GET", f"/repos/{slug}/pulls?head={owner}:{head}&state=open") or []
    if existing:
        print(f"PR already open: {existing[0]['html_url']}")
        return
    body = Path(a.body_file).read_text(encoding="utf-8") if a.body_file else (a.body or "")
    r = api("POST", f"/repos/{slug}/pulls", {"title": a.title, "head": head, "base": base, "body": body})
    print(f"PR #{r['number']} opened: {r['html_url']}  ({head} → {base})")


def check(a) -> None:
    if not token():
        sys.exit("GITHUB_TOKEN is not set in .env")
    slug = repo_slug(a.repo)
    r = api("GET", f"/repos/{slug}")
    if not r:
        sys.exit(f"Token works but cannot see {slug}. Add this repo to the token's repository access.")
    perms = r.get("permissions") or {}
    print(f"OK: token can reach {slug} (push={'yes' if perms.get('push') else 'no'}); "
          f"PR base would be '{default_base(slug)}'")


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = p.add_subparsers(dest="cmd", required=True)
    s = sub.add_parser("credential"); s.add_argument("action")
    s = sub.add_parser("pr", help="Open a PR for the current branch")
    s.add_argument("--title", required=True); s.add_argument("--body"); s.add_argument("--body-file")
    s.add_argument("--base", help="default: dev or staging if it exists, else main")
    s.add_argument("--head", help="default: current branch"); s.add_argument("--repo", default=".")
    s = sub.add_parser("check", help="Test the token against this repo's origin"); s.add_argument("--repo", default=".")
    a = p.parse_args()
    {"credential": lambda: credential(a.action), "pr": lambda: pr(a), "check": lambda: check(a)}[a.cmd]()


if __name__ == "__main__":
    main()

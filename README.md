# Agent Team (Claude Code)

4 alag agents. Har ek ka apna folder aur apni `CLAUDE.md` hai. Agents aapas me sirf `shared/` folder ke zariye baat karte hain. Ye sab aap ki **Claude subscription** pe chalta hai (Claude Code CLI), is liye API key ki zaroorat nahi.

```
Agents/
├── CLAUDE.md                  team rules + communication protocol (har agent padhta hai)
├── team.py                    orchestrator
├── .claude/agents/*.md        same agents, Claude Desktop/CLI ke andar subagents ki tarah
├── rules/GLOBAL_RULES.md      ← aap ke rules jo HAR project pe lagte hain
├── agents/
│   ├── requirements/  CLAUDE.md  projects/<p>/BRD.md, REQUIREMENTS.md (SRS), CHANGELOG.md, versions/
│   ├── development/   CLAUDE.md  (code htdocs me hota hai, neeche dekhein)
│   ├── qa/            CLAUDE.md  projects/<p>/   ← tests, TEST_PLAN, QA_REPORT
│   └── docs/          CLAUDE.md  projects/<p>/   ← USER_GUIDE, API, ARCHITECTURE…
├── shared/projects/<p>/       ← communication
│   ├── RULES.md               ← SIRF IS PROJECT ke rules (har agent ko har run me milte hain)
│   ├── BRIEF.md  decisions.md  board.md  project.json
│   ├── handoffs/              01-requirements-to-development.md …
│   └── inbox/<agent>/         agents ke ek doosre ke liye sawal; inbox/owner/ = aap ke liye
└── api-version/               purana Claude-API wala version (API key pe chalta hai)

C:\xampp\htdocs\<p>\           ← project ka code (developer agent yahin kaam karta hai)
```

### Code kahan banta hai
Naye project ka code `C:\xampp\htdocs\<project>` me banta hai, to seedha `http://localhost/<project>` pe chala sakte hain. Developer agent sirf usi folder me likh sakta hai. QA aur requirements use sirf parh sakte hain, aur docs agent wahan sirf `README.md` likh sakta hai.
- Pehle se bana folder ho (existing app), to system confirm karega aur developer usi code pe kaam karega.
- Kisi aur jagah ke liye `--code-dir D:\sites\myapp` dein. Code team folder ke andar rakhna ho to `--in-team` dein.

### Path kab dena padta hai?

| Kaam | Path dena hoga? |
|---|---|
| Naya project | **Nahi.** `htdocs\<naam>` khud ban jata hai. Kahin aur chahiye ho to `--code-dir` |
| `htdocs` me pehle se bana project | **Nahi**, sirf folder ka naam, maslan `python team.py dev myshop "..."` |
| `htdocs` ke bahar ka project | Sirf **pehli baar** poora path dein, uske baad sirf naam |
| Registered project | Sirf naam |

Kisi bhi command (`dev`, `ask`, `run`, `adopt`, `rule -p`) me `htdocs` ka folder naam dein to project khud register ho jata hai.

### Existing project ko system kaise samajhta hai (BRD + SRS)
Har project ke liye requirements agent 2 documents rakhta hai, aur baqi saare agents inhi se kaam karte hain:
- **`BRD.md`**: Business Requirements. Ye system kis liye hai, users kaun hain, business rules aur scope.
- **`REQUIREMENTS.md`**: SRS. Har feature FR-ID ke saath, data model, pages/endpoints aur known issues.

**Naya project:** Ye dono brief aur aap ke jawabon se bante hain.

**Existing project (pehli baar):** Requirements agent poora code parhta hai: routes, DB schema, models, integrations aur roles. Phir v1 likhta hai, yani "system aaj jaisa hai" (as-is baseline), aur jo cheezein code se pata nahi chal saktin woh aap se poochta hai. Uske baad har badlaav v2, v3… me changelog ke saath jata hai.

```bash
python team.py adopt myshop
```

`adopt` alag se chalana zaroori nahi. Agar kisi existing project pe pehli dafa `dev`, `ask` ya `run` chalayein aur uske documents nahi bane, to system poochega *"Document the project first? [y/n]"*. `y` dein to pehle BRD + SRS banenge, phir kaam hoga. Jaldi wala fix ho to `n` dein.

Agar `new` ko kisi aise folder ka naam dein jisme pehle se code ho, to requirements agent pehle as-is v1 banata hai, phir aap ki nayi maang ko v2 me daalta hai.

### Rules: sab projects ke liye ya ek project ke liye
Rules ki 2 level hain. Dono **har agent ko har run pe** khud milti hain, aur takraav ho to project ka rule jeet-ta hai.

| File | Kis pe lagta hai |
|---|---|
| `rules/GLOBAL_RULES.md` | **Har project** pe |
| `shared/projects/<p>/RULES.md` | Sirf us project pe |

Ek rule ek dafa likhein:

```bash
python team.py rule "SQL hamesha prepared statements se likho"
```

```bash
python team.py rule "Code me comments English me ho" --for development
```

```bash
python team.py rule "Is project me Bootstrap 5 use karo" -p myshop
```

`--for` me `all`, `stack`, `requirements`, `development`, `qa` ya `docs` de sakte hain. Poori file editor me kholne ke liye:

```bash
python team.py rules
```

```bash
python team.py rules myshop
```

**Feedback me bola gaya rule bhi yaad rehta hai.** Agar approval gate pe aap likhein *"aage se hamesha …"*, to agent use us project ki `RULES.md` me save kar deta hai. Agar likhein *"har project me …"*, to `GLOBAL_RULES.md` me. Dono soorat me woh batata hai ke rule kahan save hua.

### Developer se kisi project me jaa kar test + fix karwana (`dev`)

```bash
python team.py dev myshop "Checkout page pe total galat aa raha hai, discount do dafa minus ho raha hai"
```

`myshop` ki jagah registered project ka naam, `htdocs` ka folder naam, ya poora path (`D:\sites\app`) de sakte hain. Folder register na ho to system use khud register kar leta hai, aur us ke liye pipeline nahi chalti.

1. **Jaanch:** Developer code parhta hai aur tests, `php -l` ya `curl http://localhost/myshop/` chalata hai. Is step me woh project ki koi file **nahi badal sakta**. Phir `shared/projects/myshop/fixes/<time>-fix.md` me proposal likhta hai: masla, kaise test kiya, root cause (file:line), kya badlega, risk aur verify kaise karega.
2. **Aap ka faisla:** `a` approve, `f` "aise nahi, aise karo", ya `s` chhod do (kuch nahi badlega).
3. **Fix:** Approve karne pe agent (bina backup ke) usi project folder me fix lagata hai, dobara test karta hai, aur report me `## Result` likhta hai.
4. **Aakhri check:** Badli hui files ki list dikhti hai (M/A/D). Phir `a` accept karein, ya `f` aur badlaav maangein. Koi backup/undo nahi — tabdeeliyan sirf report aur `board.md` me likhi jaati hain.

### PDF documents + version history
Requirements phase (BRD, SRS) aur docs phase (User Guide, API, Architecture, Deployment, Changelog) approve hote hi **PDF khud ban jati hai**:

| Kis ke documents | PDF kahan |
|---|---|
| BRD + SRS | `agents\requirements\projects\<p>\pdf\` |
| Saare docs | `agents\docs\projects\<p>\pdf\` |
| Har version ki purani copy | `pdf\archive\<DOC>-v<N>.pdf` |

Har PDF me ye hota hai:
- cover page: project, title, version, date, prepared by
- har page pe header aur "Page X of Y"
- tables, code blocks aur diagrams (mermaid)
- **aakhri page pe "Document Version History"**: Version 1, 2, 3… date aur badlaav ki tafseel ke saath

**"Prepared by" ka naam** ek rule se badalta hai. Ye naam cover page aur version history ke "Author" column, dono me aata hai:

```bash
python team.py rule "Prepared by: AI Engineer"
```

```bash
python team.py rule "Prepared by: Avaib Tech — Documentation Team" -p todo-app
```

Pehli command sab projects pe lagti hai. Doosri (`-p`) sirf us ek project pe, aur woh global wale naam se upar hoti hai.

Agents har badlaav pe is table me ek nayi row jodte hain aur purani rows kabhi nahi badalte. SRS ka version number `CHANGELOG.md` ke saath match karta hai. PDFs dobara banane ke liye:

```bash
python team.py pdf todo-app
```

Docs agent by default **Sonnet** pe chalta hai, kyunki documentation ke liye ye kaafi hai aur subscription ka usage kam lagta hai. Badalna ho to `.env` me `TEAM_MODEL_DOCS=opus` likhein. Isi tarah `TEAM_MODEL_QA`, `TEAM_MODEL_DEVELOPMENT` aur `TEAM_MODEL_REQUIREMENTS` bhi set ho sakte hain.

### Requirements badlein to?
`REQUIREMENTS.md` hamesha latest version hoti hai, aur uske header me `Version: N` hota hai. Har badlaav pe:
- purani version `versions/REQUIREMENTS-vN.md` me save hoti hai
- `CHANGELOG.md` me entry likhi jati hai: kya add, change ya remove hua, kyun hua, kis ne kaha, aur kis cheez pe asar padega
- development (aur zaroorat ho to QA) ke inbox me message jata hai

Developer `DEV_NOTES.md` me likhta hai ke code kis version pe bana hai, aur nayi version aaye to sirf changelog wale FR-IDs implement karta hai.

Badlaav maangne ke liye:

```bash
python team.py ask requirements school-portal "Parents ko SMS notification bhi chahiye, aur attendance report PDF me ho"
```

```bash
python team.py run school-portal --from development
```

**Isolation:** Har agent apne folder me chalta hai. Woh baqi sab folders padh sakta hai, lekin doosre agent ke folder me likhna permission rules se **block** hai. QA kabhi developer ka code nahi badal sakta, sirf bug report de sakta hai.

## Ek dafa setup

Claude Desktop ka login CLI ke saath share nahi hota. Ek dafa apne normal terminal me token banayein:

```bash
claude setup-token
```

`.env.example` ko copy karke `.env` banayein aur token `CLAUDE_CODE_OAUTH_TOKEN=` ke aage paste karein. `team.py` ise khud parh leta hai. Ye token kisi ko na dein, aur `.env` gitignore me hai.

## Use

```bash
python team.py new school-portal "Laravel school management: students, fees, attendance, parent login"
```

Existing codebase pe kaam (developer wahan code likhega):

```bash
python team.py new myapp-invoices "Invoice PDF export add karo" --code-dir C:\xampp\htdocs\myapp
```

Ya agar project ka naam hi folder ka naam ho (`htdocs\myapp`), to bas:

```bash
python team.py new myapp "Invoice PDF export add karo"
```

```bash
python team.py run school-portal --from qa
```

```bash
python team.py ask development school-portal "Login page pe remember-me add karo"
```

```bash
python team.py fix school-portal --rounds 2
```

```bash
python team.py route school-portal
```

```bash
python team.py open qa school-portal
```

```bash
python team.py status
```

| Command | Kya karta hai |
|---|---|
| `new` | project banao + poori pipeline chalao |
| `run --from/--only` | pipeline resume karo ya ek phase chalao |
| `ask` | kisi ek agent ko seedha kaam do |
| `fix` | Developer QA bugs fix kare → QA dobara test kare (loop) |
| `route` | agents ke inbox me pare sawalon ke jawab dilwao |
| `open` | us agent ke folder me interactive Claude Code session (uski pichli baat-cheet se aage) |
| `rules [project]` | global rules ya kisi project ki `RULES.md` editor me kholo |
| `rule "<text>" [-p p] [--for role]` | ek rule add karo (default: sab projects) |
| `adopt <folder>` | existing project register karo + BRD/SRS banwao |
| `pdf <project>` | BRD, SRS aur saare docs ki PDFs dobara banao |
| `dev` | kisi bhi project me developer jaanch kare → proposal → aap approve karein → fix (sirf md report update, koi backup nahi) |

Har phase ke baad approval gate: `a` approve, `f` feedback ya sawalon ke jawab (agent apni hi session me aage chalta hai), `s` stop.

## Claude Desktop se

Desktop me `C:\xampp\htdocs\Agents` folder kholein aur likhein, maslan: *"qa agent se school-portal test karwao"*. `.claude/agents/` wale subagents wahi `CLAUDE.md` rules follow karte hain.

## Settings (environment variables)

| Var | Default | |
|---|---|---|
| `TEAM_MODEL` | (aap ka default) | `opus`, `sonnet`, `haiku`, `fable` |
| `TEAM_EFFORT` | `high` | `low` … `max` |
| `TEAM_PERMISSION_MODE` | `acceptEdits` | file edits auto; shell sirf `ALLOWED_TOOLS` list wale. `auto` = Claude Code auto mode |
| `TEAM_APPROVAL_GATES` | `true` | `false` = bina ruke poori pipeline |
| `TEAM_CODE_ROOT` | `C:\xampp\htdocs` | naye projects ka code kahan bane; khali = team folder ke andar |

Shell commands ki allow-list `team.py` me `ALLOWED_TOOLS` me hai. Koi command block ho to output me `✗ denied` dikhega, use list me add kar dein.

## Server pe

Server pe Claude Code install karein, wahan `claude setup-token` se long-lived token banayein, `CLAUDE_CODE_OAUTH_TOKEN` env var me set karein, aur `team.py` waise hi chalayein. Aage Trello poller cards ko `team.py new/run` me bhejega.

# Agentic Dev Team (personal)

Aap ki apni AI team jo Claude API (`claude-opus-5-5`) pe chalti hai:

| Agent | Kaam | Output file |
|---|---|---|
| Requirements Analyst | Brief samajhna, sawal poochna, spec likhna | `docs/REQUIREMENTS.md` |
| Senior Developer | Code likhna, tests chalana, QA bugs fix karna | code + `docs/DEV_NOTES.md` |
| QA Engineer | Tests likhna/chalana, code review, bug report | `docs/QA_REPORT.md` |
| Technical Writer | README, API, user guide, architecture docs | `README.md`, `docs/*` |

Har phase ke baad **approval gate** aata hai: `a` = approve, `f` = feedback/jawab do (agent wahi se continue karega), `s` = ruk jao (baad me resume).

## Setup (Windows, local)

```bash
pip install -r requirements.txt
```

```bash
copy .env.example .env
```

`.env` me apni `ANTHROPIC_API_KEY` daalein (console.anthropic.com se).

## Use

Naya project (folder `workspace/<name>` me banega):

```bash
python main.py new school-portal "Laravel me school management system: students, fees, attendance, parent login"
```

Existing project pe kaam (koi bhi folder):

```bash
python main.py new C:\xampp\htdocs\myapp "Is app me invoice PDF export add karo" 
```

Resume / ek phase dobara:

```bash
python main.py run school-portal --from developer
```

```bash
python main.py run school-portal --only docs
```

Kisi ek agent ko direct kaam:

```bash
python main.py ask developer school-portal "Login page pe 'remember me' add karo"
```

QA bugs → Developer fix → QA re-test loop:

```bash
python main.py fix school-portal --rounds 2
```

Status:

```bash
python main.py status
```

## Safety

- Agents sirf project folder ke andar files padh/likh sakte hain.
- Har shell command (`pip install`, `npm test` waghera) se pehle aap se poocha jata hai (`AGENTIC_COMMAND_MODE=ask`).
- Har project ka log `.agentic/log.md` me, state `.agentic/state.json` me.

## Server pe le jana

```bash
docker build -t agentic .
```

```bash
docker run -it --rm --env-file .env -e AGENTIC_COMMAND_MODE=auto -v agentic_ws:/workspace agentic new my-api "..."
```

Container khud sandbox hai, is liye wahan `auto` mode theek hai. Apni local machine pe `ask` hi rakhein.

## Code structure

```
agentic/
  config.py    settings (.env se)
  tools.py     list/read/write/edit/search files, run_command (sandboxed)
  llm.py       Claude tool-loop (streaming, adaptive thinking, prompt caching, refusal fallback)
  agents.py    4 roles ke system prompts + tools  <- naya agent yahan add karein
  pipeline.py  phases, approval gates, state, QA-fix loop
  cli.py       commands
```

## Aage (roadmap)

- **Trello**: ek poller jo "To Do" list ke cards ko `create_project` + `run_pipeline` me bhejega aur har phase pe card ko list move karega / comment karega. `pipeline.run_pipeline()` isi liye alag function hai.
- Web dashboard / Telegram bot.
- Git integration (har phase ke baad commit, PR).

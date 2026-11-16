# Trello se agents chalana: agent account ko @mention karke

Trello pe ek alag **agent account** hota hai, maslan `@aiagent`. Jab aap kisi card ke **comment** ya **description** me use tag karte hain, to woh poora card parhta hai, aap ki baat se tay karta hai ke kaun sa agent chalana hai, kaam karta hai, aur card pe aap ko @mention karke jawab deta hai.

Agents (`agents/`) aur `team.py` me koi tabdeeli nahi hui. Sab kaam `trello_bridge.py` karta hai.

## Kaise kaam karta hai

| Aap card pe likhte hain | Kya hota hai |
|---|---|
| `@aiagent is card ki requirements likho` | Requirements agent BRD/SRS update karta hai. Sawal hon to comment me poochta hai |
| `@aiagent dev karo` | Developer code likhta hai (pehle backup banta hai) aur badli hui files ki list deta hai |
| `@aiagent dev karo phir qa karo` | Developer, phir QA, isi tarteeb se |
| `@aiagent test karo` | QA report: READY / NOT READY, bugs ke saath |
| `@aiagent docs update karo` | Docs update hote hain aur PDFs ban jati hain |
| `@aiagent footer bold kar do` *(agent ka naam nahi)* | Jis agent ne is card pe aakhri kaam kiya, wahi isay feedback samajh ke aage chalta hai |
| `@aiagent undo` | Aakhri dev run se pehle wala code wapas aa jata hai |
| Card ki description me `@aiagent ...` likh dena | Comment jaisa hi kaam karta hai |

**Agent kaise chuna jata hai:**
- Comment me ye alfaaz dekhe jate hain, English ya Roman Urdu me:
  - Requirements: `requirements`, `SRS`, `BRD`, `scope`
  - Developer: `dev`, `developer`, `implement`, `code`, `fix`
  - QA: `qa`, `test`, `verify`, `check karo`
  - Docs: `docs`, `documentation`, `user guide`, `readme`
- Agar koi naam na mile, to ek chhota model (Haiku) comment parh ke faisla karta hai. Isme takreeban 20 second lagte hain.
- Phir bhi samajh na aaye, to agent poochta hai ke kaun sa agent chahiye.

**Agent kya kya parhta hai:** Card ka title, description, checklists, attachments ke naam aur links, aur saare comments (kis ne kya kaha).

**Jawab:** Har jawab aap ko @mention karta hai, taake Trello ki notification aaye. Requirements agent ne sawal kiye hon to agla agent tab tak nahi chalta jab tak aap jawab na dein.

Ek waqt me ek hi kaam chalta hai, baqi line me rehte hain. Card ki list se koi farq nahi padta, aap apne board ki lists jaise chahein rakhein.

## QA ke bugs ke liye khud cards banna
Jab Trello se QA chalta hai aur bugs nikalte hain:
1. **Har bug ka naya card** usi list me sab se upar banta hai:
   - title `[BUG-1] ...`
   - laal **Bug** label (board pe na ho to khud ban jata hai)
   - description me: severity, file:line, reproduce karne ke steps, original card ka link, aur fix karwane ka tareeqa
2. QA wale card pe **sirf bugs ki list** aati hai, har bug ek line me us ke card ke link ke saath. Bug na hon to sirf "QA: No bugs found."
3. **Developer khud nahi chalta.** Kisi bug ko fix karwana ho to us bug card pe comment karein:
   ```
   @aiagent01 dev fix karo
   ```
   Fix backup aur `undo` ke saath hota hai.
4. Fix ke baad usi card pe `@aiagent01 qa` likh kar dobara test karwayein.
5. **Duplicate nahi banta:** dobara QA me wahi bug aaye, chahe number badal jaye, to naya card nahi banta. Comment me purane card ka link "(still open)" ke saath aata hai.

### Developer shuru ho to card khud Doing me
`.env` me ye setting on hai:
```
TRELLO_DEV_TO_DOING=true
```
- Jaise hi Developer kisi card pe kaam shuru karta hai, card **Doing** list me chala jata hai. Ye "dev karo phir qa karo" jaise combined kaam me bhi hota hai.
- Card pehle se Doing me ho to kuch nahi hota.
- List ka naam alag ho to `.env` me `TRELLO_DOING_LIST=In Progress` likhein. Board pe woh list na ho to card nahi hilta, sirf bridge ki window me note aata hai.
- Band karna ho to `TRELLO_DEV_TO_DOING=false` karein.

Is tarah card ka safar khud chalta hai: **To Do → Doing** (Developer ne kaam shuru kiya) **→ Completed** (QA me koi bug nahi mila).

### QA pass ho to card khud Done me
`.env` me ye setting on hai:
```
TRELLO_QA_PASS_TO_DONE=true
```
- **QA ko koi bug na mile** to card khud **Done** list me chala jata hai, aur comment aata hai: "QA: No bugs found. Moved to Done."
- **Bug mile**, chahe purana hi kyun na ho, to card apni jagah rehta hai.
- Board pe "Done" naam ki list na ho to card nahi hilta, aur comment me ye bata diya jata hai.
- List ka naam alag ho, maslan "Completed", to `.env` me `TRELLO_DONE_LIST=Completed` likhein.
- Band karna ho to `TRELLO_QA_PASS_TO_DONE=false` karein.
- Setting badalne ke baad bridge restart karein.

---

## Setup (ek dafa)

### 1. Agent ka Trello account
1. Ek **naye email** se Trello account banayein. Maslan username `aiagent` rakhein, yahi tag hoga.
2. Apne har project board me is account ko **member** bana dein (Board → Share → invite).

### 2. API key, secret aur token (agent account se)
1. **Agent account se login** karke https://trello.com/power-ups/admin kholein, **New** dabayein, naam "Agent Team" rakhein, aur workspace chunein.
2. **API key** tab se **API Key** aur **Secret** copy karein.
3. Usi page ke **Token** link pe click karein aur **Allow** karein. Ye token **agent account ka** hona chahiye, kyunki agent isi se comment karega.
4. `C:\xampp\htdocs\Agents\.env` me ye lines khud add karein. Ye values chat me ya kisi aur ko na dein:
   ```
   TRELLO_KEY=...
   TRELLO_SECRET=...
   TRELLO_TOKEN=...
   TRELLO_BRIDGE_PORT=8787
   TRELLO_CALLBACK_URL=
   ```
5. Check karein ke token sahi account ka hai. Jawab me `@aiagent` aana chahiye:
   ```bash
   python trello_bridge.py whoami
   ```

### 3. Bridge chalayein (Terminal 1, chalta rahe)
```bash
cd C:\xampp\htdocs\Agents
```
```bash
python trello_bridge.py serve
```

### 4. ngrok (Terminal 2, chalta rahe)
```bash
ngrok http 8787
```
Jo `https://….ngrok-free.app` URL aaye, use `.env` me aakhir me `/trello` laga kar likhein:
```
TRELLO_CALLBACK_URL=https://aap-ka-naam.ngrok-free.app/trello
```
Phir Terminal 1 me bridge **Ctrl+C** se band karke dobara `serve` chalayein.

> **Tip:** ngrok dashboard → Domains se ek free **static domain** lein, us ke saath URL kabhi nahi badalta:
> `ngrok http --url=aap-ka-naam.ngrok-free.app 8787`

### 5. Board ko project se jodein
Board ka link dekhne ke liye:
```bash
python trello_bridge.py boards
```
Phir board ko project se jodein:
```bash
python trello_bridge.py link todo-app https://trello.com/b/AbCd1234/todo-app
```
Har project ke board ke liye `link` ek dafa chalayein. `htdocs` ka koi bhi folder naam de sakte hain.

---

## Rozana
1. Terminal 1 me `python trello_bridge.py serve`.
2. Terminal 2 me ngrok.
3. Trello pe card banayein, task likhein, aur `@aiagent` tag karein. Bridge ki window me live progress dikhti hai.

## ngrok URL badal jaye (static domain na ho to)
1. `.env` me naya URL likhein.
2. Bridge restart karein.
3. Har project ke liye webhook dobara register karein:
   ```bash
   python trello_bridge.py webhook todo-app
   ```

## Board hatana
```bash
python trello_bridge.py unlink todo-app
```

## Masle aur hal
| Masla | Hal |
|---|---|
| Tag kiya, kuch nahi hua | Bridge ki window aur ngrok ki window (`POST /trello 200`) dekhein. Agent account board ka member ho, aur tag bilkul username jaisa ho |
| `link` pe error | Bridge aur ngrok dono chal rahe hon, URL ke aakhir me `/trello` ho, aur agent account board ka member ho |
| `rejected request with bad signature` | `.env` me `TRELLO_SECRET` ya `TRELLO_CALLBACK_URL` galat hai, ya URL badla aur bridge restart nahi hua |
| Galat agent chala | Comment me saaf naam likhein: `@aiagent qa: ...` |
| "usage limit" wala comment | Claude subscription ki limit. Reset ke baad dobara tag karein |

## Server pe
`python trello_bridge.py serve` chalayein (systemd service bana dein), aur Nginx me `https://your-domain/trello` → `127.0.0.1:8787` lagayein. Server pe ngrok ki zaroorat nahi. `.env` me `TRELLO_CALLBACK_URL=https://your-domain/trello` likh kar har project ke liye `webhook` chala dein.

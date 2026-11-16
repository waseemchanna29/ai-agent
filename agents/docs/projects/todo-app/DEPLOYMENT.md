# Deployment & Setup — School To-Do

Local-only app for XAMPP. No public hosting, no CI/CD.

## 1. Requirements

- **PHP 8.2+** (XAMPP's PHP is fine) with the usual Laravel extensions
  (`pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `ctype`, `json`, `bcmath`, `fileinfo`).
- **MySQL** (XAMPP) running on `127.0.0.1:3306`.
- **Composer** (for dependencies). No Node/npm required — the UI is plain Blade +
  inline CSS.
- Code location: `C:\xampp\htdocs\todo-app`.

## 2. Environment variables

Copy `.env.example` → `.env` and set the key values below. The example file already
targets `school_db`.

| Variable | Value (local) | Notes |
|----------|---------------|-------|
| `APP_NAME` | `"School To-Do"` | Shown in the title bar and nav |
| `APP_ENV` | `local` | |
| `APP_KEY` | *(generated)* | Run `php artisan key:generate` |
| `APP_DEBUG` | `true` | Set `false` for any non-local use |
| `APP_URL` | `http://localhost` | |
| `DB_CONNECTION` | `mysql` | |
| `DB_HOST` | `127.0.0.1` | |
| `DB_PORT` | `3306` | |
| `DB_DATABASE` | `school_db` | Must exist before migrating |
| `DB_USERNAME` | `root` | XAMPP default |
| `DB_PASSWORD` | *(empty)* | XAMPP default — local only |
| `SESSION_DRIVER` | `database` | Sessions stored in the `sessions` table |
| `BCRYPT_ROUNDS` | `12` | Password hashing cost |

> **Secrets:** never commit a real `.env`. Keep `.env.example` (no secrets) in source
> control. The blank DB password is acceptable **only** because this is a local XAMPP
> dev setup.

## 3. First-time install

Run from `C:\xampp\htdocs\todo-app`:

```bash
# 1. Install PHP dependencies
composer install

# 2. Create the env file and an app key
copy .env.example .env        # (Windows)   — or: cp .env.example .env
php artisan key:generate

# 3. Create the database (once), e.g. in phpMyAdmin or MySQL CLI:
#    CREATE DATABASE school_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 4. Run migrations (creates users, tasks, sessions/cache/jobs tables)
php artisan migrate
```

> `SESSION_DRIVER=database` means the `sessions` table (and the default cache/jobs
> tables) are created by the migrations in step 4 — needed for login to work.

## 4. Running the app

**Option A — Artisan dev server (recommended):**

```bash
php artisan serve
```
Then open `http://127.0.0.1:8000` (root redirects to `/tasks`, which redirects a
guest to `/login`).

**Option B — XAMPP Apache:**
With the project under `C:\xampp\htdocs\todo-app`, start Apache + MySQL from the
XAMPP control panel and open `http://localhost/todo-app/public`.

## 5. Running the tests

```bash
php artisan test
```
Tests run on **in-memory SQLite** and never touch `school_db`. Expected: all
developer feature tests pass (QA confirmed 30 automated tests green overall,
including the supplementary QA suite in the QA folder).

## 6. Resetting / reseeding the database (dev only)

```bash
php artisan migrate:fresh      # drops all tables and re-migrates — DELETES all data
```
Use only against the local dev database.

## 7. Going beyond local (if ever needed)

This app is scoped to local use. Before exposing it anywhere else you would need to,
at minimum:

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Generate a fresh `APP_KEY` and use a **non-empty** DB password and a non-root user.
- Serve `public/` behind Apache/Nginx with HTTPS.
- Run `php artisan config:cache route:cache view:cache` for performance.

These are out of scope for the delivered school project.

## 8. Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| `SQLSTATE[HY000] [1049] Unknown database 'school_db'` | DB not created | Create `school_db`, then `php artisan migrate` |
| `SQLSTATE... Access denied` | Wrong DB user/password in `.env` | Match XAMPP (`root`, empty password) |
| "No application encryption key has been specified" | `APP_KEY` empty | `php artisan key:generate` |
| Login seems to never persist | `sessions` table missing | Run `php artisan migrate` (session driver is `database`) |
| 419 Page Expired on submit | CSRF token expired/stale | Reload the page and resubmit |
| Styles missing / 404 on `/tasks` via Apache | Wrong URL root | Use `http://localhost/todo-app/public` |

## Document Version History

| Version | Date | Author | Description of changes |
|---|---|---|---|
| 1 | 2026-11-12 | Technical Writer | Initial release |

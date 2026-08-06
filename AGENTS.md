# AGENTS.md

## Stack
- Laravel 13 (`laravel/framework` v13.24), PHP ^8.3, SQLite by default (no host/user/pass needed)
- Auth stack: Sanctum 4 (API/SPA tokens), Fortify 1.37 (session auth; 2FA + passkeys available), Socialite 5.29
- `laravel/passkeys` comes in as a Fortify dependency — the `passkeys` table migration exists, but passkeys/2FA features are still commented out in `config/fortify.php`
- This backend serves a sibling SPA repo at `../frontend` (React 19 + Vite). SPA session auth is wired: `config/cors.php` is published (`allowed_origins: ['http://localhost:5173']`, `supports_credentials: true`), `SANCTUM_STATEFUL_DOMAINS` is set to the frontend origin, and `bootstrap/app.php` registers `$middleware->statefulApi()` (required for Sanctum to use cookies/sessions on `api/*` routes). The browser must send an `Origin` matching a stateful domain or `auth:sanctum` will 401 even with a valid session cookie.

## Commands (composer scripts in composer.json)
- `composer setup` — full bootstrap: composer install, copy `.env` from `.env.example`, `key:generate`, `migrate --force`, `npm install` + `npm run build`
- `composer dev` — runs `php artisan serve`, queue worker, `php artisan pail` (logs), and Vite concurrently
- `composer test` — `artisan config:clear` then `artisan test`
- `php artisan test --filter=<name>` — run a single test
- `./vendor/bin/pint` — style fixer; no `pint.json`, uses the Laravel default preset

## DB & tests
- Local DB is the SQLite file `database/database.sqlite` (already present); just run `php artisan migrate`
- `phpunit.xml` forces all tests onto in-memory SQLite; suites are plain PHPUnit `tests/Unit` and `tests/Feature`

## API & auth conventions
- JSON error rendering is enabled only for `api/*` paths (`shouldRenderJsonWhen` in `bootstrap/app.php`)
- `routes/api.php` has only `GET /api/user` behind `auth:sanctum`; Fortify routes are session/web-based
- Rate limiters defined in `app/Providers/FortifyServiceProvider.php`: login 5/min, two-factor 5/min, passkeys 10/min
- Fortify `passkeys.allowed_origins` / `relying_party_id` derive from `APP_URL` (`config/fortify.php`) — WebAuthn only works if the frontend origin matches `APP_URL` exactly

## Repo notes
- `.env`, `vendor/`, `node_modules/` are gitignored; `.env` exists locally
- Repo-local OpenCode skills under `.opencode/skills/` (`laravel-expert`, `php-pro`, `api-*`, `database-*`, security) apply here — load the matching skill before API/DB/security work
- No CI workflows, no `opencode.json`, no app seeders yet

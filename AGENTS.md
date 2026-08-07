# AGENTS.md

## Stack
- Laravel 13 (`laravel/framework` ^13.8), PHP ^8.3
- **Auth is JWT** via `php-open-source-saver/jwt-auth` (^2.9). Sanctum was removed — do not reintroduce session/cookie API auth.
- Fortify 1.37 kept only for password reset + the `CreateNewUser` action + email-verification links. `laravel/passkeys` comes in as a Fortify dep (passkeys/2FA commented out in `config/fortify.php`). Socialite 5.29 present.
- Local dev DB is **Supabase Postgres** (`.env` has `DB_CONNECTION=pgsql`) using the **IPv4 pooler** host (`aws-0-eu-north-1.pooler.supabase.com`, user `postgres.<ref>`) — the direct host is IPv6-only and fails on many networks. `.env.example` still defaults to sqlite; `database/database.sqlite` exists but is unused.
- Frontend: sibling SPA at `../frontend` (React 19 + Vite, has its own AGENTS.md). It stores the JWT in `localStorage`, sends `Authorization: Bearer`, no cookies/CSRF. CORS `supports_credentials=false`, `allowed_origins` from `FRONTEND_URL`.

## Commands (composer scripts)
- `composer setup` — install, copy `.env`, `key:generate`, `migrate --force`, build assets
- `composer dev` — `php artisan serve` + queue worker + `pail` + Vite concurrently
- `composer test` — runs `config:clear` first (phpunit env overrides are inert if config is cached), then `artisan test`
- `php artisan test --filter=<name>` — single test
- `./vendor/bin/pint` — Laravel default preset, no `pint.json`

## DB & tests
- `phpunit.xml` forces all tests onto in-memory SQLite (`DB_CONNECTION=sqlite`, `:memory:`, `QUEUE_CONNECTION=sync`). Feature tests never touch Supabase.
- Local `QUEUE_CONNECTION=database` — verification emails only send while `composer dev`'s queue worker runs.

## API & auth conventions
- `routes/api.php`: public throttled `POST /api/register` + `/api/login`; then `auth:api` group: `GET /api/user`, `POST /api/logout`, `POST /api/email/verification-notification`. Guard `api` → driver `jwt` (`config/auth.php`).
- JWT: HS256, `JWT_TTL=10080` (7 days). **No refresh endpoint** by design.
- Rate limiters in `app/Providers/FortifyServiceProvider.php`: login 5/min, register 5/min, two-factor 5/min, passkeys 10/min.
- Email verification is **custom and stateless**: `GET /email/verify/{id}/{hash}` in `routes/web.php` → `App\Http\Controllers\Auth\EmailVerificationController`, middleware `['signed','throttle:6,1']`, name `verification.verify`. It marks verified and redirects to `FRONTEND_URL/email-verified` (204 for JSON). `Features::emailVerification()` is disabled in `config/fortify.php` so Fortify does NOT register its `auth:web`-gated verify route (that was the bug where links bounced to login).
- Verification email is sent via `Registered` → `SendEmailVerificationNotification` listener in `AppServiceProvider`.
- Signed links are host-bound: `APP_URL` must match the host users actually click, or verify links 403. Production `APP_URL` must be the exact Render backend URL.
- `bootstrap/app.php` sets `redirectGuestsTo(FRONTEND_URL.'/login')`. This overrides Laravel 13's default `route('login')` — without it, unauthenticated non-JSON requests throw `RouteNotFoundException` (there is no `login` route).
- JSON error rendering is enabled only for `api/*` paths or `expectsJson()` (`shouldRenderJsonWhen` in `bootstrap/app.php`).

## Deploy (Render + Docker)
- `Dockerfile` is intentionally minimal: single-stage `php:8.4-cli-alpine` + `pdo_pgsql`, runs `php artisan migrate --force` then `php artisan serve`. No nginx, no queue worker, no healthcheck → set `QUEUE_CONNECTION=sync` on Render.
- Health check endpoint: `GET /up` (checks DB connectivity). Set Render health check path to `/up`.
- `.dockerignore` excludes `.env`, `vendor/`, `node_modules/`; Render supplies all env vars itself: `APP_KEY`, `JWT_SECRET`, `JWT_TTL`, `DB_*` (pooler creds), `FRONTEND_URL`, `APP_URL`, `APP_DEBUG=false`, `APP_ENV=production`, `QUEUE_CONNECTION=sync`.

## Repo notes
- `.env`, `vendor/`, `node_modules/` gitignored; `.env` exists locally with real Supabase/JWT creds.
- No CI, no `opencode.json`, no app seeders.
- Repo-local OpenCode skills under `.opencode/skills/` (`laravel-expert`, `php-pro`, `api-*`, `database-*`, security) — load the matching skill before API/DB/security work.

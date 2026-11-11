# Heavy Vehicle Time-Restricted Movement Permit System

Permit application, review, payment, issuance and verification system for the Addis Ababa
City Administration Traffic Management Authority.

**Status:** Milestones 1 (Foundation) and 2 (Organizations & Vehicles) of 9 are complete.
See [docs/plans/](docs/plans/) for what exists and [docs/DECISIONS.md](docs/DECISIONS.md)
for assumptions and open questions.

## Stack

Laravel 12 · PHP 8.3+ · PostgreSQL 16 · Inertia 2 + React 19 + TypeScript · Tailwind 4 ·
Fortify (2FA) · spatie/laravel-permission · Pest · Vitest · Vite

## Requirements

- PHP 8.3+ with `pdo_pgsql`, `intl`, `mbstring`, `bcmath`
- Composer 2, Node 22+, Docker (for PostgreSQL and Redis)

## Setup

```bash
docker compose up -d                 # PostgreSQL 16 on 127.0.0.1:54320, Redis on 6379
composer install
npm install
cp .env.example .env                 # already points at the compose database
php artisan key:generate
php artisan migrate --seed           # roles, vehicle types; demo users in APP_ENV=local
composer dev                         # app server, queue listener, logs and Vite
```

PostgreSQL is required (later milestones use exclusion constraints and `daterange`);
keep `DB_CONNECTION=pgsql`.

Demo accounts (local only): `applicant@example.test`, `reviewer@…`, `approver@…`,
`cashier@…`, `traffic_officer@…`, `admin@…` — password `password`. Staff accounts are
sent to the two-factor setup screen on first sign-in.

## Environment variables

Everything is listed in `.env.example`. Project-specific ones:

| Variable | Default | Purpose |
|---|---|---|
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `am` / `en` | Interface language and fallback |
| `APP_DISPLAY_TIMEZONE` | `Africa/Addis_Ababa` | Timezone for displaying UTC timestamps |
| `PERMITS_STAFF_TWO_FACTOR_REQUIRED` | `true` | Mandatory 2FA for staff — keep on in production |
| `FORWARD_DB_PORT` | `54320` | Host port of the compose PostgreSQL service |

## Quality checks

```bash
php artisan test            # Pest — needs `docker compose up -d postgres`
vendor/bin/pint --test      # code style
composer analyse            # Larastan level 6
npm run types               # tsc --noEmit
npm run lint:check          # ESLint
npm test                    # Vitest
composer ci:check           # all of the above
```

The test suite always runs against the `heavy_testing` database of the compose
PostgreSQL service, whatever `.env` says, and refuses to start otherwise.

After changing a language file that is sent to the frontend, regenerate the typed
translation keys: `php artisan translations:types`.

## Deploying

Run `php artisan migrate --force` and `php artisan db:seed --force` on every deploy. The
seeders are idempotent: roles and permissions are kept in sync with the code, and vehicle
types are only added, never overwritten. Queues, scheduler,
legacy import and the production stack arrive in later milestones.

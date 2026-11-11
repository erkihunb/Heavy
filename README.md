# Heavy Vehicle Time-Restricted Movement Permit System

Permit application, review, payment, issuance and verification system for the Addis Ababa
City Administration Traffic Management Authority.

**Status:** Milestones 1 (Foundation), 2 (Organizations & Vehicles), 3 (Applications &
Workflow), 4 (Fees & Payments), 5 (Issuance, QR, PDF, Verification), 6 (Notifications &
Scheduling), 8 (Reporting & Exports) and 9 (Hardening & Deployment) are complete. The legacy
Google-Form import (Milestone 7) was removed at the owner's request: organizations register
in this system from scratch.
See [docs/plans/](docs/plans/) for what exists and [docs/DECISIONS.md](docs/DECISIONS.md)
for assumptions and open questions.

## Stack

Laravel 12 · PHP 8.3+ · MySQL 8 / MariaDB 10.4+ · Inertia 2 + React 19 + TypeScript · Tailwind 4 ·
Fortify (2FA) · spatie/laravel-permission · Pest · Vitest · Vite

## Requirements

- PHP 8.3+ with `pdo_mysql`, `intl`, `mbstring`, `bcmath`
- Composer 2, Node 22+, and MySQL 8 or MariaDB 10.4+ (your own server, or the one in `compose.yaml`)

## Setup

```bash
docker compose up -d                 # optional: MariaDB on 3306, Redis on 6379, Gotenberg (PDF) on 3000
composer install
npm install
cp .env.example .env                 # already points at the compose database
php artisan key:generate
php artisan migrate --seed           # roles, vehicle types; placeholder fees and demo users in APP_ENV=local
composer dev                         # app server, queue listener, logs and Vite
```

The database is MySQL / MariaDB (`DB_CONNECTION=mysql`). The account in `.env` needs the
`TRIGGER` privilege: two rules (no overlapping permits for a vehicle, append-only status
history) are enforced by triggers. The tests need a second database on the same server:
`CREATE DATABASE heavy_testing;`

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
| `FORWARD_DB_PORT` | `3306` | Host port of the compose MariaDB service |
| `PERMITS_VERIFY_URL` | `APP_URL` | Public HTTPS address printed in QR codes (`<url>/v/<token>`) |
| `GOTENBERG_URL` | `http://127.0.0.1:3000` | PDF renderer (`docker compose up -d gotenberg`) |
| `PERMITS_PDF_RENDERER` | `gotenberg` | `fake` on machines without Gotenberg |
| `PERMITS_DOCUMENT_LOGO_PATH` | — | Official logo for the PDF (placeholder until set) |
| `PERMITS_EXPORTS_KEEP_DAYS` | `7` | Days a spreadsheet export is kept before `exports:prune` deletes it |
| `TRUSTED_PROXIES` | — | Reverse proxies in front of the app (`*` or a list of IPs) so HTTPS and client IPs are seen |
| `SENTRY_LARAVEL_DSN` | — | Error tracking; off when empty |
| `SMS_DRIVER` / `SMS_HTTP_*` | `log` | SMS provider defaults (also in System settings → SMS) |
| `TELEGRAM_*` | off | Staff alert defaults (also in System settings → Telegram) |
| `PERMITS_THEME` / `PERMITS_DEFAULT_MODE` | `navy` / `system` | Colour theme and default light/dark mode (also in System settings) |

Most `PERMITS_*`, mail, SMS and Telegram values are only defaults: an Admin can override them
in **System settings** without a deployment.

## Quality checks

```bash
php artisan test            # Pest — needs the heavy_testing database
vendor/bin/pint --test      # code style
composer analyse            # Larastan level 6
npm run types               # tsc --noEmit
npm run lint:check          # ESLint
npm test                    # Vitest
composer ci:check           # all of the above
```

The test suite always runs against the `heavy_testing` database of the compose
MySQL server from `.env`, never the application's own database, and refuses to start otherwise.

After changing a language file that is sent to the frontend, regenerate the typed
translation keys: `php artisan translations:types`. After changing a PHP enum the frontend
uses, regenerate its TypeScript type: `php artisan enums:types`.

## Deploying

The production stack is Docker (spec §16): `Dockerfile` builds the `app` image (PHP-FPM,
also used by the queue worker and the scheduler) and the `web` image (nginx);
`compose.production.yaml` runs them with MariaDB, Redis, Gotenberg and a backup service.
TLS ends at the reverse proxy or load balancer in front of `web`.

```bash
cp .env.production.example .env.production     # fill every value marked CHANGE
docker compose -f compose.production.yaml --env-file .env.production up -d --build
docker compose -f compose.production.yaml --env-file .env.production run --rm app php artisan migrate --force
docker compose -f compose.production.yaml --env-file .env.production run --rm app php artisan db:seed --force
```

Open the first Admin account; the command prints a link to choose its password (demo
users are seeded only locally):

```bash
docker compose -f compose.production.yaml --env-file .env.production run --rm app php artisan admin:create admin@example.gov.et --name="Full Name"
```

Sign in, turn on two-factor authentication, and work through **Admin → System settings**:
the overview lists what still needs attention (placeholder restricted-hours text, e-mail
and SMS providers, error tracking). Further staff accounts are opened in **Admin → Users**.

- **Every deploy:** build, `up -d`, then `migrate --force` and `db:seed --force`. The
  seeders are idempotent: roles and permissions follow the code; vehicle types are only
  added. No fees are seeded outside `APP_ENV=local`: an Admin enters the official fee
  schedule before the first application can be approved.
- **Migrations without downtime:** add columns as nullable or with a default, backfill in a
  job, and only then make them required; never rename or drop a column in the same deploy
  that stops using it; build indexes on large tables in a separate deploy.
- **Private files** (permit PDFs, receipts, exports) live on the `documents` disk in the
  `storage` volume. It is never web-served.
- **Health:** `GET /up` checks the database, the cache and the queue connection and answers
  500 if one is down. The `web` container's health check uses it.
- **Errors:** set `SENTRY_LARAVEL_DSN` to send errors to Sentry; personal data and SQL
  values are never sent. Logs are JSON lines on stderr (`LOG_STACK=json`).
- **Queues:** Redis with the `worker` container (`queue:work`, restarted by Docker). Horizon
  is not used (owner's decision; see docs/DECISIONS.md, Milestone 9).

### Backups and restore

The `backup` service writes a database dump and an archive of the private files every day
at `BACKUP_AT` (UTC, default 00:00 = 03:00 in Addis Ababa) to `BACKUP_PATH` (default
`./backups`), and deletes those older than `BACKUP_RETENTION_DAYS` (30). Copy that folder to
another machine regularly. A backup on demand:

```bash
docker compose -f compose.production.yaml --env-file .env.production exec backup /bin/bash /backup/backup.sh
```

To restore, stop the application, restore, start again and check `/up`:

```bash
docker compose -f compose.production.yaml --env-file .env.production stop app worker scheduler web
docker compose -f compose.production.yaml --env-file .env.production exec backup /bin/bash /backup/restore.sh /backups/db-YYYYMMDD-HHMMSS.sql.gz /backups/files-YYYYMMDD-HHMMSS.tar.gz
docker compose -f compose.production.yaml --env-file .env.production start app worker scheduler web
```

The procedure was tested on 2026-11-12: a dump of the development database restored into
an empty database gave the same row counts and all six append-only triggers, and the
files archive restored the documents folder.

## System settings

Admins change most behaviour without a deployment under **Admin → System settings**:
general rules and the restricted-hours text, appearance and colour theme, notifications,
e-mail (SMTP), SMS provider, Telegram staff alerts, security limits and localization. A
saved value overrides the `.env` default at once (also in running queue workers), is
recorded in the audit log, and secrets are stored encrypted with `APP_KEY` — rotating the
key means entering them again. The overview page shows the state of every service.

## Queue and scheduler

Permit PDFs, spreadsheet exports and all e-mails / SMS are queued. Run a worker and the
scheduler:

```bash
php artisan queue:work               # or `composer dev`, which runs a queue listener
php artisan schedule:work            # or cron: * * * * * php artisan schedule:run
```

| When (Africa/Addis_Ababa) | Command | What |
|---|---|---|
| 00:05 daily | `permits:expire` | Issued permits whose last day has passed become *expired* |
| 08:00 daily (`PERMITS_EXPIRING_REMINDER_TIME`) | `permits:notify-expiring` | "Expires soon" e-mail + SMS, once per permit, `PERMITS_EXPIRING_REMINDER_DAYS` (7) days ahead |
| 03:00 daily | `exports:prune` | Deletes spreadsheet exports (and their files) older than `PERMITS_EXPORTS_KEEP_DAYS` (7) |

All three commands are safe to run by hand at any time. Until an SMS provider is chosen,
`SMS_DRIVER=log` writes text messages to the log with the number masked.

# Heavy Vehicle Time-Restricted Movement Permit System

Permit application, review, payment, issuance and verification system for the Addis Ababa
City Administration Traffic Management Authority.

**Status:** Milestones 1 (Foundation), 2 (Organizations & Vehicles), 3 (Applications &
Workflow), 4 (Fees & Payments), 5 (Issuance, QR, PDF, Verification), 6 (Notifications &
Scheduling), 7 (Legacy Import) and 8 (Reporting & Exports) of 9 are complete.
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

Run `php artisan migrate --force` and `php artisan db:seed --force` on every deploy. The
seeders are idempotent: roles and permissions are kept in sync with the code, and vehicle
types are only added, never overwritten. No fees are seeded outside `APP_ENV=local`: an
Admin must enter the official fee schedule before the first application can be approved.
Payment receipts are stored on the private `documents` disk (`storage/app/documents` by
default, `PERMITS_DOCUMENTS_DISK`); it must never be web-served and must be backed up with
the database. The legacy import and the production stack (Horizon, Docker images) arrive
in later milestones.

## Importing the legacy Google Form data

Download the sheet "Form Responses 1" as CSV (File → Download → Comma-separated values),
then either use **Admin → Legacy import** or the command line:

```bash
php artisan legacy:import form-responses.csv --dry-run --user=admin@example.org   # check first
php artisan legacy:import form-responses.csv --user=admin@example.org             # then import
```

Review the issues CSV of the dry run (path printed at the end; also downloadable on the
Admin screen), add missing vehicle-type spellings to `database/import/vehicle_type_map.php`,
and run it again. Re-running never creates duplicates. See docs/DECISIONS.md, Milestone 7.

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

# Heavy Vehicle Time-Restricted Movement Permit System

Permit application, review, payment, issuance and verification system for the Addis Ababa
City Roads Authority.

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
`cashier@…`, `traffic_officer@…`, `registrar@…`, `admin@…` — password `password`. Staff accounts are
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
| `PERMITS_PDF_RENDERER` | `chrome` | `fake` on machines without Chrome (blank placeholder PDFs) |
| `CHROME_BINARY` | `google-chrome` | Google Chrome or Chromium that prints permit PDFs: its path, or its name on the PATH |
| `PERMITS_DOCUMENT_LOGO_PATH` | — | Logo file for the PDF; otherwise the logo uploaded in System settings → General is used (placeholder when neither) |
| `PERMITS_SUPPORT_EMAIL` / `PERMITS_SUPPORT_PHONE` | — | Support contacts shown on every page; also editable in System settings → General |
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
  seeders are idempotent: new permissions are created and given to the default roles that
  list them, but what an Admin changed on the Roles screen is kept (see Users, roles and
  permissions below); vehicle types are only added. No fees are seeded outside `APP_ENV=local`: an Admin enters the official fee
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

## Users, roles and permissions

Ported from EUISIS. Under **Admin → Users and audit**:

- **Users** (`/users`): open staff accounts (the person gets an e-mail link to choose
  a password, then must turn on two-factor authentication), give them **one or more
  roles**, set the branch they work at, and **deactivate** or **restore** an account.
  Deactivating keeps the account and its history; the person is signed out and cannot sign
  in. Nobody changes their own roles or deactivates themselves, and the last active
  administrator is always kept. Applicants are registered at the office (Applicants) and are listed only.
- **Roles** (`/roles`): the default roles (`App\Enums\Role`, marked *system*: their
  code name cannot change and they cannot be deleted) and any role an Admin creates, with
  an Amharic name and a picker of permissions grouped by module. The `admin` role always
  holds every staff permission. A role nobody holds can be deleted.
- **Permissions** (`/permissions`): the read-only catalogue
  (`App\Support\Rbac\PermissionCatalog`, equal to `App\Enums\Permission` — a test keeps
  them equal) and which roles hold each.

**Where staff see applications (branch scope).** Each member of staff has scope rows: the
head office (applications filed without a branch) and/or branches, set on the Branches
card of their page. Their branch-scoped roles apply only there; with no row they see
nothing (fail closed). A role whose scope type is **global**, and the `admin` role, see
every branch. Only an Admin creates or gives a global role. A new account starts with the
branch it works at, or — at the head office — the head office and every branch that exists
then; a branch added later must be added to the people who should see it.

**Adding a permission:** add the case to `App\Enums\Permission`, list it in
`PermissionCatalog::MODULES`, label it in `lang/{en,am}/rbac.php` (`permission_labels`,
`permission_descriptions`), add it to the default roles in `App\Enums\Role::permissions()`,
check it in a Policy, and deploy: `db:seed --force` creates it and gives it to those roles.

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

Permit PDFs are printed by the queue worker with headless Google Chrome installed on the
server; nothing else needs to run. On Ubuntu, install Google's own package (the snap
Chromium cannot be run by the web server's user) and point `CHROME_BINARY` at it:

```bash
wget https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb
sudo apt install -y ./google-chrome-stable_current_amd64.deb   # CHROME_BINARY=/usr/bin/google-chrome
```

If one is missing or is a placeholder
(written while `PERMITS_PDF_RENDERER=fake`), the download link shows it as being prepared;
rebuild it with the real renderer:

```bash
php artisan permits:documents --dry-run   # list them
php artisan permits:documents --now       # rebuild and render at once
```

After a new logo, `php artisan permits:documents --all --now` rebuilds the documents that
are in order too. Each gets a new QR code, so a copy printed before stops verifying.

## Signed-in layout and UI kit

The signed-in layout is an admin shell ported from a sister system, without any of its modules: sidebar
(collapsible to an icon rail, a drawer on phones), top bar with the Ctrl+K page search,
bell, light/dark and interface style, EN/አማ switch and account menu, breadcrumbs,
heading band and toasts. It lives in `resources/js/shell/` (`Layouts/`, `Components/`,
`Components/ui/`, `hooks/`, `lib/`, `contexts/`), its UI kit in `packages/app-ui`
(imported as `@app/ui`), its styles in `resources/css/shell.css`, its text in
`lang/{en,am}/shell.php`. The Dashboard uses it; the other pages still use
`layouts/app-layout.tsx`.

**The menu** is one file, `resources/js/config/navigation.ts`: sections → groups →
pages. The sidebar, the breadcrumbs and the Ctrl+K palette all read it. A page:

```ts
{ href: reports().url, labelKey: 'nav.reports', icon: ChartIcon, permission: 'reports.view' }
```

`href` comes from a Wayfinder route, `labelKey` is a translation key, `icon` is exported
by `resources/js/shell/Components/NavIcons.tsx` (add a Solar icon there with `adapt()`),
and `permission` / `anyPermission` hide it from people without that permission
(`app/Enums/Permission.php`; the server still authorizes every route). A group whose
person sees only one page shows as a plain link. A page is highlighted when the address
is its `href` or below it (`/queue/12` → Review queue); `owns: ['/other/path']`
claims other paths.

**Putting a page in the layout:**

```tsx
import PageHeader from '@/shell/Components/PageHeader';
import AuthenticatedLayout from '@/shell/Layouts/AuthenticatedLayout';

export default function Reports() {
    return (
        <AuthenticatedLayout header={<PageHeader title="…" description="…" actions={…} />}>
            <Head title="…" />
            {/* the page; the layout sets width and padding */}
        </AuthenticatedLayout>
    );
}
```

Breadcrumbs follow the menu (home › group › page › Edit / Create / Details); a page the
menu does not cover passes its own with `breadcrumbs={[{ label, href }]}`. Flash
messages (`->with('success', …)`, `->with('warning', …)`) show as toasts; in the browser,
`import { toast } from '@/shell/lib/toast'` and call `toast.success('…')`.

### UI kit

Cards, forms, buttons, badges, table styles, pagination, filter panels, the row action
menu and toasts, the same in light and dark mode and in both languages.

**Install:** nothing to add. The kit is a local package, `packages/app-ui`, resolved
through the `@app/ui` alias (`tsconfig.json`, `vite.config.ts`, `vitest.config.ts`); its
npm dependencies (`@headlessui/react`, `sonner`, `cmdk`) are in `package.json`.

**Import paths:**

| What | Import |
|---|---|
| Kit primitives (Button, Card, inputs, badges, table class names) | `@app/ui` |
| Kit + app wrappers (action menu, numbered pagination…) in one import | `@/shell/Components/ui` |
| Tables in pages | `@/components/data-table` (`TableCard`, `Table`, `THead`, `TBody`, `Tr`, `Th`, `Td`, `EmptyRow`) and `@/components/pagination` |
| Other components | `@/shell/Components/<Name>` (ConfirmationDialog, ConfirmProvider, StatCard, PageHeader, EmptyState, UserAvatar) |
| Icons | `@/shell/Components/NavIcons` (menu, toolbar), `lucide-react` (page content) |
| Toasts / confirm | `@/shell/lib/toast`, `@/shell/hooks/useConfirm` (inside `<ConfirmProvider>`) |

The kit's own labels (pagination, filters, table tools, upload) come from
`lang/{en,am}/shell.php` through `LocalizedUiProvider`, which the layout mounts.

**Buttons and badges**

```tsx
<Button>{t('shell.actions.save')}</Button>                    {/* "Save" gets its icon by itself */}
<Button variant="outline" size="sm" loading={form.processing}>…</Button>
<Tooltip content="Print"><Button size="icon" variant="ghost" aria-label="Print"><PrinterIcon className="h-4 w-4" /></Button></Tooltip>
<span className={badgeClassName('warning')}>…</span>         {/* or <StatusBadge tone="warning"> */}
```

Variants: `default primary secondary outline ghost destructive destructiveOutline link`;
sizes: `xs sm default lg icon`.

**Cards and figures**

```tsx
<Card><CardHeader><CardTitle>…</CardTitle></CardHeader>…<CardFooter>…</CardFooter></Card>
<StatCard label="Waiting for review" value={37} tone="warning" />
```

**Forms** (errors show under their field; the first invalid field gets focus after a submit)

```tsx
<FormField label="E-mail" required error={form.errors.email}>
    {({ id, describedBy, invalid }) => (
        <Input id={id} type="email" aria-describedby={describedBy} aria-invalid={invalid}
            value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
    )}
</FormField>
<FileUpload label="Registration document" accept=".pdf" maxSizeMb={2}
    value={form.data.document} error={form.errors.document} onChange={(file) => form.setData('document', file)} />
```

Also `Select`, `Textarea` and `SearchInput`.

**Tables and pagination**

```tsx
<TableCard title="Permits" footer={<Pagination links={list.links} meta={list.meta} />}>
    <Table>
        <THead><Tr><Th>Plate</Th><Th>Status</Th></Tr></THead>
        <TBody>{list.data.map((row) => <Tr key={row.id}><Td>{row.plate}</Td><Td>…</Td></Tr>)}</TBody>
    </Table>
</TableCard>
<ListPagination meta={list.meta} />   {/* numbered pages, from '@/shell/Components/ui' */}
<AppActionMenu items={[{ label: 'Edit', href: edit(row.original).url }]} />
```

`ListPagination`'s `meta` is a Laravel paginator's page fields (`current_page`,
`last_page`, `per_page`, `total`, `from`, `to`). Wrapped in `<TableTools>`, a table of
three or more columns lets the viewer sort the page by a header, hide columns, choose
row density and keep the header visible.

**Confirm and feedback**

```tsx
const { confirm } = useConfirm();
const { confirmed, reason } = await confirm({ title: 'Revoke this permit?', variant: 'danger', requireReason: true });
<Alert tone="warning" title="…">…</Alert>   <ErrorState title="…" />   <EmptyState title="…" />
toast.success('Saved');                     // or a server flash: ->with('success', …)
```

**Changing the brand colour:** don't edit components. Set the colour in **Admin → System
settings → Appearance** (or `PERMITS_THEME` / `PERMITS_PRIMARY_COLOR` in `.env`). Every
component reads it through the tokens (`--primary` in `resources/css/app.css`, mapped to
the kit's names in `packages/app-ui/src/tokens.css` and `resources/css/shell.css`), and
the hover and dark-mode variants are derived from it.

**Adding an icon:** for page content, import it from `lucide-react`. For the menu or
toolbar, add a Solar icon to `NavIcons.tsx`:
`import { GarageIcon as SolarGarageIcon } from '@solar-icons/react/linear/garage';` then
`export const GarageIcon = adapt(SolarGarageIcon);` (names: `node_modules/@solar-icons/react/dist/icons/linear/`).

**Brand colour and logo:** both come from **Admin → System settings**. Appearance sets the
colour preset or a custom brand, accent and sidebar colour (`PERMITS_THEME`,
`PERMITS_PRIMARY_COLOR`, `PERMITS_SIDEBAR_COLOR`); General sets the logo. The shell
receives them as the `settings` shared prop (`App\Domain\Settings\ShellSettings`) and
derives the hover, dark-mode and sidebar-selection colours with
`resources/js/shell/lib/brandColor.ts`. With no logo uploaded, the sidebar
shows the placeholder mark in `resources/js/components/brand-mark.tsx`. The product name
is `common.app_name` in `lang/{en,am}/common.php`.

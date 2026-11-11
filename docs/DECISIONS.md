# Decisions, assumptions and open questions

Every place where the specification was ambiguous, where reality differed from it, or
where a default had to be chosen. Newest milestone last. Items marked **CONFIRM** need
an answer from the Authority before go-live.

## Milestone 1 — Foundation

### Stack and versions

| Topic | Decision | Why |
|---|---|---|
| Laravel version | **Laravel 12** (`^12.0`), as specified. | The spec names Laravel 12. The official React starter kit's `main` branch has since moved to Laravel 13 / Inertia 3, so the project is based on the last starter-kit revision that targets Laravel 12 (`laravel/react-starter-kit@d6145cb`, 2026-03-10). **CONFIRM:** Laravel 12 is in its security-fix-only window; check the end date in Laravel's support policy and schedule the upgrade to 13 before it. |
| React version | **React 19**, not 18. | The official starter kit ships React 19 (with the React Compiler). Downgrading the kit to 18 would diverge from the supported base for no benefit. |
| Routing helpers | **Wayfinder** (shipped by the kit), not Ziggy. | The spec allows either. Generated files (`resources/js/routes`, `actions`) are git-ignored and produced by `php artisan wayfinder:generate --with-form` / the Vite plugin. |
| PHP | `^8.3` | Spec minimum; typed class constants are used. |
| Tests | Pest 4 (PHPUnit 12). The kit's PHPUnit tests were converted to Pest. | Spec. |
| Licence field | `composer.json` says `proprietary`. | Placeholder — **CONFIRM** the licence the Authority wants. |

### Packages added beyond the starter kit

| Package | Reason |
|---|---|
| `spatie/laravel-permission` | Named in spec §2. |
| `pestphp/pest`, `pestphp/pest-plugin-laravel` | Named in spec §0. |
| `larastan/larastan` | Named in spec §12 (CI). |
| `vitest`, `@testing-library/react`, `@testing-library/dom`, `@testing-library/user-event` | Named in spec §17. `user-event` and `dom` are the companions needed to test keyboard interaction. |
| `jsdom` | **Not in the spec.** DOM implementation Vitest needs to render components. Dev-only. |
| `@fontsource-variable/noto-sans-ethiopic` | **Not in the spec.** This is how Noto Sans Ethiopic is self-hosted (spec §6): the OFL-licensed font files come from npm and are bundled by Vite into `public/build`, so no font CDN is contacted. |

No Ethiopian-calendar package was added: `App\Support\EthiopianCalendar` is a small
native implementation (integer arithmetic over the Julian Day Number).

### Database

- PostgreSQL 16 from `compose.yaml`, published on host port **54320** (not 5432) so it
  cannot clash with a locally installed server. Override with `FORWARD_DB_PORT`.
- The compose credentials (`heavy` / `secret`) are fixed local-development values and are
  deliberately **not** read from the app's `DB_*` variables, so a real password in `.env`
  never ends up in a container definition.
- **Tests never use the database in `.env`.** `phpunit.xml` forces the suite onto
  `pgsql` → `heavy_testing` on port 54320, and `Tests\TestCase` refuses to run unless the
  connection is PostgreSQL and the database name ends in `_testing` (the suite wipes the
  database it is pointed at).
- Timestamps are stored in UTC (`app.timezone`). `localization.display_timezone`
  (`Africa/Addis_Ababa`) is the timezone for showing them and for turning an instant into
  a calendar date.

### Ethiopian calendar

- `EthiopianCalendar::toGregorian()` returns a `CarbonImmutable` at midnight **UTC**;
  callers store/compare `->toDateString()`.
- `toEthiopian()` uses the calendar date of the given instant *in that instant's own
  timezone*. Convert a UTC timestamp to the display timezone first (a test pins this).
- Numeric format `DD/MM/YYYY`, label format `መስከረም 28, 2018` (`Meskerem 28, 2018` in English).
- English month spellings (Meskerem, Tikimt, Hidar, Tahsas, Tir, Yekatit, Megabit, Miazia,
  Ginbot, Sene, Hamle, Nehase, Pagume) are a transliteration choice — **CONFIRM**.

### Plate numbers (`App\Domain\Vehicles\PlateNumber`)

- Canonical form `<region>-<code>-<number>`, e.g. `አአ-03-A45301`.
- Normalisation: Unicode NFKC, every dash / whitespace / underscore run becomes one `-`,
  Latin letters upper-cased, the code zero-padded to two digits. The separator between
  region and code is optional; the one between code and number is required (otherwise
  `ኢት-0340134` is ambiguous).
- **Region prefix is a controlled list** (`App\Enums\PlateRegion`), stored in its Ethiopic
  form. A Latin spelling (`AA`, `ET`, …) is accepted on input and mapped to the Ethiopic
  code, so `AA-3-a45301` and `አአ-03-A45301` are the same vehicle.
  **CONFIRM:** the region list and Latin codes are a placeholder to be checked against
  the official register; unknown prefixes are rejected.
- Accepted shapes — code: 1–2 digits; number: an optional single Latin letter followed
  by 4–6 digits. **CONFIRM** against real plates; anything else is rejected (and will
  surface as an import issue in Milestone 7 rather than being silently accepted).
- A value containing two plates (`…/ …`) is rejected; splitting is the importer's job.

### Roles, permissions and registration

- Roles and permissions are PHP enums (`App\Enums\Role`, `App\Enums\Permission`); the
  mapping lives in `Role::permissions()` and is synced by the idempotent
  `RolesAndPermissionsSeeder` (run it on every deploy). Policies check permissions.
- **Admin is not a super-user.** It holds only the permissions listed for Admin in
  spec §2; it cannot review, approve, revoke, verify payments or scan. This is the
  conservative reading and supports separation of duties.
- Cashier has only `payments.verify`; Approver can see the review queue but not review.
- Self-registration always creates an **Applicant**; a role in the request is ignored.
  Staff accounts will be created by an Admin (admin UI: later milestone). Until then,
  `DemoUserSeeder` creates one account per role in the `local` environment only
  (`<role>@example.test` / `password`) and throws anywhere else.
- E-mail verification is enforced (`User implements MustVerifyEmail`).
- The browser receives a whitelist of the signed-in user's fields
  (`id, name, email, email_verified_at, locale, roles`), never the serialised model.
  `roles` is for navigation only.

### Mandatory two-factor authentication for staff

- `RequireTwoFactorForStaff` (in the `web` group) redirects any staff user without
  *confirmed* 2FA to the two-factor settings screen; JSON requests get 403. Only the
  2FA setup endpoints, password confirmation, e-mail verification, language switch and
  logout stay reachable.
- Behind `permits.staff_two_factor_required` (`PERMITS_STAFF_TWO_FACTOR_REQUIRED`,
  default **true**). It exists for local development and must stay on in production.

### Localisation

- Default locale `am`, fallback `en`. Resolution order: signed-in user's saved
  `users.locale` → guest's session → default. Switching (`PUT /locale`) is open to
  guests (the public verification page will need it) and rate-limited to 30/min.
- Only the namespaces in `config/localization.php` (`frontend_namespaces`) are sent to
  the browser. `php artisan translations:types` generates the `TranslationKey` union
  that makes the frontend `t()` helper type-safe; CI fails if it is stale, and a test
  fails if Amharic and English do not have identical keys.
- **Known gap — translation coverage.** Translated so far: application shell
  (sidebar, user menu), landing page, login, registration, dashboard title, the
  "2FA required" notice, and the server-side `auth` / `passwords` messages. Still
  English: the remaining starter-kit auth and settings screens, and Laravel's
  `validation` messages (they fall back to English). These are translated as the
  screens are reworked in later milestones.
- **CONFIRM:** all Amharic text was written by the implementer and needs review by a
  native speaker, including the system name and the Authority's official name used on
  the landing page.

### Branding

- No official logo, letterhead or legal wording exists in the code base. The mark shown
  is the starter kit's placeholder icon. See open question 6.

## Open questions from the specification (§14)

Defaults that later milestones will implement as configuration. None is built yet.

| # | Question | Default that will be implemented | Milestone |
|---|---|---|---|
| 1 | Fee schedule per permit type / vehicle type / duration | `fee_schedules` table seeded with clearly marked placeholder rows; no amount is hard-coded | 4 |
| 2 | Restricted hours for "ሙሉ ቀን" vs "ይለፍ" | Free-text setting (placeholder) shown on the permit and the verification page | 5 |
| 3 | Online payment (Telebirr / Chapa) in v1? | No — manual receipt verification; a `PaymentGateway` interface is designed for later | 4 |
| 4 | Does public verification show the organisation name? | Yes; everything else listed in §7 stays hidden | 5 |
| 5 | Single-step or two-step approval? | Two-step: Reviewer → Approver | 3 |
| 6 | Official logo, letterhead and legal wording for the PDF | Placeholders from config | 5 |
| 7 | SMS provider | `SmsGateway` interface with a log driver | 6 |
| 8 | Hosting environment and data residency | Docker Compose stack that runs on-prem or in a cloud; S3-compatible private storage | 9 |

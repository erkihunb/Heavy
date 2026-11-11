# Milestone 1 — Foundation: plan

Scope (spec §12.1): starter kit, Postgres, Pest, CI, roles/permissions seeder, 2FA for
staff, locale switching, Ethiopic font, `EthiopianCalendar` + tests, `PlateNumber` + tests.
Assumptions and deviations are recorded in [../DECISIONS.md](../DECISIONS.md).

## Base

- Official Laravel React starter kit at its last Laravel 12 revision (Fortify auth and
  2FA screens, Inertia 2, Tailwind 4, Wayfinder).
- `compose.yaml`: PostgreSQL 16 (+ a `heavy_testing` database) and Redis 7.
- `phpunit.xml`: suite pinned to PostgreSQL `heavy_testing`; `Tests\TestCase` guard.

## Backend

| Area | Files |
|---|---|
| Ethiopian calendar | `app/Support/EthiopianCalendar.php`, `app/Support/EthiopianDate.php` |
| Plate numbers | `app/Domain/Vehicles/PlateNumber.php`, `app/Enums/PlateRegion.php`, `app/Exceptions/InvalidPlateNumber.php` |
| Roles & permissions | `app/Enums/Role.php`, `app/Enums/Permission.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `database/seeders/DemoUserSeeder.php`, spatie migration + `config/permission.php`, `User` uses `HasRoles` |
| Registration | `app/Actions/Fortify/CreateNewUser.php` assigns Applicant only; `User implements MustVerifyEmail` |
| Staff 2FA | `app/Http/Middleware/RequireTwoFactorForStaff.php`, `User::isStaff()`, `User::mustEnableTwoFactor()`, `config/permits.php` |
| Locale | migration `add_locale_to_users_table`, `app/Http/Middleware/SetLocale.php`, `app/Http/Controllers/LocaleController.php`, `app/Http/Requests/UpdateLocaleRequest.php`, `config/localization.php`, `lang/{am,en}/*` |
| Frontend contract | `app/Support/FrontendTranslations.php`, `app/Console/Commands/GenerateTranslationTypes.php` (`translations:types [--check]`), whitelisted shared props in `HandleInertiaRequests` |
| Defaults | `Model::preventLazyLoading()` outside production |

## Frontend

- `resources/js/lib/i18n.ts` — typed `t()` / `useTranslation()`; generated
  `resources/js/types/translations.ts`.
- `resources/js/components/language-switcher.tsx` (landing page, auth layout, app header).
- Noto Sans Ethiopic self-hosted through `@fontsource-variable/noto-sans-ethiopic`;
  the kit's font-CDN link is removed.
- Landing page, login, registration and shell localised; kit boilerplate links removed.
- Vitest + Testing Library (`vitest.config.ts`, `npm test`).

## Quality gates

- `.github/workflows/ci.yml`: Pint, Larastan level 6, translation-type check, Pest against
  a PostgreSQL 16 service, `composer audit`; ESLint, Prettier, `tsc`, Vitest, build, `npm audit`.
- `phpstan.neon` (level 6), ESLint `no-explicit-any` raised to an error.

## Tests

| Subject | File |
|---|---|
| Calendar conversions incl. leap years, Pagume 5/6, invalid dates, formatting | `tests/Unit/Support/EthiopianCalendarTest.php` |
| Plate normalisation and validation with real samples and malformed input | `tests/Unit/Domain/Vehicles/PlateNumberTest.php` |
| Role × permission matrix, idempotent seeding, demo users | `tests/Feature/RolesAndPermissionsTest.php` |
| Registration creates an Applicant only | `tests/Feature/Auth/RegistrationTest.php` |
| Staff confined to 2FA setup until confirmed | `tests/Feature/StaffTwoFactorTest.php` |
| Locale resolution, switching, validation, rate limit, shared-prop whitelist, no font CDN | `tests/Feature/LocaleTest.php` |
| Amharic/English key parity, generated types in sync | `tests/Feature/TranslationsTest.php` |
| `translate()` and `LanguageSwitcher` | `resources/js/lib/i18n.test.ts`, `resources/js/components/language-switcher.test.tsx` |

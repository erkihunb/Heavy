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

## UI design language (added after Milestone 1)

- **Direction: road signage.** Asphalt neutrals, a permit green for primary actions, one
  signal amber as the accent; sign-like shapes (large corner radius, confident borders,
  hard unblurred shadows on the few "plate" elements); lane-dash and hazard-stripe motifs
  instead of generic decoration. Tokens and utilities live in `resources/css/app.css`
  (`surface-asphalt`, `hazard-stripe`, `lane-dash`, `shadow-plate`, `text-display`,
  `text-eyebrow`). Every text/background pair in the palette was checked at ≥ 4.5:1
  (WCAG AA); most exceed 7:1.
- **One typeface.** Noto Sans Ethiopic (variable weight) for both scripts. The width axis
  was not added: it costs ~130 KB more on the Ethiopic subset, which matters on phones.
- **No official branding.** `BrandMark` and `public/favicon.svg` are a neutral placeholder
  (a truck on a sign plate). The starter kit's Laravel logo, `favicon.ico` and
  `apple-touch-icon.png` were removed. **CONFIRM:** supply the Authority's logo and colours;
  swapping them means changing the tokens and `brand-mark.tsx` only.
- **Touch targets.** Inputs are 44 px high and buttons 40–48 px, because traffic officers
  use phones.
- **Dates in the page chrome.** Today's date in both calendars is a shared Inertia prop
  (`today`), computed in the display timezone, and shown in the app header.
- **Landing page content.** The sample permit card is an illustration with placeholder
  values (`TMA-HV-0000-000000`, `አአ-00-A00000`), labelled as such; its dates are real
  calendar conversions. The "how a permit is issued" steps and the three feature
  statements describe the finished service (QR verification and the audit trail arrive in
  later milestones). **CONFIRM** the wording with the Authority.
- **Dashboard.** Shows role, account status and the process, plus an explicit notice that
  applications are not open yet. Replace the notice when Milestone 3 ships.
- **Removed starter-kit files:** the unused `auth-simple-layout` and `auth-card-layout`
  (the auth screens now use the split layout).

## Milestone 2 — Organizations & Vehicles

### Organizations

- **Ownership.** The applicant who creates the profile is its owner (`organizations.created_by`).
  Any member can view the profile; only the owner can change it. There is no separate
  `owner_id` column — the spec's schema has `created_by` only.
- **One organization per applicant**, enforced by the policy and, under a row lock, by the
  `CreateOrganization` action. There is no screen yet for adding further users to an
  organization; the data model allows it and the policies already distinguish members
  from the owner. **CONFIRM** how colleagues should join (invitation by the owner, or
  assignment by an Admin).
- **Fields.** Amharic name, phone, e-mail and address are required; English name and TIN
  are optional. TIN must be exactly 10 digits and unique when given — **CONFIRM** the TIN
  format. Phone numbers (mobile or landline) are stored as `+251` + nine digits.
- **Ids.** Organizations use ordinary integer ids because no organization id ever appears
  in a URL (`/organization` is always the signed-in user's own). Vehicles use ULIDs.

### Vehicles

- **Vehicle types are a placeholder list**: only the two named in the spec's import
  mapping (`cargo` / የጭነት, `special_mobile` / ልዩ ተንቀሳቃሽ). **CONFIRM** the full list;
  it is seeded without overwriting later edits, and an Admin screen to maintain it comes
  with the admin milestone. Caching of vehicle types is added with that screen.
- **Duplicate plates.** Checked in validation, again in the Action, and finally by the
  unique index (a lost race becomes the same validation error). The message says whether
  the plate is in your own register or registered by another organization, never which one.
- **Cross-organization access answers 404**, not 403, so a vehicle's existence is not
  revealed. Lists are always filtered by the user's organization in the query.
- **A plate can still be corrected** after registration. From Milestone 3 it must be
  locked once the vehicle is on a submitted permit — otherwise editing it would rewrite
  permit history.
- **Vehicles cannot be deleted.** Not in the spec's actions, and permits will reference
  them (`ON DELETE RESTRICT`).
- **Plate search** matches any part of the normalised plate, ignoring letter case and
  the kind of separator typed. A Latin region prefix is not translated in search
  (`AA` does not find `አአ`).
- **The plate inputs have no `maxlength`**: browsers count pasted spaces and dashes and
  would cut a plate short before it is cleaned.

### Accounts

- **Self-service account deletion is removed** (starter-kit feature). Accounts own
  organizations and will carry permit history; removal needs a retention policy.
  **CONFIRM** the policy for closing accounts.

### Frontend contract

- Resource shapes are mirrored by hand in `resources/js/types/models.ts`, and each
  Resource has a test asserting its exact fields. Generated TypeScript for enums is
  deferred to Milestone 3, where `PermitStatus` first needs it.
- `JsonResource::withoutWrapping()` is on: a single resource reaches the page as a plain
  object; paginated collections keep `data` / `links` / `meta`.
- Amharic validation messages now exist for the rules in use (`lang/am/validation.php`);
  any other rule still falls back to English.

### Deferred within this milestone

- The audit log (`spatie/laravel-activitylog`) starts with Milestone 3's state transitions.

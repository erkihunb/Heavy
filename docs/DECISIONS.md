# Decisions, assumptions and open questions

Every place where the specification was ambiguous, where reality differed from it, or
where a default had to be chosen. Newest milestone last. Items marked **CONFIRM** need
an answer from the Authority before go-live.

## Milestone 1 — Foundation

### Stack and versions

| Topic           | Decision                                                             | Why                                                                                                                                                                                                                                                                                                                                                                                                           |
| --------------- | -------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Laravel version | **Laravel 12** (`^12.0`), as specified.                              | The spec names Laravel 12. The official React starter kit's `main` branch has since moved to Laravel 13 / Inertia 3, so the project is based on the last starter-kit revision that targets Laravel 12 (`laravel/react-starter-kit@d6145cb`, 2026-03-10). **CONFIRM:** Laravel 12 is in its security-fix-only window; check the end date in Laravel's support policy and schedule the upgrade to 13 before it. |
| React version   | **React 19**, not 18.                                                | The official starter kit ships React 19 (with the React Compiler). Downgrading the kit to 18 would diverge from the supported base for no benefit.                                                                                                                                                                                                                                                            |
| Routing helpers | **Wayfinder** (shipped by the kit), not Ziggy.                       | The spec allows either. Generated files (`resources/js/routes`, `actions`) are git-ignored and produced by `php artisan wayfinder:generate --with-form` / the Vite plugin.                                                                                                                                                                                                                                    |
| PHP             | `^8.3`                                                               | Spec minimum; typed class constants are used.                                                                                                                                                                                                                                                                                                                                                                 |
| Tests           | Pest 4 (PHPUnit 12). The kit's PHPUnit tests were converted to Pest. | Spec.                                                                                                                                                                                                                                                                                                                                                                                                         |
| Licence field   | `composer.json` says `proprietary`.                                  | Placeholder — **CONFIRM** the licence the Authority wants.                                                                                                                                                                                                                                                                                                                                                    |

### Packages added beyond the starter kit

| Package                                                                                   | Reason                                                                                                                                                                                            |
| ----------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `spatie/laravel-permission`                                                               | Named in spec §2.                                                                                                                                                                                 |
| `pestphp/pest`, `pestphp/pest-plugin-laravel`                                             | Named in spec §0.                                                                                                                                                                                 |
| `larastan/larastan`                                                                       | Named in spec §12 (CI).                                                                                                                                                                           |
| `vitest`, `@testing-library/react`, `@testing-library/dom`, `@testing-library/user-event` | Named in spec §17. `user-event` and `dom` are the companions needed to test keyboard interaction.                                                                                                 |
| `jsdom`                                                                                   | **Not in the spec.** DOM implementation Vitest needs to render components. Dev-only.                                                                                                              |
| `@fontsource-variable/noto-sans-ethiopic`                                                 | **Not in the spec.** This is how Noto Sans Ethiopic is self-hosted (spec §6): the OFL-licensed font files come from npm and are bundled by Vite into `public/build`, so no font CDN is contacted. |

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
- `toEthiopian()` uses the calendar date of the given instant _in that instant's own
  timezone_. Convert a UTC timestamp to the display timezone first (a test pins this).
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
- **Admin holds every staff permission** (changed on the owner's instruction, 2026-11-11;
  the original reading kept Admin to the permissions listed for it in spec §2). Admin can
  now also review, approve, revoke, verify payments and scan. It still does not hold the
  applicant's own-organization permissions. The per-permit rules are unchanged and apply
  to an Admin like anyone else: the approver must differ from the recommender, and the
  approver must differ from the payment verifier. **CONFIRM** before go-live that a
  single all-powerful role is acceptable for separation of duties.
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
  _confirmed_ 2FA to the two-factor settings screen; JSON requests get 403. Only the
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

| #   | Question                                                | Default that will be implemented                                                           | Milestone |
| --- | ------------------------------------------------------- | ------------------------------------------------------------------------------------------ | --------- |
| 1   | Fee schedule per permit type / vehicle type / duration  | `fee_schedules` table seeded with clearly marked placeholder rows; no amount is hard-coded | 4         |
| 2   | Restricted hours for "ሙሉ ቀን" vs "ይለፍ"                   | Free-text setting (placeholder) shown on the permit and the verification page              | 5         |
| 3   | Online payment (Telebirr / Chapa) in v1?                | No — manual receipt verification; a `PaymentGateway` interface is designed for later       | 4         |
| 4   | Does public verification show the organisation name?    | Yes; everything else listed in §7 stays hidden                                             | 5         |
| 5   | Single-step or two-step approval?                       | Two-step: Reviewer → Approver                                                              | 3         |
| 6   | Official logo, letterhead and legal wording for the PDF | Placeholders from config                                                                   | 5         |
| 7   | SMS provider                                            | `SmsGateway` interface with a log driver                                                   | 6         |
| 8   | Hosting environment and data residency                  | Docker Compose stack that runs on-prem or in a cloud; S3-compatible private storage        | 9         |

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
  values (`AACRA-0000-000000`, `አአ-00-A00000`), labelled as such; its dates are real
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

- The audit log (`spatie/laravel-activitylog`) is deferred to the hardening milestone; from Milestone 3 state transitions are recorded in `permit_status_transitions`.

## Milestone 3 — Applications & Workflow

Plan: [plans/milestone-3-applications-workflow.md](plans/milestone-3-applications-workflow.md).

### Status and lifecycle

- **`active` is computed, never stored.** A permit is stored as `issued`;
  `Permit::effectiveStatus($at)` turns it into `active` from `valid_from` and `expired`
  after `valid_to` (dates in the display timezone). Lists, the status filter
  (`inEffectiveStatus`) and Resources all go through it, so verification (Milestone 5)
  has one method to call. `permits:expire` (Milestone 6) will store `expired`.
- **Every status change goes through `PermitTransitions`**: lock the permit row → ask
  `PermitStateMachine` → save → sync `permit_vehicles` → write the history row, in one
  transaction. Actions never set `status` themselves.
- **History is append-only at database level**: a trigger on
  `permit_status_transitions` raises on `UPDATE` and `DELETE`. The function is created
  with `CREATE OR REPLACE` because `migrate:fresh` drops tables but not functions.
- **Drafts are complete applications.** A draft needs every field (type, primary vehicle,
  dates, reason); there are no half-filled drafts to validate again on submission.
- **`submitted_at` is reset on resubmission** after changes were requested, so the queue
  age (SLA) counts from when the Authority last received the application.
- **Withdrawal** is possible from `draft` and `submitted` only (spec §3), by the
  applicant's organization.

### Approval (open question §14.5)

- **Two-step by default** (`permits.two_step_approval`, env `PERMITS_TWO_STEP_APPROVAL`):
  a Reviewer records a _recommendation_ (approve / reject, note required for reject) on
  the permit while it is under review; an Approver then decides. The recommendation is
  not a status of its own — the spec's state machine is unchanged.
- **The Approver must not be the person who recommended** (`ApprovalRules`). With the
  setting off, an Approver can decide without a recommendation.
- **Staff policy abilities check permission and visibility only**; whether the action is
  possible _now_ is the state machine's job. The Resource's `can` map combines both so
  the UI only offers what will succeed; the server re-checks everything.
- **Staff never see drafts or withdrawn applications** (policy answers "not found").
- Rejecting and requesting changes require a note; the applicant sees it in the history.
  Applicants never see which member of staff acted.

### Vehicles, overlap and numbers

- One **primary** vehicle and up to `permits.max_trailers` (default 2) **trailers**, all
  from the applicant's own organization. A partial unique index guarantees one primary.
- **Overlap** (spec §4.1) is enforced twice: `OverlapGuard` in the Action (vehicle rows
  locked) and the `btree_gist` exclusion constraint on `permit_vehicles`. A permit is
  _blocking_ from approval (`awaiting_payment`) until it is revoked or expired; `period`
  is an inclusive `daterange`. Applications still under review do not block each other —
  the staff detail page shows the vehicle's other permits and flags conflicts.
- **The plate of a vehicle is locked** once it is on any application that is not a draft
  or withdrawn: the plate on a submitted or issued permit must not change underneath it.
- **Permit number** `AACRA-<Ethiopian year>-<6 digits>` is assigned at approval from
  `permit_number_sequences` under a row lock. The year is the Ethiopian _calendar_ year
  of the approval date. **CONFIRM** calendar year vs fiscal year (spec says either), and
  the prefix (`permits.number_prefix`).
- **The number rule is a setting** (2026-11-12, System settings → General → Permit
  numbers): prefix, the order of `{prefix}`, `{year}`, `{seq}` (`permits.number_format`)
  and the counter's digits (4–8). `{year}` and `{seq}` are mandatory because the counter
  is per year; only capitals, digits and hyphens are allowed (look-ups upper-case their
  input, the number is a file name). A change applies to later approvals only.
- **Branch offices** (owner's request, 2026-11-12; Administration → Branches): a code of
  2–6 capitals/digits fixed at creation, names in both languages, closed rather than
  deleted. Managed by whoever holds `settings.manage` (no new permission).
- **Branch scope** (owner's request, 2026-11-12). Staff are placed at one branch or none
  by an Admin (`users.branch_id`, audited as `user.branch_changed`). **Admins and staff
  with no branch (the head office) see the whole institution; everyone else sees and
  acts on their own branch only** (`User::branchScope()`, `Permit::scopeInBranchOf()`):
  review queue, permit pages and actions, payment queue and decisions, reports, the
  dashboard and exports (the branch is written into the export by the server). Another
  branch's permit or payment answers 404.
    - An application gets its branch when it is started (`permits.branch_id`): the
      applicant chooses it on the form (empty = head office), a walk-in application takes
      the Registrar's branch. A head-office application is seen by the head office only.
    - `{branch}` in the number rule prints that branch's code (`AACRA-BOL-2018-000123`,
      the default rule) and is dropped with its hyphen for the head office. The counter
      stays **one per Ethiopian year for all branches**, so numbers are unique whatever
      the rule says.
    - Not scoped on purpose: traffic officers (the roadside is not a branch), the public
      verification page, the applicant directory of Registrars (a carrier may go to any
      branch; the applications inside it are scoped), and the "other permits of this
      vehicle" list on the review page (overlaps must be seen across branches).
    - **CONFIRM** with the Authority: whether each branch should count from 1 on its
      own, and whether an online applicant must choose a branch.
- **Validity** (spec §4.6): `valid_from <= valid_to` (also a CHECK constraint), at most
  `permits.max_duration_days` (default 90, counted inclusively), not starting before
  today in the display timezone. The staff override for past dates is deferred until
  staff can create applications.

### Frontend

- **One long form instead of a multi-page wizard**: four numbered sections (type →
  vehicles → dates → reason) on one screen, saved as a draft; "review & submit" is the
  detail page. Fewer round-trips on slow connections and nothing is lost between steps.
- **`EthiopianDateInput`** (three native selects, Pagume included) is the only place the
  frontend converts dates; `resources/js/lib/ethiopian-calendar.ts` mirrors
  `App\Support\EthiopianCalendar` and is tested with the same known pairs. It offers the
  current Ethiopian year and the next one by default.
- **`resources/js/types/enums.ts` is generated** by `php artisan enums:types`; CI runs it
  with `--check`. Resource shapes are still mirrored by hand in `types/models.ts` and
  pinned by Resource tests (`spatie/laravel-typescript-transformer` not added).
- Withdrawing has no confirmation dialog; the button is separated from the main actions
  and labelled as final. **CONFIRM** whether a confirmation step is wanted.

### Deferred within this milestone

- Fee snapshot at approval → Milestone 4. Revocation UI → Milestone 5 (the Action,
  policy and transition exist and are tested). Stored `expired` → Milestone 6.
- `spatie/laravel-activitylog` → hardening milestone; state transitions are already
  recorded in `permit_status_transitions` (who, from, to, when, note).

### Known limits and points to confirm

- **Every transition note is shown to the applicant**, including an Approver's optional
  note on approval. **CONFIRM** whether staff need a separate internal note.
- **An Approver may approve an application whose recommendation is "reject"** (and the
  reverse): the recommendation is advice, the decision is the Approver's. **CONFIRM**.
- **The revocation reason** reaches the applicant only as the note of the `revoked`
  history step. Decide with the revocation UI (Milestone 5) whether it needs its own field.
- **Plate lock race**: `UpdateVehicle` checks `hasLockedPlate()` without locking the
  vehicle row, so a plate change racing a submission of the same vehicle could slip
  through. To be closed in the hardening milestone (lock the vehicle row in both Actions).
- **Permit numbers** are zero-padded to six digits; beyond 999,999 in one Ethiopian year
  the number grows to seven digits rather than failing.
- List filters ignore values that are not text (`?status[]=x`) instead of failing.

## Milestone 4 — Fees & Payments

Plan: [plans/milestone-4-fees-payments.md](plans/milestone-4-fees-payments.md).

### Fees (open question §14.1)

- **The seeded fee schedule is a PLACEHOLDER** (`FeeScheduleSeeder`, local environment
  only): the three amounts seen in the legacy sheet (1,000 / 6,000 / 15,000 ETB) over
  invented bands (1–30, 31–60, 61+ days) for both permit types. **CONFIRM** the official
  tariff; an Admin enters it under _Fee schedule_.
- A schedule line prices a **permit type × vehicle type (or every type) × duration band**
  while it is in force (`effective_from`–`effective_to`, both days included, no end date
  = until replaced). The **primary vehicle's** type decides; trailers do not change the fee.
  A line for the vehicle type beats a line for every type; among equals the latest start
  date wins. The Admin form refuses two lines that would price the same permit on the
  same day (table lock while checking), so normally exactly one line applies.
- **The fee is copied onto the permit at approval** (`permits.fee_cents`) from the
  schedule in force on the approval day (display timezone). Editing the schedule never
  changes an approved permit. **Approval is refused** when no line applies, rather than
  issuing a permit with no fee.
- Lines are never deleted; to retire a fee, give it an end date.
- Money is integer santim everywhere (`App\Support\Money`); amounts are typed in birr
  ("1500" / "1,500.50") and parsed without floats.
- The application form shows a **live fee estimate** (`GET /fee-estimate`) by today's
  schedule; it is informative only.
- Permits approved before this milestone have no fee; in production there are none.
  Legacy imports (Milestone 7) will set the fee from the sheet.

### Payments (open question §14.3)

- **No online payment in v1.** The applicant pays at the bank or the Authority's cashier
  and submits the receipt; a cashier verifies it. `App\Domain\Payments\PaymentGateway` is
  the interface for a later Telebirr / Chapa integration; nothing implements it yet.
- Methods an applicant can choose: bank deposit, cash. `legacy` is reserved for imports.
- **The amount must equal the fee exactly** (checked by the form and again in the Action).
  Partial payments and over-payments are refused. **CONFIRM** whether partial payment or
  a tolerance is needed.
- **Receipt numbers** are normalised (upper-case, no spaces, one kind of dash) and unique
  among payments that are not rejected (partial unique index): a rejected receipt can be
  submitted again, e.g. when the scan was unreadable. The error names the receipt and
  nothing about who used it.
- **Receipt files**: PDF / JPG / PNG, at most `permits.receipt_max_kilobytes` (5 MB),
  checked by **content** (finfo), stored on the private `documents` disk under a random
  40-character name, served only through authorising controllers with `no-store` and
  `nosniff`. The applicant's file name is never stored. A file is deleted again if the
  submission fails.
- A rejected payment stays on record with the cashier's reason (shown to the applicant);
  the permit goes back to _awaiting payment_. A cashier's note on a _verified_ payment is
  internal (cashiers and Admins only).
- **Verifying the payment issues the permit** (`issued`, `issued_at`). The verification
  token, QR code and PDF are added to this step in Milestone 5.

### Separation of duties (spec §2)

- `permits.separation_of_duties` (default on): the person who **approved** a permit cannot
  **verify its payment**. With Admin now holding every staff permission (Milestone 1
  section), this rule is what stops one Admin from approving and collecting alone.
  Together with two-step approval (the recommender must differ from the approver), the
  recommend → approve → verify steps of one permit need at least two different people:
  the recommender may still verify the payment. **CONFIRM** whether verifier and
  recommender must also differ.

### Frontend

- New screens: payment form and payment history on the applicant's permit page; the
  cashier's payment queue and verification page (`staff/payments`); the Admin fee
  schedule (`admin/fee-schedules`). Sidebar: Payments for cashiers and Admins, Fee
  schedule for Admins; the review queue entry now also shows for Admins.
- `PaymentStatus` and `PaymentMethod` are added to the generated `types/enums.ts`.

### Deferred within this milestone

- Payment notifications (Milestone 6). Fee and payment changes are not yet in an audit
  log beyond `permit_status_transitions` (hardening milestone).

## Database: MySQL / MariaDB instead of PostgreSQL (owner's decision)

The specification asks for PostgreSQL 16 (exclusion constraints, `daterange`). The owner
chose **MySQL / MariaDB** (development server: MariaDB 10.4). This section replaces every
earlier statement about PostgreSQL in this file and in the milestone plans.

| PostgreSQL feature the spec relied on                                    | What is used instead                                                                                                                                                                                                                                                                                                                   |
| ------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `daterange` column `permit_vehicles.period`                              | Two `DATE` columns, `period_from` and `period_to` (both days included)                                                                                                                                                                                                                                                                 |
| Exclusion constraint: no overlapping blocking permits per vehicle (§4.1) | `OverlapGuard` locks the vehicle rows inside the approval transaction (this is what holds under concurrency), plus `BEFORE INSERT` / `BEFORE UPDATE` triggers on `permit_vehicles` that refuse an overlapping reserving row with `SQLSTATE 45000` (`permit_vehicles_no_overlap`), so a write that bypasses the Action is still refused |
| Partial unique index: one primary vehicle per permit                     | Virtual generated column `primary_marker` (1 for the primary, NULL otherwise) with a unique index on `(permit_id, primary_marker)`                                                                                                                                                                                                     |
| Partial unique index: receipt number unique unless rejected (§4.5)       | Virtual generated column `payments.live_receipt_number` with a unique index                                                                                                                                                                                                                                                            |
| plpgsql trigger: append-only `permit_status_transitions`                 | Two MySQL triggers (`BEFORE UPDATE`, `BEFORE DELETE`) that `SIGNAL` an error                                                                                                                                                                                                                                                           |
| `INSERT … ON CONFLICT … RETURNING` for permit numbers (§4.7)             | `INSERT … ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)`; still one atomic statement under the row lock, no `MAX() + 1`                                                                                                                                                                                        |
| `LOCK TABLE … SHARE ROW EXCLUSIVE` when saving a fee schedule line       | A named lock (`GET_LOCK`), because `LOCK TABLES` would end the transaction                                                                                                                                                                                                                                                             |

Things to know:

- **The overlap rule is weaker at the database level than in the spec.** A trigger cannot
  see another transaction's uncommitted row, so two simultaneous raw writes could both
  pass. Every application write goes through `ApprovePermit`, which serialises on the
  vehicle rows, so the application is safe; a script that writes to `permit_vehicles`
  directly, concurrently, is not.
- **Schema changes are not transactional in MySQL.** A migration that fails half-way
  leaves its tables behind. `create_permit_vehicles_table` therefore drops a leftover
  table before creating it.
- **The database account needs the `TRIGGER` privilege**, and on a server with binary
  logging enabled also `SUPER` or `log_bin_trust_function_creators = 1`, to run the migrations.
- **MariaDB 10.4 is past its end of life** (June 2024). It works and CI runs against it,
  but production should use a supported release — **CONFIRM** the production version.
- **Tests** run on the MySQL server from `.env` but always in the separate database
  `heavy_testing`; `Tests\TestCase` refuses any database whose name does not end in `_testing`.
- Database errors are reported with MySQL's codes: `23000` for unique, foreign-key and
  check violations, `45000` for the triggers.

## Milestone 5 — Issuance, QR, PDF, Verification

Plan: [plans/milestone-5-issuance-verification.md](plans/milestone-5-issuance-verification.md).
Flows and diagrams: [ARCHITECTURE.md](ARCHITECTURE.md).

### Tokens and QR codes (spec §7)

- **Issuance happens when the cashier verifies the payment** (`VerifyPayment` →
  `IssuePermit`): 32 random bytes, base64url (43 characters); only `sha256(token)` is
  stored (`permits.verification_token_hash`, unique). The token itself exists only in the
  QR code and, briefly, in the queued PDF job, whose payload is **encrypted**
  (`ShouldBeEncrypted`).
- The QR code holds `PERMITS_VERIFY_URL/v/{token}` and nothing else. It is generated on
  the server as SVG with `bacon/bacon-qr-code` (already installed through Fortify; no new
  package) at error-correction level **Q** (spec: M or higher), so a creased paper copy
  still scans. **CONFIRM** the public verification domain: changing it later breaks every
  printed permit.
- **Reissue** (Approver or Admin, reason required, only while the permit is issued and
  not yet expired) rotates the token: the old QR code answers NOT FOUND at once, the old
  PDF is deleted, a new one is queued, and the history records an `issued → issued` step
  with the reason.
- **Revocation** (Approver or Admin, reason required) takes effect immediately: the next
  scan answers REVOKED. Nothing about verification is cached.

### Verification (spec §7)

- Answers: **VALID** (issued and today within its dates), **NOT YET VALID** (issued, first
  day still ahead — added to the spec's four so a driver is not stopped by a "valid" that
  does not apply yet), **EXPIRED**, **REVOKED**, **NOT FOUND**. All come from
  `Permit::effectiveStatus()`. Permits that were never issued are always NOT FOUND, so
  applications in progress are never revealed.
- The public page is **server-rendered Blade** (works without JavaScript), sends
  `Cache-Control: no-store`, `X-Robots-Tag: noindex`, `Referrer-Policy: no-referrer`, and
  is limited to `PERMITS_VERIFICATION_PER_MINUTE` (30) requests per minute per IP.
- Shown publicly: permit number, status, permit type, plates with role and vehicle type,
  organization name (open question §14.4, default yes, `PERMITS_PUBLIC_SHOWS_ORGANIZATION`),
  validity in both calendars, time-window rules. Never: e-mails, phones, TIN, owner names,
  fees, receipts, the reason, staff names, notes, internal ids, the token.
- **Lookup by permit number requires a plate of that permit**, written in any form the
  plate parser accepts; the number is case-insensitive.
- **Scan log** (`verification_scans`): every check, public or officer, with result,
  channel (QR / lookup), the officer if signed in, the user agent and an **HMAC-SHA256 of
  the IP keyed with the app key** — the IP itself is never stored. Rotating `APP_KEY`
  changes the hashes; that is acceptable for an abuse log.
- **Traffic officers** (and Admins) opening the same QR address while signed in get the
  officer view: the public answer plus the status history (with revocation and reissue
  reasons), the last 10 checks and the last 10 officer reports, and a form to **report a
  violation** (`permit_reports`, append-only). Officers also have a manual lookup screen.
- **Scanning in the page** (owner's decision, 2026-11-12): Verify permit opens the device
  camera (the back camera on a phone) and reads the QR code with jsQR, on the device; the
  picture never leaves it. Only a code whose address is `PERMITS_VERIFY_URL/v/<token>` (or
  this site) is followed; anything else shows "not a permit of this system". The number
  and plate form stays below for damaged codes. `Permissions-Policy` now allows the camera
  for this site only (`camera=(self)`); browsers allow the camera only over HTTPS (or on
  localhost), so production must be served over HTTPS.

### Requests by letter at the office (owner's decision, 2026-11-12)

- **Online sign-up and applying were paused**, then removed altogether on 2026-11-13 (see
  "The office does everything" below): there is no `permits.online_applications` setting any more.
- **The letter.** Applicants hand a letter to a branch or the head office. The Registrar records
  the request with the letter's number, date and a scanned copy (PDF/JPG/PNG, required; on an edit
  only when none is stored) and sends it on. Scans are on the private documents disk under
  `letters/`, served by `staff.permits.letter` to staff who see the application and to Registrars
  of its branch; never to the applicant.
- **The Reviewer decides alone.** Two-step approval now defaults to off, and Reviewers hold
  `permits.approve`. With two-step approval on, a Reviewer still cannot decide on a recommendation
  they recorded. Revoking and reissuing stay with Approvers.
- **Payment at the cashier's desk.** A cashier takes the fee or checks a bank/mobile payment and
  records the receipt (`RecordPayment`): the payment is created verified and the application moves
  to the new status `payment_confirmed`. Methods: bank deposit, bank transfer, mobile money, cash,
  other. Verifying a receipt an applicant sent in (online flow) also ends in `payment_confirmed`.
- **The Issuer issues and prints.** New role `issuer` (`permits.issue`): checks the paid request,
  issues it (`IssuePaidPermit`: token, document, `issued_by`) and prints it. Verifying a payment no
  longer issues the permit.
- **The head office.** New role `supervisor` (`permits.view-queue`, `reports.view`,
  `permits.comment`), placed at the head office (no branch), sees every branch's applications,
  decisions, permits and revenue and leaves comments. Comments (`permit_comments`, append-only,
  audited) are internal: staff see them, the applicant never does.
- Cashiers and Issuers also hold `permits.view-queue` to open applications; the queue's default
  filter follows the desk (review, payment, issue).

### Movement hours (owner's decision, 2026-11-12)

- Each permit may let its vehicles move only between two times of the day (`move_from`,
  `move_to`, Addis Ababa time, 24-hour clock; both empty = any time of day). They are
  entered on the application (applicant, Registrar, one-page office form) and seen by the
  reviewer and approver like the rest of the application. A window may run past midnight;
  the start is included, the end is not. Choosing a Pass switches the form to a window,
  but the server does not require one (a form that sends no hours means any time).
- Every time is also shown on the Ethiopian clock (hours counted from 06:00 and 18:00;
  ከቀኑ for 06:00–17:59, ከምሽቱ until midnight, ከሌሊቱ after): 11:00–14:00 is
  "ከቀኑ 5:00 – ከቀኑ 8:00".
- A verification of a permit in force today, made outside its hours, answers
  **"Not allowed now"** (`ScanResult::OutsideHours`, amber), with the allowed hours and the
  time of the check in both clocks; it is logged like any other result. Expired, revoked
  and not-yet-valid answers are unchanged. The hours are printed on the permit (entry 7).

### Permit document (spec §8)

- **Broken documents** (2026-11-12): a stored PDF that is missing or smaller than 1 KB (the
  test renderer's placeholder) is never handed out; the permit shows "being prepared".
  `permits:documents` rebuilds those of issued, expired and revoked permits with a new
  verification token (the QR code is printed in the PDF and only the token's hash is kept;
  a placeholder had no QR code anyone could scan), audited as
  `permit.document_regenerated`. It refuses to run while the fake renderer is configured.

- Rendered by a **queued job** with **Gotenberg** (headless Chromium over HTTP, its own
  container, `GOTENBERG_URL`) rather than Browsershot, so the PHP image needs neither
  Node nor Chrome. No new PHP package: Laravel's HTTP client posts the HTML.
- The HTML is self-contained: Abyssinica SIL (OFL, copied to `resources/fonts` with
  its licence; one weight, bold is synthesised) is embedded as data URIs, the QR code
  inline as SVG. One **A4 landscape** page (owner's request, 2026-11-12).
- **Two PDFs per permit, one per language** (owner's request, 2026-11-12): Amharic in
  `document_path`, English in `document_path_en`, both carrying the same QR code. The
  permit page offers a download button for each; neither is offered until both exist.
- **Content follows the Authority's paper permit** (owner's request, 2026-11-12; the
  visual design is our own): № and date, the nine numbered entries — requesting
  organisation, vehicle owner, permitted time (full day / pass), vehicle type, plate,
  reason, validity, **payment receipt number**, **issuer's name with a signature line** —
  then the ማሳሰቢያ notes (`permits.document.notes`), plus the QR code and a stamp box.
  This reverses the earlier choice to leave the receipt number and staff names out; the
  fee, contact details and the token are still never printed. **CONFIRM** the English
  wording of the notes; the logo (`PERMITS_DOCUMENT_LOGO_PATH`) is a **PLACEHOLDER** until
  the Authority provides it (open question §14.6).
- Stored on the private `documents` disk under a random name. The job is idempotent: it
  does nothing if the token was rotated meanwhile, and attaches the file only if the token
  still matches. 3 tries, back-off 30 s / 2 min / 10 min; `failed()` logs without the
  token.
- **Download** only through a **temporary signed URL** (30 minutes) **and** the
  `download` policy: the permit's organization while it is issued or expired (not after
  revocation), and staff who can see the permit (Reviewer, Approver, Admin).
- `PERMITS_PDF_RENDERER=fake` uses a stand-in renderer (tests, machines without
  Gotenberg). One integration test renders a real PDF when Gotenberg is reachable and is
  skipped otherwise.

### Deferred

- **Offline verification** with an Ed25519-signed payload: designed in ARCHITECTURE.md,
  not built (spec §7 Phase 2).
- Notifications on issue / revoke → Milestone 6. A staff screen listing officer reports
  → later; reports are visible to officers on each scan.

## Milestone 6 — Notifications & Scheduling

Plan: [plans/milestone-6-notifications-scheduling.md](plans/milestone-6-notifications-scheduling.md).

### Who is told what (spec §11)

- **Recipients:** every member of the permit's organization who holds the Applicant role
  and has **verified their e-mail**, by e-mail in their own language (`users.locale`,
  falling back to Amharic); and the **organization's phone**, once, by SMS (in Amharic).
  Staff are not notified. **CONFIRM** whether SMS should go to members' own phones
  instead.
- **Messages:** approved (with the fee to pay), changes requested and rejected (with the
  Authority's note), receipt rejected (with the cashier's reason), issued, revoked (with
  the reason), and expires soon. The spec's separate _PaymentVerified_ is merged into
  _PermitIssued_: verifying the payment issues the permit in the same step, so two
  messages would say the same thing. A cashier's remark on a **verified** payment is
  internal and is not passed on.
- **No sensitive data in SMS:** permit number, what happened and dates only — no fee,
  receipt number, names or links. E-mails link to the permit page (sign-in required),
  never to the verification token.
- All notifications are **queued**, sent **after the transaction commits** (a refused or
  rolled-back action sends nothing), 3 tries with back-off.
- **SMS provider (open question §14.7):** only the `SmsGateway` contract and a `log`
  driver exist (`SMS_DRIVER=log`), which masks the phone number. A provider is added as a
  new driver; an unknown driver fails loudly at the first message.
- The spec's `Jobs/SendPermitNotification` is not needed: the notifications are queued
  themselves.

### Scheduling (spec §4.9, §16)

- `permits:expire` daily at **00:05 Africa/Addis_Ababa**: issued permits whose last day
  is **before today** (the last day itself still counts) become `expired`, one short
  transaction per permit, history row with no actor, vehicles freed. Verification never
  depended on it: `effectiveStatus()` already answers EXPIRED by date.
- `permits:notify-expiring` daily at **08:00** (`PERMITS_EXPIRING_REMINDER_TIME`): issued
  permits ending within `PERMITS_EXPIRING_REMINDER_DAYS` (**7**) days, today included,
  get one reminder each (`permits.expiry_reminded_at`, claimed before sending so parallel
  runs cannot send twice).
- Both use `withoutOverlapping()` and `onOneServer()` (cache locks). Horizon's snapshot and
  the database backups are scheduled with the production stack (Milestone 9).

## Milestone 7 — Legacy Import

> **Removed in Milestone 9 (owner's decision, 2026-11-12):** the owner does not need the
> Google-Form data; every organization registers in this system from scratch. The importer,
> its admin screen, command, tables (`import_batches`, `import_issues`), `permits.legacy_source_ref`,
> the `legacy` payment method and the `imports.run` permission are gone (migration
> `2026_11_19_000003_remove_legacy_import`). The notes below are kept as history only.

Plan: [plans/milestone-7-legacy-import.md](plans/milestone-7-legacy-import.md).

### What is imported and how

- Input: the CSV of "Form Responses 1", columns by position as in spec §1 (Timestamp,
  e-mail, owner, organization, permit type, vehicle type, plates, reason, validity,
  amount, receipt number, QR). The QR column is ignored: those QR codes point to the old
  spreadsheet and cannot be verified.
- **Idempotent** on `legacy_source_ref` = sha256 of the timestamp, the e-mail (lower
  case) and the plate cell as written. A second run, or a duplicate row, is counted as
  _already imported_.
- **Skipped** (with an issue) when the row is not safely a permit: no readable plate,
  permit type, validity period or organization name, or too few columns.
- **Imported with a warning** otherwise: unreadable timestamp (no submission time),
  invalid e-mail, unmapped vehicle type (type `legacy_unmapped`, not offered to
  applicants), missing / unreadable amount (no fee), missing receipt (no payment),
  receipt already used (no payment), unreadable trailer plate (trailer left out), a
  vehicle already registered to another organization (stays with that one), and overlaps.
- **Overlaps** (spec §13): an issued legacy permit that would reserve a vehicle already
  reserved for some of the same days is imported with `is_blocking = false` and a warning.
- **Status** by date on the day of the import: last day today or later → `issued`,
  otherwise `expired`. Permit numbers are generated in the normal sequence for the
  Ethiopian year of the submission. No verification token or PDF: an Approver can
  reissue a still-valid legacy permit to give it a QR code and document.
- Payment: method `legacy`, status `verified`, amount and receipt from the sheet, paid
  on the submission date.
- **Timestamps** are read as Addis Ababa time, trying `n/j/Y G:i:s` (Google Sheets'
  US-style export) and a few other formats; `--timestamp-format=` overrides. **CONFIRM**
  the format after a dry run on the real file.
- Vehicle types: `database/import/vehicle_type_map.php` holds the reviewed spellings
  (only those named in the spec so far). **Run a dry run on the real CSV, review the
  "vehicle_type" issues, extend the map, then run the import.**

### People and organizations

- **No user accounts are created for the people in the sheet.** An e-mail address in a
  form submission is not consent to an account, and one address often submitted for
  several institutions. Legacy permits belong to a system user `legacy-import@system.invalid`
  (no roles, unverified, random password: it cannot sign in). **CONFIRM** how an
  organization later claims its legacy permits (e.g. an Admin linking a registered
  applicant to the organization) — not built yet.
- Organizations are recognised by name, ignoring spacing and punctuation; created with the
  first submitter's e-mail and **empty phone and address** (the sheet has neither).

### Running it

- Chunks of 200 rows, one transaction per chunk and a savepoint per row; memory stays flat
  (the file is streamed). A **dry run** runs everything in one transaction that is rolled
  back, keeping only the batch and its issues.
- Every run is an `import_batches` row (who, when, totals) with its `import_issues`, and an
  issues CSV (UTF-8 with BOM for Excel) on the private documents disk.
- CLI: `php artisan legacy:import file.csv [--dry-run] [--user=admin@…]`. Admin screen:
  _Legacy import_ uploads the CSV and runs it on the queue (one try; re-running is safe).

## Milestone 8 — Reporting & Exports

Plan: [plans/milestone-8-reporting-exports.md](plans/milestone-8-reporting-exports.md).

### Who sees reports

- New permission `reports.view`, held by **Approver, Cashier and Admin**. Reviewers and
  traffic officers do not see revenue. **CONFIRM** with the Authority who should see the
  dashboard and the exports.
- Exports are for the same people. Each file can be downloaded only by the person who
  requested it (anyone else gets 404); the list shows only one's own exports.

### What the figures mean (spec §11)

- Everything is grouped by **Ethiopian month** in Africa/Addis_Ababa. The dashboard shows
  the current month and the 11 before it; Pagume counts as a month, so twelve months
  cover a little less than a year.
- **Issued** counts permits by `issued_at`, whatever happened to them since (revoked,
  expired). Legacy permits count in the month of their form submission, which the import
  uses as their issue time; one with an unreadable timestamp has no issue time and is not
  counted as issued in any month.
- **Revenue** is the sum of **verified** payments by the day they were paid (`paid_at`),
  not by the day the cashier verified them. Submitted and rejected payments are not revenue.
- **By status** is the status people see today: issued permits are split into _issued_
  (not started), _active_ and _expired_ by their dates, even before the nightly job runs.
- **By vehicle type** uses the primary vehicle's type; **top organizations** lists the ten
  with the most permits issued in the period, with the verified revenue of those permits.
- **Expiring soon**: issued permits whose last day is today or within the next 7 days.

### Exports

- openspout (named in the spec) writes XLSX or CSV row by row on the queue, so a large
  export never sits in memory. CSV starts with a UTF-8 byte-order mark so Excel shows
  Ethiopic text.
- Datasets: **permits** (never drafts or withdrawn applications; filter by status and by a
  period the permit is valid in) and **payments** (filter by status and by the day paid).
  Dates are given in both calendars, money in birr.
- **No e-mail addresses, phone numbers or staff names** are exported.
- Text that a spreadsheet program would run as a formula (starting with `=`, `+`, `-`,
  `@`, a tab or a carriage return) gets a leading apostrophe ("CSV injection"):
  organization names and receipt numbers are typed by applicants.
- Files are stored on the private documents disk under a random name, the requester gets
  an e-mail when one is ready, and `exports:prune` deletes exports older than
  `PERMITS_EXPORTS_KEEP_DAYS` (7) days every night at 03:00. Requests are limited to 10
  a minute per user.

### Deferred

- Charts are simple bars drawn in CSS; no charting library was added. PDF reports and
  scheduled (e-mailed) reports are not built.

## Milestone 9 — Hardening & Deployment

Plan: [plans/milestone-9-hardening-deployment.md](plans/milestone-9-hardening-deployment.md).

### Owner's decisions

- **Redis with plain `queue:work` workers, no Horizon** (deviation from spec §16): Horizon
  needs the pcntl/posix PHP extensions, which Windows lacks, and the owner develops on
  Windows. The `worker` container is restarted by Docker; failed jobs are listed with
  `php artisan queue:failed` and counted on the System settings overview.
- **The legacy import is removed** (see Milestone 7).
- **The admin screens of spec §11 are built here:** users and roles, vehicle types, system
  settings and the audit log viewer.

### Security (spec §15)

- **Headers on every response:** a Content-Security-Policy with a per-request nonce for
  scripts (Vite adds it; the Vite dev server is allowed only locally), `frame-ancestors
'none'`, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy:
strict-origin-when-cross-origin` (the verification pages keep `no-referrer`),
  `Permissions-Policy` without camera, microphone, geolocation or payment, COOP, and HSTS on
  HTTPS in production. Styles may be inline because React sets style attributes.
- **Session cookie** `secure` by default when `APP_URL` is HTTPS; `httpOnly` and
  `sameSite=lax` as before. Behind a TLS-ending proxy set `TRUSTED_PROXIES`.
- **Rate limits** added for registration, password-reset e-mails, password reset and
  password confirmation (5 a minute and 20 an hour per form and IP). Login attempts per
  minute are a setting (default 5).
- **Audit log** with `spatie/laravel-activitylog`, written explicitly by the Actions (never
  by model events): every permit transition (including system expiry, with no actor),
  reissue, payment verification and rejection, fee schedule change, staff account
  creation, role change, vehicle type change and settings change — who, what, when, before
  and after. **Append-only:** database triggers refuse updates and deletes, like the permit
  history. IPs are kept only as a keyed hash; secrets are recorded as "changed", never their
  value; staff e-mails are not copied into entries. The activity log is always on (not an
  `.env` switch).
- **Plate-lock race closed** (Milestone 3 known limit): `UpdateVehicle` and `SubmitPermit`
  lock the vehicle rows (in id order, so two submissions cannot deadlock).
- **Staff two-factor authentication** stays a server setting
  (`PERMITS_STAFF_TWO_FACTOR_REQUIRED`), not an Admin setting: it is non-negotiable in the
  spec, so it cannot be switched off from the browser. The overview warns if it is off.

### Users and roles

- Admins open **staff** accounts only; applicants register themselves. The account gets a
  link to choose a password (the reset-password link, valid
  `auth.passwords.users.expire` minutes; after that "Forgot password"). The e-mail is
  marked verified because the Admin vouches for it and choosing the password proves it.
- One role per staff user. "No role" removes access while keeping the account for the
  history it is part of. **An Admin cannot change their own role**, so there is always at
  least one Admin and nobody locks themselves out. Applicants' roles are not changed here.
- `php artisan admin:create email --name=…` opens the first Admin on a new installation and
  prints the password link (e-mail is usually not configured yet).

### Applicants registered at the office (owner's decision, 2026-11-12)

Applicants may come to the office in person. A **Registrar** (a new staff role, with only
`applicants.register`; Admins hold it too) registers them under Back office → Applicants:

- One form opens the contact person's account (applicant role, chosen language) and the
  organization profile, validated exactly as the applicant's own form (TIN unique, phone
  in international form). The applicant owns the profile afterwards and edits it as if
  they had registered online.
- **Nobody at the office chooses or sees the password.** The applicant is e-mailed a link
  to set it (the reset-password link, as for staff accounts); the e-mail is marked
  verified because the Registrar saw the person and setting the password proves the
  address. An e-mail address is therefore required; a walk-in without one cannot be
  registered yet.
- `users.registered_by` records who registered them (empty for online registrations), the
  list shows it and can be filtered by it, and the audit log has `applicant.registered`
  with the organization. The list covers every applicant so the Registrar can search
  (name, e-mail, organization, TIN, phone) before opening a second account.
- Registrars are staff: two-factor authentication is required. Run
  `RolesAndPermissionsSeeder` on deploy to create the role.
- **The whole application at the office** (owner's follow-up): from the applicant's page
  the Registrar registers their vehicles, starts, edits and submits the application, hands
  in the payment receipt once it is approved, and prints the issued permit. The same
  Actions, FormRequests and rules as online are used (plate uniqueness, own vehicles only,
  validity, overlap, the exact fee); only the organization comes from the applicant in
  the address instead of the signed-in user. The application belongs to the applicant
  (`applicant_id`), who sees and can continue it after signing in; the status history and
  the payment's `submitted_by` name the Registrar. `PermitPolicy::*For` allows this only
  for an applicant's own applications and in the same stages as the applicant; a Registrar
  may download issued and expired permits to print them. Reviewing, approving and
  verifying payments stay with their own roles.
- **One page, as the paper form** (owner's follow-up): "Register an applicant" holds the
  person, the organization, the vehicle with its trailers and the permit (type, period,
  reason), and is saved as a draft or submitted for review in one step, by the same
  Actions as online inside one transaction — all of it or nothing; the password e-mail
  goes out only after it is saved. Plates must be in nobody's register yet (the
  organization is new) and not given twice. The permit part can be switched off to
  register only the person; more vehicles and applications are added from their page.

### Vehicle types

- Created, renamed and retired (inactive), never deleted. The **code is fixed** once created.
  The list is cached and the cache is dropped on every save.

### System settings

- One area with an **overview** (system, services, counts, what needs attention, last
  change) and sections: general, appearance & themes, notifications, e-mail, SMS, Telegram,
  security, localization (`App\Domain\Settings\SettingsRegistry`).
- A saved value **overrides the config key** it belongs to, at boot and before every queued
  job, so the rest of the code keeps reading `config()` and running workers see changes.
  Defaults stay in `.env` / `config/*.php`. Only changed values are written and audited.
- **Secrets** (SMTP password, SMS token, Telegram bot token) are encrypted with `APP_KEY`,
  never sent to the browser, kept when the field is left empty, removable with a
  checkbox. A secret saved under another key is ignored (the default applies) — after
  rotating `APP_KEY`, enter them again.
- **E-mail, SMS and Telegram** have a "send test" button that reports the provider's error.
- **SMS:** a generic HTTP provider (JSON `{to, message, sender}` with a bearer token) next
  to "log only". **CONFIRM** the provider (§14.7); one with a different API gets its own
  class.
- **Telegram:** alerts to one staff group chat when an application is submitted, a receipt
  waits for verification or a permit is revoked. Only the permit number (or reference) and
  a link to the back office are sent — no names, plates or phone numbers. Queued, so a slow
  Telegram never delays the person acting.
- **Themes:** four colour themes (navy — the EUISIS look and the default —, forest, sunset, graphite) change
  the brand colour only; success stays green and destructive stays red. The **default light
  / dark mode** applies until a person chooses their own (stored under new names, so earlier
  automatic "system" values do not hide the default).
- **Localization:** the default language for people who have not chosen one, and the
  display timezone (kept Africa/Addis_Ababa unless told otherwise; it decides calendar days).

### Operations (spec §16)

- **Health:** `/up` also checks the database, the cache and the queue connection.
- **Error tracking:** `sentry/sentry-laravel`, off until `SENTRY_LARAVEL_DSN` is set; personal
  data and SQL bindings are never sent (pinned in `config/sentry.php`, not `.env`).
- **Logs:** a `json` channel (JSON lines on stderr) for production.
- **Docker:** a multi-stage `Dockerfile` (`app` PHP-FPM 8.4 with opcache, `web` nginx) and
  `compose.production.yaml` (app, web, worker, scheduler, mariadb, redis, gotenberg, backup).
  TLS ends at a reverse proxy. `REDIS_QUEUE_RETRY_AFTER` is longer than the longest job.
- **Backups:** daily database dump (single transaction, triggers included, DEFINERs
  stripped) and private-files archive, kept 30 days, by the `backup` service; restore script
  and procedure in README. **Tested on 2026-11-12** against the development database: same
  row counts and all six triggers after restoring into an empty database.
- **Caching:** settings, vehicle types and fee schedules are cached and dropped on change;
  verification results are never cached.
- **CONFIRM** the hosting environment and data residency (§14.8): the stack runs anywhere
  Docker does, on-premises included.

### Not built

- Off-site copying of backups (copy `BACKUP_PATH` to another machine; depends on hosting).
- Horizon dashboard (see above). Offline verification (Phase 2, ARCHITECTURE.md).

## Interface: the EUISIS design system (owner's decision, 2026-11-12)

The owner asked for the UI/UX and layout of the EUISIS project. Heavy now follows it:

- **Look:** flat "swiss" surfaces in true greys, hairline borders, small radii, navy brand
  colour (`#122170`) with a red-orange accent, Inter for Latin and Abyssinica SIL for
  Amharic (more line height for Ethiopic). The shadcn token names are kept, so every
  component took the new values; the large rounded utilities resolve to the card radius.
- **Shell:** a white sidebar that collapses to an icon rail, with section labels and the
  current page marked by a brand-colour edge; a top bar with navigation search (Ctrl/⌘+K),
  today's date in both calendars, light/dark and language switches and the account menu; a
  white heading band with breadcrumbs above the page title; messages as toasts; a footer.
- **Lists are tables** everywhere (EUISIS table style: muted header row, row dividers, hover
  wash), scrolling sideways on phones instead of switching to cards.
- **Dashboard** (EUISIS): KPI cards per role, "needs attention" with counts and links, the
  spread of permits over their statuses, recent activity, and for applicants their recent
  applications. Staff names are not shown to applicants.
- **Ethiopian date picker** (EUISIS): one field opening a month grid with the 13 months
  (Pagume 5 or 6 days), the week days, today marked, a year list, Today and Clear. It still
  hands the form a Gregorian ISO date and shows it under the field.
- **Theme management** (System settings → Appearance, as in EUISIS): colour preset (navy,
  forest, sunset, graphite) with free brand, accent and sidebar colours (dark variants and
  readable text worked out on the server; only `#rrggbb` accepted), interface style (swiss,
  classic, soft, contrast), button and card shape, table density, animations, breadcrumbs,
  compact sidebar by default, the default light/dark mode and whether people may choose
  their own. Sticky table headers were left out: tables scroll sideways in their card, so a
  sticky header would not work as expected.
- Icons: Solar (linear) in the shell and dashboard, as in EUISIS; lucide stays inside pages.
- **One place for appearance:** the personal Appearance page is gone; it repeated System
  settings → Appearance. Light/dark is the top-bar switch (when the Admin allows a personal
  choice). "My account" is one page with tabs (profile, password, two-factor) and section
  cards, as in EUISIS; all of it is translated.
- **Error pages** (EUISIS): 403, 404, 405, 419, 429, 500 and 503 show a small card with the
  error reference, a plain message and a way back, in the system's design and language.
  JSON callers and the debug page in development keep Laravel's own answer; an expired
  form (419) goes back to its page with a notice.
- **Branding** (EUISIS, System settings → General): an Admin uploads the logo (PNG, JPG or
  WebP, ≤ 2 MB) and the browser-tab icon (PNG or ICO, ≤ 512 KB), and sets the support
  e-mail and phone and a sign-in message per language. The logo replaces the placeholder
  mark in the sidebar, on the sign-in, landing, error and verification pages, and on the
  permit PDF (a file named by `PERMITS_DOCUMENT_LOGO_PATH` still wins). SVG is refused,
  since a drawing can carry script; raster files are decoded and written again (PNG, or
  WebP for photos) under a generated name. Files stay on the private disk (the `storage`
  volume in production) and are served by `/branding/{logo|favicon}` with a version in the
  address, so they need no storage link, satisfy the CSP and can be cached for good.
  Changes are audited as "file" changed, never the content. An Admin can also hide the
  language switch (Appearance), as in EUISIS.
- The public verification pages (Blade, no JavaScript) use the same tokens, brand bar and
  radii. The Vite dev server listens on `127.0.0.1`, since a Content-Security-Policy cannot
  name an IPv6 address and the stylesheet of these pages was blocked in development.

## The office does everything; permissions decide (owner's decision, 2026-11-13)

- **Applicants have no pages.** An applicant never applies themselves: a person holding
  `applicants.register` records the letter. `routes/applicant.php`, its controllers, pages, menu
  section, the applicant's dashboard, self sign-up (Fortify registration) and the
  `permits.online_applications` setting are gone, with the applicant-only policy abilities.
  The Registrar's requests reuse the validation rules in `App\Http\Requests\Applicant\*`
  (now abstract). Applicant accounts are records only: they get no password link and cannot
  sign in (`User::maySignIn()`; a session left from before is ended on its next request, and
  "Forgot password" sends them nothing), unless their roles also give them office work.
  Notification e-mails carry no link. `UpdateOrganization` and `WithdrawPermit` are no
  longer called from anywhere.
- **No role is fixed in code.** What a person may do is decided by the permissions their roles
  hold, never by a role's name: `User::isStaff()` (any permission of the office's work),
  `User::isApplicant()` (`permits.apply`), the review queue (the union of the desks a person's
  permissions give), the decision page and the dashboard all read permissions. Role names remain
  only where a role is _given_ (the default role of an account opened for an applicant, the rule
  that the applicant's role is not combined with staff roles) and for the protected administrator
  role, which exists so the system always keeps someone who can manage roles.
- **The dashboard has a permission**, `dashboard.view`. Without it `/dashboard` sends the person
  to the first page their permissions open (`App\Support\Rbac\Landing`), and the menu, the
  breadcrumbs and the command palette do not offer it. The migration grants it to every role that
  does office work, so nobody loses it on deploy; applicants' roles do not get it.
- **The applicant portal's permissions are gone** (`organization.manage-own`,
  `vehicles.manage-own`, `payments.submit`, `permits.download-own`; migration
  `2026_11_28_000002`), with the policy branches that let an applicant see their own
  applications, payments and PDFs. `permits.apply` stays, labelled "Applicant account": it marks
  the accounts requests are recorded for. Notices to applicants send them to the office, and the
  landing page describes the office flow.
- **Roles and permissions are not put into categories.** The Roles list, the role picker on a
  user, the permission catalogue and the permission picker each show one flat list;
  `PermissionCatalog` is a single ordered list.

## Speed on slow phones (2026-11-13)

Measured with Lighthouse (mobile: slow 4G, 4x slower CPU), median of three runs, against the
production build served by production's own nginx config (`docker/nginx/default.conf`).

- **Text is compressed** (gzip in nginx). Nothing was compressed before: about 1.1 MB of HTML,
  JavaScript and CSS per first visit went out as it is. `gzip_proxied any`, because a proxy in
  front may add a Via header. The pages carry no secret in their body (the CSRF token is in a
  cookie), so compressing HTML does not open the BREACH attack.
- **The interface's words are sent once.** `translations` (about 125 KB of JSON) was in every
  page and every in-app visit. It is now an Inertia once prop keyed by language and a fingerprint
  of the language files (`FrontendTranslations::version()`), so a language switch or a deploy that
  changes the wording sends it again; a browser holding two languages' keys is always sent it.
- **Only the icons the app uses are bundled.** `NavIcons.tsx` exported 198 icons, wrapped in a
  function call each, which bundlers must keep: every signed-in page downloaded all of them. It
  now lists the 26 in use, each marked `/* @__PURE__ */`. Add icons there as they are needed.
- **The logo goes to web pages as a small WebP copy** (at most 160 px, three times the largest
  place it is drawn), made once beside the upload; the upload itself stays as it is for the permit
  PDF. The landing and sign-in pages preload it, since it is the largest thing on their first
  screen and is otherwise only requested once the scripts have run. The production image now
  installs PHP's `gd`, which the logo upload already needed.
- **The public verify page no longer jumps:** the "Scan QR code" button is shown by an inline
  script right after it instead of by the scanner's script, which arrives later on a slow phone.
- **Left as they are:** the core bundle (React, Inertia and the libraries Inertia brings: axios,
  lodash, qs, laravel-precognition) cannot shrink without replacing Inertia; the signed-in shell's
  menus, dialogs and toasts are drawn on every page. Fonts load only the subsets a page needs.
  The biggest remaining step for the first paint would be server-side rendering (`ssr.tsx`
  exists, but production runs no SSR server): the pages are drawn by JavaScript.

## One layout for create and edit pages (2026-11-13)

Every create and edit page reads the same way, as the shell's Users and Roles forms:
`layouts/form-page-layout.tsx` gives the heading band (breadcrumbs, a link back, the title, an
optional one-line description) and the forms' common width (`max-w-4xl`, set once in
`AuthenticatedLayout`); the fields sit in `FormSection` cards and end with `FormActions`
(Cancel, then the main action, on the right; stacked full width on a phone, main action on top),
both in `components/form-layout.tsx`. Fields the server requires carry a red asterisk
(`required` on `FormField`, `EthiopianDateInput` and `PlateInput`, or `RequiredMark`), read out as
"required". File fields use `components/file-picker.tsx`, which speaks the page's language (the
browser's own control says "Choose File" in the browser's language). On phones the header's
back link gives way to the breadcrumb's "‹ Parent", which leads to the same place.

The office's one-page intake form lays itself out by the room the form has, not the window
(container queries, `@container/intake`): the progress panel sits beside the form only from a
64 rem form area, field pairs only from 42 rem, so the sidebar can no longer squeeze it; below
that the panel shows above the form without the step list, and the buttons sit at the end of the
form — one set of buttons at any width.

## Requests live under Permits; there is no Applicants page (owner's decision, 2026-11-13)

The Applicants section asked the office to think of a person first and a request second, which
is not how the counter works: someone arrives with a letter about a vehicle. Everything the
Registrar does now starts from **Permits**.

- **The pages and routes are gone.** `admin/applicants/*` — the list, the person's page, the
  separate "start an application" page — no longer exist, in the menu or in `routes/admin.php`.
  Their work moved to `admin/permits/{permit}`: `show`, `edit`, `update`, `submit`, `payment`,
  `payments.store`, `vehicles.create`, `vehicles.store` and `fee-estimate`
  (`Admin\WalkInController`, keyed by the request, not by the applicant). The applicant is read
  from the request (`$permit->applicant`); a request without one is not found. The Registrar's
  gates are unchanged (`PermitPolicy::viewFor`, `updateFor`, `submitFor`, `submitPaymentFor`).
- **"Add new" takes both kinds of request.** Step 1 of the one-page form asks who the request is
  for: a **new organisation** (registered with the request, as before) or one **already
  registered**, found with a search of the contact's name or e-mail, the organisation's name, TIN
  or phone (`admin/permits/organizations`, at least two characters, at most eight results, phones
  matched by their digits with `+251`/`0` treated as the same number). Choosing one hides the
  contact and organisation fields, sends `applicant_id`, and requires a request with it — the
  lookup is for filing requests, not for registering someone twice.
- **A known plate is the same vehicle.** For an organisation on record, a plate already in its
  register is used as it is; a new plate joins its register
  (`RegisterWalkInApplication::forExisting`, one transaction). A plate belonging to _another_
  organisation is still refused.
- **A vehicle missing from a request's lists** is registered from the edit page's "Register a
  vehicle that is not listed" link, which comes back to the edit page.
- **Nothing the office did was lost**: registering a person alone (the request part switched off)
  now lands back on a fresh form with the lookup ready for their request when it comes.

## The sign-in illustration

The sign-in panel carries a moving illustration: fields of light drifting
behind the roads of the city, with heavy vehicles travelling them.

- **No animation library.** `motion` (Framer Motion) was installed for this
  and then removed: every loop here is a CSS keyframe on `transform`,
  `opacity` or `stroke-dashoffset`, and the vehicles follow their roads with
  SVG `offset-path`. Nothing needed orchestration from JavaScript, so a
  40 KB dependency would have bought nothing. No Lottie, GSAP or three.js
  either; the scene is inline SVG and self-hosted, so it works on-premises
  with no outside request.
- **Ambient, not literal.** A first version drew a clock face for the
  permitted hours and a QR code for roadside checks. Both read as clip art
  pinned to a background and crowded the text. The panel already says what
  the system does in words, so the picture only sets a mood: the "aurora"
  treatment (large soft gradients on 19–33s cycles) with the roads over it.
- **It stops.** The loops end for `prefers-reduced-motion`, for the Admin's
  "animations off" switch, while the tab is in the background
  (`visibilitychange`) and while the panel is off-screen
  (`IntersectionObserver`). The still version is the same scene with the
  vehicles parked, so nothing disappears when motion is off.
- **One chunk, not two.** The illustration was going to be lazy-loaded, and
  that was reverted. A page module that lazy-imports part of itself is
  demoted by Rollup from a page entry to a shared chunk (`_login-*.js`),
  which breaks the Vite manifest lookup in `resources/views/app.blade.php`
  (`@vite(["resources/js/pages/{$page['component']}.tsx"])`) and 500s the
  page. At roughly 1.3 KB gzipped the split was not worth that.
- **Budget.** The sign-in page is 4.86 KB gzipped in all, against a 60 KB
  budget for the illustration alone. The scene animates only composited
  properties, so it adds no layout or paint work beside the form.
- **The logo is a slot.** `<BrandMark />` draws whatever an Admin uploaded
  in System settings → General, and a neutral placeholder otherwise. No
  emblem, seal or flag is drawn by this code.

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
  a Reviewer records a *recommendation* (approve / reject, note required for reject) on
  the permit while it is under review; an Approver then decides. The recommendation is
  not a status of its own — the spec's state machine is unchanged.
- **The Approver must not be the person who recommended** (`ApprovalRules`). With the
  setting off, an Approver can decide without a recommendation.
- **Staff policy abilities check permission and visibility only**; whether the action is
  possible *now* is the state machine's job. The Resource's `can` map combines both so
  the UI only offers what will succeed; the server re-checks everything.
- **Staff never see drafts or withdrawn applications** (policy answers "not found").
- Rejecting and requesting changes require a note; the applicant sees it in the history.
  Applicants never see which member of staff acted.

### Vehicles, overlap and numbers

- One **primary** vehicle and up to `permits.max_trailers` (default 2) **trailers**, all
  from the applicant's own organization. A partial unique index guarantees one primary.
- **Overlap** (spec §4.1) is enforced twice: `OverlapGuard` in the Action (vehicle rows
  locked) and the `btree_gist` exclusion constraint on `permit_vehicles`. A permit is
  *blocking* from approval (`awaiting_payment`) until it is revoked or expired; `period`
  is an inclusive `daterange`. Applications still under review do not block each other —
  the staff detail page shows the vehicle's other permits and flags conflicts.
- **The plate of a vehicle is locked** once it is on any application that is not a draft
  or withdrawn: the plate on a submitted or issued permit must not change underneath it.
- **Permit number** `TMA-HV-<Ethiopian year>-<6 digits>` is assigned at approval from
  `permit_number_sequences` under a row lock. The year is the Ethiopian *calendar* year
  of the approval date. **CONFIRM** calendar year vs fiscal year (spec says either), and
  the prefix (`permits.number_prefix`).
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
  tariff; an Admin enters it under *Fee schedule*.
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
  the permit goes back to *awaiting payment*. A cashier's note on a *verified* payment is
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

| PostgreSQL feature the spec relied on | What is used instead |
|---|---|
| `daterange` column `permit_vehicles.period` | Two `DATE` columns, `period_from` and `period_to` (both days included) |
| Exclusion constraint: no overlapping blocking permits per vehicle (§4.1) | `OverlapGuard` locks the vehicle rows inside the approval transaction (this is what holds under concurrency), plus `BEFORE INSERT` / `BEFORE UPDATE` triggers on `permit_vehicles` that refuse an overlapping reserving row with `SQLSTATE 45000` (`permit_vehicles_no_overlap`), so a write that bypasses the Action is still refused |
| Partial unique index: one primary vehicle per permit | Virtual generated column `primary_marker` (1 for the primary, NULL otherwise) with a unique index on `(permit_id, primary_marker)` |
| Partial unique index: receipt number unique unless rejected (§4.5) | Virtual generated column `payments.live_receipt_number` with a unique index |
| plpgsql trigger: append-only `permit_status_transitions` | Two MySQL triggers (`BEFORE UPDATE`, `BEFORE DELETE`) that `SIGNAL` an error |
| `INSERT … ON CONFLICT … RETURNING` for permit numbers (§4.7) | `INSERT … ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)`; still one atomic statement under the row lock, no `MAX() + 1` |
| `LOCK TABLE … SHARE ROW EXCLUSIVE` when saving a fee schedule line | A named lock (`GET_LOCK`), because `LOCK TABLES` would end the transaction |

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

### Permit document (spec §8)

- Rendered by a **queued job** with **Gotenberg** (headless Chromium over HTTP, its own
  container, `GOTENBERG_URL`) rather than Browsershot, so the PHP image needs neither
  Node nor Chrome. No new PHP package: Laravel's HTTP client posts the HTML.
- The HTML is self-contained: Noto Sans Ethiopic (OFL, copied to `resources/fonts` with
  its licence) is embedded as data URIs, the QR code inline as SVG. A4, Amharic first with
  English beside it, both calendars.
- **Left out of the PDF on purpose**: the fee, receipt, contact details and staff names —
  the paper travels with the driver and is shown at the roadside. "Issued by" is the
  Authority, not a person. **CONFIRM** the legal wording; the time-window rules text
  (`config/permits.php`) and the logo (`PERMITS_DOCUMENT_LOGO_PATH`) are **PLACEHOLDERS**
  until the Authority provides them (open questions §14.2, §14.6).
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
  the reason), and expires soon. The spec's separate *PaymentVerified* is merged into
  *PermitIssued*: verifying the payment issues the permit in the same step, so two
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

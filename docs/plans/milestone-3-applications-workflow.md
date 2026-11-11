# Milestone 3 — Applications & Workflow: plan

Scope (spec §12.3): permits, state machine, actions, overlap guard (+ exclusion
constraint), staff review UI, transition history, tests for every transition and policy
(spec §2, §3, §4.1, §4.3, §4.6, §4.7, §9, §10, §11).
Assumptions are recorded in [../DECISIONS.md](../DECISIONS.md).

## Migrations

| Migration | Contents |
|---|---|
| `create_permits_table` | `id` ULID, `permit_number` nullable **unique**, `organization_id` FK (restrict), `applicant_id` FK users (restrict), `permit_type`, `status`, `reason`, `valid_from`, `valid_to` (+ `CHECK valid_from <= valid_to`), `fee_cents` nullable, `currency` default `ETB`, recommendation (`recommendation`, `recommended_by` FK, `recommended_at`, `recommendation_note`), `approved_by` FK / `approved_at`, `issued_at`, `revoked_by` FK / `revoked_at` / `revocation_reason`, `verification_token_hash` nullable **unique**, `document_path`, `legacy_source_ref` nullable **unique**, `submitted_at`, timestamps. Indexes: `(status)`, `(organization_id, status)`, `(valid_to)`, `(submitted_at)` and every FK |
| `create_permit_vehicles_table` | `permit_id` FK (cascade), `vehicle_id` FK (restrict, indexed), `role`, `period daterange`, `is_blocking`, PK `(permit_id, vehicle_id)`; partial unique index "one primary per permit"; `btree_gist` + `EXCLUDE USING gist (vehicle_id WITH =, period WITH &&) WHERE (is_blocking)` |
| `create_permit_status_transitions_table` | `id`, `permit_id` FK (restrict), `from_status` nullable, `to_status`, `actor_id` FK users nullable (restrict), `note` nullable, `created_at`; trigger that raises on `UPDATE` / `DELETE` |
| `create_permit_number_sequences_table` | `year` int PK, `last_number` int |

## Backend

| Area | Classes |
|---|---|
| Enums | `PermitStatus`, `PermitType`, `VehicleRole`, `Recommendation` |
| Models | `Permit` (+ `PermitFactory` with a state per status), `PermitVehicle` (pivot), `PermitStatusTransition` (append-only); `Vehicle::permits()`, `Vehicle::hasLockedPlate()` |
| Domain | `Permits\PermitStateMachine`, `Permits\PermitTransitions` (lock → state machine → save → sync vehicles → history row), `Permits\PermitVehicles` (attach vehicles, keep `period` / `is_blocking` in sync), `Permits\OverlapGuard`, `Permits\PermitNumberGenerator`, `Permits\ValidityRules`, `Permits\ApprovalRules` |
| Exceptions | `PermitException` (base), `InvalidPermitTransition`, `OverlappingPermit`, `PermitRuleViolation`; `VehiclePlateLocked` |
| DTOs | `App\Data\PermitData`, `App\Data\RecommendationData` |
| Actions | `Permits\CreateDraftPermit`, `UpdateDraftPermit`, `SubmitPermit`, `StartReview`, `RecommendPermit`, `RequestChanges`, `ApprovePermit`, `RejectPermit`, `WithdrawPermit`, `RevokePermit`; `Vehicles\UpdateVehicle` refuses a plate change once the plate is locked |
| Policy | `PermitPolicy` (viewAny, create, view, update, submit, withdraw, viewQueue, startReview, recommend, requestChanges, approve, reject, revoke) |
| Requests | Applicant: `StorePermitRequest`, `UpdatePermitRequest`, `SubmitPermitRequest`, `WithdrawPermitRequest`. Staff: `StartReviewRequest`, `RecommendPermitRequest`, `RequestChangesRequest`, `ApprovePermitRequest`, `RejectPermitRequest` |
| Resources | `PermitResource`, `PermitVehicleResource`, `PermitTransitionResource`, `VehiclePermitHistoryResource` (whitelisted fields; staff-only fields only for staff viewers) |
| Controllers | `Applicant\PermitController` (index/create/store/show/edit/update/submit/withdraw), `Staff\PermitReviewController` (index/show/startReview/recommend/requestChanges/approve/reject) |
| Routes | `routes/applicant.php` (`permits.*`), new `routes/staff.php` (`staff.permits.*`) |
| Console | `enums:types {--check}` → `resources/js/types/enums.ts` (checked in CI) |
| Config | `permits.two_step_approval`, `permits.max_duration_days`, `permits.max_trailers`, `permits.number_prefix` |

Rules implemented: every status change goes through an Action inside a transaction with
the permit row locked; overlap prevention in the Action (vehicle rows locked) and by the
exclusion constraint; two-step approval; permit number sequence per Ethiopian year;
validity limits; one primary vehicle + trailers from the applicant's own organization;
plate lock; tenant isolation; drafts invisible to staff.

Not built here (their transitions are already in the state machine and tested):
`SubmitPayment`, `VerifyPayment`, `RejectPayment` (Milestone 4), `IssuePermit`
(Milestone 5), `ExpirePermits` (Milestone 6).

## Frontend

- Pages `applicant/permits/{index,create,edit,show}` and `staff/permits/{index,show}`.
- Components `StatusBadge`, `EthiopianDateInput` (the only place the frontend converts
  dates), `PermitTimeline`, `PermitForm`, `PermitDetails`, `StaffAction`.
- Sidebar entries (applicant "Permits"; reviewer / approver "Review queue"); the
  dashboard's "not open yet" notice becomes a link to the right place for the role.
- Language files `permits` in Amharic and English; generated `types/enums.ts`.

## Tests

| Subject | File |
|---|---|
| Every allowed transition, representative disallowed ones | `tests/Unit/Domain/Permits/PermitStateMachineTest.php` |
| Actions: status, history row, side effects | `tests/Feature/Permits/PermitActionsTest.php` |
| Overlap: guard and exclusion constraint | `tests/Feature/Permits/OverlapTest.php` |
| Permit number sequence | `tests/Feature/Permits/PermitNumberTest.php` |
| `effectiveStatus`, append-only history, schema constraints | `tests/Feature/Permits/PermitModelTest.php` |
| Applicant HTTP flow, validation, vehicle and validity rules, lists | `tests/Feature/Permits/ApplicantPermitTest.php` |
| Staff HTTP flow, queue filters, vehicle history | `tests/Feature/Permits/StaffReviewTest.php` |
| Cross-organization access denied | `tests/Feature/Permits/PermitTenantIsolationTest.php` |
| Resource field whitelists | `tests/Feature/Permits/PermitResourceTest.php` |
| Role × action matrix | `tests/Feature/Policies/PermitPolicyTest.php` |
| Plate lock | `tests/Feature/Vehicles/VehiclePlateLockTest.php` |
| Generated enum types | `tests/Feature/EnumTypesTest.php` |
| `StatusBadge`, `EthiopianDateInput` (incl. Pagume 5/6) | `resources/js/components/*.test.tsx` |

Deferred: fee snapshot at approval (Milestone 4), staff override of "not in the past"
(staff do not create applications yet), UI for revocation (Milestone 5, with issuance),
`spatie/laravel-activitylog` (hardening milestone), a multi-page wizard.

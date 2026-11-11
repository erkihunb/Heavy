# Milestone 4 — Fees & Payments: plan

Scope (spec §12.4): fee schedules, calculator, payment submission / verification, receipt
uniqueness, separation of duties, tests (spec §2, §3, §4.4, §4.5, §9, §11, §14.1, §14.3, §15).
Assumptions are recorded in [../DECISIONS.md](../DECISIONS.md).

## Migrations

| Migration | Contents |
|---|---|
| `create_fee_schedules_table` | `id`, `permit_type`, `vehicle_type_id` FK nullable (null = every type), `min_days`, `max_days`, `amount_cents`, `effective_from`, `effective_to` nullable, timestamps; CHECKs (`min_days >= 1`, `max_days >= min_days`, `effective_to >= effective_from`); lookup index |
| `create_payments_table` | `id` ULID, `permit_id` FK (restrict), `method`, `receipt_number`, `amount_cents`, `paid_at` date, `receipt_file_path` nullable, `status`, `submitted_by` FK, `verified_by` FK nullable, `verified_at`, `notes`, timestamps; **unique** index on `receipt_number` for payments that are not rejected; indexes on `status`, FKs |

## Backend

| Area | Classes |
|---|---|
| Enums | `PaymentStatus` (submitted / verified / rejected), `PaymentMethod` (bank_deposit / cash / legacy) |
| Models | `FeeSchedule`, `Payment` (+ factories); `Permit::payments()` |
| Domain | `Permits\FeeCalculator` (schedule lookup by type × vehicle type × duration × date, cached), `Payments\ReceiptNumber` (normalisation), `Payments\PaymentGateway` (interface only, for online payment later) |
| Support | `Money` (santim ↔ display, parsing without floats) |
| Exceptions | `DuplicateReceipt` |
| DTOs | `PaymentData`, `FeeScheduleData` |
| Actions | `Permits\SubmitPayment`, `VerifyPayment`, `RejectPayment`; `Fees\SaveFeeSchedule`; `ApprovePermit` snapshots the fee |
| Policies | `PaymentPolicy`, `FeeSchedulePolicy`; `PermitPolicy::submitPayment` |
| Requests | Applicant `StorePaymentRequest`, `FeeEstimateRequest`; Staff `VerifyPaymentRequest`, `RejectPaymentRequest`; Admin `FeeScheduleRequest` |
| Resources | `PaymentResource`, `FeeScheduleResource`; `PermitResource` gains `fee`, `payments`, `can.submitPayment` |
| Controllers | `Applicant\PaymentController` (store, receipt), `Applicant\FeeEstimateController`, `Staff\PaymentController` (index, show, verify, reject, receipt), `Admin\FeeScheduleController` (index, create, store, edit, update) |
| Routes | `routes/applicant.php`, `routes/staff.php`, new `routes/admin.php` |
| Config | `permits.separation_of_duties`, `permits.documents_disk`, `permits.receipt_max_kilobytes`; private `documents` disk |
| Seeder | `FeeScheduleSeeder` — PLACEHOLDER amounts, local only |

Rules implemented: the fee is computed from the schedule in force on the approval date
and snapshotted on the permit; approval is refused when no schedule matches; money is
integer santim; receipt numbers are normalised and unique among payments that were not
rejected; the receipt file is MIME-sniffed (PDF / JPG / PNG, ≤ 5 MB), stored on a private
disk under a random name and served only through an authorised controller; the person
who approved a permit cannot verify its payment (configurable); verifying the payment
moves the permit to `issued` (token and PDF are added to that step in Milestone 5).

## Frontend

- Applicant: fee on the application detail, live fee estimate in the form, payment form
  (method, receipt number, amount, date paid in E.C., receipt file) and payment history.
- Cashier: payment queue (`staff/payments`) and verification screen.
- Admin: fee schedule list and form (`admin/fee-schedules`).
- Sidebar entries for Cashier and Admin; language files `payments`, `fees`.

## Tests

| Subject | File |
|---|---|
| `Money` | `tests/Unit/Support/MoneyTest.php` |
| Fee calculation by schedule and effective dates; specific type beats general | `tests/Feature/Fees/FeeCalculatorTest.php` |
| Snapshot at approval, unchanged after schedule edits, approval refused without a schedule | `tests/Feature/Fees/FeeSnapshotTest.php` |
| Admin fee schedule CRUD, overlap validation, authorization | `tests/Feature/Fees/FeeScheduleAdminTest.php` |
| Submit / verify / reject Actions, history rows, separation of duties | `tests/Feature/Payments/PaymentActionsTest.php` |
| Receipt uniqueness (app and DB), normalisation, no leak of other organizations | `tests/Feature/Payments/ReceiptUniquenessTest.php` |
| Applicant HTTP flow, upload validation, fee estimate | `tests/Feature/Payments/ApplicantPaymentTest.php` |
| Cashier HTTP flow, queue filters | `tests/Feature/Payments/CashierPaymentTest.php` |
| Receipt download authorization, tenant isolation | `tests/Feature/Payments/PaymentTenantIsolationTest.php` |
| Role × action matrix | `tests/Feature/Policies/PaymentPolicyTest.php`, `FeeSchedulePolicyTest.php` |
| Resource field whitelists | `tests/Feature/Payments/PaymentResourceTest.php` |

Deferred: online payment (Telebirr / Chapa) — interface only; verification token and PDF
(Milestone 5); payment notifications (Milestone 6); audit-log package (hardening).

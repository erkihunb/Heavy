# Milestone 7 — Legacy Import: plan

Scope (spec §13): `php artisan legacy:import {file.csv} {--dry-run} {--batch=}` for the CSV
exported from the Google Sheet "Form Responses 1" (~6,750 rows), plus the Admin "legacy
import runner" (spec §11). Assumptions are recorded in [../DECISIONS.md](../DECISIONS.md).

## Migrations

| Migration | Contents |
|---|---|
| `create_import_batches_table` | `id`, `file_name`, `stored_path`, `status`, `dry_run`, `totals` json, `started_by` FK users, `started_at`, `finished_at`, `error`, timestamps |
| `create_import_issues_table` | `id`, `import_batch_id` FK (cascade), `source_row`, `field`, `raw_value`, `message`, `severity` (skipped / warning); index `(import_batch_id, source_row)` |

## Backend

| Area | Classes |
|---|---|
| Mapping file | `database/import/vehicle_type_map.php` — reviewed spellings → vehicle type code |
| Parsing | `Domain\Legacy\LegacyValues` (permit type, vehicle type, plates split on `/`, E.C. validity text → Gregorian, amount, receipt, timestamp, source ref), `Domain\Legacy\LegacyRow` (one parsed row + its issues) |
| Import | `Domain\Legacy\LegacyImporter` — streams the CSV, chunks of 200 rows in one transaction each, a savepoint per row; find-or-create organizations and vehicles; permit + payment (`legacy`) + history row; overlap check → `is_blocking = false` + issue; idempotent on `legacy_source_ref`; dry run rolls every chunk back; issues CSV on the private disk |
| Models / enums | `ImportBatch`, `ImportIssue`, `ImportStatus`, `IssueSeverity` |
| Command | `legacy:import {file} {--dry-run} {--batch=} {--user=} {--timestamp-format=}` |
| Job | `RunLegacyImport` (queued, one try, long timeout, marks the batch failed) |
| Admin | `Admin\ImportController` (index, store = upload + queue, issues CSV download), `ImportBatchPolicy` (`imports.run`), page `admin/imports/index` |

## Tests

| Subject | File |
|---|---|
| Value parsing with the real-world spellings | `tests/Unit/Legacy/LegacyValuesTest.php` |
| Fixture CSV → expected permits, vehicles, organizations, payments, issues; idempotent re-run; dry run; overlaps; chunking; command; issues CSV | `tests/Feature/Legacy/LegacyImportTest.php` |
| Admin upload, queued job, download, authorization | `tests/Feature/Legacy/ImportAdminTest.php` |

Fixture: `tests/Fixtures/legacy/form-responses.csv`.

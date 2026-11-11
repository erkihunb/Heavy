# Milestone 8 — Reporting & Exports: plan

Scope (spec §11 Reporting, §12.8, §16): a reporting dashboard and queued XLSX/CSV
exports, downloadable when done and announced by e-mail. Assumptions are recorded in
[../DECISIONS.md](../DECISIONS.md).

## Access

New permission `reports.view` for Approvers and Cashiers (Admins hold every staff
permission). Exports are downloadable only by the person who requested them.

## Migration

| Migration | Contents |
|---|---|
| `create_exports_table` | `id`, `user_id` FK, `dataset` (permits / payments), `format` (xlsx / csv), `filters` json, `status`, `path`, `rows`, `error`, `finished_at`, timestamps; index `(user_id, created_at)` |

## Backend

| Area | Classes |
|---|---|
| Calendar | `Support\EthiopianMonth` (a month of the Ethiopian calendar as Gregorian bounds, previous / next, label) |
| Reports | `Domain\Reports\PermitReports`: permits by effective status; issued this E.C. month; issued and revenue per E.C. month (last 12); permits by vehicle type and top organizations over those months; expiring in the next 7 days |
| Exports | `Domain\Reports\ExportDatasets` (rows of each dataset, streamed with `lazy()`), `Jobs\GenerateExport` (openspout writer, private disk, notifies the requester), `Notifications\ExportReady`, `exports:prune` (files older than 7 days) |
| Enums / models | `ExportDataset`, `ExportFormat`, `ExportStatus`, `Export` |
| HTTP | `Staff\ReportController` (index), `Staff\ExportController` (index, store, download), `StoreExportRequest`, `ExportPolicy`, `ExportResource` |
| Package | `openspout/openspout` (named in the spec) for XLSX and CSV |

## Frontend

`staff/reports/index` (tiles, monthly bars, status, vehicle types, top organizations,
expiring soon) and `staff/exports/index` (request form, my exports with download).
Sidebar entry "Reports" for Approvers, Cashiers and Admins.

## Tests

| Subject | File |
|---|---|
| `EthiopianMonth` bounds incl. Pagume and leap years | `tests/Unit/Support/EthiopianMonthTest.php` |
| Report figures on known data | `tests/Feature/Reports/PermitReportsTest.php` |
| Reports page and access | `tests/Feature/Reports/ReportPageTest.php` |
| Export request, queued job, file content (CSV and XLSX), notification, download only by owner, prune | `tests/Feature/Reports/ExportTest.php` |

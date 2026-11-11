# Milestone 5 — Issuance, QR, PDF, Verification: plan

Scope (spec §12.5): token service, SVG QR, PDF job, public & officer verification, scan
log, rate limits, revocation and reissue, tests (spec §3, §4.8, §7, §8, §11, §15, §17).
Assumptions are recorded in [../DECISIONS.md](../DECISIONS.md).

## Migrations

| Migration | Contents |
|---|---|
| `create_verification_scans_table` | `id`, `permit_id` FK nullable (null on delete), `result`, `channel` (`qr` / `lookup`), `user_id` FK nullable, `ip_hash` char(64), `user_agent` varchar(255) nullable, `scanned_at`; indexes `(permit_id, scanned_at)`, `scanned_at`, `user_id` |
| `create_permit_reports_table` | `id`, `permit_id` FK (restrict), `reported_by` FK users (restrict), `note` text, `created_at`; indexes on both FKs — the officer's "report violation / flag" |

`permits.verification_token_hash` (unique) and `document_path` already exist.

## Backend

| Area | Classes |
|---|---|
| Enums | `ScanResult` (valid, not_yet_valid, expired, revoked, not_found), `ScanChannel` |
| Domain | `Permits\VerificationTokenService` (32 random bytes, base64url, sha256 lookup), `Permits\PermitVerifier` (token / number + plate → permit + result, single source: `Permit::effectiveStatus()`), `Verification\ScanLogger` (HMAC-hashed IP), `Documents\PermitQrCode` (bacon SVG, ECC level M), `Documents\PdfRenderer` interface + `GotenbergPdfRenderer` |
| Actions | `Permits\IssuePermit` (rotate token, clear old document, queue PDF after commit) — called by `VerifyPayment`; `Permits\ReissuePermit` (reason required, history row); `RevokePermit` gets its HTTP endpoint; `Verification\ReportPermit` |
| Job | `GeneratePermitDocument` (queued, payload encrypted, idempotent: does nothing if the token was rotated; `tries`, `backoff`, `failed()`) |
| Policy | `PermitPolicy::download`, `reissue`, `scan`, `report` |
| Requests | `Staff\RevokePermitRequest`, `Staff\ReissuePermitRequest`, `Officer\LookupPermitRequest`, `Officer\ReportPermitRequest`, `Verification\PublicLookupRequest` |
| Resources | `PublicPermitVerificationResource` (whitelist), `OfficerPermitVerificationResource` (public + history, scans, reports); `PermitResource` gains `document_url` (temporary signed URL) and `can.reissue` |
| Controllers | `PublicVerificationController` (Blade, no JS: `/v/{token}`, `/verify`), `Officer\VerificationController` (Inertia), `PermitDocumentController` (signed + policy), `Staff\PermitReviewController@revoke/@reissue` |
| Middleware | `VerificationHeaders` (no-store, noindex, no-referrer) |
| Config | `permits.verify_url`, `permits.time_windows` (PLACEHOLDER text), `services.gotenberg.*`, rate limiter `verification` (30/min/IP) |
| Compose | `gotenberg` service (Chromium) |

## Frontend

- Public verification: Blade (`resources/views/verification/*`), server-rendered, works
  without JavaScript, big colour-coded status.
- PDF: Blade (`resources/views/documents/permit.blade.php`), fonts embedded.
- Applicant: download button / "document being prepared" on issued permits.
- Staff: revoke and reissue on the permit page; document download.
- Officer (Inertia, mobile-first): manual lookup, scan result with history, scans and
  reports, report form. Sidebar entry "Verify permit".

## Tests

| Subject | File |
|---|---|
| Token format, hashing, lookup | `tests/Feature/Verification/VerificationTokenTest.php` |
| Issuance on payment verification, reissue rotates, revoke over HTTP | `tests/Feature/Verification/IssuanceTest.php` |
| PDF job (fake renderer), stale job, private storage, renderer HTTP call, Gotenberg smoke test (skipped when unreachable) | `tests/Feature/Documents/PermitDocumentTest.php` |
| Signed, authorised download | `tests/Feature/Documents/PermitDocumentDownloadTest.php` |
| Public page: valid / not yet valid / expired / revoked / not found / rotated, field whitelist, headers, rate limit, lookup, scan log | `tests/Feature/Verification/PublicVerificationTest.php` |
| Officer view, scan log with user, report | `tests/Feature/Verification/OfficerVerificationTest.php` |
| New policy abilities × roles | `tests/Feature/Policies/PermitPolicyTest.php` (extended) |

Deferred: offline verification with an Ed25519-signed payload (Phase 2 — designed in
DECISIONS only); notifications on issue / revoke (Milestone 6).

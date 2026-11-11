# Manual QA checklist (spec §18)

Run on a production-like installation before go-live, with real devices. Tick each line
and note the date and who ran it. Automated tests cover each step separately
(`php artisan test`); this checklist proves they work together, on real phones, in both
languages.

## Accounts

- [ ] `php artisan admin:create` opens the first Admin; the printed link sets the password.
- [ ] The Admin must turn on two-factor authentication before reaching the back office.
- [ ] Admin → Users: open a Reviewer, an Approver, a Cashier and a Traffic officer account;
      each receives the "choose your password" e-mail.
- [ ] An applicant registers, verifies the e-mail and creates the organisation profile.

## The permit flow

- [ ] Applicant registers a truck and a trailer; a duplicate plate is refused.
- [ ] Applicant applies (dates chosen in the Ethiopian calendar), sees the fee estimate,
      submits. Telegram (if on) alerts the staff chat.
- [ ] Reviewer starts the review, requests changes; applicant corrects and resubmits.
- [ ] Reviewer recommends approval; Approver approves; the permit number is assigned and
      the applicant is told by e-mail (and SMS if on).
- [ ] Applicant uploads a receipt (PDF or photo, under 5 MB); a reused receipt number is
      refused.
- [ ] Cashier verifies the payment — the Approver of the same permit cannot.
- [ ] The permit is issued; the PDF is ready to download, fits one A4 page, Amharic renders.
- [ ] **Scan the QR code with a phone camera:** the public page shows VALID with the
      restricted hours, plates and dates in both calendars, and nothing personal.
- [ ] A signed-in traffic officer scanning the same code sees the officer view.
- [ ] Approver revokes the permit with a reason. **Scan again: REVOKED.**
- [ ] Approver reissues a still-valid permit: the old QR code answers NOT FOUND, the new one VALID.

## Back office

- [ ] Reports show the month's figures; an export (Excel and CSV) arrives by e-mail and
      opens with Ethiopic text intact.
- [ ] Admin → System settings: every section saves; e-mail, SMS and Telegram tests arrive;
      a theme change shows on the next page load; the overview lists nothing to attend to.
- [ ] Admin → Audit log shows each step above with who and when.

## Operations

- [ ] `GET /up` answers 200; stopping redis makes it answer 500.
- [ ] A forced error appears in Sentry without personal data.
- [ ] The nightly backup files appear; a restore into a spare database succeeds.
- [ ] `permits:expire` and `permits:notify-expiring` ran overnight (check the audit log).

## Run so far

| Date | By | Scope | Result |
|---|---|---|---|
| 2026-11-12 | Development (Claude) | Backup and restore against the development database | Same row counts and triggers after restore |
| 2026-11-12 | Development (Claude) | Reports and exports pages, export built by the queue | Pages render; CSV with Amharic header and BOM |
| earlier milestones | Development (Claude) | Issue → QR VALID → revoke → REVOKED, officer view, signed PDF | As expected (no phone camera used) |

# Milestone 9 — Hardening & Deployment

Spec §15 (security), §16 (operations), §11 Admin, §18 (definition of done), plus what
earlier milestones deferred here. Owner's decisions (2026-11-12): **Redis with plain
supervised `queue:work` workers instead of Horizon** (Horizon needs pcntl/posix, which
Windows lacks), and **all four admin screens are in scope**.

Committed on `main` in parts.

## Part A — Security hardening and audit log

1. **Security headers** middleware on every web response: Content-Security-Policy with a
   per-request nonce (Vite and the inline appearance script use it; the Vite dev server is
   allowed only in `local`), `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
   `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (no camera,
   microphone, geolocation, payment), `Cross-Origin-Opener-Policy: same-origin`, and
   HSTS on HTTPS in production.
2. **Session cookie**: `secure` by default when `APP_URL` is HTTPS; `httpOnly`,
   `sameSite=lax` stay.
3. **Rate limits** on registration, password-reset e-mails, password reset and
   verification e-mail resend (login and two-factor are already limited by Fortify).
4. **Audit log** with `spatie/laravel-activitylog`, made append-only by database
   triggers like `permit_status_transitions`. Who, what, when, before / after for:
   every permit transition (incl. revocation and expiry), reissue, payment verification
   and rejection, role changes and staff accounts, settings changes, vehicle types,
   fee schedules and legacy imports. No PII beyond the actor: IPs are hashed.
5. **Plate-lock race** (M3 known limit): `UpdateVehicle` and `SubmitPermit` lock the
   vehicle rows.

## Part B — Admin screens (spec §11)

1. **Users & roles**: list (search by name / e-mail, filter by role), create a staff
   account (it gets a password-reset link, never a password by e-mail), change a
   user's role. An Admin cannot remove their own admin role. Every change audited.
2. **Vehicle types**: list, create, edit (code, names, active, order). Cached, cache
   busted on save.
3. **System settings**: `settings` table holding the restricted-hours text per permit
   type (am / en), max duration, two-step approval and separation of duties. Cached;
   values override `config('permits.*')` at boot and before every queued job, so the
   rest of the code keeps reading config. Every change audited with before / after.
4. **Audit log viewer**: paginated, filtered by event and by actor, with the changes.

## Part C — Operations and deployment

1. **Health**: `/up` also checks the database, the cache and the queue connection
   (fails with 500 if one is down).
2. **Error tracking**: `sentry/sentry-laravel`, off unless `SENTRY_LARAVEL_DSN` is set;
   personal data not sent. **Structured logs**: a `json` log channel.
3. **Caching**: settings, vehicle types, fee schedules (busted on update). Verification
   status is not cached.
4. **Production stack**: `Dockerfile` (multi-stage: Node build → PHP 8.4 FPM with
   opcache), `docker/nginx` config, `compose.production.yaml` with app, nginx, mariadb,
   redis, worker (`queue:work redis`), scheduler (`schedule:work`), gotenberg and backup.
5. **Backups**: daily database dump + private files archive, 30-day retention, by a
   backup container; restore script and procedure in README, tested once locally.
6. **Zero-downtime migrations**: rules written down in README.

## Part D — Documents and QA

- README: production deployment, backups / restore, health, Sentry, settings.
- DECISIONS.md: Milestone 9 section (headers, audit, admin, Redis without Horizon).
- ARCHITECTURE.md: audit log and deployment diagram.
- `docs/QA-CHECKLIST.md`: the manual QA checklist of spec §18, with what was run.

## Tests

SecurityHeadersTest, AuthRateLimitTest, AuditLogTest (each audited event; append-only),
UserAdminTest, VehicleTypeAdminTest, SettingsTest (override, cache bust, queued jobs
see changes), AuditLogViewerTest, HealthCheckTest, plate-lock test.

## Scope changes during the milestone (owner, 2026-11-12)

- **Legacy import removed completely** (code, screen, command, tables, permission, payment
  method, export column); organizations register from scratch.
- **System settings became a full area** instead of one form: overview, general,
  appearance & colour themes, notifications, e-mail (SMTP + test), SMS (HTTP provider +
  test), Telegram staff alerts (+ test), security and localization. Secrets encrypted.
- Added `php artisan admin:create` for the first Admin of a new installation.

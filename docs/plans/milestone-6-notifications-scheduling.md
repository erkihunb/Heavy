# Milestone 6 — Notifications & Scheduling: plan

Scope (spec §12.6): expiry, expiring-soon reminders, queued notifications, tests with
`Notification::fake()` / `Bus::fake()` (spec §3, §4.9, §11, §16, §17).
Assumptions are recorded in [../DECISIONS.md](../DECISIONS.md).

## Migration

| Migration | Contents |
|---|---|
| `add_expiry_reminded_at_to_permits_table` | `expiry_reminded_at` timestamp nullable — the expiring-soon reminder is sent once per permit |

## Backend

| Area | Classes |
|---|---|
| Actions | `Permits\ExpirePermits` (issued permits whose last day has passed → `expired`, one transaction per permit, history row without an actor), `Permits\RemindExpiringPermits` |
| Commands | `permits:expire`, `permits:notify-expiring` (scheduled in `routes/console.php`) |
| Notifications | `PermitNotification` (base: queued, after commit, mail + SMS, user's language), `PermitApproved`, `PermitIssued` (also says the payment was verified), `PermitRevoked`, `PermitExpiringSoon`, `ChangesRequested`, `PermitRejected`, `PaymentRejected` |
| Delivery | `Domain\Notifications\PermitNotifier` (members of the permit's organization by e-mail; the organization's phone by SMS) |
| SMS | `Domain\Sms\SmsGateway` interface, `LogSmsGateway` (masks the number), `Notifications\Channels\SmsChannel`; `services.sms.driver` |
| Model | `User` implements `HasLocalePreference` (`users.locale`) |
| Config | `permits.notifications.sms`, `permits.notifications.expiring_days` (7), `permits.notifications.reminder_hour` |

Actions that notify: `ApprovePermit`, `RejectPermit`, `RequestChanges`, `VerifyPayment`,
`RejectPayment`, `RevokePermit`; `RemindExpiringPermits`.

## Schedule (Africa/Addis_Ababa)

| When | What |
|---|---|
| 00:05 daily | `permits:expire` |
| 08:00 daily | `permits:notify-expiring` |

Both `withoutOverlapping()` and `onOneServer()`. Horizon snapshot and database backups
are added with Horizon and the production stack (Milestone 9).

## Tests

| Subject | File |
|---|---|
| Expiry: who expires, history, vehicles freed, idempotent, command | `tests/Feature/Scheduling/ExpirePermitsTest.php` |
| Reminders: window, once only, skips revoked / expired, command | `tests/Feature/Scheduling/ExpiringSoonTest.php` |
| Schedule registered at the right times | `tests/Feature/Scheduling/ScheduleTest.php` |
| Each Action notifies the right people, on the right channels, after commit | `tests/Feature/Notifications/PermitNotificationsTest.php` |
| Mail and SMS content in Amharic and English, no sensitive data in SMS | `tests/Feature/Notifications/NotificationContentTest.php` |
| SMS channel and log gateway (number masked) | `tests/Feature/Notifications/SmsChannelTest.php` |

<?php

namespace App\Domain\Notifications;

use App\Enums\PermitStatus;
use App\Jobs\SendTelegramMessage;
use App\Models\Permit;

/**
 * Alerts to the staff Telegram chat when there is work waiting (a new
 * application, a receipt to verify) or a permit is revoked. Only the permit
 * number or reference and a link to the back office are sent: no names,
 * plates or phone numbers leave the system this way.
 */
final class StaffAlerts
{
    /** @var array<string, string> the status reached → the alert switch */
    private const array ALERTS = [
        'submitted' => 'submitted',
        'payment_submitted' => 'payment_submitted',
        'revoked' => 'revoked',
    ];

    public function permitChanged(Permit $permit, PermitStatus $to): void
    {
        $alert = self::ALERTS[$to->value] ?? null;

        if ($alert === null
            || ! config('services.telegram.enabled')
            || ! config("services.telegram.alerts.{$alert}")) {
            return;
        }

        $reference = $permit->permit_number ?? strtoupper(substr($permit->id, -8));
        $locale = (string) config('app.locale');

        $text = __("admin.telegram_alerts.{$alert}", ['permit' => $reference], $locale)
            ."\n".route('staff.permits.show', $permit);

        SendTelegramMessage::dispatch($text);
    }
}

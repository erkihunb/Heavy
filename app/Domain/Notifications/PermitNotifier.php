<?php

namespace App\Domain\Notifications;

use App\Enums\Role;
use App\Models\Permit;
use App\Models\User;
use App\Notifications\PermitNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Who hears about a permit (spec §11): every verified member of the permit's
 * organization by e-mail, in their own language, and the organization's
 * phone by SMS. Staff are never notified here.
 */
final class PermitNotifier
{
    public function send(Permit $permit, PermitNotification $notification): void
    {
        $members = User::query()
            ->where('organization_id', $permit->organization_id)
            ->whereNotNull('email_verified_at')
            ->role(Role::Applicant->value)
            ->get();

        if (config('permits.notifications.mail') && $members->isNotEmpty()) {
            Notification::send($members, $notification);
        }

        $phone = $permit->organization()->value('phone');

        if (config('permits.notifications.sms') && is_string($phone) && trim($phone) !== '') {
            Notification::route('sms', $phone)->notify($notification);
        }
    }
}

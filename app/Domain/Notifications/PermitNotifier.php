<?php

namespace App\Domain\Notifications;

use App\Enums\Permission;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\User;
use App\Notifications\PermitNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifications;

/**
 * Who hears about a permit (spec §11): every verified member of the permit's
 * organization by e-mail, in their own language, and the organization's
 * phone by SMS. Staff are never notified here.
 */
final class PermitNotifier
{
    public function send(Permit $permit, PermitNotification $notification): void
    {
        $this->sendToOrganization($permit->organization_id, $notification);
    }

    /**
     * The same, for a message about the organization's request letter.
     */
    public function sendToOrganization(int $organizationId, Notification $notification): void
    {
        $members = User::query()
            ->where('organization_id', $organizationId)
            ->whereNotNull('email_verified_at')
            ->permission(Permission::ApplyForPermits->value)
            ->get();

        if (config('permits.notifications.mail') && $members->isNotEmpty()) {
            Notifications::send($members, $notification);
        }

        $phone = Organization::query()->whereKey($organizationId)->value('phone');

        if (config('permits.notifications.sms') && is_string($phone) && trim($phone) !== '') {
            Notifications::route('sms', $phone)->notify($notification);
        }
    }
}

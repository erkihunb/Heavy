<?php

namespace App\Notifications;

/**
 * Sent once the application is approved; it states the fee to pay.
 */
class PermitApproved extends PermitNotification
{
    protected function key(): string
    {
        return 'approved';
    }
}

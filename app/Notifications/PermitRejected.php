<?php

namespace App\Notifications;

/**
 * Sent when the application is rejected; carries the reason.
 */
class PermitRejected extends PermitNotification
{
    protected function key(): string
    {
        return 'rejected';
    }
}

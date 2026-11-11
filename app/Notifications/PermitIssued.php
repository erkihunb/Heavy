<?php

namespace App\Notifications;

/**
 * Sent when the payment is verified and the permit is issued.
 */
class PermitIssued extends PermitNotification
{
    protected function key(): string
    {
        return 'issued';
    }
}

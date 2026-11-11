<?php

namespace App\Notifications;

/**
 * Sent a few days before an issued permit's last day.
 */
class PermitExpiringSoon extends PermitNotification
{
    protected function key(): string
    {
        return 'expiring_soon';
    }
}

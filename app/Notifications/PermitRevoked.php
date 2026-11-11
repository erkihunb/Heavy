<?php

namespace App\Notifications;

/**
 * Sent when an issued permit is revoked; carries the reason.
 */
class PermitRevoked extends PermitNotification
{
    protected function key(): string
    {
        return 'revoked';
    }
}

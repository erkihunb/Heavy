<?php

namespace App\Notifications;

/**
 * Sent when the Authority sends the application back; carries what to change.
 */
class ChangesRequested extends PermitNotification
{
    protected function key(): string
    {
        return 'changes_requested';
    }
}

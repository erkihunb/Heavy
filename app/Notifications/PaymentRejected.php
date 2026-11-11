<?php

namespace App\Notifications;

/**
 * Sent when the cashier rejects the receipt; carries the reason.
 */
class PaymentRejected extends PermitNotification
{
    protected function key(): string
    {
        return 'payment_rejected';
    }
}

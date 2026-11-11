<?php

namespace App\Domain\Payments;

use App\Models\Payment;
use App\Models\Permit;

/**
 * Online payment (Telebirr, Chapa, …) is not part of v1: fees are paid at a
 * bank or cashier and the receipt is verified by hand (spec §14, question 3).
 * A provider is added later by implementing this contract; nothing in the
 * application depends on an implementation yet.
 */
interface PaymentGateway
{
    /**
     * Start a payment of the permit's fee and return the address the payer is sent to.
     */
    public function initiate(Permit $permit): string;

    /**
     * Confirm with the provider that the payment it reported was really made.
     *
     * @param  array<string, mixed>  $payload  the provider's callback
     */
    public function confirm(Payment $payment, array $payload): bool;
}

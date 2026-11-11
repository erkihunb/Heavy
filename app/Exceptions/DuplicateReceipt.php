<?php

namespace App\Exceptions;

/**
 * The receipt number has already been used for a payment (spec §4.5). The
 * message names the receipt and nothing about the payment that holds it, which
 * may belong to another organization.
 */
final class DuplicateReceipt extends PermitException
{
    public function __construct(public readonly string $receiptNumber)
    {
        parent::__construct("Receipt [{$receiptNumber}] has already been used.");
    }

    public function userMessage(): string
    {
        return __('payments.errors.receipt_duplicate', ['receipt' => $this->receiptNumber]);
    }
}

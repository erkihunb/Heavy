<?php

namespace App\Data;

use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * A validated payment receipt, as an applicant submits it.
 */
final readonly class PaymentData
{
    public function __construct(
        public PaymentMethod $method,
        /** Normalised by App\Domain\Payments\ReceiptNumber. */
        public string $receiptNumber,
        /** Santim. */
        public int $amountCents,
        public CarbonImmutable $paidAt,
        public ?UploadedFile $receiptFile = null,
    ) {}
}

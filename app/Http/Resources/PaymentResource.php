<?php

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\EthiopianCalendar;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment as shown to the organization that made it and to cashiers. The
 * path of the receipt file never leaves the server; who verified the payment
 * is shown to cashiers only.
 *
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $forCashier = (bool) $user?->can('viewQueue', Payment::class);
        $locale = app()->getLocale();
        $timezone = config('localization.display_timezone');

        return [
            'id' => $this->id,
            'permit_id' => $this->permit_id,
            'method' => $this->method->value,
            'receipt_number' => $this->receipt_number,
            'amount' => Money::present($this->amount_cents),
            'paid_at' => EthiopianCalendar::present($this->paid_at, $locale),
            'status' => $this->status->value,
            // The cashier's reason for a rejection is for the applicant; a remark on a verified payment is internal.
            'notes' => $forCashier || $this->status === PaymentStatus::Rejected ? $this->notes : null,
            'has_receipt_file' => $this->receipt_file_path !== null,
            'submitted_at' => EthiopianCalendar::present($this->created_at?->setTimezone($timezone), $locale),
            'decided_at' => EthiopianCalendar::present($this->verified_at?->setTimezone($timezone), $locale),
            'permit' => PermitResource::make($this->whenLoaded('permit')),
            'can' => [
                'verify' => $this->status === PaymentStatus::Submitted && (bool) $user?->can('verify', $this->resource),
                'reject' => $this->status === PaymentStatus::Submitted && (bool) $user?->can('reject', $this->resource),
            ],

            ...($forCashier ? [
                'submitted_by' => $this->whenLoaded('submitter', fn (): ?string => $this->submitter?->name),
                'decided_by' => $this->whenLoaded('verifier', fn (): ?string => $this->verifier?->name),
            ] : []),
        ];
    }
}

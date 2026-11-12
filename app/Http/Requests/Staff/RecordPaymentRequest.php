<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\Applicant\StorePaymentRequest;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

/**
 * A cashier records a payment taken at the office, or one checked with the
 * bank or another payment service: how it was paid, the receipt number, the
 * amount and the day. A scan of the receipt is optional; a note stays internal.
 */
class RecordPaymentRequest extends StorePaymentRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('recordPayment', $this->route('permit'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'receipt_file' => ['nullable', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max((int) config('permits.receipt_max_kilobytes'))],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function note(): ?string
    {
        $note = $this->input('note');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }
}

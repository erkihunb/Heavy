<?php

namespace App\Http\Requests\Staff;

use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Http\Requests\Applicant\StorePaymentRequest;
use App\Models\Permit;
use App\Models\RequestLetter;
use App\Support\Money;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

/**
 * One receipt for every approved vehicle of a request letter: how it was
 * paid, the receipt number, the total of the fees and the day. As for one
 * permit (RecordPaymentRequest), the scan is optional and a note internal.
 */
class RecordLetterPaymentRequest extends StorePaymentRequest
{
    public function authorize(): Response
    {
        $letter = $this->route('letter');
        $first = $letter instanceof RequestLetter ? $letter->permits()->first() : null;

        if (! $first instanceof Permit || ! Gate::inspect('viewLetter', $first)->allowed()) {
            return Response::denyAsNotFound();
        }

        return $this->user()?->can(Permission::VerifyPayments->value) ? Response::allow() : Response::deny();
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

    /**
     * The amount is the total of the fees of the vehicles waiting for payment.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $letter = $this->route('letter');

                if ($validator->errors()->has('amount') || ! $letter instanceof RequestLetter) {
                    return;
                }

                $unpaid = $letter->permits()->where('status', PermitStatus::AwaitingPayment)->get();
                $total = (int) $unpaid->sum('fee_cents');

                if ($unpaid->isEmpty() || Money::parse($this->string('amount')->toString()) !== $total) {
                    $validator->errors()->add('amount', __('payments.errors.amount_mismatch', [
                        'fee' => Money::format($total, (string) ($unpaid->value('currency') ?? 'ETB')),
                    ]));
                }
            },
        ];
    }

    public function note(): ?string
    {
        $note = $this->input('note');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }
}

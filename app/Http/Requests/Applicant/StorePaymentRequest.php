<?php

namespace App\Http\Requests\Applicant;

use App\Data\PaymentData;
use App\Domain\Payments\ReceiptNumber;
use App\Domain\Permits\ValidityRules;
use App\Enums\PaymentMethod;
use App\Models\Permit;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

/**
 * An applicant reports the fee of an approved permit as paid. The amount is
 * typed in birr ("1500" or "1500.50") and handled as santim from here on.
 */
abstract class StorePaymentRequest extends FormRequest
{
    abstract public function authorize(): Response;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('receipt_number'))) {
            $this->merge(['receipt_number' => ReceiptNumber::normalize($this->input('receipt_number'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(array_map(
                fn (PaymentMethod $method): string => $method->value,
                PaymentMethod::selectable(),
            ))],
            'receipt_number' => ['bail', 'required', 'string', 'max:'.ReceiptNumber::MAX_LENGTH, function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! ReceiptNumber::isValid($value)) {
                    $fail(__('payments.errors.receipt_invalid'));
                }
            }],
            'amount' => ['bail', 'required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! Money::isValid($value)) {
                    $fail(__('payments.errors.amount_invalid'));
                }
            }],
            'paid_at' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:'.ValidityRules::today()->toDateString()],
            // The content is sniffed, not the extension the browser sent.
            'receipt_file' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max((int) config('permits.receipt_max_kilobytes'))],
        ];
    }

    /**
     * The amount must be exactly the fee fixed at approval; the Action checks
     * it again under the row lock.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $permit = $this->route('permit');

                if ($validator->errors()->has('amount') || ! $permit instanceof Permit) {
                    return;
                }

                if ($permit->fee_cents === null || Money::parse($this->string('amount')->toString()) !== $permit->fee_cents) {
                    $validator->errors()->add('amount', __('payments.errors.amount_mismatch', [
                        'fee' => $permit->fee_cents === null ? '—' : Money::format($permit->fee_cents, $permit->currency),
                    ]));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'method' => __('payments.fields.method'),
            'receipt_number' => __('payments.fields.receipt_number'),
            'amount' => __('payments.fields.amount'),
            'paid_at' => __('payments.fields.paid_at'),
            'receipt_file' => __('payments.fields.receipt_file'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paid_at.before_or_equal' => __('payments.errors.paid_in_future'),
            'receipt_file.mimes' => __('payments.errors.file_type'),
            'receipt_file.max' => __('payments.errors.file_size', ['max' => intdiv((int) config('permits.receipt_max_kilobytes'), 1024)]),
        ];
    }

    public function toData(): PaymentData
    {
        /** @var array{method: string, receipt_number: string, amount: string, paid_at: string} $validated */
        $validated = $this->validated();
        $file = $this->file('receipt_file');

        return new PaymentData(
            method: PaymentMethod::from($validated['method']),
            receiptNumber: $validated['receipt_number'],
            amountCents: Money::parse($validated['amount']),
            paidAt: CarbonImmutable::createFromFormat('!Y-m-d', $validated['paid_at'], 'UTC'),
            receiptFile: $file instanceof UploadedFile ? $file : null,
        );
    }
}

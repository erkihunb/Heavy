<?php

namespace App\Http\Requests\Staff;

class VerifyPaymentRequest extends PaymentActionRequest
{
    protected function ability(): string
    {
        return 'verify';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

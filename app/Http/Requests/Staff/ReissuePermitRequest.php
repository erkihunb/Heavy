<?php

namespace App\Http\Requests\Staff;

class ReissuePermitRequest extends PermitActionRequest
{
    protected function ability(): string
    {
        return 'reissue';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Why the old QR code is being replaced, for the history.
            'note' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => __('permits.errors.reissue_reason_required'),
        ];
    }
}

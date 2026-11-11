<?php

namespace App\Http\Requests\Staff;

class RejectPermitRequest extends PermitActionRequest
{
    protected function ability(): string
    {
        return 'reject';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // The applicant has to be told why.
            'note' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => __('permits.errors.note_required'),
        ];
    }
}

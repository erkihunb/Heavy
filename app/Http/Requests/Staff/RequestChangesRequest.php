<?php

namespace App\Http\Requests\Staff;

class RequestChangesRequest extends PermitActionRequest
{
    protected function ability(): string
    {
        return 'requestChanges';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // The applicant has to be told what to change.
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

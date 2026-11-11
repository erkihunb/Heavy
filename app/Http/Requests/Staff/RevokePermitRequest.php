<?php

namespace App\Http\Requests\Staff;

class RevokePermitRequest extends PermitActionRequest
{
    protected function ability(): string
    {
        return 'revoke';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Spec §3: a revocation always gives its reason.
            'note' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => __('permits.errors.revocation_reason_required'),
        ];
    }
}

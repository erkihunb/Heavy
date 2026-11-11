<?php

namespace App\Http\Requests\Staff;

class ApprovePermitRequest extends PermitActionRequest
{
    protected function ability(): string
    {
        return 'approve';
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

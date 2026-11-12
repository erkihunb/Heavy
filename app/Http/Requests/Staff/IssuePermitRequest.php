<?php

namespace App\Http\Requests\Staff;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * An Issuer confirms they have checked a paid application and issues it.
 */
class IssuePermitRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('issue', $this->route('permit'));
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function note(): ?string
    {
        $note = $this->input('note');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }
}

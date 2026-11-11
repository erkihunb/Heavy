<?php

namespace App\Http\Requests\Staff;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * What every staff decision on an application shares: it is authorised by
 * the PermitPolicy ability of the same name and may carry a note.
 */
abstract class PermitActionRequest extends FormRequest
{
    /**
     * The PermitPolicy ability that allows this action.
     */
    abstract protected function ability(): string;

    /**
     * Returning the policy's response keeps its "not found" answer for an
     * application staff may not see (a draft, or one that was withdrawn).
     */
    public function authorize(): Response
    {
        return Gate::inspect($this->ability(), $this->route('permit'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'note' => __('permits.fields.note'),
            'recommendation' => __('permits.fields.recommendation'),
        ];
    }

    /**
     * The note, or null when none was written.
     */
    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }
}

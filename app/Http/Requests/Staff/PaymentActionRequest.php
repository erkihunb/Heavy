<?php

namespace App\Http\Requests\Staff;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * What a cashier's decisions on a payment share: each is authorised by the
 * PaymentPolicy ability of the same name and may carry a note.
 */
abstract class PaymentActionRequest extends FormRequest
{
    /**
     * The PaymentPolicy ability that allows this action.
     */
    abstract protected function ability(): string;

    public function authorize(): Response
    {
        return Gate::inspect($this->ability(), $this->route('payment'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'note' => __('permits.fields.note'),
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

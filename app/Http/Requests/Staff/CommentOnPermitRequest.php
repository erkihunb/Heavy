<?php

namespace App\Http\Requests\Staff;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The head office's comment on an application.
 */
class CommentOnPermitRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('comment', $this->route('permit'));
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['body' => __('permits.comments.body')];
    }

    public function body(): string
    {
        return trim($this->string('body')->toString());
    }
}

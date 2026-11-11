<?php

namespace App\Http\Requests\Staff;

use App\Data\RecommendationData;
use App\Enums\Recommendation;
use Illuminate\Validation\Rule;

class RecommendPermitRequest extends PermitActionRequest
{
    protected function ability(): string
    {
        return 'recommend';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'recommendation' => ['required', Rule::enum(Recommendation::class)],
            // Advising rejection needs a reason; advising approval may have a remark.
            'note' => ['nullable', 'required_if:recommendation,'.Recommendation::Reject->value, 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required_if' => __('permits.errors.note_required'),
        ];
    }

    public function toData(): RecommendationData
    {
        return new RecommendationData(
            recommendation: $this->enum('recommendation', Recommendation::class),
            note: $this->note(),
        );
    }
}

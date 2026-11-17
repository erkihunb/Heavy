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
            // The assessment: what the Reviewer found, whatever they recommend.
            'note' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => __('permits.errors.assessment_required'),
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

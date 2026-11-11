<?php

namespace App\Data;

use App\Enums\Recommendation;

/**
 * A Reviewer's validated recommendation, as passed from a FormRequest to an Action.
 */
final readonly class RecommendationData
{
    public function __construct(
        public Recommendation $recommendation,
        public ?string $note,
    ) {}
}

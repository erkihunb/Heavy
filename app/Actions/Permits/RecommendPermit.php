<?php

namespace App\Actions\Permits;

use App\Data\RecommendationData;
use App\Domain\Permits\PermitTransitions;
use App\Enums\PermitStatus;
use App\Enums\Recommendation;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RecommendPermit
{
    public function __construct(private readonly PermitTransitions $transitions) {}

    /**
     * A Reviewer advises the Approver to approve or reject an application
     * under review. This is not a status change; a later recommendation
     * replaces the earlier one.
     *
     * @throws PermitRuleViolation
     */
    public function handle(Permit $permit, User $reviewer, RecommendationData $data): Permit
    {
        if ($data->recommendation === Recommendation::Reject && blank($data->note)) {
            throw new PermitRuleViolation('note_required');
        }

        return DB::transaction(function () use ($permit, $reviewer, $data): Permit {
            $this->transitions->lock($permit);

            if ($permit->status !== PermitStatus::UnderReview) {
                throw new PermitRuleViolation('not_under_review');
            }

            $permit->recommendation = $data->recommendation;
            $permit->recommended_by = $reviewer->id;
            $permit->recommended_at = CarbonImmutable::now();
            $permit->recommendation_note = filled($data->note) ? trim((string) $data->note) : null;
            $permit->save();

            return $permit;
        });
    }
}

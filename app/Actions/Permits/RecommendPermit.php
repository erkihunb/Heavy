<?php

namespace App\Actions\Permits;

use App\Data\RecommendationData;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Permits\PermitTransitions;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use App\Models\PermitReview;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RecommendPermit
{
    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly AuditLog $audit,
    ) {}

    /**
     * A Reviewer assesses an application under review: a written assessment
     * and what they recommend the Approver do (approve, ask for changes or
     * reject). It is not a status change. Every assessment is kept, so several
     * Reviewers can each leave theirs; the permit also carries the latest one,
     * which the two-step approval rule reads.
     *
     * @throws PermitRuleViolation
     */
    public function handle(Permit $permit, User $reviewer, RecommendationData $data): Permit
    {
        Gate::forUser($reviewer)->authorize(Permission::ReviewPermits->value);

        if (blank($data->note)) {
            throw new PermitRuleViolation('note_required');
        }

        $assessment = trim((string) $data->note);

        return DB::transaction(function () use ($permit, $reviewer, $data, $assessment): Permit {
            $this->transitions->lock($permit);

            if ($permit->status !== PermitStatus::UnderReview) {
                throw new PermitRuleViolation('not_under_review');
            }

            $review = new PermitReview([
                'recommendation' => $data->recommendation,
                'assessment' => $assessment,
            ]);
            $review->permit_id = $permit->id;
            $review->reviewer_id = $reviewer->id;
            $review->save();

            $permit->recommendation = $data->recommendation;
            $permit->recommended_by = $reviewer->id;
            $permit->recommended_at = CarbonImmutable::now();
            $permit->recommendation_note = $assessment;
            $permit->save();

            $this->audit->record(AuditEvent::PermitReviewed, $permit, $reviewer,
                new: ['recommendation' => $data->recommendation->value],
                context: array_filter(['permit_number' => $permit->permit_number, 'review_id' => $review->id]),
            );

            return $permit;
        });
    }
}

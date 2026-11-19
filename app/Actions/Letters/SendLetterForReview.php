<?php

namespace App\Actions\Letters;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Enums\Permission;
use App\Models\Permit;
use App\Models\PermitReviewRequest;
use App\Models\RequestLetter;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The deciding person asks chosen Reviewers for their opinion on the chosen
 * vehicles of a letter. It only puts the vehicles in those Reviewers' queues
 * and lets them record an assessment: the decision never waits for it.
 * Who may be asked is decided by permissions alone (permits.review and the
 * review queue, for the permit's branch), never by a role's name.
 */
final class SendLetterForReview
{
    public function __construct(
        private readonly LetterPermits $letters,
        private readonly AuditLog $audit,
    ) {}

    /**
     * People who may be asked for an opinion on this permit, by name.
     *
     * @return Collection<int, User>
     */
    public static function candidates(Permit $permit, User $asker): Collection
    {
        return User::permission(Permission::ReviewPermits->value)
            ->whereNull('deactivated_at')
            ->whereKeyNot($asker->getKey())
            ->with(['roles.permissions', 'permissions', 'branchScopes'])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $user->can(Permission::ViewReviewQueue->value) && $permit->isInBranchOf($user))
            ->values();
    }

    /**
     * @param  list<string>  $ids  the chosen permits of the letter
     * @param  list<int>  $reviewerIds
     * @return list<Permit>
     */
    public function handle(RequestLetter $letter, array $ids, User $asker, array $reviewerIds): array
    {
        $chosen = array_values(array_filter(
            $this->letters->chosen($letter, $ids),
            fn (Permit $permit): bool => $permit->status->isAwaitingReview(),
        ));

        if ($chosen === []) {
            throw ValidationException::withMessages(['permits' => __('letters.errors.none_chosen')]);
        }

        $allowed = self::candidates($chosen[0], $asker)->keyBy('id');
        $reviewers = collect(array_unique(array_map('intval', $reviewerIds)))->map(fn (int $id): ?User => $allowed->get($id));

        if ($reviewerIds === [] || $reviewers->contains(null)) {
            throw ValidationException::withMessages(['reviewers' => __('letters.review.reviewer_invalid')]);
        }

        $permits = $this->letters->each($chosen, function (Permit $permit) use ($asker, $reviewers): Permit {
            Gate::forUser($asker)->authorize('sendForReview', $permit);

            foreach ($reviewers as $reviewer) {
                /** @var User $reviewer */
                if (! $permit->isInBranchOf($reviewer) || $permit->wasSentForReviewTo($reviewer)) {
                    continue;
                }

                $request = new PermitReviewRequest;
                $request->permit_id = $permit->id;
                $request->reviewer_id = $reviewer->id;
                $request->requested_by = $asker->id;
                $request->save();
            }

            $this->audit->record(AuditEvent::PermitSentForReview, $permit, $asker, context: array_filter([
                'permit_number' => $permit->permit_number,
                'request_letter' => $permit->request_letter_id,
                'reviewers' => $reviewers->map(fn (User $user): int => $user->id)->values()->all(),
            ]));

            return $permit;
        });

        return $permits;
    }
}

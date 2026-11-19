<?php

namespace App\Actions\Letters;

use App\Actions\Permits\ApprovePermit;
use App\Actions\Permits\RecommendPermit;
use App\Actions\Permits\RejectPermit;
use App\Actions\Permits\RequestChanges;
use App\Actions\Permits\StartReview;
use App\Data\RecommendationData;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Notifications\PermitNotifier;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Models\Permit;
use App\Models\RequestLetter;
use App\Models\User;
use App\Notifications\LetterNotification;
use Illuminate\Support\Facades\Gate;

/**
 * Review and decide the vehicles of a request letter together: the
 * Reviewer's recommendation, then the Approver's decision — some vehicles
 * approved, the rest refused, as the Approver chooses (for example 40 of the
 * 100 asked for). Each permit goes through its own Action, so the approval
 * rules, the overlap check and the fee still apply to every vehicle; the
 * organization hears once for the letter instead of once per vehicle.
 */
final class DecideLetter
{
    public function __construct(
        private readonly LetterPermits $letters,
        private readonly StartReview $startReview,
        private readonly RecommendPermit $recommendPermit,
        private readonly ApprovePermit $approvePermit,
        private readonly RejectPermit $rejectPermit,
        private readonly RequestChanges $requestChanges,
        private readonly PermitNotifier $notifier,
        private readonly AuditLog $audit,
    ) {}

    /**
     * The Reviewer's recommendation for the chosen vehicles; those not yet
     * under review are taken into review first.
     *
     * @param  list<string>  $ids
     * @return list<Permit>
     */
    public function recommend(RequestLetter $letter, array $ids, User $reviewer, RecommendationData $data): array
    {
        $permits = $this->letters->each($this->letters->chosen($letter, $ids), function (Permit $permit) use ($reviewer, $data): Permit {
            Gate::forUser($reviewer)->authorize('recommend', $permit);

            return $this->recommendPermit->handle($this->underReview($permit, $reviewer), $reviewer, $data);
        });

        $this->record($letter, $reviewer, 'recommended_'.$data->recommendation->value, $permits, $data->note);

        return $permits;
    }

    /**
     * Approve the chosen vehicles. Each gets its permit number and fee; the
     * organization is told once how many were approved and what to pay.
     *
     * @param  list<string>  $ids
     * @return list<Permit>
     */
    public function approve(RequestLetter $letter, array $ids, User $approver, ?string $note = null): array
    {
        $permits = $this->letters->each($this->letters->chosen($letter, $ids), function (Permit $permit) use ($approver, $note): Permit {
            Gate::forUser($approver)->authorize('approve', $permit);

            return $this->approvePermit->handle($this->underReview($permit, $approver), $approver, $note, notify: false);
        });

        $this->record($letter, $approver, 'approved', $permits, $note);
        $this->notify($letter, $permits, 'approved', $note);

        return $permits;
    }

    /**
     * Refuse the chosen vehicles, with the reason the organization is given.
     *
     * @param  list<string>  $ids
     * @return list<Permit>
     */
    public function reject(RequestLetter $letter, array $ids, User $approver, string $note): array
    {
        $permits = $this->letters->each($this->letters->chosen($letter, $ids), function (Permit $permit) use ($approver, $note): Permit {
            Gate::forUser($approver)->authorize('reject', $permit);

            return $this->rejectPermit->handle($this->underReview($permit, $approver), $approver, $note, notify: false);
        });

        $this->record($letter, $approver, 'rejected', $permits, $note);
        $this->notify($letter, $permits, 'rejected', $note);

        return $permits;
    }

    /**
     * Return the chosen vehicles to the Registrar to correct, with what is
     * wrong; the organization is told once for the letter.
     *
     * @param  list<string>  $ids
     * @return list<Permit>
     */
    public function requestChanges(RequestLetter $letter, array $ids, User $actor, string $note): array
    {
        $permits = $this->letters->each($this->letters->chosen($letter, $ids), function (Permit $permit) use ($actor, $note): Permit {
            Gate::forUser($actor)->authorize('requestChanges', $permit);

            return $this->requestChanges->handle($this->underReview($permit, $actor), $actor, $note, notify: false);
        });

        $this->record($letter, $actor, 'changes_requested', $permits, $note);
        $this->notify($letter, $permits, 'changes_requested', $note);

        return $permits;
    }

    /**
     * A permit waiting for review is taken into review by whoever acts on it,
     * when they may review or decide: the deciding person never waits for a
     * Reviewer to open it first.
     */
    private function underReview(Permit $permit, User $actor): Permit
    {
        if ($permit->status === PermitStatus::Submitted
            && ($actor->can(Permission::ReviewPermits->value) || $actor->can(Permission::ApprovePermits->value))) {
            return $this->startReview->handle($permit, $actor);
        }

        return $permit;
    }

    /**
     * @param  list<Permit>  $permits
     */
    private function record(RequestLetter $letter, User $actor, string $decision, array $permits, ?string $note): void
    {
        $this->audit->record(AuditEvent::RequestLetterDecided, $letter, $actor, new: ['decision' => $decision], context: [
            'letter_number' => $letter->letter_number,
            'permits' => array_map(fn (Permit $permit): string => $permit->id, $permits),
            'count' => count($permits),
            'note' => filled($note) ? trim((string) $note) : null,
        ]);
    }

    /**
     * @param  list<Permit>  $permits
     */
    private function notify(RequestLetter $letter, array $permits, string $what, ?string $note): void
    {
        $total = array_sum(array_map(fn (Permit $permit): int => (int) $permit->fee_cents, $permits));

        $this->notifier->sendToOrganization(
            $letter->organization_id,
            new LetterNotification($letter, $what, count($permits), $what === 'approved' ? $total : null, $note),
        );
    }
}

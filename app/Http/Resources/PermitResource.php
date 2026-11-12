<?php

namespace App\Http\Resources;

use App\Domain\Documents\StoredDocument;
use App\Domain\Permits\ApprovalRules;
use App\Domain\Permits\PermitStateMachine;
use App\Enums\PermitStatus;
use App\Models\Permit;
use App\Models\PermitComment;
use App\Models\User;
use App\Support\EthiopianCalendar;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * A permit as shown to the members of its organization and to reviewing
 * staff. The recommendation, the applicant's name and the waiting time are
 * internal to the Authority and are only present for staff viewers.
 *
 * @mixin Permit
 */
class PermitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $forStaff = (bool) $user?->can('viewQueue', Permit::class);
        $locale = app()->getLocale();
        $timezone = config('localization.display_timezone');
        $now = CarbonImmutable::now();

        return [
            'id' => $this->id,
            'permit_number' => $this->permit_number,
            'status' => $this->status->value,
            'effective_status' => $this->effectiveStatus($now)->value,
            'permit_type' => $this->permit_type->value,
            'reason' => $this->reason,
            'valid_from' => EthiopianCalendar::present($this->valid_from, $locale),
            'valid_to' => EthiopianCalendar::present($this->valid_to, $locale),
            'duration_days' => $this->durationDays(),
            // The hours of the day it lets the vehicles move; null for any time of day.
            'hours' => $this->movementHours()?->present($locale),
            // Copied from the fee schedule at approval; null until then.
            'fee' => $this->fee_cents === null ? null : Money::present($this->fee_cents, $this->currency),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'vehicles' => PermitVehicleResource::collection($this->whenLoaded('vehicles')),
            // The branch the application was made at; null for the head office.
            'branch' => $this->whenLoaded('branch', fn (): ?array => $this->branch === null ? null : [
                'id' => $this->branch->id,
                'code' => $this->branch->code,
                'name' => $this->branch->localizedName($locale),
            ]),
            // The letter the request came in with; for the office's staff only.
            'letter' => $this->letter_number !== null && (bool) $user?->can('viewLetter', $this->resource) ? [
                'number' => $this->letter_number,
                'date' => EthiopianCalendar::present($this->letter_date, $locale),
                'url' => $this->letter_file_path === null ? null : route('staff.permits.letter', $this->resource),
            ] : null,
            'organization' => $this->whenLoaded('organization', fn (): string => $this->organization->localizedName($locale)),
            'submitted_at' => EthiopianCalendar::present($this->submitted_at?->setTimezone($timezone), $locale),
            'transitions' => PermitTransitionResource::collection($this->whenLoaded('transitions')),
            // Short-lived signed links to the Amharic and English PDFs; null while they are being prepared or not allowed.
            'document_urls' => (bool) $user?->can('download', $this->resource) && StoredDocument::isUsable($this->resource)
                ? collect(StoredDocument::LANGUAGES)->map(fn (string $column, string $language): string => URL::temporarySignedRoute(
                    'permits.document', now()->addMinutes(30), ['permit' => $this->id, 'language' => $language],
                ))->all()
                : null,
            'document_pending' => $this->status === PermitStatus::Issued && ! StoredDocument::isUsable($this->resource),
            'can' => $this->abilities($user),

            ...($forStaff ? [
                'applicant' => $this->whenLoaded('applicant', fn (): string => $this->applicant->name),
                // Whole hours the application has been waiting for the Authority.
                'waiting_hours' => $this->status->isAwaitingReview() && $this->submitted_at !== null
                    ? (int) $this->submitted_at->diffInHours($now)
                    : null,
                // The head office's comments: internal, never shown to the applicant.
                'comments' => $this->whenLoaded('comments', fn (): array => $this->comments->map(fn (PermitComment $comment): array => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'author' => $comment->author->name,
                    'at' => EthiopianCalendar::present($comment->created_at->setTimezone($timezone), $locale),
                    'time' => $comment->created_at->setTimezone($timezone)->format('H:i'),
                ])->all()),
                'issued_by' => $this->whenLoaded('issuer', fn (): ?string => $this->issuer?->name),
                'recommendation' => $this->recommendation === null ? null : [
                    'decision' => $this->recommendation->value,
                    'note' => $this->recommendation_note,
                    'by' => $this->whenLoaded('recommender', fn (): ?string => $this->recommender?->name),
                    'at' => EthiopianCalendar::present($this->recommended_at?->setTimezone($timezone), $locale),
                ],
            ] : []),
        ];
    }

    /**
     * What the current user can do with this permit right now: allowed by the
     * policy and possible in the permit's current stage. It only decides
     * which buttons are shown; every action is authorised again on the server.
     *
     * @return array<string, bool>
     */
    private function abilities(?User $user): array
    {
        /** @var Permit $permit */
        $permit = $this->resource;
        $status = $permit->status;
        $machine = app(PermitStateMachine::class);

        $may = fn (string $ability): bool => (bool) $user?->can($ability, $permit);
        $canMoveTo = fn (PermitStatus $to): bool => $machine->canTransition($status, $to);
        $mayDecide = fn (): bool => $user !== null && app(ApprovalRules::class)->violation($permit, $user) === null;

        return [
            'update' => $may('update'),
            'submit' => $may('submit'),
            'withdraw' => $may('withdraw'),
            'submitPayment' => $may('submitPayment'),
            'startReview' => $canMoveTo(PermitStatus::UnderReview) && $may('startReview'),
            // Without two-step approval the Reviewer decides, so there is nothing to recommend.
            'recommend' => (bool) config('permits.two_step_approval') && $status === PermitStatus::UnderReview && $may('recommend'),
            'requestChanges' => $canMoveTo(PermitStatus::ChangesRequested) && $may('requestChanges'),
            'approve' => $canMoveTo(PermitStatus::AwaitingPayment) && $may('approve') && $mayDecide(),
            'reject' => $canMoveTo(PermitStatus::Rejected) && $may('reject') && $mayDecide(),
            'recordPayment' => $status === PermitStatus::AwaitingPayment && $may('recordPayment'),
            'issue' => $status === PermitStatus::PaymentConfirmed && $may('issue'),
            'comment' => $may('comment'),
            'revoke' => $canMoveTo(PermitStatus::Revoked) && $may('revoke'),
            'reissue' => in_array($permit->effectiveStatus(CarbonImmutable::now()), [PermitStatus::Issued, PermitStatus::Active], true)
                && $may('reissue'),
        ];
    }
}

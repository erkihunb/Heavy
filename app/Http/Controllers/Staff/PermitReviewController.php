<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Letters\LetterPermits;
use App\Actions\Permits\ApprovePermit;
use App\Actions\Permits\RecommendPermit;
use App\Actions\Permits\ReissuePermit;
use App\Actions\Permits\RejectPermit;
use App\Actions\Permits\RequestChanges;
use App\Actions\Permits\RevokePermit;
use App\Actions\Permits\StartReview;
use App\Domain\Permits\OverlapGuard;
use App\Domain\Vehicles\PlateNumber;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ApprovePermitRequest;
use App\Http\Requests\Staff\RecommendPermitRequest;
use App\Http\Requests\Staff\ReissuePermitRequest;
use App\Http\Requests\Staff\RejectPermitRequest;
use App\Http\Requests\Staff\RequestChangesRequest;
use App\Http\Requests\Staff\RevokePermitRequest;
use App\Http\Requests\Staff\StartReviewRequest;
use App\Http\Resources\PermitResource;
use App\Http\Resources\VehiclePermitHistoryResource;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VerificationScan;
use App\Support\EthiopianCalendar;
use App\Support\Money;
use App\Support\QueryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The review queue and the decisions Reviewers and Approvers take on an
 * application. Drafts and withdrawn applications never appear here.
 */
class PermitReviewController extends Controller
{
    /**
     * The stages at which a request letter's vehicles are handled together
     * (one payment, one issuing), so the queue shows the letter once.
     *
     * @var list<string>
     */
    private const array LETTER_STAGES = ['awaiting_payment', 'payment_confirmed'];

    private const int PER_PAGE = 20;

    /** How many other permits are listed per vehicle on the detail page. */
    private const int HISTORY_PER_VEHICLE = 10;

    /** The `status` filter value that lifts the default "waiting for review" filter. */
    private const string ALL_STATUSES = 'all';

    public function index(Request $request): Response
    {
        Gate::authorize('viewQueue', Permit::class);

        $all = $request->query('status') === self::ALL_STATUSES;
        $status = PermitStatus::tryFrom(QueryFilter::text($request, 'status'));
        $status = $status?->isVisibleToStaff() ? $status : null;
        $permitType = PermitType::tryFrom(QueryFilter::text($request, 'permit_type'));
        $plate = QueryFilter::text($request, 'plate');
        $organization = QueryFilter::text($request, 'organization');
        $submittedFrom = QueryFilter::date($request, 'submitted_from');
        $submittedTo = QueryFilter::date($request, 'submitted_to');

        $like = fn (string $term): string => '%'.addcslashes($term, '%_\\').'%';

        $permits = Permit::query()->ordinary()
            ->with(['vehicles.vehicleType', 'organization', 'branch', 'applicant', 'recommender'])
            ->whereNotIn('status', PermitStatus::hiddenFromStaff())
            // Staff placed at a branch work on that branch's applications only.
            ->inBranchOf($request->user())
            ->when($status !== null, fn (Builder $query) => $query->inStatusGroup($status))
            // Unless asked otherwise, the queue is what waits for this person's desk.
            ->when($status === null && ! $all, fn (Builder $query) => $this->onDesk($query, $request->user()))
            ->when($permitType !== null, fn (Builder $query) => $query->where('permit_type', $permitType))
            ->when($plate !== '', fn (Builder $query) => $query->whereHas('vehicles', fn (Builder $query) => $query
                ->whereLike('plate_normalized', $like(PlateNumber::searchTerm($plate)))))
            ->when($organization !== '', fn (Builder $query) => $query->whereHas('organization', fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->whereLike('name_am', $like($organization))
                    ->orWhereLike('name_en', $like($organization)))))
            ->when($submittedFrom !== null, fn (Builder $query) => $query->where('submitted_at', '>=', $submittedFrom->startOfDay()->utc()))
            ->when($submittedTo !== null, fn (Builder $query) => $query->where('submitted_at', '<=', $submittedTo->endOfDay()->utc()))
            // A letter's vehicles are paid for, and issued, together: while waiting for
            // either, they are one row — the first vehicle at that stage.
            ->where(fn (Builder $query) => $query
                ->whereNull('request_letter_id')
                ->orWhereNotIn('status', self::LETTER_STAGES)
                ->orWhereNotExists(fn ($sibling) => $sibling
                    ->selectRaw('1')
                    ->from('permits as sibling')
                    ->whereColumn('sibling.request_letter_id', 'permits.request_letter_id')
                    ->whereColumn('sibling.status', 'permits.status')
                    ->whereColumn('sibling.id', '<', 'permits.id')))
            // Oldest first: the application that has waited longest is on top.
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Worked out before the rows become resources (that replaces them in the paginator).
        $letterPayments = $this->letterPayments($permits->getCollection());

        return Inertia::render('staff/permits/index', [
            'permits' => PermitResource::collection($permits),
            // The letters behind the rows that stand for a letter's one payment.
            'letterPayments' => $letterPayments,
            'filters' => [
                'status' => $all ? self::ALL_STATUSES : $status?->value,
                'permit_type' => $permitType?->value,
                'plate' => $plate,
                'organization' => $organization,
                'submitted_from' => $submittedFrom?->toDateString(),
                'submitted_to' => $submittedTo?->toDateString(),
            ],
        ]);
    }

    /**
     * Limits the queue to what waits for the person's desks. A Reviewer who
     * does not decide sees, of a letter's vehicles waiting for review, only
     * those an Approver sent them for an opinion (requests without a letter,
     * from before letters, stay open to every Reviewer).
     *
     * @param  Builder<Permit>  $query
     */
    private function onDesk(Builder $query, User $user): void
    {
        $desk = $this->desk($user);
        $review = [PermitStatus::Submitted, PermitStatus::UnderReview];

        if ($user->can(Permission::ApprovePermits->value) || ! $user->can(Permission::ReviewPermits->value)) {
            $query->whereIn('status', $desk);

            return;
        }

        $others = array_values(array_filter($desk, fn (PermitStatus $status): bool => ! in_array($status, $review, true)));

        $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->whereIn('status', $review)
                ->where(fn (Builder $query) => $query->whereNull('request_letter_id')->orWhere(fn (Builder $query) => $query->sentForReviewTo($user))))
            ->when($others !== [], fn (Builder $query) => $query->orWhereIn('status', $others)));
    }

    /**
     * What waits for the person's desks — review, payment and issuing, each
     * by its permission, so someone who holds several sees them all. The
     * head office, which only oversees, sees everything in progress.
     *
     * @return list<PermitStatus>
     */
    private function desk(User $user): array
    {
        $waiting = [
            ...($user->can(Permission::ReviewPermits->value) || $user->can(Permission::ApprovePermits->value)
                ? [PermitStatus::Submitted, PermitStatus::UnderReview] : []),
            ...($user->can(Permission::VerifyPayments->value)
                ? [PermitStatus::AwaitingPayment, PermitStatus::PaymentSubmitted] : []),
            ...($user->can(Permission::IssuePermits->value)
                ? [PermitStatus::PaymentConfirmed] : []),
        ];

        return $waiting !== [] ? $waiting : [
            PermitStatus::Submitted,
            PermitStatus::UnderReview,
            PermitStatus::ChangesRequested,
            PermitStatus::AwaitingPayment,
            PermitStatus::PaymentSubmitted,
            PermitStatus::PaymentConfirmed,
        ];
    }

    /**
     * The application, read only: its details, the vehicles' permit history,
     * the head office's comments and the timeline. Each role works on it on
     * its own page (decision, payment, issue), linked from here — except the
     * work waiting for this person now, which this address opens directly.
     */
    public function show(Request $request, Permit $permit, OverlapGuard $overlap): Response
    {
        Gate::authorize('view', $permit);

        // When the application waits for this person to move it on (decide, take the
        // payment, issue), that work opens here directly instead of behind a link.
        $abilities = PermitResource::make($permit)->abilitiesFor($request->user());
        $waiting = array_keys(array_filter([
            'staff/permits/decision' => $abilities['startReview'] || $abilities['recommend'] || $abilities['approve'] || $abilities['requestChanges'] || $abilities['reject'],
            'staff/permits/payment' => $abilities['recordPayment'],
            'staff/permits/issue' => $abilities['issue'],
        ]));

        return $this->render($waiting[0] ?? 'staff/permits/show', $request, $permit, $overlap, direct: $waiting !== []);
    }

    /**
     * The Reviewer's and Approver's page: the details and the vehicles'
     * history, then the decision (start, recommend, approve, request changes,
     * reject) and, once issued, revoking or reissuing.
     */
    public function decision(Request $request, Permit $permit, OverlapGuard $overlap): Response
    {
        Gate::authorize('view', $permit);
        abort_unless($this->desks($request->user())['decision'], 403);

        return $this->render('staff/permits/decision', $request, $permit, $overlap);
    }

    /**
     * The cashier's page: the fee, the receipts handed in, and recording a
     * payment taken at the office.
     */
    public function payment(Request $request, Permit $permit, OverlapGuard $overlap): Response
    {
        Gate::authorize('view', $permit);
        abort_unless($this->desks($request->user())['payment'], 403);

        return $this->render('staff/permits/payment', $request, $permit, $overlap);
    }

    /**
     * The Issuer's page: the verified payment, then issuing and printing.
     */
    public function issuing(Request $request, Permit $permit, OverlapGuard $overlap): Response
    {
        Gate::authorize('view', $permit);
        abort_unless($this->desks($request->user())['issue'], 403);

        return $this->render('staff/permits/issue', $request, $permit, $overlap);
    }

    /**
     * @param  bool  $direct  A role's page shown on the application's own address,
     *                        so it has no "back to the application" link.
     */
    private function render(string $page, Request $request, Permit $permit, OverlapGuard $overlap, bool $direct = false): Response
    {
        $permit->load(['vehicles.vehicleType', 'organization', 'branch', 'applicant', 'recommender', 'reviews.reviewer', 'issuer', 'comments.author', 'transitions.actor', 'payments']);
        $resource = PermitResource::make($permit);
        $can = $resource->abilitiesFor($request->user());
        $desks = $this->desks($request->user());
        // Waiting for this person on that page now.
        $pending = [
            'decision' => $can['startReview'] || $can['recommend'] || $can['approve'] || $can['requestChanges'] || $can['reject'] || $can['revoke'] || $can['reissue'],
            'payment' => $can['recordPayment'],
            'issue' => $can['issue'],
        ];
        $routes = ['decision' => 'staff.permits.decision', 'payment' => 'staff.permits.payment-desk', 'issue' => 'staff.permits.issue-desk'];

        return Inertia::render($page, [
            'permit' => $resource,
            'vehicleHistory' => $this->vehicleHistory($permit, $overlap),
            // The other vehicles of the same request letter, when it asked for several.
            'letterVehicles' => $this->letterVehicles($permit),
            'scans' => $this->scans($permit),
            'receiptMaxKilobytes' => (int) config('permits.receipt_max_kilobytes'),
            'direct' => $direct,
            'desks' => array_map(
                fn (string $key): array => ['key' => $key, 'url' => route($routes[$key], $permit), 'pending' => $pending[$key]],
                array_keys(array_filter($desks)),
            ),
        ]);
    }

    /**
     * For each row that stands for a request letter's one payment (keyed by
     * the permit's id): how many vehicles the payment covers, its total and
     * the letter's page where it is taken.
     *
     * @param  Collection<int, Permit>  $permits
     * @return array<string, array{letter: string|null, vehicles: int, total: string, url: string, stage: string}>
     */
    private function letterPayments(Collection $permits): array
    {
        $rows = $permits->filter(fn (Permit $permit): bool => $permit->request_letter_id !== null && in_array($permit->status->value, self::LETTER_STAGES, true));

        if ($rows->isEmpty()) {
            return [];
        }

        $totals = Permit::query()
            ->whereIn('request_letter_id', $rows->pluck('request_letter_id')->unique()->all())
            ->whereIn('status', self::LETTER_STAGES)
            ->groupBy('request_letter_id', 'status')
            ->selectRaw('request_letter_id, status, count(*) as vehicles, sum(fee_cents) as total')
            ->get()
            ->keyBy(fn (Permit $row): string => $row->request_letter_id.'|'.$row->status->value);

        $groups = [];

        foreach ($rows as $permit) {
            $total = $totals->get($permit->request_letter_id.'|'.$permit->status->value);

            if ($total === null || (int) $total->getAttribute('vehicles') < 2) {
                continue;
            }

            $groups[$permit->id] = [
                'letter' => $permit->letter_number,
                'vehicles' => (int) $total->getAttribute('vehicles'),
                'total' => Money::format((int) $total->getAttribute('total'), $permit->currency),
                'url' => route('letters.show', $permit->request_letter_id),
                'stage' => $permit->status->value,
            ];
        }

        return $groups;
    }

    /**
     * Every vehicle of the permit's request letter, this one included, when
     * the letter asked for more than one; empty otherwise.
     *
     * @return list<array<string, mixed>>
     */
    private function letterVehicles(Permit $permit): array
    {
        if ($permit->request_letter_id === null) {
            return [];
        }

        $permits = Permit::query()
            ->where('request_letter_id', $permit->request_letter_id)
            ->with('vehicles.vehicleType')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($permits->count() < 2) {
            return [];
        }

        return $permits->map(fn (Permit $each): array => [
            ...LetterPermits::row($each),
            'current' => $each->is($permit),
        ])->values()->all();
    }

    /**
     * How often the permit has been checked at the roadside or by the public
     * (every QR scan and every number-and-plate lookup is recorded).
     *
     * @return array{total: int, qr: int, lookup: int, by_officers: int, by_public: int, last: array{date: array<string, mixed>, time: string}|null}
     */
    private function scans(Permit $permit): array
    {
        $summary = VerificationScan::summaryFor($permit);
        $last = $summary['last_at']?->setTimezone(config('localization.display_timezone'));
        unset($summary['last_at']);

        return [...$summary, 'last' => $last === null ? null : [
            'date' => EthiopianCalendar::present($last, app()->getLocale()),
            'time' => $last->format('H:i'),
        ]];
    }

    /**
     * The role pages a person has, by their permissions: one page per role,
     * whatever stage the application is in.
     *
     * @return array{decision: bool, payment: bool, issue: bool}
     */
    private function desks(User $user): array
    {
        return [
            'decision' => $user->can(Permission::ReviewPermits->value) || $user->can(Permission::ApprovePermits->value) || $user->can(Permission::RevokePermits->value),
            'payment' => $user->can(Permission::VerifyPayments->value),
            'issue' => $user->can(Permission::IssuePermits->value),
        ];
    }

    public function startReview(StartReviewRequest $request, Permit $permit, StartReview $startReview): RedirectResponse
    {
        $startReview->handle($permit, $request->user());

        return to_route('staff.permits.show', $permit)->with('success', __('permits.messages.review_started'));
    }

    public function recommend(RecommendPermitRequest $request, Permit $permit, RecommendPermit $recommendPermit): RedirectResponse
    {
        $recommendPermit->handle($permit, $request->user(), $request->toData());

        return to_route('staff.permits.show', $permit)->with('success', __('permits.messages.recommended'));
    }

    public function requestChanges(RequestChangesRequest $request, Permit $permit, RequestChanges $requestChanges): RedirectResponse
    {
        $requestChanges->handle($permit, $request->user(), (string) $request->note());

        return to_route('staff.permits.show', $permit)->with('success', __('permits.messages.changes_requested'));
    }

    public function approve(ApprovePermitRequest $request, Permit $permit, ApprovePermit $approvePermit): RedirectResponse
    {
        $approvePermit->handle($permit, $request->user(), $request->note());

        return to_route('staff.permits.show', $permit)->with('success', __('permits.messages.approved'));
    }

    public function reject(RejectPermitRequest $request, Permit $permit, RejectPermit $rejectPermit): RedirectResponse
    {
        $rejectPermit->handle($permit, $request->user(), (string) $request->note());

        return to_route('staff.permits.show', $permit)->with('success', __('permits.messages.rejected'));
    }

    public function revoke(RevokePermitRequest $request, Permit $permit, RevokePermit $revokePermit): RedirectResponse
    {
        $revokePermit->handle($permit, $request->user(), (string) $request->note());

        return to_route('staff.permits.show', $permit)->with('success', __('permits.messages.revoked'));
    }

    public function reissue(ReissuePermitRequest $request, Permit $permit, ReissuePermit $reissuePermit): RedirectResponse
    {
        $reissuePermit->handle($permit, $request->user(), (string) $request->note());

        return to_route('staff.permits.show', $permit)->with('success', __('permits.messages.reissued'));
    }

    /**
     * For each vehicle on the application: its other permits, newest first,
     * with those that reserve it for overlapping days marked as conflicts.
     *
     * @return list<array{vehicle_id: string, plate: string, has_conflict: bool, permits: list<VehiclePermitHistoryResource>}>
     */
    private function vehicleHistory(Permit $permit, OverlapGuard $overlap): array
    {
        $conflicts = $overlap->conflicts($permit)
            ->map(fn (object $row): string => "{$row->vehicle_id}|{$row->permit_id}")
            ->flip();

        $others = Permit::query()->ordinary()
            ->whereKeyNot($permit->id)
            ->whereNotIn('status', PermitStatus::hiddenFromStaff())
            ->whereHas('vehicles', fn (Builder $query) => $query->whereKey($permit->vehicles->modelKeys()))
            ->with('vehicles:id')
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get();

        return $permit->vehicles
            ->map(function (Vehicle $vehicle) use ($others, $conflicts): array {
                $permits = $others
                    ->filter(fn (Permit $other): bool => $other->vehicles->contains($vehicle))
                    ->map(fn (Permit $other): VehiclePermitHistoryResource => new VehiclePermitHistoryResource(
                        $other,
                        conflicts: $conflicts->has("{$vehicle->id}|{$other->id}"),
                    ))
                    // Conflicts are never cut off by the limit.
                    ->sortByDesc(fn (VehiclePermitHistoryResource $history): bool => $conflicts->has("{$vehicle->id}|{$history->resource->id}"))
                    ->take(self::HISTORY_PER_VEHICLE)
                    ->values();

                return [
                    'vehicle_id' => $vehicle->id,
                    'plate' => $vehicle->plate_normalized,
                    'has_conflict' => $conflicts->keys()->contains(fn (string $key): bool => str_starts_with($key, "{$vehicle->id}|")),
                    'permits' => $permits->all(),
                ];
            })
            ->values()
            ->all();
    }
}

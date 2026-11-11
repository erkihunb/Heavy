<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Permits\ApprovePermit;
use App\Actions\Permits\RecommendPermit;
use App\Actions\Permits\RejectPermit;
use App\Actions\Permits\RequestChanges;
use App\Actions\Permits\StartReview;
use App\Domain\Permits\OverlapGuard;
use App\Domain\Vehicles\PlateNumber;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ApprovePermitRequest;
use App\Http\Requests\Staff\RecommendPermitRequest;
use App\Http\Requests\Staff\RejectPermitRequest;
use App\Http\Requests\Staff\RequestChangesRequest;
use App\Http\Requests\Staff\StartReviewRequest;
use App\Http\Resources\PermitResource;
use App\Http\Resources\VehiclePermitHistoryResource;
use App\Models\Permit;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The review queue and the decisions Reviewers and Approvers take on an
 * application. Drafts and withdrawn applications never appear here.
 */
class PermitReviewController extends Controller
{
    private const int PER_PAGE = 20;

    /** How many other permits are listed per vehicle on the detail page. */
    private const int HISTORY_PER_VEHICLE = 10;

    /** The `status` filter value that lifts the default "waiting for review" filter. */
    private const string ALL_STATUSES = 'all';

    public function index(Request $request): Response
    {
        Gate::authorize('viewQueue', Permit::class);

        $all = $request->query('status') === self::ALL_STATUSES;
        $status = PermitStatus::tryFrom($this->text($request, 'status'));
        $status = $status?->isVisibleToStaff() ? $status : null;
        $permitType = PermitType::tryFrom($this->text($request, 'permit_type'));
        $plate = $this->text($request, 'plate');
        $organization = $this->text($request, 'organization');
        $submittedFrom = $this->date($request, 'submitted_from');
        $submittedTo = $this->date($request, 'submitted_to');

        $like = fn (string $term): string => '%'.addcslashes($term, '%_\\').'%';

        $permits = Permit::query()
            ->with(['vehicles.vehicleType', 'organization', 'applicant', 'recommender'])
            ->whereNotIn('status', [PermitStatus::Draft, PermitStatus::Withdrawn])
            ->when($status !== null, fn (Builder $query) => $query->inEffectiveStatus($status))
            // Unless asked otherwise, the queue is what is waiting for the Authority.
            ->when($status === null && ! $all, fn (Builder $query) => $query->whereIn('status', [
                PermitStatus::Submitted,
                PermitStatus::UnderReview,
            ]))
            ->when($permitType !== null, fn (Builder $query) => $query->where('permit_type', $permitType))
            ->when($plate !== '', fn (Builder $query) => $query->whereHas('vehicles', fn (Builder $query) => $query
                ->whereLike('plate_normalized', $like(PlateNumber::searchTerm($plate)))))
            ->when($organization !== '', fn (Builder $query) => $query->whereHas('organization', fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->whereLike('name_am', $like($organization))
                    ->orWhereLike('name_en', $like($organization)))))
            ->when($submittedFrom !== null, fn (Builder $query) => $query->where('submitted_at', '>=', $submittedFrom->startOfDay()->utc()))
            ->when($submittedTo !== null, fn (Builder $query) => $query->where('submitted_at', '<=', $submittedTo->endOfDay()->utc()))
            // Oldest first: the application that has waited longest is on top.
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('staff/permits/index', [
            'permits' => PermitResource::collection($permits),
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

    public function show(Permit $permit, OverlapGuard $overlap): Response
    {
        Gate::authorize('view', $permit);

        $permit->load(['vehicles.vehicleType', 'organization', 'applicant', 'recommender', 'transitions.actor', 'payments']);

        return Inertia::render('staff/permits/show', [
            'permit' => PermitResource::make($permit),
            'vehicleHistory' => $this->vehicleHistory($permit, $overlap),
        ]);
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

        $others = Permit::query()
            ->whereKeyNot($permit->id)
            ->whereNotIn('status', [PermitStatus::Draft, PermitStatus::Withdrawn])
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

    private function text(Request $request, string $key): string
    {
        return is_string($request->query($key)) ? trim($request->query($key)) : '';
    }

    /**
     * A Gregorian `Y-m-d` filter value as that calendar day in the display
     * timezone, or null when it is missing or malformed.
     */
    private function date(Request $request, string $key): ?CarbonImmutable
    {
        $value = $this->text($request, $key);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map(intval(...), explode('-', $value));

        return checkdate($month, $day, $year)
            ? CarbonImmutable::create($year, $month, $day, 0, 0, 0, config('localization.display_timezone'))
            : null;
    }
}

<?php

namespace App\Http\Controllers\Applicant;

use App\Actions\Permits\CreateDraftPermit;
use App\Actions\Permits\SubmitPermit;
use App\Actions\Permits\UpdateDraftPermit;
use App\Actions\Permits\WithdrawPermit;
use App\Domain\Permits\ValidityRules;
use App\Domain\Vehicles\PlateNumber;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\StorePermitRequest;
use App\Http\Requests\Applicant\SubmitPermitRequest;
use App\Http\Requests\Applicant\UpdatePermitRequest;
use App\Http\Requests\Applicant\WithdrawPermitRequest;
use App\Http\Resources\PermitResource;
use App\Http\Resources\VehicleResource;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The permit applications of the applicant's own organization.
 */
class PermitController extends Controller
{
    private const int PER_PAGE = 15;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Permit::class);

        $status = PermitStatus::tryFrom($this->text($request, 'status'));
        $permitType = PermitType::tryFrom($this->text($request, 'permit_type'));
        $plate = $this->text($request, 'plate');

        $permits = Permit::query()
            ->whereBelongsTo($this->organization($request))
            ->with(['vehicles.vehicleType', 'organization'])
            ->when($status !== null, fn (Builder $query) => $query->inEffectiveStatus($status))
            ->when($permitType !== null, fn (Builder $query) => $query->where('permit_type', $permitType))
            ->when($plate !== '', fn (Builder $query) => $query->whereHas('vehicles', fn (Builder $query) => $query->whereLike(
                'plate_normalized',
                '%'.addcslashes(PlateNumber::searchTerm($plate), '%_\\').'%',
            )))
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('applicant/permits/index', [
            'permits' => PermitResource::collection($permits),
            'filters' => [
                'status' => $status?->value,
                'permit_type' => $permitType?->value,
                'plate' => $plate,
            ],
            'canCreate' => $request->user()->can('create', Permit::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Permit::class);

        return Inertia::render('applicant/permits/create', [
            'vehicles' => $this->vehicles($this->organization($request)),
            'limits' => $this->limits(),
        ]);
    }

    public function store(StorePermitRequest $request, CreateDraftPermit $createDraftPermit): RedirectResponse
    {
        $permit = $createDraftPermit->handle($this->organization($request), $request->user(), $request->toData());

        return to_route('permits.show', $permit)->with('success', __('permits.messages.draft_saved'));
    }

    public function show(Permit $permit): Response
    {
        Gate::authorize('view', $permit);

        return Inertia::render('applicant/permits/show', [
            'permit' => PermitResource::make($permit->load(['vehicles.vehicleType', 'organization', 'transitions'])),
        ]);
    }

    public function edit(Request $request, Permit $permit): Response
    {
        Gate::authorize('update', $permit);

        return Inertia::render('applicant/permits/edit', [
            'permit' => PermitResource::make($permit->load(['vehicles.vehicleType', 'organization', 'transitions'])),
            'vehicles' => $this->vehicles($this->organization($request)),
            'limits' => $this->limits(),
        ]);
    }

    public function update(UpdatePermitRequest $request, Permit $permit, UpdateDraftPermit $updateDraftPermit): RedirectResponse
    {
        $updateDraftPermit->handle($permit, $request->toData());

        return to_route('permits.show', $permit)->with('success', __('permits.messages.draft_saved'));
    }

    public function submit(SubmitPermitRequest $request, Permit $permit, SubmitPermit $submitPermit): RedirectResponse
    {
        $submitPermit->handle($permit, $request->user());

        return to_route('permits.show', $permit)->with('success', __('permits.messages.submitted'));
    }

    public function withdraw(WithdrawPermitRequest $request, Permit $permit, WithdrawPermit $withdrawPermit): RedirectResponse
    {
        $withdrawPermit->handle($permit, $request->user());

        return to_route('permits.show', $permit)->with('success', __('permits.messages.withdrawn'));
    }

    /**
     * The middleware guarantees the user belongs to an organization.
     */
    private function organization(Request $request): Organization
    {
        /** @var Organization $organization */
        $organization = $request->user()->organization;

        return $organization;
    }

    /**
     * A filter value from the query string; anything that is not text counts as absent.
     */
    private function text(Request $request, string $key): string
    {
        return is_string($request->query($key)) ? trim($request->query($key)) : '';
    }

    /**
     * The register the applicant chooses the primary vehicle and trailers from.
     */
    private function vehicles(Organization $organization): AnonymousResourceCollection
    {
        return VehicleResource::collection(
            Vehicle::query()
                ->whereBelongsTo($organization)
                ->with('vehicleType')
                ->orderBy('plate_normalized')
                ->get(),
        );
    }

    /**
     * The limits the form explains and the date picker starts from; the
     * server validates them again.
     *
     * @return array{max_trailers: int, max_duration_days: int}
     */
    private function limits(): array
    {
        return [
            'max_trailers' => (int) config('permits.max_trailers'),
            'max_duration_days' => ValidityRules::maxDurationDays(),
        ];
    }
}

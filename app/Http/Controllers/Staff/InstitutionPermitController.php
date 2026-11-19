<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Institution\IssueInstitutionPermit;
use App\Actions\Institution\RevokeInstitutionPermit;
use App\Domain\Documents\StoredDocument;
use App\Domain\Institution\InstitutionFleet;
use App\Domain\Permits\ValidityRules;
use App\Domain\Vehicles\PlateNumber;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Http\Controllers\Controller;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Support\EthiopianCalendar;
use App\Support\Paginated;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The permits of the Authority's own vehicles. Whoever holds
 * institution-permits.issue gives a registered vehicle
 * (InstitutionVehicleController) its permit directly, prints its paper, and
 * withdraws it when needed. No application, review, approval or payment is
 * involved.
 */
class InstitutionPermitController extends Controller
{
    private const int PER_PAGE = 20;

    /** What an institution permit can be on a given day: in force, starting later, expired or revoked. */
    private const array STATUSES = [PermitStatus::Active, PermitStatus::Issued, PermitStatus::Expired, PermitStatus::Revoked];

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can(Permission::ViewInstitutionPermits->value) ?? false, 403);

        $locale = app()->getLocale();
        $today = ValidityRules::today();
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $term = $search === '' ? '' : PlateNumber::searchTerm($search);
        $status = PermitStatus::tryFrom(is_string($request->query('status')) ? $request->query('status') : '');
        $status = in_array($status, self::STATUSES, true) ? $status : null;

        $permits = Permit::query()
            ->where('is_institutional', true)
            ->with(['vehicles.vehicleType', 'issuer:id,name'])
            ->when($status !== null, fn (Builder $query) => $query->inEffectiveStatus($status))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('permit_number', "%{$search}%")
                ->orWhereHas('vehicles', fn (Builder $query) => $query->whereLike('plate_normalized', "%{$term}%"))))
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Permit $permit): array => $this->presentPermit($permit, $today, $locale));

        return Inertia::render('staff/institution-permits/index', [
            'permits' => Paginated::present($permits),
            // With no vehicle registered there is nothing to give a permit to yet.
            'vehicleCount' => InstitutionFleet::vehicles()->count(),
            'filters' => ['search' => $search, 'status' => $status === null ? '' : $status->value],
            'statuses' => array_map(fn (PermitStatus $item): array => [
                'value' => $item->value,
                'label' => __("institution.statuses.{$item->value}"),
            ], self::STATUSES),
            'urls' => [
                'index' => route('staff.institution-permits.index'),
                'create' => $request->user()->can(Permission::IssueInstitutionPermits->value) ? route('staff.institution-permits.create') : null,
                'vehicles' => $request->user()->can(Permission::ViewInstitutionVehicles->value) ? route('staff.institution-vehicles.index') : null,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('issueInstitutional', Permit::class);

        $today = ValidityRules::today();
        $vehicles = $this->vehicles($today, app()->getLocale());
        // Opened from a vehicle's row: that vehicle is already chosen, when it can take a permit.
        $chosen = is_string($request->query('vehicle')) ? $request->query('vehicle') : null;

        return Inertia::render('staff/institution-permits/create', [
            'vehicles' => $vehicles,
            'preselected' => collect($vehicles)->where('id', $chosen)->where('permit', null)->pluck('id')->values()->all(),
            'today' => $today->toDateString(),
            'maxDays' => IssueInstitutionPermit::MAX_DAYS,
            'urls' => [
                'index' => route('staff.institution-permits.index'),
                'store' => route('staff.institution-permits.store'),
                'vehicles' => route('staff.institution-vehicles.index'),
                'registerVehicle' => route('staff.institution-vehicles.create'),
            ],
        ]);
    }

    public function store(Request $request, IssueInstitutionPermit $issue): RedirectResponse
    {
        Gate::authorize('issueInstitutional', Permit::class);

        $today = ValidityRules::today()->toDateString();
        $validated = $request->validate([
            'vehicles' => ['required', 'array', 'min:1', 'max:50'],
            'vehicles.*' => ['string', 'distinct', Rule::exists(Vehicle::class, 'id')],
            'valid_from' => ['required', 'date_format:Y-m-d', "after_or_equal:{$today}"],
            'valid_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'reason' => ['required', 'string', 'max:500'],
            'route' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'vehicles' => __('institution.fields.vehicles'),
            'valid_from' => __('institution.fields.valid_from'),
            'valid_to' => __('institution.fields.valid_to'),
            'reason' => __('institution.fields.reason'),
            'route' => __('institution.fields.route'),
        ]);

        $zone = (string) config('localization.display_timezone');
        $from = CarbonImmutable::createFromFormat('!Y-m-d', $validated['valid_from'], $zone);
        $to = CarbonImmutable::createFromFormat('!Y-m-d', $validated['valid_to'], $zone);
        $vehicles = InstitutionFleet::vehicles()->whereKey($validated['vehicles'])->get();

        if ($from === null || $to === null || $vehicles->count() !== count($validated['vehicles'])) {
            throw ValidationException::withMessages(['vehicles' => __('institution.errors.not_institution_vehicle')]);
        }

        // Each vehicle gets its own permit and paper; all of them, or none.
        $issued = DB::transaction(fn (): int => $vehicles
            ->each(fn (Vehicle $vehicle) => $issue->handle($request->user(), $vehicle, $from, $to, $validated['reason'], $validated['route'] ?? null))
            ->count());

        return to_route('staff.institution-permits.index')->with('success', trans_choice('institution.messages.issued', $issued, ['count' => $issued]));
    }

    public function revoke(Request $request, Permit $permit, RevokeInstitutionPermit $revoke): RedirectResponse
    {
        abort_unless($request->user()?->can(Permission::RevokeInstitutionPermits->value) ?? false, 403);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']], attributes: ['reason' => __('institution.fields.revoke_reason')]);

        $revoke->handle($permit, $request->user(), $validated['reason']);

        return back()->with('success', __('institution.messages.revoked'));
    }

    /**
     * The fleet, each vehicle with the permit it holds today, if any.
     *
     * @return list<array<string, mixed>>
     */
    private function vehicles(CarbonImmutable $today, string $locale): array
    {
        return InstitutionFleet::vehicles()
            ->with(['vehicleType', 'permits' => fn ($query) => $query
                ->where('is_institutional', true)
                ->where('status', PermitStatus::Issued->value)
                ->whereDate('valid_to', '>=', $today->toDateString())
                ->orderBy('valid_to')])
            ->orderBy('plate_normalized')
            ->get()
            ->map(function (Vehicle $vehicle) use ($locale): array {
                $permit = $vehicle->permits->first();

                return [
                    'id' => $vehicle->id,
                    'plate' => $vehicle->plate_normalized,
                    'type' => $vehicle->typeName($locale),
                    'permit' => $permit === null ? null : [
                        'number' => $permit->permit_number,
                        'valid_to' => EthiopianCalendar::present($permit->valid_to, $locale),
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPermit(Permit $permit, CarbonImmutable $today, string $locale): array
    {
        $status = $permit->effectiveStatus($today);
        $ready = StoredDocument::isUsable($permit);

        return [
            'id' => $permit->id,
            'number' => $permit->permit_number,
            'plates' => $permit->vehicles->pluck('plate_normalized')->all(),
            'vehicle_type' => $permit->vehicles->first()?->typeName($locale),
            'reason' => $permit->reason,
            'route' => $permit->route,
            'valid_from' => EthiopianCalendar::present($permit->valid_from, $locale),
            'valid_to' => EthiopianCalendar::present($permit->valid_to, $locale),
            'issued_by' => $permit->issuer?->name,
            'status' => $status->value,
            'status_label' => __("institution.statuses.{$status->value}"),
            'in_force' => in_array($status, [PermitStatus::Issued, PermitStatus::Active], true),
            'document_pending' => $permit->status === PermitStatus::Issued && ! $ready,
            // Short-lived signed links to the two papers; null while they are being prepared.
            'document_urls' => $ready
                ? collect(StoredDocument::LANGUAGES)->map(fn (string $column, string $language): string => URL::temporarySignedRoute(
                    'permits.document', now()->addMinutes(30), ['permit' => $permit->id, 'language' => $language],
                ))->all()
                : null,
            'revokeUrl' => request()->user()?->can(Permission::RevokeInstitutionPermits->value) ? route('staff.institution-permits.revoke', $permit) : null,
        ];
    }
}

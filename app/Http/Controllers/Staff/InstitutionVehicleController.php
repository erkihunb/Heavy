<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Vehicles\RegisterVehicle;
use App\Data\VehicleData;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Institution\InstitutionFleet;
use App\Domain\Permits\ValidityRules;
use App\Domain\Vehicles\PlateNumber;
use App\Domain\Vehicles\PlateRegistry;
use App\Enums\PermitStatus;
use App\Exceptions\DuplicateVehiclePlate;
use App\Exceptions\InvalidPlateNumber;
use App\Http\Controllers\Controller;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\EthiopianCalendar;
use App\Support\Paginated;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Authority's own vehicles: the list, registering one, changing it and
 * removing it. A vehicle is registered once and then only needs a permit for
 * each period (InstitutionPermitController). One that has ever held a permit
 * is kept, so its permits keep naming it; its plate is not changed while a
 * permit printed with it is in force.
 */
class InstitutionVehicleController extends Controller
{
    private const int PER_PAGE = 25;

    public function index(Request $request): Response
    {
        Gate::authorize('issueInstitutional', Permit::class);

        $locale = app()->getLocale();
        $today = ValidityRules::today()->toDateString();
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $term = $search === '' ? '' : PlateNumber::searchTerm($search);

        $vehicles = InstitutionFleet::vehicles()
            ->with(['vehicleType', 'permits' => fn (QueryBuilder $query) => $this->inForce($query, $today)])
            ->withCount('permits')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('plate_normalized', "%{$term}%")
                ->orWhereLike('plate_raw', "%{$search}%")
                ->orWhereLike('type_detail', "%{$search}%")))
            ->orderBy('plate_normalized')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(function (Vehicle $vehicle) use ($locale): array {
                $permit = $vehicle->permits->first();

                return [
                    'id' => $vehicle->id,
                    'plate' => $vehicle->plate_normalized,
                    'type' => $vehicle->typeName($locale),
                    'type_detail' => $vehicle->type_detail,
                    'registered_at' => EthiopianCalendar::present($vehicle->created_at?->setTimezone((string) config('localization.display_timezone')), $locale),
                    'permit' => $permit === null ? null : [
                        'number' => $permit->permit_number,
                        'valid_to' => EthiopianCalendar::present($permit->valid_to, $locale),
                    ],
                    // One that has held a permit stays on record, so its permits keep naming it.
                    'deletable' => $vehicle->permits_count === 0,
                    'editUrl' => route('staff.institution-vehicles.edit', $vehicle),
                    // Straight to the give-permit form with this vehicle chosen.
                    'issueUrl' => route('staff.institution-permits.create', ['vehicle' => $vehicle->id]),
                    'destroyUrl' => route('staff.institution-vehicles.destroy', $vehicle),
                ];
            });

        return Inertia::render('staff/institution-vehicles/index', [
            'vehicles' => Paginated::present($vehicles),
            'filters' => ['search' => $search],
            'total' => InstitutionFleet::vehicles()->count(),
            'urls' => [
                'index' => route('staff.institution-vehicles.index'),
                'create' => route('staff.institution-vehicles.create'),
                'permits' => route('staff.institution-permits.index'),
                'issue' => route('staff.institution-permits.create'),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('issueInstitutional', Permit::class);

        return Inertia::render('staff/institution-vehicles/form', [
            'vehicle' => null,
            'vehicleTypes' => $this->vehicleTypes(),
            'urls' => [
                'index' => route('staff.institution-vehicles.index'),
                'save' => route('staff.institution-vehicles.store'),
            ],
        ]);
    }

    public function store(Request $request, RegisterVehicle $register): RedirectResponse
    {
        Gate::authorize('issueInstitutional', Permit::class);

        $validated = $this->validated($request);

        try {
            $organization = InstitutionFleet::organizationFor($request->user());
            $register->handle($organization, new VehicleData(
                PlateNumber::parse($validated['plate']),
                (int) $validated['vehicle_type_id'],
                $organization->name_am,
                $validated['type_detail'] ?? null,
            ));
        } catch (InvalidPlateNumber) {
            throw ValidationException::withMessages(['plate' => __('institution.errors.plate_invalid')]);
        } catch (DuplicateVehiclePlate) {
            throw ValidationException::withMessages(['plate' => __('institution.errors.plate_taken')]);
        }

        return to_route('staff.institution-vehicles.index')->with('success', __('institution.messages.vehicle_registered'));
    }

    public function edit(Vehicle $vehicle): Response
    {
        Gate::authorize('issueInstitutional', Permit::class);
        $this->ensureInstitutional($vehicle);

        return Inertia::render('staff/institution-vehicles/form', [
            'vehicle' => [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate_raw,
                'vehicle_type_id' => $vehicle->vehicle_type_id,
                'type_detail' => $vehicle->type_detail,
                // Its plate is on a paper in force: it stays as printed.
                'plate_locked' => $this->holdsPermitInForce($vehicle),
            ],
            'vehicleTypes' => $this->vehicleTypes($vehicle->vehicle_type_id),
            'urls' => [
                'index' => route('staff.institution-vehicles.index'),
                'save' => route('staff.institution-vehicles.update', $vehicle),
            ],
        ]);
    }

    public function update(Request $request, Vehicle $vehicle, PlateRegistry $plates, AuditLog $audit): RedirectResponse
    {
        Gate::authorize('issueInstitutional', Permit::class);
        $this->ensureInstitutional($vehicle);

        $validated = $this->validated($request, $vehicle->vehicle_type_id);

        try {
            $plate = PlateNumber::parse($validated['plate']);
        } catch (InvalidPlateNumber) {
            throw ValidationException::withMessages(['plate' => __('institution.errors.plate_invalid')]);
        }

        $plateChanged = $plate->normalized !== $vehicle->plate_normalized;

        if ($plateChanged && $this->holdsPermitInForce($vehicle)) {
            throw ValidationException::withMessages(['plate' => __('institution.errors.plate_locked')]);
        }

        try {
            $plates->ensureAvailable($plate, $vehicle->organization_id, $vehicle->id);
        } catch (DuplicateVehiclePlate) {
            throw ValidationException::withMessages(['plate' => __('institution.errors.plate_taken')]);
        }

        DB::transaction(function () use ($vehicle, $plate, $validated, $request, $audit): void {
            $old = $vehicle->only(['plate_normalized', 'vehicle_type_id', 'type_detail']);

            $vehicle->fill([
                'plate_raw' => trim($plate->raw),
                'plate_normalized' => $plate->normalized,
                'region_code' => $plate->region->value,
                'vehicle_type_id' => (int) $validated['vehicle_type_id'],
                'type_detail' => filled($validated['type_detail'] ?? null) ? trim((string) $validated['type_detail']) : null,
            ]);

            if ($vehicle->isDirty()) {
                $vehicle->save();
                $audit->record(AuditEvent::InstitutionVehicleUpdated, $vehicle, $request->user(), old: $old, new: $vehicle->only(array_keys($old)));
            }
        });

        return to_route('staff.institution-vehicles.index')->with('success', __('institution.messages.vehicle_updated'));
    }

    public function destroy(Request $request, Vehicle $vehicle, AuditLog $audit): RedirectResponse
    {
        Gate::authorize('issueInstitutional', Permit::class);
        $this->ensureInstitutional($vehicle);

        if ($vehicle->permits()->exists()) {
            throw ValidationException::withMessages(['vehicle' => __('institution.errors.vehicle_has_permits')]);
        }

        DB::transaction(function () use ($vehicle, $request, $audit): void {
            $audit->record(AuditEvent::InstitutionVehicleDeleted, $vehicle, $request->user(), old: ['plate' => $vehicle->plate_normalized]);
            $vehicle->delete();
        });

        return back()->with('success', __('institution.messages.vehicle_deleted'));
    }

    /**
     * @return array{plate: string, vehicle_type_id: int|string, type_detail?: string|null}
     */
    private function validated(Request $request, ?int $currentTypeId = null): array
    {
        return $request->validate([
            'plate' => ['required', 'string', 'max:50'],
            // A type since switched off stays choosable for the vehicle that already has it.
            'vehicle_type_id' => ['required', 'integer', Rule::exists(VehicleType::class, 'id')->where(
                fn ($query) => $query->where('is_active', true)->when($currentTypeId !== null, fn ($query) => $query->orWhere('id', $currentTypeId)),
            )],
            'type_detail' => ['nullable', 'string', 'max:100'],
        ], attributes: [
            'plate' => __('institution.fields.plate'),
            'vehicle_type_id' => __('institution.fields.vehicle_type'),
            'type_detail' => __('institution.fields.type_detail'),
        ]);
    }

    /**
     * Another organization's vehicle is not found here, whatever its id.
     */
    private function ensureInstitutional(Vehicle $vehicle): void
    {
        abort_unless($vehicle->organization_id === InstitutionFleet::organization()?->id, 404);
    }

    private function holdsPermitInForce(Vehicle $vehicle): bool
    {
        return $this->inForce($vehicle->permits(), ValidityRules::today()->toDateString())->exists();
    }

    /**
     * Narrows a query over permits to the institution permits in force on the day given.
     */
    private function inForce(QueryBuilder $query, string $today): QueryBuilder
    {
        return $query
            ->where('permits.is_institutional', true)
            ->where('permits.status', PermitStatus::Issued->value)
            ->whereDate('permits.valid_to', '>=', $today)
            ->orderBy('permits.valid_to');
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function vehicleTypes(?int $currentTypeId = null): array
    {
        $locale = app()->getLocale();

        return VehicleType::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->when($currentTypeId !== null, fn (Builder $query) => $query->orWhere('id', $currentTypeId)))
            ->ordered()
            ->get()
            ->map(fn (VehicleType $type): array => ['id' => $type->id, 'name' => $type->localizedName($locale)])
            ->all();
    }
}

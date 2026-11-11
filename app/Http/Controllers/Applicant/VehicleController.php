<?php

namespace App\Http\Controllers\Applicant;

use App\Actions\Vehicles\RegisterVehicle;
use App\Actions\Vehicles\UpdateVehicle;
use App\Domain\Vehicles\PlateNumber;
use App\Enums\PlateRegion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\StoreVehicleRequest;
use App\Http\Requests\Applicant\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Http\Resources\VehicleTypeResource;
use App\Models\Organization;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The vehicle register of the applicant's own organization.
 */
class VehicleController extends Controller
{
    private const int PER_PAGE = 15;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Vehicle::class);

        $plate = is_string($request->query('plate')) ? trim($request->query('plate')) : '';
        $vehicleType = filter_var($request->query('vehicle_type'), FILTER_VALIDATE_INT) ?: null;

        $vehicles = Vehicle::query()
            ->whereBelongsTo($this->organization($request))
            ->with('vehicleType')
            ->when($plate !== '', fn (Builder $query) => $query->whereLike(
                'plate_normalized',
                '%'.addcslashes(PlateNumber::searchTerm($plate), '%_\\').'%',
            ))
            ->when($vehicleType !== null, fn (Builder $query) => $query->where('vehicle_type_id', $vehicleType))
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('applicant/vehicles/index', [
            'vehicles' => VehicleResource::collection($vehicles),
            'filters' => [
                'plate' => $plate,
                'vehicle_type' => $vehicleType,
            ],
            'vehicleTypes' => $this->vehicleTypes(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Vehicle::class);

        return Inertia::render('applicant/vehicles/create', [
            'vehicleTypes' => $this->vehicleTypes(),
            'plateRegions' => $this->plateRegions(),
        ]);
    }

    public function store(StoreVehicleRequest $request, RegisterVehicle $registerVehicle): RedirectResponse
    {
        $registerVehicle->handle($this->organization($request), $request->toData());

        return to_route('vehicles.index')->with('success', __('vehicles.registered_message'));
    }

    public function edit(Vehicle $vehicle): Response
    {
        Gate::authorize('update', $vehicle);

        return Inertia::render('applicant/vehicles/edit', [
            'vehicle' => VehicleResource::make($vehicle->load('vehicleType')),
            'vehicleTypes' => $this->vehicleTypes(alwaysInclude: $vehicle->vehicle_type_id),
            'plateRegions' => $this->plateRegions(),
            // The plate is fixed once the vehicle is on a submitted application.
            'plateLocked' => $vehicle->hasLockedPlate(),
        ]);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle, UpdateVehicle $updateVehicle): RedirectResponse
    {
        $updateVehicle->handle($vehicle, $request->toData());

        return to_route('vehicles.index')->with('success', __('vehicles.updated_message'));
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
     * Types that can be chosen: the active ones, plus the type a vehicle
     * already has if that was retired later.
     */
    private function vehicleTypes(?int $alwaysInclude = null): AnonymousResourceCollection
    {
        return VehicleTypeResource::collection(
            VehicleType::cachedOrdered()
                ->filter(fn (VehicleType $type): bool => $type->is_active || $type->id === $alwaysInclude)
                ->values(),
        );
    }

    /**
     * @return list<array{value: string, latin: string}>
     */
    private function plateRegions(): array
    {
        return array_map(
            fn (PlateRegion $region): array => ['value' => $region->value, 'latin' => $region->latinCode()],
            PlateRegion::cases(),
        );
    }
}

<?php

namespace App\Actions\Permits;

use App\Actions\Vehicles\CorrectVehicle;
use App\Actions\Vehicles\RegisterVehicle;
use App\Data\VehicleData;
use App\Enums\VehicleRole;
use App\Exceptions\DuplicateVehiclePlate;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;

/**
 * The vehicles of a request corrected on the office form, typed as on the
 * create form (plate, type, owner; then its trailer), turned into vehicles of
 * the organization's register:
 *
 * - a plate already in the register is that vehicle, its type and owner
 *   brought up to date while no request that has gone further carries it;
 * - a new plate corrects the request's own vehicle in that place when only
 *   open requests carry it (a typo is fixed, not left in the register);
 * - otherwise the new plate is registered as a new vehicle.
 */
final class ResolveEditedVehicles
{
    public function __construct(
        private readonly CorrectVehicle $correctVehicle,
        private readonly RegisterVehicle $registerVehicle,
    ) {}

    /**
     * @param  list<VehicleData>  $vehicles  the primary vehicle first, then its trailers
     * @return array{primary: string, trailers: list<string>}
     *
     * @throws ValidationException when a plate belongs to another organization
     */
    public function handle(Permit $permit, Organization $organization, array $vehicles, User $actor): array
    {
        $permit->loadMissing('vehicles');
        $current = [
            $permit->vehicles->first(fn (Vehicle $vehicle): bool => $vehicle->roleOnPermit() === VehicleRole::Primary),
            ...$permit->vehicles->filter(fn (Vehicle $vehicle): bool => $vehicle->roleOnPermit() === VehicleRole::Trailer)->values()->all(),
        ];

        $ids = [];

        foreach ($vehicles as $slot => $data) {
            try {
                $ids[] = $this->resolve($permit, $organization, $data, $current[$slot] ?? null, $actor)->id;
            } catch (DuplicateVehiclePlate $duplicate) {
                throw ValidationException::withMessages(["vehicles.{$slot}.plate" => $duplicate->userMessage()]);
            }
        }

        return ['primary' => $ids[0], 'trailers' => array_slice($ids, 1)];
    }

    private function resolve(Permit $permit, Organization $organization, VehicleData $data, ?Vehicle $current, User $actor): Vehicle
    {
        $known = Vehicle::query()
            ->where('organization_id', $organization->id)
            ->where('plate_normalized', $data->plate->normalized)
            ->first();

        if ($known !== null) {
            return $known->isOnlyOnOpenRequests($permit->id)
                ? $this->correctVehicle->handle($known, $data, $actor)
                : $known;
        }

        if ($current !== null && $current->isOnlyOnOpenRequests($permit->id)) {
            return $this->correctVehicle->handle($current, $data, $actor);
        }

        return $this->registerVehicle->handle($organization, $data);
    }
}

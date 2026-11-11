<?php

namespace App\Domain\Permits;

use App\Enums\VehicleRole;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use App\Models\Vehicle;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Writes the vehicles of a permit (spec §4.3) and keeps the two denormalised
 * columns the overlap rule depends on in step with the permit:
 * `period` = the validity as a daterange, `is_blocking` = whether the permit
 * currently reserves its vehicles. Call it inside the Action's transaction.
 */
final class PermitVehicles
{
    /**
     * Put exactly these vehicles on the permit: one primary and any trailers.
     *
     * @param  list<string>  $trailerVehicleIds
     *
     * @throws PermitRuleViolation
     */
    public function replace(Permit $permit, string $primaryVehicleId, array $trailerVehicleIds): void
    {
        $this->ensureAllowed($permit, $primaryVehicleId, $trailerVehicleIds);

        DB::table('permit_vehicles')->where('permit_id', $permit->id)->delete();

        $row = fn (string $vehicleId, VehicleRole $role): array => [
            'permit_id' => $permit->id,
            'vehicle_id' => $vehicleId,
            'role' => $role->value,
            'period' => $this->period($permit),
            'is_blocking' => $permit->status->blocksVehicles(),
        ];

        DB::table('permit_vehicles')->insert([
            $row($primaryVehicleId, VehicleRole::Primary),
            ...array_map(fn (string $id): array => $row($id, VehicleRole::Trailer), $trailerVehicleIds),
        ]);

        $permit->unsetRelation('vehicles');
    }

    /**
     * Bring `period` and `is_blocking` in line with the permit's dates and status.
     */
    public function sync(Permit $permit): void
    {
        DB::table('permit_vehicles')->where('permit_id', $permit->id)->update([
            'period' => $this->period($permit),
            'is_blocking' => $permit->status->blocksVehicles(),
        ]);

        $permit->unsetRelation('vehicles');
    }

    /**
     * What must hold before an application is handed to the Authority: one
     * primary vehicle, and every vehicle still belongs to the organization.
     *
     * @throws PermitRuleViolation
     */
    public function ensureComplete(Permit $permit): void
    {
        $vehicles = $permit->vehicles()->get();

        $primaries = $permit->vehicles()->wherePivot('role', VehicleRole::Primary->value)->count();

        if ($primaries !== 1) {
            throw new PermitRuleViolation('primary_vehicle_required');
        }

        if ($vehicles->contains(fn (Vehicle $vehicle): bool => $vehicle->organization_id !== $permit->organization_id)) {
            throw new PermitRuleViolation('vehicles_not_owned');
        }
    }

    /**
     * @param  list<string>  $trailerVehicleIds
     *
     * @throws PermitRuleViolation
     */
    private function ensureAllowed(Permit $permit, string $primaryVehicleId, array $trailerVehicleIds): void
    {
        $ids = [$primaryVehicleId, ...$trailerVehicleIds];
        $maxTrailers = (int) config('permits.max_trailers');

        if (count(array_unique($ids)) !== count($ids)) {
            throw new PermitRuleViolation('vehicles_duplicate');
        }

        if (count($trailerVehicleIds) > $maxTrailers) {
            throw new PermitRuleViolation('too_many_trailers', ['max' => $maxTrailers]);
        }

        $owned = Vehicle::query()
            ->whereKey($ids)
            ->where('organization_id', $permit->organization_id)
            ->count();

        if ($owned !== count($ids)) {
            throw new PermitRuleViolation('vehicles_not_owned');
        }
    }

    /**
     * Both days are included. The dates come from Carbon, never from input.
     */
    private function period(Permit $permit): Expression
    {
        return DB::raw(sprintf(
            "daterange('%s', '%s', '[]')",
            $permit->valid_from->toDateString(),
            $permit->valid_to->toDateString(),
        ));
    }
}

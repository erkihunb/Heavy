<?php

namespace App\Domain\Permits;

use App\Exceptions\OverlappingPermit;
use App\Models\Permit;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Spec §4.1: a vehicle may not be on two blocking permits (awaiting payment,
 * payment submitted, issued) whose dates overlap.
 *
 * At approval the guard locks the vehicle rows, so two approvals for the same
 * vehicle are decided one after the other; the exclusion constraint on
 * `permit_vehicles` is the final authority. At submission the same check runs
 * without locks, only to tell the applicant early.
 */
class OverlapGuard
{
    /**
     * @param  bool  $lock  lock the vehicle rows until the surrounding transaction ends
     *
     * @throws OverlappingPermit
     */
    public function ensureNoOverlap(Permit $permit, bool $lock = false): void
    {
        $vehicleIds = $this->vehicleIds($permit);

        if ($lock && $vehicleIds !== []) {
            // Always in the same order, so two approvals cannot deadlock.
            Vehicle::query()->whereKey($vehicleIds)->orderBy('id')->lockForUpdate()->get(['id']);
        }

        $conflict = $this->conflictQuery($permit, $vehicleIds)->first();

        if ($conflict !== null) {
            throw new OverlappingPermit(
                $conflict->plate,
                CarbonImmutable::parse($conflict->valid_from),
                CarbonImmutable::parse($conflict->valid_to),
            );
        }
    }

    /**
     * Every blocking permit that overlaps this one, for the staff detail page.
     *
     * @return Collection<int, stdClass> rows with permit_id, vehicle_id, plate, valid_from, valid_to
     */
    public function conflicts(Permit $permit): Collection
    {
        return $this->conflictQuery($permit, $this->vehicleIds($permit))->get();
    }

    /**
     * @return list<string>
     */
    private function vehicleIds(Permit $permit): array
    {
        /** @var list<string> */
        return DB::table('permit_vehicles')
            ->where('permit_id', $permit->id)
            ->orderBy('vehicle_id')
            ->pluck('vehicle_id')
            ->all();
    }

    /**
     * @param  list<string>  $vehicleIds
     */
    private function conflictQuery(Permit $permit, array $vehicleIds): Builder
    {
        return DB::table('permit_vehicles')
            ->join('permits', 'permits.id', '=', 'permit_vehicles.permit_id')
            ->join('vehicles', 'vehicles.id', '=', 'permit_vehicles.vehicle_id')
            ->whereIn('permit_vehicles.vehicle_id', $vehicleIds)
            ->where('permit_vehicles.permit_id', '!=', $permit->id)
            ->where('permit_vehicles.is_blocking', true)
            ->whereRaw("permit_vehicles.period && daterange(?::date, ?::date, '[]')", [
                $permit->valid_from->toDateString(),
                $permit->valid_to->toDateString(),
            ])
            ->orderBy('permits.valid_from')
            ->orderBy('permits.id')
            ->select([
                'permit_vehicles.permit_id',
                'permit_vehicles.vehicle_id',
                'vehicles.plate_normalized as plate',
                'permits.valid_from',
                'permits.valid_to',
            ]);
    }
}

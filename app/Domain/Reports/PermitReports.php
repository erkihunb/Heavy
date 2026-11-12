<?php

namespace App\Domain\Reports;

use App\Domain\Permits\ValidityRules;
use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Enums\VehicleRole;
use App\Support\EthiopianMonth;
use App\Support\Rbac\BranchScope;
use Illuminate\Support\Facades\DB;

/**
 * The figures of the reporting dashboard (spec §11). Everything is counted in
 * Ethiopian months, in the Authority's timezone. "Issued" means the permit
 * was issued in the period, whatever has happened to it since (revoked,
 * expired); revenue is the verified payments by the day they were paid.
 * Every figure can be limited to one branch (`$scope`): staff placed at a
 * branch see their own branch's figures.
 */
final class PermitReports
{
    /**
     * Permits by status as people see it today: issued permits split into
     * "issued" (not started), "active" and "expired" by their dates.
     *
     * @param  int|null  $organizationId  only this organization's permits
     * @return array<string, int>
     */
    public function byStatus(?int $organizationId = null, ?BranchScope $scope = null): array
    {
        $today = ValidityRules::today()->toDateString();
        $issued = PermitStatus::Issued->value;

        $rows = DB::table('permits')
            ->selectRaw(
                'CASE WHEN status = ? AND valid_to < ? THEN ? WHEN status = ? AND valid_from <= ? THEN ? ELSE status END AS effective, COUNT(*) AS total',
                [$issued, $today, PermitStatus::Expired->value, $issued, $today, PermitStatus::Active->value],
            )
            ->when($organizationId !== null, fn ($query) => $query->where('organization_id', $organizationId))
            ->when($scope !== null, fn ($query) => $scope->apply($query, 'branch_id'))
            ->groupBy('effective')
            ->pluck('total', 'effective');

        $counts = [];

        foreach (PermitStatus::cases() as $status) {
            $counts[$status->value] = (int) ($rows[$status->value] ?? 0);
        }

        return $counts;
    }

    public function issuedIn(EthiopianMonth $month, ?BranchScope $scope = null): int
    {
        [$from, $until] = $month->instants();

        return DB::table('permits')
            ->where('issued_at', '>=', $from)
            ->where('issued_at', '<', $until)
            ->when($scope !== null, fn ($query) => $scope->apply($query, 'branch_id'))
            ->count();
    }

    /**
     * Revenue of verified payments paid in the month, in santim.
     */
    public function revenueIn(EthiopianMonth $month, ?BranchScope $scope = null): int
    {
        return (int) DB::table('payments')
            ->where('status', PaymentStatus::Verified->value)
            ->whereBetween('paid_at', [$month->firstDay()->toDateString(), $month->lastDay()->toDateString()])
            ->when($scope !== null, fn ($query) => $query->whereIn('permit_id', $scope->apply(DB::table('permits'), 'branch_id')->select('id')))
            ->sum('amount_cents');
    }

    /**
     * Permits issued and revenue for each of the given months, oldest first.
     *
     * @param  list<EthiopianMonth>  $months
     * @return list<array{month: EthiopianMonth, issued: int, revenue_cents: int}>
     */
    public function monthly(array $months, ?BranchScope $scope = null): array
    {
        return array_map(fn (EthiopianMonth $month): array => [
            'month' => $month,
            'issued' => $this->issuedIn($month, $scope),
            'revenue_cents' => $this->revenueIn($month, $scope),
        ], $months);
    }

    /**
     * Permits issued in the period by the type of their primary vehicle, most first.
     *
     * @return list<array{vehicle_type_id: int, name_am: string, name_en: string, permits: int}>
     */
    public function byVehicleType(EthiopianMonth $from, EthiopianMonth $until, ?BranchScope $scope = null): array
    {
        [$start] = $from->instants();
        [, $end] = $until->instants();

        return DB::table('permits')
            ->join('permit_vehicles', function ($join): void {
                $join->on('permit_vehicles.permit_id', '=', 'permits.id')->where('permit_vehicles.role', VehicleRole::Primary->value);
            })
            ->join('vehicles', 'vehicles.id', '=', 'permit_vehicles.vehicle_id')
            ->join('vehicle_types', 'vehicle_types.id', '=', 'vehicles.vehicle_type_id')
            ->where('permits.issued_at', '>=', $start)
            ->where('permits.issued_at', '<', $end)
            ->when($scope !== null, fn ($query) => $scope->apply($query, 'permits.branch_id'))
            ->groupBy('vehicle_types.id', 'vehicle_types.name_am', 'vehicle_types.name_en')
            ->orderByDesc('permits')
            ->orderBy('vehicle_types.id')
            ->get(['vehicle_types.id as vehicle_type_id', 'vehicle_types.name_am', 'vehicle_types.name_en', DB::raw('COUNT(*) as permits')])
            ->map(fn (object $row): array => [
                'vehicle_type_id' => (int) $row->vehicle_type_id,
                'name_am' => (string) $row->name_am,
                'name_en' => (string) $row->name_en,
                'permits' => (int) $row->permits,
            ])
            ->all();
    }

    /**
     * The organizations with the most permits issued in the period, with the
     * revenue of those permits.
     *
     * @return list<array{organization_id: int, name_am: string, name_en: string|null, permits: int, revenue_cents: int}>
     */
    public function topOrganizations(EthiopianMonth $from, EthiopianMonth $until, int $limit = 10, ?BranchScope $scope = null): array
    {
        [$start] = $from->instants();
        [, $end] = $until->instants();

        $revenue = DB::table('payments')
            ->where('status', PaymentStatus::Verified->value)
            ->groupBy('permit_id')
            ->select('permit_id', DB::raw('SUM(amount_cents) as paid'));

        return DB::table('permits')
            ->join('organizations', 'organizations.id', '=', 'permits.organization_id')
            ->leftJoinSub($revenue, 'revenue', 'revenue.permit_id', '=', 'permits.id')
            ->where('permits.issued_at', '>=', $start)
            ->where('permits.issued_at', '<', $end)
            ->when($scope !== null, fn ($query) => $scope->apply($query, 'permits.branch_id'))
            ->groupBy('organizations.id', 'organizations.name_am', 'organizations.name_en')
            ->orderByDesc('permits')
            ->orderBy('organizations.id')
            ->limit($limit)
            ->get([
                'organizations.id as organization_id',
                'organizations.name_am',
                'organizations.name_en',
                DB::raw('COUNT(*) as permits'),
                DB::raw('COALESCE(SUM(revenue.paid), 0) as revenue_cents'),
            ])
            ->map(fn (object $row): array => [
                'organization_id' => (int) $row->organization_id,
                'name_am' => (string) $row->name_am,
                'name_en' => $row->name_en === null ? null : (string) $row->name_en,
                'permits' => (int) $row->permits,
                'revenue_cents' => (int) $row->revenue_cents,
            ])
            ->all();
    }

    /**
     * Issued permits whose last day is within the next `$days` days, today included.
     *
     * @return array{count: int, permits: list<array{id: string, permit_number: string|null, organization_am: string, organization_en: string|null, plate: string|null, valid_to: string}>}
     */
    public function expiringSoon(int $days = 7, int $limit = 20, ?BranchScope $scope = null): array
    {
        $today = ValidityRules::today();

        $query = DB::table('permits')
            ->where('permits.status', PermitStatus::Issued->value)
            ->whereBetween('permits.valid_to', [$today->toDateString(), $today->addDays($days)->toDateString()])
            ->when($scope !== null, fn ($query) => $scope->apply($query, 'permits.branch_id'));

        $permits = (clone $query)
            ->join('organizations', 'organizations.id', '=', 'permits.organization_id')
            ->leftJoin('permit_vehicles', function ($join): void {
                $join->on('permit_vehicles.permit_id', '=', 'permits.id')->where('permit_vehicles.role', VehicleRole::Primary->value);
            })
            ->leftJoin('vehicles', 'vehicles.id', '=', 'permit_vehicles.vehicle_id')
            ->orderBy('permits.valid_to')
            ->orderBy('permits.id')
            ->limit($limit)
            ->get([
                'permits.id', 'permits.permit_number', 'permits.valid_to',
                'organizations.name_am', 'organizations.name_en', 'vehicles.plate_normalized',
            ])
            ->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'permit_number' => $row->permit_number === null ? null : (string) $row->permit_number,
                'organization_am' => (string) $row->name_am,
                'organization_en' => $row->name_en === null ? null : (string) $row->name_en,
                'plate' => $row->plate_normalized === null ? null : (string) $row->plate_normalized,
                'valid_to' => (string) $row->valid_to,
            ])
            ->all();

        return ['count' => $query->count(), 'permits' => $permits];
    }
}

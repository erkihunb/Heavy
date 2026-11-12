<?php

namespace App\Domain\Reports;

use App\Enums\PaymentStatus;
use App\Enums\PermitStatus;
use App\Enums\ScanResult;
use App\Support\EthiopianCalendar;
use App\Support\EthiopianMonth;
use App\Support\Rbac\BranchScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The charts and progress figures of the dashboard: trends over months and
 * days, how review decisions and payments went this month and what officers'
 * scans found today. Days are the Authority's days (display timezone); every
 * figure can be limited to one organization or one branch.
 */
final class DashboardInsights
{
    public function __construct(private readonly PermitReports $reports) {}

    /**
     * Permits issued and revenue for the last `$count` Ethiopian months, oldest first.
     *
     * @return list<array{key: string, label: string, short: string, issued: int, revenue_cents: int}>
     */
    public function trend(int $count, string $locale, ?BranchScope $scope = null): array
    {
        return array_map(fn (array $row): array => [
            'key' => $row['month']->key(),
            'label' => $row['month']->label($locale),
            'short' => EthiopianCalendar::monthName($row['month']->month, $locale),
            'issued' => $row['issued'],
            'revenue_cents' => $row['revenue_cents'],
        ], $this->reports->monthly(EthiopianMonth::current()->lastMonths($count), $scope));
    }

    /**
     * Applications sent in (first submissions and resubmissions) on each of
     * the last `$days` days, today last.
     *
     * @return list<array{date: string, label: string, gregorian: string, count: int}>
     */
    public function submissionsByDay(int $days, string $locale, ?int $organizationId = null, ?BranchScope $scope = null): array
    {
        $timezone = $this->timezone();
        $first = CarbonImmutable::now($timezone)->startOfDay()->subDays($days - 1);

        $buckets = DB::table('permit_status_transitions')
            ->where('to_status', PermitStatus::Submitted->value)
            ->where('created_at', '>=', $first->utc())
            ->when($organizationId !== null || $scope !== null, fn (Builder $query) => $query->whereIn(
                'permit_id',
                $this->permitIds($organizationId, $scope),
            ))
            ->pluck('created_at')
            ->countBy(fn (string $at): string => CarbonImmutable::parse($at, 'UTC')->setTimezone($timezone)->toDateString());

        $rows = [];

        for ($day = $first; count($rows) < $days; $day = $day->addDay()) {
            $date = EthiopianCalendar::toEthiopian($day);

            $rows[] = [
                'date' => $day->toDateString(),
                'label' => EthiopianCalendar::monthName($date->month, $locale).' '.$date->day,
                'gregorian' => $day->format('M j'),
                'count' => (int) ($buckets[$day->toDateString()] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * What reviewers and approvers decided this month: approved (sent for
     * payment), returned for changes and rejected.
     *
     * @return array{approved: int, changes_requested: int, rejected: int}
     */
    public function decisionsIn(EthiopianMonth $month, ?BranchScope $scope = null): array
    {
        [$from, $until] = $month->instants();

        $rows = DB::table('permit_status_transitions')
            ->whereIn('to_status', [PermitStatus::AwaitingPayment->value, PermitStatus::ChangesRequested->value, PermitStatus::Rejected->value])
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $until)
            ->when($scope !== null, fn (Builder $query) => $query->whereIn('permit_id', $this->permitIds(null, $scope)))
            ->groupBy('to_status')
            ->selectRaw('to_status, COUNT(*) as total')
            ->pluck('total', 'to_status');

        return [
            'approved' => (int) ($rows[PermitStatus::AwaitingPayment->value] ?? 0),
            'changes_requested' => (int) ($rows[PermitStatus::ChangesRequested->value] ?? 0),
            'rejected' => (int) ($rows[PermitStatus::Rejected->value] ?? 0),
        ];
    }

    /**
     * Payments sent in this month by what happened to them, with the amounts.
     *
     * @return array<string, array{count: int, amount_cents: int}>
     */
    public function paymentsIn(EthiopianMonth $month, ?BranchScope $scope = null): array
    {
        [$from, $until] = $month->instants();

        $rows = DB::table('payments')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $until)
            ->when($scope !== null, fn (Builder $query) => $query->whereIn('permit_id', $this->permitIds(null, $scope)))
            ->groupBy('status')
            ->get(['status', DB::raw('COUNT(*) as total'), DB::raw('COALESCE(SUM(amount_cents), 0) as amount')])
            ->keyBy('status');

        $result = [];

        foreach (PaymentStatus::cases() as $status) {
            $row = $rows->get($status->value);
            $result[$status->value] = [
                'count' => (int) ($row->total ?? 0),
                'amount_cents' => (int) ($row->amount ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Today's verification checks by their result.
     *
     * @return array<string, int>
     */
    public function scansToday(): array
    {
        $rows = DB::table('verification_scans')
            ->where('scanned_at', '>=', CarbonImmutable::now($this->timezone())->startOfDay()->utc())
            ->groupBy('result')
            ->selectRaw('result, COUNT(*) as total')
            ->pluck('total', 'result');

        $result = [];

        foreach (ScanResult::cases() as $case) {
            $result[$case->value] = (int) ($rows[$case->value] ?? 0);
        }

        return $result;
    }

    /**
     * Verification checks on each of the last `$days` days, today last.
     *
     * @return list<array{date: string, label: string, gregorian: string, count: int}>
     */
    public function scansByDay(int $days, string $locale): array
    {
        $timezone = $this->timezone();
        $first = CarbonImmutable::now($timezone)->startOfDay()->subDays($days - 1);

        $buckets = DB::table('verification_scans')
            ->where('scanned_at', '>=', $first->utc())
            ->pluck('scanned_at')
            ->countBy(fn (string $at): string => CarbonImmutable::parse($at, 'UTC')->setTimezone($timezone)->toDateString());

        $rows = [];

        for ($day = $first; count($rows) < $days; $day = $day->addDay()) {
            $date = EthiopianCalendar::toEthiopian($day);

            $rows[] = [
                'date' => $day->toDateString(),
                'label' => EthiopianCalendar::monthName($date->month, $locale).' '.$date->day,
                'gregorian' => $day->format('M j'),
                'count' => (int) ($buckets[$day->toDateString()] ?? 0),
            ];
        }

        return $rows;
    }

    private function permitIds(?int $organizationId, ?BranchScope $scope): Builder
    {
        return DB::table('permits')
            ->when($organizationId !== null, fn (Builder $query) => $query->where('organization_id', $organizationId))
            ->when($scope !== null, fn (Builder $query) => $scope->apply($query, 'branch_id'))
            ->select('id');
    }

    private function timezone(): string
    {
        return (string) config('localization.display_timezone');
    }
}

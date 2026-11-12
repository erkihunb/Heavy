<?php

namespace App\Http\Controllers\Staff;

use App\Domain\Reports\PermitReports;
use App\Http\Controllers\Controller;
use App\Support\EthiopianCalendar;
use App\Support\EthiopianMonth;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reporting dashboard (spec §11): permits by status, issued and revenue
 * per Ethiopian month for the last twelve months, the vehicle types and
 * organizations behind them, and what expires in the next seven days.
 */
class ReportController extends Controller
{
    private const int MONTHS = 12;

    private const int EXPIRING_DAYS = 7;

    public function index(Request $request, PermitReports $reports): Response
    {
        Gate::authorize('viewReports');

        // Staff placed at a branch get that branch's figures.
        $scope = $request->user()->branchScope();

        $locale = app()->getLocale();
        $current = EthiopianMonth::current();
        $months = $current->lastMonths(self::MONTHS);
        $name = fn (string $am, ?string $en): string => $locale === 'en' && $en !== null && $en !== '' ? $en : $am;

        $monthly = $reports->monthly($months, $scope);
        $thisMonth = $monthly[array_key_last($monthly)];

        return Inertia::render('staff/reports/index', [
            'period' => [
                'from' => $months[0]->label($locale),
                'to' => $current->label($locale),
            ],
            'summary' => [
                'month' => $current->label($locale),
                'issued_this_month' => $thisMonth['issued'],
                'revenue_this_month' => Money::present($thisMonth['revenue_cents']),
                'revenue_period' => Money::present(array_sum(array_column($monthly, 'revenue_cents'))),
                'issued_period' => array_sum(array_column($monthly, 'issued')),
            ],
            'byStatus' => $reports->byStatus(scope: $scope),
            'monthly' => array_map(fn (array $row): array => [
                'key' => $row['month']->key(),
                'label' => $row['month']->label($locale),
                'issued' => $row['issued'],
                'revenue' => Money::present($row['revenue_cents']),
            ], $monthly),
            'byVehicleType' => array_map(fn (array $row): array => [
                'name' => $name($row['name_am'], $row['name_en']),
                'permits' => $row['permits'],
            ], $reports->byVehicleType($months[0], $current, $scope)),
            'topOrganizations' => array_map(fn (array $row): array => [
                'name' => $name($row['name_am'], $row['name_en']),
                'permits' => $row['permits'],
                'revenue' => Money::present($row['revenue_cents']),
            ], $reports->topOrganizations($months[0], $current, scope: $scope)),
            'expiring' => (function () use ($reports, $locale, $name, $scope): array {
                $expiring = $reports->expiringSoon(self::EXPIRING_DAYS, scope: $scope);

                return [
                    'days' => self::EXPIRING_DAYS,
                    'count' => $expiring['count'],
                    'permits' => array_map(fn (array $row): array => [
                        'id' => $row['id'],
                        'permit_number' => $row['permit_number'],
                        'organization' => $name($row['organization_am'], $row['organization_en']),
                        'plate' => $row['plate'],
                        'valid_to' => EthiopianCalendar::present(CarbonImmutable::parse($row['valid_to'], 'UTC'), $locale),
                    ], $expiring['permits']),
                ];
            })(),
        ]);
    }
}

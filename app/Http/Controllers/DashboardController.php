<?php

namespace App\Http\Controllers;

use App\Domain\Permits\ValidityRules;
use App\Domain\Reports\DashboardInsights;
use App\Domain\Reports\PermitReports;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\PermitStatusTransition;
use App\Models\User;
use App\Models\VerificationScan;
use App\Support\EthiopianCalendar;
use App\Support\EthiopianMonth;
use App\Support\Money;
use App\Support\Rbac\BranchScope;
use App\Support\Rbac\Landing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's home screen (EUISIS dashboard): headline figures,
 * what needs attention, how permits are spread over their statuses and the
 * latest activity — each for what the person's permissions allow. Seeing
 * the dashboard at all takes the `dashboard.view` permission.
 */
class DashboardController extends Controller
{
    private const int ACTIVITY = 8;

    private const int EXPIRING_DAYS = 7;

    private const int TREND_MONTHS = 6;

    private const int TREND_DAYS = 14;

    private const int SCAN_DAYS = 7;

    public function __invoke(Request $request, PermitReports $reports, DashboardInsights $insights): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // The dashboard has a permission of its own; without it the person
        // starts on the first page their permissions open.
        if (! $user->can(Permission::ViewDashboard->value)) {
            return to_route(Landing::routeFor($user));
        }

        // Each figure by the permission of the work it belongs to; no role is named here.
        // Those who decide on requests; the cashier and Issuer open applications too, but do not review them.
        $reviews = $user->can(Permission::ReviewPermits->value) || $user->can(Permission::ApprovePermits->value);
        $oversees = $user->can('viewQueue', Permit::class);
        $collects = $user->can(Permission::VerifyPayments->value);
        $issues = $user->can(Permission::IssuePermits->value);
        $cashier = $user->can('viewQueue', Payment::class);
        $seesReports = $user->can(Permission::ViewReports->value);
        $officer = $user->can('scan', Permit::class);
        // Those who can open the list of permits (the queue, the register or the reports).
        $seesPermits = $oversees || $seesReports || $user->can(Permission::RegisterApplicants->value);
        // People placed at a branch count that branch only.
        $scope = $user->branchScope();

        $today = ValidityRules::today();
        $expiringUntil = $today->addDays(self::EXPIRING_DAYS)->toDateString();

        // Permits this person may count: those of the branches they work at.
        $permits = fn (): Builder => $scope->apply(Permit::query(), 'branch_id');

        $counts = [
            'in_force' => $seesPermits
                ? $permits()->inEffectiveStatus(PermitStatus::Active)->count()
                : null,
            'waiting_review' => $reviews
                ? Permit::query()->inBranchOf($user)->whereIn('status', [PermitStatus::Submitted, PermitStatus::UnderReview])->count()
                : null,
            'payments_to_verify' => $cashier
                ? Payment::query()->where('status', PaymentStatus::Submitted)->whereHas('permit', fn (Builder $query) => $query->inBranchOf($user))->count()
                : null,
            'expiring' => $seesPermits
                ? $permits()->where('status', PermitStatus::Issued)->whereBetween('valid_to', [$today->toDateString(), $expiringUntil])->count()
                : null,
            'to_collect' => $collects
                ? Permit::query()->inBranchOf($user)->where('status', PermitStatus::AwaitingPayment)->count()
                : null,
            'to_issue' => $issues
                ? Permit::query()->inBranchOf($user)->where('status', PermitStatus::PaymentConfirmed)->count()
                : null,
            'scans_today' => $officer
                ? VerificationScan::query()->where('scanned_at', '>=', now((string) config('localization.display_timezone'))->startOfDay()->utc())->count()
                : null,
        ];

        $month = EthiopianMonth::current();
        $locale = app()->getLocale();
        $trend = $seesReports ? $insights->trend(self::TREND_MONTHS, $locale, $scope) : null;

        return Inertia::render('dashboard', [
            'account' => [
                'emailVerified' => $user->hasVerifiedEmail(),
                'twoFactorEnabled' => $user->hasEnabledTwoFactorAuthentication(),
                // Staff are asked to turn two-factor authentication on.
                'staff' => $user->isStaff(),
            ],
            'counts' => array_filter($counts, fn (?int $count): bool => $count !== null),
            'month' => $trend !== null ? $this->month($trend, $month->label($locale)) : null,
            // Charts and progress figures, each only for those whose work they concern.
            'insights' => [
                'month' => $month->label($locale),
                'trend' => $trend === null ? null : array_map(fn (array $row): array => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'short' => $row['short'],
                    'issued' => $row['issued'],
                    'revenue' => Money::present($row['revenue_cents']),
                ], $trend),
                'submissions' => $reviews || $seesReports
                    ? $insights->submissionsByDay(self::TREND_DAYS, $locale, null, $scope)
                    : null,
                'decisions' => $reviews || $seesReports ? $insights->decisionsIn($month, $scope) : null,
                'payments' => $cashier || $seesReports
                    ? array_map(fn (array $row): array => [
                        'count' => $row['count'],
                        'amount' => Money::present($row['amount_cents']),
                    ], $insights->paymentsIn($month, $scope))
                    : null,
                'scans' => $officer || $seesReports ? [
                    'today' => $insights->scansToday(),
                    'days' => $insights->scansByDay(self::SCAN_DAYS, $locale),
                ] : null,
            ],
            'distribution' => $reviews || $seesReports ? $reports->byStatus(null, $scope) : null,
            'activity' => $oversees ? $this->activity($scope) : null,
        ]);
    }

    /**
     * This month's headline figures with the change from last month and the
     * months before for a sparkline.
     *
     * @param  list<array{key: string, label: string, short: string, issued: int, revenue_cents: int}>  $trend
     * @return array<string, mixed>
     */
    private function month(array $trend, string $label): array
    {
        $current = $trend[array_key_last($trend)];
        $previous = count($trend) > 1 ? $trend[count($trend) - 2] : null;

        return [
            'label' => $label,
            'issued' => $current['issued'],
            'revenue' => Money::present($current['revenue_cents']),
            'previous' => $previous === null ? null : [
                'label' => $previous['label'],
                'issued' => $previous['issued'],
                'revenue' => Money::present($previous['revenue_cents']),
                'revenue_cents' => $previous['revenue_cents'],
            ],
            'revenue_cents' => $current['revenue_cents'],
        ];
    }

    /**
     * The latest permit status changes, newest first, each with the status
     * its permit has now.
     *
     * @return list<array<string, mixed>>
     */
    private function activity(BranchScope $scope): array
    {
        $timezone = (string) config('localization.display_timezone');
        $locale = app()->getLocale();

        return PermitStatusTransition::query()
            ->with(['permit:id,permit_number,organization_id,permit_type,status,valid_from,valid_to', 'permit.organization:id,name_am,name_en', 'actor:id,name'])
            ->whereIn('permit_id', $scope->apply(DB::table('permits'), 'branch_id')->select('id'))
            ->latest('id')
            ->limit(self::ACTIVITY)
            ->get()
            ->map(fn (PermitStatusTransition $step): array => [
                'id' => $step->id,
                'permit_id' => $step->permit_id,
                'reference' => $step->permit->permit_number ?? strtoupper(substr($step->permit_id, -8)),
                'organization' => $step->permit->organization->localizedName($locale),
                'from' => $step->from_status?->value,
                'to' => $step->to_status->value,
                // Where the permit stands today: a step in this list may be long past.
                'now' => $step->permit->effectiveStatus(CarbonImmutable::now())->value,
                'at' => EthiopianCalendar::present($step->created_at->setTimezone($timezone), $locale),
                'time' => $step->created_at->setTimezone($timezone)->format('H:i'),
                'actor' => $step->actor?->name,
            ])
            ->all();
    }
}

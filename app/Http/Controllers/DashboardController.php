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
use App\Models\Vehicle;
use App\Models\VerificationScan;
use App\Support\EthiopianCalendar;
use App\Support\EthiopianMonth;
use App\Support\Money;
use App\Support\Rbac\BranchScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's home screen (EUISIS dashboard): headline figures,
 * what needs attention, how permits are spread over their statuses and the
 * latest activity — each for what the person's role may see.
 */
class DashboardController extends Controller
{
    private const int ACTIVITY = 8;

    private const int EXPIRING_DAYS = 7;

    private const int TREND_MONTHS = 6;

    private const int TREND_DAYS = 14;

    private const int SCAN_DAYS = 7;

    public function __invoke(Request $request, PermitReports $reports, DashboardInsights $insights): Response
    {
        /** @var User $user */
        $user = $request->user();
        $applicant = $user->can('viewAny', Vehicle::class);
        $organizationId = $applicant ? $user->organization_id : null;
        // Those who decide on requests; the cashier and Issuer open applications too, but do not review them.
        $reviews = $user->can(Permission::ReviewPermits->value) || $user->can(Permission::ApprovePermits->value);
        $oversees = $user->can('viewQueue', Permit::class);
        $collects = $user->can(Permission::VerifyPayments->value);
        $issues = $user->can(Permission::IssuePermits->value);
        $cashier = $user->can('viewQueue', Payment::class);
        $seesReports = $user->can(Permission::ViewReports->value);
        $officer = $user->can('scan', Permit::class);
        $staff = $user->isStaff();
        // Staff placed at a branch count that branch only.
        $scope = $staff ? $user->branchScope() : null;

        $today = ValidityRules::today();
        $expiringUntil = $today->addDays(self::EXPIRING_DAYS)->toDateString();

        // Permits this person may count: their organization's, or all of them for staff.
        $permits = fn (): Builder => Permit::query()
            ->when($applicant, fn (Builder $query) => $query->where('organization_id', $organizationId ?? 0))
            ->when($scope !== null, fn (Builder $query) => $scope->apply($query, 'branch_id'));

        $counts = [
            'vehicles' => $applicant && $organizationId !== null
                ? Vehicle::query()->where('organization_id', $organizationId)->count()
                : null,
            'in_force' => ($applicant && $organizationId !== null) || $staff
                ? $permits()->inEffectiveStatus(PermitStatus::Active)->count()
                : null,
            'in_progress' => $applicant && $organizationId !== null
                ? $permits()->whereIn('status', [PermitStatus::Submitted, PermitStatus::UnderReview])->count()
                : null,
            'changes_requested' => $applicant && $organizationId !== null
                ? $permits()->where('status', PermitStatus::ChangesRequested)->count()
                : null,
            'awaiting_payment' => $applicant && $organizationId !== null
                ? $permits()->where('status', PermitStatus::AwaitingPayment)->count()
                : null,
            'waiting_review' => $reviews
                ? Permit::query()->inBranchOf($user)->whereIn('status', [PermitStatus::Submitted, PermitStatus::UnderReview])->count()
                : null,
            'payments_to_verify' => $cashier
                ? Payment::query()->where('status', PaymentStatus::Submitted)->whereHas('permit', fn (Builder $query) => $query->inBranchOf($user))->count()
                : null,
            'expiring' => ($applicant && $organizationId !== null) || $staff
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
        $hasOrganization = $applicant && $organizationId !== null;
        $trend = $seesReports ? $insights->trend(self::TREND_MONTHS, $locale, $scope) : null;

        return Inertia::render('dashboard', [
            'account' => [
                'emailVerified' => $user->hasVerifiedEmail(),
                'twoFactorEnabled' => $user->hasEnabledTwoFactorAuthentication(),
            ],
            // Where an applicant stands in setting up their register; null for everyone else.
            'registration' => $applicant ? [
                'hasOrganization' => $organizationId !== null,
                'vehicleCount' => (int) ($counts['vehicles'] ?? 0),
            ] : null,
            // Applying online is paused while requests come in by letter at the office.
            'canApply' => $user->can('create', Permit::class),
            'counts' => array_filter($counts, fn (?int $count): bool => $count !== null),
            'month' => $trend !== null ? $this->month($trend, $month->label($locale)) : null,
            // Charts and progress figures, each only for the roles it concerns.
            'insights' => [
                'month' => $month->label($locale),
                'trend' => $trend === null ? null : array_map(fn (array $row): array => [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'short' => $row['short'],
                    'issued' => $row['issued'],
                    'revenue' => Money::present($row['revenue_cents']),
                ], $trend),
                'submissions' => $hasOrganization || $reviews || $seesReports
                    ? $insights->submissionsByDay(self::TREND_DAYS, $locale, $applicant ? $organizationId : null, $scope)
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
                'setup' => $applicant ? $this->setup($user, $organizationId, (int) ($counts['vehicles'] ?? 0)) : null,
            ],
            'distribution' => ($applicant && $organizationId !== null) || $reviews || $seesReports
                ? $reports->byStatus($applicant ? $organizationId : null, $scope)
                : null,
            'activity' => ($applicant && $organizationId !== null) || $oversees
                ? $this->activity($user, $applicant ? $organizationId : null, $scope)
                : null,
            'recentPermits' => $applicant && $organizationId !== null
                ? $permits()
                    ->latest('updated_at')
                    ->limit(5)
                    ->get()
                    ->map(fn (Permit $permit): array => [
                        'id' => $permit->id,
                        'permit_number' => $permit->permit_number,
                        'permit_type' => $permit->permit_type->value,
                        'status' => $permit->effectiveStatus(now())->value,
                        'valid_from' => EthiopianCalendar::present($permit->valid_from, app()->getLocale()),
                        'valid_to' => EthiopianCalendar::present($permit->valid_to, app()->getLocale()),
                        'progress' => $this->validity($permit, $today),
                    ])
                    ->all()
                : null,
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
     * How far an applicant is with getting their first permit.
     *
     * @return list<array{key: string, done: bool}>
     */
    private function setup(User $user, ?int $organizationId, int $vehicles): array
    {
        $permits = fn (): Builder => Permit::query()->where('organization_id', $organizationId ?? 0);

        return [
            ['key' => 'organization', 'done' => $organizationId !== null],
            ['key' => 'vehicle', 'done' => $vehicles > 0],
            ['key' => 'applied', 'done' => $organizationId !== null && $permits()->whereNotNull('submitted_at')->exists()],
            ['key' => 'issued', 'done' => $organizationId !== null && $permits()->whereNotNull('issued_at')->exists()],
            ['key' => 'two_factor', 'done' => $user->hasEnabledTwoFactorAuthentication()],
        ];
    }

    /**
     * How much of a permit's validity has gone by, for a permit in force today.
     *
     * @return array{percent: int, days_left: int, days: int}|null
     */
    private function validity(Permit $permit, CarbonImmutable $today): ?array
    {
        if ($permit->effectiveStatus(now()) !== PermitStatus::Active) {
            return null;
        }

        $from = CarbonImmutable::parse($permit->valid_from->toDateString());
        $to = CarbonImmutable::parse($permit->valid_to->toDateString());
        $days = (int) $from->diffInDays($to) + 1;
        $used = (int) $from->diffInDays($today) + 1;

        return [
            'percent' => (int) min(100, max(0, round($used / $days * 100))),
            'days_left' => max(0, $days - $used),
            'days' => $days,
        ];
    }

    /**
     * The latest permit status changes, newest first.
     *
     * @return list<array<string, mixed>>
     */
    private function activity(User $viewer, ?int $organizationId, ?BranchScope $scope): array
    {
        $timezone = (string) config('localization.display_timezone');
        $locale = app()->getLocale();

        return PermitStatusTransition::query()
            ->with(['permit:id,permit_number,organization_id,permit_type', 'permit.organization:id,name_am,name_en', 'actor:id,name'])
            ->when($organizationId !== null, fn (Builder $query) => $query->whereIn(
                'permit_id',
                DB::table('permits')->where('organization_id', $organizationId)->select('id'),
            ))
            ->when($scope !== null, fn (Builder $query) => $query->whereIn(
                'permit_id',
                $scope->apply(DB::table('permits'), 'branch_id')->select('id'),
            ))
            ->latest('id')
            ->limit(self::ACTIVITY)
            ->get()
            ->map(fn (PermitStatusTransition $step): array => [
                'id' => $step->id,
                'permit_id' => $step->permit_id,
                'reference' => $step->permit->permit_number ?? strtoupper(substr($step->permit_id, -8)),
                'organization' => $organizationId === null ? $step->permit->organization->localizedName($locale) : null,
                'from' => $step->from_status?->value,
                'to' => $step->to_status->value,
                'at' => EthiopianCalendar::present($step->created_at->setTimezone($timezone), $locale),
                'time' => $step->created_at->setTimezone($timezone)->format('H:i'),
                // Applicants see what happened, not which member of staff did it.
                'actor' => $organizationId === null ? $step->actor?->name : null,
                'staff_link' => $organizationId === null,
            ])
            ->all();
    }
}

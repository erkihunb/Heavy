<?php

namespace App\Http\Controllers;

use App\Domain\Permits\ValidityRules;
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

    public function __invoke(Request $request, PermitReports $reports): Response
    {
        /** @var User $user */
        $user = $request->user();
        $applicant = $user->can('viewAny', Vehicle::class);
        $organizationId = $applicant ? $user->organization_id : null;
        $reviews = $user->can('viewQueue', Permit::class);
        $cashier = $user->can('viewQueue', Payment::class);
        $seesReports = $user->can(Permission::ViewReports->value);
        $officer = $user->can('scan', Permit::class);
        $staff = $user->isStaff();

        $today = ValidityRules::today();
        $expiringUntil = $today->addDays(self::EXPIRING_DAYS)->toDateString();

        // Permits this person may count: their organization's, or all of them for staff.
        $permits = fn (): Builder => Permit::query()
            ->when($applicant, fn (Builder $query) => $query->where('organization_id', $organizationId ?? 0));

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
                ? Permit::query()->whereIn('status', [PermitStatus::Submitted, PermitStatus::UnderReview])->count()
                : null,
            'payments_to_verify' => $cashier
                ? Payment::query()->where('status', PaymentStatus::Submitted)->count()
                : null,
            'expiring' => ($applicant && $organizationId !== null) || $staff
                ? $permits()->where('status', PermitStatus::Issued)->whereBetween('valid_to', [$today->toDateString(), $expiringUntil])->count()
                : null,
            'scans_today' => $officer
                ? VerificationScan::query()->where('scanned_at', '>=', now((string) config('localization.display_timezone'))->startOfDay()->utc())->count()
                : null,
        ];

        $month = EthiopianMonth::current();

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
            'counts' => array_filter($counts, fn (?int $count): bool => $count !== null),
            'month' => $seesReports ? [
                'label' => $month->label(app()->getLocale()),
                'issued' => $reports->issuedIn($month),
                'revenue' => Money::present($reports->revenueIn($month)),
            ] : null,
            'distribution' => ($applicant && $organizationId !== null) || $reviews || $seesReports
                ? $reports->byStatus($applicant ? $organizationId : null)
                : null,
            'activity' => ($applicant && $organizationId !== null) || $reviews
                ? $this->activity($user, $applicant ? $organizationId : null)
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
                    ])
                    ->all()
                : null,
        ]);
    }

    /**
     * The latest permit status changes, newest first.
     *
     * @return list<array<string, mixed>>
     */
    private function activity(User $viewer, ?int $organizationId): array
    {
        $timezone = (string) config('localization.display_timezone');
        $locale = app()->getLocale();

        return PermitStatusTransition::query()
            ->with(['permit:id,permit_number,organization_id,permit_type', 'permit.organization:id,name_am,name_en', 'actor:id,name'])
            ->when($organizationId !== null, fn (Builder $query) => $query->whereIn(
                'permit_id',
                DB::table('permits')->where('organization_id', $organizationId)->select('id'),
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

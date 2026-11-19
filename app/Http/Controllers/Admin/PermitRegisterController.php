<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Vehicles\PlateNumber;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Http\Controllers\Controller;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\EthiopianCalendar;
use App\Support\Paginated;
use App\Support\QueryFilter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The register of permits: every application and permit, newest first, with
 * a search over number, plate, organization, TIN and applicant, and filters
 * on status, type, where it was registered (online or at the office) and the
 * date it was started. Each row opens the page the viewer works on it from. Staff see the branches in
 * their scope only (User::branchScope()).
 */
class PermitRegisterController extends Controller
{
    private const int PER_PAGE = 25;

    public function __invoke(Request $request): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();

        // Those who work on permits: the review roles (and Admins), and Registrars.
        abort_unless(
            $viewer->can(Permission::ViewReviewQueue->value) || $viewer->can(Permission::RegisterApplicants->value),
            403,
        );
        $search = QueryFilter::text($request, 'search');
        $status = PermitStatus::tryFrom(QueryFilter::text($request, 'status'));
        $permitType = PermitType::tryFrom(QueryFilter::text($request, 'permit_type'));
        $from = QueryFilter::date($request, 'from');
        $to = QueryFilter::date($request, 'to');

        // What the viewer may open: the review screens show what reached the Authority;
        // a Registrar works on drafts too, from the applicant's page.
        $staffView = $viewer->can('viewQueue', Permit::class);
        $registrar = $viewer->can('registerApplicant', User::class);

        $like = fn (string $term): string => '%'.addcslashes($term, '%_\\').'%';

        $base = fn (): Builder => Permit::query()->ordinary()
            ->inBranchOf($viewer)
            ->when(! $registrar, fn (Builder $query) => $query->whereNotIn('status', PermitStatus::hiddenFromStaff()))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('permit_number', $like($search))
                ->orWhereHas('vehicles', fn (Builder $query) => $query
                    ->whereLike('plate_normalized', $like(PlateNumber::searchTerm($search))))
                ->orWhereHas('organization', fn (Builder $query) => $query
                    ->whereLike('name_am', $like($search))
                    ->orWhereLike('name_en', $like($search))
                    ->orWhereLike('tin', $like($search)))
                ->orWhereHas('applicant', fn (Builder $query) => $query
                    ->whereLike('name', $like($search))
                    ->orWhereLike('email', $like($search)))))
            ->when($permitType !== null, fn (Builder $query) => $query->where('permit_type', $permitType))
            ->when($from !== null, fn (Builder $query) => $query->where('created_at', '>=', $from->startOfDay()->utc()))
            ->when($to !== null, fn (Builder $query) => $query->where('created_at', '<=', $to->endOfDay()->utc()));

        // The seven groups people filter by, in the order of a permit's life.
        $statuses = array_keys(PermitStatus::groups());

        $permits = $base()
            ->with(['vehicles', 'organization', 'applicant:id,name'])
            ->when($status !== null, fn (Builder $query) => $query->inStatusGroup($status))
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Permit $permit): array => $this->present($permit, $staffView, $registrar));

        return Inertia::render('admin/permits/index', [
            'permits' => Paginated::present($permits),
            'filters' => [
                'search' => $search,
                'status' => $status?->value,
                'permit_type' => $permitType?->value,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'statuses' => $statuses,
            // A new permit is taken at the office: the walk-in form registers the applicant and the application.
            'createUrl' => $registrar ? route('admin.permits.create') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Permit $permit, bool $staffView, bool $registrar): array
    {
        $locale = app()->getLocale();
        $timezone = (string) config('localization.display_timezone');
        $now = CarbonImmutable::now();

        return [
            'id' => $permit->id,
            'permit_number' => $permit->permit_number,
            'permit_type' => $permit->permit_type->value,
            'status' => $permit->effectiveStatus($now)->value,
            'organization' => $permit->organization?->localizedName($locale),
            'applicant' => $permit->applicant?->name,
            'plates' => $permit->vehicles->map(fn (Vehicle $vehicle): string => $vehicle->plate_normalized)->values()->all(),
            'valid_from' => EthiopianCalendar::present($permit->valid_from, $locale),
            'valid_to' => EthiopianCalendar::present($permit->valid_to, $locale),
            'created_at' => EthiopianCalendar::present($permit->created_at?->setTimezone($timezone), $locale),
            'href' => $this->href($permit, $staffView, $registrar),
        ];
    }

    /**
     * The page the viewer opens the permit on: the review screen once it has
     * reached the Authority, otherwise (drafts, withdrawn) the Registrar's
     * page for the applicant; none when neither is open to the viewer.
     */
    private function href(Permit $permit, bool $staffView, bool $registrar): ?string
    {
        if ($staffView && $permit->status->isVisibleToStaff()) {
            return route('staff.permits.show', $permit);
        }

        if ($registrar) {
            return route('admin.permits.show', $permit);
        }

        return null;
    }
}

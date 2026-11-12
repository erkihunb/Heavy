<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\RegisterWalkInApplication;
use App\Domain\Permits\FeeCalculator;
use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Enums\PlateRegion;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegisterApplicantRequest;
use App\Http\Resources\VehicleTypeResource;
use App\Models\User;
use App\Models\VehicleType;
use App\Support\EthiopianCalendar;
use App\Support\Money;
use App\Support\Paginated;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Applicants registered at the office (walk-ins). A Registrar, or an Admin,
 * opens the account and the organization for a person who came in; the
 * person then sets their own password from an e-mailed link. The list shows
 * every applicant, so a Registrar can check whether someone is already
 * registered before opening a second account.
 */
class ApplicantController extends Controller
{
    private const int PER_PAGE = 25;

    public function index(Request $request): Response
    {
        Gate::authorize('registerApplicant', User::class);

        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $source = in_array($request->query('source'), ['office', 'online'], true) ? (string) $request->query('source') : '';

        $applicants = User::query()
            ->role(Role::Applicant->value)
            ->with(['organization:id,name_am,name_en,tin,phone', 'registeredBy:id,name'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")
                ->orWhereHas('organization', fn (Builder $query) => $query
                    ->whereLike('name_am', "%{$search}%")
                    ->orWhereLike('name_en', "%{$search}%")
                    ->orWhereLike('tin', "%{$search}%")
                    ->orWhereLike('phone', '%'.preg_replace('/\D/', '', $search).'%'))))
            ->when($source === 'office', fn (Builder $query) => $query->whereNotNull('registered_by'))
            ->when($source === 'online', fn (Builder $query) => $query->whereNull('registered_by'))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'organization' => $user->organization === null ? null : [
                    'name' => app()->getLocale() === 'en' && filled($user->organization->name_en)
                        ? $user->organization->name_en
                        : $user->organization->name_am,
                    'tin' => $user->organization->tin,
                    'phone' => $user->organization->phone,
                ],
                'registered_by' => $user->registeredBy?->name,
                'created_at' => EthiopianCalendar::present($user->created_at?->setTimezone((string) config('localization.display_timezone')), app()->getLocale()),
            ]);

        return Inertia::render('admin/applicants/index', [
            'applicants' => Paginated::present($applicants),
            'filters' => ['search' => $search, 'source' => $source],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('registerApplicant', User::class);

        return Inertia::render('admin/permits/create', [
            'locales' => config('localization.supported'),
            // The vehicle and permit parts of the one-page form.
            'vehicleTypes' => VehicleTypeResource::collection(VehicleType::cachedOrdered()->filter(fn (VehicleType $type): bool => $type->is_active)->values()),
            'plateRegions' => array_map(
                fn (PlateRegion $region): array => ['value' => $region->value, 'latin' => $region->latinCode()],
                PlateRegion::cases(),
            ),
            'limits' => [
                'max_trailers' => (int) config('permits.max_trailers'),
                'max_duration_days' => ValidityRules::maxDurationDays(),
            ],
            'receiptMaxKilobytes' => (int) config('permits.receipt_max_kilobytes'),
        ]);
    }

    /**
     * What the request on the one-page form would cost by today's schedule,
     * from the type of its vehicle; the amount on the receipt must match it.
     */
    public function feeEstimate(Request $request, FeeCalculator $fees): JsonResponse
    {
        Gate::authorize('registerApplicant', User::class);

        $validated = $request->validate([
            'permit_type' => ['required', Rule::enum(PermitType::class)],
            'vehicle_type_id' => ['required', 'integer', Rule::exists(VehicleType::class, 'id')],
            'valid_from' => ['bail', 'required', 'date_format:Y-m-d'],
            'valid_to' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
        ]);

        $from = CarbonImmutable::createFromFormat('!Y-m-d', $validated['valid_from'], 'UTC');
        $to = CarbonImmutable::createFromFormat('!Y-m-d', $validated['valid_to'], 'UTC');
        $days = (int) $from->diffInDays($to) + 1;
        $amount = $fees->amountFor(PermitType::from($validated['permit_type']), (int) $validated['vehicle_type_id'], $days, ValidityRules::today());

        return response()->json([
            'days' => $days,
            'fee' => $amount === null ? null : Money::present($amount),
        ]);
    }

    public function store(RegisterApplicantRequest $request, RegisterWalkInApplication $register): RedirectResponse
    {
        ['applicant' => $user, 'permit' => $permit] = $register->handle(
            $request->string('contact_name')->toString(),
            $request->string('contact_email')->toString(),
            $request->string('locale')->toString(),
            $request->toData(),
            $request->vehicles(),
            $request->permit(),
            $request->user(),
            $request->payment(),
        );

        $message = __('applicants.messages.registered', ['email' => $user->email]);

        // To the application when there is one, otherwise on to the vehicles and the application.
        return $permit === null
            ? to_route('admin.applicants.show', $user)->with('success', $message)
            : to_route('admin.applicants.permits.show', [$user, $permit])->with('success', $message.' '.__(
                $permit->status === PermitStatus::Submitted ? 'applicants.messages.submitted' : 'applicants.messages.draft',
            ));
    }
}

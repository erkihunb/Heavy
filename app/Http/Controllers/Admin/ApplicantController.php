<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\RegisterWalkInApplication;
use App\Domain\Permits\FeeCalculator;
use App\Domain\Permits\ValidityRules;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Enums\PlateRegion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegisterApplicantRequest;
use App\Http\Resources\VehicleTypeResource;
use App\Models\User;
use App\Models\VehicleType;
use App\Support\Money;
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
 * A new request taken at the office (Permits › Add new): the one-page form
 * registers the applicant, their organization and vehicles with the request,
 * or files the request for an organization already on record, found by the
 * lookup below. The applicant's account is a record only: nobody signs in.
 */
class ApplicantController extends Controller
{
    private const int LOOKUP_LIMIT = 8;

    /**
     * Organizations already on record that match the text (the contact's name
     * or e-mail, the organization's name, TIN or phone), for a new request of
     * theirs: the request is filed under the same record instead of a second
     * account.
     */
    public function organizations(Request $request): JsonResponse
    {
        Gate::authorize('registerApplicant', User::class);

        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';

        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $like = '%'.addcslashes($search, '%_\\').'%';
        // Phones are stored as +251…; "0911…" and "251911…" are the same number.
        $digits = (string) preg_replace(['/\D/', '/^(251|0)/'], '', $search);
        $locale = app()->getLocale();

        $applicants = User::query()
            ->permission(Permission::ApplyForPermits->value)
            ->whereNotNull('organization_id')
            ->with('organization:id,name_am,name_en,tin,phone')
            ->where(fn (Builder $query) => $query
                ->whereLike('name', $like)
                ->orWhereLike('email', $like)
                ->orWhereHas('organization', fn (Builder $query) => $query
                    ->whereLike('name_am', $like)
                    ->orWhereLike('name_en', $like)
                    ->orWhereLike('tin', $like)
                    // Phones are compared by their digits, and only when the text has some.
                    ->when(strlen($digits) >= 3, fn (Builder $query) => $query->orWhereLike('phone', "%{$digits}%"))))
            ->orderBy('name')
            ->limit(self::LOOKUP_LIMIT)
            ->get();

        return response()->json([
            'data' => $applicants->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'organization' => $locale === 'en' && filled($user->organization?->name_en)
                    ? (string) $user->organization->name_en
                    : (string) $user->organization?->name_am,
                'tin' => $user->organization?->tin,
                'phone' => $user->organization?->phone,
            ])->values()->all(),
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
        $existing = $request->existingApplicant();

        ['permit' => $permit] = $existing !== null
            ? $register->forExisting($existing, $request->vehicles(), $request->permitData(), $request->user())
            : $register->handle(
                $request->string('contact_name')->toString(),
                $request->string('contact_email')->toString(),
                $request->string('locale')->toString(),
                $request->toData(),
                $request->vehicles(),
                $request->permit(),
                $request->user(),
            );

        $message = $existing !== null ? '' : __('applicants.messages.registered').' ';

        // To the request when there is one; a person registered without a request is
        // found with the lookup when their request comes.
        return $permit === null
            ? to_route('admin.permits.create')->with('success', trim($message))
            : to_route('admin.permits.show', $permit)->with('success', $message.__(
                $permit->status === PermitStatus::Submitted ? 'applicants.messages.submitted' : 'applicants.messages.draft',
            ));
    }
}

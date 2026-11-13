<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Permits\CreateDraftPermit;
use App\Actions\Permits\SubmitPayment;
use App\Actions\Permits\SubmitPermit;
use App\Actions\Permits\UpdateDraftPermit;
use App\Actions\Vehicles\RegisterVehicle;
use App\Domain\Permits\FeeCalculator;
use App\Domain\Permits\ValidityRules;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\PermitType;
use App\Enums\PlateRegion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Registrar\FeeEstimateForApplicantRequest;
use App\Http\Requests\Registrar\StorePaymentForApplicantRequest;
use App\Http\Requests\Registrar\StorePermitForApplicantRequest;
use App\Http\Requests\Registrar\StoreVehicleForApplicantRequest;
use App\Http\Requests\Registrar\UpdatePermitForApplicantRequest;
use App\Http\Resources\PermitResource;
use App\Http\Resources\VehicleResource;
use App\Http\Resources\VehicleTypeResource;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The whole application for an applicant who came to the office (walk-ins):
 * a Registrar registers their vehicles, fills in and submits the permit
 * application, and hands in the payment receipt — the same steps, rules
 * and checks as when the applicant does it online. The application belongs
 * to the applicant; its history names the Registrar who acted.
 */
class WalkInController extends Controller
{
    public function show(Request $request, User $applicant): Response
    {
        $organization = $this->organization($applicant);

        return Inertia::render('admin/applicants/show', [
            'applicant' => [
                'id' => $applicant->id,
                'name' => $applicant->name,
                'email' => $applicant->email,
                'registered_by' => $applicant->registeredBy?->name,
            ],
            'organization' => [
                'name_am' => $organization->name_am,
                'name_en' => $organization->name_en,
                'tin' => $organization->tin,
                'phone' => $organization->phone,
                'email' => $organization->email,
                'address' => $organization->address,
            ],
            'vehicles' => $this->vehicles($organization),
            'permits' => PermitResource::collection(
                Permit::query()
                    ->whereBelongsTo($organization)
                    ->inBranchOf($request->user())
                    ->with(['vehicles.vehicleType', 'organization', 'branch'])
                    ->latest()
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get(),
            ),
        ]);
    }

    public function createVehicle(User $applicant): Response
    {
        $this->organization($applicant);

        return Inertia::render('admin/applicants/vehicle-create', [
            'applicant' => $this->summary($applicant),
            'vehicleTypes' => VehicleTypeResource::collection(VehicleType::cachedOrdered()->filter(fn (VehicleType $type): bool => $type->is_active)->values()),
            'plateRegions' => array_map(
                fn (PlateRegion $region): array => ['value' => $region->value, 'latin' => $region->latinCode()],
                PlateRegion::cases(),
            ),
        ]);
    }

    public function storeVehicle(StoreVehicleForApplicantRequest $request, User $applicant, RegisterVehicle $registerVehicle): RedirectResponse
    {
        $registerVehicle->handle($this->organization($applicant), $request->toData());

        return to_route('admin.applicants.show', $applicant)->with('success', __('vehicles.registered_message'));
    }

    public function createPermit(User $applicant): Response
    {
        return Inertia::render('admin/applicants/permit-create', [
            'applicant' => $this->summary($applicant),
            'vehicles' => $this->vehicles($this->organization($applicant)),
            'limits' => $this->limits(),
        ]);
    }

    public function storePermit(StorePermitForApplicantRequest $request, User $applicant, CreateDraftPermit $createDraftPermit): RedirectResponse
    {
        $permit = $createDraftPermit->handle($this->organization($applicant), $applicant, $request->toData(), actor: $request->user());

        return to_route('admin.applicants.permits.show', [$applicant, $permit])->with('success', __('permits.messages.draft_saved'));
    }

    public function showPermit(Request $request, User $applicant, Permit $permit): Response
    {
        Gate::authorize('viewFor', [$permit, $applicant]);

        $may = fn (string $ability): bool => Gate::allows($ability, [$permit, $applicant]);

        return Inertia::render('admin/applicants/permit-show', [
            'applicant' => $this->summary($applicant),
            'permit' => PermitResource::make($permit->load(['vehicles.vehicleType', 'organization', 'branch', 'transitions', 'payments'])),
            'actions' => [
                'update' => $may('updateFor'),
                'submit' => $may('submitFor'),
                'submitPayment' => $may('submitPaymentFor'),
            ],
        ]);
    }

    /**
     * The receipt for an approved application, on a page of its own: the
     * registered application is not opened for editing to pay for it.
     */
    public function paymentPermit(User $applicant, Permit $permit): Response
    {
        Gate::authorize('submitPaymentFor', [$permit, $applicant]);

        return Inertia::render('admin/applicants/permit-payment', [
            'applicant' => $this->summary($applicant),
            'permit' => PermitResource::make($permit->load(['vehicles.vehicleType', 'organization', 'branch', 'payments'])),
            'paymentMethods' => array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::selectable()),
            'receiptMaxKilobytes' => (int) config('permits.receipt_max_kilobytes'),
        ]);
    }

    public function editPermit(User $applicant, Permit $permit): Response
    {
        Gate::authorize('updateFor', [$permit, $applicant]);

        return Inertia::render('admin/applicants/permit-edit', [
            'applicant' => $this->summary($applicant),
            'permit' => PermitResource::make($permit->load(['vehicles.vehicleType', 'organization', 'branch', 'transitions'])),
            'vehicles' => $this->vehicles($this->organization($applicant)),
            'limits' => $this->limits(),
        ]);
    }

    public function updatePermit(UpdatePermitForApplicantRequest $request, User $applicant, Permit $permit, UpdateDraftPermit $updateDraftPermit): RedirectResponse
    {
        $updateDraftPermit->handle($permit, $request->toData(), keepBranch: true);

        return to_route('admin.applicants.permits.show', [$applicant, $permit])->with('success', __('permits.messages.draft_saved'));
    }

    public function submitPermit(Request $request, User $applicant, Permit $permit, SubmitPermit $submitPermit): RedirectResponse
    {
        Gate::authorize('submitFor', [$permit, $applicant]);

        $submitPermit->handle($permit, $request->user());

        return to_route('admin.applicants.permits.show', [$applicant, $permit])->with('success', __('permits.messages.submitted'));
    }

    public function storePayment(StorePaymentForApplicantRequest $request, User $applicant, Permit $permit, SubmitPayment $submitPayment): RedirectResponse
    {
        $submitPayment->handle($permit, $request->user(), $request->toData());

        return to_route('admin.applicants.permits.show', [$applicant, $permit])->with('success', __('payments.messages.submitted'));
    }

    public function feeEstimate(FeeEstimateForApplicantRequest $request, User $applicant, FeeCalculator $fees): JsonResponse
    {
        $this->organization($applicant);

        /** @var array{permit_type: string, primary_vehicle_id: string, valid_from: string, valid_to: string} $validated */
        $validated = $request->validated();

        // What the permit would cost by today's schedule; the fee owed is fixed at approval.
        $vehicle = Vehicle::query()->findOrFail($validated['primary_vehicle_id']);
        $from = CarbonImmutable::createFromFormat('!Y-m-d', $validated['valid_from'], 'UTC');
        $to = CarbonImmutable::createFromFormat('!Y-m-d', $validated['valid_to'], 'UTC');
        $days = (int) $from->diffInDays($to) + 1;

        $amount = $fees->amountFor(PermitType::from($validated['permit_type']), $vehicle->vehicle_type_id, $days, ValidityRules::today());

        return response()->json([
            'days' => $days,
            'fee' => $amount === null ? null : Money::present($amount),
        ]);
    }

    /**
     * The applicant in the address, who must be an applicant with an
     * organization; anyone else is not found here.
     */
    private function organization(User $applicant): Organization
    {
        Gate::authorize('registerApplicant', User::class);

        $organization = $applicant->can(Permission::ApplyForPermits->value) ? $applicant->organization : null;

        abort_if($organization === null, 404);

        return $organization;
    }

    /**
     * @return array{id: int, name: string, organization: string}
     */
    private function summary(User $applicant): array
    {
        $organization = $this->organization($applicant);

        return [
            'id' => $applicant->id,
            'name' => $applicant->name,
            'organization' => app()->getLocale() === 'en' && filled($organization->name_en) ? (string) $organization->name_en : $organization->name_am,
        ];
    }

    private function vehicles(Organization $organization): AnonymousResourceCollection
    {
        return VehicleResource::collection(
            Vehicle::query()
                ->whereBelongsTo($organization)
                ->with('vehicleType')
                ->orderBy('plate_normalized')
                ->get(),
        );
    }

    /**
     * @return array{max_trailers: int, max_duration_days: int}
     */
    private function limits(): array
    {
        return [
            'max_trailers' => (int) config('permits.max_trailers'),
            'max_duration_days' => ValidityRules::maxDurationDays(),
        ];
    }
}

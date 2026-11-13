<?php

namespace App\Http\Controllers\Admin;

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
 * A request taken at the office, as the Registrar works on it under Permits:
 * open it, correct it and send it again when changes are asked for, register
 * a vehicle it needs, and hand in the receipt once it is approved — the same
 * rules and checks as everywhere. The request belongs to the applicant it was
 * filed for; its history names the Registrar who acted.
 */
class WalkInController extends Controller
{
    public function show(Request $request, Permit $permit): Response
    {
        $applicant = $this->applicant($permit);
        Gate::authorize('viewFor', [$permit, $applicant]);

        $may = fn (string $ability): bool => Gate::allows($ability, [$permit, $applicant]);

        return Inertia::render('admin/permits/show', [
            'applicant' => $this->summary($applicant),
            'permit' => PermitResource::make($permit->load(['vehicles.vehicleType', 'organization', 'branch', 'transitions', 'payments'])),
            'actions' => [
                'update' => $may('updateFor'),
                'submit' => $may('submitFor'),
                'submitPayment' => $may('submitPaymentFor'),
            ],
        ]);
    }

    public function edit(Permit $permit): Response
    {
        $applicant = $this->applicant($permit);
        Gate::authorize('updateFor', [$permit, $applicant]);

        return Inertia::render('admin/permits/edit', [
            'applicant' => $this->summary($applicant),
            'permit' => PermitResource::make($permit->load(['vehicles.vehicleType', 'organization', 'branch', 'transitions'])),
            'vehicles' => $this->vehicles($this->organization($applicant)),
            'limits' => $this->limits(),
        ]);
    }

    public function update(UpdatePermitForApplicantRequest $request, Permit $permit, UpdateDraftPermit $updateDraftPermit): RedirectResponse
    {
        $updateDraftPermit->handle($permit, $request->toData(), keepBranch: true);

        return to_route('admin.permits.show', $permit)->with('success', __('permits.messages.draft_saved'));
    }

    public function submit(Request $request, Permit $permit, SubmitPermit $submitPermit): RedirectResponse
    {
        Gate::authorize('submitFor', [$permit, $this->applicant($permit)]);

        $submitPermit->handle($permit, $request->user());

        return to_route('admin.permits.show', $permit)->with('success', __('permits.messages.submitted'));
    }

    /**
     * The receipt for an approved request, on a page of its own: the request
     * is not opened for editing to pay for it.
     */
    public function payment(Permit $permit): Response
    {
        $applicant = $this->applicant($permit);
        Gate::authorize('submitPaymentFor', [$permit, $applicant]);

        return Inertia::render('admin/permits/payment', [
            'applicant' => $this->summary($applicant),
            'permit' => PermitResource::make($permit->load(['vehicles.vehicleType', 'organization', 'branch', 'payments'])),
            'paymentMethods' => array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::selectable()),
            'receiptMaxKilobytes' => (int) config('permits.receipt_max_kilobytes'),
        ]);
    }

    public function storePayment(StorePaymentForApplicantRequest $request, Permit $permit, SubmitPayment $submitPayment): RedirectResponse
    {
        $submitPayment->handle($permit, $request->user(), $request->toData());

        return to_route('admin.permits.show', $permit)->with('success', __('payments.messages.submitted'));
    }

    /**
     * A vehicle the request needs that its organization has not registered
     * yet; back to correcting the request afterwards.
     */
    public function createVehicle(Permit $permit): Response
    {
        $applicant = $this->applicant($permit);
        Gate::authorize('updateFor', [$permit, $applicant]);

        return Inertia::render('admin/permits/vehicle-create', [
            'applicant' => $this->summary($applicant),
            'permit' => ['id' => $permit->id, 'permit_number' => $permit->permit_number],
            'vehicleTypes' => VehicleTypeResource::collection(VehicleType::cachedOrdered()->filter(fn (VehicleType $type): bool => $type->is_active)->values()),
            'plateRegions' => array_map(
                fn (PlateRegion $region): array => ['value' => $region->value, 'latin' => $region->latinCode()],
                PlateRegion::cases(),
            ),
        ]);
    }

    public function storeVehicle(StoreVehicleForApplicantRequest $request, Permit $permit, RegisterVehicle $registerVehicle): RedirectResponse
    {
        $applicant = $this->applicant($permit);
        Gate::authorize('updateFor', [$permit, $applicant]);

        $registerVehicle->handle($this->organization($applicant), $request->toData());

        return to_route('admin.permits.edit', $permit)->with('success', __('vehicles.registered_message'));
    }

    public function feeEstimate(FeeEstimateForApplicantRequest $request, Permit $permit, FeeCalculator $fees): JsonResponse
    {
        $this->organization($this->applicant($permit));

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
     * The applicant the request was filed for; a request without one is not
     * found here.
     */
    private function applicant(Permit $permit): User
    {
        $applicant = $permit->applicant;

        abort_if($applicant === null, 404);

        return $applicant;
    }

    /**
     * The applicant's organization; someone who is not an applicant with an
     * organization is not found here.
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

<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Permits\ResolveEditedVehicles;
use App\Actions\Permits\SubmitPermit;
use App\Actions\Permits\UpdateDraftPermit;
use App\Actions\Permits\WithdrawPermit;
use App\Actions\Vehicles\CorrectVehicle;
use App\Actions\Vehicles\RegisterVehicle;
use App\Domain\Permits\FeeCalculator;
use App\Domain\Permits\ValidityRules;
use App\Enums\Permission;
use App\Enums\PermitType;
use App\Enums\PlateRegion;
use App\Enums\VehicleRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Registrar\FeeRatesForApplicantRequest;
use App\Http\Requests\Registrar\StoreVehicleForApplicantRequest;
use App\Http\Requests\Registrar\UpdatePermitForApplicantRequest;
use App\Http\Requests\Registrar\UpdateVehicleForApplicantRequest;
use App\Http\Resources\FeeRateResource;
use App\Http\Resources\PermitResource;
use App\Http\Resources\VehicleTypeResource;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'delete' => $may('deleteFor'),
            ],
        ]);
    }

    public function edit(Permit $permit, FeeCalculator $fees): Response
    {
        $applicant = $this->applicant($permit);
        Gate::authorize('updateFor', [$permit, $applicant]);

        $permit->load(['vehicles.vehicleType', 'organization', 'branch', 'transitions']);

        $organization = $this->organization($applicant);
        $primary = $permit->vehicles->first(fn (Vehicle $vehicle): bool => $vehicle->roleOnPermit() === VehicleRole::Primary);
        $trailer = $permit->vehicles->first(fn (Vehicle $vehicle): bool => $vehicle->roleOnPermit() === VehicleRole::Trailer);
        $typeIds = $permit->vehicles->pluck('vehicle_type_id')->all();

        // The same form as registering a request, filled in with this one.
        return Inertia::render('admin/permits/edit', [
            'applicant' => $this->summary($applicant),
            'organization' => [
                'name' => $organization->name_am,
                'tin' => $organization->tin,
                'phone' => $applicant->phone ?? $organization->phone,
                'contact_name' => $applicant->name,
                'contact_email' => $applicant->email,
            ],
            'permit' => PermitResource::make($permit),
            'intake' => [
                'vehicle_type_id' => $primary === null ? '' : (string) $primary->vehicle_type_id,
                'type_detail' => $primary->type_detail ?? '',
                'plate' => $primary->plate_raw ?? '',
                'owner_name' => $primary->owner_name ?? '',
                'trailer_plate' => $trailer->plate_raw ?? '',
            ],
            'vehicleTypes' => VehicleTypeResource::collection(VehicleType::cachedOrdered()
                ->filter(fn (VehicleType $type): bool => $type->is_active || in_array($type->id, $typeIds, true))
                ->values()),
            'plateRegions' => array_map(
                fn (PlateRegion $region): array => ['value' => $region->value, 'latin' => $region->latinCode()],
                PlateRegion::cases(),
            ),
            'limits' => $this->limits(),
            'feeScheduleId' => $this->currentRate($permit, $fees),
            // A vehicle of a request letter shares the letter's scan.
            'hasStoredScan' => $permit->letter_file_path !== null || $permit->requestLetter?->letter_file_path !== null,
        ]);
    }

    public function update(UpdatePermitForApplicantRequest $request, Permit $permit, UpdateDraftPermit $updateDraftPermit, ResolveEditedVehicles $resolveVehicles): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $typed = $request->typedVehicles();

        // The vehicles typed on the form become vehicles of the register, and the
        // request is saved with them, together or not at all.
        DB::transaction(function () use ($request, $permit, $updateDraftPermit, $resolveVehicles, $typed, $user): void {
            $resolved = $typed === null
                ? null
                : $resolveVehicles->handle($permit, $this->organization($this->applicant($permit)), $typed, $user);

            $updateDraftPermit->handle($permit, $request->toData($resolved), keepBranch: true);
        });

        return to_route('admin.permits.show', $permit)->with('success', __('permits.messages.draft_saved'));
    }

    /**
     * Delete a draft or returned request: it becomes withdrawn and stays in the record.
     */
    public function destroy(Request $request, Permit $permit, WithdrawPermit $withdrawPermit): RedirectResponse
    {
        Gate::authorize('deleteFor', [$permit, $this->applicant($permit)]);

        $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        /** @var User $user */
        $user = $request->user();
        $withdrawPermit->handle($permit, $user, $request->string('note')->trim()->value() ?: null);

        return to_route('admin.permits.index')->with('success', __('permits.messages.deleted'));
    }

    public function submit(Request $request, Permit $permit, SubmitPermit $submitPermit): RedirectResponse
    {
        Gate::authorize('submitFor', [$permit, $this->applicant($permit)]);

        $submitPermit->handle($permit, $request->user());

        return to_route('admin.permits.show', $permit)->with('success', __('permits.messages.submitted'));
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

    /**
     * Correct a vehicle of the request: its plate, type and owner.
     */
    public function editVehicle(Permit $permit, Vehicle $vehicle): Response
    {
        $applicant = $this->applicant($permit);
        Gate::authorize('updateVehicleFor', [$permit, $applicant, $vehicle]);

        return Inertia::render('admin/permits/vehicle-edit', [
            'applicant' => $this->summary($applicant),
            'permit' => ['id' => $permit->id, 'permit_number' => $permit->permit_number],
            'vehicle' => [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate_raw,
                'vehicle_type_id' => (string) $vehicle->vehicle_type_id,
                'owner_name' => $vehicle->owner_name ?? '',
            ],
            'vehicleTypes' => VehicleTypeResource::collection(VehicleType::cachedOrdered()
                ->filter(fn (VehicleType $type): bool => $type->is_active || $type->id === $vehicle->vehicle_type_id)
                ->values()),
            'plateRegions' => array_map(
                fn (PlateRegion $region): array => ['value' => $region->value, 'latin' => $region->latinCode()],
                PlateRegion::cases(),
            ),
        ]);
    }

    public function updateVehicle(UpdateVehicleForApplicantRequest $request, Permit $permit, Vehicle $vehicle, CorrectVehicle $correctVehicle): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $correctVehicle->handle($vehicle, $request->toData(), $user);

        return to_route('admin.permits.edit', $permit)->with('success', __('vehicles.corrected_message'));
    }

    /**
     * The fee rates the request can be registered at by today's schedule,
     * for the permit type and the primary vehicle on the form; with the start
     * date, each tells the day the permit would end.
     */
    public function feeRates(FeeRatesForApplicantRequest $request, Permit $permit, FeeCalculator $fees): JsonResponse
    {
        $this->organization($this->applicant($permit));

        /** @var array{permit_type: string, primary_vehicle_id: string, valid_from?: string|null} $validated */
        $validated = $request->validated();
        $vehicle = Vehicle::query()->findOrFail($validated['primary_vehicle_id']);

        $rates = $fees->rates(PermitType::from($validated['permit_type']), $vehicle->vehicle_type_id, ValidityRules::today());

        return response()->json([
            'rates' => FeeRateResource::list(
                $rates,
                isset($validated['valid_from']) ? CarbonImmutable::createFromFormat('!Y-m-d', $validated['valid_from'], 'UTC') : null,
                $request,
            ),
        ]);
    }

    /**
     * The rate the draft is at, chosen beforehand on the form: the one offered
     * today whose days are the draft's own, if there is one.
     */
    private function currentRate(Permit $permit, FeeCalculator $fees): ?int
    {
        $primary = $permit->vehicles->first(fn (Vehicle $vehicle): bool => $vehicle->roleOnPermit() === VehicleRole::Primary);
        $days = $permit->durationDays();

        foreach ($fees->rates($permit->permit_type, $primary?->vehicle_type_id, ValidityRules::today()) as $rate) {
            if ($rate['days'] === $days) {
                return $rate['id'];
            }
        }

        return null;
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
        Gate::authorize('workOnRegisteredRequests', User::class);

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
            'organization' => $organization->localizedName(app()->getLocale()),
        ];
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

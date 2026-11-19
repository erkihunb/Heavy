<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Vehicles\CorrectVehicle;
use App\Data\VehicleData;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Domain\Vehicles\PlateNumber;
use App\Enums\PlateRegion;
use App\Exceptions\DuplicateVehiclePlate;
use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleTypeResource;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\RequestLetter;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\EthiopianCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The organizations requests are registered for, and their vehicles: listed,
 * opened, corrected and deleted, each by its own permission
 * (OrganizationPolicy). An organization is added with its first request, on
 * the office form (applicants.register).
 *
 * What a decision or a certificate was made from never changes under it: an
 * organization with any request is not deleted, and a vehicle is corrected
 * or deleted only while no request that has gone further carries it.
 */
class OrganizationController extends Controller
{
    private const int PER_PAGE = 25;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Organization::class);

        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $like = '%'.addcslashes($search, '%_\\').'%';
        $user = $request->user();

        $organizations = Organization::query()
            ->where('is_institution', false)
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name_am', $like)
                ->orWhereLike('name_en', $like)
                ->orWhereLike('tin', $like)
                ->orWhereLike('phone', $like)))
            ->withCount('vehicles')
            ->orderBy('name_am')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $permits = Permit::query()
            ->whereIn('organization_id', $organizations->pluck('id'))
            ->groupBy('organization_id')
            ->selectRaw('organization_id, count(*) as total')
            ->pluck('total', 'organization_id');

        return Inertia::render('admin/organizations/index', [
            'organizations' => JsonResource::collection($organizations->through(fn (Organization $organization): array => [
                ...$this->present($organization),
                'vehicles' => (int) $organization->vehicles_count,
                'permits' => (int) ($permits[$organization->id] ?? 0),
                'can' => [
                    'update' => $user?->can('update', $organization) ?? false,
                    'delete' => $user?->can('delete', $organization) ?? false,
                ],
            ])),
            'filters' => ['search' => $search],
            'canRegister' => $user?->can('registerApplicant', User::class) ?? false,
        ]);
    }

    public function show(Request $request, Organization $organization): Response
    {
        Gate::authorize('view', $organization);

        $locale = app()->getLocale();
        $user = $request->user();
        $mayCorrect = $user?->can('updateVehicle', $organization) ?? false;
        $mayDelete = $user?->can('deleteVehicle', $organization) ?? false;

        return Inertia::render('admin/organizations/show', [
            'organization' => $this->present($organization),
            'contacts' => $organization->users()->orderBy('name')->get(['id', 'name', 'email', 'phone'])
                ->map(fn (User $contact): array => ['id' => $contact->id, 'name' => $contact->name, 'email' => $contact->email, 'phone' => $contact->phone])
                ->all(),
            'vehicles' => $organization->vehicles()->with('vehicleType')->withCount('permits')->orderBy('plate_normalized')->get()
                ->map(fn (Vehicle $vehicle): array => [
                    'id' => $vehicle->id,
                    'plate' => $vehicle->plate_normalized,
                    'type' => $vehicle->typeName($locale),
                    'owner' => $vehicle->owner_name,
                    'permits' => (int) $vehicle->permits_count,
                    // Corrected only while no request that has gone further carries it.
                    'editUrl' => $mayCorrect && $vehicle->isOnlyOnOpenRequests() ? route('admin.organizations.vehicles.edit', [$organization, $vehicle]) : null,
                    'deleteUrl' => $mayDelete ? route('admin.organizations.vehicles.destroy', [$organization, $vehicle]) : null,
                    'deletable' => $vehicle->permits_count === 0,
                ])
                ->all(),
            'permits' => Permit::query()->where('organization_id', $organization->id)->latest()->limit(20)->get()
                ->map(fn (Permit $permit): array => [
                    'id' => $permit->id,
                    'number' => $permit->permit_number,
                    'status' => $permit->effectiveStatus(now())->value,
                    'valid_from' => EthiopianCalendar::present($permit->valid_from, $locale),
                    'valid_to' => EthiopianCalendar::present($permit->valid_to, $locale),
                ])
                ->all(),
            'can' => [
                'update' => $user?->can('update', $organization) ?? false,
                'delete' => $user?->can('delete', $organization) ?? false,
            ],
            'deletable' => $this->isUnused($organization),
        ]);
    }

    public function edit(Organization $organization): Response
    {
        Gate::authorize('update', $organization);

        return Inertia::render('admin/organizations/edit', [
            'organization' => $this->present($organization),
        ]);
    }

    public function update(Request $request, Organization $organization, AuditLog $audit): RedirectResponse
    {
        Gate::authorize('update', $organization);

        $validated = $request->validate([
            'name_am' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'tin' => ['nullable', 'digits:10', Rule::unique(Organization::class, 'tin')->ignore($organization->id)],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'name_am' => __('organizations.fields.name_am'),
            'name_en' => __('organizations.fields.name_en'),
            'tin' => __('organizations.fields.tin'),
            'phone' => __('organizations.fields.phone'),
            'email' => __('organizations.fields.email'),
            'address' => __('organizations.fields.address'),
        ]);

        DB::transaction(function () use ($organization, $validated, $request, $audit): void {
            $old = $organization->only(array_keys($validated));
            $organization->fill([...$validated, 'address' => $validated['address'] ?? '']);

            if ($organization->isDirty()) {
                $organization->save();
                $audit->record(AuditEvent::OrganizationUpdated, $organization, $request->user(), old: $old, new: $organization->only(array_keys($validated)));
            }
        });

        return to_route('admin.organizations.show', $organization)->with('success', __('organizations.messages.saved'));
    }

    /**
     * Delete an organization that has no request at all, with its vehicles
     * and the applicant accounts that only stood for it.
     */
    public function destroy(Request $request, Organization $organization, AuditLog $audit): RedirectResponse
    {
        Gate::authorize('delete', $organization);

        if (! $this->isUnused($organization)) {
            throw ValidationException::withMessages(['organization' => __('organizations.errors.in_use')]);
        }

        DB::transaction(function () use ($organization, $request, $audit): void {
            $audit->record(AuditEvent::OrganizationDeleted, $organization, $request->user(), old: $this->present($organization));

            $accounts = $organization->users()->get();
            $organization->vehicles()->delete();
            User::query()->where('organization_id', $organization->id)->update(['organization_id' => null]);
            $organization->delete();

            // The accounts existed only to stand for the organization.
            $accounts->filter(fn (User $account): bool => $account->isApplicant())->each->delete();
        });

        return to_route('admin.organizations.index')->with('success', __('organizations.messages.deleted'));
    }

    public function editVehicle(Organization $organization, Vehicle $vehicle): Response
    {
        Gate::authorize('updateVehicle', $organization);
        $this->ensureOwn($organization, $vehicle);
        abort_unless($vehicle->isOnlyOnOpenRequests(), 403, __('permits.errors.vehicle_on_other_permit'));

        return Inertia::render('admin/organizations/vehicle-edit', [
            'organization' => $this->present($organization),
            'vehicle' => [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate_raw,
                'vehicle_type_id' => (string) $vehicle->vehicle_type_id,
                'owner_name' => $vehicle->owner_name,
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

    public function updateVehicle(Request $request, Organization $organization, Vehicle $vehicle, CorrectVehicle $correctVehicle): RedirectResponse
    {
        Gate::authorize('updateVehicle', $organization);
        $this->ensureOwn($organization, $vehicle);

        if (! $vehicle->isOnlyOnOpenRequests()) {
            throw ValidationException::withMessages(['plate' => __('permits.errors.vehicle_on_other_permit')]);
        }

        $validated = $request->validate([
            'plate' => ['required', 'string', 'max:50'],
            'vehicle_type_id' => ['required', 'integer', Rule::exists(VehicleType::class, 'id')->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhere('id', $vehicle->vehicle_type_id))],
            'owner_name' => ['required', 'string', 'max:255'],
        ], attributes: [
            'plate' => __('vehicles.fields.plate'),
            'vehicle_type_id' => __('vehicles.fields.vehicle_type'),
            'owner_name' => __('vehicles.fields.owner_name'),
        ]);

        $plate = PlateNumber::tryParse($validated['plate']);

        if ($plate === null) {
            throw ValidationException::withMessages(['plate' => __('vehicles.errors.plate_invalid')]);
        }

        /** @var User $user */
        $user = $request->user();

        try {
            $correctVehicle->handle($vehicle, new VehicleData($plate, (int) $validated['vehicle_type_id'], $validated['owner_name']), $user);
        } catch (DuplicateVehiclePlate $duplicate) {
            throw ValidationException::withMessages(['plate' => $duplicate->userMessage()]);
        }

        return to_route('admin.organizations.show', $organization)->with('success', __('vehicles.corrected_message'));
    }

    public function destroyVehicle(Request $request, Organization $organization, Vehicle $vehicle, AuditLog $audit): RedirectResponse
    {
        Gate::authorize('deleteVehicle', $organization);
        $this->ensureOwn($organization, $vehicle);

        if ($vehicle->permits()->exists()) {
            throw ValidationException::withMessages(['vehicle' => __('organizations.errors.vehicle_in_use')]);
        }

        DB::transaction(function () use ($vehicle, $request, $audit): void {
            $audit->record(AuditEvent::VehicleDeleted, $vehicle, $request->user(), old: ['plate' => $vehicle->plate_normalized, 'owner_name' => $vehicle->owner_name]);
            $vehicle->delete();
        });

        return back()->with('success', __('organizations.messages.vehicle_deleted'));
    }

    private function ensureOwn(Organization $organization, Vehicle $vehicle): void
    {
        abort_unless($vehicle->organization_id === $organization->id, 404);
    }

    /**
     * No request and no request letter names the organization.
     */
    private function isUnused(Organization $organization): bool
    {
        return ! Permit::query()->where('organization_id', $organization->id)->exists()
            && ! RequestLetter::query()->where('organization_id', $organization->id)->exists();
    }

    /**
     * @return array{id: int, name_am: string, name_en: string|null, tin: string|null, phone: string, email: string, address: string}
     */
    private function present(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name_am' => $organization->name_am,
            'name_en' => $organization->name_en,
            'tin' => $organization->tin,
            'phone' => $organization->phone,
            'email' => $organization->email,
            'address' => $organization->address,
        ];
    }
}

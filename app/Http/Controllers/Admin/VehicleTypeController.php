<?php

namespace App\Http\Controllers\Admin;

use App\Actions\VehicleTypes\SaveVehicleType;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VehicleTypeRequest;
use App\Models\FeeSchedule;
use App\Models\VehicleType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vehicle types (spec §11): the list applicants choose from when they
 * register a vehicle. A type in use is retired; one no vehicle and no fee
 * line uses may be deleted (vehicle-types.delete).
 */
class VehicleTypeController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', VehicleType::class);

        $counts = VehicleType::query()->withCount('vehicles')->pluck('vehicles_count', 'id');
        $feeLines = FeeSchedule::query()->whereNotNull('vehicle_type_id')->distinct()->pluck('vehicle_type_id')->all();

        return Inertia::render('admin/vehicle-types/index', [
            'types' => VehicleType::cachedOrdered()
                ->map(fn (VehicleType $type): array => [
                    ...$this->present($type),
                    'vehicles' => (int) ($counts[$type->id] ?? 0),
                    // Only a type nothing uses may be deleted.
                    'deletable' => (int) ($counts[$type->id] ?? 0) === 0 && ! in_array($type->id, $feeLines, true),
                ])
                ->values()
                ->all(),
            'can' => [
                'create' => Gate::allows('create', VehicleType::class),
                'update' => $request->user()?->can(Permission::UpdateVehicleTypes->value) ?? false,
                'delete' => $request->user()?->can(Permission::DeleteVehicleTypes->value) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', VehicleType::class);

        return Inertia::render('admin/vehicle-types/create');
    }

    public function store(VehicleTypeRequest $request, SaveVehicleType $saveVehicleType): RedirectResponse
    {
        $saveVehicleType->handle($request->attributesToSave(), $request->user());

        return to_route('admin.vehicle-types.index')->with('success', __('admin.vehicle_types.messages.saved'));
    }

    public function edit(VehicleType $vehicleType): Response
    {
        Gate::authorize('update', $vehicleType);

        return Inertia::render('admin/vehicle-types/edit', [
            'type' => $this->present($vehicleType),
        ]);
    }

    public function update(VehicleTypeRequest $request, VehicleType $vehicleType, SaveVehicleType $saveVehicleType): RedirectResponse
    {
        $saveVehicleType->handle($request->attributesToSave(), $request->user(), $vehicleType);

        return to_route('admin.vehicle-types.index')->with('success', __('admin.vehicle_types.messages.saved'));
    }

    public function destroy(Request $request, VehicleType $vehicleType, AuditLog $audit): RedirectResponse
    {
        Gate::authorize('delete', $vehicleType);

        if ($vehicleType->vehicles()->exists() || FeeSchedule::query()->where('vehicle_type_id', $vehicleType->id)->exists()) {
            throw ValidationException::withMessages(['vehicle_type' => __('admin.vehicle_types.errors.in_use')]);
        }

        DB::transaction(function () use ($request, $vehicleType, $audit): void {
            $audit->record(AuditEvent::VehicleTypeDeleted, $vehicleType, $request->user(), old: $this->present($vehicleType));
            $vehicleType->delete();
        });

        return to_route('admin.vehicle-types.index')->with('success', __('admin.vehicle_types.messages.deleted'));
    }

    /**
     * @return array{id: int, code: string, name_am: string, name_en: string, is_active: bool, sort_order: int}
     */
    private function present(VehicleType $type): array
    {
        return [
            'id' => $type->id,
            'code' => $type->code,
            'name_am' => $type->name_am,
            'name_en' => $type->name_en,
            'is_active' => $type->is_active,
            'sort_order' => $type->sort_order,
        ];
    }
}

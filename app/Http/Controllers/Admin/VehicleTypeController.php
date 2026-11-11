<?php

namespace App\Http\Controllers\Admin;

use App\Actions\VehicleTypes\SaveVehicleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VehicleTypeRequest;
use App\Models\VehicleType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vehicle types (spec §11): the list applicants choose from when they
 * register a vehicle. Types are retired, never deleted.
 */
class VehicleTypeController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', VehicleType::class);

        $counts = VehicleType::query()->withCount('vehicles')->pluck('vehicles_count', 'id');

        return Inertia::render('admin/vehicle-types/index', [
            'types' => VehicleType::cachedOrdered()
                ->map(fn (VehicleType $type): array => [
                    ...$this->present($type),
                    'vehicles' => (int) ($counts[$type->id] ?? 0),
                ])
                ->values()
                ->all(),
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

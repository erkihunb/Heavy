<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Fees\SaveFeeSchedule;
use App\Enums\PermitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FeeScheduleRequest;
use App\Http\Resources\FeeScheduleResource;
use App\Http\Resources\VehicleTypeResource;
use App\Models\FeeSchedule;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The fee schedule (spec §4.4, §11 Admin). Lines are never deleted: a line
 * that should stop applying is given an end date, so the history of what was
 * charged stays readable.
 */
class FeeScheduleController extends Controller
{
    private const int PER_PAGE = 30;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', FeeSchedule::class);

        $permitType = PermitType::tryFrom(is_string($request->query('permit_type')) ? $request->query('permit_type') : '');

        $schedules = FeeSchedule::query()
            ->with('vehicleType')
            ->when($permitType !== null, fn (Builder $query) => $query->where('permit_type', $permitType))
            ->orderBy('permit_type')
            ->orderByRaw('vehicle_type_id IS NOT NULL, vehicle_type_id')
            ->orderBy('min_days')
            ->orderByDesc('effective_from')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/fee-schedules/index', [
            'schedules' => FeeScheduleResource::collection($schedules),
            'filters' => ['permit_type' => $permitType?->value],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', FeeSchedule::class);

        return Inertia::render('admin/fee-schedules/create', [
            'vehicleTypes' => $this->vehicleTypes(),
        ]);
    }

    public function store(FeeScheduleRequest $request, SaveFeeSchedule $saveFeeSchedule): RedirectResponse
    {
        $saveFeeSchedule->handle($request->toData());

        return to_route('admin.fee-schedules.index')->with('success', __('fees.messages.saved'));
    }

    public function edit(FeeSchedule $feeSchedule): Response
    {
        Gate::authorize('update', $feeSchedule);

        return Inertia::render('admin/fee-schedules/edit', [
            'schedule' => FeeScheduleResource::make($feeSchedule->load('vehicleType')),
            'vehicleTypes' => $this->vehicleTypes(),
        ]);
    }

    public function update(FeeScheduleRequest $request, FeeSchedule $feeSchedule, SaveFeeSchedule $saveFeeSchedule): RedirectResponse
    {
        $saveFeeSchedule->handle($request->toData(), $feeSchedule);

        return to_route('admin.fee-schedules.index')->with('success', __('fees.messages.saved'));
    }

    private function vehicleTypes(): AnonymousResourceCollection
    {
        return VehicleTypeResource::collection(VehicleType::query()->ordered()->get());
    }
}

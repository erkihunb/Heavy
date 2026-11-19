<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Fees\SaveFeeSchedule;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Enums\Permission;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The fee schedule (spec §4.4, §11 Admin). A line that should stop applying
 * is usually given an end date; one entered by mistake may be deleted
 * (fee-schedules.delete). Permits keep the fee they were charged, so deleting
 * a line changes no permit, and the audit log keeps what the line was.
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
            'can' => [
                'create' => Gate::allows('create', FeeSchedule::class),
                'update' => $request->user()?->can(Permission::UpdateFeeSchedules->value) ?? false,
                'delete' => $request->user()?->can(Permission::DeleteFeeSchedules->value) ?? false,
            ],
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
        $saveFeeSchedule->handle($request->toData(), actor: $request->user());

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
        $saveFeeSchedule->handle($request->toData(), $feeSchedule, $request->user());

        return to_route('admin.fee-schedules.index')->with('success', __('fees.messages.saved'));
    }

    public function destroy(Request $request, FeeSchedule $feeSchedule, AuditLog $audit): RedirectResponse
    {
        Gate::authorize('delete', $feeSchedule);

        DB::transaction(function () use ($request, $feeSchedule, $audit): void {
            $audit->record(AuditEvent::FeeScheduleDeleted, $feeSchedule, $request->user(), old: $feeSchedule->only([
                'permit_type', 'vehicle_type_id', 'min_days', 'max_days', 'amount_cents', 'effective_from', 'effective_to',
            ]));
            $feeSchedule->delete();
        });

        return to_route('admin.fee-schedules.index')->with('success', __('fees.messages.deleted'));
    }

    private function vehicleTypes(): AnonymousResourceCollection
    {
        return VehicleTypeResource::collection(VehicleType::cachedOrdered());
    }
}

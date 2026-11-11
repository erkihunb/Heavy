<?php

namespace App\Actions\Fees;

use App\Data\FeeScheduleData;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Models\FeeSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveFeeSchedule
{
    private const string LOCK = 'fee_schedules.save';

    /**
     * Add a line to the fee schedule, or change one. Two lines for the same
     * permit type and vehicle type may not cover the same number of days on
     * the same date, so every permit has exactly one fee.
     *
     * Changing a line never changes a permit that was already approved: the
     * fee is copied onto the permit at approval (spec §4.4).
     *
     * @throws ValidationException when the line overlaps another
     */
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(FeeScheduleData $data, ?FeeSchedule $schedule = null, ?User $actor = null): FeeSchedule
    {
        // Serialise changes to the schedule so two Admins cannot both pass the
        // overlap check. A named lock is used because LOCK TABLES would end the
        // surrounding transaction in MySQL.
        if (! DB::scalar('SELECT GET_LOCK(?, 10)', [self::LOCK])) {
            throw ValidationException::withMessages(['min_days' => __('fees.errors.busy')]);
        }

        try {
            return $this->save($data, $schedule, $actor);
        } finally {
            DB::scalar('SELECT RELEASE_LOCK(?)', [self::LOCK]);
        }
    }

    private function save(FeeScheduleData $data, ?FeeSchedule $schedule, ?User $actor): FeeSchedule
    {
        return DB::transaction(function () use ($data, $schedule, $actor): FeeSchedule {
            if ($this->overlaps($data, $schedule)) {
                throw ValidationException::withMessages([
                    'min_days' => __('fees.errors.overlap'),
                ]);
            }

            $schedule ??= new FeeSchedule;
            $old = $schedule->exists ? $this->audited($schedule) : [];

            $schedule->fill([
                'permit_type' => $data->permitType,
                'vehicle_type_id' => $data->vehicleTypeId,
                'min_days' => $data->minDays,
                'max_days' => $data->maxDays,
                'amount_cents' => $data->amountCents,
                'effective_from' => $data->effectiveFrom->toDateString(),
                'effective_to' => $data->effectiveTo?->toDateString(),
            ])->save();

            $this->audit->record(AuditEvent::FeeScheduleSaved, $schedule, $actor, old: $old, new: $this->audited($schedule->refresh()));

            return $schedule;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function audited(FeeSchedule $schedule): array
    {
        return [
            'permit_type' => $schedule->permit_type->value,
            'vehicle_type_id' => $schedule->vehicle_type_id,
            'min_days' => $schedule->min_days,
            'max_days' => $schedule->max_days,
            'amount_cents' => $schedule->amount_cents,
            'effective_from' => $schedule->effective_from->toDateString(),
            'effective_to' => $schedule->effective_to?->toDateString(),
        ];
    }

    private function overlaps(FeeScheduleData $data, ?FeeSchedule $except): bool
    {
        return FeeSchedule::query()
            ->when($except !== null, fn (Builder $query) => $query->whereKeyNot($except->id))
            ->where('permit_type', $data->permitType)
            ->where(fn (Builder $query) => $data->vehicleTypeId === null
                ? $query->whereNull('vehicle_type_id')
                : $query->where('vehicle_type_id', $data->vehicleTypeId))
            // Day bands intersect.
            ->where('min_days', '<=', $data->maxDays)
            ->where('max_days', '>=', $data->minDays)
            // Periods in force intersect; no end date means "until further notice".
            ->where(fn (Builder $query) => $data->effectiveTo === null
                ? $query
                : $query->where('effective_from', '<=', $data->effectiveTo->toDateString()))
            ->where(fn (Builder $query) => $query
                ->whereNull('effective_to')
                ->orWhere('effective_to', '>=', $data->effectiveFrom->toDateString()))
            ->exists();
    }
}

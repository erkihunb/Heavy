<?php

namespace App\Domain\Permits;

use App\Enums\PermitType;
use App\Enums\VehicleRole;
use App\Exceptions\PermitRuleViolation;
use App\Models\FeeSchedule;
use App\Models\Permit;
use App\Models\Vehicle;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

/**
 * What a permit costs (spec §4.4): the fee schedule line for its permit type,
 * the type of its primary vehicle and the number of days it covers, in force
 * on a given day. A line for the vehicle type itself wins over a line for
 * "every vehicle type"; among equals the most recent line wins.
 *
 * The schedule is small and read on every estimate, so it is cached and the
 * cache is dropped whenever a line changes (see FeeSchedule::booted()).
 */
final class FeeCalculator
{
    private const string CACHE_KEY = 'fee-schedules.v1';

    /**
     * The fee in santim, or null when no schedule line applies.
     *
     * @param  int|null  $vehicleTypeId  the type of the primary vehicle
     * @param  int  $days  days covered, counting the first and the last day
     * @param  DateTimeInterface  $on  the calendar day whose schedule applies
     */
    public function amountFor(PermitType $permitType, ?int $vehicleTypeId, int $days, DateTimeInterface $on): ?int
    {
        return $this->bestLine($permitType, $vehicleTypeId, $days, $on)['amount_cents'] ?? null;
    }

    /**
     * The rates a permit can be registered at (the office picks one, then the
     * start date): each schedule line in force on the day for the permit type
     * and the primary vehicle's type. A permit at a rate runs for the upper
     * limit of the line's band, but never longer than a permit may run
     * (ValidityRules::maxDurationDays()); a line that starts beyond that is
     * not offered. A line is offered only when it is the one the calculator
     * picks for its own number of days — so the fee charged at approval is
     * the fee of the rate chosen. Shortest first.
     *
     * @param  int|null  $vehicleTypeId  the type of the primary vehicle
     * @return list<array{id: int, min_days: int, max_days: int, days: int, amount_cents: int}>
     */
    public function rates(PermitType $permitType, ?int $vehicleTypeId, DateTimeInterface $on): array
    {
        $longest = ValidityRules::maxDurationDays();
        $rates = [];

        foreach ($this->lines() as $line) {
            if (! $this->applies($line, $permitType, $vehicleTypeId, $on) || $line['min_days'] > $longest) {
                continue;
            }

            $days = min($line['max_days'], $longest);

            if (($this->bestLine($permitType, $vehicleTypeId, $days, $on)['id'] ?? null) !== $line['id']) {
                continue;
            }

            $rates[] = [
                'id' => $line['id'],
                'min_days' => $line['min_days'],
                'max_days' => $line['max_days'],
                'days' => $days,
                'amount_cents' => $line['amount_cents'],
            ];
        }

        usort($rates, fn (array $a, array $b): int => [$a['days'], $a['id']] <=> [$b['days'], $b['id']]);

        return $rates;
    }

    /**
     * One of the rates() by its schedule line, or null when that line is not
     * offered for the permit type and vehicle type on the day.
     *
     * @return array{id: int, min_days: int, max_days: int, days: int, amount_cents: int}|null
     */
    public function rate(PermitType $permitType, ?int $vehicleTypeId, int $feeScheduleId, DateTimeInterface $on): ?array
    {
        foreach ($this->rates($permitType, $vehicleTypeId, $on) as $rate) {
            if ($rate['id'] === $feeScheduleId) {
                return $rate;
            }
        }

        return null;
    }

    /**
     * The fee of a permit as it stands, by the schedule in force on a day.
     */
    public function amountForPermit(Permit $permit, DateTimeInterface $on): ?int
    {
        /** @var Vehicle|null $primary */
        $primary = $permit->vehicles()->wherePivot('role', VehicleRole::Primary->value)->first();

        return $this->amountFor($permit->permit_type, $primary?->vehicle_type_id, $permit->durationDays(), $on);
    }

    /**
     * @throws PermitRuleViolation when the schedule has no line for the permit
     */
    public function requireAmountForPermit(Permit $permit, DateTimeInterface $on): int
    {
        return $this->amountForPermit($permit, $on)
            ?? throw new PermitRuleViolation('fee_not_configured');
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<array{id: int, permit_type: string, vehicle_type_id: int|null, min_days: int, max_days: int, amount_cents: int, effective_from: string, effective_to: string|null}>
     */
    private function lines(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => FeeSchedule::query()
            ->orderBy('id')
            ->get()
            ->map(fn (FeeSchedule $line): array => [
                'id' => $line->id,
                'permit_type' => $line->permit_type->value,
                'vehicle_type_id' => $line->vehicle_type_id,
                'min_days' => $line->min_days,
                'max_days' => $line->max_days,
                'amount_cents' => $line->amount_cents,
                'effective_from' => $line->effective_from->toDateString(),
                'effective_to' => $line->effective_to?->toDateString(),
            ])
            ->all());
    }

    /**
     * The schedule line that prices a permit of the type, vehicle type and
     * number of days on the day, or null when none applies.
     *
     * @return array{id: int, permit_type: string, vehicle_type_id: int|null, min_days: int, max_days: int, amount_cents: int, effective_from: string, effective_to: string|null}|null
     */
    private function bestLine(PermitType $permitType, ?int $vehicleTypeId, int $days, DateTimeInterface $on): ?array
    {
        $best = null;

        foreach ($this->lines() as $line) {
            if (! $this->applies($line, $permitType, $vehicleTypeId, $on)
                || $days < $line['min_days']
                || $days > $line['max_days']
            ) {
                continue;
            }

            if ($best === null || $this->rank($line) > $this->rank($best)) {
                $best = $line;
            }
        }

        return $best;
    }

    /**
     * Whether a line is for the permit type and the vehicle type (or every
     * vehicle type) and is in force on the day, whatever the number of days.
     *
     * @param  array{permit_type: string, vehicle_type_id: int|null, effective_from: string, effective_to: string|null}  $line
     */
    private function applies(array $line, PermitType $permitType, ?int $vehicleTypeId, DateTimeInterface $on): bool
    {
        $date = $on->format('Y-m-d');

        return $line['permit_type'] === $permitType->value
            && ($line['vehicle_type_id'] === null || $line['vehicle_type_id'] === $vehicleTypeId)
            && $date >= $line['effective_from']
            && ($line['effective_to'] === null || $date <= $line['effective_to']);
    }

    /**
     * Specific vehicle type first, then the latest start date, then the newest line.
     *
     * @param  array{id: int, vehicle_type_id: int|null, effective_from: string}  $line
     * @return array{int, string, int}
     */
    private function rank(array $line): array
    {
        return [$line['vehicle_type_id'] === null ? 0 : 1, $line['effective_from'], $line['id']];
    }
}

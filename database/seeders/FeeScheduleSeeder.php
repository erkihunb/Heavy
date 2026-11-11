<?php

namespace Database\Seeders;

use App\Enums\PermitType;
use App\Models\FeeSchedule;
use Illuminate\Database\Seeder;

/**
 * PLACEHOLDER FEES — NOT THE AUTHORITY'S TARIFF.
 *
 * The amounts are the three values named in the specification
 * (1,000 / 6,000 / 15,000 ETB) spread over invented duration bands, only so
 * that applications can be approved in development. The real schedule must
 * come from the Authority and be entered by an Admin (docs/DECISIONS.md,
 * open question 1). Runs only when the schedule is empty.
 */
class FeeScheduleSeeder extends Seeder
{
    public function run(): void
    {
        if (FeeSchedule::query()->exists()) {
            return;
        }

        $bands = [
            [1, 30, 100_000],
            [31, 60, 600_000],
            [61, 3650, 1_500_000],
        ];

        foreach (PermitType::cases() as $type) {
            foreach ($bands as [$min, $max, $cents]) {
                FeeSchedule::query()->create([
                    'permit_type' => $type,
                    'vehicle_type_id' => null,
                    'min_days' => $min,
                    'max_days' => $max,
                    'amount_cents' => $cents,
                    'effective_from' => '2025-09-11',
                    'effective_to' => null,
                ]);
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\VehicleType;
use Illuminate\Database\Seeder;

/**
 * PLACEHOLDER LIST. These are only the vehicle types named in the
 * specification; the full list must come from the
 * Authority (docs/DECISIONS.md) and is maintained by an Admin.
 *
 * Idempotent: a type that already exists is left untouched, so edits made by
 * an Admin survive re-seeding.
 */
class VehicleTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'cargo', 'name_am' => 'የጭነት', 'name_en' => 'Cargo', 'sort_order' => 10],
            ['code' => 'special_mobile', 'name_am' => 'ልዩ ተንቀሳቃሽ', 'name_en' => 'Special mobile equipment', 'sort_order' => 20],
        ];

        foreach ($types as $type) {
            VehicleType::firstOrCreate(['code' => $type['code']], $type);
        }
    }
}

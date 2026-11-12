<?php

namespace Database\Seeders;

use App\Models\VehicleType;
use Illuminate\Database\Seeder;

/**
 * The vehicle types of the Authority's application form. Maintained by an
 * Admin afterwards; "other" (ሌላ) asks the form for the type in words.
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
            ['code' => 'tractor', 'name_am' => 'ጎታች', 'name_en' => 'Tractor unit', 'sort_order' => 30],
            ['code' => VehicleType::TRAILER, 'name_am' => 'ተሳቢ', 'name_en' => 'Trailer', 'sort_order' => 40],
            ['code' => 'dual_purpose', 'name_am' => 'ድርብ ተግባር', 'name_en' => 'Dual purpose', 'sort_order' => 50],
            ['code' => 'mixer_truck', 'name_am' => 'ሚክሰር ትራክ', 'name_en' => 'Mixer truck', 'sort_order' => 60],
            ['code' => 'wheel_loader', 'name_am' => 'ዊል ሎደር', 'name_en' => 'Wheel loader', 'sort_order' => 70],
            ['code' => 'loader', 'name_am' => 'ሎደር', 'name_en' => 'Loader', 'sort_order' => 80],
            ['code' => 'roller', 'name_am' => 'ሮለር', 'name_en' => 'Roller', 'sort_order' => 90],
            ['code' => 'dump_truck', 'name_am' => 'ገልባጭ መኪና', 'name_en' => 'Dump truck', 'sort_order' => 100],
            ['code' => 'water_tanker', 'name_am' => 'የውሃ ቦቴ', 'name_en' => 'Water tanker', 'sort_order' => 110],
            ['code' => 'liquid_waste', 'name_am' => 'የፈሳሽ ቆሻሻ ማንሻ', 'name_en' => 'Liquid waste truck', 'sort_order' => 120],
            ['code' => VehicleType::OTHER, 'name_am' => 'ሌላ', 'name_en' => 'Other', 'sort_order' => 990],
        ];

        foreach ($types as $type) {
            VehicleType::firstOrCreate(['code' => $type['code']], $type);
        }
    }
}

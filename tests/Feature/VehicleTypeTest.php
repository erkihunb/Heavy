<?php

use App\Models\Vehicle;
use App\Models\VehicleType;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\VehicleTypeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

it('seeds the vehicle types named in the specification', function () {
    $this->seed(VehicleTypeSeeder::class);

    expect(VehicleType::orderBy('sort_order')->pluck('name_am', 'code')->all())->toBe([
        'cargo' => 'የጭነት',
        'special_mobile' => 'ልዩ ተንቀሳቃሽ',
    ]);
});

it('is idempotent and does not overwrite later edits', function () {
    $this->seed(VehicleTypeSeeder::class);
    VehicleType::where('code', 'cargo')->update(['name_en' => 'Edited by an admin', 'is_active' => false]);

    $this->seed(VehicleTypeSeeder::class);

    expect(VehicleType::count())->toBe(2)
        ->and(VehicleType::where('code', 'cargo')->sole()->name_en)->toBe('Edited by an admin')
        ->and(VehicleType::where('code', 'cargo')->sole()->is_active)->toBeFalse();
});

it('is part of the default database seed', function () {
    $this->seed(DatabaseSeeder::class);

    expect(VehicleType::count())->toBe(2);
});

it('requires a unique code', function () {
    VehicleType::factory()->create(['code' => 'cargo']);

    expect(fn () => VehicleType::factory()->create(['code' => 'cargo']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('lists only active types, in their sort order', function () {
    $second = VehicleType::factory()->create(['sort_order' => 20]);
    $first = VehicleType::factory()->create(['sort_order' => 10]);
    VehicleType::factory()->inactive()->create(['sort_order' => 5]);

    expect(VehicleType::active()->ordered()->pluck('id')->all())->toBe([$first->id, $second->id]);
});

it('names itself in the requested language, falling back to Amharic', function () {
    $type = VehicleType::factory()->make(['name_am' => 'የጭነት', 'name_en' => 'Cargo']);

    expect($type->localizedName('am'))->toBe('የጭነት')
        ->and($type->localizedName('en'))->toBe('Cargo')
        ->and($type->localizedName('fr'))->toBe('የጭነት');
});

it('cannot be deleted while vehicles use it', function () {
    $vehicle = Vehicle::factory()->create();

    expect(fn () => $vehicle->vehicleType->delete())->toThrow(QueryException::class);
});

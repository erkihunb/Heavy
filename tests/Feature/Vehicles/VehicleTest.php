<?php

use App\Actions\Vehicles\RegisterVehicle;
use App\Data\VehicleData;
use App\Domain\Vehicles\PlateNumber;
use App\Enums\PlateRegion;
use App\Enums\Role;
use App\Exceptions\DuplicateVehiclePlate;
use App\Models\Organization;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\UniqueConstraintViolationException;
use Inertia\Testing\AssertableInertia as Assert;

function vehiclePayload(array $overrides = []): array
{
    return [
        'plate' => 'ኢት-03-40134',
        'vehicle_type_id' => VehicleType::factory()->create()->id,
        'owner_name' => 'አበበ ከበደ',
        ...$overrides,
    ];
}

describe('registering', function () {
    it('shows the form with active vehicle types and plate regions', function () {
        $active = VehicleType::factory()->create(['sort_order' => 2]);
        $first = VehicleType::factory()->create(['sort_order' => 1]);
        VehicleType::factory()->inactive()->create();

        $this->actingAs(applicantWithOrganization())
            ->get(route('vehicles.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('applicant/vehicles/create')
                ->where('vehicleTypes', fn ($types) => collect($types)->pluck('id')->all() === [$first->id, $active->id])
                ->where('plateRegions.0', ['value' => 'ኢት', 'latin' => 'ET'])
                ->has('plateRegions', count(PlateRegion::cases())));
    });

    it('registers a vehicle for the organization of the applicant', function () {
        $user = applicantWithOrganization();
        $type = VehicleType::factory()->create();

        $this->actingAs($user)
            ->post(route('vehicles.store'), vehiclePayload(['vehicle_type_id' => $type->id]))
            ->assertRedirect(route('vehicles.index'))
            ->assertSessionHas('success');

        $vehicle = Vehicle::sole();

        expect($vehicle->organization_id)->toBe($user->organization_id)
            ->and($vehicle->vehicle_type_id)->toBe($type->id)
            ->and($vehicle->plate_normalized)->toBe('ኢት-03-40134')
            ->and($vehicle->region_code)->toBe('ኢት')
            ->and($vehicle->owner_name)->toBe('አበበ ከበደ')
            ->and(strlen($vehicle->id))->toBe(26);
    });

    it('normalises the plate and keeps what was typed', function () {
        $this->actingAs(applicantWithOrganization())
            ->post(route('vehicles.store'), vehiclePayload(['plate' => ' aa 3 a45301 ']))
            ->assertSessionHasNoErrors();

        $vehicle = Vehicle::sole();

        expect($vehicle->plate_normalized)->toBe('አአ-03-A45301')
            ->and($vehicle->plate_raw)->toBe('aa 3 a45301')
            ->and($vehicle->region_code)->toBe('አአ');
    });

    it('rejects invalid input', function (array $overrides, string $field) {
        $this->actingAs(applicantWithOrganization())
            ->post(route('vehicles.store'), vehiclePayload($overrides))
            ->assertSessionHasErrors($field);

        expect(Vehicle::count())->toBe(0);
    })->with([
        'missing plate' => [['plate' => ''], 'plate'],
        'malformed plate' => [['plate' => 'ኢት-03'], 'plate'],
        'unknown region' => [['plate' => 'XX-03-40134'], 'plate'],
        'two plates in one field' => [['plate' => 'ኢት-03-A33490/ ኢት-03-40134'], 'plate'],
        'plate as array' => [['plate' => ['ኢት-03-40134']], 'plate'],
        'missing vehicle type' => [['vehicle_type_id' => null], 'vehicle_type_id'],
        'unknown vehicle type' => [['vehicle_type_id' => 999999], 'vehicle_type_id'],
        'missing owner' => [['owner_name' => ''], 'owner_name'],
        'owner too long' => [['owner_name' => str_repeat('a', 256)], 'owner_name'],
    ]);

    it('explains an invalid plate in the language of the user', function () {
        $this->actingAs(applicantWithOrganization())
            ->post(route('vehicles.store'), vehiclePayload(['plate' => 'not a plate']))
            ->assertSessionHasErrors(['plate' => __('vehicles.errors.plate_invalid')]);
    });

    it('rejects a vehicle type that is no longer active', function () {
        $this->actingAs(applicantWithOrganization())
            ->post(route('vehicles.store'), vehiclePayload(['vehicle_type_id' => VehicleType::factory()->inactive()->create()->id]))
            ->assertSessionHasErrors('vehicle_type_id');
    });

    it('detects a plate already in the register of the organization, however it is typed', function () {
        $user = applicantWithOrganization();
        Vehicle::factory()->for($user->organization)->withPlate('አአ-03-A45301')->create();

        $this->actingAs($user)
            ->post(route('vehicles.store'), vehiclePayload(['plate' => 'AA 3 a45301']))
            ->assertSessionHasErrors(['plate' => __('vehicles.errors.plate_duplicate_own')]);

        expect(Vehicle::count())->toBe(1);
    });

    it('detects a plate registered by another organization without naming it', function () {
        $other = Organization::factory()->create(['name_am' => 'ዋሊያ ትራንስፖርት', 'name_en' => 'Walia Transport']);
        Vehicle::factory()->for($other)->withPlate('ኢት-03-40134')->create();

        $response = $this->actingAs(applicantWithOrganization())
            ->post(route('vehicles.store'), vehiclePayload())
            ->assertSessionHasErrors(['plate' => __('vehicles.errors.plate_duplicate_other')]);

        $message = session('errors')->first('plate');

        expect($message)->not->toContain('ዋሊያ')->not->toContain('Walia')
            ->and(Vehicle::count())->toBe(1);
    });

    it('ignores an organization or id supplied in the request', function () {
        $other = Organization::factory()->create();
        $user = applicantWithOrganization();

        $this->actingAs($user)->post(route('vehicles.store'), vehiclePayload([
            'organization_id' => $other->id,
            'id' => '01JAAAAAAAAAAAAAAAAAAAAAAA',
            'plate_normalized' => 'ኢት-03-99999',
        ]));

        $vehicle = Vehicle::sole();

        expect($vehicle->organization_id)->toBe($user->organization_id)
            ->and($vehicle->id)->not->toBe('01JAAAAAAAAAAAAAAAAAAAAAAA')
            ->and($vehicle->plate_normalized)->toBe('ኢት-03-40134');
    });

    it('sends an applicant without an organization to create one first', function () {
        $this->actingAs(userWithRole(Role::Applicant))
            ->get(route('vehicles.create'))
            ->assertRedirect(route('organization.show'));

        $this->actingAs(userWithRole(Role::Applicant))
            ->post(route('vehicles.store'), vehiclePayload())
            ->assertRedirect(route('organization.show'));

        expect(Vehicle::count())->toBe(0);
    });

    it('forbids staff', function (Role $role) {
        $this->actingAs(userWithRole($role, twoFactor: true))
            ->post(route('vehicles.store'), vehiclePayload())
            ->assertForbidden();

        expect(Vehicle::count())->toBe(0);
    })->with(fn () => Role::staff());

    it('redirects guests to the login page', function () {
        $this->get(route('vehicles.index'))->assertRedirect(route('login'));
        $this->post(route('vehicles.store'), [])->assertRedirect(route('login'));
    });
});

describe('the register action', function () {
    it('turns a duplicate that slips past validation into a domain exception', function () {
        $organization = Organization::factory()->create();
        $type = VehicleType::factory()->create();
        Vehicle::factory()->for($organization)->withPlate('ኢት-03-40134')->create();

        $data = new VehicleData(PlateNumber::parse('ኢት 03 40134'), $type->id, 'አበበ');

        expect(fn () => app(RegisterVehicle::class)->handle($organization, $data))
            ->toThrow(DuplicateVehiclePlate::class);

        expect(Vehicle::count())->toBe(1);
    });

    it('says whether the duplicate is in the same organization', function () {
        $mine = Organization::factory()->create();
        $theirs = Organization::factory()->create();
        $type = VehicleType::factory()->create();
        Vehicle::factory()->for($theirs)->withPlate('ኢት-03-40134')->create();
        Vehicle::factory()->for($mine)->withPlate('አአ-03-A45301')->create();

        $register = app(RegisterVehicle::class);

        try {
            $register->handle($mine, new VehicleData(PlateNumber::parse('ኢት-03-40134'), $type->id, 'አበበ'));
        } catch (DuplicateVehiclePlate $e) {
            expect($e->inSameOrganization)->toBeFalse();
        }

        try {
            $register->handle($mine, new VehicleData(PlateNumber::parse('አአ-03-A45301'), $type->id, 'አበበ'));
        } catch (DuplicateVehiclePlate $e) {
            expect($e->inSameOrganization)->toBeTrue();
        }

        expect(Vehicle::count())->toBe(2);
    });

    it('is backed by a unique index on the normalised plate', function () {
        Vehicle::factory()->withPlate('ኢት-03-40134')->create();

        expect(fn () => Vehicle::factory()->withPlate('ኢት-03-40134')->create())
            ->toThrow(UniqueConstraintViolationException::class);
    });
});

describe('updating', function () {
    it('shows the edit form for a vehicle of the organization', function () {
        $user = applicantWithOrganization();
        $vehicle = Vehicle::factory()->for($user->organization)->withPlate('ኢት-03-40134')->create();

        $this->actingAs($user)
            ->get(route('vehicles.edit', $vehicle))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('applicant/vehicles/edit')
                ->where('vehicle.id', $vehicle->id)
                ->where('vehicle.plate', 'ኢት-03-40134'));
    });

    it('updates the type, the owner and the plate', function () {
        $user = applicantWithOrganization();
        $vehicle = Vehicle::factory()->for($user->organization)->withPlate('ኢት-03-40134')->create();
        $type = VehicleType::factory()->create();

        $this->actingAs($user)
            ->put(route('vehicles.update', $vehicle), [
                'plate' => 'ኢት-03-40135',
                'vehicle_type_id' => $type->id,
                'owner_name' => 'ከበደ አበበ',
            ])
            ->assertRedirect(route('vehicles.index'))
            ->assertSessionHas('success');

        $vehicle->refresh();

        expect($vehicle->plate_normalized)->toBe('ኢት-03-40135')
            ->and($vehicle->plate_raw)->toBe('ኢት-03-40135')
            ->and($vehicle->vehicle_type_id)->toBe($type->id)
            ->and($vehicle->owner_name)->toBe('ከበደ አበበ')
            ->and($vehicle->organization_id)->toBe($user->organization_id);
    });

    it('lets a vehicle keep its own plate', function () {
        $user = applicantWithOrganization();
        $vehicle = Vehicle::factory()->for($user->organization)->withPlate('ኢት-03-40134')->create();

        $this->actingAs($user)
            ->put(route('vehicles.update', $vehicle), vehiclePayload(['owner_name' => 'ከበደ']))
            ->assertSessionHasNoErrors();

        expect($vehicle->refresh()->owner_name)->toBe('ከበደ');
    });

    it('rejects a plate that another vehicle already has', function () {
        $user = applicantWithOrganization();
        Vehicle::factory()->for($user->organization)->withPlate('አአ-03-A45301')->create();
        $vehicle = Vehicle::factory()->for($user->organization)->withPlate('ኢት-03-40134')->create();

        $this->actingAs($user)
            ->put(route('vehicles.update', $vehicle), vehiclePayload(['plate' => 'አአ-03-A45301']))
            ->assertSessionHasErrors(['plate' => __('vehicles.errors.plate_duplicate_own')]);

        expect($vehicle->refresh()->plate_normalized)->toBe('ኢት-03-40134');
    });

    it('keeps the type of a vehicle whose type was later deactivated', function () {
        $user = applicantWithOrganization();
        $retired = VehicleType::factory()->inactive()->create();
        $vehicle = Vehicle::factory()->for($user->organization)->for($retired, 'vehicleType')->withPlate('ኢት-03-40134')->create();

        $this->actingAs($user)
            ->put(route('vehicles.update', $vehicle), vehiclePayload(['vehicle_type_id' => $retired->id, 'owner_name' => 'ከበደ']))
            ->assertSessionHasNoErrors();

        expect($vehicle->refresh()->owner_name)->toBe('ከበደ');
    });
});

describe('listing', function () {
    it('lists the vehicles of the organization with whitelisted fields', function () {
        $user = applicantWithOrganization();
        $type = VehicleType::factory()->create(['code' => 'cargo', 'name_am' => 'የጭነት', 'name_en' => 'Cargo']);
        $vehicle = Vehicle::factory()->for($user->organization)->for($type, 'vehicleType')->withPlate('ኢት-03-40134')->create([
            'owner_name' => 'አበበ ከበደ',
            'created_at' => '2025-10-08 09:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('vehicles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('applicant/vehicles/index')
                ->has('vehicles.data', 1)
                ->where('vehicles.data.0', [
                    'id' => $vehicle->id,
                    'plate' => 'ኢት-03-40134',
                    'region_code' => 'ኢት',
                    'owner_name' => 'አበበ ከበደ',
                    'vehicle_type' => ['id' => $type->id, 'code' => 'cargo', 'name' => 'የጭነት'],
                    'type_detail' => null,
                    'registered_at' => [
                        'gregorian' => '2025-10-08',
                        'ethiopian' => '28/01/2018',
                        'ethiopian_label' => 'መስከረም 28, 2018',
                    ],
                ])
                ->where('vehicles.meta.total', 1));
    });

    it('names the vehicle type in the language of the user', function () {
        $user = applicantWithOrganization();
        $user->forceFill(['locale' => 'en'])->save();
        $type = VehicleType::factory()->create(['name_am' => 'የጭነት', 'name_en' => 'Cargo']);
        Vehicle::factory()->for($user->organization)->for($type, 'vehicleType')->create();

        $this->actingAs($user)
            ->get(route('vehicles.index'))
            ->assertInertia(fn (Assert $page) => $page->where('vehicles.data.0.vehicle_type.name', 'Cargo'));
    });

    it('paginates on the server, newest first', function () {
        $user = applicantWithOrganization();
        Vehicle::factory()->for($user->organization)->count(17)->create();
        $newest = Vehicle::factory()->for($user->organization)->create(['created_at' => now()->addMinute()]);

        $this->actingAs($user)
            ->get(route('vehicles.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('vehicles.data', 15)
                ->where('vehicles.data.0.id', $newest->id)
                ->where('vehicles.meta.total', 18)
                ->where('vehicles.meta.last_page', 2));

        $this->actingAs($user)
            ->get(route('vehicles.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('vehicles.data', 3));
    });

    it('filters by plate, however the search is typed', function (string $search) {
        $user = applicantWithOrganization();
        $match = Vehicle::factory()->for($user->organization)->withPlate('አአ-03-A45301')->create();
        Vehicle::factory()->for($user->organization)->withPlate('ኢት-03-40134')->create();

        $this->actingAs($user)
            ->get(route('vehicles.index', ['plate' => $search]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('vehicles.data', 1)
                ->where('vehicles.data.0.id', $match->id)
                ->where('filters.plate', $search));
    })->with(['A45301', 'a45301', '45301', 'አአ-03', 'አአ 03 a453', '03-A45']);

    it('treats wildcard characters in the search literally', function () {
        $user = applicantWithOrganization();
        Vehicle::factory()->for($user->organization)->count(3)->create();

        $this->actingAs($user)
            ->get(route('vehicles.index', ['plate' => '%']))
            ->assertInertia(fn (Assert $page) => $page->has('vehicles.data', 0));
    });

    it('filters by vehicle type', function () {
        $user = applicantWithOrganization();
        $cargo = VehicleType::factory()->create();
        $match = Vehicle::factory()->for($user->organization)->for($cargo, 'vehicleType')->create();
        Vehicle::factory()->for($user->organization)->create();

        $this->actingAs($user)
            ->get(route('vehicles.index', ['vehicle_type' => $cargo->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('vehicles.data', 1)
                ->where('vehicles.data.0.id', $match->id)
                ->where('filters.vehicle_type', $cargo->id));
    });

    it('ignores a malformed filter instead of failing', function () {
        $user = applicantWithOrganization();
        Vehicle::factory()->for($user->organization)->count(2)->create();

        $this->actingAs($user)
            ->get(route('vehicles.index', ['vehicle_type' => 'abc', 'plate' => ['x']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('vehicles.data', 2));
    });

    it('keeps the filters in the pagination links', function () {
        $user = applicantWithOrganization();
        Vehicle::factory()->for($user->organization)->count(16)->create();

        $this->actingAs($user)
            ->get(route('vehicles.index', ['plate' => '-']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehicles.links.next', fn ($url) => str_contains((string) $url, 'plate=')));
    });
});

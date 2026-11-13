<?php

use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\PermitStatusTransition;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The office's one-page form, as the paper form: the person, the
 * organization, the vehicle and trailers, and the permit — saved together.
 */

beforeEach(function () {
    Notification::fake();
    $this->registrar = staffUser(Role::Registrar);
    $this->type = VehicleType::factory()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function onePageForm(array $overrides = []): array
{
    $today = ValidityRules::today();
    $type = VehicleType::query()->value('id');

    return [
        'contact_name' => 'Abebe Kebede',
        'contact_email' => 'abebe@example.test',
        'locale' => 'am',
        'name_am' => 'አበበ የጭነት ማጓጓዣ',
        'name_en' => 'Abebe Freight',
        'tin' => '0012345678',
        'phone' => '0911223344',
        'email' => 'office@abebe.test',
        'address' => 'Bole, Addis Ababa',
        'apply' => true,
        'submit' => true,
        'confirmed' => true,
        'vehicles' => [
            ['plate' => 'ኢት-03-40134', 'vehicle_type_id' => $type, 'owner_name' => 'አበበ ከበደ'],
            ['plate' => 'ኢት-03-40135', 'vehicle_type_id' => $type, 'owner_name' => 'አበበ ከበደ'],
            ['plate' => '', 'vehicle_type_id' => '', 'owner_name' => ''],
        ],
        'permit_type' => 'full_day',
        'valid_from' => $today->addDays(3)->toDateString(),
        'valid_to' => $today->addDays(12)->toDateString(),
        'reason' => 'የግንባታ ዕቃ ማጓጓዝ',
        ...letterFields(),
        ...$overrides,
    ];
}

it('offers the vehicle types, plate regions and limits', function () {
    $this->actingAs($this->registrar)
        ->get(route('admin.permits.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/permits/create')
            ->has('vehicleTypes', 1)
            ->has('plateRegions')
            ->where('limits.max_trailers', (int) config('permits.max_trailers')));
});

it('registers the person, the organization, the vehicles and submits the permit in one step', function () {
    $response = $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), onePageForm())
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'abebe@example.test')->sole();
    $permit = Permit::query()->with('vehicles')->sole();

    $response->assertRedirect(route('admin.applicants.permits.show', [$user, $permit]));

    expect($user->organization_id)->toBe(Organization::query()->sole()->id)
        ->and(Vehicle::query()->where('organization_id', $user->organization_id)->count())->toBe(2)
        ->and($permit->applicant_id)->toBe($user->id)
        ->and($permit->status)->toBe(PermitStatus::Submitted)
        ->and($permit->vehicles->pluck('plate_normalized')->sort()->values()->all())->toHaveCount(2)
        ->and(PermitStatusTransition::query()->where('permit_id', $permit->id)->pluck('actor_id')->unique()->all())->toBe([$this->registrar->id]);

    // The account is a record only: no password link.
    Notification::assertNothingSentTo($user);
});

it('can keep the application as a draft', function () {
    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), onePageForm(['submit' => false]))
        ->assertSessionHasNoErrors();

    expect(Permit::query()->sole()->status)->toBe(PermitStatus::Draft);
});

it('registers only the person when the permit part is off', function () {
    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), onePageForm(['apply' => false, 'vehicles' => [], 'permit_type' => '']))
        ->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'abebe@example.test')->exists())->toBeTrue()
        ->and(Vehicle::query()->count())->toBe(0)
        ->and(Permit::query()->count())->toBe(0);
});

it('saves nothing when a part is wrong', function (array $overrides, string $field) {
    Vehicle::factory()->withPlate('ኢት-03-77777')->create();

    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), onePageForm($overrides))
        ->assertSessionHasErrors($field);

    expect(User::query()->where('email', 'abebe@example.test')->exists())->toBeFalse()
        ->and(Organization::query()->where('tin', '0012345678')->exists())->toBeFalse()
        ->and(Permit::query()->count())->toBe(0);
})->with([
    'no vehicle' => [['vehicles' => []], 'vehicles'],
    'malformed plate' => [['vehicles' => [['plate' => 'xyz', 'vehicle_type_id' => 1, 'owner_name' => 'A']]], 'vehicles.0.plate'],
    'plate already registered' => [['vehicles' => [['plate' => 'ኢት-03-77777', 'vehicle_type_id' => 1, 'owner_name' => 'A']]], 'vehicles.0.plate'],
    'same plate twice' => [['vehicles' => [
        ['plate' => 'ኢት-03-40134', 'vehicle_type_id' => 1, 'owner_name' => 'A'],
        ['plate' => 'ኢት-03-40134', 'vehicle_type_id' => 1, 'owner_name' => 'A'],
    ]], 'vehicles.1.plate'],
    'no owner' => [['vehicles' => [['plate' => 'ኢት-03-40134', 'vehicle_type_id' => 1, 'owner_name' => '']]], 'vehicles.0.owner_name'],
    'no permit type' => [['permit_type' => ''], 'permit_type'],
    'starts in the past' => [['valid_from' => '2020-01-01'], 'valid_from'],
    'no reason' => [['reason' => ''], 'reason'],
]);

it('accepts at most the allowed number of trailers', function () {
    $type = VehicleType::query()->value('id');
    $rows = array_map(fn (int $n): array => ['plate' => 'ኢት-03-5000'.$n, 'vehicle_type_id' => $type, 'owner_name' => 'A'], range(1, 2 + (int) config('permits.max_trailers')));

    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), onePageForm(['vehicles' => $rows]))
        ->assertSessionHasErrors('vehicles');
});

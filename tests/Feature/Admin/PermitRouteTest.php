<?php

use App\Domain\Documents\PermitDocument;
use App\Domain\Permits\VerificationTokenService;
use App\Enums\Role;
use App\Models\Permit;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The route or area an office request lets the vehicles travel (owner's
 * request, 2026-11-18): optional, entered on the one-page form and the edit
 * form, shown with the permit details, on the printed permit and to anyone
 * who checks the permit.
 */

beforeEach(function () {
    // Registering or submitting a permit needs a fee in the schedule.
    withFeeSchedule();
    $this->registrar = staffUser(Role::Registrar);
    $this->applicant = applicantWithOrganization();
});

it('saves the route from the office one-page form, trimmed', function () {
    $type = VehicleType::factory()->create();

    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), [
            'contact_name' => 'Abebe Kebede',
            'contact_email' => 'abebe@example.test',
            'locale' => 'am',
            'name_am' => 'አበበ',
            'phone' => '0911223344',
            'email' => 'office@abebe.test',
            'address' => 'Bole',
            'apply' => true,
            'submit' => false,
            'vehicles' => [['plate' => 'ኢት-03-40134', 'vehicle_type_id' => $type->id, 'owner_name' => 'አበበ']],
            'permit_type' => 'full_day',
            'valid_from' => now()->addDays(3)->toDateString(),
            'valid_to' => now()->addDays(5)->toDateString(),
            'reason' => 'ጭነት',
            'route' => '  ከመገናኛ እስከ ቃሊቲ  ',
            'any_time' => true,
            ...letterFields(),
        ])
        ->assertSessionHasNoErrors();

    expect(Permit::query()->sole()->route)->toBe('ከመገናኛ እስከ ቃሊቲ');
});

it('leaves the route empty when none is given', function (?string $route) {
    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), requestFor($this->applicant, ['route' => $route]))
        ->assertSessionHasNoErrors();

    expect(Permit::query()->sole()->route)->toBeNull();
})->with([
    'nothing' => [null],
    'blank' => ['   '],
]);

it('saves the route for an applicant on record', function () {
    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), requestFor($this->applicant, ['route' => 'Within Bole sub-city']))
        ->assertSessionHasNoErrors();

    expect(Permit::query()->sole()->route)->toBe('Within Bole sub-city');
});

it('refuses a route longer than 255 characters', function () {
    $this->actingAs($this->registrar)
        ->post(route('admin.permits.store'), requestFor($this->applicant, ['route' => str_repeat('a', 256)]))
        ->assertSessionHasErrors('route');

    expect(Permit::query()->count())->toBe(0);
});

it('edits and clears the route of a draft', function () {
    $vehicle = Vehicle::factory()->for($this->applicant->organization)->create();
    $permit = Permit::factory()->forVehicles($vehicle)->draft()->create([
        'applicant_id' => $this->applicant->id,
        'route' => 'Megenagna to Kality',
    ]);
    $payload = [...permitPayload($this->applicant, ['primary_vehicle_id' => $vehicle->id]), ...letterFields()];

    $this->actingAs($this->registrar)
        ->get(route('admin.permits.edit', $permit))
        ->assertInertia(fn (Assert $page) => $page->where('permit.route', 'Megenagna to Kality'));

    $this->actingAs($this->registrar)
        ->put(route('admin.permits.update', $permit), [...$payload, 'route' => 'Within Bole sub-city'])
        ->assertSessionHasNoErrors();

    expect($permit->refresh()->route)->toBe('Within Bole sub-city');

    $this->actingAs($this->registrar)
        ->put(route('admin.permits.update', $permit), [...$payload, 'route' => ''])
        ->assertSessionHasNoErrors();

    expect($permit->refresh()->route)->toBeNull();

    $this->actingAs($this->registrar)
        ->put(route('admin.permits.update', $permit), [...$payload, 'route' => str_repeat('a', 256)])
        ->assertSessionHasErrors('route');
});

it('shows the route with the permit details', function () {
    $vehicle = Vehicle::factory()->for($this->applicant->organization)->create();
    $permit = Permit::factory()->forVehicles($vehicle)->draft()->create([
        'applicant_id' => $this->applicant->id,
        'route' => 'Megenagna to Kality',
    ]);

    $this->actingAs($this->registrar)
        ->get(route('admin.permits.show', $permit))
        ->assertInertia(fn (Assert $page) => $page->where('permit.route', 'Megenagna to Kality'));
});

it('prints the route on an office permit, and no route line without one', function () {
    $permit = Permit::factory()->active()->create(['route' => 'Megenagna to Kality']);

    expect(PermitDocument::html($permit, 'token', 'en'))->toContain('Permitted route or area')->toContain('Megenagna to Kality')
        ->and(PermitDocument::html($permit, 'token', 'am'))->toContain('የተፈቀደለት የእንቅስቃሴ መስመር ወይም አካባቢ');

    $permit->forceFill(['route' => null])->save();

    expect(PermitDocument::html($permit->refresh(), 'token', 'en'))->not->toContain('Any route in the city');
});

it('shows the route to anyone who checks the permit', function () {
    ['token' => $token, 'hash' => $hash] = app(VerificationTokenService::class)->generate();
    Permit::factory()->active()->create(['verification_token_hash' => $hash, 'route' => 'ከመገናኛ እስከ ቃሊቲ']);

    $this->withCookie('locale', 'am')
        ->get(route('verification.token', $token))
        ->assertOk()
        ->assertSee(__('verification.fields.route', [], 'am'))
        ->assertSee('ከመገናኛ እስከ ቃሊቲ');
});

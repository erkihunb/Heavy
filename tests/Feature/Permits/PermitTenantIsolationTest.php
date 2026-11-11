<?php

use App\Enums\PermitStatus;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * An applicant only ever sees and touches the applications and vehicles of
 * their own organization, and never the back office.
 */

beforeEach(function () {
    $this->user = applicantWithOrganization();
    $this->myVehicle = Vehicle::factory()->for($this->user->organization)->withPlate('አአ-03-A45301')->create();

    $this->otherOrganization = Organization::factory()->create(['name_am' => 'ዋሊያ ትራንስፖርት', 'name_en' => 'Walia Transport']);
    $this->theirVehicle = Vehicle::factory()->for($this->otherOrganization)->withPlate('ኢት-03-40134')->create();
    $this->theirDraft = Permit::factory()->forVehicles($this->theirVehicle)->draft()->create(['reason' => 'የሌላ ተቋም ማመልከቻ']);
    $this->theirSubmitted = Permit::factory()->forVehicles($this->theirVehicle)->submitted()->create();
});

it('never lists the applications of another organization', function () {
    $mine = Permit::factory()->forVehicles($this->myVehicle)->create();

    $this->actingAs($this->user)
        ->get(route('permits.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('permits.data', 1)
            ->where('permits.data.0.id', $mine->id)
            ->where('permits.meta.total', 1));
});

it('does not find the applications of another organization by plate or status', function (array $query) {
    $this->actingAs($this->user)
        ->get(route('permits.index', $query))
        ->assertInertia(fn (Assert $page) => $page->has('permits.data', 0));
})->with([
    'their plate' => [['plate' => '40134']],
    'their status' => [['status' => 'submitted']],
    'their permit type' => [['permit_type' => 'full_day']],
]);

it('answers 404 when opening an application of another organization', function (string $route) {
    $this->actingAs($this->user)->get(route($route, $this->theirDraft))->assertNotFound();
    $this->actingAs($this->user)->get(route($route, $this->theirSubmitted))->assertNotFound();
})->with(['permits.show', 'permits.edit']);

it('answers 404 and changes nothing when updating an application of another organization', function () {
    $this->actingAs($this->user)
        ->put(route('permits.update', $this->theirDraft), permitPayload($this->user, [
            'primary_vehicle_id' => $this->myVehicle->id,
            'reason' => 'ተቀይሯል',
        ]))
        ->assertNotFound();

    expect($this->theirDraft->refresh()->reason)->toBe('የሌላ ተቋም ማመልከቻ')
        ->and($this->theirDraft->organization_id)->toBe($this->otherOrganization->id)
        ->and(permitVehicleRows($this->theirDraft)->pluck('vehicle_id')->all())->toBe([$this->theirVehicle->id]);
});

it('answers 404 before it validates anything', function () {
    $this->actingAs($this->user)
        ->put(route('permits.update', $this->theirDraft), [])
        ->assertNotFound()
        ->assertSessionHasNoErrors();
});

it('answers 404 and changes nothing when submitting an application of another organization', function () {
    $this->actingAs($this->user)->post(route('permits.submit', $this->theirDraft))->assertNotFound();

    expect($this->theirDraft->refresh()->status)->toBe(PermitStatus::Draft)
        ->and($this->theirDraft->submitted_at)->toBeNull()
        ->and($this->theirDraft->transitions()->count())->toBe(0);
});

it('answers 404 and changes nothing when withdrawing an application of another organization', function () {
    $this->actingAs($this->user)->post(route('permits.withdraw', $this->theirDraft))->assertNotFound();
    $this->actingAs($this->user)->post(route('permits.withdraw', $this->theirSubmitted))->assertNotFound();

    expect($this->theirDraft->refresh()->status)->toBe(PermitStatus::Draft)
        ->and($this->theirSubmitted->refresh()->status)->toBe(PermitStatus::Submitted);
});

it('answers 404 for an application that does not exist, exactly like one that belongs to someone else', function () {
    $missing = '01JZZZZZZZZZZZZZZZZZZZZZZZ';

    $this->actingAs($this->user)->get(route('permits.show', $missing))->assertNotFound();
    $this->actingAs($this->user)->get(route('permits.edit', $missing))->assertNotFound();
    $this->actingAs($this->user)->put(route('permits.update', $missing), [])->assertNotFound();
    $this->actingAs($this->user)->post(route('permits.submit', $missing))->assertNotFound();
    $this->actingAs($this->user)->post(route('permits.withdraw', $missing))->assertNotFound();
});

it('does not offer the vehicles of another organization on the forms', function () {
    $mine = Permit::factory()->forVehicles($this->myVehicle)->draft()->create();

    foreach ([route('permits.create'), route('permits.edit', $mine)] as $url) {
        $this->actingAs($this->user)
            ->get($url)
            ->assertInertia(fn (Assert $page) => $page
                ->has('vehicles', 1)
                ->where('vehicles.0.id', $this->myVehicle->id));
    }
});

it('cannot put a vehicle of another organization on a new application', function (string $as) {
    $payload = $as === 'primary'
        ? permitPayload($this->user, ['primary_vehicle_id' => $this->theirVehicle->id])
        : permitPayload($this->user, ['primary_vehicle_id' => $this->myVehicle->id, 'trailer_vehicle_ids' => [$this->theirVehicle->id]]);

    $field = $as === 'primary' ? 'primary_vehicle_id' : 'trailer_vehicle_ids.0';

    $this->actingAs($this->user)
        ->post(route('permits.store'), $payload)
        ->assertSessionHasErrors([$field => __('permits.errors.vehicles_not_owned')]);

    expect(Permit::query()->whereBelongsTo($this->user->organization)->count())->toBe(0)
        ->and(DB::table('permit_vehicles')->where('vehicle_id', $this->theirVehicle->id)->count())->toBe(2);
})->with(['primary', 'trailer']);

it('cannot put a vehicle of another organization on an existing application', function (string $as) {
    $mine = Permit::factory()->forVehicles($this->myVehicle)->draft()->create();

    $payload = $as === 'primary'
        ? permitPayload($this->user, ['primary_vehicle_id' => $this->theirVehicle->id])
        : permitPayload($this->user, ['primary_vehicle_id' => $this->myVehicle->id, 'trailer_vehicle_ids' => [$this->theirVehicle->id]]);

    $this->actingAs($this->user)
        ->put(route('permits.update', $mine), $payload)
        ->assertSessionHasErrors($as === 'primary' ? 'primary_vehicle_id' : 'trailer_vehicle_ids.0');

    expect(permitVehicleRows($mine)->pluck('vehicle_id')->all())->toBe([$this->myVehicle->id]);
})->with(['primary', 'trailer']);

it('does not say whether an unknown vehicle exists elsewhere', function () {
    $error = function (string $vehicleId): string {
        $this->actingAs($this->user)
            ->post(route('permits.store'), permitPayload($this->user, ['primary_vehicle_id' => $vehicleId]))
            ->assertSessionHasErrors('primary_vehicle_id');

        return session('errors')->first('primary_vehicle_id');
    };

    expect($error($this->theirVehicle->id))->toBe($error('01JZZZZZZZZZZZZZZZZZZZZZZZ'))
        ->not->toContain('ዋሊያ')
        ->not->toContain('Walia');
});

it('cannot move an application into another organization through the form', function () {
    $mine = Permit::factory()->forVehicles($this->myVehicle)->draft()->create();

    $this->actingAs($this->user)
        ->put(route('permits.update', $mine), permitPayload($this->user, [
            'primary_vehicle_id' => $this->myVehicle->id,
            'organization_id' => $this->otherOrganization->id,
        ]))
        ->assertSessionHasNoErrors();

    expect($mine->refresh()->organization_id)->toBe($this->user->organization_id);
});

it('loses access when the applicant no longer belongs to the organization', function () {
    $mine = Permit::factory()->forVehicles($this->myVehicle)->draft()->create();
    $this->user->forceFill(['organization_id' => $this->otherOrganization->id])->save();

    $this->actingAs($this->user)->get(route('permits.show', $mine))->assertNotFound();
    $this->actingAs($this->user)->post(route('permits.submit', $mine))->assertNotFound();
    // …and sees the applications of the organization they now belong to.
    $this->actingAs($this->user)->get(route('permits.show', $this->theirDraft))->assertOk();
});

describe('the back office', function () {
    it('is closed to applicants, even for their own applications', function () {
        $mine = Permit::factory()->forVehicles($this->myVehicle)->recommended()->create();

        $this->actingAs($this->user)->get(route('staff.permits.index'))->assertForbidden();
        $this->actingAs($this->user)->get(route('staff.permits.show', $mine))->assertForbidden();
        $this->actingAs($this->user)->get(route('staff.permits.show', $this->theirSubmitted))->assertForbidden();

        foreach (['start-review', 'recommend', 'request-changes', 'approve', 'reject'] as $action) {
            $this->actingAs($this->user)
                ->post(route("staff.permits.{$action}", $mine), ['recommendation' => 'approve', 'note' => 'ማስታወሻ'])
                ->assertForbidden();
        }

        expect($mine->refresh()->status)->toBe(PermitStatus::UnderReview)
            ->and($mine->permit_number)->toBeNull()
            ->and($mine->transitions()->count())->toBe(0);
    });

    it('answers JSON requests from applicants with 403 as well', function () {
        $this->actingAs($this->user)->getJson(route('staff.permits.index'))->assertForbidden();
        $this->actingAs($this->user)->postJson(route('staff.permits.approve', $this->theirSubmitted))->assertForbidden();
    });
});

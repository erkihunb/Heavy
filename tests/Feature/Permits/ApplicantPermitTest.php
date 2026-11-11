<?php

use App\Domain\Permits\ValidityRules;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Enums\Recommendation;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\PermitStatusTransition;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The applicant's side of an application over HTTP: the list, the form, and
 * handing it in or taking it back.
 */

beforeEach(function () {
    $this->user = applicantWithOrganization();
    $this->vehicle = Vehicle::factory()->for($this->user->organization)->withPlate('ኢት-03-40134')->create();
});

/**
 * A valid application form. Whole numbers given for the dates are days
 * counted from today.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function applicationForm(User $applicant, Vehicle $primary, array $overrides = []): array
{
    $today = ValidityRules::today();

    foreach (['valid_from', 'valid_to'] as $field) {
        if (is_int($overrides[$field] ?? null)) {
            $overrides[$field] = $today->addDays($overrides[$field])->toDateString();
        }
    }

    return permitPayload($applicant, ['primary_vehicle_id' => $primary->id, ...$overrides]);
}

describe('the list', function () {
    it('lists the applications of the organization, newest first', function () {
        $old = Permit::factory()->forVehicles($this->vehicle)->draft()->create(['created_at' => now()->subDays(2)]);
        $new = Permit::factory()->forVehicles($this->vehicle)->submitted()->create(['created_at' => now()->subDay()]);

        $this->actingAs($this->user)
            ->get(route('permits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('applicant/permits/index')
                ->has('permits.data', 2)
                ->where('permits.data.0.id', $new->id)
                ->where('permits.data.0.status', 'submitted')
                ->where('permits.data.0.vehicles.0.plate', 'ኢት-03-40134')
                ->where('permits.data.0.vehicles.0.role', 'primary')
                ->where('permits.data.1.id', $old->id)
                ->where('permits.meta.total', 2)
                ->where('filters', ['status' => null, 'permit_type' => null, 'plate' => ''])
                ->where('canCreate', true));
    });

    it('shows fifteen applications per page and keeps the filters in the page links', function () {
        Permit::factory()->count(16)->forVehicles($this->vehicle)->create(['permit_type' => PermitType::Pass]);

        $this->actingAs($this->user)
            ->get(route('permits.index', ['permit_type' => 'pass']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('permits.data', 15)
                ->where('permits.meta.total', 16)
                ->where('permits.meta.per_page', 15)
                ->where('permits.meta.last_page', 2)
                ->where('permits.links.next', fn (string $url) => str_contains($url, 'permit_type=pass') && str_contains($url, 'page=2')));

        $this->actingAs($this->user)
            ->get(route('permits.index', ['permit_type' => 'pass', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('permits.data', 1)
                ->where('permits.meta.current_page', 2));
    });

    describe('filters', function () {
        beforeEach(function () {
            $today = ValidityRules::today();
            $other = Vehicle::factory()->for($this->user->organization)->withPlate('አአ-03-A45301')->create();

            $this->draft = Permit::factory()->forVehicles($this->vehicle)->draft()
                ->create(['permit_type' => PermitType::FullDay]);
            $this->submitted = Permit::factory()->forVehicles($other)->submitted()
                ->create(['permit_type' => PermitType::Pass]);
            $this->active = Permit::factory()->forVehicles($this->vehicle)->active()
                ->create(['permit_type' => PermitType::FullDay]);
            $this->upcoming = Permit::factory()->forVehicles($other)->issued()
                ->validBetween($today->addDays(50), $today->addDays(60))
                ->create(['permit_type' => PermitType::Pass]);
            $this->lapsed = Permit::factory()->forVehicles($other)->issued()
                ->validBetween($today->subDays(30), $today->subDays(20))
                ->create(['permit_type' => PermitType::Pass]);

            $this->listed = fn (array $query): array => collect(
                $this->actingAs($this->user)->get(route('permits.index', $query))->assertOk()->inertiaPage()['props']['permits']['data'],
            )->pluck('id')->sort()->values()->all();

            $this->ids = fn (Permit ...$permits): array => collect($permits)->pluck('id')->sort()->values()->all();
        });

        it('filters by status as people see it today', function () {
            expect(($this->listed)(['status' => 'draft']))->toBe(($this->ids)($this->draft))
                ->and(($this->listed)(['status' => 'submitted']))->toBe(($this->ids)($this->submitted))
                ->and(($this->listed)(['status' => 'active']))->toBe(($this->ids)($this->active))
                ->and(($this->listed)(['status' => 'issued']))->toBe(($this->ids)($this->upcoming))
                ->and(($this->listed)(['status' => 'expired']))->toBe(($this->ids)($this->lapsed))
                ->and(($this->listed)(['status' => 'rejected']))->toBe([]);
        });

        it('filters by permit type', function () {
            expect(($this->listed)(['permit_type' => 'full_day']))->toBe(($this->ids)($this->draft, $this->active))
                ->and(($this->listed)(['permit_type' => 'pass']))->toBe(($this->ids)($this->submitted, $this->upcoming, $this->lapsed));
        });

        it('filters by part of a plate, however it is typed', function () {
            expect(($this->listed)(['plate' => '40134']))->toBe(($this->ids)($this->draft, $this->active))
                ->and(($this->listed)(['plate' => 'a45301']))->toBe(($this->ids)($this->submitted, $this->upcoming, $this->lapsed))
                ->and(($this->listed)(['plate' => '03 a453']))->toBe(($this->ids)($this->submitted, $this->upcoming, $this->lapsed))
                ->and(($this->listed)(['plate' => '99999']))->toBe([])
                // A wildcard is a character like any other.
                ->and(($this->listed)(['plate' => '%']))->toBe([]);
        });

        it('combines filters', function () {
            expect(($this->listed)(['permit_type' => 'pass', 'status' => 'submitted', 'plate' => 'A45301']))
                ->toBe(($this->ids)($this->submitted))
                ->and(($this->listed)(['permit_type' => 'full_day', 'status' => 'submitted']))->toBe([]);
        });

        it('ignores filter values it does not know', function () {
            $all = ($this->ids)($this->draft, $this->submitted, $this->active, $this->upcoming, $this->lapsed);

            expect(($this->listed)(['status' => 'bogus', 'permit_type' => 'bogus']))->toBe($all)
                ->and(($this->listed)(['status' => ['draft'], 'plate' => ['40134']]))->toBe($all);
        });

        it('tells the page which filters are applied', function () {
            $this->actingAs($this->user)
                ->get(route('permits.index', ['status' => 'active', 'permit_type' => 'full_day', 'plate' => ' 40134 ']))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('filters', ['status' => 'active', 'permit_type' => 'full_day', 'plate' => '40134'])
                    ->where('permits.data.0.effective_status', 'active')
                    ->where('permits.data.0.status', 'issued'));
        });
    });
});

describe('access', function () {
    it('sends guests to the login page', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create();

        $this->get(route('permits.index'))->assertRedirect(route('login'));
        $this->get(route('permits.show', $permit))->assertRedirect(route('login'));
        $this->post(route('permits.store'), [])->assertRedirect(route('login'));
        $this->post(route('permits.submit', $permit))->assertRedirect(route('login'));
    });

    it('sends an applicant without an organization to create one first', function () {
        $user = userWithRole(Role::Applicant);

        $this->actingAs($user)->get(route('permits.index'))->assertRedirect(route('organization.show'));
        $this->actingAs($user)->get(route('permits.create'))->assertRedirect(route('organization.show'));
        $this->actingAs($user)->post(route('permits.store'), [])->assertRedirect(route('organization.show'));

        expect(Permit::count())->toBe(0);
    });

    it('is closed to staff', function (Role $role) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->submitted()->create();
        $staff = staffUser($role);

        $this->actingAs($staff)->get(route('permits.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('permits.create'))->assertForbidden();
        $this->actingAs($staff)->get(route('permits.show', $permit))->assertForbidden();
        $this->actingAs($staff)->post(route('permits.store'), [])->assertForbidden();
        $this->actingAs($staff)->post(route('permits.withdraw', $permit))->assertForbidden();

        expect($permit->refresh()->status)->toBe(PermitStatus::Submitted);
    })->with(Role::staff());
});

describe('starting an application', function () {
    it('shows the form with the vehicles of the organization and the limits', function () {
        config(['permits.max_trailers' => 3, 'permits.max_duration_days' => 45]);

        $first = Vehicle::factory()->for($this->user->organization)->withPlate('አአ-03-A45301')->create();
        Vehicle::factory()->create();

        $this->actingAs($this->user)
            ->get(route('permits.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('applicant/permits/create')
                ->has('vehicles', 2)
                ->where('vehicles.0.id', $first->id)
                ->where('vehicles.1.id', $this->vehicle->id)
                ->where('limits', ['max_trailers' => 3, 'max_duration_days' => 45]));
    });

    it('saves a draft for the organization and opens it', function () {
        $trailer = Vehicle::factory()->for($this->user->organization)->create();
        $form = applicationForm($this->user, $this->vehicle, [
            'permit_type' => 'pass',
            'trailer_vehicle_ids' => [$trailer->id],
            'reason' => '  የግንባታ ዕቃ ማጓጓዝ  ',
        ]);

        $response = $this->actingAs($this->user)->post(route('permits.store'), $form);

        $permit = Permit::sole();

        $response->assertRedirect(route('permits.show', $permit))
            ->assertSessionHas('success', __('permits.messages.draft_saved'));

        expect($permit->status)->toBe(PermitStatus::Draft)
            ->and($permit->organization_id)->toBe($this->user->organization_id)
            ->and($permit->applicant_id)->toBe($this->user->id)
            ->and($permit->permit_type)->toBe(PermitType::Pass)
            ->and($permit->reason)->toBe('የግንባታ ዕቃ ማጓጓዝ')
            ->and($permit->valid_from->toDateString())->toBe($form['valid_from'])
            ->and($permit->valid_to->toDateString())->toBe($form['valid_to'])
            ->and($permit->submitted_at)->toBeNull()
            ->and($permit->permit_number)->toBeNull();

        $rows = permitVehicleRows($permit);

        expect($rows->pluck('vehicle_id')->all())->toBe([$this->vehicle->id, $trailer->id])
            ->and($rows->pluck('role')->all())->toBe(['primary', 'trailer'])
            ->and($rows->pluck('is_blocking')->all())->toBe([false, false])
            ->and($rows[0]->period_from)->toBe($form['valid_from'])
            ->and($rows[0]->period_to)->toBe($form['valid_to']);

        $history = PermitStatusTransition::sole();

        expect($history->from_status)->toBeNull()
            ->and($history->to_status)->toBe(PermitStatus::Draft)
            ->and($history->actor_id)->toBe($this->user->id);
    });

    it('accepts an application without trailers', function () {
        $form = applicationForm($this->user, $this->vehicle);
        unset($form['trailer_vehicle_ids']);

        $this->actingAs($this->user)->post(route('permits.store'), $form)->assertSessionHasNoErrors();

        expect(permitVehicleRows(Permit::sole())->pluck('role')->all())->toBe(['primary']);
    });

    it('ignores everything the applicant is not supposed to decide', function () {
        $other = Organization::factory()->create();
        $someone = User::factory()->create();

        $this->actingAs($this->user)->post(route('permits.store'), applicationForm($this->user, $this->vehicle, [
            'id' => '01JAAAAAAAAAAAAAAAAAAAAAAA',
            'status' => 'issued',
            'permit_number' => 'TMA-HV-2019-000001',
            'organization_id' => $other->id,
            'applicant_id' => $someone->id,
            'approved_by' => $someone->id,
            'fee_cents' => 0,
            'submitted_at' => now()->toDateTimeString(),
            'recommendation' => 'approve',
        ]))->assertSessionHasNoErrors();

        $permit = Permit::sole();

        expect($permit->id)->not->toBe('01JAAAAAAAAAAAAAAAAAAAAAAA')
            ->and($permit->status)->toBe(PermitStatus::Draft)
            ->and($permit->permit_number)->toBeNull()
            ->and($permit->organization_id)->toBe($this->user->organization_id)
            ->and($permit->applicant_id)->toBe($this->user->id)
            ->and($permit->approved_by)->toBeNull()
            ->and($permit->fee_cents)->toBeNull()
            ->and($permit->submitted_at)->toBeNull()
            ->and($permit->recommendation)->toBeNull();
    });

    it('rejects invalid input', function (array $overrides, string $field) {
        $this->actingAs($this->user)
            ->post(route('permits.store'), applicationForm($this->user, $this->vehicle, $overrides))
            ->assertSessionHasErrors($field);

        expect(Permit::count())->toBe(0)
            ->and(DB::table('permit_vehicles')->count())->toBe(0);
    })->with([
        'missing permit type' => [['permit_type' => ''], 'permit_type'],
        'unknown permit type' => [['permit_type' => 'weekly'], 'permit_type'],
        'missing primary vehicle' => [['primary_vehicle_id' => null], 'primary_vehicle_id'],
        'unknown primary vehicle' => [['primary_vehicle_id' => '01JZZZZZZZZZZZZZZZZZZZZZZZ'], 'primary_vehicle_id'],
        'primary vehicle as array' => [['primary_vehicle_id' => ['01JZZZZZZZZZZZZZZZZZZZZZZZ']], 'primary_vehicle_id'],
        'trailers not a list' => [['trailer_vehicle_ids' => 'none'], 'trailer_vehicle_ids'],
        'unknown trailer' => [['trailer_vehicle_ids' => ['01JZZZZZZZZZZZZZZZZZZZZZZZ']], 'trailer_vehicle_ids.0'],
        'missing first day' => [['valid_from' => ''], 'valid_from'],
        'missing last day' => [['valid_to' => ''], 'valid_to'],
        'first day not a date' => [['valid_from' => 'tomorrow'], 'valid_from'],
        'first day in another format' => [['valid_from' => '28/01/2019'], 'valid_from'],
        'last day that does not exist' => [['valid_to' => '2027-02-30'], 'valid_to'],
        'first day in the past' => [['valid_from' => -1], 'valid_from'],
        'last day before the first' => [['valid_from' => 5, 'valid_to' => 4], 'valid_to'],
        'longer than 90 days' => [['valid_from' => 1, 'valid_to' => 91], 'valid_to'],
        'missing reason' => [['reason' => ''], 'reason'],
        'blank reason' => [['reason' => '   '], 'reason'],
        'reason too long' => [['reason' => str_repeat('ሀ', 2001)], 'reason'],
        'reason as array' => [['reason' => ['ምክንያት']], 'reason'],
    ]);

    it('explains what is wrong with the period', function (array $overrides, string $field, string $message, array $replace) {
        $this->actingAs($this->user)
            ->post(route('permits.store'), applicationForm($this->user, $this->vehicle, $overrides))
            ->assertSessionHasErrors([$field => __("permits.errors.{$message}", $replace)]);
    })->with([
        'past' => [['valid_from' => -1], 'valid_from', 'valid_from_in_past', []],
        'reversed' => [['valid_from' => 5, 'valid_to' => 4], 'valid_to', 'valid_to_before_from', []],
        'too long' => [['valid_from' => 1, 'valid_to' => 91], 'valid_to', 'duration_too_long', ['max' => 90]],
    ]);

    it('accepts a period that starts today and one of exactly the maximum length', function () {
        $this->actingAs($this->user)
            ->post(route('permits.store'), applicationForm($this->user, $this->vehicle, ['valid_from' => 0, 'valid_to' => 89]))
            ->assertSessionHasNoErrors();

        expect(Permit::sole()->durationDays())->toBe(90);
    });

    it('takes the longest period from the configuration', function () {
        config(['permits.max_duration_days' => 10]);

        $this->actingAs($this->user)
            ->post(route('permits.store'), applicationForm($this->user, $this->vehicle, ['valid_from' => 1, 'valid_to' => 11]))
            ->assertSessionHasErrors(['valid_to' => __('permits.errors.duration_too_long', ['max' => 10])]);

        $this->actingAs($this->user)
            ->post(route('permits.store'), applicationForm($this->user, $this->vehicle, ['valid_from' => 1, 'valid_to' => 10]))
            ->assertSessionHasNoErrors();

        expect(Permit::sole()->durationDays())->toBe(10);
    });

    it('accepts a single-day permit', function () {
        $this->actingAs($this->user)
            ->post(route('permits.store'), applicationForm($this->user, $this->vehicle, ['valid_from' => 2, 'valid_to' => 2]))
            ->assertSessionHasNoErrors();

        expect(Permit::sole()->durationDays())->toBe(1);
    });

    it('only accepts vehicles from the register of the organization', function () {
        $theirs = Vehicle::factory()->create();

        $this->actingAs($this->user)
            ->post(route('permits.store'), applicationForm($this->user, $theirs))
            ->assertSessionHasErrors(['primary_vehicle_id' => __('permits.errors.vehicles_not_owned')]);

        $this->actingAs($this->user)
            ->post(route('permits.store'), applicationForm($this->user, $this->vehicle, ['trailer_vehicle_ids' => [$theirs->id]]))
            ->assertSessionHasErrors(['trailer_vehicle_ids.0' => __('permits.errors.vehicles_not_owned')]);

        expect(Permit::count())->toBe(0);
    });

    it('limits the number of trailers to the configured maximum', function () {
        $trailers = Vehicle::factory()->for($this->user->organization)->count(3)->create()->pluck('id')->all();

        $this->actingAs($this->user)
            ->post(route('permits.store'), applicationForm($this->user, $this->vehicle, ['trailer_vehicle_ids' => $trailers]))
            ->assertSessionHasErrors(['trailer_vehicle_ids' => __('permits.errors.too_many_trailers', ['max' => 2])]);

        expect(Permit::count())->toBe(0);

        $this->actingAs($this->user)
            ->post(route('permits.store'), applicationForm($this->user, $this->vehicle, ['trailer_vehicle_ids' => array_slice($trailers, 0, 2)]))
            ->assertSessionHasNoErrors();

        expect(permitVehicleRows(Permit::sole())->pluck('role')->all())->toBe(['primary', 'trailer', 'trailer']);

        config(['permits.max_trailers' => 0]);

        $this->actingAs($this->user)
            ->post(route('permits.store'), applicationForm($this->user, $this->vehicle, ['trailer_vehicle_ids' => [$trailers[0]]]))
            ->assertSessionHasErrors('trailer_vehicle_ids');
    });

    it('does not accept the primary vehicle as its own trailer', function () {
        $this->actingAs($this->user)
            ->post(route('permits.store'), applicationForm($this->user, $this->vehicle, ['trailer_vehicle_ids' => [$this->vehicle->id]]))
            ->assertSessionHasErrors(['trailer_vehicle_ids.0' => __('permits.errors.vehicles_duplicate')]);

        expect(Permit::count())->toBe(0);
    });

    it('does not accept the same trailer twice', function () {
        $trailer = Vehicle::factory()->for($this->user->organization)->create();

        $this->actingAs($this->user)
            ->post(route('permits.store'), applicationForm($this->user, $this->vehicle, ['trailer_vehicle_ids' => [$trailer->id, $trailer->id]]))
            ->assertSessionHasErrors(['trailer_vehicle_ids.0' => __('permits.errors.vehicles_duplicate')]);

        expect(Permit::count())->toBe(0);
    });
});

describe('viewing an application', function () {
    it('shows an application of the organization with its vehicles and history', function () {
        $this->actingAs($this->user)->post(route('permits.store'), applicationForm($this->user, $this->vehicle));
        $permit = Permit::sole();

        $this->actingAs($this->user)
            ->get(route('permits.show', $permit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('applicant/permits/show')
                ->where('permit.id', $permit->id)
                ->where('permit.status', 'draft')
                ->where('permit.effective_status', 'draft')
                ->where('permit.valid_from.gregorian', $permit->valid_from->toDateString())
                ->has('permit.vehicles', 1)
                ->where('permit.vehicles.0.plate', 'ኢት-03-40134')
                ->has('permit.transitions', 1)
                ->where('permit.transitions.0.from', null)
                ->where('permit.transitions.0.to', 'draft')
                ->where('permit.can.update', true)
                ->where('permit.can.submit', true)
                ->where('permit.can.withdraw', true)
                ->where('permit.can.approve', false));
    });

    it('shows an application in every stage to its organization', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status]);

        $this->actingAs($this->user)
            ->get(route('permits.show', $permit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('permit.status', $status->value));
    })->with(PermitStatus::stored());

    it('shows it to a colleague in the same organization', function () {
        $colleague = userWithRole(Role::Applicant);
        $colleague->forceFill(['organization_id' => $this->user->organization_id])->save();
        $permit = Permit::factory()->forVehicles($this->vehicle)->create();

        $this->actingAs($colleague)->get(route('permits.show', $permit))->assertOk();
    });

    it('never shows the recommendation or who took a decision', function () {
        $reviewer = userWithRole(Role::Reviewer);
        $permit = Permit::factory()->forVehicles($this->vehicle)
            ->recommended(Recommendation::Reject, $reviewer)
            ->create(['applicant_id' => $this->user->id]);
        $permit->transitions()->create(['from_status' => 'submitted', 'to_status' => 'under_review', 'actor_id' => $reviewer->id]);

        $this->actingAs($this->user)
            ->get(route('permits.show', $permit))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('permit.recommendation')
                ->missing('permit.applicant')
                ->missing('permit.waiting_hours')
                ->has('permit.transitions', 1)
                ->missing('permit.transitions.0.actor'));
    });

    it('answers 404 for an application that does not exist', function () {
        $this->actingAs($this->user)
            ->get(route('permits.show', '01JZZZZZZZZZZZZZZZZZZZZZZZ'))
            ->assertNotFound();
    });
});

describe('changing an application', function () {
    it('opens the form for a draft and for an application that was sent back', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status]);

        $this->actingAs($this->user)
            ->get(route('permits.edit', $permit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('applicant/permits/edit')
                ->where('permit.id', $permit->id)
                ->has('permit.vehicles', 1)
                ->has('vehicles', 1)
                ->where('vehicles.0.id', $this->vehicle->id)
                ->where('limits', ['max_trailers' => 2, 'max_duration_days' => 90]));
    })->with([PermitStatus::Draft, PermitStatus::ChangesRequested]);

    it('saves the changes without changing the stage', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status, 'permit_type' => PermitType::FullDay]);
        $other = Vehicle::factory()->for($this->user->organization)->create();

        $form = applicationForm($this->user, $other, [
            'permit_type' => 'pass',
            'trailer_vehicle_ids' => [$this->vehicle->id],
            'valid_from' => 20,
            'valid_to' => 24,
            'reason' => 'አዲስ ምክንያት',
        ]);

        $this->actingAs($this->user)
            ->put(route('permits.update', $permit), $form)
            ->assertRedirect(route('permits.show', $permit))
            ->assertSessionHas('success', __('permits.messages.draft_saved'));

        $permit->refresh();
        $rows = permitVehicleRows($permit);

        expect($permit->status)->toBe($status)
            ->and($permit->permit_type)->toBe(PermitType::Pass)
            ->and($permit->reason)->toBe('አዲስ ምክንያት')
            ->and($permit->valid_from->toDateString())->toBe($form['valid_from'])
            ->and($permit->valid_to->toDateString())->toBe($form['valid_to'])
            ->and($rows->pluck('vehicle_id')->all())->toBe([$other->id, $this->vehicle->id])
            ->and($rows->pluck('role')->all())->toBe(['primary', 'trailer'])
            ->and($rows[0]->period_from)->toBe($form['valid_from'])
            ->and($rows[0]->period_to)->toBe($form['valid_to'])
            ->and($permit->transitions()->count())->toBe(0);
    })->with([PermitStatus::Draft, PermitStatus::ChangesRequested]);

    it('answers 403 and changes nothing once the application is no longer editable', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status, 'reason' => 'የመጀመሪያ ምክንያት']);
        $other = Vehicle::factory()->for($this->user->organization)->create();

        $this->actingAs($this->user)->get(route('permits.edit', $permit))->assertForbidden();

        $this->actingAs($this->user)
            ->put(route('permits.update', $permit), applicationForm($this->user, $other, ['reason' => 'ተቀይሯል']))
            ->assertForbidden();

        expect($permit->refresh()->reason)->toBe('የመጀመሪያ ምክንያት')
            ->and(permitVehicleRows($permit)->pluck('vehicle_id')->all())->toBe([$this->vehicle->id]);
    })->with(array_values(array_filter(
        PermitStatus::stored(),
        fn (PermitStatus $status): bool => ! $status->isEditable(),
    )));

    it('validates the changes like a new application', function (array $overrides, string $field) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->draft()->create(['reason' => 'የመጀመሪያ ምክንያት']);

        if (($overrides['trailer_vehicle_ids'] ?? null) === ['same-as-primary']) {
            $overrides['trailer_vehicle_ids'] = [$this->vehicle->id];
        }

        $this->actingAs($this->user)
            ->put(route('permits.update', $permit), applicationForm($this->user, $this->vehicle, $overrides))
            ->assertSessionHasErrors($field);

        expect($permit->refresh()->reason)->toBe('የመጀመሪያ ምክንያት');
    })->with([
        'first day in the past' => [['valid_from' => -1], 'valid_from'],
        'last day before the first' => [['valid_from' => 5, 'valid_to' => 4], 'valid_to'],
        'longer than 90 days' => [['valid_from' => 1, 'valid_to' => 91], 'valid_to'],
        'missing reason' => [['reason' => ''], 'reason'],
        'unknown permit type' => [['permit_type' => 'weekly'], 'permit_type'],
        'primary vehicle as trailer' => [['trailer_vehicle_ids' => ['same-as-primary']], 'trailer_vehicle_ids.0'],
    ]);

    it('cannot change the stage, number or organization through the form', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->draft()->create();
        $other = Organization::factory()->create();

        $this->actingAs($this->user)
            ->put(route('permits.update', $permit), applicationForm($this->user, $this->vehicle, [
                'status' => 'issued',
                'permit_number' => 'TMA-HV-2019-000001',
                'organization_id' => $other->id,
            ]))
            ->assertSessionHasNoErrors();

        expect($permit->refresh()->status)->toBe(PermitStatus::Draft)
            ->and($permit->permit_number)->toBeNull()
            ->and($permit->organization_id)->toBe($this->user->organization_id);
    });
});

describe('submitting', function () {
    it('hands in a draft', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->draft()->create();

        $this->actingAs($this->user)
            ->post(route('permits.submit', $permit))
            ->assertRedirect(route('permits.show', $permit))
            ->assertSessionHas('success', __('permits.messages.submitted'));

        $history = PermitStatusTransition::sole();

        expect($permit->refresh()->status)->toBe(PermitStatus::Submitted)
            ->and($permit->submitted_at)->not->toBeNull()
            ->and($history->from_status)->toBe(PermitStatus::Draft)
            ->and($history->to_status)->toBe(PermitStatus::Submitted)
            ->and($history->actor_id)->toBe($this->user->id);
    });

    it('hands in an application again after changes were requested', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->recommended()
            ->create(['status' => PermitStatus::ChangesRequested]);

        $this->actingAs($this->user)
            ->post(route('permits.submit', $permit))
            ->assertRedirect(route('permits.show', $permit));

        expect($permit->refresh()->status)->toBe(PermitStatus::Submitted)
            ->and($permit->recommendation)->toBeNull();
    });

    it('answers 403 for an application that was already handed in or decided', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status]);

        $this->actingAs($this->user)->post(route('permits.submit', $permit))->assertForbidden();

        expect($permit->refresh()->status)->toBe($status)
            ->and($permit->transitions()->count())->toBe(0);
    })->with([
        PermitStatus::Submitted,
        PermitStatus::UnderReview,
        PermitStatus::Rejected,
        PermitStatus::AwaitingPayment,
        PermitStatus::Issued,
        PermitStatus::Withdrawn,
    ]);

    it('keeps a draft whose first day has passed and says why', function () {
        $today = ValidityRules::today();
        $permit = Permit::factory()->forVehicles($this->vehicle)->draft()
            ->validBetween($today->subDay(), $today->addDays(3))
            ->create();

        $this->actingAs($this->user)
            ->from(route('permits.show', $permit))
            ->post(route('permits.submit', $permit))
            ->assertRedirect(route('permits.show', $permit))
            ->assertSessionHas('warning', __('permits.errors.valid_from_in_past'))
            ->assertSessionMissing('success');

        expect($permit->refresh()->status)->toBe(PermitStatus::Draft)
            ->and($permit->submitted_at)->toBeNull();
    });

    it('keeps a draft whose vehicle is already reserved for those days and names the plate', function () {
        $today = ValidityRules::today();
        Permit::factory()->forVehicles($this->vehicle)->issued()
            ->validBetween($today->addDays(10), $today->addDays(20))
            ->create();
        $permit = Permit::factory()->forVehicles($this->vehicle)->draft()
            ->validBetween($today->addDays(15), $today->addDays(25))
            ->create();

        $this->actingAs($this->user)
            ->from(route('permits.show', $permit))
            ->post(route('permits.submit', $permit))
            ->assertRedirect(route('permits.show', $permit))
            ->assertSessionHas('warning', fn (string $message) => str_contains($message, 'ኢት-03-40134'));

        expect($permit->refresh()->status)->toBe(PermitStatus::Draft);
    });

    it('answers a JSON request with 422 and the reason', function () {
        $today = ValidityRules::today();
        $permit = Permit::factory()->forVehicles($this->vehicle)->draft()
            ->validBetween($today->subDay(), $today->addDays(3))
            ->create();

        $this->actingAs($this->user)
            ->postJson(route('permits.submit', $permit))
            ->assertUnprocessable()
            ->assertExactJson(['message' => __('permits.errors.valid_from_in_past')]);
    });
});

describe('withdrawing', function () {
    it('takes back a draft or an application that is still in the queue', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status]);

        $this->actingAs($this->user)
            ->post(route('permits.withdraw', $permit))
            ->assertRedirect(route('permits.show', $permit))
            ->assertSessionHas('success', __('permits.messages.withdrawn'));

        $history = PermitStatusTransition::sole();

        expect($permit->refresh()->status)->toBe(PermitStatus::Withdrawn)
            ->and($history->from_status)->toBe($status)
            ->and($history->to_status)->toBe(PermitStatus::Withdrawn)
            ->and($history->actor_id)->toBe($this->user->id);
    })->with([PermitStatus::Draft, PermitStatus::Submitted]);

    it('answers 403 once the review has started', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status]);

        $this->actingAs($this->user)->post(route('permits.withdraw', $permit))->assertForbidden();

        expect($permit->refresh()->status)->toBe($status)
            ->and($permit->transitions()->count())->toBe(0);
    })->with([
        PermitStatus::UnderReview,
        PermitStatus::ChangesRequested,
        PermitStatus::Rejected,
        PermitStatus::AwaitingPayment,
        PermitStatus::Issued,
        PermitStatus::Withdrawn,
    ]);

    it('can no longer be opened for editing or handed in afterwards', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->draft()->create();

        $this->actingAs($this->user)->post(route('permits.withdraw', $permit));

        $this->actingAs($this->user)->get(route('permits.edit', $permit))->assertForbidden();
        $this->actingAs($this->user)->post(route('permits.submit', $permit))->assertForbidden();
        $this->actingAs($this->user)->get(route('permits.show', $permit))->assertOk();
    });
});

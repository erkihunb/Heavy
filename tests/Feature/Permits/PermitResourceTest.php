<?php

use App\Enums\Recommendation;
use App\Enums\Role;
use App\Http\Resources\PermitResource;
use App\Http\Resources\PermitTransitionResource;
use App\Http\Resources\VehiclePermitHistoryResource;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * The exact fields the browser receives. A new field has to be added here on
 * purpose; what is internal to the Authority never reaches an applicant.
 */

const PERMIT_FIELDS = [
    'id',
    'permit_number',
    'status',
    'effective_status',
    'permit_type',
    'reason',
    'valid_from',
    'valid_to',
    'duration_days',
    'fee',
    'payments',
    'vehicles',
    'organization',
    'submitted_at',
    'transitions',
    'document_url',
    'document_pending',
    'can',
];

const PERMIT_STAFF_FIELDS = ['applicant', 'waiting_hours', 'recommendation'];

const PERMIT_ABILITIES = [
    'update',
    'submit',
    'withdraw',
    'submitPayment',
    'startReview',
    'recommend',
    'requestChanges',
    'approve',
    'reject',
    'revoke',
    'reissue',
];

const TRANSITION_FIELDS = ['from', 'to', 'note', 'at', 'time'];

const DATE_FIELDS = ['gregorian', 'ethiopian', 'ethiopian_label'];

/**
 * The resource as the given user's browser receives it.
 *
 * @return array<string, mixed>
 */
function resourceAs(?User $viewer, JsonResource $resource): array
{
    $request = Request::create('/');

    // Nested resources are serialised with the request the container holds.
    // Binding it resets the user resolver, so the viewer is set afterwards.
    app()->instance('request', $request);
    $request->setUserResolver(fn () => $viewer);

    return $resource->response($request)->getData(true);
}

/**
 * @param  array<string, mixed>  $data
 * @return list<string>
 */
function sortedKeys(array $data): array
{
    $keys = array_keys($data);
    sort($keys);

    return $keys;
}

/**
 * @param  list<string>  ...$lists
 * @return list<string>
 */
function sortedFields(array ...$lists): array
{
    $fields = array_merge(...$lists);
    sort($fields);

    return $fields;
}

beforeEach(function () {
    $this->applicant = applicantWithOrganization(['name_am' => 'ዋሊያ ትራንስፖርት', 'name_en' => 'Walia Transport']);
    $this->applicant->forceFill(['name' => 'አበበ ከበደ'])->save();
    $this->reviewer = userWithRole(Role::Reviewer);
    $this->reviewer->forceFill(['name' => 'ሰላም ተስፋዬ'])->save();
    $this->approver = userWithRole(Role::Approver);

    $this->vehicle = Vehicle::factory()->for($this->applicant->organization)->withPlate('ኢት-03-40134')->create();
    $trailer = Vehicle::factory()->for($this->applicant->organization)->withPlate('ኢት-03-A33490')->create();

    $this->permit = Permit::factory()
        ->forVehicles($this->vehicle, $trailer)
        ->recommended(Recommendation::Reject, $this->reviewer)
        ->validBetween('2026-12-01', '2026-12-10')
        ->create([
            'applicant_id' => $this->applicant->id,
            'reason' => 'የግንባታ ዕቃ ማጓጓዝ',
            'submitted_at' => now()->subHours(26),
            'recommendation_note' => 'መንገዱ አይፈቅድም',
        ]);

    $this->permit->transitions()->create(['from_status' => null, 'to_status' => 'draft', 'actor_id' => $this->applicant->id]);
    $this->permit->transitions()->create(['from_status' => 'draft', 'to_status' => 'submitted', 'actor_id' => $this->applicant->id]);
    $this->permit->transitions()->create([
        'from_status' => 'submitted',
        'to_status' => 'under_review',
        'actor_id' => $this->reviewer->id,
        'note' => 'ማስታወሻ',
    ]);

    // Everything either page ever loads.
    $this->loaded = fn (): Permit => Permit::query()
        ->with(['vehicles.vehicleType', 'organization', 'applicant', 'recommender', 'transitions.actor', 'payments'])
        ->findOrFail($this->permit->id);
});

describe('PermitResource', function () {
    it('gives an applicant exactly the public fields', function () {
        $data = resourceAs($this->applicant, PermitResource::make(($this->loaded)()));

        expect(sortedKeys($data))->toBe(sortedFields(PERMIT_FIELDS))
            ->and($data)->not->toHaveKeys(PERMIT_STAFF_FIELDS);
    });

    it('gives staff the internal fields as well', function (Role $role) {
        $data = resourceAs(userWithRole($role), PermitResource::make(($this->loaded)()));

        expect(sortedKeys($data))->toBe(sortedFields(PERMIT_FIELDS, PERMIT_STAFF_FIELDS))
            ->and(sortedKeys($data['recommendation']))->toBe(['at', 'by', 'decision', 'note']);
    })->with([Role::Reviewer, Role::Approver]);

    it('gives staff who are not part of the review, and guests, the public fields only', function (?Role $role) {
        $viewer = $role === null ? null : userWithRole($role);

        $data = resourceAs($viewer, PermitResource::make(($this->loaded)()));

        expect(sortedKeys($data))->toBe(sortedFields(PERMIT_FIELDS))
            ->and(array_filter($data['can']))->toBe([]);

        foreach ($data['transitions'] as $transition) {
            expect(sortedKeys($transition))->toBe(sortedFields(TRANSITION_FIELDS));
        }
    })->with([Role::Cashier, Role::TrafficOfficer, null]);

    it('presents the application itself', function () {
        $data = resourceAs($this->applicant, PermitResource::make(($this->loaded)()));

        expect($data['id'])->toBe($this->permit->id)
            ->and($data['permit_number'])->toBeNull()
            ->and($data['status'])->toBe('under_review')
            ->and($data['effective_status'])->toBe('under_review')
            ->and($data['permit_type'])->toBe('full_day')
            ->and($data['reason'])->toBe('የግንባታ ዕቃ ማጓጓዝ')
            ->and($data['duration_days'])->toBe(10)
            ->and($data['organization'])->toBe('ዋሊያ ትራንስፖርት')
            ->and(array_keys($data['valid_from']))->toBe(DATE_FIELDS)
            ->and($data['valid_from']['gregorian'])->toBe('2026-12-01')
            ->and($data['valid_from']['ethiopian'])->toBe('22/03/2019')
            ->and($data['valid_to']['gregorian'])->toBe('2026-12-10')
            ->and(array_keys($data['submitted_at']))->toBe(DATE_FIELDS);
    });

    it('presents the vehicles with exactly their public fields, the primary vehicle first', function () {
        $data = resourceAs($this->applicant, PermitResource::make(($this->loaded)()));

        expect($data['vehicles'])->toHaveCount(2)
            ->and(sortedKeys($data['vehicles'][0]))->toBe(['id', 'owner_name', 'plate', 'role', 'vehicle_type'])
            ->and($data['vehicles'][0]['id'])->toBe($this->vehicle->id)
            ->and($data['vehicles'][0]['plate'])->toBe('ኢት-03-40134')
            ->and($data['vehicles'][0]['role'])->toBe('primary')
            ->and($data['vehicles'][0]['owner_name'])->toBe($this->vehicle->owner_name)
            ->and($data['vehicles'][0]['vehicle_type']['id'])->toBe($this->vehicle->vehicle_type_id)
            ->and($data['vehicles'][1]['plate'])->toBe('ኢት-03-A33490')
            ->and($data['vehicles'][1]['role'])->toBe('trailer');
    });

    it('uses the English name of the organization for an English page', function () {
        app()->setLocale('en');

        $data = resourceAs($this->applicant, PermitResource::make(($this->loaded)()));

        expect($data['organization'])->toBe('Walia Transport');
    });

    it('leaves out what was not loaded', function () {
        $data = resourceAs($this->reviewer, PermitResource::make(Permit::findOrFail($this->permit->id)));

        expect($data)->not->toHaveKeys(['vehicles', 'organization', 'transitions', 'applicant'])
            ->and($data['recommendation'])->not->toHaveKey('by')
            ->and($data['recommendation']['decision'])->toBe('reject');
    });

    it('tells staff who applied, how long it has waited and what was recommended', function () {
        $data = resourceAs($this->approver, PermitResource::make(($this->loaded)()));

        expect($data['applicant'])->toBe('አበበ ከበደ')
            ->and($data['waiting_hours'])->toBe(26)
            ->and($data['recommendation']['decision'])->toBe('reject')
            ->and($data['recommendation']['note'])->toBe('መንገዱ አይፈቅድም')
            ->and($data['recommendation']['by'])->toBe('ሰላም ተስፋዬ')
            ->and(array_keys($data['recommendation']['at']))->toBe(DATE_FIELDS);
    });

    it('has no waiting time or recommendation where there is none', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->awaitingPayment()->create();

        $data = resourceAs($this->approver, PermitResource::make($permit));

        expect($data['waiting_hours'])->toBeNull()
            ->and($data['recommendation'])->toBeNull()
            ->and($data['permit_number'])->toBe($permit->permit_number);
    });

    it('reports an issued permit that is valid today as active without changing its status', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->active()->create();

        $data = resourceAs($this->applicant, PermitResource::make($permit));

        expect($data['status'])->toBe('issued')
            ->and($data['effective_status'])->toBe('active');
    });

    it('always lists the same abilities', function (?Role $role) {
        $viewer = match ($role) {
            null => null,
            Role::Applicant => $this->applicant,
            default => userWithRole($role),
        };

        $data = resourceAs($viewer, PermitResource::make(($this->loaded)()));

        expect(array_keys($data['can']))->toBe(PERMIT_ABILITIES);

        foreach ($data['can'] as $allowed) {
            expect($allowed)->toBeBool();
        }
    })->with([...Role::cases(), null]);

    it('offers each viewer only what they can do in the current stage', function (string $state, string $viewer, array $expected) {
        $factory = Permit::factory()->forVehicles($this->vehicle);
        $permit = match ($state) {
            'recommended' => $factory->recommended(reviewer: $this->reviewer)->create(),
            'recommended by the approver' => $factory->recommended(reviewer: $this->approver)->create(),
            default => $factory->{$state}()->create(),
        };

        $data = resourceAs($this->{$viewer}, PermitResource::make($permit));

        expect(array_keys(array_filter($data['can'])))->toBe($expected);
    })->with([
        'applicant, draft' => ['draft', 'applicant', ['update', 'submit', 'withdraw']],
        'applicant, submitted' => ['submitted', 'applicant', ['withdraw']],
        'applicant, under review' => ['underReview', 'applicant', []],
        'applicant, changes requested' => ['changesRequested', 'applicant', ['update', 'submit']],
        'applicant, awaiting payment' => ['awaitingPayment', 'applicant', ['submitPayment']],
        'applicant, payment submitted' => ['paymentSubmitted', 'applicant', []],
        'applicant, issued' => ['issued', 'applicant', []],
        'applicant, rejected' => ['rejected', 'applicant', []],
        'reviewer, submitted' => ['submitted', 'reviewer', ['startReview']],
        'reviewer, under review' => ['underReview', 'reviewer', ['recommend', 'requestChanges']],
        'reviewer, recommended' => ['recommended', 'reviewer', ['recommend', 'requestChanges']],
        'reviewer, changes requested' => ['changesRequested', 'reviewer', []],
        'reviewer, issued' => ['issued', 'reviewer', []],
        'approver, submitted' => ['submitted', 'approver', []],
        'approver, under review without a recommendation' => ['underReview', 'approver', []],
        'approver, recommended' => ['recommended', 'approver', ['approve', 'reject']],
        'approver, own recommendation' => ['recommended by the approver', 'approver', []],
        'approver, awaiting payment' => ['awaitingPayment', 'approver', []],
        'approver, issued' => ['issued', 'approver', ['revoke', 'reissue']],
        'approver, active' => ['active', 'approver', ['revoke', 'reissue']],
        'approver, revoked' => ['revoked', 'approver', []],
        'approver, expired' => ['expired', 'approver', []],
    ]);

    it('lets an Approver decide without a recommendation when approval is single-step', function () {
        config(['permits.two_step_approval' => false]);
        $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

        $data = resourceAs($this->approver, PermitResource::make($permit));

        expect(array_keys(array_filter($data['can'])))->toBe(['approve', 'reject']);
    });

    it('offers nothing to an applicant of another organization', function () {
        $stranger = applicantWithOrganization();
        $permit = Permit::factory()->forVehicles($this->vehicle)->draft()->create();

        $data = resourceAs($stranger, PermitResource::make($permit));

        expect(array_filter($data['can']))->toBe([]);
    });
});

describe('PermitTransitionResource', function () {
    it('never tells an applicant which member of staff acted', function () {
        $data = resourceAs($this->applicant, PermitResource::make(($this->loaded)()));

        expect($data['transitions'])->toHaveCount(3);

        foreach ($data['transitions'] as $transition) {
            expect(sortedKeys($transition))->toBe(sortedFields(TRANSITION_FIELDS));
        }

        expect(json_encode($data, JSON_UNESCAPED_UNICODE))->not->toContain('ሰላም ተስፋዬ');
    });

    it('tells staff who acted', function (Role $role) {
        $data = resourceAs(userWithRole($role), PermitResource::make(($this->loaded)()));

        foreach ($data['transitions'] as $transition) {
            expect(sortedKeys($transition))->toBe(sortedFields(TRANSITION_FIELDS, ['actor']));
        }

        expect(array_column($data['transitions'], 'actor'))->toBe(['አበበ ከበደ', 'አበበ ከበደ', 'ሰላም ተስፋዬ']);
    })->with([Role::Reviewer, Role::Approver]);

    it('presents each step, oldest first, with its note and local time', function () {
        $data = resourceAs($this->applicant, PermitResource::make(($this->loaded)()));

        expect(array_column($data['transitions'], 'from'))->toBe([null, 'draft', 'submitted'])
            ->and(array_column($data['transitions'], 'to'))->toBe(['draft', 'submitted', 'under_review'])
            ->and(array_column($data['transitions'], 'note'))->toBe([null, null, 'ማስታወሻ'])
            ->and(array_keys($data['transitions'][0]['at']))->toBe(DATE_FIELDS)
            ->and($data['transitions'][0]['time'])->toMatch('/^\d{2}:\d{2}$/');
    });

    it('shows the day and time of a step in Addis Ababa', function () {
        $this->travelTo('2026-11-11 21:30:00');
        $permit = Permit::factory()->forVehicles($this->vehicle)->submitted()->create();
        $step = $permit->transitions()->create(['from_status' => 'draft', 'to_status' => 'submitted', 'actor_id' => $this->applicant->id]);

        $data = resourceAs($this->applicant, PermitTransitionResource::make($step));

        expect(sortedKeys($data))->toBe(sortedFields(TRANSITION_FIELDS))
            ->and($data['at']['gregorian'])->toBe('2026-11-12')
            ->and($data['time'])->toBe('00:30');
    });

    it('leaves the actor out for staff when it was not loaded, and has none for the system', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->expired()->create();
        $step = $permit->transitions()->create(['from_status' => 'issued', 'to_status' => 'expired', 'actor_id' => null]);

        expect(resourceAs($this->reviewer, PermitTransitionResource::make($step)))->not->toHaveKey('actor');

        $data = resourceAs($this->reviewer, PermitTransitionResource::make($step->load('actor')));

        expect($data)->toHaveKey('actor')
            ->and($data['actor'])->toBeNull();
    });
});

describe('VehiclePermitHistoryResource', function () {
    it('has exactly the fields staff need to judge a conflict', function (bool $conflicts) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->active()->create();

        $data = resourceAs($this->reviewer, new VehiclePermitHistoryResource($permit, $conflicts));

        expect(sortedKeys($data))->toBe([
            'conflicts',
            'effective_status',
            'id',
            'permit_number',
            'permit_type',
            'status',
            'valid_from',
            'valid_to',
        ])
            ->and($data['id'])->toBe($permit->id)
            ->and($data['permit_number'])->toBe($permit->permit_number)
            ->and($data['status'])->toBe('issued')
            ->and($data['effective_status'])->toBe('active')
            ->and($data['conflicts'])->toBe($conflicts)
            ->and(array_keys($data['valid_from']))->toBe(DATE_FIELDS)
            // Nothing about who holds the other permit.
            ->and(json_encode($data, JSON_UNESCAPED_UNICODE))->not->toContain('ዋሊያ');
    })->with([true, false]);
});

it('never exposes a field of the model that is not listed', function () {
    $permit = Permit::factory()->forVehicles($this->vehicle)->revoked()->create(['applicant_id' => $this->applicant->id]);
    $json = json_encode(resourceAs($this->applicant, PermitResource::make($permit->load(['vehicles.vehicleType', 'organization', 'transitions']))), JSON_UNESCAPED_UNICODE);

    expect($json)->not->toContain('organization_id')
        ->not->toContain('applicant_id')
        ->not->toContain('revoked_by')
        ->not->toContain('revocation_reason')
        ->not->toContain('verification_token_hash')
        ->not->toContain('document_path')
        ->not->toContain('fee_cents')
        ->not->toContain('created_at');
});

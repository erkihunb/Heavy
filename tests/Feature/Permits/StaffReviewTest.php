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
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The back office over HTTP: the review queue, the detail page with the
 * history of the vehicles, and the decisions of Reviewers and Approvers.
 */

beforeEach(function () {
    withFeeSchedule();
    $this->reviewer = staffUser(Role::Reviewer);
    $this->approver = staffUser(Role::Approver);
    $this->organization = Organization::factory()->create(['name_am' => 'ዋሊያ ትራንስፖርት', 'name_en' => 'Walia Transport']);
    $this->vehicle = Vehicle::factory()->for($this->organization)->withPlate('ኢት-03-40134')->create();
});

/**
 * The ids of the applications the queue shows for the given query, in order.
 *
 * @param  array<string, mixed>  $query
 * @return list<string>
 */
function queueIds(User $staff, array $query = []): array
{
    $page = test()->actingAs($staff)->get(route('staff.permits.index', $query))->assertOk()->inertiaPage();

    return array_column($page['props']['permits']['data'], 'id');
}

describe('the queue', function () {
    it('shows what is waiting for the Authority, the longest wait on top', function () {
        $applicant = User::factory()->create(['name' => 'አበበ ከበደ']);
        $recent = Permit::factory()->forVehicles($this->vehicle)->submitted()->create(['submitted_at' => now()->subHours(3)]);
        $oldest = Permit::factory()->forVehicles($this->vehicle)->underReview()
            ->create(['submitted_at' => now()->subHours(50), 'applicant_id' => $applicant->id]);
        $middle = Permit::factory()->forVehicles($this->vehicle)->submitted()->create(['submitted_at' => now()->subHours(20)]);

        // Not waiting for a decision.
        Permit::factory()->forVehicles($this->vehicle)->changesRequested()->create();
        Permit::factory()->forVehicles($this->vehicle)->rejected()->create();
        Permit::factory()->forVehicles($this->vehicle)->awaitingPayment()->create();
        // Never shown to staff.
        Permit::factory()->forVehicles($this->vehicle)->draft()->create();
        Permit::factory()->forVehicles($this->vehicle)->withdrawn()->create(['submitted_at' => now()->subHours(90)]);

        $this->actingAs($this->reviewer)
            ->get(route('staff.permits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/permits/index')
                ->has('permits.data', 3)
                ->where('permits.data.0.id', $oldest->id)
                ->where('permits.data.0.status', 'under_review')
                ->where('permits.data.0.waiting_hours', 50)
                ->where('permits.data.0.applicant', 'አበበ ከበደ')
                ->where('permits.data.0.organization', 'ዋሊያ ትራንስፖርት')
                ->where('permits.data.0.vehicles.0.plate', 'ኢት-03-40134')
                ->where('permits.data.1.id', $middle->id)
                ->where('permits.data.2.id', $recent->id)
                ->where('permits.data.2.waiting_hours', 3)
                ->where('permits.meta.total', 3)
                ->where('filters', [
                    'status' => null,
                    'permit_type' => null,
                    'plate' => '',
                    'organization' => '',
                    'submitted_from' => null,
                    'submitted_to' => null,
                ]));
    });

    it('is open to Approvers as well', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->submitted()->create();

        expect(queueIds($this->approver))->toBe([$permit->id]);
    });

    it('shows twenty applications per page and keeps the filters in the page links', function () {
        Permit::factory()->count(21)->forVehicles($this->vehicle)->submitted()->create();

        $this->actingAs($this->reviewer)
            ->get(route('staff.permits.index', ['plate' => '40134']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('permits.data', 20)
                ->where('permits.meta.total', 21)
                ->where('permits.meta.per_page', 20)
                ->where('permits.links.next', fn (string $url) => str_contains($url, 'plate=40134') && str_contains($url, 'page=2')));

        expect(queueIds($this->reviewer, ['plate' => '40134', 'page' => 2]))->toHaveCount(1);
    });

    describe('filters', function () {
        beforeEach(function () {
            $today = ValidityRules::today();
            $otherOrganization = Organization::factory()->create(['name_am' => 'ሰላም ኮንስትራክሽን', 'name_en' => 'Selam Construction']);
            $otherVehicle = Vehicle::factory()->for($otherOrganization)->withPlate('አአ-03-A45301')->create();

            $this->submitted = Permit::factory()->forVehicles($this->vehicle)->submitted()->create([
                'permit_type' => PermitType::FullDay,
                // 01:00 on 2 November 2026 in Addis Ababa.
                'submitted_at' => CarbonImmutable::parse('2026-11-01 22:00', 'UTC'),
            ]);
            $this->underReview = Permit::factory()->forVehicles($otherVehicle)->underReview()->create([
                'permit_type' => PermitType::Pass,
                'submitted_at' => CarbonImmutable::parse('2026-11-05 09:00', 'UTC'),
            ]);
            $this->rejected = Permit::factory()->forVehicles($otherVehicle)->rejected()->create([
                'permit_type' => PermitType::Pass,
                'submitted_at' => CarbonImmutable::parse('2026-10-20 09:00', 'UTC'),
            ]);
            $this->awaitingPayment = Permit::factory()->forVehicles($this->vehicle)->awaitingPayment()->create([
                'permit_type' => PermitType::FullDay,
                'submitted_at' => CarbonImmutable::parse('2026-10-25 09:00', 'UTC'),
            ]);
            $this->active = Permit::factory()->forVehicles($otherVehicle)->active()->create([
                'permit_type' => PermitType::FullDay,
                'submitted_at' => CarbonImmutable::parse('2026-10-10 09:00', 'UTC'),
            ]);
            $this->draft = Permit::factory()->forVehicles($this->vehicle)->draft()->create();
            $this->withdrawn = Permit::factory()->forVehicles($this->vehicle)->withdrawn()->create([
                'submitted_at' => CarbonImmutable::parse('2026-11-03 09:00', 'UTC'),
            ]);

            // Oldest submission first.
            $this->everything = [
                $this->active->id,
                $this->rejected->id,
                $this->awaitingPayment->id,
                $this->submitted->id,
                $this->underReview->id,
            ];
        });

        it('shows every stage except drafts and withdrawn applications when asked for all', function () {
            expect(queueIds($this->reviewer, ['status' => 'all']))->toBe($this->everything);

            $this->actingAs($this->reviewer)
                ->get(route('staff.permits.index', ['status' => 'all']))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('filters.status', 'all')
                    // Only waiting applications have a waiting time.
                    ->where('permits.data.0.waiting_hours', null));
        });

        it('filters by one stage', function () {
            expect(queueIds($this->reviewer, ['status' => 'submitted']))->toBe([$this->submitted->id])
                ->and(queueIds($this->reviewer, ['status' => 'under_review']))->toBe([$this->underReview->id])
                ->and(queueIds($this->reviewer, ['status' => 'rejected']))->toBe([$this->rejected->id])
                ->and(queueIds($this->reviewer, ['status' => 'awaiting_payment']))->toBe([$this->awaitingPayment->id])
                ->and(queueIds($this->reviewer, ['status' => 'active']))->toBe([$this->active->id])
                ->and(queueIds($this->reviewer, ['status' => 'issued']))->toBe([])
                ->and(queueIds($this->reviewer, ['status' => 'revoked']))->toBe([]);
        });

        it('never shows drafts or withdrawn applications, whatever is asked', function (array $query) {
            $ids = queueIds($this->reviewer, $query);

            expect($ids)->not->toContain($this->draft->id)
                ->not->toContain($this->withdrawn->id);
        })->with([
            'default' => [[]],
            'all' => [['status' => 'all']],
            'drafts' => [['status' => 'draft']],
            'withdrawn' => [['status' => 'withdrawn']],
            'by plate' => [['status' => 'all', 'plate' => '40134']],
            'by organization' => [['status' => 'all', 'organization' => 'ዋሊያ']],
            'by submission day' => [['status' => 'all', 'submitted_from' => '2026-11-03', 'submitted_to' => '2026-11-03']],
        ]);

        it('falls back to the waiting applications for a stage staff may not see or do not know', function (string $status) {
            expect(queueIds($this->reviewer, ['status' => $status]))->toBe([$this->submitted->id, $this->underReview->id]);

            $this->actingAs($this->reviewer)
                ->get(route('staff.permits.index', ['status' => $status]))
                ->assertInertia(fn (Assert $page) => $page->where('filters.status', null));
        })->with(['draft', 'withdrawn', 'bogus']);

        it('filters by permit type', function () {
            expect(queueIds($this->reviewer, ['permit_type' => 'pass']))->toBe([$this->underReview->id])
                ->and(queueIds($this->reviewer, ['permit_type' => 'full_day']))->toBe([$this->submitted->id])
                ->and(queueIds($this->reviewer, ['permit_type' => 'pass', 'status' => 'all']))->toBe([$this->rejected->id, $this->underReview->id])
                ->and(queueIds($this->reviewer, ['permit_type' => 'bogus']))->toBe([$this->submitted->id, $this->underReview->id]);
        });

        it('filters by part of a plate, however it is typed', function () {
            expect(queueIds($this->reviewer, ['plate' => '40134']))->toBe([$this->submitted->id])
                ->and(queueIds($this->reviewer, ['plate' => 'a45301']))->toBe([$this->underReview->id])
                ->and(queueIds($this->reviewer, ['plate' => '03 a453', 'status' => 'all']))->toBe([$this->active->id, $this->rejected->id, $this->underReview->id])
                ->and(queueIds($this->reviewer, ['plate' => '99999']))->toBe([])
                ->and(queueIds($this->reviewer, ['plate' => '%']))->toBe([]);
        });

        it('filters by part of the name of the organization, in either language', function () {
            expect(queueIds($this->reviewer, ['organization' => 'ዋሊያ']))->toBe([$this->submitted->id])
                ->and(queueIds($this->reviewer, ['organization' => 'walia']))->toBe([$this->submitted->id])
                ->and(queueIds($this->reviewer, ['organization' => 'Construction']))->toBe([$this->underReview->id])
                ->and(queueIds($this->reviewer, ['organization' => 'ሰላም', 'status' => 'all']))->toBe([$this->active->id, $this->rejected->id, $this->underReview->id])
                ->and(queueIds($this->reviewer, ['organization' => 'nobody']))->toBe([])
                ->and(queueIds($this->reviewer, ['organization' => '_']))->toBe([]);
        });

        it('filters by the day of submission in Addis Ababa', function () {
            // The first application was handed in at 22:00 UTC on 1 November: 2 November locally.
            expect(queueIds($this->reviewer, ['submitted_from' => '2026-11-02']))->toBe([$this->submitted->id, $this->underReview->id])
                ->and(queueIds($this->reviewer, ['submitted_to' => '2026-11-01']))->toBe([])
                ->and(queueIds($this->reviewer, ['submitted_from' => '2026-11-02', 'submitted_to' => '2026-11-02']))->toBe([$this->submitted->id])
                ->and(queueIds($this->reviewer, ['submitted_from' => '2026-11-03']))->toBe([$this->underReview->id])
                ->and(queueIds($this->reviewer, ['submitted_from' => '2026-11-05', 'submitted_to' => '2026-11-05']))->toBe([$this->underReview->id])
                ->and(queueIds($this->reviewer, ['submitted_from' => '2026-11-06']))->toBe([])
                ->and(queueIds($this->reviewer, ['status' => 'all', 'submitted_to' => '2026-10-20']))->toBe([$this->active->id, $this->rejected->id]);
        });

        it('ignores dates that are not dates', function (string $value) {
            expect(queueIds($this->reviewer, ['submitted_from' => $value, 'submitted_to' => $value]))
                ->toBe([$this->submitted->id, $this->underReview->id]);

            $this->actingAs($this->reviewer)
                ->get(route('staff.permits.index', ['submitted_from' => $value]))
                ->assertInertia(fn (Assert $page) => $page->where('filters.submitted_from', null));
        })->with(['yesterday', '02/11/2026', '2026-02-30', '2026-13-01']);

        it('combines filters and tells the page which are applied', function () {
            $query = [
                'status' => 'all',
                'permit_type' => 'pass',
                'plate' => ' a45301 ',
                'organization' => ' selam ',
                'submitted_from' => '2026-10-20',
                'submitted_to' => '2026-10-31',
            ];

            expect(queueIds($this->reviewer, $query))->toBe([$this->rejected->id]);

            $this->actingAs($this->reviewer)
                ->get(route('staff.permits.index', $query))
                ->assertInertia(fn (Assert $page) => $page->where('filters', [
                    'status' => 'all',
                    'permit_type' => 'pass',
                    'plate' => 'a45301',
                    'organization' => 'selam',
                    'submitted_from' => '2026-10-20',
                    'submitted_to' => '2026-10-31',
                ]));
        });

        it('survives filter values of the wrong shape', function () {
            expect(queueIds($this->reviewer, [
                'status' => ['all'],
                'permit_type' => ['pass'],
                'plate' => ['40134'],
                'organization' => ['ዋሊያ'],
                'submitted_from' => ['2026-11-02'],
            ]))->toBe([$this->submitted->id, $this->underReview->id]);
        });
    });
});

describe('access', function () {
    it('sends guests to the login page', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->submitted()->create();

        $this->get(route('staff.permits.index'))->assertRedirect(route('login'));
        $this->get(route('staff.permits.show', $permit))->assertRedirect(route('login'));
        $this->post(route('staff.permits.start-review', $permit))->assertRedirect(route('login'));

        expect($permit->refresh()->status)->toBe(PermitStatus::Submitted);
    });

    it('is closed to staff who do not work on applications', function (Role $role) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->submitted()->create();
        $staff = staffUser($role);

        $this->actingAs($staff)->get(route('staff.permits.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('staff.permits.show', $permit))->assertForbidden();
        $this->actingAs($staff)->post(route('staff.permits.start-review', $permit))->assertForbidden();
        $this->actingAs($staff)->post(route('staff.permits.approve', $permit))->assertForbidden();

        expect($permit->refresh()->status)->toBe(PermitStatus::Submitted);
    })->with([Role::TrafficOfficer, Role::Registrar]);

    it('lets the cashier, the Issuer and the head office open applications, but not review them', function (Role $role) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->submitted()->create();
        $staff = staffUser($role);

        $this->actingAs($staff)->get(route('staff.permits.index'))->assertOk();
        $this->actingAs($staff)->get(route('staff.permits.show', $permit))->assertOk();
        $this->actingAs($staff)->post(route('staff.permits.start-review', $permit))->assertForbidden();
        $this->actingAs($staff)->post(route('staff.permits.approve', $permit))->assertForbidden();

        expect($permit->refresh()->status)->toBe(PermitStatus::Submitted);
    })->with([Role::Cashier, Role::Issuer, Role::Supervisor]);

    it('blocks staff who have not confirmed two-factor authentication', function (Role $role) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->recommended()->create();
        $staff = userWithRole($role);

        $this->actingAs($staff)->get(route('staff.permits.index'))->assertRedirect(route('two-factor.show'));
        $this->actingAs($staff)->get(route('staff.permits.show', $permit))->assertRedirect(route('two-factor.show'));

        foreach (['start-review', 'recommend', 'request-changes', 'approve', 'reject'] as $action) {
            $this->actingAs($staff)
                ->post(route("staff.permits.{$action}", $permit), ['recommendation' => 'approve', 'note' => 'ማስታወሻ'])
                ->assertRedirect(route('two-factor.show'));
        }

        $this->actingAs($staff)->getJson(route('staff.permits.index'))->assertForbidden();
        $this->actingAs($staff)->postJson(route('staff.permits.approve', $permit))->assertForbidden();

        expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview)
            ->and($permit->permit_number)->toBeNull()
            ->and(PermitStatusTransition::count())->toBe(0);
    })->with([Role::Reviewer, Role::Approver]);

    it('lets them in again when two-factor authentication is not required', function () {
        config(['permits.staff_two_factor_required' => false]);

        $this->actingAs(userWithRole(Role::Reviewer))->get(route('staff.permits.index'))->assertOk();
    });
});

describe('the detail page', function () {
    it('shows the application with the applicant, the recommendation and who did what', function () {
        $applicant = User::factory()->create(['name' => 'አበበ ከበደ']);
        $permit = Permit::factory()->forVehicles($this->vehicle)
            ->recommended(Recommendation::Reject, $this->reviewer)
            ->create(['applicant_id' => $applicant->id, 'submitted_at' => now()->subHours(5)]);
        $permit->transitions()->create(['from_status' => 'draft', 'to_status' => 'submitted', 'actor_id' => $applicant->id]);
        $permit->transitions()->create(['from_status' => 'submitted', 'to_status' => 'under_review', 'actor_id' => $this->reviewer->id]);

        $this->actingAs($this->approver)
            ->get(route('staff.permits.show', $permit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/permits/show')
                ->where('permit.id', $permit->id)
                ->where('permit.status', 'under_review')
                ->where('permit.applicant', 'አበበ ከበደ')
                ->where('permit.organization', 'ዋሊያ ትራንስፖርት')
                ->where('permit.waiting_hours', 5)
                ->where('permit.recommendation.decision', 'reject')
                ->where('permit.recommendation.note', $permit->recommendation_note)
                ->where('permit.recommendation.by', $this->reviewer->name)
                ->has('permit.vehicles', 1)
                ->has('permit.transitions', 2)
                ->where('permit.transitions.0.actor', 'አበበ ከበደ')
                ->where('permit.transitions.1.to', 'under_review')
                ->where('permit.transitions.1.actor', $this->reviewer->name)
                ->where('permit.can.approve', true)
                ->where('permit.can.reject', true)
                ->where('permit.can.recommend', false)
                // Changing an application is the Registrar's, on the applicant's own page.
                ->missing('permit.can.update')
                ->has('vehicleHistory', 1)
                ->where('vehicleHistory.0.vehicle_id', $this->vehicle->id)
                ->where('vehicleHistory.0.plate', 'ኢት-03-40134')
                ->where('vehicleHistory.0.has_conflict', false)
                ->where('vehicleHistory.0.permits', []));
    });

    it('lists the other permits of each vehicle and marks those that reserve it for the same days', function () {
        $today = ValidityRules::today();
        $trailer = Vehicle::factory()->for($this->organization)->withPlate('ኢት-03-A33490')->create();

        $permit = Permit::factory()->forVehicles($this->vehicle, $trailer)->underReview()
            ->validBetween($today->addDays(15), $today->addDays(25))
            ->create();

        $conflict = Permit::factory()->forVehicles($this->vehicle)->issued()
            ->validBetween($today->addDays(10), $today->addDays(20))
            ->create();
        $rejected = Permit::factory()->forVehicles($this->vehicle)->rejected()
            ->validBetween($today->addDays(30), $today->addDays(40))
            ->create();
        $expired = Permit::factory()->forVehicles($this->vehicle)->expired()->create();
        $onTrailer = Permit::factory()->forVehicles($trailer)->awaitingPayment()
            ->validBetween($today->addDays(60), $today->addDays(70))
            ->create();
        // Not staff's business.
        Permit::factory()->forVehicles($this->vehicle)->draft()->create();
        Permit::factory()->forVehicles($this->vehicle)->withdrawn()->create();
        // Another vehicle altogether.
        Permit::factory()->issued()->validBetween($today->addDays(15), $today->addDays(25))->create();

        $this->actingAs($this->reviewer)
            ->get(route('staff.permits.show', $permit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('vehicleHistory', 2)
                ->where('vehicleHistory.0.vehicle_id', $this->vehicle->id)
                ->where('vehicleHistory.0.has_conflict', true)
                ->has('vehicleHistory.0.permits', 3)
                ->where('vehicleHistory.0.permits.0.id', $conflict->id)
                ->where('vehicleHistory.0.permits.0.conflicts', true)
                ->where('vehicleHistory.0.permits.0.permit_number', $conflict->permit_number)
                ->where('vehicleHistory.0.permits.0.status', 'issued')
                ->where('vehicleHistory.0.permits.0.valid_from.gregorian', $today->addDays(10)->toDateString())
                ->where('vehicleHistory.0.permits.1.id', $rejected->id)
                ->where('vehicleHistory.0.permits.1.conflicts', false)
                ->where('vehicleHistory.0.permits.2.id', $expired->id)
                ->where('vehicleHistory.0.permits.2.conflicts', false)
                ->where('vehicleHistory.0.permits.2.effective_status', 'expired')
                ->where('vehicleHistory.1.vehicle_id', $trailer->id)
                ->where('vehicleHistory.1.plate', 'ኢት-03-A33490')
                ->where('vehicleHistory.1.has_conflict', false)
                ->has('vehicleHistory.1.permits', 1)
                ->where('vehicleHistory.1.permits.0.id', $onTrailer->id)
                ->where('vehicleHistory.1.permits.0.conflicts', false));
    });

    it('lists at most ten other permits per vehicle and never cuts off a conflict', function () {
        $today = ValidityRules::today();

        $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()
            ->validBetween($today->addDays(15), $today->addDays(25))
            ->create();
        $conflict = Permit::factory()->forVehicles($this->vehicle)->issued()
            ->validBetween($today->addDays(10), $today->addDays(20))
            ->create();
        // Twelve newer applications that reserve nothing.
        Permit::factory()->count(12)->forVehicles($this->vehicle)->rejected()
            ->validBetween($today->addDays(40), $today->addDays(50))
            ->create();

        $this->actingAs($this->reviewer)
            ->get(route('staff.permits.show', $permit))
            ->assertInertia(fn (Assert $page) => $page
                ->has('vehicleHistory.0.permits', 10)
                ->where('vehicleHistory.0.has_conflict', true)
                ->where('vehicleHistory.0.permits.0.id', $conflict->id)
                ->where('vehicleHistory.0.permits.0.conflicts', true)
                ->where('vehicleHistory.0.permits.1.conflicts', false));
    });

    it('answers 404 for a draft, a withdrawn application and one that does not exist', function () {
        $draft = Permit::factory()->forVehicles($this->vehicle)->draft()->create();
        $withdrawn = Permit::factory()->forVehicles($this->vehicle)->withdrawn()->create();

        $this->actingAs($this->reviewer)->get(route('staff.permits.show', $draft))->assertNotFound();
        $this->actingAs($this->reviewer)->get(route('staff.permits.show', $withdrawn))->assertNotFound();
        $this->actingAs($this->approver)->get(route('staff.permits.show', $draft))->assertNotFound();
        $this->actingAs($this->reviewer)->get(route('staff.permits.show', '01JZZZZZZZZZZZZZZZZZZZZZZZ'))->assertNotFound();
    });

    it('shows an application in every stage staff may see', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->create(['status' => $status]);

        $this->actingAs($this->reviewer)->get(route('staff.permits.show', $permit))->assertOk();
    })->with(array_values(array_filter(
        PermitStatus::stored(),
        fn (PermitStatus $status): bool => $status->isVisibleToStaff(),
    )));
});

describe('starting the review', function () {
    it('lets a Reviewer take a submitted application', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->submitted()->create();

        $this->actingAs($this->reviewer)
            ->post(route('staff.permits.start-review', $permit))
            ->assertRedirect(route('staff.permits.show', $permit))
            ->assertSessionHas('success', __('permits.messages.review_started'));

        $history = PermitStatusTransition::sole();

        expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview)
            ->and($history->from_status)->toBe(PermitStatus::Submitted)
            ->and($history->to_status)->toBe(PermitStatus::UnderReview)
            ->and($history->actor_id)->toBe($this->reviewer->id);
    });

    it('is not for Approvers', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->submitted()->create();

        $this->actingAs($this->approver)->post(route('staff.permits.start-review', $permit))->assertForbidden();

        expect($permit->refresh()->status)->toBe(PermitStatus::Submitted);
    });

    it('explains instead of failing when someone else already started', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

        $this->actingAs($this->reviewer)
            ->from(route('staff.permits.show', $permit))
            ->post(route('staff.permits.start-review', $permit))
            ->assertRedirect(route('staff.permits.show', $permit))
            ->assertSessionHas('warning', __('permits.errors.invalid_transition', ['status' => __('permits.status.under_review')]))
            ->assertSessionMissing('success');

        expect(PermitStatusTransition::count())->toBe(0);
    });

    it('answers 404 for a draft', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->draft()->create();

        $this->actingAs($this->reviewer)->post(route('staff.permits.start-review', $permit))->assertNotFound();

        expect($permit->refresh()->status)->toBe(PermitStatus::Draft);
    });
});

describe('recommending', function () {
    beforeEach(function () {
        $this->permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();
    });

    it('records a recommendation to approve, with or without a remark', function (?string $note) {
        $this->actingAs($this->reviewer)
            ->post(route('staff.permits.recommend', $this->permit), ['recommendation' => 'approve', 'note' => $note])
            ->assertRedirect(route('staff.permits.show', $this->permit))
            ->assertSessionHas('success', __('permits.messages.recommended'));

        $this->permit->refresh();

        expect($this->permit->status)->toBe(PermitStatus::UnderReview)
            ->and($this->permit->recommendation)->toBe(Recommendation::Approve)
            ->and($this->permit->recommended_by)->toBe($this->reviewer->id)
            ->and($this->permit->recommended_at)->not->toBeNull()
            ->and($this->permit->recommendation_note)->toBe($note)
            ->and(PermitStatusTransition::count())->toBe(0);
    })->with([null, 'ሰነዶቹ ተሟልተዋል']);

    it('records a recommendation to reject with its reason', function () {
        $this->actingAs($this->reviewer)
            ->post(route('staff.permits.recommend', $this->permit), ['recommendation' => 'reject', 'note' => 'መንገዱ አይፈቅድም'])
            ->assertSessionHasNoErrors();

        expect($this->permit->refresh()->recommendation)->toBe(Recommendation::Reject)
            ->and($this->permit->recommendation_note)->toBe('መንገዱ አይፈቅድም');
    });

    it('needs a note to recommend rejection', function (?string $note) {
        $this->actingAs($this->reviewer)
            ->post(route('staff.permits.recommend', $this->permit), ['recommendation' => 'reject', 'note' => $note])
            ->assertSessionHasErrors(['note' => __('permits.errors.note_required')]);

        expect($this->permit->refresh()->recommendation)->toBeNull();
    })->with([null, '', '   ']);

    it('rejects invalid input', function (array $input, string $field) {
        $this->actingAs($this->reviewer)
            ->post(route('staff.permits.recommend', $this->permit), $input)
            ->assertSessionHasErrors($field);

        expect($this->permit->refresh()->recommendation)->toBeNull();
    })->with([
        'no recommendation' => [[], 'recommendation'],
        'unknown recommendation' => [['recommendation' => 'maybe'], 'recommendation'],
        'note too long' => [['recommendation' => 'approve', 'note' => str_repeat('ሀ', 2001)], 'note'],
        'note as array' => [['recommendation' => 'approve', 'note' => ['x']], 'note'],
    ]);

    it('is not for Approvers', function () {
        $this->actingAs($this->approver)
            ->post(route('staff.permits.recommend', $this->permit), ['recommendation' => 'approve'])
            ->assertForbidden();

        expect($this->permit->refresh()->recommendation)->toBeNull();
    });

    it('explains instead of failing when the application is not under review', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->submitted()->create();

        $this->actingAs($this->reviewer)
            ->from(route('staff.permits.show', $permit))
            ->post(route('staff.permits.recommend', $permit), ['recommendation' => 'approve'])
            ->assertRedirect(route('staff.permits.show', $permit))
            ->assertSessionHas('warning', __('permits.errors.not_under_review'));

        expect($permit->refresh()->recommendation)->toBeNull();
    });
});

describe('requesting changes', function () {
    beforeEach(function () {
        $this->permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();
    });

    it('sends the application back with a note for the applicant', function () {
        $this->actingAs($this->reviewer)
            ->post(route('staff.permits.request-changes', $this->permit), ['note' => 'የተሽከርካሪውን ሊብሬ ያያይዙ'])
            ->assertRedirect(route('staff.permits.show', $this->permit))
            ->assertSessionHas('success', __('permits.messages.changes_requested'));

        $history = PermitStatusTransition::sole();

        expect($this->permit->refresh()->status)->toBe(PermitStatus::ChangesRequested)
            ->and($history->to_status)->toBe(PermitStatus::ChangesRequested)
            ->and($history->actor_id)->toBe($this->reviewer->id)
            ->and($history->note)->toBe('የተሽከርካሪውን ሊብሬ ያያይዙ');
    });

    it('needs a note', function (?string $note) {
        $this->actingAs($this->reviewer)
            ->post(route('staff.permits.request-changes', $this->permit), ['note' => $note])
            ->assertSessionHasErrors(['note' => __('permits.errors.note_required')]);

        expect($this->permit->refresh()->status)->toBe(PermitStatus::UnderReview);
    })->with([null, '', '   ']);

    it('is not for Approvers', function () {
        $this->actingAs($this->approver)
            ->post(route('staff.permits.request-changes', $this->permit), ['note' => 'ማስታወሻ'])
            ->assertForbidden();

        expect($this->permit->refresh()->status)->toBe(PermitStatus::UnderReview);
    });

    it('explains instead of failing when the review has not started', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->submitted()->create();

        $this->actingAs($this->reviewer)
            ->from(route('staff.permits.show', $permit))
            ->post(route('staff.permits.request-changes', $permit), ['note' => 'ማስታወሻ'])
            ->assertRedirect(route('staff.permits.show', $permit))
            ->assertSessionHas('warning', __('permits.errors.invalid_transition', ['status' => __('permits.status.submitted')]));

        expect($permit->refresh()->status)->toBe(PermitStatus::Submitted);
    });
});

describe('approving', function () {
    beforeEach(function () {
        $this->permit = Permit::factory()->forVehicles($this->vehicle)->recommended(reviewer: $this->reviewer)->create();
    });

    it('lets an Approver approve a recommended application', function () {
        $this->actingAs($this->approver)
            ->post(route('staff.permits.approve', $this->permit), ['note' => 'ጸድቋል'])
            ->assertRedirect(route('staff.permits.show', $this->permit))
            ->assertSessionHas('success', __('permits.messages.approved'));

        $this->permit->refresh();
        $history = PermitStatusTransition::sole();

        expect($this->permit->status)->toBe(PermitStatus::AwaitingPayment)
            ->and($this->permit->permit_number)->toMatch('/^AACRA-\d{4}-000001$/')
            ->and($this->permit->approved_by)->toBe($this->approver->id)
            ->and($this->permit->approved_at)->not->toBeNull()
            ->and($history->from_status)->toBe(PermitStatus::UnderReview)
            ->and($history->to_status)->toBe(PermitStatus::AwaitingPayment)
            ->and($history->actor_id)->toBe($this->approver->id)
            ->and($history->note)->toBe('ጸድቋል')
            ->and(permitVehicleRows($this->permit)->pluck('is_blocking')->all())->toBe([true]);
    });

    it('does not need a note', function () {
        $this->actingAs($this->approver)
            ->post(route('staff.permits.approve', $this->permit))
            ->assertSessionHasNoErrors();

        expect($this->permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment)
            ->and(PermitStatusTransition::sole()->note)->toBeNull();
    });

    it('rejects a note that is too long', function () {
        $this->actingAs($this->approver)
            ->post(route('staff.permits.approve', $this->permit), ['note' => str_repeat('ሀ', 2001)])
            ->assertSessionHasErrors('note');

        expect($this->permit->refresh()->status)->toBe(PermitStatus::UnderReview);
    });

    it('is not for the Reviewer who recorded the recommendation (two-step approval)', function () {
        $this->actingAs($this->reviewer)
            ->from(route('staff.permits.show', $this->permit))
            ->post(route('staff.permits.approve', $this->permit))
            ->assertRedirect(route('staff.permits.show', $this->permit))
            ->assertSessionHas('warning', __('permits.errors.approver_is_recommender'));

        expect($this->permit->refresh()->status)->toBe(PermitStatus::UnderReview)
            ->and($this->permit->permit_number)->toBeNull();
    });

    it('lets a Reviewer decide alone when two-step approval is switched off', function () {
        config(['permits.two_step_approval' => false]);
        $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

        $this->actingAs($this->reviewer)
            ->post(route('staff.permits.approve', $permit))
            ->assertSessionHas('success', __('permits.messages.approved'));

        expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment)
            ->and($permit->approved_by)->toBe($this->reviewer->id);
    });

    it('explains instead of failing when no recommendation has been recorded', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

        $this->actingAs($this->approver)
            ->from(route('staff.permits.show', $permit))
            ->post(route('staff.permits.approve', $permit))
            ->assertRedirect(route('staff.permits.show', $permit))
            ->assertSessionHas('warning', __('permits.errors.recommendation_required'))
            ->assertSessionMissing('success');

        expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview)
            ->and($permit->permit_number)->toBeNull();
    });

    it('explains instead of failing when the Approver recorded the recommendation', function () {
        $both = staffUser(Role::Reviewer);
        $both->assignRole(Role::Approver->value);
        $permit = Permit::factory()->forVehicles($this->vehicle)->recommended(reviewer: $both)->create();

        $this->actingAs($both)
            ->from(route('staff.permits.show', $permit))
            ->post(route('staff.permits.approve', $permit))
            ->assertRedirect(route('staff.permits.show', $permit))
            ->assertSessionHas('warning', __('permits.errors.approver_is_recommender'));

        expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview);
    });

    it('approves without a recommendation when two-step approval is switched off', function () {
        config(['permits.two_step_approval' => false]);
        $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

        $this->actingAs($this->approver)
            ->post(route('staff.permits.approve', $permit))
            ->assertSessionHas('success');

        expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment);
    });

    it('explains instead of failing when a vehicle is already reserved for those days', function () {
        $today = ValidityRules::today();
        Permit::factory()->forVehicles($this->vehicle)->issued()
            ->validBetween($today->addDays(10), $today->addDays(20))
            ->create();
        $permit = Permit::factory()->forVehicles($this->vehicle)->recommended()
            ->validBetween($today->addDays(15), $today->addDays(25))
            ->create();

        $this->actingAs($this->approver)
            ->from(route('staff.permits.show', $permit))
            ->post(route('staff.permits.approve', $permit))
            ->assertRedirect(route('staff.permits.show', $permit))
            ->assertSessionHas('warning', fn (string $message) => str_contains($message, 'ኢት-03-40134'))
            ->assertSessionMissing('success');

        expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview)
            ->and($permit->permit_number)->toBeNull()
            ->and(DB::table('permit_number_sequences')->count())->toBe(0)
            ->and(PermitStatusTransition::count())->toBe(0);
    });

    it('explains instead of failing when the application is in another stage', function (PermitStatus $status) {
        $permit = Permit::factory()->forVehicles($this->vehicle)->recommended()->create(['status' => $status]);

        $this->actingAs($this->approver)
            ->from(route('staff.permits.show', $permit))
            ->post(route('staff.permits.approve', $permit))
            ->assertRedirect(route('staff.permits.show', $permit))
            ->assertSessionHas('warning', __('permits.errors.invalid_transition', ['status' => __("permits.status.{$status->value}")]));

        expect($permit->refresh()->status)->toBe($status)
            ->and($permit->permit_number)->toBeNull();
    })->with([PermitStatus::Submitted, PermitStatus::ChangesRequested, PermitStatus::Rejected]);

    it('answers a JSON request with 422 and the reason', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

        $this->actingAs($this->approver)
            ->postJson(route('staff.permits.approve', $permit))
            ->assertUnprocessable()
            ->assertExactJson(['message' => __('permits.errors.recommendation_required')]);
    });

    it('answers 404 for a draft and a withdrawn application', function () {
        $draft = Permit::factory()->forVehicles($this->vehicle)->draft()->create();
        $withdrawn = Permit::factory()->forVehicles($this->vehicle)->withdrawn()->create();

        $this->actingAs($this->approver)->post(route('staff.permits.approve', $draft))->assertNotFound();
        $this->actingAs($this->approver)->post(route('staff.permits.approve', $withdrawn))->assertNotFound();
        $this->actingAs($this->approver)->post(route('staff.permits.reject', $draft), ['note' => 'ምክንያት'])->assertNotFound();

        expect($draft->refresh()->status)->toBe(PermitStatus::Draft)
            ->and($withdrawn->refresh()->status)->toBe(PermitStatus::Withdrawn);
    });
});

describe('rejecting', function () {
    beforeEach(function () {
        $this->permit = Permit::factory()->forVehicles($this->vehicle)->recommended(Recommendation::Reject, $this->reviewer)->create();
    });

    it('lets an Approver reject a recommended application with the reason', function () {
        $this->actingAs($this->approver)
            ->post(route('staff.permits.reject', $this->permit), ['note' => 'መንገዱ ክብደቱን አይችልም'])
            ->assertRedirect(route('staff.permits.show', $this->permit))
            ->assertSessionHas('success', __('permits.messages.rejected'));

        $history = PermitStatusTransition::sole();

        expect($this->permit->refresh()->status)->toBe(PermitStatus::Rejected)
            ->and($this->permit->permit_number)->toBeNull()
            ->and($history->from_status)->toBe(PermitStatus::UnderReview)
            ->and($history->to_status)->toBe(PermitStatus::Rejected)
            ->and($history->actor_id)->toBe($this->approver->id)
            ->and($history->note)->toBe('መንገዱ ክብደቱን አይችልም');
    });

    it('needs a note', function (?string $note) {
        $this->actingAs($this->approver)
            ->post(route('staff.permits.reject', $this->permit), ['note' => $note])
            ->assertSessionHasErrors(['note' => __('permits.errors.note_required')]);

        expect($this->permit->refresh()->status)->toBe(PermitStatus::UnderReview);
    })->with([null, '', '   ']);

    it('is not for the Reviewer who recorded the recommendation (two-step approval)', function () {
        $this->actingAs($this->reviewer)
            ->from(route('staff.permits.show', $this->permit))
            ->post(route('staff.permits.reject', $this->permit), ['note' => 'ምክንያት'])
            ->assertSessionHas('warning', __('permits.errors.approver_is_recommender'));

        expect($this->permit->refresh()->status)->toBe(PermitStatus::UnderReview);
    });

    it('explains instead of failing when no recommendation has been recorded', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->underReview()->create();

        $this->actingAs($this->approver)
            ->from(route('staff.permits.show', $permit))
            ->post(route('staff.permits.reject', $permit), ['note' => 'ምክንያት'])
            ->assertRedirect(route('staff.permits.show', $permit))
            ->assertSessionHas('warning', __('permits.errors.recommendation_required'));

        expect($permit->refresh()->status)->toBe(PermitStatus::UnderReview);
    });

    it('explains instead of failing when the application was already decided', function () {
        $permit = Permit::factory()->forVehicles($this->vehicle)->awaitingPayment()->create();

        $this->actingAs($this->approver)
            ->from(route('staff.permits.show', $permit))
            ->post(route('staff.permits.reject', $permit), ['note' => 'ምክንያት'])
            ->assertRedirect(route('staff.permits.show', $permit))
            ->assertSessionHas('warning', __('permits.errors.invalid_transition', ['status' => __('permits.status.awaiting_payment')]));

        expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment);
    });
});

it('takes an application through review and approval', function () {
    $permit = Permit::factory()->forVehicles($this->vehicle)->submitted()->create();

    $this->actingAs($this->reviewer)->post(route('staff.permits.start-review', $permit))->assertSessionHas('success');
    $this->actingAs($this->reviewer)->post(route('staff.permits.recommend', $permit), ['recommendation' => 'approve'])->assertSessionHas('success');
    $this->actingAs($this->approver)->post(route('staff.permits.approve', $permit))->assertSessionHas('success');

    expect($permit->refresh()->status)->toBe(PermitStatus::AwaitingPayment)
        ->and($permit->recommended_by)->toBe($this->reviewer->id)
        ->and($permit->approved_by)->toBe($this->approver->id)
        ->and($permit->transitions()->pluck('to_status')->all())->toBe([PermitStatus::UnderReview, PermitStatus::AwaitingPayment]);

    // It has left the queue.
    expect(queueIds($this->reviewer))->toBe([])
        ->and(queueIds($this->reviewer, ['status' => 'awaiting_payment']))->toBe([$permit->id]);
});

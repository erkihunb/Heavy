<?php

use App\Enums\PermitStatus;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\User;
use App\Policies\PermitPolicy;
use Illuminate\Support\Facades\Gate;

/*
 * Role × action matrix for permits. Applicants act on the applications of
 * their own organization, in the right stage; Reviewers and Approvers act on
 * applications that have been submitted. For staff the policy decides who,
 * the state machine decides when.
 */

const APPLICANT_ABILITIES = ['update', 'submit', 'withdraw'];
const REVIEWER_ABILITIES = ['startReview', 'recommend', 'requestChanges'];
const APPROVER_ABILITIES = ['approve', 'reject', 'revoke'];

/**
 * A user with the role who belongs to the organization of the permit, so
 * that only the role decides.
 */
function memberWithRole(Role $role, Permit $permit): User
{
    $user = userWithRole($role);
    $user->forceFill(['organization_id' => $permit->organization_id])->save();

    return $user;
}

/**
 * The abilities among the given ones that the user has on the permit.
 *
 * @param  list<string>  $abilities
 * @return list<string>
 */
function allowedAbilities(User $user, Permit $permit, array $abilities): array
{
    return array_values(array_filter($abilities, fn (string $ability): bool => $user->can($ability, $permit)));
}

/**
 * The HTTP status a refusal is reported with: 404 hides the permit, 403 does not.
 */
function denialStatus(User $user, string $ability, Permit $permit): ?int
{
    $response = Gate::forUser($user)->inspect($ability, $permit);

    return $response->allowed() ? null : ($response->status() ?? 403);
}

describe('without a permit', function () {
    it('lets only applicants reach the list of applications', function (Role $role) {
        expect(userWithRole($role)->can('viewAny', Permit::class))->toBe($role === Role::Applicant);
    })->with(Role::cases());

    it('lets only applicants with an organization start an application', function (Role $role) {
        $user = userWithRole($role);
        $user->forceFill(['organization_id' => Organization::factory()->create()->id])->save();

        expect($user->can('create', Permit::class))->toBe($role === Role::Applicant);
    })->with(Role::cases());

    it('does not let an applicant without an organization start an application', function () {
        $user = userWithRole(Role::Applicant);

        expect($user->can('viewAny', Permit::class))->toBeTrue()
            ->and($user->can('create', Permit::class))->toBeFalse();
    });

    it('lets only Reviewers, Approvers and Admins reach the review queue', function (Role $role) {
        expect(userWithRole($role)->can('viewQueue', Permit::class))
            ->toBe(in_array($role, [Role::Reviewer, Role::Approver, Role::Admin], true));
    })->with(Role::cases());
});

describe('viewing', function () {
    it('shows a draft or withdrawn application to applicants of its organization only', function (Role $role, PermitStatus $status) {
        $permit = Permit::factory()->create(['status' => $status]);

        expect(memberWithRole($role, $permit)->can('view', $permit))->toBe($role === Role::Applicant);
    })->with(Role::cases())->with([PermitStatus::Draft, PermitStatus::Withdrawn]);

    it('shows a submitted application to its organization, Reviewers, Approvers and Admins', function (Role $role, PermitStatus $status) {
        $permit = Permit::factory()->create(['status' => $status]);

        expect(memberWithRole($role, $permit)->can('view', $permit))
            ->toBe(in_array($role, [Role::Applicant, Role::Reviewer, Role::Approver, Role::Admin], true));
    })->with(Role::cases())->with([
        PermitStatus::Submitted,
        PermitStatus::UnderReview,
        PermitStatus::ChangesRequested,
        PermitStatus::Rejected,
        PermitStatus::AwaitingPayment,
        PermitStatus::PaymentSubmitted,
        PermitStatus::Issued,
        PermitStatus::Expired,
        PermitStatus::Revoked,
    ]);

    it('hides an application from applicants of another organization', function (PermitStatus $status) {
        $permit = Permit::factory()->create(['status' => $status]);
        $stranger = applicantWithOrganization();

        expect($stranger->can('view', $permit))->toBeFalse()
            ->and(denialStatus($stranger, 'view', $permit))->toBe(404);
    })->with(PermitStatus::stored());

    it('reports a hidden application as not found', function () {
        $draft = Permit::factory()->draft()->create();

        expect(denialStatus(userWithRole(Role::Reviewer), 'view', $draft))->toBe(404)
            ->and(denialStatus(userWithRole(Role::Cashier), 'view', $draft))->toBe(404)
            ->and(denialStatus(userWithRole(Role::Applicant), 'view', $draft))->toBe(404);
    });
});

describe('the applicant\'s actions', function () {
    it('lets only applicants of the organization change, submit or withdraw a draft', function (Role $role) {
        $permit = Permit::factory()->draft()->create();

        expect(allowedAbilities(memberWithRole($role, $permit), $permit, APPLICANT_ABILITIES))
            ->toBe($role === Role::Applicant ? APPLICANT_ABILITIES : []);
    })->with(Role::cases());

    it('allows each action only in the stages it belongs to', function (PermitStatus $status, array $expected) {
        $permit = Permit::factory()->create(['status' => $status]);
        $applicant = memberWithRole(Role::Applicant, $permit);

        expect(allowedAbilities($applicant, $permit, APPLICANT_ABILITIES))->toBe($expected);

        // Their own application in the wrong stage is forbidden, not hidden.
        foreach (array_diff(APPLICANT_ABILITIES, $expected) as $ability) {
            expect(denialStatus($applicant, $ability, $permit))->toBe(403);
        }
    })->with([
        'draft' => [PermitStatus::Draft, ['update', 'submit', 'withdraw']],
        'submitted' => [PermitStatus::Submitted, ['withdraw']],
        'under review' => [PermitStatus::UnderReview, []],
        'changes requested' => [PermitStatus::ChangesRequested, ['update', 'submit']],
        'rejected' => [PermitStatus::Rejected, []],
        'awaiting payment' => [PermitStatus::AwaitingPayment, []],
        'payment submitted' => [PermitStatus::PaymentSubmitted, []],
        'issued' => [PermitStatus::Issued, []],
        'expired' => [PermitStatus::Expired, []],
        'revoked' => [PermitStatus::Revoked, []],
        'withdrawn' => [PermitStatus::Withdrawn, []],
    ]);

    it('hides the application of another organization from an applicant, whatever the action', function (PermitStatus $status) {
        $permit = Permit::factory()->create(['status' => $status]);
        $stranger = applicantWithOrganization();

        expect(allowedAbilities($stranger, $permit, APPLICANT_ABILITIES))->toBe([]);

        foreach (APPLICANT_ABILITIES as $ability) {
            expect(denialStatus($stranger, $ability, $permit))->toBe(404);
        }
    })->with([PermitStatus::Draft, PermitStatus::Submitted, PermitStatus::ChangesRequested]);

    it('does not let an applicant without an organization act on any application', function () {
        $permit = Permit::factory()->draft()->create();
        $user = userWithRole(Role::Applicant);

        expect($user->can('view', $permit))->toBeFalse()
            ->and(allowedAbilities($user, $permit, APPLICANT_ABILITIES))->toBe([]);
    });
});

describe('the decisions of staff', function () {
    it('gives each role its own decisions on a submitted application', function (Role $role, PermitStatus $status) {
        $permit = Permit::factory()->create(['status' => $status]);
        $user = memberWithRole($role, $permit);

        $expected = match ($role) {
            Role::Reviewer => REVIEWER_ABILITIES,
            Role::Approver => APPROVER_ABILITIES,
            Role::Admin => [...REVIEWER_ABILITIES, ...APPROVER_ABILITIES],
            default => [],
        };

        expect(allowedAbilities($user, $permit, [...REVIEWER_ABILITIES, ...APPROVER_ABILITIES]))->toBe($expected);
    })->with(Role::cases())->with([
        // The stage is the business of the state machine, not of the policy.
        PermitStatus::Submitted,
        PermitStatus::UnderReview,
        PermitStatus::AwaitingPayment,
        PermitStatus::Issued,
        PermitStatus::Rejected,
    ]);

    it('gives nobody a decision on a draft or a withdrawn application', function (Role $role, PermitStatus $status) {
        $permit = Permit::factory()->create(['status' => $status]);
        $user = memberWithRole($role, $permit);

        expect(allowedAbilities($user, $permit, [...REVIEWER_ABILITIES, ...APPROVER_ABILITIES]))->toBe([]);

        if ($role->isStaff()) {
            foreach ([...REVIEWER_ABILITIES, ...APPROVER_ABILITIES] as $ability) {
                expect(denialStatus($user, $ability, $permit))->toBe(404);
            }
        }
    })->with(Role::cases())->with([PermitStatus::Draft, PermitStatus::Withdrawn]);

    it('forbids, rather than hides, the decisions of the other role', function () {
        $permit = Permit::factory()->underReview()->create();
        $reviewer = userWithRole(Role::Reviewer);
        $approver = userWithRole(Role::Approver);

        foreach (APPROVER_ABILITIES as $ability) {
            expect(denialStatus($reviewer, $ability, $permit))->toBe(403);
        }

        foreach (REVIEWER_ABILITIES as $ability) {
            expect(denialStatus($approver, $ability, $permit))->toBe(403);
        }
    });

    it('hides the application from staff who cannot see the queue', function (Role $role) {
        $permit = Permit::factory()->underReview()->create();
        $user = userWithRole($role);

        foreach ([...REVIEWER_ABILITIES, ...APPROVER_ABILITIES] as $ability) {
            expect(denialStatus($user, $ability, $permit))->toBe(404);
        }
    })->with([Role::Applicant, Role::Cashier, Role::TrafficOfficer]);

    it('does not depend on the organization of the member of staff', function () {
        $permit = Permit::factory()->underReview()->create();

        expect(allowedAbilities(userWithRole(Role::Reviewer), $permit, REVIEWER_ABILITIES))->toBe(REVIEWER_ABILITIES)
            ->and(allowedAbilities(userWithRole(Role::Approver), $permit, APPROVER_ABILITIES))->toBe(APPROVER_ABILITIES)
            ->and(userWithRole(Role::Reviewer)->can('view', $permit))->toBeTrue();
    });
});

it('denies a user without any role everything', function () {
    $permit = Permit::factory()->submitted()->create();
    $user = User::factory()->create();
    $user->forceFill(['organization_id' => $permit->organization_id])->save();

    expect($user->can('viewAny', Permit::class))->toBeFalse()
        ->and($user->can('create', Permit::class))->toBeFalse()
        ->and($user->can('viewQueue', Permit::class))->toBeFalse()
        ->and($user->can('view', $permit))->toBeFalse()
        ->and(allowedAbilities($user, $permit, [...APPLICANT_ABILITIES, ...REVIEWER_ABILITIES, ...APPROVER_ABILITIES]))->toBe([]);
});

it('has no policy for deleting permits', function () {
    $permit = Permit::factory()->draft()->create();

    expect(memberWithRole(Role::Applicant, $permit)->can('delete', $permit))->toBeFalse()
        ->and(userWithRole(Role::Admin)->can('delete', $permit))->toBeFalse();
});

it('covers every ability of the policy', function () {
    $methods = collect((new ReflectionClass(PermitPolicy::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->map(fn (ReflectionMethod $method): string => $method->getName())
        ->sort()
        ->values()
        ->all();

    // submitPayment is covered in PaymentPolicyTest; download, reissue, scan and report in VerificationPolicyTest.
    $tested = [
        'viewAny', 'create', 'view', 'viewQueue', 'submitPayment', 'download', 'reissue', 'scan', 'report',
        ...APPLICANT_ABILITIES, ...REVIEWER_ABILITIES, ...APPROVER_ABILITIES,
    ];
    sort($tested);

    expect($methods)->toBe($tested);
});

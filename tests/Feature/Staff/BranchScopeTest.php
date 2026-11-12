<?php

use App\Actions\Users\GiveDefaultBranchScope;
use App\Domain\Reports\PermitReports;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Export;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use App\Support\Rbac\BranchScope;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Branch scope: staff see and act on the applications of the branches in
 * their scope rows only (a new account starts with where they work; the
 * head office starts with every branch). Admins see the whole institution. An application gets its branch when it is started.
 */

beforeEach(function () {
    withFeeSchedule();

    $this->bole = Branch::factory()->create(['code' => 'BOL']);
    $this->yeka = Branch::factory()->create(['code' => 'YEK']);

    $this->at = function (Role $role, ?Branch $branch): User {
        $user = staffUser($role);
        $user->forceFill(['branch_id' => $branch?->id])->save();
        // Their scope starts from where they work, as for a new account.
        $user->branchScopes()->delete();
        app(GiveDefaultBranchScope::class)->handle($user);

        return $user->refresh();
    };

    $this->atBole = Permit::factory()->submitted()->create(['branch_id' => $this->bole->id]);
    $this->atYeka = Permit::factory()->submitted()->create(['branch_id' => $this->yeka->id]);
    $this->atHeadOffice = Permit::factory()->submitted()->create();

    $this->queueIds = fn (User $user): array => collect(
        $this->actingAs($user)->get(route('staff.permits.index'))->assertOk()->viewData('page')['props']['permits']['data'],
    )->pluck('id')->sort()->values()->all();
});

it('shows a branch reviewer only the applications of their branch', function () {
    expect(($this->queueIds)(($this->at)(Role::Reviewer, $this->bole)))->toBe([$this->atBole->id]);
});

it('shows the whole institution to admins and to head-office staff', function (Role $role, bool $atBranch) {
    $all = collect([$this->atBole->id, $this->atYeka->id, $this->atHeadOffice->id])->sort()->values()->all();

    // An Admin sees everything even when placed at a branch.
    expect(($this->queueIds)(($this->at)($role, $atBranch ? $this->bole : null)))->toBe($all);
})->with([
    'admin at a branch' => [Role::Admin, true],
    'admin at the head office' => [Role::Admin, false],
    'head-office reviewer' => [Role::Reviewer, false],
    'head-office approver' => [Role::Approver, false],
]);

it('hides another branch\'s application as if it did not exist', function () {
    $approver = ($this->at)(Role::Approver, $this->bole);

    $this->actingAs($approver)->get(route('staff.permits.show', $this->atBole))->assertOk();
    $this->actingAs($approver)->get(route('staff.permits.show', $this->atYeka))->assertNotFound();
    $this->actingAs($approver)->get(route('staff.permits.show', $this->atHeadOffice))->assertNotFound();

    $recommended = Permit::factory()->recommended()->create(['branch_id' => $this->yeka->id]);
    $this->actingAs($approver)->post(route('staff.permits.approve', $recommended))->assertNotFound();

    expect($recommended->refresh()->permit_number)->toBeNull();
});

it('limits a branch cashier to the payments of their branch', function () {
    $payment = fn (Permit $permit): Payment => Payment::factory()->create(['permit_id' => $permit->id, 'status' => PaymentStatus::Submitted]);
    $mine = $payment(Permit::factory()->paymentSubmitted()->create(['branch_id' => $this->bole->id]));
    $theirs = $payment(Permit::factory()->paymentSubmitted()->create(['branch_id' => $this->yeka->id]));
    $cashier = ($this->at)(Role::Cashier, $this->bole);

    $listed = collect($this->actingAs($cashier)->get(route('staff.payments.index'))->assertOk()->viewData('page')['props']['payments']['data'])->pluck('id');

    expect($listed->all())->toBe([$mine->id]);

    $this->actingAs($cashier)->get(route('staff.payments.show', $theirs))->assertNotFound();
    $this->actingAs($cashier)->post(route('staff.payments.verify', $theirs))->assertNotFound();

    expect($theirs->refresh()->status)->toBe(PaymentStatus::Submitted);

    // The head office still sees both.
    $all = collect($this->actingAs(($this->at)(Role::Cashier, null))->get(route('staff.payments.index'))->viewData('page')['props']['payments']['data'])->pluck('id');
    expect($all->sort()->values()->all())->toBe(collect([$mine->id, $theirs->id])->sort()->values()->all());
});

it('counts only the branch in reports and on the dashboard', function () {
    Permit::factory()->active()->count(2)->create(['branch_id' => $this->bole->id]);
    Permit::factory()->active()->create(['branch_id' => $this->yeka->id]);

    $reports = app(PermitReports::class);

    expect($reports->byStatus(scope: BranchScope::of(false, [$this->bole->id]))['active'])->toBe(2)
        ->and($reports->byStatus()['active'])->toBe(3);

    $this->actingAs(($this->at)(Role::Approver, $this->bole))
        ->get(route('staff.reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('byStatus.active', 2)->where('byStatus.submitted', 1));

    $this->actingAs(($this->at)(Role::Approver, $this->bole))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('counts.waiting_review', 1)->where('counts.in_force', 2));

    $this->actingAs(($this->at)(Role::Approver, null))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('counts.waiting_review', 3)->where('counts.in_force', 3));
});

it('fixes the branch of an export to the person who asks for it', function () {
    Queue::fake();

    $this->actingAs(($this->at)(Role::Approver, $this->bole))
        ->post(route('staff.exports.store'), ['dataset' => 'permits', 'format' => 'csv', 'branch_id' => $this->yeka->id])
        ->assertRedirect();

    $this->actingAs(($this->at)(Role::Approver, null))
        ->post(route('staff.exports.store'), ['dataset' => 'permits', 'format' => 'csv'])
        ->assertRedirect();

    $filters = Export::query()->orderBy('id')->get()->map(fn ($export) => $export->filters['scope'] ?? null)->all();

    expect($filters)->toBe([
        ['head_office' => false, 'branch_ids' => [$this->bole->id]],
        ['head_office' => true, 'branch_ids' => [$this->bole->id, $this->yeka->id]],
    ]);
});

describe('where an application belongs', function () {
    it('lets an applicant choose the branch they apply to', function () {
        $applicant = applicantWithOrganization();

        $this->actingAs($applicant)
            ->get(route('permits.create'))
            ->assertInertia(fn (Assert $page) => $page->has('branches', 2)->where('branches.0.code', 'BOL'));

        $this->actingAs($applicant)
            ->post(route('permits.store'), permitPayload($applicant, ['branch_id' => $this->yeka->id]))
            ->assertRedirect();

        $permit = Permit::query()->where('applicant_id', $applicant->id)->sole();
        expect($permit->branch_id)->toBe($this->yeka->id);

        // Changing a draft may move it, here to the head office.
        $this->actingAs($applicant)
            ->put(route('permits.update', $permit), permitPayload($applicant, ['branch_id' => '']))
            ->assertRedirect();

        expect($permit->refresh()->branch_id)->toBeNull();
    });

    it('refuses a closed or unknown branch', function (Closure $branchId) {
        $applicant = applicantWithOrganization();

        $this->actingAs($applicant)
            ->post(route('permits.store'), permitPayload($applicant, ['branch_id' => $branchId()]))
            ->assertSessionHasErrors('branch_id');
    })->with([
        'closed' => [fn () => Branch::factory()->closed()->create()->id],
        'unknown' => [fn () => 999_999],
    ]);

    it('gives a walk-in application the branch of the registrar, whatever the form says', function () {
        $registrar = ($this->at)(Role::Registrar, $this->bole);
        $applicant = applicantWithOrganization();

        $this->actingAs($registrar)
            ->post(route('admin.applicants.permits.store', $applicant), [...permitPayload($applicant, ['branch_id' => $this->yeka->id]), ...letterFields()])
            ->assertRedirect();

        $permit = Permit::query()->where('applicant_id', $applicant->id)->sole();
        expect($permit->branch_id)->toBe($this->bole->id);

        // A registrar of another branch cannot open it; the head office can.
        $this->actingAs(($this->at)(Role::Registrar, $this->yeka))
            ->get(route('admin.applicants.permits.show', [$applicant, $permit]))
            ->assertNotFound();
        $this->actingAs(($this->at)(Role::Registrar, null))
            ->get(route('admin.applicants.permits.show', [$applicant, $permit]))
            ->assertOk();
    });
});

it('lets a traffic officer check and report a permit of any branch', function () {
    $permit = Permit::factory()->active()->create(['branch_id' => $this->yeka->id]);
    $officer = ($this->at)(Role::TrafficOfficer, $this->bole);

    // The roadside is not a branch: verification stays institution-wide.
    expect($officer->can('scan', Permit::class))->toBeTrue()
        ->and($officer->can('report', $permit))->toBeTrue();
});

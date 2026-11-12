<?php

use App\Actions\Permits\ApprovePermit;
use App\Domain\Audit\AuditEvent;
use App\Domain\Documents\PermitDocument;
use App\Domain\Permits\PermitNumberGenerator;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * Branch offices: added, renamed and closed by an Admin; staff are placed at
 * one; a permit keeps the branch of its approver, whose code is part of the
 * permit number when the number rule names {branch}.
 */

beforeEach(function () {
    $this->admin = staffUser(Role::Admin);
});

it('is only for admins', function () {
    $this->actingAs(staffUser(Role::Approver))->get(route('admin.branches.index'))->assertForbidden();
    $this->actingAs(staffUser(Role::Approver))->post(route('admin.branches.store'), [])->assertForbidden();
});

it('lists the branches with their staff and permits', function () {
    $bole = Branch::factory()->create(['code' => 'BOL']);
    Branch::factory()->closed()->create(['code' => 'YEK']);
    User::factory()->count(2)->create()->each(fn (User $user) => $user->forceFill(['branch_id' => $bole->id])->save());

    $this->actingAs($this->admin)
        ->get(route('admin.branches.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/branches/index')
            ->has('branches', 2)
            ->where('branches.0.code', 'BOL')
            ->where('branches.0.staff', 2)
            ->where('branches.0.permits', 0)
            ->where('branches.1.is_active', false)
            ->where('in_number', true));
});

it('creates a branch with an upper-case code and audits it', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.branches.store'), ['code' => ' bol ', 'name_am' => 'ቦሌ ቅርንጫፍ', 'name_en' => 'Bole branch', 'is_active' => true])
        ->assertRedirect(route('admin.branches.index'));

    $branch = Branch::query()->where('code', 'BOL')->sole();

    expect($branch->name_am)->toBe('ቦሌ ቅርንጫፍ')->and($branch->is_active)->toBeTrue()
        ->and(Activity::query()->where('event', AuditEvent::BranchCreated->value)->sole()->properties['attributes']['code'])->toBe('BOL');
});

it('keeps the code once created, and closes a branch', function () {
    $branch = Branch::factory()->create(['code' => 'ARA']);

    $this->actingAs($this->admin)
        ->put(route('admin.branches.update', $branch), ['code' => 'NEW', 'name_am' => 'አራዳ', 'name_en' => 'Arada', 'is_active' => false])
        ->assertRedirect();

    expect($branch->refresh()->code)->toBe('ARA')
        ->and($branch->name_en)->toBe('Arada')
        ->and($branch->is_active)->toBeFalse()
        ->and(Activity::query()->where('event', AuditEvent::BranchUpdated->value)->sole()->properties['old']['is_active'])->toBeTrue();
});

it('validates the branch', function (array $input, string $field) {
    Branch::factory()->create(['code' => 'BOL']);

    $this->actingAs($this->admin)
        ->post(route('admin.branches.store'), ['code' => 'NEW', 'name_am' => 'አ', 'name_en' => 'A', ...$input])
        ->assertSessionHasErrors($field);
})->with([
    'taken code' => [['code' => 'bol'], 'code'],
    'code with a hyphen' => [['code' => 'B-1'], 'code'],
    'too long a code' => [['code' => 'BOLESUB'], 'code'],
    'one letter' => [['code' => 'B'], 'code'],
    'no Amharic name' => [['name_am' => ''], 'name_am'],
]);

describe('staff', function () {
    it('opens a staff account at a branch', function () {
        Notification::fake();
        $branch = Branch::factory()->create(['code' => 'BOL']);

        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), ['name' => 'Demo Approver', 'email' => 'demo.approver@example.test', 'roles' => [Role::Approver->value], 'branch_id' => $branch->id])
            ->assertRedirect();

        expect(User::query()->where('email', 'demo.approver@example.test')->sole()->branch_id)->toBe($branch->id)
            ->and(Activity::query()->where('event', AuditEvent::UserCreated->value)->sole()->properties['attributes']['branch'])->toBe('BOL');
    });

    it('moves a member of staff to another branch or to the head office, and audits it', function () {
        $bole = Branch::factory()->create(['code' => 'BOL']);
        $approver = staffUser(Role::Approver);

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $approver), ['name' => $approver->name, 'branch_id' => $bole->id])
            ->assertRedirect();

        expect($approver->refresh()->branch_id)->toBe($bole->id);

        $this->actingAs($this->admin)->put(route('admin.users.update', $approver), ['name' => $approver->name, 'branch_id' => ''])->assertRedirect();

        expect($approver->refresh()->branch_id)->toBeNull();

        $entries = Activity::query()->where('event', AuditEvent::UserBranchChanged->value)->orderBy('id')->get();
        expect($entries)->toHaveCount(2)
            ->and($entries[0]->properties['attributes'])->toBe(['branch' => 'BOL'])
            ->and($entries[1]->properties['old'])->toBe(['branch' => 'BOL']);
    });

    it('shows the branch in the list and offers the open branches', function () {
        $bole = Branch::factory()->create(['code' => 'BOL']);
        $closed = Branch::factory()->closed()->create();
        $approver = staffUser(Role::Approver);
        $approver->forceFill(['branch_id' => $bole->id])->save();

        $this->actingAs($this->admin)
            ->get(route('admin.users.edit', $approver))
            ->assertInertia(fn (Assert $page) => $page
                ->where('user.branch.code', 'BOL')
                ->where('user.is_staff', true)
                ->has('branches', 1)
                ->where('branches.0.id', $bole->id));

        expect($closed->is_active)->toBeFalse();
    });

    it('places nobody at a closed branch, and no applicant at any', function () {
        $closed = Branch::factory()->closed()->create();
        $open = Branch::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', staffUser(Role::Cashier)), ['name' => 'Demo Cashier', 'branch_id' => $closed->id])
            ->assertSessionHasErrors('branch_id');

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', applicantWithOrganization()), ['name' => 'Demo Applicant', 'branch_id' => $open->id])
            ->assertForbidden();

        $this->actingAs(staffUser(Role::Approver))
            ->put(route('admin.users.update', staffUser(Role::Cashier)), ['name' => 'Demo Cashier', 'branch_id' => $open->id])
            ->assertForbidden();
    });
});

describe('permit numbers', function () {
    beforeEach(function () {
        withFeeSchedule();
        $this->at = EthiopianCalendar::toGregorian(2019, 5, 1)->setTime(9, 0);
        $this->numbers = app(PermitNumberGenerator::class);
    });

    it('puts the branch code in the number and leaves it out for the head office', function () {
        $bole = Branch::factory()->create(['code' => 'BOL']);

        expect($this->numbers->next($this->at, $bole))->toBe('AACRA-BOL-2019-000001')
            ->and($this->numbers->next($this->at))->toBe('AACRA-2019-000002')
            // One counter for all branches: a number is never repeated.
            ->and($this->numbers->next($this->at, Branch::factory()->create(['code' => 'YEK'])))->toBe('AACRA-YEK-2019-000003');
    });

    it('follows a rule that puts the branch elsewhere, or nowhere', function () {
        $bole = Branch::factory()->create(['code' => 'BOL']);

        config(['permits.number_format' => '{branch}-{year}-{seq}']);
        expect($this->numbers->next($this->at, $bole))->toBe('BOL-2019-000001')
            ->and($this->numbers->next($this->at))->toBe('2019-000002');

        config(['permits.number_format' => '{prefix}-{year}-{seq}']);
        expect($this->numbers->next($this->at, $bole))->toBe('AACRA-2019-000003');
    });

    it('numbers an approved permit with the branch it was applied at, on the document too', function () {
        $bole = Branch::factory()->create(['code' => 'BOL', 'name_am' => 'ቦሌ ቅርንጫፍ', 'name_en' => 'Bole branch']);
        // A head-office approver: the number follows the application, not the approver.
        $approver = userWithRole(Role::Approver);
        $applicant = applicantWithOrganization();
        $permit = Permit::factory()
            ->forVehicles(Vehicle::factory()->for($applicant->organization)->create())
            ->recommended()
            ->create(['branch_id' => $bole->id]);
        $year = EthiopianCalendar::toEthiopian(CarbonImmutable::now(config('localization.display_timezone')))->year;

        $approved = app(ApprovePermit::class)->handle($permit, $approver);

        expect($approved->permit_number)->toBe("AACRA-BOL-{$year}-000001")
            ->and($approved->refresh()->branch_id)->toBe($bole->id)
            ->and(PermitDocument::html($approved, str_repeat('a', 43)))->toContain('ቦሌ ቅርንጫፍ')
            ->and(PermitDocument::html($approved, str_repeat('a', 43), 'en'))->toContain('Bole branch');
    });

    it('accepts {branch} in the number rule, but the counter only once', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'codes'), settingsForm('codes', ['permits.number_format' => '{year}-{branch}-{seq}']))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->put(route('admin.settings.update', 'codes'), settingsForm('codes', ['permits.number_format' => '{year}-{seq}-{seq}']))
            ->assertSessionHasErrors('permits__number_format');
    });
});

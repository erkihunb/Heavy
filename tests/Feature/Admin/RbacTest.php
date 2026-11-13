<?php

use App\Domain\Audit\AuditEvent;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Permit;
use App\Models\Role as RoleModel;
use App\Support\Rbac\BranchScope;
use App\Support\Rbac\PermissionCatalog;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * Roles, permissions and branch scopes (EUISIS RBAC): roles are editable on
 * the Roles screen, the permission catalogue is read-only and equal to the
 * code's, and a member of staff sees applications only where their scope
 * rows say (fail closed), unless a role applies everywhere.
 */

beforeEach(function () {
    $this->admin = staffUser(Role::Admin);
});

it('keeps the catalogue equal to the permissions the code checks', function () {
    $catalogue = PermissionCatalog::names();
    sort($catalogue);
    $enum = array_map(fn (Permission $permission): string => $permission->value, Permission::cases());
    sort($enum);

    expect($catalogue)->toBe($enum);
});

it('labels every permission in both languages', function (string $locale) {
    // Keyed by the permission name, which has a dot: read the array, as PermissionCatalog does.
    $labels = (array) trans('rbac.permission_labels', [], $locale);
    $descriptions = (array) trans('rbac.permission_descriptions', [], $locale);

    foreach (PermissionCatalog::names() as $name) {
        expect($labels[$name] ?? null)->toBeString()->not->toBeEmpty()
            ->and($descriptions[$name] ?? null)->toBeString();
    }
})->with(['en', 'am']);

it('creates, edits and deletes a role, and audits each', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.roles.store'), [
            'name' => 'night_desk',
            'name_am' => 'የሌሊት ዴስክ',
            'scope_type' => 'branch',
            'permissions' => ['permits.view-queue', 'permits.comment'],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $role = RoleModel::findByName('night_desk');
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['permits.comment', 'permits.view-queue'])
        ->and($role->is_system)->toBeFalse();

    $this->actingAs($this->admin)
        ->put(route('admin.roles.update', $role), [
            'name' => 'night_desk',
            'name_am' => 'የሌሊት ዴስክ',
            'scope_type' => 'branch',
            'permissions' => ['permits.view-queue'],
        ])
        ->assertRedirect();

    expect($role->refresh()->permissions->pluck('name')->all())->toBe(['permits.view-queue']);

    $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $role))->assertRedirect(route('admin.roles.index'));

    expect(RoleModel::query()->where('name', 'night_desk')->exists())->toBeFalse()
        ->and(Activity::query()->whereIn('event', [AuditEvent::RoleCreated->value, AuditEvent::RoleUpdated->value, AuditEvent::RoleDeleted->value])->count())->toBe(3);
});

it('never deletes a default role or one somebody holds', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.roles.destroy', RoleModel::findByName('reviewer')))
        ->assertSessionHas('warning');

    $held = RoleModel::query()->create(['name' => 'helper', 'guard_name' => 'web']);
    staffUser(Role::Registrar)->assignRole('helper');

    $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $held))->assertSessionHas('warning');

    expect(RoleModel::query()->whereIn('name', ['reviewer', 'helper'])->count())->toBe(2);
});

it('lists the permission catalogue and who holds each', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.permissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/permissions/index')
            ->has('permissions', count(PermissionCatalog::names())));

    $this->actingAs($this->admin)
        ->get(route('admin.permissions.show', 'payments.verify'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/permissions/show'));

    $this->actingAs($this->admin)->get(route('admin.permissions.show', 'nope.nothing'))->assertNotFound();
});

it('keeps roles and permissions screens from people without the permission', function () {
    $reviewer = staffUser(Role::Reviewer);

    $this->actingAs($reviewer)->get(route('admin.roles.index'))->assertForbidden();
    $this->actingAs($reviewer)->get(route('admin.permissions.index'))->assertForbidden();
});

it('adds and removes branch scope rows, and fails closed without one', function () {
    $bole = Branch::factory()->create(['code' => 'BOL']);
    $yeka = Branch::factory()->create(['code' => 'YEK']);
    $atBole = Permit::factory()->submitted()->create(['branch_id' => $bole->id]);
    Permit::factory()->submitted()->create(['branch_id' => $yeka->id]);
    $reviewer = staffUser(Role::Reviewer);
    $reviewer->branchScopes()->delete();

    $ids = fn () => collect($this->actingAs($reviewer->refresh())->get(route('staff.permits.index'))->viewData('page')['props']['permits']['data'])->pluck('id')->all();

    // No row: nothing.
    expect($ids())->toBe([]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.branch-scopes.store', $reviewer), ['branches' => [(string) $bole->id]])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($ids())->toBe([$atBole->id]);

    $row = $reviewer->branchScopes()->sole();
    $this->actingAs($this->admin)->delete(route('admin.users.branch-scopes.destroy', [$reviewer, $row]))->assertRedirect();

    expect($ids())->toBe([])
        ->and(Activity::query()->where('event', AuditEvent::UserScopesChanged->value)->count())->toBe(2);
});

it('lets a global role see every branch without scope rows', function () {
    Permit::factory()->submitted()->create(['branch_id' => Branch::factory()->create()->id]);
    $role = RoleModel::query()->create(['name' => 'inspector', 'guard_name' => 'web', 'scope_type' => RoleModel::SCOPE_GLOBAL]);
    $role->givePermissionTo('permits.view-queue');
    $user = staffUser(Role::Registrar);
    $user->branchScopes()->delete();
    $user->assignRole('inspector');

    expect($user->refresh()->branchScope())->toEqual(BranchScope::everywhere());
});

it('decides who is staff by the permissions of their roles, not by a role\'s name', function () {
    $clerk = RoleModel::query()->create(['name' => 'clerk', 'guard_name' => 'web']);
    $user = userWithRole(Role::Registrar);
    $user->syncRoles(['clerk']);

    // A created role that opens nothing of the office's work makes nobody staff…
    expect($user->refresh()->isStaff())->toBeFalse();

    // …the dashboard alone does not either…
    $clerk->givePermissionTo('dashboard.view');
    expect($user->refresh()->isStaff())->toBeFalse();

    // …any permission of the office's work does.
    $clerk->givePermissionTo('applicants.register');
    expect($user->refresh()->isStaff())->toBeTrue();
});

<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the application and run against the dedicated PostgreSQL
| test database (see phpunit.xml). Roles and permissions are reference data
| every deployment has, so they are seeded before each feature test.
|
| Unit tests are pure PHP: they do not boot the framework or touch the database.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A verified user holding the given role. Staff need confirmed two-factor
 * authentication to get past RequireTwoFactorForStaff.
 */
function userWithRole(Role $role, bool $twoFactor = false): User
{
    $factory = $twoFactor ? User::factory()->withTwoFactor() : User::factory();
    $user = $factory->create();
    $user->assignRole($role->value);

    return $user;
}

/**
 * An applicant who owns an organization.
 *
 * @param  array<string, mixed>  $organization  attribute overrides for the organization
 */
function applicantWithOrganization(array $organization = []): User
{
    $user = userWithRole(Role::Applicant);

    $created = Organization::factory()->create(['created_by' => $user->id, ...$organization]);
    $user->forceFill(['organization_id' => $created->id])->save();

    return $user->refresh();
}

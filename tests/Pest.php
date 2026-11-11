<?php

use App\Data\PermitData;
use App\Domain\Permits\ValidityRules;
use App\Domain\Settings\SettingsRegistry;
use App\Domain\Settings\SystemSettings;
use App\Enums\PermitType;
use App\Enums\Role;
use App\Models\FeeSchedule;
use App\Models\Organization;
use App\Models\Permit;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
 * A member of staff who has confirmed two-factor authentication, as the
 * back office requires.
 */
function staffUser(Role $role): User
{
    return userWithRole($role, twoFactor: true);
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

/**
 * The form fields of a valid application for the applicant's organization.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function permitPayload(User $applicant, array $overrides = []): array
{
    $today = ValidityRules::today();

    return [
        'permit_type' => PermitType::FullDay->value,
        'primary_vehicle_id' => array_key_exists('primary_vehicle_id', $overrides)
            ? $overrides['primary_vehicle_id']
            : Vehicle::factory()->create(['organization_id' => $applicant->organization_id])->id,
        'trailer_vehicle_ids' => [],
        'valid_from' => $today->addDays(3)->toDateString(),
        'valid_to' => $today->addDays(12)->toDateString(),
        'reason' => 'የግንባታ ዕቃ ማጓጓዝ',
        ...$overrides,
    ];
}

/**
 * Validated application details, as a FormRequest would hand them to an Action.
 *
 * @param  list<Vehicle>  $trailers
 */
function permitData(Vehicle $primary, array $trailers = [], ?string $from = null, ?string $to = null): PermitData
{
    $today = ValidityRules::today();

    return new PermitData(
        permitType: PermitType::Pass,
        primaryVehicleId: $primary->id,
        trailerVehicleIds: array_map(fn (Vehicle $trailer): string => $trailer->id, $trailers),
        validFrom: CarbonImmutable::parse($from ?? $today->addDays(3)->toDateString(), 'UTC'),
        validTo: CarbonImmutable::parse($to ?? $today->addDays(12)->toDateString(), 'UTC'),
        reason: 'የግንባታ ዕቃ ማጓጓዝ',
    );
}

/**
 * A fee schedule that prices every permit, so that approval can snapshot a
 * fee. Tests about the schedule itself build their own lines instead.
 */
function withFeeSchedule(int $cents = 100_000): void
{
    foreach (PermitType::cases() as $type) {
        FeeSchedule::factory()->forType($type)->amount($cents)->create();
    }
}

/**
 * The vehicle rows of a permit as the database holds them, primary first:
 * vehicle_id, role, is_blocking and the first and last day of `period`.
 *
 * @return Collection<int, stdClass>
 */
function permitVehicleRows(Permit $permit): Collection
{
    return DB::table('permit_vehicles')
        ->where('permit_id', $permit->id)
        ->orderBy('role')
        ->orderBy('vehicle_id')
        ->get([
            'vehicle_id',
            'role',
            'is_blocking',
            'period_from',
            'period_to',
        ])
        // MySQL returns booleans as 0 / 1.
        ->map(function (stdClass $row): stdClass {
            $row->is_blocking = (bool) $row->is_blocking;

            return $row;
        });
}

/**
 * The System settings form of a section with its current values, changed by
 * $changes (keyed by setting key or form field). Secrets and uploaded files
 * are left out, as the browser leaves them out when nothing was typed or chosen.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function settingsForm(string $section, array $changes = []): array
{
    $form = [];

    foreach (app(SystemSettings::class)->current($section) as $key => $value) {
        if (in_array(SettingsRegistry::all()[$key]['type'], ['secret', 'image'], true)) {
            continue;
        }

        $form[SettingsRegistry::field($key)] = $value;
    }

    foreach ($changes as $key => $value) {
        $form[str_contains($key, '__') ? $key : SettingsRegistry::field($key)] = $value;
    }

    return $form;
}

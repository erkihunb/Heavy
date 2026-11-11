<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

/**
 * One demo account per role, for local development only.
 *
 * Password for every account: "password". The demo applicant owns a demo organization. Staff accounts have no two-factor
 * secret, so they are sent to the 2FA setup screen on first sign-in.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException('Demo users may only be seeded in the local environment.');
        }

        foreach (Role::cases() as $role) {
            $user = User::query()->where('email', "{$role->value}@example.test")->first()
                ?? User::factory()->create([
                    'name' => 'Demo '.str($role->value)->replace('_', ' ')->title(),
                    'email' => "{$role->value}@example.test",
                ]);

            $user->syncRoles([$role->value]);

            if ($role === Role::Applicant && $user->organization_id === null) {
                $organization = Organization::factory()->create([
                    'name_am' => 'የማሳያ ተቋም',
                    'name_en' => 'Demo Organisation',
                    'created_by' => $user->id,
                ]);

                $user->forceFill(['organization_id' => $organization->id])->save();
            }
        }
    }
}

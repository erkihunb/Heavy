<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

/**
 * One demo account per role, for local development only.
 *
 * Password for every account: "password". Staff accounts have no two-factor
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
        }
    }
}

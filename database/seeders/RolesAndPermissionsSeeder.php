<?php

namespace Database\Seeders;

use App\Support\Rbac\RbacSynchronizer;
use Illuminate\Database\Seeder;

/**
 * Creates the permissions and default roles from the code, without undoing
 * what administrators changed on the Roles screen. Safe to run on every
 * deploy (App\Support\Rbac\RbacSynchronizer).
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(RbacSynchronizer $synchronizer): void
    {
        $synchronizer->sync();
    }
}

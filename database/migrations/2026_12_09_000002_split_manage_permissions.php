<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Every action by its own permission (owner's decision 2026-11-20): the
 * "manage" permissions become see, add, change and delete. A role that held
 * the old one gets all of the new ones, so nobody loses anything on deploy;
 * administrators narrow them on the Roles screen. The old ones are removed.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> old => new */
    private const array SPLIT = [
        'vehicle-types.manage' => ['vehicle-types.view', 'vehicle-types.create', 'vehicle-types.update', 'vehicle-types.delete'],
        'fee-schedules.manage' => ['fee-schedules.view', 'fee-schedules.create', 'fee-schedules.update', 'fee-schedules.delete'],
        'settings.manage' => ['settings.view', 'settings.update', 'branches.view', 'branches.create', 'branches.update', 'branches.delete'],
    ];

    /** @var array<string, list<string>> kept => added */
    private const array ADD = [
        'institution-permits.issue' => ['institution-vehicles.view', 'institution-vehicles.create', 'institution-vehicles.update', 'institution-vehicles.delete'],
    ];

    public function up(): void
    {
        foreach ([...self::SPLIT, ...self::ADD] as $old => $new) {
            $this->grant($old, $new);
        }

        DB::table('permissions')->whereIn('name', array_keys(self::SPLIT))->where('guard_name', 'web')->delete();
        app('cache')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        foreach (self::SPLIT as $old => $new) {
            $this->grant($new[0], [$old]);
        }

        DB::table('permissions')
            ->whereIn('name', array_merge(...array_values(self::SPLIT), ...array_values(self::ADD)))
            ->where('guard_name', 'web')
            ->delete();
        app('cache')->forget(config('permission.cache.key'));
    }

    /**
     * @param  list<string>  $names
     */
    private function grant(string $from, array $names): void
    {
        $now = now();
        $roles = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', $from)
            ->distinct()
            ->pluck('role_has_permissions.role_id');
        $users = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('permissions.name', $from)
            ->get(['model_has_permissions.model_type', 'model_has_permissions.model_id']);

        foreach ($names as $name) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);

            foreach ($roles as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
            }

            foreach ($users as $user) {
                DB::table('model_has_permissions')->insertOrIgnore(['permission_id' => $id, 'model_type' => $user->model_type, 'model_id' => $user->model_id]);
            }
        }
    }
};

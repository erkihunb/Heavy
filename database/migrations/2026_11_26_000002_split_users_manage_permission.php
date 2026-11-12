<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * `users.manage` becomes the finer permissions of the user, role and
 * permission screens (App\Support\Rbac\PermissionCatalog). Every role that
 * held it gets all of them, so nobody gains or loses access on deploy.
 */
return new class extends Migration
{
    private const array REPLACEMENTS = [
        'users.view',
        'users.create',
        'users.update',
        'users.deactivate',
        'users.assign-roles',
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'permissions.view',
    ];

    public function up(): void
    {
        $old = DB::table('permissions')->where('name', 'users.manage')->where('guard_name', 'web')->value('id');
        $now = now();
        $ids = [];

        foreach (self::REPLACEMENTS as $name) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            $ids[] = $id ?? DB::table('permissions')->insertGetId([
                'name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if ($old !== null) {
            foreach (DB::table('role_has_permissions')->where('permission_id', $old)->pluck('role_id') as $roleId) {
                foreach ($ids as $id) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
                }
            }

            foreach (DB::table('model_has_permissions')->where('permission_id', $old)->get() as $grant) {
                foreach ($ids as $id) {
                    DB::table('model_has_permissions')->insertOrIgnore([
                        'permission_id' => $id, 'model_type' => $grant->model_type, 'model_id' => $grant->model_id,
                    ]);
                }
            }

            DB::table('permissions')->where('id', $old)->delete();
        }

        app('cache')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $now = now();
        $id = DB::table('permissions')->where('name', 'users.manage')->where('guard_name', 'web')->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => 'users.manage', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
            ]);
        $viewId = DB::table('permissions')->where('name', 'users.view')->value('id');

        if ($viewId !== null) {
            foreach (DB::table('role_has_permissions')->where('permission_id', $viewId)->pluck('role_id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
            }
        }

        DB::table('permissions')->whereIn('name', self::REPLACEMENTS)->delete();
        app('cache')->forget(config('permission.cache.key'));
    }
};

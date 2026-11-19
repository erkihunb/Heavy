<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Correcting and deleting a request registered at the office get permissions
 * of their own (`permits.update-registered`, `permits.delete-registered`,
 * owner's decision 2026-11-19: every action by its own permission). Every
 * role that registers requests today gets both, so nobody loses the work on
 * deploy; administrators take them away on the Roles screen.
 */
return new class extends Migration
{
    private const array PERMISSIONS = ['permits.update-registered', 'permits.delete-registered'];

    public function up(): void
    {
        $now = now();
        $roles = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', 'applicants.register')
            ->distinct()
            ->pluck('role_has_permissions.role_id');

        foreach (self::PERMISSIONS as $name) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
                ]);

            foreach ($roles as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
            }
        }

        app('cache')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();
        app('cache')->forget(config('permission.cache.key'));
    }
};

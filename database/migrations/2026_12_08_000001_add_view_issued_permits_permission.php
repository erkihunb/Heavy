<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Seeing issued permits gets a permission of its own (`permits.view-issued`,
 * owner's decision 2026-11-19). Every role that sees permits today — the
 * review queue or the Registrar's work — gets it, so nobody loses sight of
 * them on deploy; administrators take it away on the Roles screen.
 */
return new class extends Migration
{
    private const string PERMISSION = 'permits.view-issued';

    private const array SEES_PERMITS = ['permits.view-queue', 'applicants.register'];

    public function up(): void
    {
        $now = now();
        $id = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => self::PERMISSION, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
            ]);

        $roles = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereIn('permissions.name', self::SEES_PERMITS)
            ->distinct()
            ->pluck('role_has_permissions.role_id');

        foreach ($roles as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
        }

        app('cache')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->delete();
        app('cache')->forget(config('permission.cache.key'));
    }
};

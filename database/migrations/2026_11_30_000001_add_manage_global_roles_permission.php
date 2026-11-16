<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Making global roles and changing the administrator role were decided by
 * holding the role named `admin`; they now have a permission of their own
 * (`roles.manage-global`). The administrator role gets it here, so nobody
 * loses anything on deploy; RbacSynchronizer keeps it there afterwards.
 */
return new class extends Migration
{
    private const string PERMISSION = 'roles.manage-global';

    public function up(): void
    {
        $now = now();
        $id = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => self::PERMISSION, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
            ]);

        $admin = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->value('id');

        if ($admin !== null) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $admin]);
        }

        app('cache')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->delete();
        app('cache')->forget(config('permission.cache.key'));
    }
};

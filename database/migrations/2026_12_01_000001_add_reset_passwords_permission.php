<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Resetting someone's password or two-factor (`users.reset-passwords`) is
 * new. The administrator role gets it here, so it works right after deploy;
 * RbacSynchronizer keeps it there afterwards, and other roles get it on the
 * Roles screen.
 */
return new class extends Migration
{
    private const string PERMISSION = 'users.reset-passwords';

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

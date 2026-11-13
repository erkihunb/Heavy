<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The applicant portal is gone (applicants have no pages and cannot sign in),
 * so the permissions that opened it control nothing: they are removed, with
 * every grant of them. `permits.apply` stays: it marks an applicant's account.
 * Going back recreates them and gives them to the default applicant role.
 */
return new class extends Migration
{
    private const array PERMISSIONS = [
        'organization.manage-own',
        'vehicles.manage-own',
        'payments.submit',
        'permits.download-own',
    ];

    public function up(): void
    {
        $ids = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app('cache')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $now = now();
        $applicant = DB::table('roles')->where('name', 'applicant')->where('guard_name', 'web')->value('id');

        foreach (self::PERMISSIONS as $name) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
                ]);

            if ($applicant !== null) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $applicant]);
            }
        }

        app('cache')->forget(config('permission.cache.key'));
    }
};

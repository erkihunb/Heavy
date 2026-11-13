<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The dashboard gets a permission of its own (`dashboard.view`). Every role
 * that does any of the office's work — holds a permission other than the
 * applicant's own — gets it, so nobody loses their dashboard on deploy;
 * administrators can take it away again on the Roles screen. Applicants'
 * roles do not get it: applicants have no pages of their own.
 */
return new class extends Migration
{
    private const string PERMISSION = 'dashboard.view';

    private const array APPLICANT_ONLY = [
        'organization.manage-own',
        'vehicles.manage-own',
        'permits.apply',
        'payments.submit',
        'permits.download-own',
    ];

    public function up(): void
    {
        $now = now();
        $id = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', 'web')->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => self::PERMISSION, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
            ]);

        $roles = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereNotIn('permissions.name', self::APPLICANT_ONLY)
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

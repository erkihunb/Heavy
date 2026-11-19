<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Every action by its own permission (owner's decision 2026-11-20), part two:
 * organizations and their vehicles get a register of their own, and seeing
 * payments, seeing the Authority's own permits and revoking one are no longer
 * part of another permission. A role gets each new permission when it holds
 * the one that covered it until now, so nobody loses anything on deploy.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> held today => granted too */
    private const array GRANT = [
        'applicants.register' => ['organizations.view', 'organizations.update', 'vehicles.update'],
        'permits.delete-registered' => ['organizations.delete', 'vehicles.delete'],
        'payments.verify' => ['payments.view'],
        'institution-permits.issue' => ['institution-permits.view', 'institution-permits.revoke'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::GRANT as $from => $names) {
            $roles = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('permissions.name', $from)
                ->distinct()
                ->pluck('role_has_permissions.role_id');

            foreach ($names as $name) {
                $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id')
                    ?? DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);

                foreach ($roles as $roleId) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
                }
            }
        }

        app('cache')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', array_merge(...array_values(self::GRANT)))->where('guard_name', 'web')->delete();
        app('cache')->forget(config('permission.cache.key'));
    }
};

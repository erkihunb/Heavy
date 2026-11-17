<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The Authority's own vehicles move without paying the fee. They are
 * registered once under the Authority's own organization
 * (`organizations.is_institution`), and whoever holds
 * `institution-permits.issue` gives each a permit directly
 * (`permits.is_institutional`): no review, approval or payment, a paper of
 * its own, and the same QR check on the road. The administrator role gets
 * the permission here; RbacSynchronizer keeps it there afterwards.
 */
return new class extends Migration
{
    private const string PERMISSION = 'institution-permits.issue';

    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('is_institution')->default(false)->after('address');
        });

        Schema::table('permits', function (Blueprint $table) {
            $table->boolean('is_institutional')->default(false)->after('permit_type')->index();
            // Where the vehicle goes, when the issuer says; an institution vehicle may move anywhere.
            $table->string('route', 255)->nullable()->after('reason');
        });

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

        Schema::table('permits', function (Blueprint $table) {
            $table->dropIndex(['is_institutional']);
            $table->dropColumn(['is_institutional', 'route']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('is_institution');
        });
    }
};

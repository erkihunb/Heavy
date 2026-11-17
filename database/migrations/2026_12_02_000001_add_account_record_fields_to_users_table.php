<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The full staff record: the name in its three parts (given name, father's
 * name, grandfather's name; `name` stays the whole name everything else
 * reads), a username to sign in with besides the e-mail, where the person
 * sits in the institution, their preferences, and what the system records
 * about the account (who opened it, the last sign-in).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
            $table->string('username', 40)->nullable()->unique()->after('last_name');

            $table->string('institution', 160)->nullable()->after('employee_number');
            $table->string('department', 160)->nullable()->after('institution');
            $table->foreignId('manager_id')->nullable()->after('department')->constrained('users')->nullOnDelete();
            $table->string('employment_status', 20)->nullable()->after('manager_id');

            $table->string('timezone', 64)->nullable()->after('locale');
            $table->string('theme', 10)->nullable()->after('timezone');
            $table->json('notification_preferences')->nullable()->after('theme');

            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->foreignId('created_by')->nullable()->after('last_login_ip')->constrained('users')->nullOnDelete();
        });

        // Existing people: the whole name split at its spaces (given name, father's name, the rest).
        DB::table('users')->select(['id', 'name'])->orderBy('id')->chunkById(200, function ($users): void {
            foreach ($users as $user) {
                $parts = preg_split('/\s+/u', trim((string) $user->name), 3, PREG_SPLIT_NO_EMPTY) ?: [];

                DB::table('users')->where('id', $user->id)->update([
                    'first_name' => $parts[0] ?? null,
                    'middle_name' => $parts[1] ?? null,
                    'last_name' => $parts[2] ?? null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropUnique(['username']);
            $table->dropColumn([
                'first_name', 'middle_name', 'last_name', 'username', 'institution', 'department',
                'employment_status', 'timezone', 'theme', 'notification_preferences', 'last_login_at', 'last_login_ip',
            ]);
        });
    }
};

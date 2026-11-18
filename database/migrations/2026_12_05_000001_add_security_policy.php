<?php

use App\Domain\Settings\SystemSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Security as in EUISIS (System settings › Security):
 * - Two-factor authentication is a policy (on, required for everyone, or for
 *   chosen roles) instead of one switch for all staff, and an Admin can
 *   override it for one person: always required, or never.
 * - The last passwords of each person are kept (hashed) so they cannot be
 *   used again.
 * - The default password for new staff accounts is gone: a new person always
 *   gets an e-mail with a link to choose their password.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // null follows the policy; 'required' or 'exempt' is an Admin's choice for this person.
            $table->string('two_factor_requirement', 10)->nullable()->after('two_factor_confirmed_at');
        });

        Schema::create('password_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('password');
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'id']);
        });

        // The old switch for all staff becomes "Required for all staff" (on by default).
        $stored = DB::table('settings')->where('key', 'permits.staff_two_factor_required')->value('value');
        $required = $stored !== null
            ? json_decode((string) $stored, true) !== false
            : filter_var(env('PERMITS_STAFF_TWO_FACTOR_REQUIRED', true), FILTER_VALIDATE_BOOL);

        if (! $required) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'permits.security.mfa_required_for_all'],
                ['value' => json_encode(false), 'updated_at' => now()],
            );
        }

        DB::table('settings')
            ->whereIn('key', [
                'permits.staff_two_factor_required',
                'permits.security.default_password',
                'permits.security.login_per_minute',
            ])
            ->delete();

        Cache::forget(SystemSettings::CACHE_KEY);
    }

    public function down(): void
    {
        Schema::dropIfExists('password_histories');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('two_factor_requirement');
        });

        DB::table('settings')->where('key', 'permits.security.mfa_required_for_all')->delete();
    }
};

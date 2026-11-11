<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // An applicant belongs to at most one organization; staff have none.
            $table->foreignId('organization_id')->nullable()->after('email')->constrained()->restrictOnDelete();
            $table->string('phone', 20)->nullable()->after('organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('phone');
        });
    }
};

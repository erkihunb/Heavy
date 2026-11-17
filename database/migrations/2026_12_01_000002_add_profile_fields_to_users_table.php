<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * What an Admin records about a member of staff besides name and e-mail: a
 * photo, their job title and their staff number (unique when given). The
 * phone number and preferred language already have columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('phone');
            $table->string('job_title', 120)->nullable()->after('avatar_path');
            $table->string('employee_number', 40)->nullable()->unique()->after('job_title');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['employee_number']);
            $table->dropColumn(['avatar_path', 'job_title', 'employee_number']);
        });
    }
};

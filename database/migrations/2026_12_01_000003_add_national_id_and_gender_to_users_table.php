<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A member of staff's national ID (Fayda: the 16-digit FAN or the 12-digit
 * FIN, digits only, unique when given) and gender.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('national_id', 16)->nullable()->unique()->after('employee_number');
            $table->string('gender', 10)->nullable()->after('national_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['national_id']);
            $table->dropColumn(['national_id', 'gender']);
        });
    }
};

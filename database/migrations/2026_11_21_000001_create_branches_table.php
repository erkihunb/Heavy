<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Branch offices of the Authority. Staff belong to at most one branch; a
 * permit keeps the branch of the person who approved it, whose code can be
 * part of the permit number ({branch} in permits.number_format).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            // Printed in permit numbers, e.g. "BOL"; never changes once created.
            $table->string('code', 6)->unique();
            $table->string('name_am');
            $table->string('name_en');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
        });

        Schema::table('permits', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('permits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::dropIfExists('branches');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name_am');
            $table->string('name_en')->nullable();
            // Taxpayer identification number; optional, but unique when present.
            $table->string('tin', 20)->nullable()->unique();
            $table->string('phone', 20);
            $table->string('email');
            $table->string('address', 500);
            // The applicant who created the profile is its owner. An account is never
            // deleted while it owns an organization.
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_type_id')->constrained()->restrictOnDelete();
            // Exactly what was typed, kept for audit.
            $table->string('plate_raw', 50);
            // The identity of the vehicle: <region>-<code>-<number>, see App\Domain\Vehicles\PlateNumber.
            $table->string('plate_normalized', 30)->unique();
            $table->string('region_code', 8);
            $table->string('owner_name');
            $table->timestamps();

            // The register of one organization, newest first.
            $table->index(['organization_id', 'created_at']);
            $table->index('vehicle_type_id');
            $table->index('region_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

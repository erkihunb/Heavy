<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_vehicles', function (Blueprint $table) {
            $table->foreignUlid('permit_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('vehicle_id')->constrained()->restrictOnDelete();
            // App\Enums\VehicleRole
            $table->string('role', 10);
            // True only while the permit reserves the vehicle (awaiting payment … issued).
            $table->boolean('is_blocking')->default(false);

            $table->primary(['permit_id', 'vehicle_id']);
            $table->index('vehicle_id');
        });

        // The validity of the permit, copied here so the database itself can
        // compare the periods of two permits for the same vehicle.
        DB::statement('ALTER TABLE permit_vehicles ADD COLUMN period daterange NOT NULL');

        // Exactly one primary vehicle per permit (trailers are not limited here).
        DB::statement("CREATE UNIQUE INDEX permit_vehicles_one_primary ON permit_vehicles (permit_id) WHERE role = 'primary'");

        // Spec §4.1: a vehicle may not be on two blocking permits whose periods
        // overlap. btree_gist lets the vehicle id take part in a GiST index.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement(
            'ALTER TABLE permit_vehicles ADD CONSTRAINT permit_vehicles_no_overlap '.
            'EXCLUDE USING gist (vehicle_id WITH =, period WITH &&) WHERE (is_blocking)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_vehicles');
    }
};

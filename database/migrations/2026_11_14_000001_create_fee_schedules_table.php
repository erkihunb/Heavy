<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('permit_type', 20);
            // null: the fee applies to every vehicle type that has no fee of its own.
            $table->foreignId('vehicle_type_id')->nullable()->constrained()->restrictOnDelete();
            // The duration band in days, both ends included.
            $table->unsignedSmallInteger('min_days');
            $table->unsignedSmallInteger('max_days');
            // Santim (1 ETB = 100 santim). Never a float.
            $table->unsignedBigInteger('amount_cents');
            // Gregorian calendar dates, both included; no end date = until replaced.
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['permit_type', 'effective_from']);
            $table->index('vehicle_type_id');
        });

        DB::statement('ALTER TABLE fee_schedules ADD CONSTRAINT fee_schedules_days_check CHECK (min_days >= 1 AND max_days >= min_days)');
        DB::statement('ALTER TABLE fee_schedules ADD CONSTRAINT fee_schedules_effective_check CHECK (effective_to IS NULL OR effective_to >= effective_from)');
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_schedules');
    }
};

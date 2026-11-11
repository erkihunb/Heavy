<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The overlap condition shared by both triggers: another reserving row
     * for the same vehicle whose days intersect the new row's (both ends included).
     */
    private const string OVERLAP = <<<'SQL'
        NEW.is_blocking AND EXISTS (
            SELECT 1 FROM permit_vehicles other
            WHERE other.vehicle_id = NEW.vehicle_id
              AND other.permit_id <> NEW.permit_id
              AND other.is_blocking
              AND other.period_from <= NEW.period_to
              AND other.period_to >= NEW.period_from
        )
        SQL;

    public function up(): void
    {
        // A failed earlier run may have left the table behind (DDL is not transactional in MySQL).
        Schema::dropIfExists('permit_vehicles');

        Schema::create('permit_vehicles', function (Blueprint $table) {
            $table->foreignUlid('permit_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('vehicle_id')->constrained()->restrictOnDelete();
            // App\Enums\VehicleRole
            $table->string('role', 10);
            // The validity of the permit, copied here so the database itself can
            // compare the periods of two permits for the same vehicle.
            $table->date('period_from');
            $table->date('period_to');
            // True only while the permit reserves the vehicle (awaiting payment … issued).
            $table->boolean('is_blocking')->default(false);
            // 1 for the primary vehicle, NULL for trailers. A unique index ignores
            // NULLs, so this allows exactly one primary and any number of trailers.
            $table->unsignedTinyInteger('primary_marker')
                ->nullable()
                ->virtualAs("case when role = 'primary' then 1 end");

            $table->primary(['permit_id', 'vehicle_id']);
            $table->unique(['permit_id', 'primary_marker'], 'permit_vehicles_one_primary');
            $table->index(['vehicle_id', 'is_blocking', 'period_from', 'period_to'], 'permit_vehicles_overlap_index');
        });

        // Spec §4.1: a vehicle may not be on two blocking permits whose periods
        // overlap. MySQL has no exclusion constraints, so two triggers refuse
        // such a row. ApprovePermit additionally locks the vehicle rows, which
        // is what makes the rule hold under concurrent approvals.
        DB::unprepared(
            'CREATE TRIGGER permit_vehicles_no_overlap_insert BEFORE INSERT ON permit_vehicles FOR EACH ROW '.
            'BEGIN IF '.self::OVERLAP." THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'permit_vehicles_no_overlap'; END IF; END"
        );

        DB::unprepared(
            'CREATE TRIGGER permit_vehicles_no_overlap_update BEFORE UPDATE ON permit_vehicles FOR EACH ROW '.
            'BEGIN IF '.self::OVERLAP." THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'permit_vehicles_no_overlap'; END IF; END"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_vehicles');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The audit log (spec §15), in the shape spatie/laravel-activitylog expects.
 * Subjects and causers are referenced by string ids because permits,
 * vehicles and payments have ULIDs while users have integers. Rows are only
 * ever added: database triggers refuse updates and deletes, so not even the
 * application can rewrite history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 36)->nullable();
            $table->string('event')->nullable();
            $table->string('causer_type')->nullable();
            $table->string('causer_id', 36)->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();

            $table->index('log_name');
            $table->index(['subject_type', 'subject_id'], 'subject');
            $table->index(['causer_type', 'causer_id'], 'causer');
            $table->index(['event', 'id']);
            $table->index('created_at');
        });

        foreach (['UPDATE', 'DELETE'] as $event) {
            DB::unprepared(
                'CREATE TRIGGER activity_log_no_'.strtolower($event).
                " BEFORE {$event} ON activity_log FOR EACH ROW ".
                "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'activity_log is append-only'"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('permit_id')->constrained()->restrictOnDelete();
            // Null for the first row, written when the draft is created.
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            // Null when the system made the change (for example expiry).
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at');

            $table->index(['permit_id', 'id']);
            $table->index('actor_id');
        });

        // The history is an audit trail: rows are only ever added.
        foreach (['UPDATE', 'DELETE'] as $event) {
            DB::unprepared(
                'CREATE TRIGGER permit_status_transitions_no_'.strtolower($event).
                " BEFORE {$event} ON permit_status_transitions FOR EACH ROW ".
                "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'permit_status_transitions is append-only'"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_status_transitions');
    }
};

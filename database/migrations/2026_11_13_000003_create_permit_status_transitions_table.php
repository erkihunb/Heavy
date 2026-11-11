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
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION permit_status_transitions_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'permit_status_transitions is append-only'
                    USING ERRCODE = 'restrict_violation';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER permit_status_transitions_append_only
                BEFORE UPDATE OR DELETE ON permit_status_transitions
                FOR EACH ROW EXECUTE FUNCTION permit_status_transitions_append_only();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_status_transitions');
        DB::statement('DROP FUNCTION IF EXISTS permit_status_transitions_append_only()');
    }
};

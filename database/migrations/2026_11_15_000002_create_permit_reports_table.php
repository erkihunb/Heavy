<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A traffic officer's report of a violation or problem with a permit (spec §7:
        // "report violation / flag"). Append-only.
        Schema::create('permit_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('permit_id')->constrained()->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->text('note');
            // An explicit default stops MariaDB adding ON UPDATE CURRENT_TIMESTAMP.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['permit_id', 'created_at']);
            $table->index('reported_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_reports');
    }
};

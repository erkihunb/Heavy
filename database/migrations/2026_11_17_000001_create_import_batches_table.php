<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One run of the legacy import (spec §13): who ran it, on which file, and the totals.
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            // Path of the uploaded CSV on the private documents disk; null for a CLI run.
            $table->string('stored_path')->nullable();
            // App\Enums\ImportStatus
            $table->string('status', 20)->default('pending');
            $table->boolean('dry_run')->default(false);
            $table->json('totals')->nullable();
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('started_by');
            $table->index(['status', 'created_at']);
        });

        Schema::create('import_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            // Line number in the CSV, the header being line 1.
            $table->unsignedInteger('source_row');
            $table->string('field', 40);
            $table->text('raw_value')->nullable();
            $table->string('message', 500);
            // App\Enums\IssueSeverity: the row was skipped, or imported with this warning.
            $table->string('severity', 10);

            $table->index(['import_batch_id', 'source_row']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_issues');
        Schema::dropIfExists('import_batches');
    }
};

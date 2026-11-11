<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A spreadsheet export requested by a member of staff (spec §11), built on the queue.
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // App\Enums\ExportDataset, App\Enums\ExportFormat, App\Enums\ExportStatus
            $table->string('dataset', 20);
            $table->string('format', 10);
            $table->json('filters')->nullable();
            $table->string('status', 20)->default('pending');
            // On the private documents disk; deleted by exports:prune after 7 days.
            $table->string('path')->nullable();
            $table->unsignedInteger('rows')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};

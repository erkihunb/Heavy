<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Comments the head office leaves on an application. Internal: staff who can
 * see the application read them; the applicant never does. Append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('permit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['permit_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_comments');
    }
};

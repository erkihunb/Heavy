<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who an Approver sent an application to for an opinion. Append-only: a
 * request is never edited or taken back; a resubmitted application starts a
 * new round (see Permit::isCurrentReview).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_review_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('permit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['permit_id', 'reviewer_id']);
            $table->index(['reviewer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_review_requests');
    }
};

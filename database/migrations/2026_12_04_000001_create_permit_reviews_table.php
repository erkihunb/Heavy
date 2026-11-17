<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * What each Reviewer found when assessing an application: their
 * recommendation to the Approver and the written assessment behind it.
 * Several Reviewers may each leave one; the Approver reads them all before
 * deciding. Internal to staff and append-only: the applicant never sees it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('permit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('recommendation', 10);
            $table->text('assessment');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['permit_id', 'id']);
            $table->index('reviewer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_reviews');
    }
};

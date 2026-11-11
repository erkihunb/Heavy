<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // Assigned at approval: <prefix>-<Ethiopian year>-<sequence>.
            $table->string('permit_number', 32)->nullable()->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('applicant_id')->constrained('users')->restrictOnDelete();
            $table->string('permit_type', 20);
            // App\Enums\PermitStatus; "active" is computed from the dates and never stored.
            $table->string('status', 30)->default('draft');
            $table->text('reason');
            // Gregorian calendar dates; both days are included in the validity.
            $table->date('valid_from');
            $table->date('valid_to');

            // Snapshot of the fee at approval (Milestone 4), in santim.
            $table->unsignedBigInteger('fee_cents')->nullable();
            $table->char('currency', 3)->default('ETB');

            // The Reviewer's advice to the Approver; cleared when the application is resubmitted.
            $table->string('recommendation', 10)->nullable();
            $table->foreignId('recommended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('recommended_at')->nullable();
            $table->text('recommendation_note')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();

            // sha256 of the verification token (Milestone 5); the token itself is never stored.
            $table->char('verification_token_hash', 64)->nullable()->unique();
            $table->string('document_path')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['organization_id', 'status']);
            $table->index('valid_to');
            $table->index('submitted_at');
            $table->index('applicant_id');
            $table->index('recommended_by');
            $table->index('approved_by');
            $table->index('revoked_by');
        });

        DB::statement('ALTER TABLE permits ADD CONSTRAINT permits_valid_period_check CHECK (valid_from <= valid_to)');
    }

    public function down(): void
    {
        Schema::dropIfExists('permits');
    }
};

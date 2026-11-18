<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One request letter of an organization can ask for permits for many
 * vehicles. The letter is recorded once; each vehicle (with its trailers)
 * still gets a permit of its own — its own number, fee, certificate and QR
 * code — linked to the letter, so the office can review, decide, take the
 * payment for and print the whole letter together. A permit taken before
 * this, or given directly to one of the Authority's own vehicles, has none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_letters', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('applicant_id')->constrained('users')->restrictOnDelete();
            $table->string('letter_number', 64);
            $table->date('letter_date')->nullable();
            // The scanned letter, on the private documents disk; every permit of the letter refers to the same file.
            $table->string('letter_file_path')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });

        Schema::table('permits', function (Blueprint $table): void {
            $table->foreignUlid('request_letter_id')->nullable()->after('applicant_id')->constrained('request_letters')->nullOnDelete();
        });

        // One bank receipt may pay for all the approved vehicles of one letter: each
        // permit still has a payment of its own fee, under the same receipt.
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignUlid('request_letter_id')->nullable()->after('permit_id')->constrained('request_letters')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_receipt_number_unique');
            // A receipt on its own is unique as before; within a letter, each permit has one live payment
            // per receipt. That a letter's receipt pays for nothing else is checked by RecordLetterPayment.
            $table->string('receipt_scope', 26)
                ->virtualAs("case when request_letter_id is null then '' else permit_id end");
            $table->unique(['live_receipt_number', 'receipt_scope'], 'payments_receipt_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_receipt_number_unique');
            $table->dropColumn('receipt_scope');
            $table->unique('live_receipt_number', 'payments_receipt_number_unique');
            $table->dropConstrainedForeignId('request_letter_id');
        });

        Schema::table('permits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('request_letter_id');
        });

        Schema::dropIfExists('request_letters');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('permit_id')->constrained()->restrictOnDelete();
            // App\Enums\PaymentMethod
            $table->string('method', 20);
            // Normalised by App\Domain\Payments\ReceiptNumber.
            $table->string('receipt_number', 64);
            // Santim (1 ETB = 100 santim). Never a float.
            $table->unsignedBigInteger('amount_cents');
            // The calendar day written on the receipt (Gregorian).
            $table->date('paid_at');
            // Path on the private documents disk; the file name is random.
            $table->string('receipt_file_path')->nullable();
            // App\Enums\PaymentStatus
            $table->string('status', 20)->default('submitted');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            // Who verified or rejected the payment, and when.
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            // The receipt number while the payment is live, NULL once it is rejected.
            // A receipt pays for one permit (spec §4.5); a unique index ignores NULLs,
            // so a rejected payment gives its receipt number back for a corrected submission.
            $table->string('live_receipt_number', 64)
                ->nullable()
                ->virtualAs("case when status <> 'rejected' then receipt_number end");

            $table->unique('live_receipt_number', 'payments_receipt_number_unique');
            $table->index('permit_id');
            $table->index(['status', 'created_at']);
            $table->index('submitted_by');
            $table->index('verified_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

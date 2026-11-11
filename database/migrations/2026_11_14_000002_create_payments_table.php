<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

            $table->index('permit_id');
            $table->index(['status', 'created_at']);
            $table->index('submitted_by');
            $table->index('verified_by');
        });

        // A receipt pays for one permit (spec §4.5). A rejected payment gives
        // its receipt number back, so a corrected submission can use it again.
        DB::statement("CREATE UNIQUE INDEX payments_receipt_number_unique ON payments (receipt_number) WHERE status <> 'rejected'");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

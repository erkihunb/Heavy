<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Requests come in by letter at a branch or the head office: the Registrar
 * records the letter's reference number, its date and a scanned copy
 * (private disk). An Issuer issues and prints the permit once it is paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permits', function (Blueprint $table): void {
            $table->string('letter_number', 64)->nullable()->after('reason');
            $table->date('letter_date')->nullable()->after('letter_number');
            $table->string('letter_file_path')->nullable()->after('letter_date');
            $table->foreignId('issued_by')->nullable()->after('issued_at')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('permits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('issued_by');
            $table->dropColumn(['letter_number', 'letter_date', 'letter_file_path']);
        });
    }
};

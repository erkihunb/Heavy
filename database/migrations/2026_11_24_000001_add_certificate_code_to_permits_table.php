<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The certificate code printed on a permit's PDF (e.g. CRT-7K3P-QX2M), kept
 * as printed so the verification page shows the same code even after the
 * Admin changes the code rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permits', function (Blueprint $table): void {
            $table->string('certificate_code', 40)->nullable()->after('document_path_en');
        });
    }

    public function down(): void
    {
        Schema::table('permits', function (Blueprint $table): void {
            $table->dropColumn('certificate_code');
        });
    }
};

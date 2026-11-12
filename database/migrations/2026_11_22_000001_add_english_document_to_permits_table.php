<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The permit is printed twice, once per language. The existing
 * document_path holds the Amharic PDF; this column holds the English one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permits', function (Blueprint $table): void {
            $table->string('document_path_en')->nullable()->after('document_path');
        });
    }

    public function down(): void
    {
        Schema::table('permits', function (Blueprint $table): void {
            $table->dropColumn('document_path_en');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy Google-Form import is removed (owner's decision, 2026-11-12):
 * every organization registers in this system from scratch. Databases that
 * ran the import migrations lose its tables, the permits' legacy reference
 * and the permission; a fresh database never had them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('import_issues');
        Schema::dropIfExists('import_batches');

        if (Schema::hasColumn('permits', 'legacy_source_ref')) {
            Schema::table('permits', function (Blueprint $table) {
                $table->dropUnique(['legacy_source_ref']);
                $table->dropColumn('legacy_source_ref');
            });
        }

        // Role rows lose it by cascade.
        DB::table('permissions')->where('name', 'imports.run')->delete();
    }

    public function down(): void
    {
        // Not restored: the import no longer exists.
    }
};

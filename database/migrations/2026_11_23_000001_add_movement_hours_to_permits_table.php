<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The hours of the day a permit lets its vehicles move (Addis Ababa time).
 * Both empty: any time of day. A window may run past midnight (from after to).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permits', function (Blueprint $table): void {
            $table->time('move_from')->nullable()->after('valid_to');
            $table->time('move_to')->nullable()->after('move_from');
        });
    }

    public function down(): void
    {
        Schema::table('permits', function (Blueprint $table): void {
            $table->dropColumn(['move_from', 'move_to']);
        });
    }
};

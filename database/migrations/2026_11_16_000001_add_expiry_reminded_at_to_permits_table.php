<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permits', function (Blueprint $table) {
            // When the "expires soon" reminder was sent; it is sent once per permit.
            $table->timestamp('expiry_reminded_at')->nullable()->after('issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('permits', function (Blueprint $table) {
            $table->dropColumn('expiry_reminded_at');
        });
    }
};

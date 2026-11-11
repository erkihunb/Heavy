<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every verification attempt (spec §7): QR scans and number + plate lookups,
        // by the public and by traffic officers. Append-only by convention.
        Schema::create('verification_scans', function (Blueprint $table) {
            $table->id();
            // null when nothing matched.
            $table->foreignUlid('permit_id')->nullable()->constrained()->nullOnDelete();
            // App\Enums\ScanResult
            $table->string('result', 20);
            // App\Enums\ScanChannel: qr | lookup
            $table->string('channel', 10);
            // The officer who scanned; null for the public.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // HMAC-SHA256 of the IP address with the app key; the address itself is never stored.
            $table->char('ip_hash', 64);
            $table->string('user_agent', 255)->nullable();
            // An explicit default stops MariaDB adding ON UPDATE CURRENT_TIMESTAMP.
            $table->timestamp('scanned_at')->useCurrent();

            $table->index(['permit_id', 'scanned_at']);
            $table->index('scanned_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_scans');
    }
};

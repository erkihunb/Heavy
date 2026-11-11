<?php

namespace App\Domain\Verification;

use App\Enums\ScanChannel;
use App\Enums\ScanResult;
use App\Models\Permit;
use App\Models\User;
use App\Models\VerificationScan;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Records every verification (spec §7). The IP address is kept only as an
 * HMAC keyed with the application key (spec §15: hash IPs in scan logs): the
 * same address gives the same value, so abuse can be spotted, but the address
 * cannot be recovered from the log alone.
 */
final class ScanLogger
{
    public function log(?Permit $permit, ScanResult $result, ScanChannel $channel, Request $request, ?User $officer = null): VerificationScan
    {
        $scan = new VerificationScan;
        $scan->permit_id = $permit?->id;
        $scan->result = $result;
        $scan->channel = $channel;
        $scan->user_id = $officer?->id;
        $scan->ip_hash = self::hashIp((string) $request->ip());
        $scan->user_agent = mb_substr((string) $request->userAgent(), 0, 255) ?: null;
        $scan->scanned_at = CarbonImmutable::now();
        $scan->save();

        return $scan;
    }

    public static function hashIp(string $ip): string
    {
        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}

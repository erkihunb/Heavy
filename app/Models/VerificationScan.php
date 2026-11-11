<?php

namespace App\Models;

use App\Enums\ScanChannel;
use App\Enums\ScanResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One verification attempt (spec §7). The IP address is stored only as an
 * HMAC, and nothing else about a member of the public is kept.
 *
 * @property int $id
 * @property string|null $permit_id
 * @property ScanResult $result
 * @property ScanChannel $channel
 * @property int|null $user_id
 * @property string $ip_hash
 * @property string|null $user_agent
 * @property CarbonImmutable $scanned_at
 */
class VerificationScan extends Model
{
    public $timestamps = false;

    /**
     * Rows are written by App\Domain\Verification\ScanLogger only.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => ScanResult::class,
            'channel' => ScanChannel::class,
            'scanned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Permit, $this>
     */
    public function permit(): BelongsTo
    {
        return $this->belongsTo(Permit::class);
    }

    /**
     * The officer who scanned; null for the public.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

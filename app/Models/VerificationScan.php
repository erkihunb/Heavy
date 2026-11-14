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
     * How often a permit has been checked, by how and by whom, and when last.
     *
     * @return array{total: int, qr: int, lookup: int, by_officers: int, by_public: int, last_at: CarbonImmutable|null}
     */
    public static function summaryFor(Permit $permit): array
    {
        $row = self::query()
            ->where('permit_id', $permit->id)
            ->toBase()
            ->selectRaw(
                'COUNT(*) AS total, COALESCE(SUM(channel = ?), 0) AS qr, COUNT(user_id) AS by_officers, MAX(scanned_at) AS last_at',
                [ScanChannel::Qr->value],
            )
            ->first();

        $total = (int) ($row->total ?? 0);
        $qr = (int) ($row->qr ?? 0);
        $byOfficers = (int) ($row->by_officers ?? 0);

        return [
            'total' => $total,
            'qr' => $qr,
            'lookup' => $total - $qr,
            'by_officers' => $byOfficers,
            'by_public' => $total - $byOfficers,
            'last_at' => ($row->last_at ?? null) === null ? null : CarbonImmutable::parse((string) $row->last_at, 'UTC'),
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

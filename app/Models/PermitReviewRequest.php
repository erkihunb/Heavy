<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * An Approver asking one Reviewer for an opinion on an application. The
 * Approver never has to wait for it: it only puts the application in that
 * Reviewer's queue and lets them record an assessment. Append-only.
 *
 * @property int $id
 * @property string $permit_id
 * @property int $reviewer_id
 * @property int $requested_by
 * @property CarbonImmutable $created_at
 */
class PermitReviewRequest extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Review requests are append-only.'));
    }

    /**
     * @return BelongsTo<Permit, $this>
     */
    public function permit(): BelongsTo
    {
        return $this->belongsTo(Permit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}

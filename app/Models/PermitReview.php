<?php

namespace App\Models;

use App\Enums\Recommendation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One Reviewer's assessment of an application: what they recommend the
 * Approver do and why. Internal to staff and append-only: written by the
 * RecommendPermit action, never edited. A Reviewer who changes their mind
 * leaves a new assessment; the latest one is theirs.
 *
 * @property int $id
 * @property string $permit_id
 * @property int $reviewer_id
 * @property Recommendation $recommendation
 * @property string $assessment
 * @property CarbonImmutable $created_at
 */
class PermitReview extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'recommendation',
        'assessment',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recommendation' => Recommendation::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Reviews are append-only.'));
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
}

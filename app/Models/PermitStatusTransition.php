<?php

namespace App\Models;

use App\Enums\PermitStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One step in the history of a permit: who moved it from which status to
 * which, when, and why. Append-only — the model refuses to change or remove a
 * row, and so does a database trigger.
 *
 * @property int $id
 * @property string $permit_id
 * @property PermitStatus|null $from_status
 * @property PermitStatus $to_status
 * @property int|null $actor_id
 * @property string|null $note
 * @property CarbonImmutable $created_at
 */
class PermitStatusTransition extends Model
{
    public const UPDATED_AT = null;

    /**
     * Rows are only written by App\Domain\Permits\PermitTransitions.
     *
     * @var list<string>
     */
    protected $fillable = [
        'from_status',
        'to_status',
        'actor_id',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => PermitStatus::class,
            'to_status' => PermitStatus::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('The permit history is append-only.'));
        static::deleting(fn () => throw new LogicException('The permit history is append-only.'));
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

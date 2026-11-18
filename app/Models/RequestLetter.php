<?php

namespace App\Models;

use App\Enums\PermitStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A request letter of an organization, which may ask for permits for many
 * vehicles. Each vehicle has a permit of its own (own number, fee,
 * certificate and QR code); the letter groups them so the office can decide,
 * take the payment for and print them together. The letter's number, date
 * and scan are also kept on each permit, as for a permit asked for alone.
 *
 * @property string $id
 * @property int $organization_id
 * @property int $applicant_id
 * @property string $letter_number
 * @property CarbonImmutable|null $letter_date
 * @property string|null $letter_file_path
 * @property int|null $registered_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class RequestLetter extends Model
{
    use HasUlids;

    protected $fillable = [
        'letter_number',
        'letter_date',
        'letter_file_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'letter_date' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * @return HasMany<Permit, $this>
     */
    public function permits(): HasMany
    {
        return $this->hasMany(Permit::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * How many of its permits are in each status.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        return $this->permits
            ->countBy(fn (Permit $permit): string => $permit->status->value)
            ->all();
    }

    /**
     * The permits still waiting for a decision, in the order they were asked for.
     *
     * @return list<Permit>
     */
    public function undecided(): array
    {
        return $this->permits
            ->filter(fn (Permit $permit): bool => in_array($permit->status, [PermitStatus::Submitted, PermitStatus::UnderReview], true))
            ->values()
            ->all();
    }
}

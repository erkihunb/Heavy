<?php

namespace App\Models;

use App\Domain\Permits\MovementHours;
use App\Enums\PermitStatus;
use App\Enums\PermitType;
use App\Enums\Recommendation;
use Carbon\CarbonImmutable;
use Database\Factories\PermitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An application for a movement permit and, once issued, the permit itself.
 *
 * @property string $id
 * @property string|null $permit_number
 * @property int $organization_id
 * @property int $applicant_id
 * @property PermitType $permit_type
 * @property PermitStatus $status
 * @property string $reason
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable $valid_to
 * @property string|null $move_from HH:MM:SS, Addis Ababa time; null with move_to for any time of day
 * @property string|null $move_to
 * @property int|null $fee_cents
 * @property string $currency
 * @property Recommendation|null $recommendation
 * @property int|null $recommended_by
 * @property CarbonImmutable|null $recommended_at
 * @property string|null $recommendation_note
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $issued_at
 * @property int|null $revoked_by
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revocation_reason
 * @property string|null $verification_token_hash
 * @property string|null $document_path the Amharic PDF
 * @property string|null $document_path_en the English PDF
 * @property string|null $certificate_code the code printed on its PDFs, e.g. CRT-7K3P-QX2M
 * @property CarbonImmutable|null $submitted_at
 * @property string|null $letter_number the reference number of the request letter
 * @property CarbonImmutable|null $letter_date
 * @property string|null $letter_file_path scanned letter, on the private disk
 * @property int|null $issued_by the Issuer who issued and printed the permit
 * @property CarbonImmutable|null $created_at
 */
class Permit extends Model
{
    /** @use HasFactory<PermitFactory> */
    use HasFactory, HasUlids;

    /**
     * The branch the application was made at: chosen by the applicant, or
     * the Registrar's own for walk-ins; null for the head office.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * The applications a member of staff works on: those filed at a branch
     * or the head office in their scope, or every one with the administrator
     * role or a global role (User::branchScope(); fail closed).
     *
     * @param  Builder<self>  $query
     */
    public function scopeInBranchOf(Builder $query, User $staff): void
    {
        $staff->branchScope()->apply($query, $query->qualifyColumn('branch_id'));
    }

    public function isInBranchOf(User $staff): bool
    {
        return $staff->branchScope()->allows($this->branch_id);
    }

    /**
     * Only what the applicant writes. Status, number, organization, applicant
     * and everything staff decide are set by the Actions, never mass-assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'permit_type',
        'reason',
        'valid_from',
        'valid_to',
        'move_from',
        'move_to',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PermitStatus::Draft->value,
        'currency' => 'ETB',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permit_type' => PermitType::class,
            'status' => PermitStatus::class,
            'recommendation' => Recommendation::class,
            'valid_from' => 'date',
            'valid_to' => 'date',
            'fee_cents' => 'integer',
            'recommended_at' => 'datetime',
            'approved_at' => 'datetime',
            'letter_date' => 'date',
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
            'submitted_at' => 'datetime',
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
    public function recommender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recommended_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * The primary vehicle first, then the trailers.
     *
     * @return BelongsToMany<Vehicle, $this, PermitVehicle, 'pivot'>
     */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'permit_vehicles')
            ->using(PermitVehicle::class)
            ->withPivot(['role', 'is_blocking'])
            ->orderBy('permit_vehicles.role')
            ->orderBy('vehicles.plate_normalized');
    }

    /**
     * Internal comments (head office), oldest first.
     *
     * @return HasMany<PermitComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(PermitComment::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * The history of the permit, oldest first.
     *
     * @return HasMany<PermitStatusTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(PermitStatusTransition::class)->orderBy('id');
    }

    /**
     * Every receipt submitted for the fee, oldest first.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /**
     * Permits whose status, as people see it today, is the given one:
     * "issued", "active" and "expired" are told apart by the dates of an
     * issued permit, exactly as {@see effectiveStatus()} does.
     *
     * @param  Builder<self>  $query
     */
    public function scopeInEffectiveStatus(Builder $query, PermitStatus $status): void
    {
        $today = CarbonImmutable::now(config('localization.display_timezone'))->toDateString();
        $issued = PermitStatus::Issued->value;

        match ($status) {
            PermitStatus::Issued => $query->where('status', $issued)->where('valid_from', '>', $today),
            PermitStatus::Active => $query->where('status', $issued)
                ->where('valid_from', '<=', $today)
                ->where('valid_to', '>=', $today),
            PermitStatus::Expired => $query->where(fn (Builder $query) => $query
                ->where('status', PermitStatus::Expired->value)
                ->orWhere(fn (Builder $query) => $query->where('status', $issued)->where('valid_to', '<', $today))),
            default => $query->where('status', $status->value),
        };
    }

    /**
     * The status as it is at a given moment (spec §3). An issued permit is
     * "active" on the days it covers and "expired" after them; before its
     * first day, and in every other stage, the stored status applies. Days
     * are calendar days in the display timezone.
     */
    public function effectiveStatus(CarbonImmutable $at): PermitStatus
    {
        if ($this->status !== PermitStatus::Issued) {
            return $this->status;
        }

        $date = $at->setTimezone(config('localization.display_timezone'))->toDateString();

        return match (true) {
            $date > $this->valid_to->toDateString() => PermitStatus::Expired,
            $date >= $this->valid_from->toDateString() => PermitStatus::Active,
            default => PermitStatus::Issued,
        };
    }

    /**
     * Number of days covered, counting the first and the last day.
     */
    public function durationDays(): int
    {
        return (int) $this->valid_from->diffInDays($this->valid_to) + 1;
    }

    /**
     * The hours of the day the vehicles may move; null for any time of day.
     */
    public function movementHours(): ?MovementHours
    {
        return MovementHours::from($this->move_from, $this->move_to);
    }
}

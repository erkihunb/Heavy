<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One receipt submitted for the fee of a permit. Payments are never deleted:
 * a rejected one stays on record and the applicant submits another.
 *
 * @property string $id
 * @property string $permit_id
 * @property PaymentMethod $method
 * @property string $receipt_number
 * @property int $amount_cents
 * @property CarbonImmutable $paid_at
 * @property string|null $receipt_file_path
 * @property PaymentStatus $status
 * @property int|null $submitted_by
 * @property int|null $verified_by
 * @property CarbonImmutable|null $verified_at
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUlids;

    /**
     * Only what the applicant writes. The permit, the file, the status and
     * everything the cashier decides are set by the Actions.
     *
     * @var list<string>
     */
    protected $fillable = [
        'method',
        'receipt_number',
        'amount_cents',
        'paid_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PaymentStatus::Submitted->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'paid_at' => 'date',
            'verified_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * The cashier who verified or rejected the payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}

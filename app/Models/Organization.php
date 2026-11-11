<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name_am
 * @property string|null $name_en
 * @property string|null $tin
 * @property string $phone
 * @property string $email
 * @property string $address
 * @property int $created_by
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * `created_by` is deliberately not fillable: ownership is set by the
     * CreateOrganization action, never from request input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name_am',
        'name_en',
        'tin',
        'phone',
        'email',
        'address',
    ];

    /**
     * The applicant who created the profile and may edit it.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function localizedName(string $locale): string
    {
        return $locale === 'en' && filled($this->name_en) ? $this->name_en : $this->name_am;
    }
}

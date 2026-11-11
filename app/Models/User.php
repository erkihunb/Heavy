<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'locale',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * The organization an applicant belongs to; staff have none.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The language e-mails and other notifications are written in.
     */
    public function preferredLocale(): string
    {
        $supported = array_keys((array) config('localization.supported'));

        return in_array($this->locale, $supported, true) ? (string) $this->locale : (string) config('app.locale');
    }

    /**
     * Whether the user holds any staff role (reviewer, approver, cashier, traffic officer, admin).
     */
    public function isStaff(): bool
    {
        return $this->hasAnyRole(Role::staffValues());
    }

    /**
     * Staff must confirm two-factor authentication before using the system.
     */
    public function mustEnableTwoFactor(): bool
    {
        return config('permits.staff_two_factor_required')
            && ! $this->hasEnabledTwoFactorAuthentication()
            && $this->isStaff();
    }
}

<?php

namespace App\Models;

use App\Enums\Permission;
use App\Models\Role as RoleModel;
use App\Support\Rbac\BranchScope;
use App\Support\Rbac\PermissionCatalog;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int|null $registered_by
 * @property int|null $branch_id
 * @property bool $must_change_password
 * @property Carbon|null $deactivated_at
 * @property int|null $deactivated_by
 */
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
            'must_change_password' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * Whoever chooses a new password (in their settings or through a reset
     * link) is no longer on the default password their account started with.
     */
    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            if ($user->isDirty('password') && ! $user->isDirty('must_change_password')) {
                $user->must_change_password = false;
            }
        });
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
     * The branch office a member of staff works at; null for the head office
     * and for applicants. Set only by the user-administration actions.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Where this user's branch-scoped roles apply: branches and/or the head
     * office (EUISIS user organization scopes). Set on the Users screen.
     *
     * @return HasMany<UserBranchScope, $this>
     */
    public function branchScopes(): HasMany
    {
        return $this->hasMany(UserBranchScope::class);
    }

    /**
     * Where this person may see applications. Everywhere with the
     * administrator role or any global role; otherwise the head office and
     * branches in their scope rows, and nothing without a row (fail closed).
     */
    public function branchScope(): BranchScope
    {
        $roles = $this->roles;

        if ($roles->contains(fn (Model $role): bool => $role instanceof RoleModel
            && ($role->isProtected() || $role->isGlobal()))) {
            return BranchScope::everywhere();
        }

        $rows = $this->branchScopes;

        return BranchScope::of(
            $rows->contains(fn (UserBranchScope $row): bool => $row->is_head_office),
            $rows->whereNotNull('branch_id')->pluck('branch_id')->map(fn ($id): int => (int) $id)->values()->all(),
        );
    }

    /**
     * The administrator who deactivated this account, while it is deactivated.
     *
     * @return BelongsTo<User, $this>
     */
    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * Whether the account may be used: false once an administrator
     * deactivated it (it is kept, with its history, and can be restored).
     */
    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * The Registrar or Admin who registered this applicant at the office;
     * null for applicants who registered themselves online. Set only by
     * the RegisterApplicant action, never from request input.
     *
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
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
     * Whether the user does any of the office's work: holds, through any
     * role, a permission other than the applicant's own. Decided by
     * permissions, never by the name of a role.
     */
    public function isStaff(): bool
    {
        $staff = PermissionCatalog::staffPermissions();

        // Loaded here when not already: a list of users should eager load
        // `permissions` and `roles.permissions` (see Admin\UserController).
        $this->loadMissing('permissions');

        return $this->getAllPermissions()->contains(fn (Model $permission): bool => in_array($permission->getAttribute('name'), $staff, true));
    }

    /**
     * Whether this is an applicant's account: one that requests are recorded for.
     */
    public function isApplicant(): bool
    {
        return $this->can(Permission::ApplyForPermits->value);
    }

    /**
     * Whether a person signs in with this account. An applicant's account is
     * the record of who a request was recorded for: it has no pages and no
     * sign-in, unless its roles also give it some of the office's work.
     */
    public function maySignIn(): bool
    {
        return ! $this->isApplicant() || $this->isStaff();
    }

    /**
     * An account nobody signs in with has no use for a password: no reset
     * link is sent for it (the form answers the same either way).
     */
    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void
    {
        if (! $this->maySignIn()) {
            return;
        }

        parent::sendPasswordResetNotification($token);
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

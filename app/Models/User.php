<?php

namespace App\Models;

use App\Enums\Permission;
use App\Models\Role as RoleModel;
use App\Support\Rbac\BranchScope;
use App\Support\Rbac\PermissionCatalog;
use Carbon\CarbonInterface;
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
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int|null $registered_by
 * @property int|null $branch_id
 * @property bool $must_change_password
 * @property string|null $two_factor_requirement
 * @property string|null $avatar_path
 * @property string|null $job_title
 * @property string|null $employee_number
 * @property string|null $national_id
 * @property string|null $gender
 * @property string|null $phone
 * @property string|null $first_name
 * @property string|null $middle_name
 * @property string|null $last_name
 * @property string|null $username
 * @property string|null $institution
 * @property string|null $department
 * @property int|null $manager_id
 * @property string|null $employment_status
 * @property string|null $timezone
 * @property string|null $theme
 * @property array<string, bool>|null $notification_preferences
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property int|null $created_by
 * @property CarbonInterface|null $email_verified_at
 * @property Carbon|null $deactivated_at
 * @property int|null $deactivated_by
 */
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /** An Admin's choice for one person, over the two-factor policy (users.two_factor_requirement). */
    public const string TWO_FACTOR_REQUIRED = 'required';

    public const string TWO_FACTOR_EXEMPT = 'exempt';

    /** Old passwords kept per person; the policy compares with at most this many. */
    public const int PASSWORD_HISTORY_KEPT = 24;

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
            'last_login_at' => 'datetime',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * Whoever chooses a new password (in their settings or through a reset
     * link) is no longer on the default password their account started with.
     */
    protected static function booted(): void
    {
        // The name parts follow the whole name wherever it is written without them:
        // the person's own profile page, applicant registration, seeders.
        static::saving(function (User $user): void {
            $partsGiven = $user->isDirty(['first_name', 'middle_name', 'last_name']);

            if (($user->isDirty('name') && ! $partsGiven) || ($user->first_name === null && ! $partsGiven)) {
                $parts = preg_split('/\s+/u', trim((string) $user->name), 3, PREG_SPLIT_NO_EMPTY) ?: [];
                $user->first_name = $parts[0] ?? null;
                $user->middle_name = $parts[1] ?? null;
                $user->last_name = $parts[2] ?? null;
            }
        });

        static::updating(function (User $user): void {
            if ($user->isDirty('password') && ! $user->isDirty('must_change_password')) {
                $user->must_change_password = false;
            }
        });

        // The password being replaced joins the history, so it cannot come back (PasswordPolicy).
        static::updated(function (User $user): void {
            $old = $user->getRawOriginal('password');

            if (! $user->wasChanged('password') || ! is_string($old) || $old === '') {
                return;
            }

            DB::table('password_histories')->insert(['user_id' => $user->id, 'password' => $old, 'created_at' => now()]);

            $keep = DB::table('password_histories')->where('user_id', $user->id)->latest('id')->skip(self::PASSWORD_HISTORY_KEPT)->take(PHP_INT_MAX)->pluck('id');

            if ($keep->isNotEmpty()) {
                DB::table('password_histories')->whereIn('id', $keep)->delete();
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
     * The person this member of staff reports to.
     *
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * The administrator who opened this account.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether the person wants e-mails about applications. Account and
     * security messages (password links) are always sent.
     */
    public function wantsEmailNotifications(): bool
    {
        return ($this->notification_preferences['email'] ?? true) !== false;
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
     * Whether this is an administrator and no other active account holds
     * the administrator role, so the system would lose whoever can manage it.
     */
    public function isLastActiveAdmin(): bool
    {
        if (! $this->hasRole(PermissionCatalog::PROTECTED_ROLE)) {
            return false;
        }

        return self::role(PermissionCatalog::PROTECTED_ROLE)
            ->whereKeyNot($this->getKey())
            ->whereNull('deactivated_at')
            ->doesntExist();
    }

    /**
     * The Registrar or Admin who registered this applicant at the office.
     * Set only by the RegisterApplicant action, never from request input.
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
     * Whether this person must use two-factor authentication: the policy in
     * System settings › Security (on, for everyone or for chosen roles),
     * unless an Admin chose otherwise for them. Only staff sign in.
     */
    public function requiresTwoFactor(): bool
    {
        if (! config('permits.security.mfa_enabled') || ! $this->isStaff()) {
            return false;
        }

        return match ($this->two_factor_requirement) {
            self::TWO_FACTOR_REQUIRED => true,
            self::TWO_FACTOR_EXEMPT => false,
            default => $this->twoFactorPolicyApplies(),
        };
    }

    /**
     * What the policy alone says for this person, before an Admin's choice.
     */
    public function twoFactorPolicyApplies(): bool
    {
        if (config('permits.security.mfa_required_for_all')) {
            return true;
        }

        $roles = array_map('intval', (array) config('permits.security.mfa_required_roles', []));

        return $roles !== [] && array_intersect($this->roles->modelKeys(), $roles) !== [];
    }

    /**
     * Staff who must use two-factor authentication confirm it before using the system.
     */
    public function mustEnableTwoFactor(): bool
    {
        return ! $this->hasEnabledTwoFactorAuthentication() && $this->requiresTwoFactor();
    }
}

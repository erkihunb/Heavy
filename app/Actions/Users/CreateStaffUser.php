<?php

namespace App\Actions\Users;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use App\Notifications\StaffAccountCreated;
use App\Support\UserAvatar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

class CreateStaffUser
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly GiveDefaultBranchScope $defaultScope,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * An Admin opens an account for a member of staff, with one or more
     * roles (default or created on the Roles screen). The new user gets an
     * e-mail with a link to choose a password (or starts on one the Admin
     * hands over, and must change it at first sign-in). When the Admin
     * requires it, they must then turn on two-factor authentication. Their
     * branch scope starts from where they work (GiveDefaultBranchScope).
     *
     * @param  Role|list<string>  $roles  a default role, or role names
     * @param  User|null  $admin  null when opened from the command line (the first Admin)
     * @param  bool  $notify  false when the caller hands over the link itself
     * @param  Branch|null  $branch  the branch office the person works at; null for the head office
     * @param  string|null  $password  a starting password the Admin hands over; the person must replace it and no e-mail is sent
     * @param  list<string>  $permissions  permissions given directly, on top of the roles
     * @param  bool  $active  false opens the account deactivated: it cannot sign in until restored, and no link is sent
     * @param  array<string, mixed>  $profile  the record's other columns (ValidatesStaffProfile::profile())
     */
    public function handle(string $name, string $email, Role|array $roles, ?User $admin, bool $notify = true, ?Branch $branch = null, ?string $password = null, array $profile = [], ?UploadedFile $avatar = null, bool $active = true, array $permissions = []): User
    {
        $roles = $roles instanceof Role ? [$roles->value] : array_values(array_unique($roles));

        if ($roles === [] || in_array(Role::Applicant->value, $roles, true)) {
            throw new InvalidArgumentException('Staff accounts get staff roles.');
        }

        // A starting password the Admin hands over must be replaced at first sign-in; no link is sent.
        $default = $password;

        // Opened from the command line or with the whole name only: the parts are its words.
        if (! isset($profile['first_name'])) {
            $parts = preg_split('/\s+/u', trim($name), 3, PREG_SPLIT_NO_EMPTY) ?: [];
            $profile = ['first_name' => $parts[0] ?? null, 'middle_name' => $parts[1] ?? null, 'last_name' => $parts[2] ?? null] + $profile;
        }

        $avatarPath = $avatar === null ? null : UserAvatar::store($avatar);

        $user = DB::transaction(function () use ($name, $email, $roles, $admin, $branch, $default, $profile, $avatarPath, $active, $permissions): User {
            $user = new User([
                'name' => trim($name),
                'email' => Str::lower(trim($email)),
                'password' => $default ?? Str::random(64),
            ]);
            $user->must_change_password = $default !== null;
            // The Admin vouches for the address; setting the password proves it.
            $user->email_verified_at = now();
            $user->branch_id = $branch?->id;
            $user->forceFill(array_filter($profile, fn ($value) => $value !== null) + ['avatar_path' => $avatarPath, 'created_by' => $admin?->id]);

            if (! $active) {
                $user->forceFill(['deactivated_at' => now(), 'deactivated_by' => $admin?->id]);
            }
            $user->save();

            $user->assignRole($roles);

            if ($permissions !== []) {
                $user->givePermissionTo($permissions);
            }
            $this->defaultScope->handle($user);

            $this->audit->record(AuditEvent::UserCreated, $user, $admin, new: array_filter(['roles' => $roles, 'permissions' => $permissions, 'branch' => $branch?->code]));

            return $user;
        });

        $this->registrar->forgetCachedPermissions();

        if ($notify && $default === null && $active) {
            $user->notify(new StaffAccountCreated(Password::broker()->createToken($user)));
        }

        return $user;
    }
}

<?php

namespace App\Actions\Users;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use App\Notifications\StaffAccountCreated;
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
     * roles (default or created on the Roles screen). The new user starts on
     * the default password and must change it at first sign-in; when none is
     * set they get an e-mail with a link to choose one instead. Either way
     * they must then turn on two-factor authentication. Their
     * branch scope starts from where they work (GiveDefaultBranchScope).
     *
     * @param  Role|list<string>  $roles  a default role, or role names
     * @param  User|null  $admin  null when opened from the command line (the first Admin)
     * @param  bool  $notify  false when the caller hands over the link itself
     * @param  Branch|null  $branch  the branch office the person works at; null for the head office
     */
    public function handle(string $name, string $email, Role|array $roles, ?User $admin, bool $notify = true, ?Branch $branch = null): User
    {
        $roles = $roles instanceof Role ? [$roles->value] : array_values(array_unique($roles));

        if ($roles === [] || in_array(Role::Applicant->value, $roles, true)) {
            throw new InvalidArgumentException('Staff accounts get staff roles.');
        }

        // With a default password in System settings › Security the person starts
        // on it and must choose their own at first sign-in; no link is sent.
        $default = $notify ? self::defaultPassword() : null;

        $user = DB::transaction(function () use ($name, $email, $roles, $admin, $branch, $default): User {
            $user = new User([
                'name' => trim($name),
                'email' => Str::lower(trim($email)),
                'password' => $default ?? Str::random(64),
            ]);
            $user->must_change_password = $default !== null;
            // The Admin vouches for the address; setting the password proves it.
            $user->email_verified_at = now();
            $user->branch_id = $branch?->id;
            $user->save();

            $user->assignRole($roles);
            $this->defaultScope->handle($user);

            $this->audit->record(AuditEvent::UserCreated, $user, $admin, new: array_filter(['roles' => $roles, 'branch' => $branch?->code]));

            return $user;
        });

        $this->registrar->forgetCachedPermissions();

        if ($notify && $default === null) {
            $user->notify(new StaffAccountCreated(Password::broker()->createToken($user)));
        }

        return $user;
    }

    /**
     * The password new staff accounts start with, when one is set.
     */
    public static function defaultPassword(): ?string
    {
        $default = config('permits.security.default_password');

        return is_string($default) && $default !== '' ? $default : null;
    }
}

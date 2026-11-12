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
     * roles (default or created on the Roles screen). Nobody ever chooses or
     * sees the password: the new user gets an e-mail with a link to set
     * their own, and must then turn on two-factor authentication. Their
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

        $user = DB::transaction(function () use ($name, $email, $roles, $admin, $branch): User {
            $user = new User([
                'name' => trim($name),
                'email' => Str::lower(trim($email)),
                'password' => Str::random(64),
            ]);
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

        if ($notify) {
            $user->notify(new StaffAccountCreated(Password::broker()->createToken($user)));
        }

        return $user;
    }
}

<?php

namespace App\Actions\Users;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\StaffAccountCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateStaffUser
{
    public function __construct(private readonly AuditLog $audit) {}

    /**
     * An Admin opens an account for a member of staff. Nobody ever chooses
     * or sees the password: the new user gets an e-mail with a link to set
     * their own, and must then turn on two-factor authentication.
     *
     * @param  User|null  $admin  null when opened from the command line (the first Admin)
     * @param  bool  $notify  false when the caller hands over the link itself
     */
    public function handle(string $name, string $email, Role $role, ?User $admin, bool $notify = true): User
    {
        if (! $role->isStaff()) {
            throw new InvalidArgumentException('Staff accounts get a staff role.');
        }

        $user = DB::transaction(function () use ($name, $email, $role, $admin): User {
            $user = new User([
                'name' => trim($name),
                'email' => Str::lower(trim($email)),
                'password' => Str::random(64),
            ]);
            // The Admin vouches for the address; setting the password proves it.
            $user->email_verified_at = now();
            $user->save();

            $user->assignRole($role->value);

            $this->audit->record(AuditEvent::UserCreated, $user, $admin, new: ['roles' => [$role->value]]);

            return $user;
        });

        if ($notify) {
            $user->notify(new StaffAccountCreated(Password::broker()->createToken($user)));
        }

        return $user;
    }
}

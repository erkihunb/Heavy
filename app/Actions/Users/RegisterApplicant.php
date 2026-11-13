<?php

namespace App\Actions\Users;

use App\Data\OrganizationData;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class RegisterApplicant
{
    public function __construct(private readonly AuditLog $audit) {}

    /**
     * A Registrar registers an applicant who came to the office in person:
     * the contact person's record and their organization, in one step. The
     * account is a record only: nobody signs in with it (User::maySignIn()),
     * so it gets a random password that nobody knows and no link to set one.
     * Notices about the request are e-mailed to the address.
     */
    public function handle(string $name, string $email, string $locale, OrganizationData $organization, User $registrar): User
    {
        return DB::transaction(function () use ($name, $email, $locale, $organization, $registrar): User {
            $user = new User([
                'name' => trim($name),
                'email' => Str::lower(trim($email)),
                'locale' => $locale,
                'password' => Str::random(64),
            ]);
            // The Registrar saw the person and took the address from them.
            $user->email_verified_at = now();
            $user->registered_by = $registrar->id;
            $user->save();

            $user->assignRole(Role::Applicant->value);

            $profile = new Organization($organization->toAttributes());
            $profile->created_by = $user->id;
            $profile->save();

            $user->forceFill(['organization_id' => $profile->id])->save();

            $this->audit->record(
                AuditEvent::ApplicantRegistered,
                $user,
                $registrar,
                new: ['roles' => [Role::Applicant->value], 'organization' => $profile->name_am],
                context: ['organization_id' => $profile->id, 'in_person' => true],
            );

            return $user;
        });
    }
}

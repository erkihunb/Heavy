<?php

namespace App\Actions\Users;

use App\Data\OrganizationData;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ApplicantAccountCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class RegisterApplicant
{
    public function __construct(private readonly AuditLog $audit) {}

    /**
     * A Registrar registers an applicant who came to the office in person:
     * the contact person's account and their organization, in one step.
     * Nobody chooses or sees the password: the applicant is e-mailed a link
     * to set their own, as staff are.
     */
    public function handle(string $name, string $email, string $locale, OrganizationData $organization, User $registrar): User
    {
        $user = DB::transaction(function () use ($name, $email, $locale, $organization, $registrar): User {
            $user = new User([
                'name' => trim($name),
                'email' => Str::lower(trim($email)),
                'locale' => $locale,
                'password' => Str::random(64),
            ]);
            // The Registrar saw the person; setting the password proves the address.
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

        $user->notify(new ApplicantAccountCreated(Password::broker()->createToken($user)));

        return $user;
    }
}

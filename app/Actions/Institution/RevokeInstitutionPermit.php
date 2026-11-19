<?php

namespace App\Actions\Institution;

use App\Domain\Permits\PermitTransitions;
use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Jobs\StampPermitDocument;
use App\Models\Permit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Withdraws an institution permit (the vehicle was sold, the permit was
 * given by mistake). It fails the check on the road from then on, its paper
 * is printed again stamped "revoked", and the vehicle can be given a new one.
 */
final class RevokeInstitutionPermit
{
    public function __construct(private readonly PermitTransitions $transitions) {}

    public function handle(Permit $permit, User $actor, string $reason): Permit
    {
        Gate::forUser($actor)->authorize(Permission::RevokeInstitutionPermits->value);

        abort_unless($permit->is_institutional, 404);

        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => __('institution.errors.revoke_reason')]);
        }

        if ($permit->status !== PermitStatus::Issued) {
            throw ValidationException::withMessages(['reason' => __('institution.errors.not_in_force')]);
        }

        $revoked = $this->transitions->apply($permit, PermitStatus::Revoked, $actor, $reason, function (Permit $permit) use ($actor, $reason): void {
            $permit->revoked_by = $actor->id;
            $permit->revoked_at = CarbonImmutable::now();
            $permit->revocation_reason = trim($reason);
        });

        StampPermitDocument::dispatch($revoked->id)->afterCommit();

        return $revoked;
    }
}

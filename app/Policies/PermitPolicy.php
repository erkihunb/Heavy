<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\PermitStatus;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Applicants act on the permits of their own organization only; staff act on
 * permits that have been submitted. A permit the user may not know about is
 * reported as "not found" rather than "forbidden".
 *
 * For staff the policy decides *who* may act; whether the permit is in the
 * right stage is decided by the state machine inside the Action, so a stale
 * page gets an explanation instead of a 403.
 */
class PermitPolicy
{
    /**
     * Reaching the applicant's list. Which permits are listed is always
     * limited to the user's own organization by the query.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ApplyForPermits->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ApplyForPermits->value)
            && $user->organization_id !== null;
    }

    public function view(User $user, Permit $permit): Response
    {
        return $this->isOwnedByOrganizationOf($user, $permit) || $this->isVisibleToStaff($user, $permit)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Permit $permit): Response
    {
        return $this->ownedAndIn($user, $permit, [PermitStatus::Draft, PermitStatus::ChangesRequested]);
    }

    public function submit(User $user, Permit $permit): Response
    {
        return $this->ownedAndIn($user, $permit, [PermitStatus::Draft, PermitStatus::ChangesRequested]);
    }

    public function withdraw(User $user, Permit $permit): Response
    {
        return $this->ownedAndIn($user, $permit, [PermitStatus::Draft, PermitStatus::Submitted]);
    }

    /**
     * Reporting the fee as paid, while the permit waits for payment.
     */
    public function submitPayment(User $user, Permit $permit): Response
    {
        if (! $this->isOwnedByOrganizationOf($user, $permit)) {
            return Response::denyAsNotFound();
        }

        return $user->can(Permission::SubmitPayments->value) && $permit->status === PermitStatus::AwaitingPayment
            ? Response::allow()
            : Response::deny();
    }

    /**
     * Reaching the review queue.
     */
    public function viewQueue(User $user): bool
    {
        return $user->can(Permission::ViewReviewQueue->value);
    }

    public function startReview(User $user, Permit $permit): Response
    {
        return $this->staffWith($user, $permit, Permission::ReviewPermits);
    }

    public function recommend(User $user, Permit $permit): Response
    {
        return $this->staffWith($user, $permit, Permission::ReviewPermits);
    }

    public function requestChanges(User $user, Permit $permit): Response
    {
        return $this->staffWith($user, $permit, Permission::ReviewPermits);
    }

    public function approve(User $user, Permit $permit): Response
    {
        return $this->staffWith($user, $permit, Permission::ApprovePermits);
    }

    public function reject(User $user, Permit $permit): Response
    {
        return $this->staffWith($user, $permit, Permission::ApprovePermits);
    }

    public function revoke(User $user, Permit $permit): Response
    {
        return $this->staffWith($user, $permit, Permission::RevokePermits);
    }

    /**
     * Replacing the QR code and document of an issued permit. Whether the
     * permit is still live is checked by ReissuePermit.
     */
    public function reissue(User $user, Permit $permit): Response
    {
        return $this->staffWith($user, $permit, Permission::RevokePermits);
    }

    /**
     * Opening the permit's PDF: its own organization while the permit is
     * issued or has expired, and staff who can see the permit.
     */
    public function download(User $user, Permit $permit): Response
    {
        if ($this->isOwnedByOrganizationOf($user, $permit)) {
            return $user->can(Permission::DownloadOwnPermits->value)
                && in_array($permit->status, [PermitStatus::Issued, PermitStatus::Expired], true)
                ? Response::allow()
                : Response::deny();
        }

        // A Registrar prints the issued permit for an applicant who came to the office.
        if ($user->can(Permission::RegisterApplicants->value)
            && in_array($permit->status, [PermitStatus::Issued, PermitStatus::Expired], true)) {
            return Response::allow();
        }

        return $this->isVisibleToStaff($user, $permit) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * The traffic officer's view of a verification (spec §7).
     */
    public function scan(User $user): bool
    {
        return $user->can(Permission::ScanPermits->value);
    }

    /**
     * Reporting a violation seen at the roadside.
     */
    public function report(User $user, Permit $permit): Response
    {
        if (! $user->can(Permission::ScanPermits->value)) {
            return Response::deny();
        }

        return in_array($permit->status, [PermitStatus::Issued, PermitStatus::Expired, PermitStatus::Revoked], true)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * A Registrar working for an applicant at the office (walk-ins): the
     * applicant's applications, in the same stages as the applicant could.
     */
    public function viewFor(User $user, Permit $permit, User $applicant): Response
    {
        return $this->forApplicant($user, $permit, $applicant, PermitStatus::cases());
    }

    public function updateFor(User $user, Permit $permit, User $applicant): Response
    {
        return $this->forApplicant($user, $permit, $applicant, [PermitStatus::Draft, PermitStatus::ChangesRequested]);
    }

    public function submitFor(User $user, Permit $permit, User $applicant): Response
    {
        return $this->forApplicant($user, $permit, $applicant, [PermitStatus::Draft, PermitStatus::ChangesRequested]);
    }

    public function submitPaymentFor(User $user, Permit $permit, User $applicant): Response
    {
        return $this->forApplicant($user, $permit, $applicant, [PermitStatus::AwaitingPayment]);
    }

    /**
     * @param  list<PermitStatus>  $statuses
     */
    private function forApplicant(User $user, Permit $permit, User $applicant, array $statuses): Response
    {
        if (! $user->can(Permission::RegisterApplicants->value)
            || ! $applicant->can(Permission::ApplyForPermits->value)
            || $applicant->organization_id === null
            || $applicant->organization_id !== $permit->organization_id) {
            return Response::denyAsNotFound();
        }

        return in_array($permit->status, $statuses, true) ? Response::allow() : Response::deny();
    }

    private function isOwnedByOrganizationOf(User $user, Permit $permit): bool
    {
        return $user->can(Permission::ApplyForPermits->value)
            && $user->organization_id !== null
            && $user->organization_id === $permit->organization_id;
    }

    /**
     * Drafts and withdrawn applications are the applicant's business only.
     */
    private function isVisibleToStaff(User $user, Permit $permit): bool
    {
        return $user->can(Permission::ViewReviewQueue->value)
            && $permit->status->isVisibleToStaff();
    }

    /**
     * A permit of another organization does not exist as far as the user is
     * concerned; one of their own in the wrong stage is simply not allowed.
     *
     * @param  list<PermitStatus>  $statuses
     */
    private function ownedAndIn(User $user, Permit $permit, array $statuses): Response
    {
        if (! $this->isOwnedByOrganizationOf($user, $permit)) {
            return Response::denyAsNotFound();
        }

        return in_array($permit->status, $statuses, true)
            ? Response::allow()
            : Response::deny();
    }

    private function staffWith(User $user, Permit $permit, Permission $permission): Response
    {
        if (! $this->isVisibleToStaff($user, $permit)) {
            return Response::denyAsNotFound();
        }

        return $user->can($permission->value)
            ? Response::allow()
            : Response::deny();
    }
}

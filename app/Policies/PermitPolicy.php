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
    public function view(User $user, Permit $permit): Response
    {
        return $this->isVisibleToStaff($user, $permit)
            ? Response::allow()
            : Response::denyAsNotFound();
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

    /**
     * A cashier recording a payment taken at the office or checked with the bank.
     */
    public function recordPayment(User $user, Permit $permit): Response
    {
        return $this->staffAt($user, $permit, Permission::VerifyPayments);
    }

    /**
     * An Issuer issuing and printing a paid permit.
     */
    public function issue(User $user, Permit $permit): Response
    {
        return $this->staffAt($user, $permit, Permission::IssuePermits);
    }

    /**
     * The scanned request letter: staff who handle the application, and the
     * Registrar who took it, at its branch.
     */
    public function viewLetter(User $user, Permit $permit): Response
    {
        if ($this->isVisibleToStaff($user, $permit)) {
            return Response::allow();
        }

        return $user->can(Permission::RegisterApplicants->value) && $permit->isInBranchOf($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * The head office commenting on an application.
     */
    public function comment(User $user, Permit $permit): Response
    {
        return $this->staffAt($user, $permit, Permission::CommentOnPermits);
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
     * Opening the permit's PDF: the Registrar who prints it for the
     * applicant, and staff who can see the permit.
     */
    public function download(User $user, Permit $permit): Response
    {
        // A Registrar prints the issued permit for an applicant who came to the office.
        if ($user->can(Permission::RegisterApplicants->value)
            && $permit->isInBranchOf($user)
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
            || $applicant->organization_id !== $permit->organization_id
            || ! $permit->isInBranchOf($user)) {
            return Response::denyAsNotFound();
        }

        return in_array($permit->status, $statuses, true) ? Response::allow() : Response::deny();
    }

    /**
     * Drafts and withdrawn applications are the applicant's business only,
     * and staff placed at a branch see that branch's applications only.
     */
    private function isVisibleToStaff(User $user, Permit $permit): bool
    {
        return $user->can(Permission::ViewReviewQueue->value)
            && $permit->status->isVisibleToStaff()
            && $permit->isInBranchOf($user);
    }

    /**
     * Staff who do not work the review queue (cashiers, issuers): the
     * permission itself, for applications of their own branch.
     */
    private function staffAt(User $user, Permit $permit, Permission $permission): Response
    {
        if (! $permit->status->isVisibleToStaff() || ! $permit->isInBranchOf($user)) {
            return Response::denyAsNotFound();
        }

        if ($user->can($permission->value)) {
            return Response::allow();
        }

        // Someone who can see the application is told no; anyone else is not told it exists.
        return $this->isVisibleToStaff($user, $permit) ? Response::deny() : Response::denyAsNotFound();
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

<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Applicants see the payments of their own organization's permits; cashiers
 * see the payments of their branch's permits (every payment for Admins and
 * head-office staff) and decide on those awaiting verification. Whether a
 * payment is still awaiting verification is checked inside the Action, so a
 * stale page gets an explanation instead of a 403.
 */
class PaymentPolicy
{
    /**
     * Reaching the cashier's payment queue.
     */
    public function viewQueue(User $user): bool
    {
        return $user->can(Permission::VerifyPayments->value);
    }

    public function view(User $user, Payment $payment): Response
    {
        return $this->isCashierFor($user, $payment)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Opening the uploaded receipt file.
     */
    public function downloadReceipt(User $user, Payment $payment): Response
    {
        return $this->view($user, $payment);
    }

    public function verify(User $user, Payment $payment): Response
    {
        return $this->isCashierFor($user, $payment) ? Response::allow() : Response::denyAsNotFound();
    }

    public function reject(User $user, Payment $payment): Response
    {
        return $this->isCashierFor($user, $payment) ? Response::allow() : Response::denyAsNotFound();
    }

    private function isCashierFor(User $user, Payment $payment): bool
    {
        if (! $user->can(Permission::VerifyPayments->value)) {
            return false;
        }

        $scope = $user->branchScope();

        if ($scope->everywhere) {
            return true;
        }

        $permitBranch = Permit::query()->whereKey($payment->permit_id)->value('branch_id');

        return $scope->allows($permitBranch === null ? null : (int) $permitBranch);
    }
}

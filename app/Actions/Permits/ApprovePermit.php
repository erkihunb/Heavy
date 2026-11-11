<?php

namespace App\Actions\Permits;

use App\Domain\Permits\ApprovalRules;
use App\Domain\Permits\OverlapGuard;
use App\Domain\Permits\PermitNumberGenerator;
use App\Domain\Permits\PermitTransitions;
use App\Enums\PermitStatus;
use App\Exceptions\InvalidPermitTransition;
use App\Exceptions\OverlappingPermit;
use App\Exceptions\PermitRuleViolation;
use App\Models\Permit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

class ApprovePermit
{
    /** PostgreSQL SQLSTATE for a violated exclusion constraint. */
    private const string EXCLUSION_VIOLATION = '23P01';

    public function __construct(
        private readonly PermitTransitions $transitions,
        private readonly ApprovalRules $rules,
        private readonly OverlapGuard $overlap,
        private readonly PermitNumberGenerator $numbers,
    ) {}

    /**
     * An Approver accepts an application under review. The permit gets its
     * number, starts reserving its vehicles for its dates and waits for
     * payment. The fee is snapshotted here from Milestone 4 on.
     *
     * @throws InvalidPermitTransition
     * @throws OverlappingPermit
     * @throws PermitRuleViolation
     */
    public function handle(Permit $permit, User $approver, ?string $note = null): Permit
    {
        try {
            return $this->transitions->apply($permit, PermitStatus::AwaitingPayment, $approver, $note, function (Permit $permit) use ($approver): void {
                $this->rules->ensureMayDecide($permit, $approver);

                // Authoritative: the vehicle rows stay locked until the approval commits.
                $this->overlap->ensureNoOverlap($permit, lock: true);

                $now = CarbonImmutable::now();

                $permit->permit_number = $this->numbers->next($now);
                $permit->approved_by = $approver->id;
                $permit->approved_at = $now;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== self::EXCLUSION_VIOLATION) {
                throw $exception;
            }

            // The database refused an overlap the guard did not see. The
            // approval was rolled back; say which vehicle if it can be found.
            $permit->refresh();
            $this->overlap->ensureNoOverlap($permit);

            throw new OverlappingPermit;
        }
    }
}

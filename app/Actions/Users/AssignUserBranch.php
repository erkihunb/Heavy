<?php

namespace App\Actions\Users;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignUserBranch
{
    public function __construct(private readonly AuditLog $audit) {}

    /**
     * Place a member of staff at a branch, or at none (the head office).
     * At a branch they work on that branch's applications only; at the head
     * office they see the whole institution.
     */
    public function handle(User $user, ?Branch $branch, User $admin): void
    {
        DB::transaction(function () use ($user, $branch, $admin): void {
            $old = $user->branch_id === null ? null : Branch::query()->whereKey($user->branch_id)->value('code');

            if ($old === $branch?->code) {
                return;
            }

            $user->branch_id = $branch?->id;
            $user->save();

            $this->audit->record(AuditEvent::UserBranchChanged, $user, $admin, old: ['branch' => $old], new: ['branch' => $branch?->code]);
        });
    }
}

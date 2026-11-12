<?php

namespace App\Actions\Users;

use App\Models\Branch;
use App\Models\User;
use App\Models\UserBranchScope;

/**
 * The branch scope a member of staff starts with, from where they work:
 * their branch; or, at the head office, the head office and every branch,
 * as head-office staff saw before scope rows existed (the 2026_11_26
 * migration gives existing staff the same). Administrators may narrow or
 * widen it on the Users screen afterwards. Adds rows only, never removes.
 */
final class GiveDefaultBranchScope
{
    public function handle(User $user): void
    {
        if ($user->branch_id !== null) {
            $this->add($user, $user->branch_id);

            return;
        }

        if (! $user->branchScopes()->where('is_head_office', true)->exists()) {
            $user->branchScopes()->create(['branch_id' => null, 'is_head_office' => true]);
        }

        foreach (Branch::query()->pluck('id') as $branchId) {
            $this->add($user, (int) $branchId);
        }

        $user->unsetRelation('branchScopes');
    }

    private function add(User $user, int $branchId): void
    {
        UserBranchScope::query()->firstOrCreate(
            ['user_id' => $user->id, 'branch_id' => $branchId],
            ['is_head_office' => false],
        );
        $user->unsetRelation('branchScopes');
    }
}

<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ImportBatch;
use App\Models\User;

/**
 * The legacy import runner is for Admins (spec §2: run imports).
 */
class ImportBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RunImports->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::RunImports->value);
    }

    public function view(User $user, ImportBatch $batch): bool
    {
        return $user->can(Permission::RunImports->value);
    }
}

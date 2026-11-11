<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Export;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Exports are for staff who may see reports, and each file only for the
 * person who requested it.
 */
class ExportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewReports->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ViewReports->value);
    }

    public function download(User $user, Export $export): Response
    {
        return $user->can(Permission::ViewReports->value) && $export->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}

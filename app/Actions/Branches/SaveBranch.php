<?php

namespace App\Actions\Branches;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveBranch
{
    private const array AUDITED = ['code', 'name_am', 'name_en', 'is_active'];

    public function __construct(private readonly AuditLog $audit) {}

    /**
     * Add a branch or change one. A branch is never deleted: closing it
     * (inactive) stops new staff being placed there and keeps its permits.
     *
     * @param  array{code?: string, name_am: string, name_en: string, is_active: bool}  $attributes
     */
    public function handle(array $attributes, User $admin, ?Branch $branch = null): Branch
    {
        return DB::transaction(function () use ($attributes, $admin, $branch): Branch {
            $creating = $branch === null;
            $branch ??= new Branch;
            $old = $creating ? [] : $branch->only(self::AUDITED);

            $branch->fill($attributes)->save();

            $this->audit->record(
                $creating ? AuditEvent::BranchCreated : AuditEvent::BranchUpdated,
                $branch,
                $admin,
                old: $old,
                new: $branch->only(self::AUDITED),
            );

            return $branch;
        });
    }
}

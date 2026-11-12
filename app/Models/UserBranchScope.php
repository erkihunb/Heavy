<?php

namespace App\Models;

use App\Support\Rbac\BranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One place a user's branch-scoped roles apply (EUISIS user organization
 * scope, on branches): a branch, or the head office (`is_head_office`,
 * branch_id null), which covers applications filed without a branch.
 *
 * Fail closed: a user whose roles are all branch-scoped and who has no row
 * sees no application (App\Support\Rbac\BranchScope).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $branch_id
 * @property bool $is_head_office
 */
class UserBranchScope extends Model
{
    protected $fillable = ['user_id', 'branch_id', 'is_head_office'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_head_office' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** The picker's key for this row: "head-office" or the branch id. */
    public function key(): string
    {
        return $this->is_head_office ? BranchScope::HEAD_OFFICE_KEY : (string) $this->branch_id;
    }
}

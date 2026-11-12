<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Models\UserBranchScope;
use App\Support\Rbac\BranchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Adds and removes the branches a user's branch-scoped roles apply to
 * (EUISIS UserOrganizationScopeController, on branches). The rows fail
 * closed: removing the last one leaves a branch-scoped user seeing nothing.
 */
class UserBranchScopeController extends Controller
{
    public function __construct(private readonly AuditLog $audit) {}

    public function store(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('assignRoles', $user);

        $openBranches = Branch::query()->where('is_active', true)->pluck('id')->map(fn (int $id): string => (string) $id)->all();

        $validated = $request->validate([
            'branches' => ['required', 'array', 'min:1'],
            'branches.*' => ['string', 'distinct', Rule::in([BranchScope::HEAD_OFFICE_KEY, ...$openBranches])],
        ], attributes: ['branches' => __('rbac.scopes.title'), 'branches.*' => __('rbac.scopes.title')]);

        $existing = $user->branchScopes()->get()->map(fn (UserBranchScope $scope): string => $scope->key())->all();
        $added = array_values(array_diff($validated['branches'], $existing));

        DB::transaction(function () use ($user, $added, $request): void {
            foreach ($added as $key) {
                $user->branchScopes()->create(
                    $key === BranchScope::HEAD_OFFICE_KEY
                        ? ['branch_id' => null, 'is_head_office' => true]
                        : ['branch_id' => (int) $key, 'is_head_office' => false],
                );
            }

            if ($added !== []) {
                $this->audit->record(AuditEvent::UserScopesChanged, $user, $request->user(), new: ['added' => $this->codes($added)]);
            }
        });

        return back()->with('success', __('rbac.scopes.messages.added'));
    }

    public function destroy(Request $request, User $user, UserBranchScope $scope): RedirectResponse
    {
        Gate::authorize('assignRoles', $user);
        abort_unless($scope->user_id === $user->id, 404);

        DB::transaction(function () use ($user, $scope, $request): void {
            $key = $scope->key();
            $scope->delete();
            $this->audit->record(AuditEvent::UserScopesChanged, $user, $request->user(), old: ['removed' => $this->codes([$key])]);
        });

        return back()->with('success', __('rbac.scopes.messages.removed'));
    }

    /**
     * Branch codes for the audit log ("HQ" for the head office), not ids.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function codes(array $keys): array
    {
        $codes = Branch::query()->whereIn('id', array_filter($keys, 'is_numeric'))->pluck('code', 'id');

        return array_map(fn (string $key): string => $key === BranchScope::HEAD_OFFICE_KEY ? 'HQ' : (string) ($codes[(int) $key] ?? $key), $keys);
    }
}

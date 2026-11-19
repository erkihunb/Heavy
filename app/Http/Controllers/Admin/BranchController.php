<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Branches\SaveBranch;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLog;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BranchRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Branch offices: where staff work and which code a permit number carries.
 * A branch in use is closed; one with no staff and no permits may be deleted
 * (branches.delete).
 */
class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Branch::class);

        return Inertia::render('admin/branches/index', [
            'branches' => Branch::query()
                ->withCount(['users' => fn ($query) => $query->exceptApplicants(), 'permits'])
                ->orderBy('code')
                ->get()
                ->map(fn (Branch $branch): array => [
                    ...$this->present($branch),
                    'staff' => (int) $branch->users_count,
                    'permits' => (int) $branch->permits_count,
                ])
                ->all(),
            // Whether the number rule names the branch at all.
            'in_number' => str_contains((string) config('permits.number_format'), '{branch}'),
            'can' => [
                'create' => Gate::allows('create', Branch::class),
                'update' => $request->user()?->can(Permission::UpdateBranches->value) ?? false,
                'delete' => $request->user()?->can(Permission::DeleteBranches->value) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Branch::class);

        return Inertia::render('admin/branches/create');
    }

    public function store(BranchRequest $request, SaveBranch $saveBranch): RedirectResponse
    {
        $saveBranch->handle($request->attributesToSave(), $request->user());

        return to_route('admin.branches.index')->with('success', __('admin.branches.messages.saved'));
    }

    public function edit(Branch $branch): Response
    {
        Gate::authorize('update', $branch);

        return Inertia::render('admin/branches/edit', [
            'branch' => $this->present($branch),
        ]);
    }

    public function update(BranchRequest $request, Branch $branch, SaveBranch $saveBranch): RedirectResponse
    {
        $saveBranch->handle($request->attributesToSave(), $request->user(), $branch);

        return to_route('admin.branches.index')->with('success', __('admin.branches.messages.saved'));
    }

    public function destroy(Request $request, Branch $branch, AuditLog $audit): RedirectResponse
    {
        Gate::authorize('delete', $branch);

        if ($branch->users()->exists() || $branch->permits()->exists()) {
            throw ValidationException::withMessages(['branch' => __('admin.branches.errors.in_use')]);
        }

        DB::transaction(function () use ($request, $branch, $audit): void {
            $audit->record(AuditEvent::BranchDeleted, $branch, $request->user(), old: $this->present($branch));
            $branch->delete();
        });

        return to_route('admin.branches.index')->with('success', __('admin.branches.messages.deleted'));
    }

    /**
     * @return array{id: int, code: string, name_am: string, name_en: string, is_active: bool}
     */
    private function present(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'code' => $branch->code,
            'name_am' => $branch->name_am,
            'name_en' => $branch->name_en,
            'is_active' => $branch->is_active,
        ];
    }
}

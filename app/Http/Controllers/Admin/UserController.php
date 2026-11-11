<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ChangeUserRole;
use App\Actions\Users\CreateStaffUser;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffUserRequest;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Models\User;
use App\Support\EthiopianCalendar;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Users and roles (spec §11). Admins open staff accounts and change staff
 * roles; applicants register themselves and are listed for reference only.
 */
class UserController extends Controller
{
    private const int PER_PAGE = 25;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $role = is_string($request->query('role')) ? $request->query('role') : '';
        $role = $role === 'none' || Role::tryFrom($role) !== null ? $role : '';

        $users = User::query()
            ->with('roles:id,name')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->when($role === 'none', fn (Builder $query) => $query->whereDoesntHave('roles'))
            ->when($role !== '' && $role !== 'none', fn (Builder $query) => $query->role($role))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $user): array => $this->present($user, $request->user()));

        return Inertia::render('admin/users/index', [
            'users' => Paginated::present($users),
            'filters' => ['search' => $search, 'role' => $role],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/users/create', [
            'roles' => Role::staffValues(),
        ]);
    }

    public function store(StoreStaffUserRequest $request, CreateStaffUser $createStaffUser): RedirectResponse
    {
        $createStaffUser->handle(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            Role::from($request->string('role')->toString()),
            $request->user(),
        );

        return to_route('admin.users.index')->with('success', __('admin.users.messages.created'));
    }

    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('admin/users/edit', [
            'user' => $this->present($user->load('roles:id,name'), $request->user()),
            'roles' => Role::staffValues(),
        ]);
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user, ChangeUserRole $changeUserRole): RedirectResponse
    {
        $changeUserRole->handle($user, $request->role(), $request->user());

        return to_route('admin.users.index')->with('success', __('admin.users.messages.role_changed'));
    }

    /**
     * @return array{id: int, name: string, email: string, roles: list<string>, two_factor: bool, created_at: array<string, mixed>|null, can_change_role: bool, role_locked_reason: string|null}
     */
    private function present(User $user, User $viewer): array
    {
        $decision = Gate::forUser($viewer)->inspect('updateRole', $user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->values()->all(),
            'two_factor' => $user->two_factor_confirmed_at !== null,
            'created_at' => EthiopianCalendar::present($user->created_at?->setTimezone((string) config('localization.display_timezone')), app()->getLocale()),
            'can_change_role' => $decision->allowed(),
            'role_locked_reason' => $decision->allowed() ? null : $decision->message(),
        ];
    }
}

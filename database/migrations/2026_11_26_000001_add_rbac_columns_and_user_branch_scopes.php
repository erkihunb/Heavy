<?php

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Editable roles, deactivated accounts and branch scope rows (EUISIS RBAC).
 *
 * - roles: an Amharic name, where the role applies (`scope_type`: "branch"
 *   or "global") and `is_system` for the default roles, whose code name the
 *   application uses.
 * - users: `deactivated_at` / `deactivated_by` take access away without
 *   deleting the account.
 * - user_branch_scopes: where a user's branch-scoped roles apply, a branch
 *   or the head office (`is_head_office`, applications filed without a
 *   branch). Fail closed: no row, no application.
 *
 * The data step keeps what everyone sees today: staff placed at a branch get
 * that branch; head-office staff (no branch), who saw every application, get
 * the head office and every branch. Administrators see everywhere through
 * their role and get no rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('name_am')->nullable()->after('name');
            $table->string('scope_type', 16)->default('branch')->after('guard_name');
            $table->boolean('is_system')->default(false)->after('scope_type');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('deactivated_at')->nullable()->index();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('user_branch_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_head_office')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'branch_id']);
            $table->index(['user_id', 'is_head_office']);
        });

        DB::table('roles')
            ->whereIn('name', array_map(fn (RoleEnum $role): string => $role->value, RoleEnum::cases()))
            ->update(['is_system' => true]);

        $this->scopeExistingStaff();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_branch_scopes');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deactivated_by');
            $table->dropColumn('deactivated_at');
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn(['name_am', 'scope_type', 'is_system']);
        });
    }

    private function scopeExistingStaff(): void
    {
        $staffRoleIds = DB::table('roles')
            ->whereIn('name', array_values(array_diff(RoleEnum::staffValues(), [RoleEnum::Admin->value])))
            ->pluck('id');
        $adminIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', RoleEnum::Admin->value)
            ->pluck('model_has_roles.model_id');

        $staff = DB::table('users')
            ->whereIn('id', DB::table('model_has_roles')->whereIn('role_id', $staffRoleIds)->select('model_id'))
            ->whereNotIn('id', $adminIds)
            ->get(['id', 'branch_id']);
        $allBranches = DB::table('branches')->pluck('id');
        $now = now();
        $rows = [];

        foreach ($staff as $user) {
            if ($user->branch_id !== null) {
                $rows[] = ['user_id' => $user->id, 'branch_id' => $user->branch_id, 'is_head_office' => false];

                continue;
            }

            $rows[] = ['user_id' => $user->id, 'branch_id' => null, 'is_head_office' => true];

            foreach ($allBranches as $branchId) {
                $rows[] = ['user_id' => $user->id, 'branch_id' => $branchId, 'is_head_office' => false];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('user_branch_scopes')->insert(array_map(
                fn (array $row): array => [...$row, 'created_at' => $now, 'updated_at' => $now],
                $chunk,
            ));
        }
    }
};

<?php

namespace App\Support\Rbac;

use App\Enums\Permission;
use App\Models\User;

/**
 * Where a person lands when they may not see the dashboard: the first page
 * of the menu their permissions open, and their own profile when they hold
 * none. Decided by permissions only (resources/js/config/navigation.ts lists
 * the same pages in the same order).
 */
final class Landing
{
    /**
     * permission => route name, in menu order.
     *
     * @var array<string, string>
     */
    private const array PAGES = [
        Permission::ViewDashboard->value => 'dashboard',
        Permission::ViewReviewQueue->value => 'staff.permits.index',
        // The cashier collects from the queue (approved requests waiting for payment).
        Permission::VerifyPayments->value => 'staff.permits.index',
        Permission::RegisterApplicants->value => 'admin.permits.index',
        Permission::UpdateRegisteredPermits->value => 'admin.permits.index',
        Permission::DeleteRegisteredPermits->value => 'admin.permits.index',
        Permission::ViewOrganizations->value => 'admin.organizations.index',
        Permission::ScanPermits->value => 'officer.verify',
        Permission::ViewReports->value => 'staff.reports.index',
        Permission::ViewUsers->value => 'admin.users.index',
        Permission::ViewRoles->value => 'admin.roles.index',
        Permission::ViewPermissions->value => 'admin.permissions.index',
        Permission::ViewAuditLog->value => 'admin.audit-log.index',
        Permission::ViewFeeSchedules->value => 'admin.fee-schedules.index',
        Permission::ViewVehicleTypes->value => 'admin.vehicle-types.index',
        Permission::ViewBranches->value => 'admin.branches.index',
        Permission::ViewSettings->value => 'admin.settings.overview',
    ];

    public static function routeFor(User $user): string
    {
        foreach (self::PAGES as $permission => $route) {
            if ($user->can($permission)) {
                return $route;
            }
        }

        return 'profile.edit';
    }
}

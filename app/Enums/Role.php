<?php

namespace App\Enums;

/**
 * The application's roles (spec §2). Values are the role names stored by
 * spatie/laravel-permission. Authorization decisions are made in Policies
 * against permissions, never by checking role names inline.
 */
enum Role: string
{
    case Applicant = 'applicant';
    case Reviewer = 'reviewer';
    case Approver = 'approver';
    case Cashier = 'cashier';
    case TrafficOfficer = 'traffic_officer';
    case Admin = 'admin';

    /**
     * Staff roles: created by an Admin only and required to use two-factor authentication.
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::Reviewer, self::Approver, self::Cashier, self::TrafficOfficer, self::Admin];
    }

    /**
     * @return list<string>
     */
    public static function staffValues(): array
    {
        return array_map(fn (self $role): string => $role->value, self::staff());
    }

    public function isStaff(): bool
    {
        return $this !== self::Applicant;
    }

    /**
     * The permissions granted to this role. Admin holds every staff
     * permission (administration, review, approval, payment verification and
     * scanning). It does not hold the applicant's own-organization
     * permissions, because an Admin does not belong to an organization.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Applicant => [
                Permission::ManageOwnOrganization,
                Permission::ManageOwnVehicles,
                Permission::ApplyForPermits,
                Permission::SubmitPayments,
                Permission::DownloadOwnPermits,
            ],
            self::Reviewer => [
                Permission::ViewReviewQueue,
                Permission::ReviewPermits,
            ],
            self::Approver => [
                Permission::ViewReviewQueue,
                Permission::ApprovePermits,
                Permission::RevokePermits,
            ],
            self::Cashier => [
                Permission::VerifyPayments,
            ],
            self::TrafficOfficer => [
                Permission::ScanPermits,
            ],
            self::Admin => array_values(array_filter(
                Permission::cases(),
                fn (Permission $permission): bool => ! in_array($permission, self::Applicant->permissions(), true),
            )),
        };
    }
}

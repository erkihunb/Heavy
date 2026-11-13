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
    case Registrar = 'registrar';
    case Issuer = 'issuer';
    case Supervisor = 'supervisor';
    case Admin = 'admin';

    /**
     * Staff roles: created by an Admin only and required to use two-factor authentication.
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::Reviewer, self::Approver, self::Cashier, self::TrafficOfficer, self::Registrar, self::Issuer, self::Supervisor, self::Admin];
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
     * Applicants have no pages of their own (requests come in by letter and
     * are recorded at the office), so they do not see the dashboard either.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            // Marks the accounts requests are recorded for; they have no pages.
            self::Applicant => [
                Permission::ApplyForPermits,
            ],
            // A Reviewer examines a request and decides on it; with two-step
            // approval on, an Approver must decide after the recommendation.
            self::Reviewer => [
                Permission::ViewDashboard,
                Permission::ViewReviewQueue,
                Permission::ReviewPermits,
                Permission::ApprovePermits,
            ],
            self::Approver => [
                Permission::ViewDashboard,
                Permission::ViewReviewQueue,
                Permission::ApprovePermits,
                Permission::RevokePermits,
                Permission::ViewReports,
            ],
            // The cashier opens approved applications to take or check their payment.
            self::Cashier => [
                Permission::ViewDashboard,
                Permission::ViewReviewQueue,
                Permission::VerifyPayments,
                Permission::ViewReports,
            ],
            self::TrafficOfficer => [
                Permission::ViewDashboard,
                Permission::ScanPermits,
            ],
            self::Registrar => [
                Permission::ViewDashboard,
                Permission::RegisterApplicants,
            ],
            // The Issuer opens paid applications to check, issue and print them.
            self::Issuer => [
                Permission::ViewDashboard,
                Permission::ViewReviewQueue,
                Permission::IssuePermits,
            ],
            // Head office: sees every branch's applications and figures and comments on them.
            self::Supervisor => [
                Permission::ViewDashboard,
                Permission::ViewReviewQueue,
                Permission::CommentOnPermits,
                Permission::ViewReports,
            ],
            self::Admin => array_values(array_filter(
                Permission::cases(),
                fn (Permission $permission): bool => ! in_array($permission, self::Applicant->permissions(), true),
            )),
        };
    }
}

<?php

use App\Actions\Permits;
use App\Data\PaymentData;
use App\Data\RecommendationData;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\Recommendation;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Permit;
use App\Models\Role as RoleModel;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

dataset('staff action permissions', [
    'start review' => [Permits\StartReview::class, Role::Reviewer, Permission::ReviewPermits, 'staff.permits.start-review'],
    'recommend' => [Permits\RecommendPermit::class, Role::Reviewer, Permission::ReviewPermits, 'staff.permits.recommend'],
    'request changes' => [Permits\RequestChanges::class, Role::Reviewer, Permission::ReviewPermits, 'staff.permits.request-changes'],
    'approve' => [Permits\ApprovePermit::class, Role::Approver, Permission::ApprovePermits, 'staff.permits.approve'],
    'reject' => [Permits\RejectPermit::class, Role::Approver, Permission::ApprovePermits, 'staff.permits.reject'],
    'revoke' => [Permits\RevokePermit::class, Role::Approver, Permission::RevokePermits, 'staff.permits.revoke'],
    'reissue' => [Permits\ReissuePermit::class, Role::Approver, Permission::RevokePermits, 'staff.permits.reissue'],
    'record payment' => [Permits\RecordPayment::class, Role::Cashier, Permission::VerifyPayments, 'staff.permits.record-payment'],
    'verify payment' => [Permits\VerifyPayment::class, Role::Cashier, Permission::VerifyPayments, 'staff.payments.verify'],
    'reject payment' => [Permits\RejectPayment::class, Role::Cashier, Permission::VerifyPayments, 'staff.payments.reject'],
    'issue' => [Permits\IssuePaidPermit::class, Role::Issuer, Permission::IssuePermits, 'staff.permits.issue'],
    'comment' => [Permits\CommentOnPermit::class, Role::Supervisor, Permission::CommentOnPermits, 'staff.permits.comments.store'],
]);

it('refuses direct staff actions after their role permission is removed', function (string $action, Role $role, Permission $permission) {
    $actor = staffUser($role);
    expect($actor->can($permission->value))->toBeTrue();
    RoleModel::findByName($role->value)->revokePermissionTo($permission->value);
    $actor->refresh();

    // No ambient login: the action must authorize its explicit actor.
    $target = in_array($action, [Permits\VerifyPayment::class, Permits\RejectPayment::class], true)
        ? new Payment : new Permit;
    $arguments = match ($action) {
        Permits\RecommendPermit::class => [new RecommendationData(Recommendation::Approve, null)],
        Permits\RecordPayment::class => [new PaymentData(PaymentMethod::Cash, 'RBAC-1', 100_000, CarbonImmutable::now())],
        Permits\RequestChanges::class, Permits\RejectPermit::class, Permits\RevokePermit::class,
        Permits\ReissuePermit::class, Permits\RejectPayment::class, Permits\CommentOnPermit::class => ['Reason'],
        default => [],
    };

    expect(fn () => app($action)->handle($target, $actor, ...$arguments))
        ->toThrow(AuthorizationException::class);
})->with('staff action permissions');

it('blocks direct HTTP submissions after their role permission is removed', function (string $action, Role $role, Permission $permission, string $route) {
    $actor = staffUser($role);
    RoleModel::findByName($role->value)->revokePermissionTo($permission->value);
    $actor->refresh();
    $permit = Permit::factory()->submitted()->create();
    $target = str_starts_with($route, 'staff.payments.')
        ? Payment::factory()->forPermit($permit)->create() : $permit;
    $before = $target->refresh()->getRawOriginal();

    $this->actingAs($actor)->postJson(route($route, $target), [])->assertForbidden();

    expect($target->refresh()->getRawOriginal())->toBe($before);
    $this->assertDatabaseCount('permit_status_transitions', 0);
    $this->assertDatabaseCount('permit_comments', 0);
})->with('staff action permissions');

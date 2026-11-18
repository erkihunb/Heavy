<?php

use App\Domain\Settings\SettingsRegistry;
use App\Http\Controllers\Admin\ApplicantController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\FeeScheduleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PermitRegisterController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserBranchScopeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserSignInController;
use App\Http\Controllers\Admin\VehicleTypeController;
use App\Http\Controllers\Admin\WalkInController;
use App\Models\Branch;
use App\Models\FeeSchedule;
use App\Models\Role;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Support\Facades\Route;

/*
 * Administration. Every route is authorised by a Policy; staff must also have
 * confirmed two-factor authentication (web middleware).
 */
Route::middleware(['auth', 'verified', 'can:viewAny,'.FeeSchedule::class])
    ->name('admin.')
    ->group(function () {
        Route::resource('fee-schedules', FeeScheduleController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);
    });

// Users: accounts, roles, branch scopes, sign-in resets, deactivation (EUISIS RBAC).
// Each action is checked by its own permission in UserPolicy, so someone who may
// only open accounts or only reset passwords is not stopped at the door.
Route::middleware(['auth', 'verified'])
    ->name('admin.')
    ->whereNumber('user')
    ->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::put('users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles');
        Route::put('users/{user}/permissions', [UserController::class, 'updatePermissions'])->name('users.permissions');
        Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore');
        Route::middleware('throttle:10,1')->group(function () {
            Route::put('users/{user}/password', [UserSignInController::class, 'password'])->name('users.password');
            Route::post('users/{user}/reset-link', [UserSignInController::class, 'resetLink'])->name('users.reset-link');
            Route::delete('users/{user}/two-factor', [UserSignInController::class, 'twoFactor'])->name('users.two-factor');
            Route::put('users/{user}/two-factor-requirement', [UserSignInController::class, 'twoFactorRequirement'])->name('users.two-factor-requirement');
        });
        Route::post('users/{user}/branch-scopes', [UserBranchScopeController::class, 'store'])->name('users.branch-scopes.store');
        Route::delete('users/{user}/branch-scopes/{scope}', [UserBranchScopeController::class, 'destroy'])
            ->whereNumber('scope')
            ->name('users.branch-scopes.destroy');
    });

// Roles, editable (RolePolicy), and the read-only permission catalogue.
Route::middleware(['auth', 'verified', 'can:viewAny,'.Role::class])
    ->name('admin.')
    ->group(function () {
        Route::resource('roles', RoleController::class)->except(['show'])->whereNumber('role');
    });

Route::middleware(['auth', 'verified', 'can:permissions.view'])
    ->name('admin.')
    ->group(function () {
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::get('permissions/{permission}', [PermissionController::class, 'show'])
            ->where('permission', '[a-z][a-z0-9\-]*\.[a-z][a-z0-9\-]*')
            ->name('permissions.show');
    });

// A new permit taken at the office: the one-page form registers the applicant, their
// vehicles and the application together (Registrar, Admin). Reached from Permits.
Route::middleware(['auth', 'verified', 'can:registerApplicant,'.User::class])
    ->name('admin.')
    ->controller(ApplicantController::class)
    ->group(function () {
        Route::get('permit-register/create', 'create')->name('permits.create');
        // The fee rates the request on the form can be registered at, by permit and vehicle type.
        Route::get('permit-register/intake-rates', 'feeRates')
            ->middleware('throttle:60,1')
            ->name('permits.intake-rates');
        // An organization already on record, for a new request of theirs.
        Route::get('permit-register/organizations', 'organizations')
            ->middleware('throttle:60,1')
            ->name('permits.organizations');
        // What each vehicle type of a letter costs for its days, for the form's total.
        Route::get('permit-register/fee-estimate', 'feeEstimate')
            ->middleware('throttle:120,1')
            ->name('permits.fee-estimate');
        // An organization's vehicles on record, to fill in a known vehicle's type.
        Route::get('permit-register/organizations/{applicant}/vehicles', 'vehicles')
            ->whereNumber('applicant')
            ->middleware('throttle:60,1')
            ->name('permits.organization-vehicles');
        Route::post('permit-register', 'store')
            ->middleware('throttle:30,1')
            ->name('permits.store');
    });

// The register of all permits (review roles, Admins, Registrars; checked in the controller).
Route::middleware(['auth', 'verified'])
    ->get('permit-register', PermitRegisterController::class)
    ->name('admin.permits.index');

// A request taken at the office, as the Registrar works on it (Registrar, Admin):
// open it, correct and send it again, a vehicle it needs, the receipt. Reached from Permits.
Route::middleware(['auth', 'verified', 'can:registerApplicant,'.User::class])
    ->prefix('permit-register/{permit}')
    ->name('admin.permits.')
    ->controller(WalkInController::class)
    ->whereUlid('permit')
    ->group(function () {
        Route::get('/', 'show')->name('show');
        Route::get('edit', 'edit')->name('edit');
        Route::put('/', 'update')->name('update');
        Route::post('submit', 'submit')->name('submit');
        Route::get('payment', 'payment')->name('payment');
        Route::post('payments', 'storePayment')
            ->middleware('throttle:10,1')
            ->name('payments.store');
        Route::get('vehicles/create', 'createVehicle')->name('vehicles.create');
        Route::post('vehicles', 'storeVehicle')->name('vehicles.store');
        Route::get('fee-rates', 'feeRates')
            ->middleware('throttle:60,1')
            ->name('fee-rates');
    });

// Vehicle types (spec §11).
Route::middleware(['auth', 'verified', 'can:viewAny,'.VehicleType::class])
    ->name('admin.')
    ->group(function () {
        Route::resource('vehicle-types', VehicleTypeController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);
    });

// Branch offices.
Route::middleware(['auth', 'verified', 'can:viewAny,'.Branch::class])
    ->name('admin.')
    ->group(function () {
        Route::resource('branches', BranchController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);
    });

// System settings and the audit log (spec §11, §15).
Route::middleware(['auth', 'verified'])
    ->name('admin.')
    ->group(function () {
        Route::middleware('can:manageSettings')->group(function () {
            $sections = implode('|', SettingsRegistry::SECTIONS);

            Route::get('system-settings', [SettingsController::class, 'overview'])->name('settings.overview');
            Route::get('system-settings/{section}', [SettingsController::class, 'show'])->where('section', $sections)->name('settings.show');
            Route::put('system-settings/{section}', [SettingsController::class, 'update'])->where('section', $sections)->name('settings.update');
            Route::post('system-settings/{section}/test', [SettingsController::class, 'test'])
                ->where('section', 'email|sms|telegram')
                ->middleware('throttle:5,1')
                ->name('settings.test');
        });
        Route::get('audit-log', [AuditLogController::class, 'index'])->middleware('can:viewAuditLog')->name('audit-log.index');
    });

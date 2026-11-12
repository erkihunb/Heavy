<?php

use App\Domain\Settings\SettingsRegistry;
use App\Http\Controllers\Admin\ApplicantController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\FeeScheduleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VehicleTypeController;
use App\Http\Controllers\Admin\WalkInController;
use App\Models\FeeSchedule;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Support\Facades\Route;

/*
 * Administration. Every route is authorised by a Policy; staff must also have
 * confirmed two-factor authentication (web middleware).
 */
Route::middleware(['auth', 'verified', 'can:viewAny,'.FeeSchedule::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('fee-schedules', FeeScheduleController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);
    });

// Users and roles (spec §11).
Route::middleware(['auth', 'verified', 'can:viewAny,'.User::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
    });

// Applicants registered at the office (Registrar, Admin).
Route::middleware(['auth', 'verified', 'can:registerApplicant,'.User::class])
    ->prefix('admin')
    ->name('admin.')
    ->controller(ApplicantController::class)
    ->group(function () {
        Route::get('applicants', 'index')->name('applicants.index');
        Route::get('applicants/create', 'create')->name('applicants.create');
        Route::post('applicants', 'store')
            ->middleware('throttle:30,1')
            ->name('applicants.store');
    });

// The whole application for a walk-in applicant: vehicles, permit, payment.
Route::middleware(['auth', 'verified', 'can:registerApplicant,'.User::class])
    ->prefix('admin/applicants/{applicant}')
    ->name('admin.applicants.')
    ->controller(WalkInController::class)
    ->whereNumber('applicant')
    ->group(function () {
        Route::get('/', 'show')->name('show');
        Route::get('vehicles/create', 'createVehicle')->name('vehicles.create');
        Route::post('vehicles', 'storeVehicle')->name('vehicles.store');
        Route::get('permits/create', 'createPermit')->name('permits.create');
        Route::post('permits', 'storePermit')->name('permits.store');
        Route::get('permits/{permit}', 'showPermit')->name('permits.show');
        Route::get('permits/{permit}/edit', 'editPermit')->name('permits.edit');
        Route::put('permits/{permit}', 'updatePermit')->name('permits.update');
        Route::post('permits/{permit}/submit', 'submitPermit')->name('permits.submit');
        Route::post('permits/{permit}/payments', 'storePayment')
            ->middleware('throttle:10,1')
            ->name('permits.payments.store');
        Route::get('fee-estimate', 'feeEstimate')
            ->middleware('throttle:60,1')
            ->name('fee-estimate');
    });

// Vehicle types (spec §11).
Route::middleware(['auth', 'verified', 'can:viewAny,'.VehicleType::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('vehicle-types', VehicleTypeController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);
    });

// System settings and the audit log (spec §11, §15).
Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::middleware('can:manageSettings')->group(function () {
            $sections = implode('|', SettingsRegistry::SECTIONS);

            Route::get('settings', [SettingsController::class, 'overview'])->name('settings.overview');
            Route::get('settings/{section}', [SettingsController::class, 'show'])->where('section', $sections)->name('settings.show');
            Route::put('settings/{section}', [SettingsController::class, 'update'])->where('section', $sections)->name('settings.update');
            Route::post('settings/{section}/test', [SettingsController::class, 'test'])
                ->where('section', 'email|sms|telegram')
                ->middleware('throttle:5,1')
                ->name('settings.test');
        });
        Route::get('audit-log', [AuditLogController::class, 'index'])->middleware('can:viewAuditLog')->name('audit-log.index');
    });

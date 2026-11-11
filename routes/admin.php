<?php

use App\Domain\Settings\SettingsRegistry;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\FeeScheduleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VehicleTypeController;
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

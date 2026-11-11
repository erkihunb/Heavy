<?php

use App\Http\Controllers\Admin\FeeScheduleController;
use App\Http\Controllers\Admin\ImportController;
use App\Models\FeeSchedule;
use App\Models\ImportBatch;
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

// The legacy import runner (spec §11, §13).
Route::middleware(['auth', 'verified', 'can:viewAny,'.ImportBatch::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
        Route::post('imports', [ImportController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('imports.store');
        Route::get('imports/{import}/issues', [ImportController::class, 'issues'])->name('imports.issues');
    });

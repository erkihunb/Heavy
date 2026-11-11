<?php

use App\Http\Controllers\Applicant\FeeEstimateController;
use App\Http\Controllers\Applicant\OrganizationController;
use App\Http\Controllers\Applicant\PaymentController;
use App\Http\Controllers\Applicant\PermitController;
use App\Http\Controllers\Applicant\VehicleController;
use App\Models\Permit;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Route;

/*
 * The applicant portal. Every route is authorised by a Policy; data is always
 * limited to the organization of the signed-in user.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::controller(OrganizationController::class)->group(function () {
        Route::get('organization', 'show')->name('organization.show');
        Route::post('organization', 'store')->name('organization.store');
        Route::put('organization', 'update')->name('organization.update');
    });

    Route::middleware(['can:viewAny,'.Vehicle::class, 'organization'])->group(function () {
        Route::resource('vehicles', VehicleController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);
    });

    Route::middleware(['can:viewAny,'.Permit::class, 'organization'])->group(function () {
        Route::resource('permits', PermitController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update']);

        Route::post('permits/{permit}/submit', [PermitController::class, 'submit'])->name('permits.submit');
        Route::post('permits/{permit}/withdraw', [PermitController::class, 'withdraw'])->name('permits.withdraw');

        // Uploads are rate limited (spec §15).
        Route::post('permits/{permit}/payments', [PaymentController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('permits.payments.store');

        Route::get('fee-estimate', FeeEstimateController::class)
            ->middleware('throttle:60,1')
            ->name('fee-estimate');
    });

    // Authorised by PaymentPolicy::downloadReceipt; also reached by cashiers via the staff route.
    Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
});

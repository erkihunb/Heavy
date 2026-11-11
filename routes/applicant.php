<?php

use App\Http\Controllers\Applicant\OrganizationController;
use App\Http\Controllers\Applicant\VehicleController;
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
});

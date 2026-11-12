<?php

use App\Http\Controllers\UiKitController;
use Illuminate\Support\Facades\Route;

/*
 * The UI kit demo page (resources/js/pages/ui-kit.tsx): every component of the
 * shared kit with sample data. For development only: the routes do not exist
 * in production.
 */
if (! app()->isProduction()) {
    Route::middleware(['auth', 'verified'])
        ->prefix('ui-kit')
        ->name('ui-kit.')
        ->controller(UiKitController::class)
        ->group(function () {
            Route::get('/', 'show')->name('show');
            Route::post('form', 'submit')->name('submit');
        });
}

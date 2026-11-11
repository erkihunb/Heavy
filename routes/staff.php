<?php

use App\Http\Controllers\Staff\PermitReviewController;
use App\Models\Permit;
use Illuminate\Support\Facades\Route;

/*
 * The back office of the Authority. Every route is authorised by a Policy;
 * staff must also have confirmed two-factor authentication (web middleware).
 */
Route::middleware(['auth', 'verified', 'can:viewQueue,'.Permit::class])
    ->prefix('staff')
    ->name('staff.')
    ->controller(PermitReviewController::class)
    ->group(function () {
        Route::get('permits', 'index')->name('permits.index');
        Route::get('permits/{permit}', 'show')->name('permits.show');

        Route::post('permits/{permit}/start-review', 'startReview')->name('permits.start-review');
        Route::post('permits/{permit}/recommend', 'recommend')->name('permits.recommend');
        Route::post('permits/{permit}/request-changes', 'requestChanges')->name('permits.request-changes');
        Route::post('permits/{permit}/approve', 'approve')->name('permits.approve');
        Route::post('permits/{permit}/reject', 'reject')->name('permits.reject');
    });

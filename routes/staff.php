<?php

use App\Http\Controllers\Staff\PaymentController;
use App\Http\Controllers\Staff\PermitReviewController;
use App\Models\Payment;
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

/*
 * The cashier's payment verification (spec §2: Cashier / Finance).
 */
Route::middleware(['auth', 'verified', 'can:viewQueue,'.Payment::class])
    ->prefix('staff')
    ->name('staff.')
    ->controller(PaymentController::class)
    ->group(function () {
        Route::get('payments', 'index')->name('payments.index');
        Route::get('payments/{payment}', 'show')->name('payments.show');
        Route::get('payments/{payment}/receipt', 'receipt')->name('payments.receipt');

        Route::post('payments/{payment}/verify', 'verify')->name('payments.verify');
        Route::post('payments/{payment}/reject', 'reject')->name('payments.reject');
    });

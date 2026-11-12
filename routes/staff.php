<?php

use App\Http\Controllers\Officer\VerificationController as OfficerVerificationController;
use App\Http\Controllers\Staff\ExportController;
use App\Http\Controllers\Staff\PaymentController;
use App\Http\Controllers\Staff\PermitDeskController;
use App\Http\Controllers\Staff\PermitReviewController;
use App\Http\Controllers\Staff\ReportController;
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
        Route::post('permits/{permit}/revoke', 'revoke')->name('permits.revoke');
        Route::post('permits/{permit}/reissue', 'reissue')->name('permits.reissue');
    });

/*
 * After the decision: payment at the cashier's desk, issuing and printing,
 * the head office's comments, and the scanned request letter.
 */
Route::middleware(['auth', 'verified', 'can:viewQueue,'.Permit::class])
    ->prefix('staff')
    ->name('staff.')
    ->controller(PermitDeskController::class)
    ->group(function () {
        Route::post('permits/{permit}/payment', 'recordPayment')
            ->middleware('throttle:30,1')
            ->name('permits.record-payment');
        Route::post('permits/{permit}/issue', 'issue')->name('permits.issue');
        Route::post('permits/{permit}/comments', 'comment')->name('permits.comments.store');
    });

// The scanned letter: also for the Registrar who took the request (PermitPolicy::viewLetter).
Route::middleware(['auth', 'verified'])
    ->get('staff/permits/{permit}/letter', [PermitDeskController::class, 'letter'])
    ->name('staff.permits.letter');

/*
 * The traffic officer's tools (spec §7). Scanning a QR code opens the public
 * /v/{token} page, which shows officers the officer view.
 */
Route::middleware(['auth', 'verified', 'can:scan,'.Permit::class])
    ->prefix('officer')
    ->name('officer.')
    ->controller(OfficerVerificationController::class)
    ->group(function () {
        Route::get('verify', 'lookup')->name('verify');
        Route::post('permits/{permit}/reports', 'report')
            ->middleware('throttle:20,1')
            ->name('permits.reports.store');
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

/*
 * Reporting and exports (spec §11): Approvers, Cashiers and Admins.
 */
Route::middleware(['auth', 'verified', 'can:viewReports'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

        Route::get('exports', [ExportController::class, 'index'])->name('exports.index');
        Route::post('exports', [ExportController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('exports.store');
        Route::get('exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');
    });

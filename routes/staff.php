<?php

use App\Http\Controllers\Officer\VerificationController as OfficerVerificationController;
use App\Http\Controllers\Staff\ExportController;
use App\Http\Controllers\Staff\InstitutionPermitController;
use App\Http\Controllers\Staff\InstitutionVehicleController;
use App\Http\Controllers\Staff\LetterController;
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
    ->name('staff.')
    ->controller(PermitReviewController::class)
    ->group(function () {
        Route::get('queue', 'index')->name('permits.index');
        Route::get('queue/{permit}', 'show')->name('permits.show');
        // One page per role (PermitReviewController::desks()).
        Route::get('queue/{permit}/decision', 'decision')->name('permits.decision');
        Route::get('queue/{permit}/payment', 'payment')->name('permits.payment-desk');
        Route::get('queue/{permit}/issue', 'issuing')->name('permits.issue-desk');

        Route::post('queue/{permit}/start-review', 'startReview')->name('permits.start-review');
        Route::post('queue/{permit}/recommend', 'recommend')->name('permits.recommend');
        Route::post('queue/{permit}/request-changes', 'requestChanges')->name('permits.request-changes');
        Route::post('queue/{permit}/approve', 'approve')->name('permits.approve');
        Route::post('queue/{permit}/reject', 'reject')->name('permits.reject');
        Route::post('queue/{permit}/revoke', 'revoke')->name('permits.revoke');
        Route::post('queue/{permit}/reissue', 'reissue')->name('permits.reissue');
    });

/*
 * After the decision: payment at the cashier's desk, issuing and printing,
 * the head office's comments, and the scanned request letter.
 */
Route::middleware(['auth', 'verified', 'can:viewQueue,'.Permit::class])
    ->name('staff.')
    ->controller(PermitDeskController::class)
    ->group(function () {
        Route::post('queue/{permit}/payment', 'recordPayment')
            ->middleware('throttle:30,1')
            ->name('permits.record-payment');
        Route::post('queue/{permit}/issue', 'issue')->name('permits.issue');
        Route::post('queue/{permit}/comments', 'comment')->name('permits.comments.store');
    });

// The scanned letter: also for the Registrar who took the request (PermitPolicy::viewLetter).
Route::middleware(['auth', 'verified'])
    ->get('queue/{permit}/letter', [PermitDeskController::class, 'letter'])
    ->name('staff.permits.letter');

// A request letter for several vehicles, each with a permit of its own, followed and decided
// together: whoever may see its permits, and the Registrar of its branch (LetterController).
Route::middleware(['auth', 'verified'])
    ->prefix('letters/{letter}')
    ->name('letters.')
    ->controller(LetterController::class)
    ->whereUlid('letter')
    ->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('send-for-review', 'sendForReview')->middleware('throttle:30,1')->name('send-for-review');
        Route::post('recommend', 'recommend')->middleware('throttle:30,1')->name('recommend');
        Route::post('approve', 'approve')->middleware('throttle:30,1')->name('approve');
        Route::post('reject', 'reject')->middleware('throttle:30,1')->name('reject');
        Route::post('payment', 'payment')->middleware('throttle:30,1')->name('payment');
        Route::post('issue', 'issue')->middleware('throttle:30,1')->name('issue');
        Route::get('documents/{language}', 'documents')
            ->whereIn('language', ['am', 'en'])
            ->middleware('throttle:20,1')
            ->name('documents');
    });

/*
 * The traffic officer's tools (spec §7). Scanning a QR code opens the public
 * /v/{token} page, which shows officers the officer view.
 */
Route::middleware(['auth', 'verified', 'can:scan,'.Permit::class])
    ->prefix('roadside')
    ->name('officer.')
    ->controller(OfficerVerificationController::class)
    ->group(function () {
        Route::get('/', 'lookup')->name('verify');
        Route::post('permits/{permit}/reports', 'report')
            ->middleware('throttle:20,1')
            ->name('permits.reports.store');
    });

/*
 * The Authority's own vehicles and their permits, given directly by whoever
 * holds institution-permits.issue (PermitPolicy::issueInstitutional).
 */
Route::middleware(['auth', 'verified', 'can:issueInstitutional,'.Permit::class])
    ->name('staff.institution-permits.')
    ->controller(InstitutionPermitController::class)
    ->group(function () {
        Route::get('institution-permits', 'index')->name('index');
        Route::get('institution-permits/create', 'create')->name('create');
        Route::post('institution-permits', 'store')->middleware('throttle:30,1')->name('store');
        Route::post('institution-permits/{permit}/revoke', 'revoke')->name('revoke');
    });

/*
 * The register of the Authority's own vehicles: list, register, change, remove.
 */
Route::middleware(['auth', 'verified', 'can:issueInstitutional,'.Permit::class])
    ->name('staff.institution-vehicles.')
    ->controller(InstitutionVehicleController::class)
    ->group(function () {
        Route::get('institution-vehicles', 'index')->name('index');
        Route::get('institution-vehicles/create', 'create')->name('create');
        Route::post('institution-vehicles', 'store')->middleware('throttle:60,1')->name('store');
        Route::get('institution-vehicles/{vehicle}/edit', 'edit')->name('edit');
        Route::put('institution-vehicles/{vehicle}', 'update')->name('update');
        Route::delete('institution-vehicles/{vehicle}', 'destroy')->name('destroy');
    });

/*
 * The record of payments taken by the cashier (spec §2: Cashier / Finance), read only:
 * payments are recorded on the letter's page (or the permit's payment desk).
 */
Route::middleware(['auth', 'verified', 'can:viewQueue,'.Payment::class])
    ->name('staff.')
    ->controller(PaymentController::class)
    ->group(function () {
        Route::get('payment-queue', 'index')->name('payments.index');
        Route::get('payment-queue/{payment}', 'show')->name('payments.show');
        Route::get('payment-queue/{payment}/receipt', 'receipt')->name('payments.receipt');
    });

/*
 * Reporting and exports (spec §11): Approvers, Cashiers and Admins.
 */
Route::middleware(['auth', 'verified', 'can:viewReports'])
    ->name('staff.')
    ->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

        Route::get('exports', [ExportController::class, 'index'])->name('exports.index');
        Route::post('exports', [ExportController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('exports.store');
        Route::get('exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');
    });

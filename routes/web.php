<?php

use App\Domain\Settings\Branding;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PermitDocumentController;
use App\Http\Controllers\PublicVerificationController;
use App\Http\Middleware\VerificationHeaders;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// The uploaded logo and browser-tab icon (System settings → General).
Route::get('branding/{asset}', BrandingController::class)
    ->whereIn('asset', Branding::ASSETS)
    ->name('branding');

Route::put('locale', [LocaleController::class, 'update'])
    ->middleware('throttle:30,1')
    ->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // The permit PDF, Amharic or English: a temporary signed link, and the policy is checked again (spec §8).
    Route::get('permits/{permit}/document/{language?}', PermitDocumentController::class)
        ->whereIn('language', ['am', 'en'])
        ->middleware('signed')
        ->name('permits.document');
});

/*
 * Permit verification (spec §7). Public, server-rendered, rate limited per IP,
 * never cached or indexed. The QR code points to /v/{token}.
 */
Route::middleware(['throttle:verification', VerificationHeaders::class])
    ->controller(PublicVerificationController::class)
    ->group(function () {
        Route::get('v/{token}', 'token')->where('token', '[A-Za-z0-9_-]{1,64}')->name('verification.token');
        Route::get('verify', 'form')->name('verification.form');
        Route::post('verify', 'lookup')->name('verification.lookup');
    });

require __DIR__.'/applicant.php';
require __DIR__.'/staff.php';
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/ui-kit.php';

<?php

use App\Exceptions\DuplicateReceipt;
use App\Exceptions\DuplicateVehiclePlate;
use App\Exceptions\PermitException;
use App\Exceptions\UserAlreadyHasOrganization;
use App\Exceptions\VehiclePlateLocked;
use App\Http\Middleware\EnsureUserHasOrganization;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireTwoFactorForStaff;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'organization' => EnsureUserHasOrganization::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
            RequireTwoFactorForStaff::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Domain exceptions become ordinary HTTP answers, so controllers stay thin.
        $exceptions->map(fn (DuplicateVehiclePlate $e) => ValidationException::withMessages([
            'plate' => $e->userMessage(),
        ]));

        $exceptions->map(fn (VehiclePlateLocked $e) => ValidationException::withMessages([
            'plate' => $e->userMessage(),
        ]));

        // Spec §4.5: a duplicate receipt is a validation error on the receipt number.
        $exceptions->map(fn (DuplicateReceipt $e) => ValidationException::withMessages([
            'receipt_number' => $e->userMessage(),
        ]));

        $exceptions->map(fn (UserAlreadyHasOrganization $e) => new ConflictHttpException($e->getMessage(), $e));

        // A permit rule stopped an Action (wrong stage, overlapping permit, …):
        // nothing was changed, and the person is told why on the page they were on.
        $exceptions->render(fn (PermitException $e, Request $request) => $request->expectsJson()
            ? response()->json(['message' => $e->userMessage()], Response::HTTP_UNPROCESSABLE_ENTITY)
            : back()->with('warning', $e->userMessage()));
    })->create();

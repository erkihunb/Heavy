<?php

use App\Domain\Settings\Branding;
use App\Exceptions\DuplicateReceipt;
use App\Exceptions\DuplicateVehiclePlate;
use App\Exceptions\PermitException;
use App\Exceptions\UserAlreadyHasOrganization;
use App\Exceptions\VehiclePlateLocked;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireTwoFactorForStaff;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ThrottleAuthForms;
use App\Support\FrontendTranslations;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance_mode', 'sidebar_state']);

        // Every response, including errors and the health check (spec §15).
        $middleware->append(SecurityHeaders::class);

        // Behind the reverse proxy that ends TLS, so that HTTPS, the client IP (rate
        // limits, scan log) and secure cookies are seen correctly (spec §16).
        $proxies = env('TRUSTED_PROXIES');

        if (is_string($proxies) && $proxies !== '') {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        $middleware->web(append: [
            SetLocale::class,
            EnsureAccountIsActive::class,
            RequireTwoFactorForStaff::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ThrottleAuthForms::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Error tracking (spec §16); does nothing until SENTRY_LARAVEL_DSN is set.
        Integration::handles($exceptions);

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

        // Error pages in the same design as the rest of the system (EUISIS). An
        // expired form (419) sends the person back with a notice instead; JSON
        // callers and the debug page in development keep Laravel's own answer.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson() || ! in_array($status, [403, 404, 405, 419, 429, 500, 503], true)) {
                return $response;
            }

            if ($status >= 500 && config('app.debug')) {
                return $response;
            }

            if ($status === 419 && $request->headers->has('referer')) {
                return back()->with('warning', __('errors.messages.419'));
            }

            // Pass what the page needs itself: on a URL that matches no route the
            // web middleware (and so the shared props) never ran.
            return Inertia::render('error', [
                'status' => $status,
                'locale' => app()->getLocale(),
                'translations' => FrontendTranslations::for(app()->getLocale()),
                'branding' => Branding::shared(),
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();

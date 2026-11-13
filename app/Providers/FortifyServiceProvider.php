<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // A deactivated account keeps its history but cannot sign in (EUISIS RBAC).
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->where(Fortify::username(), mb_strtolower(trim((string) $request->input(Fortify::username()))))->first();

            if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            if (! $user->isActive()) {
                throw ValidationException::withMessages([Fortify::username() => __('auth.deactivated')]);
            }

            // Applicants do not sign in: their requests are recorded and handled at the office.
            if (! $user->maySignIn()) {
                throw ValidationException::withMessages([Fortify::username() => __('auth.no_sign_in')]);
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute((int) config('permits.security.login_per_minute'))->by($throttleKey);
        });

        // Registration and password forms (App\Http\Middleware\ThrottleAuthForms):
        // five a minute and twenty an hour per form and IP address.
        RateLimiter::for('auth-forms', function (Request $request) {
            $key = $request->route()?->getName().'|'.$request->ip();

            return [
                Limit::perMinute(5)->by('minute|'.$key),
                Limit::perHour(20)->by('hour|'.$key),
            ];
        });
    }
}

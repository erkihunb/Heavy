<?php

namespace App\Providers;

use App\Domain\Documents\ChromePdfRenderer;
use App\Domain\Documents\FakePdfRenderer;
use App\Domain\Documents\PdfRenderer;
use App\Domain\Settings\SystemSettings;
use App\Domain\Sms\HttpSmsGateway;
use App\Domain\Sms\LogSmsGateway;
use App\Domain\Sms\SmsGateway;
use App\Enums\Permission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, fn (): SmsGateway => match (config('services.sms.driver')) {
            'log' => new LogSmsGateway,
            'http' => new HttpSmsGateway(
                (string) config('services.sms.http.url'),
                (string) config('services.sms.http.token'),
                config('services.sms.http.sender'),
            ),
            default => throw new InvalidArgumentException('Unknown SMS_DRIVER ['.config('services.sms.driver').'].'),
        });

        $this->app->bind(PdfRenderer::class, fn (): PdfRenderer => match (config('permits.pdf_renderer')) {
            'chrome' => new ChromePdfRenderer(
                (string) config('services.chrome.binary'),
                (int) config('services.chrome.timeout'),
            ),
            'fake' => new FakePdfRenderer,
            default => throw new InvalidArgumentException('Unknown PERMITS_PDF_RENDERER ['.config('permits.pdf_renderer').']: use "chrome" or "fake".'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Settings an Admin saved override config/permits.php, here and in every queued
        // job, so that a long-running worker sees a change without a restart.
        $settings = $this->app->make(SystemSettings::class);
        $settings->apply();
        Queue::before(function () use ($settings): void {
            $settings->apply();
        });

        // The reporting dashboard (spec §11): Approvers, Cashiers and Admins.
        Gate::define('viewReports', fn (User $user): bool => $user->can(Permission::ViewReports->value));

        // Administration without a model of its own (spec §11).
        Gate::define('manageSettings', fn (User $user): bool => $user->can(Permission::ManageSettings->value));
        Gate::define('viewAuditLog', fn (User $user): bool => $user->can(Permission::ViewAuditLog->value));

        // Permit verification: per IP address, for the public and officers alike (spec §7).
        RateLimiter::for('verification', fn (Request $request): Limit => Limit::perMinute(
            (int) config('permits.verification_per_minute'),
        )->by($request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // A single resource is sent to the frontend as a plain object, not wrapped in {data}.
        JsonResource::withoutWrapping();

        // Surface N+1 queries during development and in the test suite.
        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min((int) config('permits.security.password_min_length'))
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

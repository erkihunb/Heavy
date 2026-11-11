<?php

namespace App\Providers;

use App\Domain\Documents\FakePdfRenderer;
use App\Domain\Documents\GotenbergPdfRenderer;
use App\Domain\Documents\PdfRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PdfRenderer::class, fn (): PdfRenderer => config('permits.pdf_renderer') === 'fake'
            ? new FakePdfRenderer
            : new GotenbergPdfRenderer(
                (string) config('services.gotenberg.url'),
                (int) config('services.gotenberg.timeout'),
            ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

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
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

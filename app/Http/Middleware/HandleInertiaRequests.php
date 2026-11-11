<?php

namespace App\Http\Middleware;

use App\Support\FrontendTranslations;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // An explicit whitelist: never serialize the whole model to the browser.
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'locale' => $user->locale,
                    // For navigation and display only; authorization is enforced by Policies.
                    'roles' => $user->getRoleNames()->values()->all(),
                ],
                'mustEnableTwoFactor' => (bool) $user?->mustEnableTwoFactor(),
            ],
            'locale' => app()->getLocale(),
            'locales' => config('localization.supported'),
            'translations' => fn (): array => FrontendTranslations::for(app()->getLocale()),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}

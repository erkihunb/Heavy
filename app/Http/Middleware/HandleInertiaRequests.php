<?php

namespace App\Http\Middleware;

use App\Domain\Settings\Branding;
use App\Domain\Settings\ShellSettings;
use App\Support\EthiopianCalendar;
use App\Support\FrontendTranslations;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\OnceProp;
use Inertia\Support\Header;

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
                    'has_organization' => $user->organization_id !== null,
                    // For navigation and display only; authorization is enforced by Policies.
                    'roles' => $user->getRoleNames()->values()->all(),
                ],
                'mustEnableTwoFactor' => (bool) $user?->mustEnableTwoFactor(),                // For showing or hiding navigation only (resources/js/config/navigation.ts).
                'permissions' => $user === null ? [] : $user->getAllPermissions()->pluck('name')->values()->all(),
                'isSuperAdmin' => false,
            ],
            // One-off messages set by the previous request.
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'warning' => fn (): ?string => $request->session()->get('warning'),
            ],
            'locale' => app()->getLocale(),
            'locales' => config('localization.supported'),
            'translations' => $this->translations($request),
            // Today in both calendars, for the date shown in the page chrome.
            'today' => fn (): ?array => EthiopianCalendar::present(
                now(config('localization.display_timezone')),
                app()->getLocale(),
            ),
            // A person's own choice, otherwise the default an Admin set.
            'sidebarOpen' => $request->hasCookie('sidebar_state')
                ? $request->cookie('sidebar_state') === 'true'
                : ! config('permits.appearance.sidebar_compact', false),
            // How the interface behaves (System settings → Appearance).
            'appearance' => [
                'allowUserMode' => (bool) config('permits.appearance.allow_user_mode', true),
                'showBreadcrumbs' => (bool) config('permits.appearance.show_breadcrumbs', true),
                'showLanguageSwitcher' => (bool) config('permits.appearance.show_language_switcher', true),
            ],
            // Logo, icon, support contacts and the sign-in message (System settings → General).
            'branding' => fn (): array => Branding::shared(),
            // What the signed-in shell (resources/js/shell) reads, keyed as in the source kit.
            'settings' => fn (): array => ShellSettings::shared(),
        ];
    }

    /**
     * Every word of the interface in the reader's language (about 125 KB of
     * JSON): the same on every page, so it is sent with the first page only
     * and the browser keeps it (an Inertia once prop). Its key names the
     * language and the version of the wording, so switching language or a
     * deploy that changes the words sends them again. A browser that has held
     * the words of more than one language says it has both keys and cannot
     * tell which it shows now, so it is always sent them.
     */
    private function translations(Request $request): OnceProp
    {
        $locale = app()->getLocale();
        $key = "translations.{$locale}.".FrontendTranslations::version($locale);

        $held = array_filter(
            explode(',', (string) $request->header(Header::EXCEPT_ONCE_PROPS)),
            fn (string $name): bool => str_starts_with(trim($name), 'translations.'),
        );

        return Inertia::once(fn (): array => FrontendTranslations::for($locale))
            ->as($key)
            ->fresh(count($held) > 1);
    }
}

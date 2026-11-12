<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" {!! \App\Domain\Settings\ThemeStyle::attributesHtml() !!}>
<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover: the page reaches under a phone's notch and home bar, and pads itself with the safe-area insets. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#122170">
    <title>@yield('title') · {{ __('verification.title') }}</title>
    {!! \App\Domain\Settings\Branding::faviconTag() !!}
    {{-- Styles first: the page works without JavaScript; a page may add a script that enhances it. --}}
    @vite(['resources/css/app.css'])
    @stack('scripts')
    @if ($themeCss = \App\Domain\Settings\ThemeStyle::css())
        <style nonce="{{ Vite::cspNonce() }}">{!! $themeCss !!}</style>
    @endif
</head>
<body class="min-h-screen bg-background font-sans text-foreground antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:rounded-md focus:bg-card focus:px-4 focus:py-2">
        {{ __('common.skip_to_content') }}
    </a>

    {{-- An app bar: pinned while the page scrolls, below a phone's notch. --}}
    <header class="sticky top-0 z-20 border-b border-border bg-card/95 pt-[env(safe-area-inset-top)] backdrop-blur">
        <div class="brand-bar" aria-hidden="true"></div>
        <div class="mx-auto flex max-w-2xl items-center justify-between gap-3 px-4 py-2.5 md:max-w-3xl">
            <div class="flex min-w-0 items-center gap-2.5">
                <img src="{{ \App\Domain\Settings\Branding::url('logo') ?? '/favicon.svg' }}" alt="" class="size-9 shrink-0 object-contain" width="36" height="36">
                <div class="min-w-0">
                    {{-- The short name on phones, where the full one would wrap over three lines. --}}
                    <p class="truncate text-sm font-bold leading-tight sm:hidden">{{ __('common.authority_short') }}</p>
                    <p class="hidden text-sm font-bold leading-tight sm:block">{{ __('common.authority') }}</p>
                    <p class="truncate text-xs text-muted-foreground">{{ __('verification.title') }}</p>
                </div>
            </div>
            <nav aria-label="{{ __('common.language') }}" class="flex shrink-0 rounded-full bg-muted p-1">
                @foreach (config('localization.supported') as $code => $name)
                    <form method="POST" action="{{ route('locale.update') }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="locale" value="{{ $code }}">
                        <button type="submit"
                                @if (app()->getLocale() === $code) aria-current="true" @endif
                                class="min-h-9 rounded-full px-3 text-xs font-bold focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:text-sm {{ app()->getLocale() === $code ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground' }}">
                            {{ $name }}
                        </button>
                    </form>
                @endforeach
            </nav>
        </div>
    </header>

    <main id="main" class="mx-auto max-w-2xl px-4 py-4 sm:py-8 md:max-w-3xl">
        @yield('content')
    </main>

    {{-- A page's action bar, fixed to the bottom of a phone's screen. --}}
    @stack('action-bar')

    @php($branding = \App\Domain\Settings\Branding::shared())
    @if ($branding['supportEmail'] || $branding['supportPhone'])
        <footer class="mx-auto flex max-w-2xl flex-wrap justify-center gap-x-4 gap-y-1 px-4 pb-8 text-xs text-muted-foreground">
            <span>{{ __('common.support') }}:</span>
            @if ($branding['supportEmail'])
                <a href="mailto:{{ $branding['supportEmail'] }}" class="font-medium text-foreground underline-offset-4 hover:underline" translate="no">{{ $branding['supportEmail'] }}</a>
            @endif
            @if ($branding['supportPhone'])
                <a href="tel:{{ preg_replace('/[^\d+]/', '', $branding['supportPhone']) }}" class="font-medium text-foreground tabular-nums underline-offset-4 hover:underline" translate="no">{{ $branding['supportPhone'] }}</a>
            @endif
        </footer>
    @endif
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" {!! \App\Domain\Settings\ThemeStyle::attributesHtml() !!}>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#122170">
    <title>@yield('title') · {{ __('verification.title') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    {{-- Styles only: the page works without JavaScript. --}}
    @vite(['resources/css/app.css'])
    @if ($themeCss = \App\Domain\Settings\ThemeStyle::css())
        <style nonce="{{ Vite::cspNonce() }}">{!! $themeCss !!}</style>
    @endif
</head>
<body class="min-h-screen bg-background font-sans text-foreground antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:rounded-md focus:bg-card focus:px-4 focus:py-2">
        {{ __('common.skip_to_content') }}
    </a>

    <header class="border-b border-border bg-card">
        <div class="brand-bar" aria-hidden="true"></div>
        <div class="mx-auto flex max-w-2xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex min-w-0 items-center gap-3">
                <img src="/favicon.svg" alt="" class="size-8 shrink-0" width="32" height="32">
                <div class="min-w-0">
                    <p class="text-sm font-bold leading-tight">{{ __('common.authority') }}</p>
                    <p class="text-xs text-muted-foreground">{{ __('verification.title') }}</p>
                </div>
            </div>
            <nav aria-label="{{ __('common.language') }}" class="flex gap-1">
                @foreach (config('localization.supported') as $code => $name)
                    <form method="POST" action="{{ route('locale.update') }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="locale" value="{{ $code }}">
                        <button type="submit"
                                @if (app()->getLocale() === $code) aria-current="true" @endif
                                class="rounded-control px-3 py-1.5 text-sm font-semibold focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none {{ app()->getLocale() === $code ? 'bg-primary text-primary-foreground' : 'hover:bg-muted' }}">
                            {{ $name }}
                        </button>
                    </form>
                @endforeach
            </nav>
        </div>
    </header>

    <main id="main" class="mx-auto max-w-2xl px-4 py-6 sm:py-10">
        @yield('content')
    </main>
</body>
</html>

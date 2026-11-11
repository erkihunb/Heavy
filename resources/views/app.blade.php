<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" {!! \App\Domain\Settings\ThemeStyle::attributesHtml() !!} data-default-appearance="{{ $defaultAppearance ?? 'system' }}" data-user-mode="{{ config('permits.appearance.allow_user_mode') ? 'on' : 'off' }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #f6f6f6;
            }

            html.dark {
                background-color: #111112;
            }
        </style>

        @if ($themeCss = \App\Domain\Settings\ThemeStyle::css())
            {{-- Colours chosen in System settings → Appearance (validated hex values). --}}
            <style nonce="{{ Vite::cspNonce() }}">{!! $themeCss !!}</style>
        @endif

        <title inertia>{{ config('app.name') }}</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <meta name="theme-color" content="#122170">

        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

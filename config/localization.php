<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | Locales a user may switch between, keyed by locale code with the name of
    | the language written in that language. The default locale is
    | `app.locale` (Amharic) and the fallback is `app.fallback_locale`.
    |
    */

    'supported' => [
        'am' => 'አማርኛ',
        'en' => 'English',
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend Translation Namespaces
    |--------------------------------------------------------------------------
    |
    | Language files (lang/{locale}/{namespace}.php) that are sent to the
    | React frontend as the `translations` shared prop. Keep this list short:
    | everything in it is shipped on every first page load. The TypeScript
    | key type is generated from it by `php artisan translations:types`.
    |
    */

    'frontend_namespaces' => [
        'common',
        'nav',
        'auth_ui',
        'settings',
        'process',
        'landing',
        'dashboard',
        'roles',
        'organization',
        'vehicles',
    ],

    /*
    |--------------------------------------------------------------------------
    | Display Timezone
    |--------------------------------------------------------------------------
    |
    | Timestamps are stored in UTC (`app.timezone`). This is the timezone used
    | when a timestamp is shown to a person or turned into a calendar date.
    |
    */

    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Africa/Addis_Ababa'),

];

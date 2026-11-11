<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mandatory Two-Factor Authentication For Staff
    |--------------------------------------------------------------------------
    |
    | When enabled, any user holding a staff role (reviewer, approver, cashier,
    | traffic officer, admin) is confined to the two-factor setup screens until
    | they have confirmed two-factor authentication. Disable only for local
    | development; it must stay on in production (spec §2, §15).
    |
    */

    'staff_two_factor_required' => (bool) env('PERMITS_STAFF_TWO_FACTOR_REQUIRED', true),

    /*
    |--------------------------------------------------------------------------
    | Two-Step Approval
    |--------------------------------------------------------------------------
    |
    | When enabled, an Approver can approve or reject an application only after
    | a Reviewer has recorded a recommendation, and the Approver must be a
    | different person from that Reviewer (spec §14, question 5).
    |
    */

    'two_step_approval' => (bool) env('PERMITS_TWO_STEP_APPROVAL', true),

    /*
    |--------------------------------------------------------------------------
    | Validity Limits
    |--------------------------------------------------------------------------
    |
    | The longest period one permit may cover, counting both the first and the
    | last day (spec §4.6), and how many trailers one permit may list next to
    | its primary vehicle (spec §4.3).
    |
    */

    'max_duration_days' => (int) env('PERMITS_MAX_DURATION_DAYS', 90),

    'max_trailers' => (int) env('PERMITS_MAX_TRAILERS', 2),

    /*
    |--------------------------------------------------------------------------
    | Permit Number Prefix
    |--------------------------------------------------------------------------
    |
    | Permit numbers are "<prefix>-<Ethiopian year>-<six digits>", for example
    | TMA-HV-2018-000123 (spec §4.7).
    |
    */

    'number_prefix' => env('PERMITS_NUMBER_PREFIX', 'TMA-HV'),

    /*
    |--------------------------------------------------------------------------
    | Separation Of Duties
    |--------------------------------------------------------------------------
    |
    | When enabled, the person who approved a permit cannot be the one who
    | verifies its payment (spec §2).
    |
    */

    'separation_of_duties' => (bool) env('PERMITS_SEPARATION_OF_DUTIES', true),

    /*
    |--------------------------------------------------------------------------
    | Private Documents
    |--------------------------------------------------------------------------
    |
    | The filesystem disk payment receipts (and, later, permit documents) are
    | stored on. It must never be publicly reachable: files are served only
    | through controllers that authorise the request (spec §15). The largest
    | receipt file accepted, in kilobytes (spec §11: 5 MB).
    |
    */

    'documents_disk' => env('PERMITS_DOCUMENTS_DISK', 'documents'),

    'receipt_max_kilobytes' => (int) env('PERMITS_RECEIPT_MAX_KB', 5120),

    /*
    |--------------------------------------------------------------------------
    | Verification
    |--------------------------------------------------------------------------
    |
    | The address printed in every permit's QR code is "<verify_url>/v/<token>"
    | (spec §7: https://{VERIFY_DOMAIN}/v/{token}). It must be the public HTTPS
    | address of this application. Changing it later breaks printed permits.
    |
    | Whether the public page names the permit holder's organization (open
    | question §14.4; shown by default). How many checks one IP address may
    | make per minute.
    |
    */

    'verify_url' => env('PERMITS_VERIFY_URL', env('APP_URL', 'http://localhost')),

    'public_shows_organization' => (bool) env('PERMITS_PUBLIC_SHOWS_ORGANIZATION', true),

    'verification_per_minute' => (int) env('PERMITS_VERIFICATION_PER_MINUTE', 30),

    /*
    |--------------------------------------------------------------------------
    | Time-Window Rules — PLACEHOLDER TEXT
    |--------------------------------------------------------------------------
    |
    | The hours each permit type allows a heavy vehicle to move, printed on the
    | permit and shown on the verification page (open question §14.2). The
    | text below is a PLACEHOLDER and must be replaced with the Authority's
    | official wording before go-live (docs/DECISIONS.md).
    |
    */

    'time_windows' => [
        'full_day' => [
            'am' => '[ጊዜያዊ ጽሑፍ] ይህ ፍቃድ ተሽከርካሪው በገደብ ሰዓቶች ውስጥም ጨምሮ ቀኑን ሙሉ እንዲንቀሳቀስ ይፈቅዳል። ትክክለኛው የሰዓት ገደብ በባለስልጣኑ ይወሰናል።',
            'en' => '[PLACEHOLDER] This permit allows the vehicle to move throughout the day, including during restricted hours. The exact hours are set by the Authority.',
        ],
        'pass' => [
            'am' => '[ጊዜያዊ ጽሑፍ] ይህ የይለፍ ፍቃድ ተሽከርካሪው በገደብ ሰዓቶች ውስጥ ለአንድ ጉዞ እንዲያልፍ ይፈቅዳል። ትክክለኛው የሰዓት ገደብ በባለስልጣኑ ይወሰናል።',
            'en' => '[PLACEHOLDER] This pass allows the vehicle to pass through during restricted hours for a single trip. The exact hours are set by the Authority.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permit Document
    |--------------------------------------------------------------------------
    |
    | The official logo for the PDF header (open question §14.6). Until one is
    | provided, the document shows a clearly marked placeholder.
    |
    */

    'document' => [
        'logo_path' => env('PERMITS_DOCUMENT_LOGO_PATH'),
    ],

    // "gotenberg" (Chromium; required in production) or "fake" (tests, machines without Gotenberg).
    'pdf_renderer' => env('PERMITS_PDF_RENDERER', 'gotenberg'),

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Members of the permit's organization are e-mailed; the organization's
    | phone also gets an SMS when `sms` is on (provider: services.sms). The
    | "expires soon" reminder goes out once per permit, this many days before
    | its last day, at this time of day (Africa/Addis_Ababa).
    |
    */

    // Spreadsheet exports are kept this many days, then deleted by exports:prune.
    'exports' => [
        'keep_days' => (int) env('PERMITS_EXPORTS_KEEP_DAYS', 7),
    ],

    'notifications' => [
        'mail' => (bool) env('PERMITS_NOTIFY_MAIL', true),
        'sms' => (bool) env('PERMITS_NOTIFY_SMS', true),
        'expiring_days' => (int) env('PERMITS_EXPIRING_REMINDER_DAYS', 7),
        'reminder_time' => env('PERMITS_EXPIRING_REMINDER_TIME', '08:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Appearance
    |--------------------------------------------------------------------------
    |
    | The colour theme of the whole system and the light / dark mode a person
    | sees until they choose their own (System settings → Appearance).
    |
    */

    'appearance' => [
        // Colour preset; the three colours below override its brand, accent and sidebar.
        'theme' => env('PERMITS_THEME', 'navy'),
        'primary_color' => env('PERMITS_PRIMARY_COLOR'),
        'accent_color' => env('PERMITS_ACCENT_COLOR'),
        'sidebar_color' => env('PERMITS_SIDEBAR_COLOR'),
        // Interface style (swiss, classic, soft, contrast), buttons, cards and tables.
        'ui_style' => env('PERMITS_UI_STYLE', 'swiss'),
        'button_style' => 'auto',
        'card_radius' => 'auto',
        'table_density' => 'comfortable',
        'default_mode' => env('PERMITS_DEFAULT_MODE', 'system'),
        // Whether people may choose light or dark for themselves.
        'allow_user_mode' => true,
        'sidebar_compact' => false,
        'show_breadcrumbs' => true,
        'animations' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Password length required in production and login attempts allowed per
    | minute for one e-mail address and IP (spec §15).
    |
    */

    'security' => [
        'password_min_length' => (int) env('PERMITS_PASSWORD_MIN_LENGTH', 12),
        'login_per_minute' => (int) env('PERMITS_LOGIN_PER_MINUTE', 5),
    ],

];

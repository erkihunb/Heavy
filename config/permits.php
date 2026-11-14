<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mandatory Two-Factor Authentication For Staff
    |--------------------------------------------------------------------------
    |
    | When enabled, any user holding a staff role (reviewer, approver, cashier,
    | traffic officer, admin) is confined to the two-factor setup screens until
    | they have confirmed two-factor authentication. An Admin can change it in
    | System settings → Security; when off, two-factor authentication is
    | each person's own choice (spec §2, §15).
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

    'two_step_approval' => (bool) env('PERMITS_TWO_STEP_APPROVAL', false),

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
    | Permit Number Rule
    |--------------------------------------------------------------------------
    |
    | Permit numbers are "<prefix>-<Ethiopian year>-<six digits>", for example
    | AACRA-2018-000123 (spec §4.7). The format puts {prefix}, {branch}, {year}
    | and {seq} (the counter, restarted each Ethiopian year and padded to
    | `number_digits`) in order; {year} and {seq} must both be in it. {branch}
    | is the code of the branch the application was made at
    | (AACRA-BOL-2018-000123) and is left out, with its hyphen, for an
    | application made at the head office. An Admin changes all three
    | in System settings → General; a change applies to permits approved from
    | then on, and numbers already issued stay as they are.
    |
    */

    'number_prefix' => env('PERMITS_NUMBER_PREFIX', 'AACRA'),

    'number_format' => env('PERMITS_NUMBER_FORMAT', '{prefix}-{branch}-{year}-{seq}'),

    'number_digits' => (int) env('PERMITS_NUMBER_DIGITS', 6),

    // "full" prints the Ethiopian year as 2019, "short" as 19.
    'number_year_format' => env('PERMITS_NUMBER_YEAR_FORMAT', 'full'),

    // "yearly": the counter starts again each Ethiopian year; "never": it keeps counting.
    'number_reset' => env('PERMITS_NUMBER_RESET', 'yearly'),

    // The first counter value of a new year (or of the never-ending counter).
    'number_start' => (int) env('PERMITS_NUMBER_START', 1),

    // The code {type} prints for each permit type.
    'number_type_codes' => [
        'full_day' => env('PERMITS_NUMBER_TYPE_FULL_DAY', 'FD'),
        'pass' => env('PERMITS_NUMBER_TYPE_PASS', 'PS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificate code
    |--------------------------------------------------------------------------
    |
    | A short check code printed on each permit PDF and shown on the
    | verification page, e.g. CRT-7K3P-QX2M. It is computed with the app key
    | from the permit number, type, dates, organisation and plates, so a
    | printout whose details were altered no longer matches. The prefix and
    | the number of code characters (grouped by four) are Admin settings.
    |
    */

    'certificate_code' => [
        'enabled' => (bool) env('PERMITS_CERTIFICATE_CODE', true),
        'prefix' => env('PERMITS_CERTIFICATE_CODE_PREFIX', 'CRT'),
        'length' => (int) env('PERMITS_CERTIFICATE_CODE_LENGTH', 8),
    ],

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

        // The "ማሳሰቢያ" (notes) printed at the foot of the permit, as on the
        // Authority's paper permit; one list per language.
        'notes' => [
            'am' => [
                'ይህ ፈቃድ የባለስልጣኑ ማህተም ከሌለውና ስርዝ ድልዝ ካለበት አያገለግልም።',
                'የመንቀሳቀሻ ፈቃዱ ጊዜ ሲያበቃ ካርዱ ለባለስልጣን መ/ቤቱ ተመላሽ ይደረጋል።',
                'ይህ ፈቃድ ከተፈቀደለት ተሽከርካሪ ውጪ ለሌላ ተሽከርካሪ አሳልፎ መስጠት የተከለከለ ነው።',
                'ፈቃድ የተሰጠው ተሽከርካሪ በጎማ ወይም ጭነቱን ባለመሸፈኑ መንገድ ካበላሸ ፈቃዱ እስከመነጠቅ የሚደርስ እርምጃ ይወሰድበታል።',
                'ፈቃድ የተሰጣቸው ተሽከርካሪዎች ከተሰጣቸው ዓላማ ውጪ መጠቀም ፈጽሞ የተከለከለ ነው።',
                'በተከለከለ መንገድ ላይ የሚያሽከረክሩ እና ሌሎች የትራፊክ ደንብ መተላለፍ ጥፋቶች የሚፈጽሙ አሽከርካሪዎች በሕጉ መሠረት ተጠያቂ ይሆናሉ።',
            ],
            'en' => [
                'This permit is not valid without the Authority\'s stamp, or if anything on it is crossed out or altered.',
                'When the permit expires, the card is returned to the Authority\'s office.',
                'Handing this permit over to any vehicle other than the one it was issued for is prohibited.',
                'A permitted vehicle that damages the road with its tyres or an uncovered load faces action up to withdrawal of the permit.',
                'Using a permitted vehicle for any purpose other than the one stated is strictly prohibited.',
                'Drivers who use prohibited roads or commit other traffic violations are held accountable under the law.',
            ],
        ],
    ],

    // "chrome" (Google Chrome or Chromium on this server; required in production, see
    // services.chrome) or "fake" (tests, and machines without Chrome: blank placeholders).
    'pdf_renderer' => env('PERMITS_PDF_RENDERER', 'chrome'),

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
        'show_language_switcher' => true,
        'animations' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | The logo and browser-tab icon an Admin uploads (System settings →
    | General). Both hold a path on the local disk; empty means the built-in
    | mark. The logo is shown in the sidebar, the sign-in and public pages and
    | on the permit document (unless PERMITS_DOCUMENT_LOGO_PATH names a file).
    | The support contacts and the sign-in message are shown to everyone.
    |
    */

    'branding' => [
        'logo' => null,
        'favicon' => null,
        'login_message' => [
            'am' => null,
            'en' => null,
        ],
        'support_email' => env('PERMITS_SUPPORT_EMAIL'),
        'support_phone' => env('PERMITS_SUPPORT_PHONE'),
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
        // The password new staff accounts start with; they must change it at first sign-in.
        // Empty: the new person is e-mailed a link to choose a password instead.
        'default_password' => env('PERMITS_DEFAULT_PASSWORD'),
    ],

];

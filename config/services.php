<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Google Chrome or Chromium on this server, which prints permit documents
    // (spec §8): its path, or its name when it is on the PATH.
    'chrome' => [
        'binary' => env('CHROME_BINARY', 'google-chrome'),
        'timeout' => (int) env('CHROME_TIMEOUT', 60),
    ],

    // SMS provider (open question §14.7): "log" writes messages to the log;
    // "http" posts them as JSON to a provider's endpoint with a bearer token.
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'http' => [
            'url' => env('SMS_HTTP_URL'),
            'token' => env('SMS_HTTP_TOKEN'),
            'sender' => env('SMS_HTTP_SENDER'),
        ],
    ],

    // Telegram alerts to a staff group chat (System settings → Telegram).
    'telegram' => [
        'enabled' => (bool) env('TELEGRAM_ENABLED', false),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'alerts' => [
            'submitted' => true,
            'payment_submitted' => true,
            'revoked' => true,
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];

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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'fallback_api_key' => env('GEMINI_FALLBACK_API_KEY'),
        'api_keys' => env('GEMINI_API_KEYS'),
        // Tried in order; the next model is used when every key fails on the previous one.
        'models' => env('GEMINI_MODELS', 'gemini-3.5-flash-lite,gemini-3.1-flash-lite,gemini-3.5-flash,gemini-flash-latest'),
        // "low" keeps answers fast; leave empty for models without thinking support.
        'thinking_level' => env('GEMINI_THINKING_LEVEL', 'low'),
    ],

    // Google OAuth client used to send applications from the user's own Gmail account.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
    ],

    // Chrome or Edge, run headless to render PDFs and to fetch job pages that block plain HTTP clients.
    'chrome' => [
        'path' => env('CHROME_PATH'),
        'no_sandbox' => (bool) env('CHROME_NO_SANDBOX', false),
    ],

];

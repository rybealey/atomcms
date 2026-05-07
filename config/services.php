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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'turnstile' => [
        'key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
    ],

    'discord' => [
        'application_id' => env('DISCORD_APPLICATION_ID'),
        'client_id' => env('DISCORD_OAUTH_CLIENT_ID'),
        'client_secret' => env('DISCORD_OAUTH_CLIENT_SECRET'),
        'bot_token' => env('DISCORD_BOT_TOKEN'),
        'redirect_uri' => env('DISCORD_OAUTH_REDIRECT_URI'),
        'platform_name' => env('DISCORD_PLATFORM_NAME', 'Pixeltower'),
        'presence_webhook_secret' => env('DISCORD_PRESENCE_WEBHOOK_SECRET'),
    ],
];

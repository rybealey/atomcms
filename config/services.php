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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'bot_token' => env('DISCORD_BOT_TOKEN'),
        'guild_id' => env('DISCORD_GUILD_ID'),
        'invite_url' => env('DISCORD_INVITE_URL', 'https://discord.gg/pH5TQF84UZ'),
        // Role ids the bot manages. Any left empty is simply skipped, so the
        // integration can go live with just 'verified' and grow later.
        'roles' => [
            'verified' => env('DISCORD_VERIFIED_ROLE_ID'),
            'unverified' => env('DISCORD_UNVERIFIED_ROLE_ID'),
            'online' => env('DISCORD_ONLINE_ROLE_ID'),
            'vip' => env('DISCORD_VIP_ROLE_ID'),
            'staff' => env('DISCORD_STAFF_ROLE_ID'),
            'donor' => env('DISCORD_DONOR_ROLE_ID'),
            'committee' => env('DISCORD_COMMITTEE_ROLE_ID'),
        ],
    ],

    'github_deploy' => [
        'repo' => env('GITHUB_DEPLOY_REPO', 'rybealey/pixelrp'),
        'workflow' => env('GITHUB_DEPLOY_WORKFLOW', 'deploy.yml'),
        // Optional: the repo is public, and conditional requests keep
        // unauthenticated polling within GitHub's rate limit. A token only
        // raises the ceiling.
        'token' => env('GITHUB_DEPLOY_TOKEN'),
        // Defaults to base_path('CHANGELOG.md') (compose mounts it there);
        // falls back to raw.githubusercontent.com when absent.
        'changelog_path' => env('DEPLOY_CHANGELOG_PATH'),
    ],

    // pixelrp: the asset pass (App\Support\AssetPass). The same secret is
    // rendered into the web container's nginx, which checks every
    // /nitro-assets/ request against it. Unset means no pass is ever issued -
    // and nginx then turns every game file away - so compose.prod.yaml
    // refuses to start without it.
    // ttl: how long a pass lasts - an hour, twice the client's renewal (every
    // half hour, AssetPassRefresh.ts), so a copied pass is soon useless.
    // guard_pause_minutes: how long a browser guard catch keeps the account
    // from every game file (App\Support\AssetPause).
    'asset_pass' => [
        'secret' => env('ASSET_PASS_SECRET'),
        'ttl' => (int) env('ASSET_PASS_TTL', 3600),
        'guard_pause_minutes' => (int) env('ASSET_GUARD_PAUSE_MINUTES', 30),
    ],
];

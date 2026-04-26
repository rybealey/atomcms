<?php

return [
    'public_key'     => env('STRIPE_PUBLIC_KEY'),
    'secret_key'     => env('STRIPE_SECRET_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'currency'       => env('STRIPE_CURRENCY', 'USD'),

    'rate_cents_per_diamond' => 1,
    'min_diamonds'           => 100,
    'max_diamonds'           => 1_000_000,
];

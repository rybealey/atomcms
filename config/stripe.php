<?php

return [
    'secret' => env('STRIPE_SECRET'),
    'public' => env('STRIPE_PUBLIC'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

    'diamonds' => [
        'rate_usd_cents' => (int) env('STRIPE_DIAMOND_RATE_USD_CENTS', 1),
        'min_amount' => (int) env('STRIPE_DIAMOND_MIN_AMOUNT', 100),
        'max_amount' => (int) env('STRIPE_DIAMOND_MAX_AMOUNT', 100000),
        'amount_step' => (int) env('STRIPE_DIAMOND_AMOUNT_STEP', 100),
    ],
];

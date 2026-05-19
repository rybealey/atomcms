<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'badges' => [
            'driver' => 'local',
            'root' => storage_path('app/badges'),
        ],

        'ads' => [
            'driver' => 'local',
            'root' => storage_path('app/ads'),
        ],

        // Furniture Importer spool. MUST sit under /var/www/atomcms/ — php
        // runs with open_basedir = /var/www/atomcms/:/tmp/, so it cannot
        // touch /var/www/gamedata even though that's bind-mounted. The same
        // ./atomcms dir is also visible to the importer-worker (at
        // /workspace/atomcms), so storage/app/import_spool is the shared
        // rendezvous. Laravel's storage/app/.gitignore keeps it out of git;
        // the optimize cycle only clears storage/framework, never this.
        // The worker watches <root>/<jobid>/ for job.json.
        'import_spool' => [
            'driver' => 'local',
            'root' => env('IMPORT_SPOOL_ROOT', storage_path('app/import_spool')),
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];

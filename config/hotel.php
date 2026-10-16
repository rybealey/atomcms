<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hotel clients
    |--------------------------------------------------------------------------
    |
    | Which game clients the hotel serves. The badge page only maintains
    | external texts for enabled clients, and the flash paths tell it where
    | client assets live below the public directory.
    |
    */

    'client' => [

        'nitro' => [
            'enabled' => env('CLIENT_NITRO_ENABLED', true),
        ],

        'flash' => [
            'enabled' => env('CLIENT_FLASH_ENABLED', false),

            // Path to the flash client's files, relative to the public directory.
            'relative_files_path' => env('CLIENT_FLASH_FILES_PATH', 'client/flash'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Starting looks
    |--------------------------------------------------------------------------
    |
    | The figures a new citizen can pick from on the registration form. Each
    | is rendered by the imager on the form itself, so every part must exist
    | in the hotel's figuredata. The first is the default.
    |
    */

    'starting_looks' => [
        'street' => ['label' => 'Street', 'figure' => 'hd-180-1.hr-100-61.ch-210-66.lg-270-82.sh-290-80'],
        'smart' => ['label' => 'Smart', 'figure' => 'hd-180-2.hr-115-45.ch-215-1.lg-275-64.sh-305-62'],
        'sporty' => ['label' => 'Sporty', 'figure' => 'hd-180-3.hr-125-31.ch-255-66.lg-285-82.sh-300-64'],
        'night' => ['label' => 'Night shift', 'figure' => 'hd-180-4.hr-155-45.ch-225-62.lg-280-110.sh-295-62'],
    ],

];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    'auth' => [
        'driver' => env('MOTIVA_AUTH_DRIVER', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | DIGIO
    |--------------------------------------------------------------------------
    */

    'digio' => [

        'session' => [
            'cookie' => env('DIGIO_SESSION_COOKIE', 'sessionId'),

            'prefix' => env('DIGIO_SESSION_PREFIX', 'DIGIO'),

            'format' => env(
                'DIGIO_SESSION_FORMAT',
                'auto'
            ),
        ],

        'redis' => [
            'host' => env('DIGIO_REDIS_HOST'),

            'port' => env(
                'DIGIO_REDIS_PORT',
                6379
            ),

            'password' => env(
                'DIGIO_REDIS_PASSWORD'
            ),
        ],

    ],

];

<?php

declare(strict_types=1);

/**
 * Application Configuration
 *
 * All configuration can be consolidated in this single file.
 * Array values at the first level are automatically promoted to their own namespace.
 * For example, 'router' becomes accessible via Config::get('router.files').
 */

return [
    /**
     * Application Settings
     */
    'app' => [
        'name' => env('APP_NAME', 'Lalaz Web'),
        'env' => env('APP_ENV', 'development'),
        'debug' => env('APP_DEBUG', false),
        'url' => env('APP_URL', 'http://localhost'),
        'timezone' => 'UTC',
    ],

    /**
     * Router Configuration
     */
    'router' => [
        'files' => [
            __DIR__ . '/../routes/web.php',
        ],
    ],

    /**
     * View Configuration
     */
    'views' => [
        'path' => __DIR__ . '/../resources/views',
        'cache' => env('VIEW_CACHE', false)
            ? __DIR__ . '/../storage/cache/views'
            : false,
    ],

    /**
     * Session Configuration
     */
    'session' => [
        'name' => env('SESSION_NAME', 'lalaz_session'),
        'lifetime' => env('SESSION_LIFETIME', 120),
        'secure' => env('SESSION_SECURE', false),
        'http_only' => true,
        'same_site' => 'Lax',
    ],
];

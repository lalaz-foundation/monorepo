<?php

/**
 * Events Configuration
 *
 * Configure the event system, drivers, and listener discovery.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Events Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable the event system entirely.
    |
    */
    'enabled' => env('EVENTS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Event Driver
    |--------------------------------------------------------------------------
    |
    | The default driver used for async event dispatching.
    |
    | Supported: "sync", "queue", "null", or custom driver name
    |
    | - sync: Execute all listeners synchronously (no async)
    | - queue: Dispatch events via the Queue package
    | - null: Discard events (useful for testing)
    |
    */
    'driver' => env('EVENT_DRIVER', 'queue'),

    /*
    |--------------------------------------------------------------------------
    | Event Drivers Configuration
    |--------------------------------------------------------------------------
    |
    | Configure each available driver.
    |
    */
    'drivers' => [
        'queue' => [
            'queue' => env('EVENT_QUEUE', 'events'),
            'priority' => env('EVENT_QUEUE_PRIORITY', 9),
            'delay' => null, // Delay in seconds before processing
        ],

        'null' => [
            'record' => false, // Set to true to record events for testing
        ],

        // Example custom Redis Pub/Sub driver (implement your own)
        // 'redis' => [
        //     'driver' => \App\Events\Drivers\RedisDriver::class,
        //     'connection' => 'default',
        //     'prefix' => 'events:',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Listener Discovery
    |--------------------------------------------------------------------------
    |
    | Auto-discover EventListener classes from a directory.
    |
    */
    'discovery' => [
        'enabled' => env('EVENT_DISCOVERY_ENABLED', true),
        'path' => app_path('Listeners'),
    ],
];

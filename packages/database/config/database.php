<?php declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "sqlite", "mysql", "postgres"
    |
    */
    "driver" => env("DB_DRIVER", "sqlite"),

    /*
    |--------------------------------------------------------------------------
    | Connection Pool
    |--------------------------------------------------------------------------
    |
    | The connection manager pools PDO instances per runtime.
    |
    */
    "pool" => [
        "min" => (int) env("DB_POOL_MIN", 0),
        "max" => (int) env("DB_POOL_MAX", 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Read Replicas
    |--------------------------------------------------------------------------
    |
    | Optionally direct SELECT traffic to read replicas. When enabled, the
    | connection manager will round-robin across the configured connections.
    | Set "sticky" to true to force reads to the writer after a write in the
    | same request to avoid replication lag.
    */
    "read" => [
        "enabled" => (bool) env("DB_READ_ENABLED", false),
        "driver" => env("DB_READ_DRIVER", null),
        "sticky" => (bool) env("DB_READ_STICKY", true),
        "pool" => [
            "min" => (int) env("DB_READ_POOL_MIN", 0),
            "max" => (int) env("DB_READ_POOL_MAX", 5),
            "timeout_ms" => (int) env("DB_READ_POOL_TIMEOUT", 5000),
        ],
        "connections" => [
            // [
            //     "host" => env("DB_READ_HOST", "127.0.0.1"),
            //     "port" => (int) env("DB_READ_PORT", 3306),
            //     "database" => env("DB_READ_DATABASE", "lalaz"),
            //     "username" => env("DB_READ_USERNAME", "root"),
            //     "password" => env("DB_READ_PASSWORD", ""),
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Each driver shares the same structure used by the ConnectionManager.
    |
    */
    "connections" => [
        "sqlite" => [
            "path" => env(
                "DB_SQLITE_PATH",
                dirname(__DIR__) . "/storage/database.sqlite",
            ),
            "options" => [],
        ],

        "mysql" => [
            "host" => env("DB_HOST", "127.0.0.1"),
            "port" => (int) env("DB_PORT", 3306),
            "database" => env("DB_DATABASE", "lalaz"),
            "username" => env("DB_USERNAME", "root"),
            "password" => env("DB_PASSWORD", ""),
            "charset" => env("DB_CHARSET", "utf8mb4"),
            "options" => [],
        ],

        "postgres" => [
            "host" => env("DB_HOST", "127.0.0.1"),
            "port" => (int) env("DB_PORT", 5432),
            "database" => env("DB_DATABASE", "lalaz"),
            "username" => env("DB_USERNAME", "postgres"),
            "password" => env("DB_PASSWORD", ""),
            "schema" => env("DB_SCHEMA", "public"),
            "options" => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Connectors
    |--------------------------------------------------------------------------
    |
    | Register bespoke drivers by mapping a driver name to a connector class.
    | Each connector must implement Lalaz\Database\Contracts\ConnectorInterface.
    |
    */
    "connectors" => [
        // 'tenant' => App\Database\TenantConnector::class,
    ],
];

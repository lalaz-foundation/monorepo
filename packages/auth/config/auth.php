<?php declare(strict_types=1);

/**
 * Authentication Configuration
 *
 * This file contains the configuration for the authentication system,
 * including guards, user providers, and default settings.
 *
 * @package Lalaz\Auth
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'provider' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Here you may define every authentication guard for your application.
    | A great default configuration has been defined for you which uses
    | session storage plus the user provider.
    |
    | Supported drivers: "session", "jwt", "api_key"
    |
    | Available guard names:
    | - "web"     : Alias for session-based auth (full-stack apps)
    | - "api"     : Alias for JWT-based auth (API endpoints)
    | - "session" : Session-based authentication
    | - "jwt"     : JSON Web Token authentication
    | - "api_key" : API key authentication
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],

        'api_key' => [
            'driver' => 'api_key',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are retrieved from your database or other storage systems.
    |
    | Supported drivers: "model", "generic"
    |
    | The "model" driver uses a Model class for user retrieval.
    | The "generic" driver uses callbacks for flexible user retrieval.
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'model',
            'model' => env('AUTH_MODEL', 'App\\Models\\User'),
        ],

        // Example: Generic provider with custom callbacks
        // 'api_users' => [
        //     'driver' => 'generic',
        //     'callbacks' => [
        //         'byId' => fn($id) => ApiUser::find($id),
        //         'byCredentials' => fn($creds) => ApiUser::findByEmail($creds['email']),
        //     ],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | JWT Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the JWT (JSON Web Token) guard. This includes the
    | secret key used for signing tokens and the token TTL (time to live).
    |
    */

    'jwt' => [
        'secret' => env('JWT_SECRET', ''),
        'algorithm' => env('JWT_ALGORITHM', 'HS256'),
        'ttl' => env('JWT_TTL', 3600), // Token lifetime in seconds (1 hour)
        'refresh_ttl' => env('JWT_REFRESH_TTL', 604800), // Refresh token TTL (7 days)
        'blacklist_enabled' => env('JWT_BLACKLIST_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the session-based authentication guard.
    |
    */

    'session' => [
        'key' => env('AUTH_SESSION_KEY', 'auth_user_id'),
        'remember_key' => env('AUTH_REMEMBER_KEY', 'remember_token'),
        'remember_ttl' => env('AUTH_REMEMBER_TTL', 2592000), // 30 days
    ],

    /*
    |--------------------------------------------------------------------------
    | API Key Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the API key authentication guard.
    |
    */

    'api_key' => [
        'header' => env('API_KEY_HEADER', 'X-API-Key'),
        'query_param' => env('API_KEY_QUERY_PARAM', 'api_key'),
        'hash_algorithm' => 'sha256',
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect Paths
    |--------------------------------------------------------------------------
    |
    | These paths are used for redirecting users after authentication events.
    |
    */

    'redirects' => [
        'login' => '/login',
        'logout' => '/',
        'home' => '/dashboard',
        'unauthorized' => '/unauthorized',
    ],

];

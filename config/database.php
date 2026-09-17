<?php

use Illuminate\Support\Str;

return [

    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', 'postgres'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'lendflow'),
            'username' => env('DB_USERNAME', 'lendflow'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            // READ COMMITTED is Postgres's default and is the isolation level
            // LendFlow's concurrency strategy is designed around — see
            // docs/concurrency.md for why we use explicit row locks
            // (lockForUpdate) rather than SERIALIZABLE.
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [

        'client' => env('REDIS_CLIENT', 'predis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'lendflow'), '_').'_database_'),
        ],

        'default' => [
            'scheme' => 'tls',
            'host' => env('REDIS_HOST', 'redis'),
            'username' => env('REDIS_USERNAME', 'default'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => 0,
        ],

        'cache' => [
            'scheme' => 'tls',
            'host' => env('REDIS_HOST', 'redis'),
            'username' => env('REDIS_USERNAME', 'default'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => 0,
        ],

        'queue' => [
            'scheme' => 'tls',
            'host' => env('REDIS_HOST', 'redis'),
            'username' => env('REDIS_USERNAME', 'default'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => 0,
        ],

    ],

];

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

        'client' => env('REDIS_CLIENT'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER'),
            'prefix' => env('REDIS_PREFIX'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'scheme' => env('REDIS_SCHEME'),
            'host' => env('REDIS_HOST'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT'),
            'database' => env('REDIS_DB'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'scheme' => env('REDIS_SCHEME'),
            'host' => env('REDIS_HOST'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT'),
            'database' => env('REDIS_DB'),
        ],

        'queue' => [
            'url' => env('REDIS_URL'),
            'scheme' => env('REDIS_SCHEME'),
            'host' => env('REDIS_HOST'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT'),
            'database' => env('REDIS_DB'),
        ],

    ],

];

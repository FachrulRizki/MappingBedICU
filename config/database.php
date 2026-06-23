<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    | Koneksi utama untuk tabel aplikasi ICU (users, sessions, booking, spri, dll).
    | Prod: sqlsrv (SQL Server RS, database terpisah)
    | Dev lokal: mysql (MAMP/XAMPP)
    */
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [

        // ── Koneksi utama aplikasi — MySQL (dev lokal) ────────────────────
        'mysql' => [
            'driver'    => 'mysql',
            'url'       => env('DB_URL'),
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => env('DB_DATABASE', 'laravel'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset'   => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix'    => '',
            'prefix_indexes' => true,
            'strict'    => true,
            'engine'    => null,
        ],

        // ── Koneksi utama aplikasi — SQL Server (prod) ────────────────────
        // Pakai database terpisah di SQL Server RS yang sama.
        // Set DB_CONNECTION=sqlsrv di .env prod.
        'sqlsrv' => [
            'driver'                   => 'sqlsrv',
            'host'                     => env('DB_HOST', '127.0.0.1'),
            'port'                     => env('DB_PORT', '1433'),
            'database'                 => env('DB_DATABASE', 'mapping_bed_icu'),
            'username'                 => env('DB_USERNAME', ''),
            'password'                 => env('DB_PASSWORD', ''),
            'charset'                  => 'utf8',
            'prefix'                   => '',
            'prefix_indexes'           => true,
            'trust_server_certificate' => env('DB_TRUST_CERT', 'true'),
            'encrypt'                  => env('DB_ENCRYPT', 'false'),
        ],

        // ── Koneksi ke DB RS (SQL Server, read-only) ──────────────────────
        // Untuk membaca tabel existing RS: RegistrasiPasien, Pendaftaran,
        // StatusKamar, M_Kelas, M_RuangMaster, M_CaraBayar, dll.
        'sqlsrv_rsus' => [
            'driver'                   => 'sqlsrv',
            'host'                     => env('DB_RSUS_HOST', '192.168.200.160'),
            'port'                     => env('DB_RSUS_PORT', '1433'),
            'database'                 => env('DB_RSUS_DATABASE', 'DB_RSUS'),
            'username'                 => env('DB_RSUS_USERNAME', ''),
            'password'                 => env('DB_RSUS_PASSWORD', ''),
            'charset'                  => 'utf8',
            'prefix'                   => '',
            'prefix_indexes'           => true,
            'trust_server_certificate' => env('DB_RSUS_TRUST_CERT', 'true'),
            'encrypt'                  => 'false',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    */
    'migrations' => [
        'table'                  => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    */
    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster'    => env('REDIS_CLUSTER', 'redis'),
            'prefix'     => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')) . '-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url'      => env('REDIS_URL'),
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url'      => env('REDIS_URL'),
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];

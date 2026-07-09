<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Keycloak SSO Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi koneksi ke server Keycloak.
    | Setiap project cukup beda di .env — KEYCLOAK_CLIENT_ID dan
    | KEYCLOAK_CLIENT_SECRET disesuaikan dengan client di Keycloak.
    |
    */

    'enabled'      => env('KEYCLOAK_ENABLED', true),

    'base_url'     => env('KEYCLOAK_BASE_URL'),
    'realm'        => env('KEYCLOAK_REALM', 'myrealm'),
    'client_id'    => env('KEYCLOAK_CLIENT_ID'),
    'client_secret'=> env('KEYCLOAK_CLIENT_SECRET'),
    'redirect_uri' => env('KEYCLOAK_REDIRECT_URI'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout & Cache
    |--------------------------------------------------------------------------
    */

    // Timeout (detik) untuk request HTTP ke Keycloak
    'timeout'      => env('KEYCLOAK_TIMEOUT', 5),

    // TTL cache introspection token (detik) — kurangi round-trip ke Keycloak
    'cache_ttl'    => env('KEYCLOAK_CACHE_TTL', 60),

];

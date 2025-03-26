<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'users/*'],  // Tambahkan 'users/*'

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://slg.petra.ac.id',
        'http://localhost:5173',
        'http://localhost:5174',
        'http://192.168.160.20:5174',
        'http://82.29.165.72:5174',
        'http://kelvinsidhartasie.my.id:5174',
        'http://kelvinsidhartasie.my.id',
        'https://kelvinsidhartasie.my.id',
        'https://frontend.kelvinsidhartasie.my.id',
        'https://blc.works',
        'http://blc.works',
    ],

    'allowed_origins_patterns' => [
        '/^https?:\/\/(.*\.)?kelvinsidhartasie\.my\.id$/i',
    ],

    // 'allowed_headers' => [
    //     'Content-Type',
    //     'X-Requested-With',
    //     'Authorization',
    //     'Accept',
    //     'X-XSRF-TOKEN',
    // ],

    'allowed_headers' => ['*'],  // Debugging

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];

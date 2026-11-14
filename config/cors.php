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

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Mobilappen (Capacitor): iOS = capacitor://localhost, Android = https://localhost. Weben kalder fra samme adresse og behøver ikke CORS.
    // Appen (Capacitor) og klubbens hjemmeside, så den kan hente kalender og priser fra /api/public/*
    'allowed_origins' => ['capacitor://localhost', 'https://localhost', 'http://localhost', 'https://surfhorsens.dk', 'https://www.surfhorsens.dk'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];

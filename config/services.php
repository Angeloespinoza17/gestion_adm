<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'open_library' => [
        'base_url' => env('OPEN_LIBRARY_BASE_URL', 'https://openlibrary.org'),
        'application_name' => env('OPEN_LIBRARY_APP_NAME', env('APP_NAME', 'Biblioteca Escolar')),
        'contact' => env('OPEN_LIBRARY_CONTACT', env('MAIL_FROM_ADDRESS', 'biblioteca@example.invalid')),
    ],

    'weatherapi' => [
        'key' => env('WEATHERAPI_KEY'),
        'base_url' => env('WEATHERAPI_BASE_URL', 'https://api.weatherapi.com/v1'),
        'query' => env('WEATHERAPI_LOCATION_QUERY', '-39.8142,-73.2459'),
        'location_key' => 'valdivia-los-rios-cl',
        'location_name' => 'Valdivia',
        'administrative_region' => 'Región de Los Ríos',
        'latitude' => -39.8142,
        'longitude' => -73.2459,
        'forecast_days' => (int) env('WEATHERAPI_FORECAST_DAYS', 7),
        'display_days' => (int) env('WEATHERAPI_DISPLAY_DAYS', 7),
    ],

    'geovictoria' => [
        'api_key' => env('GEOVICTORIA_API_KEY'),
        'api_secret' => env('GEOVICTORIA_API_SECRET'),
        'base_url' => env('GEOVICTORIA_CUSTOMER_API_URL', 'https://customerapi.geovictoria.com'),
        'login_path' => env('GEOVICTORIA_LOGIN_PATH', '/api/v1/Login'),
        'users_path' => env('GEOVICTORIA_USERS_PATH', '/api/v1/User/List'),
        'attendance_book_path' => env('GEOVICTORIA_ATTENDANCE_BOOK_PATH', '/api/v1/AttendanceBook'),
        'connect_timeout' => (int) env('GEOVICTORIA_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('GEOVICTORIA_TIMEOUT', 45),
        'token_ttl_minutes' => (int) env('GEOVICTORIA_TOKEN_TTL_MINUTES', 270),
        'users_cache_minutes' => (int) env('GEOVICTORIA_USERS_CACHE_MINUTES', 10),
        'report_batch_size' => (int) env('GEOVICTORIA_REPORT_BATCH_SIZE', 40),
    ],

];

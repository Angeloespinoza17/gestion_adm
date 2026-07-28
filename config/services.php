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

];

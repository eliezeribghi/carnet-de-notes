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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
        'medusa' => [
        'url'        => env('MEDUSA_BACKEND_URL', 'http://localhost:9000'),
        'api_key'    => env('MEDUSA_API_TOKEN'),
        'image_base' => env('APP_URL', 'http://localhost:8001') . '/storage',
    ],
    'stripe' => [
    'key'            => env('STRIPE_KEY'),
    'secret'         => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
],
'sendcloud' => [
    'base_url'                   => env('SENDCLOUD_BASE_URL', 'https://panel.sendcloud.sc'),
    'public_key'                 => env('SENDCLOUD_PUBLIC_KEY'),
    'secret_key'                 => env('SENDCLOUD_SECRET_KEY'),
    'checkout_configuration_id'  => env('SENDCLOUD_CHECKOUT_CONFIGURATION_ID'),
    'from_name'                  => env('SENDCLOUD_FROM_NAME'),
    'from_company'               => env('SENDCLOUD_FROM_COMPANY'),
    'from_email'                 => env('SENDCLOUD_FROM_EMAIL'),
    'from_phone'                 => env('SENDCLOUD_FROM_PHONE'),
    'from_address_1'             => env('SENDCLOUD_FROM_ADDRESS_1'),
    'from_postal_code'           => env('SENDCLOUD_FROM_POSTAL_CODE'),
    'from_city'                  => env('SENDCLOUD_FROM_CITY'),
    'from_country'               => env('SENDCLOUD_FROM_COUNTRY', 'FR'),
    'sender_address_id'          => env('SENDCLOUD_SENDER_ADDRESS_ID'), // ← ajoute cette ligne
],
'insee' => [
    'api_key' => env('INSEE_API_KEY'),
],
'pennylane' => [
    'api_url'  => env('PENNYLANE_API_URL'),
    'api_key'  => env('PENNYLANE_API_KEY'),
    'enabled'  => env('PENNYLANE_ENABLED', true), // false en dev
],

];

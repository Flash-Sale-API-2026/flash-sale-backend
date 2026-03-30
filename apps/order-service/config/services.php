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

    'gateway' => [
        'user_id_header' => env('GATEWAY_USER_ID_HEADER', 'X-Internal-User-Id'),
    ],

    'internal' => [
        'token_header' => env('INTERNAL_SERVICE_TOKEN_HEADER', 'X-Internal-Service-Token'),
        'token' => env('INTERNAL_SERVICE_TOKEN'),
    ],

    'inventory' => [
        'internal_base_url' => env('INVENTORY_INTERNAL_BASE_URL', 'http://inventory-service:8000/api/internal'),
    ],

];

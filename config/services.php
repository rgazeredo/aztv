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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Exchange APIs
    |--------------------------------------------------------------------------
    |
    | Configuration for currency exchange rate APIs.
    |
    */

    'fixer' => [
        'api_key' => env('FIXER_API_KEY'),
        'base_url' => 'https://api.fixer.io/latest',
    ],

    'exchangerate_api' => [
        'base_url' => 'https://api.exchangerate-api.com/v4/latest',
    ],

    'coingecko' => [
        'api_key' => env('COINGECKO_API_KEY'), // Optional, for higher rate limits
        'base_url' => 'https://api.coingecko.com/api/v3',
    ],

];

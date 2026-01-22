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

    'trello' => [
        'base_url' => env('TRELLO_BASE_URL', 'https://api.trello.com/1'),
        'key' => env('TRELLO_KEY'),
        'token' => env('TRELLO_TOKEN'),
    ],

    'fourjaw' => [
        'base_url' => env('FOURJAW_BASE_URL', 'https://api.fourjaw.com/v2/'),
        'token' => env('FOURJAW_API_KEY'),
        'email' => env('FOURJAW_USER_EMAIL', 'email@example.com'),
        'password' => env('FOURJAW_USER_PASSWORD', 'password'),
        'remember_me' => env('FOURJAW_REMEMBER_ME', true)
    ]
];

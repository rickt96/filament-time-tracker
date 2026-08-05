<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'clockify' => [
        'base_url' => 'https://api.clockify.me/api/v1',
        'api_key' => env('CLOCKIFY_API_KEY'),
    ],

    'notion' => [
        'base_url' => 'https://api.notion.com/v1',
        // Pinned on purpose: 2022-06-28 is the last version where a database is
        // queried via /databases/{id}/query. From 2025-09-03 onwards databases
        // are split into "data sources" and the endpoint changes shape.
        'version' => env('NOTION_API_VERSION', '2022-06-28'),
        'token' => env('NOTION_API_TOKEN'),
    ],

];

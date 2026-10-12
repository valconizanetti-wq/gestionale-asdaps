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

    // Lettura degli scontrini (v0.39.0, scontrini.md §4). La chiave sta
    // SOLO nel .env (OCR_SPACE_KEY), mai nel codice né nel database.
    // OCR_SPACE_CA: file di certificati da usare al posto di quello di PHP
    // (in locale, con un antivirus che controlla l'HTTPS); vuoto sul server.
    'ocr_space' => [
        'key' => env('OCR_SPACE_KEY'),
        'url' => env('OCR_SPACE_URL', 'https://api.ocr.space/parse/image'),
        'timeout' => (int) env('OCR_SPACE_TIMEOUT', 15),
        'ca' => env('OCR_SPACE_CA'),
    ],

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

];

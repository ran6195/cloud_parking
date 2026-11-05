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

    /*
     * Motore prenotazioni, usato dal CMS via HTTP come qualsiasi altra interfaccia.
     * La chiave è una sk_ creata con "php artisan engine:client" (o dal pannello /motore).
     * Senza URL si usa questa stessa applicazione: APP_URL/api/v1.
     */
    'engine' => [
        'url' => env('ENGINE_API_URL'),
        'key' => env('ENGINE_API_KEY'),
        'timeout' => (int) env('ENGINE_API_TIMEOUT', 10),
        // Pulsanti PayPal veri nel form (servono PAYPAL_CLIENT_ID e il motore con PayPal vero);
        // altrimenti il form mostra un pagamento simulato.
        'paypal_buttons' => (bool) env('ENGINE_FORM_PAYPAL_BUTTONS', false),
        'paypal_client_id' => env('PAYPAL_CLIENT_ID'),
    ],

];

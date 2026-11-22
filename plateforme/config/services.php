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

    // Connexion « avec Google » des clients (App\Services\ConnexionGoogle). Sans identifiants, le bouton n'apparaît pas.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),                       // identifiant client OAuth de type « Application Web »
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),                     // par défaut : {APP_URL}/connexion/google/retour
        // Identifiants client « Android » acceptés pour les ID tokens de l'application (séparés par des virgules)
        'clients_android' => array_values(array_filter(explode(',', (string) env('GOOGLE_CLIENTS_ANDROID', '')))),
    ],

    // Notifications push de l'application Android (Firebase Cloud Messaging, API HTTP v1)
    'firebase' => [
        'projet' => env('FIREBASE_PROJECT_ID'),
        'compte_service' => env('FIREBASE_CREDENTIALS'),          // chemin du JSON du compte de service (hors public/)
    ],

];

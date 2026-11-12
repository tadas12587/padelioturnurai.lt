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

    'overlay' => [
        // Shared secret the external bridge sends in X-Overlay-Token to push
        // tournament snapshots to POST /overlay/ingest.
        'ingest_token' => env('OVERLAY_INGEST_TOKEN'),
        // Shared secret for the unlinked /grafikas/{tournament}/velavimai
        // court-delay admin page (passed as ?token=... in the URL).
        'admin_token' => env('OVERLAY_ADMIN_TOKEN'),
    ],

    'tournated' => [
        // Public API v2 key (read:default is enough — no PII scopes needed).
        'key' => env('TOURNATED_API_KEY'),
        'base_url' => env('TOURNATED_API_URL', 'https://api.tournated.com/api/v2'),
        // Public play.padel.lt GraphQL (no key). Fallback for match lists the
        // official API cannot serve (club-league /matches 502s) and source of
        // club-league division names ("Moterys B 1").
        'graphql_url' => env('TOURNATED_GRAPHQL_URL', 'https://play.padel.lt/api/graphql'),
    ],

    // Web Push for the /grafikas page. Keys: `php artisan push:vapid-keys <path>`.
    'push' => [
        'vapid_public' => env('VAPID_PUBLIC_KEY'),
        'vapid_private_path' => env('VAPID_PRIVATE_KEY_PATH', storage_path('app/vapid-private.pem')),
        'subject' => env('VAPID_SUBJECT', 'https://padelioturnyrai.lt'),
        // shared secret the scheduler sends to POST /push/dispatch
        'dispatch_token' => env('PUSH_DISPATCH_TOKEN'),
    ],

];

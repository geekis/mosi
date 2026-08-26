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

    // Microsoft Entra ID (Azure AD) via socialiteproviders/microsoft-azure.
    // Wired up in phase 1C; credentials are managed in Forge, never committed.
    'azure' => [
        'client_id' => env('AZURE_CLIENT_ID'),
        'client_secret' => env('AZURE_CLIENT_SECRET'),
        'redirect' => env('AZURE_REDIRECT_URI'),
        'tenant' => env('AZURE_TENANT_ID'),

        // Email domain whose users sign in with Microsoft. The identifier-first login
        // redirects these addresses to Entra instead of showing a password field
        // (.ai/rules/auth.md). Overridable per environment so a test tenant can use a
        // different domain than production's `nattura.is`.
        'entra_domain' => env('AZURE_ENTRA_DOMAIN', 'nattura.is'),
    ],

    // NVS staff API — the sole source of accounts (see .ai/rules/staff-api.md).
    // Oracle APEX ORDS endpoint behind HTTP basic auth. Set STAFF_API_URL,
    // STAFF_API_USER and STAFF_API_PASSWORD in .env / Forge. The URL may carry its own
    // row_offset/fetch_size query params; the sync overrides row_offset while paginating.
    'staff_api' => [
        'url' => env('STAFF_API_URL'),
        'username' => env('STAFF_API_USER'),
        'password' => env('STAFF_API_PASSWORD'),
        'fetch_size' => (int) env('STAFF_API_FETCH_SIZE', 1000),
    ],

];

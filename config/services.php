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
    |--------------------------------------------------------------------------
    | Harvest
    |--------------------------------------------------------------------------
    |
    | Personal access token + account id from https://id.getharvest.com/developers.
    | Used by `php artisan harvest:import` to pull clients, projects, tasks and
    | time entries into this app.
    |
    */

    'harvest' => [
        'account_id' => env('HARVEST_ACCOUNT_ID'),
        'access_token' => env('HARVEST_ACCESS_TOKEN'),
        'base_url' => env('HARVEST_BASE_URL', 'https://api.harvestapp.com/api/v2'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Moneybird
    |--------------------------------------------------------------------------
    |
    | API token + administration id from https://moneybird.com/user/applications.
    | Invoices prepared in this app are pushed to Moneybird as drafts; the
    | optional tax rate / ledger ids are applied to every invoice line.
    |
    */

    'moneybird' => [
        'access_token' => env('MONEYBIRD_ACCESS_TOKEN'),
        'administration_id' => env('MONEYBIRD_ADMINISTRATION_ID'),
        'base_url' => env('MONEYBIRD_BASE_URL', 'https://moneybird.com/api/v2'),
        'tax_rate_id' => env('MONEYBIRD_TAX_RATE_ID'),
        'ledger_account_id' => env('MONEYBIRD_LEDGER_ACCOUNT_ID'),
        'workflow_id' => env('MONEYBIRD_WORKFLOW_ID'),
    ],

];

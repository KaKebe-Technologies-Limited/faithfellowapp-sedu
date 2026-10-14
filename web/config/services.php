<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    // ── Payment gateways ────────────────────────────────────────────────
    'iotec' => [
        'base_url' => env('IOTEC_BASE_URL', 'https://pay.iotec.io/api'),
        'auth_url' => env('IOTEC_AUTH_URL', 'https://id.iotec.io/connect/token'),
        'client_id' => env('IOTEC_CLIENT_ID'),
        'client_secret' => env('IOTEC_CLIENT_SECRET'),
        // The live wallet and the test wallet (used when IOTEC_SANDBOX=true on a local machine).
        'wallet_id' => env('IOTEC_LIVE_WALLET_ID', env('IOTEC_WALLET_ID')),
        'test_wallet_id' => env('IOTEC_TEST_WALLET_ID'),
        'sandbox' => filter_var(env('IOTEC_SANDBOX', false), FILTER_VALIDATE_BOOLEAN),
        'ipn_secret' => env('IOTEC_IPN_SECRET'),
        'webhook_secret' => env('IOTEC_WEBHOOK_SECRET'),
    ],

    'mtn_momo' => [
        'base_url' => env('MTN_MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
        'environment' => env('MTN_MOMO_ENVIRONMENT', 'sandbox'),
        'subscription_key' => env('MTN_MOMO_SUBSCRIPTION_KEY'),
        'api_user' => env('MTN_MOMO_API_USER'),
        'api_key' => env('MTN_MOMO_API_KEY'),
        'currency' => env('MTN_MOMO_CURRENCY', 'EUR'),
        'webhook_secret' => env('MTN_MOMO_WEBHOOK_SECRET'),
    ],

    'airtel' => [
        'base_url' => env('AIRTEL_BASE_URL', 'https://openapiuat.airtel.africa'),
        'client_id' => env('AIRTEL_CLIENT_ID'),
        'client_secret' => env('AIRTEL_CLIENT_SECRET'),
        'country' => env('AIRTEL_COUNTRY', 'UG'),
        'currency' => env('AIRTEL_CURRENCY', 'UGX'),
        'webhook_secret' => env('AIRTEL_WEBHOOK_SECRET'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    // ── Sign-in ─────────────────────────────────────────────────────────
    // Google ID tokens are accepted only when issued for one of these OAuth client IDs
    // (the web client, plus iOS / Android clients if the app signs in natively).
    'google' => [
        // Used only by the hosted-page flow (the server swaps Google's code for an ID token).
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        // Must match an Authorised redirect URI in Google Cloud. Defaults to this site's /auth/google/app/callback.
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'client_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('GOOGLE_CLIENT_IDS', env('GOOGLE_CLIENT_ID', '')))))),
    ],

    // ── Messaging ───────────────────────────────────────────────────────
    'bible' => [
        'api_bible_key' => env('API_BIBLE_KEY'),
        // Crossway's ESV API (api.esv.org): a free key for non-commercial use, from https://api.esv.org/account/
        'esv_key' => env('ESV_API_KEY'),
    ],

    // Push to the phone app goes through Expo. The access token is optional (it adds protection if you turn on
    // "enhanced push security" in your Expo account).
    'expo' => [
        'access_token' => env('EXPO_ACCESS_TOKEN'),
    ],

    'webpush' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:service@kakebe.tech'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    'africastalking' => [
        'username' => env('AFRICASTALKING_USERNAME', 'sandbox'),
        'api_key' => env('AFRICASTALKING_API_KEY'),
        'sender_id' => env('AFRICASTALKING_SENDER_ID'),
        'whatsapp_number' => env('AFRICASTALKING_WHATSAPP_NUMBER'),
        'sms_enabled' => (bool) env('SMS_ENABLED', false),
        'whatsapp_enabled' => (bool) env('WHATSAPP_ENABLED', false),
        'sms_cost' => (float) env('SMS_COST_UGX', 35),
        'ussd_secret' => env('USSD_SECRET'),
    ],

    // ── Live streaming ──────────────────────────────────────────────────
    'streaming' => [
        'provider' => env('STREAMING_PROVIDER', 'self_hosted'),
        'rtmp_url' => env('STREAMING_RTMP_URL', 'rtmp://live.faithfellows.com/live'),
        'hls_base_url' => env('STREAMING_HLS_BASE_URL', 'https://live.faithfellows.com/hls'),
        'cloudflare_account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'cloudflare_token' => env('CLOUDFLARE_STREAM_TOKEN'),
    ],

];

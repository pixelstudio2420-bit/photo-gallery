<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
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
        'region' => env('AWS_DEFAULT_REGION', 'ap-southeast-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS CloudFront
    |--------------------------------------------------------------------------
    */
    'cloudfront' => [
        'domain'          => env('AWS_CLOUDFRONT_DOMAIN'),
        'distribution_id' => env('AWS_CLOUDFRONT_DISTRIBUTION_ID'),
        'key_pair_id'     => env('AWS_CLOUDFRONT_KEY_PAIR_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth & Drive
    |--------------------------------------------------------------------------
    | Credentials (client_id / client_secret / drive_api_key) live in the
    | `app_settings` DB table, managed via /admin/settings.
    | Read them with AppSetting::get('google_client_id' | 'google_client_secret'
    | | 'google_drive_api_key' | 'google_service_account_json').
    |
    | Socialite reads from config('services.google.*'), so
    | AuthController::googleCreds() hydrates it at runtime before calling
    | Socialite::driver('google'). Keep the keys present here (empty) so
    | Socialite's internal config lookup doesn't error.
    */
    'google' => [
        'client_id'     => null,
        'client_secret' => null,
        'redirect'      => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | LINE (OAuth login + Messaging API + Notify + OA)
    |--------------------------------------------------------------------------
    | All LINE credentials live in the `app_settings` DB table, managed via
    |   /admin/settings/line  (channel_id / secret / access_token / notify)
    |   /admin/marketing/line (broadcast channel + OA info)
    |
    | Read them with AppSetting::get('line_*') — do NOT read from config().
    | This block intentionally stays empty so any legacy config('services.line.*')
    | lookup gracefully returns null instead of throwing.
    */
    'line' => [],

    /*
    |--------------------------------------------------------------------------
    | Facebook Login
    |--------------------------------------------------------------------------
    */
    'facebook' => [
        'app_id'     => env('FB_APP_ID'),
        'app_secret' => env('FB_APP_SECRET'),
        'redirect'   => env('FB_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    | All payment-gateway credentials (Stripe / Omise / PayPal / TrueMoney /
    | 2C2P / LINE Pay / PromptPay) live in the `app_settings` DB table,
    | managed via /admin/settings/payment-gateways.
    |
    | Read them with AppSetting::get('stripe_secret_key' | 'omise_secret_key'
    | | 'paypal_client_id' | 'truemoney_merchant_id' | '2c2p_secret_key'
    | | 'line_pay_channel_id' | 'promptpay_number' | ...).
    |
    | Each Gateway class exposes typed static helpers, e.g.
    |   StripeGateway::secretKey() / StripeGateway::webhookSecret().
    |
    | Empty stubs stay here so any legacy config('services.{gateway}.*')
    | lookup returns null gracefully instead of throwing.
    */
    'stripe'    => [],
    'omise'     => [],
    'paypal'    => [],
    'truemoney' => [],
    '2c2p'      => [],
    'linepay'   => [],
    'promptpay' => [],

];

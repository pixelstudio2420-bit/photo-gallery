<?php

/*
|--------------------------------------------------------------------------
| Sentry error tracking (sentry/sentry-laravel)
|--------------------------------------------------------------------------
|
| A DSN of empty/null disables Sentry entirely — safe to ship with this
| file in git. The package is loaded in production; in local/dev we keep
| DSN empty and errors stay in laravel.log.
|
| Run the installer after `composer require sentry/sentry-laravel`:
|     php artisan sentry:publish --dsn=https://…@sentry.io/…
|
| Reference: https://docs.sentry.io/platforms/php/guides/laravel/
*/

return [

    // The DSN from Sentry's project settings. No DSN = no-op.
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    // 'release' sticks to commits so you can see which deploy broke things.
    'release' => env('SENTRY_RELEASE', trim(exec('git log --pretty="%h" -n1 HEAD 2>nul') ?: 'unknown')),

    // Only send events from these environments. Local/dev stays silent by default.
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    // Scrub obviously sensitive POST payloads.
    'send_default_pii' => false,

    // Performance monitoring — lower = cheaper. 0 disables tracing entirely.
    // Recommended: 0.1 in production (10% of transactions), 1.0 in staging.
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),

    // Profiling follows traces_sample_rate; only enabled if you've set
    // SENTRY_PROFILES_SAMPLE_RATE explicitly.
    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.0),

    // Exception classes we never want to report (expected errors / noise).
    'ignore_exceptions' => [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
    ],

    // Breadcrumbs — what kind of events to capture leading up to an error.
    'breadcrumbs' => [
        'logs'          => true,
        'cache'         => false,   // noisy
        'livewire'      => true,
        'sql_queries'   => true,
        'sql_bindings'  => false,   // can leak PII
        'queue_info'    => true,
        'command_info'  => true,
        'http_client_requests' => true,
        'notifications' => true,
    ],

    'tracing' => [
        'queue_job_transactions'    => env('SENTRY_TRACE_QUEUE_ENABLED', false),
        'queue_jobs'                => true,
        'sql_queries'               => true,
        'sql_origin'                => true,
        'views'                     => true,
        'livewire'                  => true,
        'http_client_requests'      => true,
        'redis_commands'            => env('SENTRY_TRACE_REDIS_COMMANDS', false),
        'redis_origin'              => true,
        'cache'                     => false,
        'missing_routes'            => false,
        'default_integrations'      => true,
        'continue_after_response'   => true,
    ],

];

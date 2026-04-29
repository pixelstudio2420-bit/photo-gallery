<?php

/**
 * Usage / cost / limit configuration.
 *
 * `pricing` is the cost-per-unit for each metered resource. The values are
 * vendor list prices in microcents (1¢ = 10000) so we can do exact integer
 * math when computing total spend. They feed UsageMeter::record() and
 * PlanCostCalculator::marginFor().
 *
 * `plan_caps` maps each (plan_code, resource) to a hard cap and an optional
 * soft warn threshold. The EnforceUsageQuota middleware reads here.
 *
 * `breakers` declares circuit-breaker thresholds for cost runaways — one
 * row per feature with a monthly THB ceiling.
 *
 * Production override: every value here can be moved to AppSetting later
 * if ops needs runtime tuning without redeploying.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    | When false, EnforceUsageQuota middleware is a no-op and UsageMeter
    | records but doesn't block. Useful for staging-by-impersonation tests.
    */
    'enforcement_enabled' => env('USAGE_ENFORCEMENT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Vendor unit costs (microcents per unit)
    |--------------------------------------------------------------------------
    |   1 cent = 10,000 microcents.
    |   Storage cost is billed per byte-second; we expose the more practical
    |   "GB per month" rate and the meter converts at record time.
    */
    'pricing' => [
        // AWS Rekognition — $0.001 / call → 10,000 microcents
        'ai.face_search'      => 10_000,
        'ai.face_index'       => 10_000,
        'ai.face_detect'      => 10_000,
        'ai.face_compare'     => 10_000,

        // R2 storage — $0.015 / GB / month → 0.0015¢/MB/month → 15 microcents/MB·month
        // We meter as bytes·month, so unit = bytes; cost = (bytes / 1_073_741_824) * 150
        // Stored here in microcents per byte-month for direct multiplication.
        'storage.bytes_month' => 0,            // computed by storageCost() helper
        'r2.write'            => 5,            // $0.0000045/op → 0.045 microcents → round up
        'r2.read'             => 1,            // $0.00000036/op
        'r2.delete'           => 5,
        'bandwidth.egress'    => 0,            // R2→Cloudflare is free; only set when egress is paid

        // SES — $0.0001/email → 1 microcent
        'email.transactional' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-plan caps (per resource × period)
    |--------------------------------------------------------------------------
    | Format:
    |   plan_code => [
    |     resource => ['period' => 'day'|'month', 'hard' => N, 'soft' => N|null],
    |   ]
    |
    | When the user's counter reaches `soft`, the response includes an X-Quota
    | header but the request still succeeds. When it reaches `hard`, requests
    | get a 402 Payment Required (or 429 if it's a rate-style cap).
    |
    | Use 0 to mean "feature disabled for this plan".
    | Use null to mean "no cap" (use sparingly — cost risk).
    */
    'plan_caps' => [
        'free' => [
            'ai.face_search' => ['period' => 'month', 'hard' => 50,    'soft' => 40],
            'storage.bytes'  => ['period' => 'lifetime', 'hard' => 2 * 1024 * 1024 * 1024], // 2 GB
            'event.create'   => ['period' => 'lifetime', 'hard' => 0],   // free can't sell events
            'photo.upload'   => ['period' => 'day',   'hard' => 100,    'soft' => 80],
            'export.run'     => ['period' => 'month', 'hard' => 1],
        ],
        'starter' => [
            'ai.face_search' => ['period' => 'month', 'hard' => 5_000,  'soft' => 4_000],
            'storage.bytes'  => ['period' => 'lifetime', 'hard' => 20 * 1024 * 1024 * 1024],
            'event.create'   => ['period' => 'month',  'hard' => 2],
            'photo.upload'   => ['period' => 'day',   'hard' => 2_000, 'soft' => 1_600],
            'export.run'     => ['period' => 'month', 'hard' => 10],
        ],
        'pro' => [
            // Pro/Business/Studio used to be "unlimited AI" — that's a cost-risk.
            // Replaced with high but FINITE caps. Anything past hard cap requires
            // overage approval from billing (or auto-bill if user opted in).
            'ai.face_search' => ['period' => 'month', 'hard' => 50_000,  'soft' => 10_000],
            'storage.bytes'  => ['period' => 'lifetime', 'hard' => 100 * 1024 * 1024 * 1024],
            'event.create'   => ['period' => 'month',  'hard' => 5],
            'photo.upload'   => ['period' => 'day',   'hard' => 10_000, 'soft' => 8_000],
            'export.run'     => ['period' => 'month', 'hard' => 50],
        ],
        'business' => [
            'ai.face_search' => ['period' => 'month', 'hard' => 150_000, 'soft' => 30_000],
            'storage.bytes'  => ['period' => 'lifetime', 'hard' => 500 * 1024 * 1024 * 1024],
            'event.create'   => ['period' => 'month',  'hard' => null],
            'photo.upload'   => ['period' => 'day',   'hard' => 50_000, 'soft' => 40_000],
            'export.run'     => ['period' => 'month', 'hard' => 200],
        ],
        'studio' => [
            'ai.face_search' => ['period' => 'month', 'hard' => 500_000, 'soft' => 100_000],
            'storage.bytes'  => ['period' => 'lifetime', 'hard' => 2 * 1024 * 1024 * 1024 * 1024],
            'event.create'   => ['period' => 'month',  'hard' => null],
            'photo.upload'   => ['period' => 'day',   'hard' => null],
            'export.run'     => ['period' => 'month', 'hard' => null],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Overage pricing (per unit beyond cap, in THB)
    |--------------------------------------------------------------------------
    | When a user opts into overage billing, requests past the hard cap are
    | billed at this rate. Without overage opt-in, requests past the cap
    | return 402.
    */
    'overage' => [
        'ai.face_search' => [
            'pro'      => 0.05,
            'business' => 0.04,
            'studio'   => 0.03,
        ],
        'storage.bytes' => [
            // ฿0.01 per MB·month for any tier with overage opt-in.
            'pro'      => 0.01,
            'business' => 0.008,
            'studio'   => 0.005,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit breakers (platform-wide kill-switches by monthly THB spend)
    |--------------------------------------------------------------------------
    | Per-feature monthly cost ceiling. When tripped, the feature returns
    | 503 across ALL users (paying + free) until ops re-closes the breaker
    | or the period rolls over.
    */
    'breakers' => [
        'ai.face_search' => [
            'monthly_thb_ceiling' => 50_000,    // ~$1,400/mo
            'reset_period'        => 'month',
        ],
        'ai.preset_generate' => [
            'monthly_thb_ceiling' => 10_000,
            'reset_period'        => 'month',
        ],
        'export.run' => [
            'monthly_thb_ceiling' => 5_000,
            'reset_period'        => 'month',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Anti-abuse settings (Free tier signup throttling)
    |--------------------------------------------------------------------------
    */
    'anti_abuse' => [
        'enabled'                 => env('ANTI_ABUSE_ENABLED', true),
        // After N free accounts from the same hashed IP/email/fingerprint
        // within the window, signup is gated by extra verification.
        'max_per_ip_per_day'      => 3,
        'max_per_email_domain'    => 50,    // suspect "+1@..." abuse on common domains
        'max_per_fingerprint_day' => 2,
        // Risk score thresholds
        'block_at_risk_score'     => 80,
        'flag_at_risk_score'      => 50,
        // Salt for hashing — env-controlled so a leak doesn't deanonymize.
        'hash_salt'               => env('SIGNUP_SIGNAL_SALT', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Spike detection
    |--------------------------------------------------------------------------
    | A user is flagged when their hourly usage exceeds N× their 7-day
    | moving average. The middleware soft-blocks (returns 429) and the
    | admin dashboard surfaces them for review. Helps catch credential
    | stuffing / scripted abuse before the daily cap notices.
    */
    'spike_detection' => [
        'enabled'              => true,
        'multiple_of_7d_avg'   => 10,
        'min_baseline_calls'   => 50,    // ignore tiny samples; need ≥50 calls in 7d
    ],

];

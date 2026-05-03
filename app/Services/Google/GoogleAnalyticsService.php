<?php

namespace App\Services\Google;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GoogleAnalyticsService — read-only access to GA4 Data API + Realtime API.
 *
 * Two flavours of report:
 *   • runReport()        — historical data (sessions, events, conversions
 *                          over a date range). Up to ~2y of history.
 *   • runRealtimeReport() — last-30-min data (active users, events).
 *                          Used for the "live visitors" badge.
 *
 * Auth: GoogleApiAuth (service account JWT → access token).
 *
 * Caching:
 *   • Historical reports cached 30min (data has ~24h-48h freshness lag
 *     from Google anyway; admin shouldn't see different numbers within
 *     30min).
 *   • Realtime reports cached 30s (the API itself updates every 30-60s).
 *
 * Cost (free tier = 25,000 tokens/day):
 *   • Most queries cost 5-10 tokens (1 metric + 1-2 dimensions)
 *   • 30min cache means ~48 cache misses/day per query type
 *   • Even with 7 widgets × 48 = 336 queries × 10 tokens = 3,360 tokens/day
 *   • Realtime polled every 60s by ~3 admins = 4,320 calls/day (under 2,500/day quota)
 *     → we cap at 1 admin polling at a time via cache.
 *
 * Failure mode: returns empty arrays / zero counts so widgets render
 * "no data" UX rather than 500 errors.
 */
class GoogleAnalyticsService
{
    /** GA4 Data API v1beta endpoints */
    private const ENDPOINT_BASE      = 'https://analyticsdata.googleapis.com/v1beta/properties';
    private const SCOPE              = 'https://www.googleapis.com/auth/analytics.readonly';

    public function __construct(
        private readonly GoogleApiAuth $auth,
    ) {}

    /**
     * Whether the admin has fully configured GA4 (service account
     * credentials + property ID). Both are required for any query
     * to work.
     */
    public function isConfigured(): bool
    {
        return $this->auth->isConfigured() && !empty($this->propertyId());
    }

    /**
     * Test the configured GA4 setup with a tiny realtime query.
     * Returns ['ok' => bool, 'message' => string, 'fix' => ?string]
     * for the admin UI.
     */
    public function testConnection(): array
    {
        if (!$this->auth->isConfigured()) {
            return ['ok' => false, 'message' => 'ยังไม่ได้อัปโหลด Service Account JSON'];
        }

        if (!$this->propertyId()) {
            return ['ok' => false, 'message' => 'ยังไม่ได้ใส่ GA4 Property ID'];
        }

        try {
            $body = $this->makeRequest('runRealtimeReport', [
                'metrics' => [['name' => 'activeUsers']],
                'limit'   => 1,
            ]);
            $rows = $body['rows'] ?? [];
            $count = !empty($rows) ? (int) ($rows[0]['metricValues'][0]['value'] ?? 0) : 0;
            return [
                'ok'      => true,
                'message' => "✓ เชื่อมต่อ GA4 สำเร็จ — {$count} active users ตอนนี้",
                'count'   => $count,
            ];
        } catch (\Throwable $e) {
            return $this->humaniseError($e->getMessage());
        }
    }

    /**
     * Real-time active users in the last 30 min. Used by the admin
     * "live visitors" badge (poll every 60s).
     *
     * @return int  active user count, 0 on failure or not configured
     */
    public function realtimeActiveUsers(): int
    {
        if (!$this->isConfigured()) return 0;

        return Cache::remember('ga_realtime_active', 30, function () {
            try {
                $body = $this->makeRequest('runRealtimeReport', [
                    'metrics' => [['name' => 'activeUsers']],
                ]);
                $rows = $body['rows'] ?? [];
                return !empty($rows) ? (int) ($rows[0]['metricValues'][0]['value'] ?? 0) : 0;
            } catch (\Throwable $e) {
                Log::warning('GA4 realtime query failed: ' . $e->getMessage());
                return 0;
            }
        });
    }

    /**
     * Traffic sources for a given date range. Returns array of
     * ['source', 'medium', 'sessions', 'engaged_sessions'].
     * Used by the photographer dashboard widget.
     */
    public function trafficSources(string $startDate = '7daysAgo', string $endDate = 'today', ?string $pagePathContains = null, int $limit = 10): array
    {
        if (!$this->isConfigured()) return [];

        $cacheKey = 'ga_traffic_sources:' . md5($startDate . $endDate . ($pagePathContains ?? '') . $limit);
        return Cache::remember($cacheKey, 1800, function () use ($startDate, $endDate, $pagePathContains, $limit) {
            try {
                $payload = [
                    'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
                    'dimensions' => [
                        ['name' => 'sessionSource'],
                        ['name' => 'sessionMedium'],
                    ],
                    'metrics' => [
                        ['name' => 'sessions'],
                        ['name' => 'engagedSessions'],
                    ],
                    'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                    'limit'    => $limit,
                ];

                if ($pagePathContains) {
                    $payload['dimensionFilter'] = [
                        'filter' => [
                            'fieldName'    => 'pagePath',
                            'stringFilter' => ['matchType' => 'CONTAINS', 'value' => $pagePathContains],
                        ],
                    ];
                }

                return $this->parseRows($this->makeRequest('runReport', $payload), [
                    'source', 'medium',
                    'sessions:int', 'engaged_sessions:int',
                ]);
            } catch (\Throwable $e) {
                Log::warning('GA4 trafficSources failed: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Bounce rate + avg session duration + exits per top page.
     * Used by the admin "page health" widget.
     *
     * @return array  list of ['page', 'views', 'bounce_rate', 'avg_duration', 'exits']
     */
    public function pagePerformance(string $startDate = '7daysAgo', string $endDate = 'today', int $limit = 10): array
    {
        if (!$this->isConfigured()) return [];

        $cacheKey = 'ga_page_performance:' . md5($startDate . $endDate . $limit);
        return Cache::remember($cacheKey, 1800, function () use ($startDate, $endDate, $limit) {
            try {
                return $this->parseRows($this->makeRequest('runReport', [
                    'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
                    'dimensions' => [['name' => 'pagePath']],
                    'metrics' => [
                        ['name' => 'screenPageViews'],
                        ['name' => 'bounceRate'],
                        ['name' => 'averageSessionDuration'],
                        ['name' => 'exits'],
                    ],
                    'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
                    'limit'    => $limit,
                ]), [
                    'page',
                    'views:int',
                    'bounce_rate:float',
                    'avg_duration:float',
                    'exits:int',
                ]);
            } catch (\Throwable $e) {
                Log::warning('GA4 pagePerformance failed: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Geographic breakdown — sessions by country + city. Photographer
     * dashboard heatmap. Filter by their page path so they only see
     * their own gallery viewers.
     */
    public function geoBreakdown(string $startDate = '30daysAgo', string $endDate = 'today', ?string $pagePathContains = null, int $limit = 20): array
    {
        if (!$this->isConfigured()) return [];

        $cacheKey = 'ga_geo:' . md5($startDate . $endDate . ($pagePathContains ?? '') . $limit);
        return Cache::remember($cacheKey, 1800, function () use ($startDate, $endDate, $pagePathContains, $limit) {
            try {
                $payload = [
                    'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
                    'dimensions' => [
                        ['name' => 'country'],
                        ['name' => 'city'],
                    ],
                    'metrics' => [['name' => 'activeUsers']],
                    'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]],
                    'limit'    => $limit,
                ];

                if ($pagePathContains) {
                    $payload['dimensionFilter'] = [
                        'filter' => [
                            'fieldName'    => 'pagePath',
                            'stringFilter' => ['matchType' => 'CONTAINS', 'value' => $pagePathContains],
                        ],
                    ];
                }

                return $this->parseRows($this->makeRequest('runReport', $payload), [
                    'country', 'city', 'users:int',
                ]);
            } catch (\Throwable $e) {
                Log::warning('GA4 geoBreakdown failed: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Multi-touch attribution table — sessions + conversions + revenue
     * per channel using GA4's data-driven attribution model. Admin sees
     * "Facebook ads got X conversions, but assisted Y more before
     * customer converted via Google search."
     */
    public function attributionTable(string $startDate = '30daysAgo', string $endDate = 'today', int $limit = 20): array
    {
        if (!$this->isConfigured()) return [];

        $cacheKey = 'ga_attribution:' . md5($startDate . $endDate . $limit);
        return Cache::remember($cacheKey, 1800, function () use ($startDate, $endDate, $limit) {
            try {
                return $this->parseRows($this->makeRequest('runReport', [
                    'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
                    'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
                    'metrics' => [
                        ['name' => 'sessions'],
                        ['name' => 'conversions'],
                        ['name' => 'totalRevenue'],
                    ],
                    'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                    'limit'    => $limit,
                ]), [
                    'channel',
                    'sessions:int',
                    'conversions:float',
                    'revenue:float',
                ]);
            } catch (\Throwable $e) {
                Log::warning('GA4 attributionTable failed: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Device + browser breakdown. Used by admin tab to prioritise
     * mobile-first vs desktop UX work.
     */
    public function deviceBreakdown(string $startDate = '30daysAgo', string $endDate = 'today'): array
    {
        if (!$this->isConfigured()) return [];

        $cacheKey = 'ga_device:' . md5($startDate . $endDate);
        return Cache::remember($cacheKey, 1800, function () use ($startDate, $endDate) {
            try {
                return $this->parseRows($this->makeRequest('runReport', [
                    'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
                    'dimensions' => [
                        ['name' => 'deviceCategory'],
                        ['name' => 'browser'],
                        ['name' => 'operatingSystem'],
                    ],
                    'metrics' => [
                        ['name' => 'sessions'],
                        ['name' => 'engagementRate'],
                    ],
                    'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                    'limit'    => 25,
                ]), [
                    'device', 'browser', 'os',
                    'sessions:int', 'engagement_rate:float',
                ]);
            } catch (\Throwable $e) {
                Log::warning('GA4 deviceBreakdown failed: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Bust all GA4 caches — call after admin updates property ID or
     * service account so next dashboard load fetches fresh data.
     */
    public function bustAllCaches(): void
    {
        try { Cache::flush(); } catch (\Throwable) {}
    }

    /* ───────────────── internals ───────────────── */

    private function propertyId(): string
    {
        return (string) AppSetting::get('google_analytics_property_id', '');
    }

    /**
     * Make an authenticated POST to GA4 Data API. Throws on HTTP error.
     */
    private function makeRequest(string $method, array $payload): array
    {
        $token = $this->auth->getAccessToken(self::SCOPE);
        $url   = self::ENDPOINT_BASE . '/' . $this->propertyId() . ':' . $method;

        $response = Http::withToken($token)
            ->timeout(15)
            ->retry(2, 250)
            ->post($url, $payload);

        if (!$response->successful()) {
            $msg = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException($msg);
        }

        return $response->json() ?: [];
    }

    /**
     * Translate GA4's nested rows array into a simpler list of assoc
     * arrays. The $columns parameter lists field names with optional
     * type cast suffix (':int', ':float').
     *
     * GA4 returns:
     *   { rows: [
     *       { dimensionValues: [{value:'X'}, {value:'Y'}],
     *         metricValues:    [{value:'42'}, {value:'0.5'}] },
     *       ...
     *   ] }
     *
     * We flatten dimensionValues then metricValues into one array
     * indexed by $columns labels.
     */
    private function parseRows(array $body, array $columns): array
    {
        $rows = $body['rows'] ?? [];
        $result = [];

        foreach ($rows as $row) {
            $dims    = array_column($row['dimensionValues'] ?? [], 'value');
            $metrics = array_column($row['metricValues']    ?? [], 'value');
            $values  = array_merge($dims, $metrics);

            $assoc = [];
            foreach ($columns as $i => $colSpec) {
                [$name, $cast] = array_pad(explode(':', $colSpec, 2), 2, null);
                $raw = $values[$i] ?? null;

                $assoc[$name] = match ($cast) {
                    'int'   => (int) $raw,
                    'float' => (float) $raw,
                    default => (string) ($raw ?? ''),
                };
            }
            $result[] = $assoc;
        }

        return $result;
    }

    /**
     * Friendly error messages for the most common GA4 failures.
     * Mirrors the pattern from GoogleCalendarService::humaniseError().
     */
    private function humaniseError(string $raw): array
    {
        $lower = strtolower($raw);

        if (str_contains($lower, 'permission_denied') || str_contains($lower, 'does not have sufficient permissions')) {
            $email = $this->auth->serviceAccountEmail();
            return [
                'ok'      => false,
                'message' => 'Service account ไม่มีสิทธิ์เข้า GA4 property',
                'fix'     => "ไปที่ GA4 → Admin → Property access management → กด \"+\" → ใส่ email:\n"
                    . "  {$email}\n"
                    . "ระดับ: Viewer (อ่านอย่างเดียว) → Add",
            ];
        }

        if (str_contains($lower, 'property_id') || str_contains($lower, 'invalid property') || str_contains($lower, 'not found')) {
            return [
                'ok'      => false,
                'message' => 'GA4 Property ID ไม่ถูกต้อง',
                'fix'     => "GA4 → Admin → Property Settings → Property details → คัดลอก Property ID\n"
                    . "เป็นตัวเลข ไม่ใช่ Measurement ID (G-XXXXXXX)",
            ];
        }

        if (str_contains($lower, 'analytics data api') && str_contains($lower, 'has not been used')) {
            return [
                'ok'      => false,
                'message' => 'Analytics Data API ยังไม่ enabled ใน Cloud project',
                'fix'     => "Cloud Console → APIs & Services → Library → ค้นหา \"Google Analytics Data API\" → Enable",
            ];
        }

        if (str_contains($lower, 'token') || str_contains($lower, 'invalid_grant')) {
            return [
                'ok'      => false,
                'message' => 'Service account JSON ไม่ถูกต้อง',
                'fix'     => "ตรวจ private_key ใน JSON ว่ายังครบไม่มี whitespace หลุด — re-download จาก Cloud Console",
            ];
        }

        return [
            'ok'      => false,
            'message' => 'เชื่อมต่อ GA4 ไม่สำเร็จ',
            'fix'     => 'รายละเอียด: ' . \Illuminate\Support\Str::limit($raw, 300),
        ];
    }
}

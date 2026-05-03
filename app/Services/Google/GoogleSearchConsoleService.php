<?php

namespace App\Services\Google;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GoogleSearchConsoleService — read keyword performance + impressions
 * from Search Console for our verified site.
 *
 * Why we need this on top of GA4:
 *   • GA4 doesn't include Google search query data — that lives in
 *     Search Console only (Google's privacy boundary)
 *   • Photographers desperately want to know which keywords lead to
 *     their galleries — drives SEO content decisions
 *
 * Auth: same service account as GA4 (different scope).
 *
 * Cost: free tier = 1,200 calls/min. We make ~30 calls/month for
 * monthly aggregates. Effectively zero cost.
 *
 * Setup outside our app:
 *   • Search Console → Settings → Users and permissions → add the
 *     service account email as a user (Restricted role is enough)
 *   • The site (https://loadroop.com) must already be a verified
 *     property in Search Console
 */
class GoogleSearchConsoleService
{
    private const ENDPOINT = 'https://www.googleapis.com/webmasters/v3/sites/{site}/searchAnalytics/query';
    private const SCOPE    = 'https://www.googleapis.com/auth/webmasters.readonly';

    public function __construct(
        private readonly GoogleApiAuth $auth,
    ) {}

    public function isConfigured(): bool
    {
        return $this->auth->isConfigured() && !empty($this->siteUrl());
    }

    public function testConnection(): array
    {
        if (!$this->auth->isConfigured()) {
            return ['ok' => false, 'message' => 'ยังไม่ได้อัปโหลด Service Account JSON'];
        }
        if (!$this->siteUrl()) {
            return ['ok' => false, 'message' => 'ยังไม่ได้ใส่ Site URL ของ Search Console'];
        }

        try {
            // Fetch last 7 days summary
            $rows = $this->query(['startDate' => date('Y-m-d', strtotime('-7 days')), 'endDate' => date('Y-m-d'), 'rowLimit' => 1]);
            return [
                'ok'      => true,
                'message' => '✓ เชื่อมต่อ Search Console สำเร็จ',
                'count'   => count($rows),
            ];
        } catch (\Throwable $e) {
            return $this->humaniseError($e->getMessage());
        }
    }

    /**
     * Top keywords ranked by clicks for the last N days.
     * Optional pageContains filter for per-photographer queries.
     *
     * @return array  list of ['query', 'clicks', 'impressions', 'ctr', 'position']
     */
    public function topKeywords(int $days = 30, ?string $pageContains = null, int $limit = 20): array
    {
        if (!$this->isConfigured()) return [];

        $cacheKey = 'sc_top_keywords:' . md5($days . '|' . ($pageContains ?? '') . '|' . $limit);
        return Cache::remember($cacheKey, 60 * 60 * 6, function () use ($days, $pageContains, $limit) {
            try {
                $payload = [
                    'startDate'  => date('Y-m-d', strtotime("-{$days} days")),
                    'endDate'    => date('Y-m-d'),
                    'dimensions' => ['query'],
                    'rowLimit'   => $limit,
                ];

                if ($pageContains) {
                    $payload['dimensionFilterGroups'] = [[
                        'filters' => [[
                            'dimension'  => 'page',
                            'operator'   => 'contains',
                            'expression' => $pageContains,
                        ]],
                    ]];
                }

                $rows = $this->query($payload);
                return array_map(fn ($r) => [
                    'query'       => $r['keys'][0] ?? '',
                    'clicks'      => (int) ($r['clicks'] ?? 0),
                    'impressions' => (int) ($r['impressions'] ?? 0),
                    'ctr'         => round(((float) ($r['ctr'] ?? 0)) * 100, 2),
                    'position'    => round((float) ($r['position'] ?? 0), 1),
                ], $rows);
            } catch (\Throwable $e) {
                Log::warning('Search Console topKeywords failed: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Top performing pages — which URLs get the most clicks from search.
     */
    public function topPages(int $days = 30, int $limit = 20): array
    {
        if (!$this->isConfigured()) return [];

        return Cache::remember("sc_top_pages:{$days}:{$limit}", 60 * 60 * 6, function () use ($days, $limit) {
            try {
                $rows = $this->query([
                    'startDate'  => date('Y-m-d', strtotime("-{$days} days")),
                    'endDate'    => date('Y-m-d'),
                    'dimensions' => ['page'],
                    'rowLimit'   => $limit,
                ]);
                return array_map(fn ($r) => [
                    'page'        => $r['keys'][0] ?? '',
                    'clicks'      => (int) ($r['clicks'] ?? 0),
                    'impressions' => (int) ($r['impressions'] ?? 0),
                    'ctr'         => round(((float) ($r['ctr'] ?? 0)) * 100, 2),
                    'position'    => round((float) ($r['position'] ?? 0), 1),
                ], $rows);
            } catch (\Throwable $e) {
                Log::warning('Search Console topPages failed: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Site-wide totals over a period — clicks, impressions, avg CTR,
     * avg position. For overview KPI cards.
     */
    public function summary(int $days = 30): array
    {
        if (!$this->isConfigured()) return ['clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0];

        return Cache::remember("sc_summary:{$days}", 60 * 60 * 6, function () use ($days) {
            try {
                $rows = $this->query([
                    'startDate'  => date('Y-m-d', strtotime("-{$days} days")),
                    'endDate'    => date('Y-m-d'),
                    'dimensions' => [],   // aggregate all
                ]);
                $r = $rows[0] ?? [];
                return [
                    'clicks'      => (int) ($r['clicks'] ?? 0),
                    'impressions' => (int) ($r['impressions'] ?? 0),
                    'ctr'         => round(((float) ($r['ctr'] ?? 0)) * 100, 2),
                    'position'    => round((float) ($r['position'] ?? 0), 1),
                ];
            } catch (\Throwable $e) {
                Log::warning('Search Console summary failed: ' . $e->getMessage());
                return ['clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0];
            }
        });
    }

    public function bustAllCaches(): void
    {
        try { Cache::flush(); } catch (\Throwable) {}
    }

    /* ─────────────────── internals ─────────────────── */

    private function siteUrl(): string
    {
        return (string) AppSetting::get('google_search_console_site_url', '');
    }

    private function query(array $payload): array
    {
        $token = $this->auth->getAccessToken(self::SCOPE);
        $url   = str_replace('{site}', urlencode($this->siteUrl()), self::ENDPOINT);

        $response = Http::withToken($token)
            ->timeout(15)
            ->retry(2, 250)
            ->post($url, $payload);

        if (!$response->successful()) {
            $msg = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException($msg);
        }

        return $response->json('rows', []);
    }

    private function humaniseError(string $raw): array
    {
        $lower = strtolower($raw);

        if (str_contains($lower, 'permission') || str_contains($lower, "user does not have")) {
            $email = $this->auth->serviceAccountEmail();
            return [
                'ok'      => false,
                'message' => 'Service account ไม่มีสิทธิ์เข้า Search Console property',
                'fix'     => "Search Console → Settings → Users and permissions → Add user → ใส่ email:\n  {$email}\nบทบาท: Restricted (พอ)",
            ];
        }

        if (str_contains($lower, 'site') && (str_contains($lower, 'not found') || str_contains($lower, 'unverified'))) {
            return [
                'ok'      => false,
                'message' => 'Site URL ไม่ตรงกับ verified property ใน Search Console',
                'fix'     => "ตรวจรูปแบบ URL — รวมเครื่องหมายทับท้าย เช่น \"https://loadroop.com/\"\nหรือใช้แบบ \"sc-domain:loadroop.com\" ถ้าเป็น domain property",
            ];
        }

        if (str_contains($lower, 'has not been used') && str_contains($lower, 'search console')) {
            return [
                'ok'      => false,
                'message' => 'Search Console API ยังไม่ enabled ใน Cloud project',
                'fix'     => "Cloud Console → APIs & Services → Library → \"Google Search Console API\" → Enable",
            ];
        }

        return [
            'ok'      => false,
            'message' => 'เชื่อมต่อ Search Console ไม่สำเร็จ',
            'fix'     => 'รายละเอียด: ' . \Illuminate\Support\Str::limit($raw, 300),
        ];
    }
}

<?php

namespace App\Services\Marketing;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * UTM attribution — logs inbound traffic source and attaches it to orders.
 *
 * Captures:
 *   - utm_source / utm_medium / utm_campaign / utm_term / utm_content
 *   - gclid (Google Ads), fbclid (Facebook), lineclid (LINE Ads)
 *   - referer, landing URL, user agent, IP
 *
 * Attribution model: **first-touch** (preserves the source that brought the
 * visitor) — set once per session. Can be swapped for last-touch via config.
 */
class AttributionService
{
    public function __construct(protected MarketingService $marketing) {}

    protected const SESSION_KEY = '_mkt_utm';
    protected const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
    protected const CLICK_IDS = ['gclid', 'fbclid', 'lineclid'];

    /**
     * Called from middleware on every request.
     * If URL contains UTM/click-id params, logs a new attribution row.
     */
    public function capture(Request $request): void
    {
        if (!$this->marketing->utmEnabled()) return;

        // Skip bots, assets, api
        $path = $request->path();
        if (str_starts_with($path, 'api/') || str_contains($path, '.')) return;
        if ($this->looksLikeBot($request->userAgent() ?? '')) return;

        $utm = $this->extractParams($request);
        if (empty($utm['has_any'])) return;   // no tracking params — skip

        try {
            $sessionId = session()->getId() ?: bin2hex(random_bytes(16));

            $id = DB::table('marketing_utm_attributions')->insertGetId([
                'session_id'   => $sessionId,
                'user_id'      => auth()->id(),
                'utm_source'   => $utm['utm_source'] ?? null,
                'utm_medium'   => $utm['utm_medium'] ?? null,
                'utm_campaign' => $utm['utm_campaign'] ?? null,
                'utm_term'     => $utm['utm_term'] ?? null,
                'utm_content'  => $utm['utm_content'] ?? null,
                'gclid'        => $utm['gclid'] ?? null,
                'fbclid'       => $utm['fbclid'] ?? null,
                'lineclid'     => $utm['lineclid'] ?? null,
                'referer'      => mb_substr((string) $request->header('referer', ''), 0, 500) ?: null,
                'landing_page' => mb_substr($request->fullUrl(), 0, 500),
                'user_agent'   => mb_substr((string) $request->userAgent(), 0, 255),
                'ip'           => $request->ip(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // Store attribution ID in session (first-touch preservation)
            if (!session()->has(self::SESSION_KEY)) {
                session([self::SESSION_KEY => $id]);
            }
        } catch (\Throwable $e) {
            Log::warning('UTM capture failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get the currently-attributed UTM row for this session.
     */
    public function current(): ?array
    {
        $id = session(self::SESSION_KEY);
        if (!$id) return null;
        try {
            $row = DB::table('marketing_utm_attributions')->where('id', $id)->first();
            return $row ? (array) $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Attach current attribution to a new order (call from checkout).
     */
    public function attachToOrder(int $orderId): void
    {
        if (!$this->marketing->utmEnabled()) return;
        $id = session(self::SESSION_KEY);
        if (!$id) return;

        try {
            DB::table('marketing_utm_attributions')
                ->where('id', $id)
                ->update(['order_id' => $orderId, 'updated_at' => now()]);
            DB::table('orders')
                ->where('id', $orderId)
                ->update(['utm_attribution_id' => $id]);
        } catch (\Throwable $e) {
            Log::warning('UTM attach failed', ['order' => $orderId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Channel roll-up for analytics dashboards.
     * Returns: [ ['source' => 'facebook', 'orders' => 42, 'revenue' => 15000], ... ]
     */
    public function channelPerformance(int $days = 30): array
    {
        try {
            return DB::table('marketing_utm_attributions as u')
                ->leftJoin('orders as o', 'o.id', '=', 'u.order_id')
                ->selectRaw("
                    COALESCE(u.utm_source, 'direct') as source,
                    COALESCE(u.utm_medium, 'none')  as medium,
                    COUNT(DISTINCT u.id)            as visits,
                    COUNT(DISTINCT o.id)            as orders,
                    COALESCE(SUM(o.total_amount), 0) as revenue
                ")
                ->where('u.created_at', '>=', now()->subDays($days))
                ->groupBy('source', 'medium')
                ->orderByDesc('revenue')
                ->limit(20)
                ->get()
                ->map(fn($r) => (array) $r)
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ── Internals ────────────────────────────────────────────────

    protected function extractParams(Request $request): array
    {
        $out = ['has_any' => false];
        foreach (self::UTM_KEYS as $k) {
            $v = $request->query($k);
            if ($v) { $out[$k] = mb_substr((string) $v, 0, 128); $out['has_any'] = true; }
        }
        foreach (self::CLICK_IDS as $k) {
            $v = $request->query($k);
            if ($v) { $out[$k] = mb_substr((string) $v, 0, 128); $out['has_any'] = true; }
        }
        return $out;
    }

    protected function looksLikeBot(string $ua): bool
    {
        if ($ua === '') return true;
        return (bool) preg_match(
            '/bot|crawl|spider|slurp|bingbot|googlebot|yandex|ahrefs|semrush|facebookexternalhit|pingdom|uptimerobot/i',
            $ua
        );
    }
}

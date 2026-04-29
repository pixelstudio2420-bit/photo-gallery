<?php

namespace App\Services\Monetization;

use App\Models\AdCampaign;
use App\Models\AdClick;
use App\Models\AdCreative;
use App\Models\AdImpression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ad serving — pick a creative for a placement, log the impression,
 * and route the eventual click to the destination URL.
 *
 * Public API
 * ----------
 *   pickCreative($placement)           → ?AdCreative   (or null = no ads to show)
 *   recordImpression($creative, $req)  → AdImpression
 *   recordClick($creative, $req)       → AdClick
 *
 * Selection algorithm
 * -------------------
 * 1. Filter to (active campaigns within window AND status=active AND
 *    spent < budget_cap) joined with active creatives matching $placement.
 * 2. Weighted-random pick (creative.weight) — higher weight = more
 *    served. This gives advertisers control over A/B testing without
 *    needing a separate "experiment" table.
 *
 * Anti-fraud
 * ----------
 * recordImpression() drops bot UAs and rate-limits per (ip, ua_hash).
 * recordClick() flags clicks that arrive within 100ms of impression
 * (humans don't move that fast) AND clicks from IPs with >5 clicks/hour.
 */
class AdServingService
{
    /** UAs that always count as bots — case-insensitive substring match. */
    private const BOT_UA_PATTERNS = [
        'bot', 'crawler', 'spider', 'curl', 'wget', 'headless',
        'python-requests', 'go-http-client', 'java/', 'okhttp',
        'apachebench', 'phantom', 'puppeteer', 'playwright',
    ];

    public function pickCreative(string $placement): ?AdCreative
    {
        // Cache the candidate set for 60s — campaign state doesn't move
        // faster than that. Per-request "weighted random pick" still
        // happens in PHP so each visitor sees a different creative.
        $candidates = Cache::remember("ad.candidates.{$placement}", 60, function () use ($placement) {
            $now = now();
            return AdCreative::query()
                ->where('placement', $placement)
                ->where('is_active', true)
                ->whereExists(function ($q) use ($now) {
                    $q->select(DB::raw(1))
                        ->from('ad_campaigns as c')
                        ->whereColumn('c.id', 'ad_creatives.campaign_id')
                        ->where('c.status', AdCampaign::STATUS_ACTIVE)
                        ->where('c.starts_at', '<=', $now)
                        ->where('c.ends_at',   '>=', $now)
                        // Budget gate — spent < cap, OR cap is null (unlimited).
                        ->where(function ($w) {
                            $w->whereNull('c.budget_cap_thb')
                              ->orWhereColumn('c.spent_thb', '<', 'c.budget_cap_thb');
                        });
                })
                ->get();
        });

        if ($candidates->isEmpty()) return null;

        // Weighted random pick. Sum of weights → roll a random integer
        // in [0, totalWeight) → walk the list until we accumulate past it.
        $totalWeight = (int) $candidates->sum('weight');
        if ($totalWeight <= 0) return $candidates->first();

        $roll = random_int(0, $totalWeight - 1);
        $running = 0;
        foreach ($candidates as $c) {
            $running += (int) $c->weight;
            if ($roll < $running) return $c;
        }
        return $candidates->last();
    }

    /**
     * Log an impression. Returns null silently when the request looks
     * like a bot (no client error) — bots can refresh ads endlessly
     * but we don't bill the advertiser for them.
     */
    public function recordImpression(AdCreative $creative, Request $request): ?AdImpression
    {
        $ua  = (string) $request->userAgent();
        $bot = $this->isBotUa($ua);

        // Per-IP rate limit: max 1 impression per (ip, creative) per 30s.
        // Stops the same user reload-spamming inflating impression count.
        $rateKey = "ad.imp.rate:{$creative->id}:" . $request->ip();
        if (Cache::has($rateKey)) {
            return null;   // duplicate within window — drop
        }
        Cache::put($rateKey, 1, 30);

        $imp = AdImpression::create([
            'creative_id'      => $creative->id,
            'campaign_id'      => $creative->campaign_id,
            'placement'        => $creative->placement,
            'ip'               => $request->ip(),
            'user_agent_hash'  => substr(sha1($ua), 0, 16),
            'session_id'       => substr((string) $request->session()?->getId(), 0, 80),
            'user_id'          => $request->user()?->id,
            'referrer_host'    => $this->refererHost($request),
            'is_bot'           => $bot,
            'seen_at'          => now(),
        ]);

        // Update spent on CPM campaigns: rate_thb is per 1000 impressions.
        if (!$bot) {
            $this->bumpSpend($creative->campaign_id, AdCampaign::PRICING_CPM);
        }

        return $imp;
    }

    /**
     * Log a click. Returns the click row even when flagged — caller still
     * needs to redirect the user; suspicious flag just affects billing.
     */
    public function recordClick(AdCreative $creative, Request $request): AdClick
    {
        $ua  = (string) $request->userAgent();
        $uaHash = substr(sha1($ua), 0, 16);
        $ip  = (string) $request->ip();

        $reason = null;
        $suspicious = false;

        if ($this->isBotUa($ua)) {
            $suspicious = true; $reason = 'bot_ua';
        } elseif ($this->isRateAnomaly($ip, $uaHash)) {
            $suspicious = true; $reason = 'rate_spike';
        } elseif ($this->isImpressionTooRecent($creative->id, $ip, $uaHash)) {
            $suspicious = true; $reason = 'instant_click';
        }

        $click = AdClick::create([
            'creative_id'     => $creative->id,
            'campaign_id'     => $creative->campaign_id,
            'ip'              => $ip,
            'user_agent_hash' => $uaHash,
            'user_id'         => $request->user()?->id,
            'referrer_host'   => $this->refererHost($request),
            'is_suspicious'   => $suspicious,
            'fraud_reason'    => $reason,
            'clicked_at'      => now(),
        ]);

        // Only bill non-suspicious clicks on CPC campaigns.
        if (!$suspicious) {
            $this->bumpSpend($creative->campaign_id, AdCampaign::PRICING_CPC);
        }

        return $click;
    }

    /* ────────────────── private helpers ────────────────── */

    /**
     * Add the per-event spend to the campaign's spent_thb counter, and
     * mark the campaign EXHAUSTED if it just crossed its budget cap.
     */
    private function bumpSpend(int $campaignId, string $modelMatching): void
    {
        $camp = AdCampaign::find($campaignId);
        if (!$camp || $camp->pricing_model !== $modelMatching) return;

        // CPM = rate per 1000 impressions, so each impression = rate/1000.
        // CPC = rate per click.
        $delta = $modelMatching === AdCampaign::PRICING_CPM
            ? (float) $camp->rate_thb / 1000
            : (float) $camp->rate_thb;

        $camp->increment('spent_thb', $delta);
        $camp->refresh();

        if ($camp->budget_cap_thb !== null && $camp->spent_thb >= $camp->budget_cap_thb) {
            $camp->update(['status' => AdCampaign::STATUS_EXHAUSTED]);
            // Bust the candidates cache so the next pickCreative() drops
            // this campaign even if it's still inside the 60s window.
            foreach (['homepage_banner', 'sidebar', 'search_inline', 'landing_native'] as $p) {
                Cache::forget("ad.candidates.{$p}");
            }
        }
    }

    private function isBotUa(string $ua): bool
    {
        if ($ua === '') return true;
        $lower = strtolower($ua);
        foreach (self::BOT_UA_PATTERNS as $needle) {
            if (str_contains($lower, $needle)) return true;
        }
        return false;
    }

    /** > 5 clicks per hour from same (ip, ua) = anomaly. */
    private function isRateAnomaly(string $ip, string $uaHash): bool
    {
        $key = "ad.click.rate:{$ip}:{$uaHash}";
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, 3600);
        return $count > 5;
    }

    /**
     * Click landing within 100ms of the impression is impossible for a
     * human — chrome paint + reaction time is at least 200-400ms. Almost
     * always a bot or a malicious script.
     */
    private function isImpressionTooRecent(int $creativeId, string $ip, string $uaHash): bool
    {
        $key = "ad.imp.time:{$creativeId}:{$ip}:{$uaHash}";
        $impAt = Cache::get($key);
        if (!$impAt) return false;
        $deltaMs = (microtime(true) - (float) $impAt) * 1000;
        return $deltaMs < 100;
    }

    private function refererHost(Request $req): ?string
    {
        $ref = $req->headers->get('referer');
        if (!$ref) return null;
        $host = parse_url($ref, PHP_URL_HOST);
        return $host ? mb_substr($host, 0, 120) : null;
    }
}

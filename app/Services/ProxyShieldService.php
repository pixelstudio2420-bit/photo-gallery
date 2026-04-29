<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProxyShieldService
{
    /**
     * Proxy header names that indicate forwarded / proxy traffic.
     */
    private const PROXY_HEADERS = [
        'X-Forwarded-For',
        'Via',
        'X-Real-IP',
        'X-Proxy-ID',
        'Forwarded',
        'X-Originating-IP',
        'X-Remote-Addr',
        'X-Remote-IP',
        'CF-Connecting-IP',  // suspicious only when NOT behind Cloudflare
        'X-Client-IP',
        'Client-IP',
    ];

    /**
     * Datacenter CIDR ranges for major cloud providers.
     */
    private const DATACENTER_CIDRS = [
        // AWS
        '3.0.0.0/8',
        '18.0.0.0/8',
        '52.0.0.0/8',
        '54.0.0.0/8',
        // GCP
        '34.0.0.0/8',
        '35.0.0.0/8',
        // DigitalOcean
        '104.131.0.0/16',
        '159.65.0.0/16',
        '167.99.0.0/16',
        // Azure
        '13.64.0.0/11',
        '40.74.0.0/15',
        // OVH
        '51.68.0.0/16',
        '51.75.0.0/16',
        '51.77.0.0/16',
    ];

    /**
     * rDNS keyword patterns that suggest proxy / hosting infrastructure.
     */
    private const RDNS_PATTERNS = [
        'vpn', 'proxy', 'tor', 'exit', 'relay',
        'hosting', 'vps', 'cloud', 'server', 'dedicated',
    ];

    // ---------------------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------------------

    /**
     * Check whether an IP is a proxy / VPN / TOR / datacenter address.
     *
     * @return array{is_proxy: bool, score: int, type: string, details: array}
     */
    public function checkIp(string $ip): array
    {
        $disabled = AppSetting::get('proxy_shield_enabled', '1') !== '1';
        if ($disabled) {
            return ['is_proxy' => false, 'score' => 0, 'type' => 'disabled', 'details' => []];
        }

        if ($this->isWhitelisted($ip)) {
            return ['is_proxy' => false, 'score' => 0, 'type' => 'whitelisted', 'details' => []];
        }

        $cached = $this->checkCache($ip);
        if ($cached !== null) {
            return $cached;
        }

        // Obtain current request for header-based checks
        $request = app('request');

        $details    = [];
        $totalScore = 0;

        // Layer 1: proxy headers
        $headerScore = $this->checkProxyHeaders($request);
        $totalScore += $headerScore;
        if ($headerScore > 0) {
            $details['proxy_headers'] = $headerScore;
        }

        // Layer 2: reverse DNS
        $rdnsScore = $this->checkReverseDns($ip);
        $totalScore += $rdnsScore;
        if ($rdnsScore > 0) {
            $details['reverse_dns'] = $rdnsScore;
        }

        // Layer 3: TOR exit node
        $torScore = $this->checkTorExitNode($ip);
        $totalScore += $torScore;
        if ($torScore > 0) {
            $details['tor_exit'] = $torScore;
        }

        // Layer 4: datacenter CIDR
        $dcScore = $this->checkDatacenterCidr($ip);
        $totalScore += $dcScore;
        if ($dcScore > 0) {
            $details['datacenter_cidr'] = $dcScore;
        }

        // Layer 5: header anomalies
        $anomalyScore = $this->checkHeaderAnomalies($request);
        $totalScore   += $anomalyScore;
        if ($anomalyScore > 0) {
            $details['header_anomalies'] = $anomalyScore;
        }

        $score     = min(100, $totalScore);
        $type      = $this->determineType($details, $score);
        $threshold = (int) AppSetting::get('proxy_shield_block_threshold', '60');
        $isProxy   = $score >= $threshold;

        $result = [
            'is_proxy' => $isProxy,
            'score'    => $score,
            'type'     => $type,
            'details'  => $details,
        ];

        $this->cacheResult($ip, $result);

        if ($score > 20) {
            $this->logDetection($ip, $type, $score, $isProxy);
        }

        return $result;
    }

    // ---------------------------------------------------------------------------
    // Whitelist management
    // ---------------------------------------------------------------------------

    /**
     * Check whether an IP is on either whitelist table.
     */
    public function isWhitelisted(string $ip): bool
    {
        try {
            if (DB::table('proxy_whitelist')->where('ip', $ip)->exists()) {
                return true;
            }
        } catch (\Exception $e) {
            Log::channel('single')->error('[ProxyShield] isWhitelisted proxy_whitelist check failed: ' . $e->getMessage());
        }

        try {
            if (DB::table('proxy_shield_whitelist')->where('ip', $ip)->exists()) {
                return true;
            }
        } catch (\Exception $e) {
            Log::channel('single')->error('[ProxyShield] isWhitelisted proxy_shield_whitelist check failed: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Add an IP to the whitelist (both tables for consistency).
     */
    public function addToWhitelist(string $ip, string $reason, ?string $addedBy = null): void
    {
        $now = now();

        // proxy_whitelist
        try {
            if (!DB::table('proxy_whitelist')->where('ip', $ip)->exists()) {
                DB::table('proxy_whitelist')->insert([
                    'ip'         => $ip,
                    'reason'     => $reason,
                    'added_by'   => $addedBy,
                    'created_at' => $now,
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('single')->error('[ProxyShield] addToWhitelist proxy_whitelist failed: ' . $e->getMessage());
        }

        // proxy_shield_whitelist
        try {
            if (!DB::table('proxy_shield_whitelist')->where('ip', $ip)->exists()) {
                DB::table('proxy_shield_whitelist')->insert([
                    'ip'         => $ip,
                    'reason'     => $reason,
                    'added_by'   => $addedBy,
                    'created_at' => $now,
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('single')->error('[ProxyShield] addToWhitelist proxy_shield_whitelist failed: ' . $e->getMessage());
        }

        // Invalidate cache for this IP
        try {
            DB::table('proxy_cache')->where('ip', $ip)->delete();
            DB::table('proxy_shield_cache')->where('ip', $ip)->delete();
        } catch (\Exception $e) {
            // Cache invalidation failure is non-critical
        }
    }

    /**
     * Remove an IP from all whitelist tables.
     */
    public function removeFromWhitelist(string $ip): void
    {
        try {
            DB::table('proxy_whitelist')->where('ip', $ip)->delete();
        } catch (\Exception $e) {
            Log::channel('single')->error('[ProxyShield] removeFromWhitelist proxy_whitelist failed: ' . $e->getMessage());
        }

        try {
            DB::table('proxy_shield_whitelist')->where('ip', $ip)->delete();
        } catch (\Exception $e) {
            Log::channel('single')->error('[ProxyShield] removeFromWhitelist proxy_shield_whitelist failed: ' . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------------------
    // Detection layers (private)
    // ---------------------------------------------------------------------------

    /**
     * Check cache tables for a previously stored result.
     */
    private function checkCache(string $ip): ?array
    {
        $ttlMinutes = (int) AppSetting::get('proxy_shield_cache_ttl', '60');

        // proxy_cache (has expires_at)
        try {
            $row = DB::table('proxy_cache')
                ->where('ip', $ip)
                ->where('expires_at', '>', now())
                ->first();

            if ($row) {
                $result = json_decode($row->result, true);
                if (is_array($result)) {
                    return $result;
                }
            }
        } catch (\Exception $e) {
            Log::channel('single')->error('[ProxyShield] checkCache proxy_cache failed: ' . $e->getMessage());
        }

        // proxy_shield_cache (has checked_at, no expires_at column)
        try {
            $row = DB::table('proxy_shield_cache')
                ->where('ip', $ip)
                ->where('checked_at', '>=', now()->subMinutes($ttlMinutes))
                ->first();

            if ($row) {
                $result = json_decode($row->result, true);
                if (is_array($result)) {
                    return $result;
                }
            }
        } catch (\Exception $e) {
            Log::channel('single')->error('[ProxyShield] checkCache proxy_shield_cache failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Store a detection result in both cache tables.
     */
    private function cacheResult(string $ip, array $result): void
    {
        $ttlMinutes = (int) AppSetting::get('proxy_shield_cache_ttl', '60');
        $now        = now();
        $expiresAt  = $now->copy()->addMinutes($ttlMinutes);
        $encoded    = json_encode($result);

        // proxy_cache
        try {
            $existing = DB::table('proxy_cache')->where('ip', $ip)->first();
            if ($existing) {
                DB::table('proxy_cache')->where('ip', $ip)->update([
                    'result'     => $encoded,
                    'checked_at' => $now,
                    'expires_at' => $expiresAt,
                ]);
            } else {
                DB::table('proxy_cache')->insert([
                    'ip'         => $ip,
                    'result'     => $encoded,
                    'checked_at' => $now,
                    'expires_at' => $expiresAt,
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('single')->error('[ProxyShield] cacheResult proxy_cache failed: ' . $e->getMessage());
        }

        // proxy_shield_cache
        try {
            $existing = DB::table('proxy_shield_cache')->where('ip', $ip)->first();
            if ($existing) {
                DB::table('proxy_shield_cache')->where('ip', $ip)->update([
                    'result'     => $encoded,
                    'checked_at' => $now,
                ]);
            } else {
                DB::table('proxy_shield_cache')->insert([
                    'ip'         => $ip,
                    'result'     => $encoded,
                    'checked_at' => $now,
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('single')->error('[ProxyShield] cacheResult proxy_shield_cache failed: ' . $e->getMessage());
        }
    }

    /**
     * Check for proxy-indicating HTTP headers. Returns score 0-25.
     */
    private function checkProxyHeaders(Request $request): int
    {
        $score = 0;

        foreach (self::PROXY_HEADERS as $header) {
            if ($request->hasHeader($header)) {
                $score += 10;
                if ($score >= 25) {
                    return 25;
                }
            }
        }

        return min(25, $score);
    }

    /**
     * Check reverse DNS hostname for proxy/hosting keywords. Returns score 0-40.
     */
    private function checkReverseDns(string $ip): int
    {
        try {
            $hostname = @gethostbyaddr($ip);

            // gethostbyaddr returns the IP itself on failure
            if ($hostname === false || $hostname === $ip) {
                return 0;
            }

            $hostname = strtolower($hostname);
            $score    = 0;

            foreach (self::RDNS_PATTERNS as $pattern) {
                if (strpos($hostname, $pattern) !== false) {
                    $score += 25;
                    if ($score >= 40) {
                        return 40;
                    }
                }
            }

            return min(40, $score);
        } catch (\Exception $e) {
            Log::channel('single')->error('[ProxyShield] checkReverseDns failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * DNS-based TOR exit node check. Returns 40 if confirmed TOR exit, 0 otherwise.
     */
    private function checkTorExitNode(string $ip): int
    {
        try {
            // Reverse the IP octets
            $parts   = explode('.', $ip);
            if (count($parts) !== 4) {
                return 0; // IPv6 or invalid — skip
            }
            $reversed = implode('.', array_reverse($parts));

            // Use port 80 and the TOR exit list server IP (82.94.251.227)
            $query = "{$reversed}.80.82.94.251.227.ip-port.exitlist.torproject.org";

            $resolved = @gethostbyname($query);

            // Positive match when the query resolves to 127.0.0.2
            if ($resolved === '127.0.0.2') {
                return 40;
            }

            return 0;
        } catch (\Exception $e) {
            Log::channel('single')->error('[ProxyShield] checkTorExitNode failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check whether the IP falls within known datacenter CIDR ranges. Returns 30 or 0.
     */
    private function checkDatacenterCidr(string $ip): int
    {
        // Only handle IPv4
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return 0;
        }

        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return 0;
        }

        foreach (self::DATACENTER_CIDRS as $cidr) {
            [$network, $bits] = explode('/', $cidr);
            $networkLong = ip2long($network);
            $mask        = ~((1 << (32 - (int)$bits)) - 1);

            if (($ipLong & $mask) === ($networkLong & $mask)) {
                return 30;
            }
        }

        return 0;
    }

    /**
     * Detect header anomalies that suggest non-browser / automated clients. Returns score 0-15.
     */
    private function checkHeaderAnomalies(Request $request): int
    {
        $score = 0;

        // Missing Accept-Language
        if (!$request->hasHeader('Accept-Language')) {
            $score += 5;
        }

        // Missing Accept
        if (!$request->hasHeader('Accept')) {
            $score += 5;
        }

        // Empty User-Agent
        $ua = $request->userAgent();
        if (empty($ua)) {
            $score += 10;
            return min(15, $score);
        }

        // Bot patterns in User-Agent
        $botPatterns = ['bot', 'crawler', 'spider', 'curl', 'wget', 'python', 'java/', 'go-http', 'libwww', 'scrapy', 'httpclient'];
        $uaLower = strtolower($ua);
        foreach ($botPatterns as $pattern) {
            if (strpos($uaLower, $pattern) !== false) {
                $score += 10;
                break;
            }
        }

        // HTTP/1.0 with keep-alive (unusual combination)
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? '';
        if ($protocol === 'HTTP/1.0' && strtolower($request->header('Connection', '')) === 'keep-alive') {
            $score += 5;
        }

        return min(15, $score);
    }

    // ---------------------------------------------------------------------------
    // Logging (private)
    // ---------------------------------------------------------------------------

    /**
     * Log a proxy detection event to both log tables.
     */
    private function logDetection(string $ip, string $type, int $confidence, bool $blocked): void
    {
        $now = now();

        // proxy_detections
        try {
            $allowedTypes = ['proxy', 'vpn', 'tor', 'datacenter', 'bot', 'residential'];
            $detType      = in_array($type, $allowedTypes) ? $type : 'proxy';

            DB::table('proxy_detections')->insert([
                'ip'             => $ip,
                'detection_type' => $detType,
                'confidence'     => $confidence,
                'provider'       => null,
                'country'        => null,
                'asn'            => null,
                'org'            => null,
                'is_blocked'     => $blocked ? 1 : 0,
                'headers_suspicious' => null,
                'created_at'     => $now,
            ]);
        } catch (\Exception $e) {
            Log::channel('single')->error('[ProxyShield] logDetection proxy_detections failed: ' . $e->getMessage());
        }

        // proxy_shield_log
        try {
            DB::table('proxy_shield_log')->insert([
                'ip'             => $ip,
                'detection_type' => substr($type, 0, 30),
                'confidence'     => min(255, $confidence),
                'provider'       => null,
                'headers_found'  => null,
                'action_taken'   => $blocked ? 'block' : 'monitor',
                'user_agent'     => substr(app('request')->userAgent() ?? '', 0, 500),
                'created_at'     => $now,
            ]);
        } catch (\Exception $e) {
            Log::channel('single')->error('[ProxyShield] logDetection proxy_shield_log failed: ' . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------------

    /**
     * Determine a human-readable detection type label from the score breakdown.
     */
    private function determineType(array $details, int $score): string
    {
        if (isset($details['tor_exit']) && $details['tor_exit'] >= 40) {
            return 'tor';
        }
        if (isset($details['datacenter_cidr']) && $details['datacenter_cidr'] >= 30) {
            return 'datacenter';
        }
        if (isset($details['reverse_dns']) && $details['reverse_dns'] >= 25) {
            return 'vpn';
        }
        if (isset($details['header_anomalies']) && $details['header_anomalies'] >= 10) {
            return 'bot';
        }
        if ($score > 0) {
            return 'proxy';
        }
        return 'clean';
    }
}

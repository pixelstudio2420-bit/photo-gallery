<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AppSetting;

class CheckFirewall
{
    /**
     * Malicious pattern sets for request inspection.
     */
    private array $sqlPatterns = [
        '/union\s+select/i',
        '/or\s+1\s*=\s*1/i',
        '/;\s*drop\s+table/i',
        '/;\s*delete\s+from/i',
        '/;\s*insert\s+into/i',
        '/;\s*update\s+\w+\s+set/i',
        '/select\s+.*\s+from\s+/i',
        '/exec\s*\(/i',
        '/xp_cmdshell/i',
        '/information_schema/i',
    ];

    private array $xssPatterns = [
        '/<script[\s>]/i',
        '/javascript\s*:/i',
        '/onerror\s*=/i',
        '/onload\s*=/i',
        '/onclick\s*=/i',
        '/<iframe/i',
        '/<object/i',
        '/eval\s*\(/i',
        '/document\s*\.\s*cookie/i',
    ];

    private array $traversalPatterns = [
        '/\.\.\//i',
        '/\.\.\\\\/',
        '/\/etc\/passwd/i',
        '/\/etc\/shadow/i',
        '/\/proc\/self/i',
        '/\/windows\/system32/i',
        '/%2e%2e%2f/i',
        '/%252e%252e/i',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        // 1. Check if firewall is enabled (cached in-memory via AppSetting)
        if (AppSetting::get('security_firewall_enabled', '0') !== '1') {
            return $next($request);
        }

        $ip = $request->ip();

        // 1b. Geo-access — runs independently of the rest of the firewall so
        //     country gates can be enforced even when no IP rule exists yet.
        //     Cached per-IP for 5 min so the ip-api.com lookup stays cheap.
        try {
            $geoAllowed = \Illuminate\Support\Facades\Cache::remember(
                "fw:geo:{$ip}", 300,
                fn() => app(\App\Services\GeoAccessService::class)->checkAccess($ip)
            );
            if (!$geoAllowed) {
                try {
                    DB::table('security_logs')->insert([
                        'ip'         => $ip,
                        'type'       => 'geo_blocked',
                        'detail'     => 'Country not in access list',
                        'uri'        => $request->getRequestUri(),
                        'ua'         => $request->userAgent(),
                        'created_at' => now(),
                    ]);
                } catch (\Throwable) {}
                return response('Forbidden — geo-restricted', 403);
            }
        } catch (\Throwable) {
            // Geo failures must NEVER block traffic — fail open.
        }

        // 1c. Threat-intelligence — analyze the request, auto-respond if the
        //     score is high. Disabled by default (security_threat_enabled).
        //     Asset URLs short-circuit so the analyzer doesn't run on every
        //     CSS/JS hit.
        $isAssetReq = preg_match('/\.(css|js|jpg|jpeg|png|gif|svg|ico|woff2?|ttf|eot|map)$/i', $request->getPathInfo());
        if (!$isAssetReq && AppSetting::get('security_threat_enabled', '0') === '1') {
            try {
                $threats   = app(\App\Services\ThreatIntelligenceService::class);
                $analysis  = $threats->analyzeRequest($request);
                $score     = (int) ($analysis['risk_score'] ?? 0);

                if ($score >= 71) {
                    // Block + record incident — autoRespond handles logging.
                    $threats->autoRespond($score, $ip);
                    return response('Forbidden — threat detected', 403);
                }
                if ($score >= 51) {
                    // Mark for monitoring; let the request through.
                    $threats->autoRespond($score, $ip);
                }
            } catch (\Throwable) {
                // Never let threat-intel failures take down traffic.
            }
        }

        // 2. Check if IP is banned (cached 60s to avoid per-request DB hit)
        try {
            $banned = \Illuminate\Support\Facades\Cache::remember(
                "fw:ban:{$ip}", 60,
                fn() => DB::table('security_ip_rules')
                    ->where('ip', $ip)
                    ->where('action', 'block')
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                              ->orWhere('expires_at', '>', now());
                    })
                    ->exists()
            );

            if ($banned) {
                return response('Forbidden', 403);
            }
        } catch (\Throwable $e) {
            // Table may not exist — continue
        }

        // 3. Rate limiting — use cache instead of DB for speed
        //    Skip rate limiting for admin routes & authenticated admin users
        //    Skip rate limiting for lightweight notification polling endpoints
        $isAdminRoute = str_starts_with(ltrim($request->getPathInfo(), '/'), 'admin');
        $isAdminUser  = $isAdminRoute && \Illuminate\Support\Facades\Auth::guard('admin')->check();
        $isNotificationPoll = str_starts_with(ltrim($request->getPathInfo(), '/'), 'api/notifications');

        if (!$isAdminUser && !$isNotificationPoll && AppSetting::get('security_rate_limit_enabled', '0') === '1') {
            try {
                $cacheKey = "fw:rate:{$ip}";
                $count = (int) \Illuminate\Support\Facades\Cache::get($cacheKey, 0);

                // Higher limit for authenticated users, strict for guests
                $limit = \Illuminate\Support\Facades\Auth::check() ? 300 : 120;

                if ($count > $limit) {
                    return response('Too Many Requests', 429);
                }

                \Illuminate\Support\Facades\Cache::put($cacheKey, $count + 1, 60);
            } catch (\Throwable $e) {
                // Continue if cache fails
            }
        }

        // 4. Detect malicious patterns (only on non-asset requests)
        $uri = $request->getPathInfo();
        $isAsset = preg_match('/\.(css|js|jpg|jpeg|png|gif|svg|ico|woff2?|ttf|eot|map)$/i', $uri);

        if (!$isAsset) {
            $malicious = $this->detectMaliciousPatterns($request);
            if ($malicious !== null) {
                try {
                    DB::table('security_logs')->insert([
                        'ip'         => $ip,
                        'type'       => 'malicious_request',
                        'detail'     => $malicious,
                        'uri'        => $request->getRequestUri(),
                        'ua'         => $request->userAgent(),
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $e) {}

                return response('Forbidden', 403);
            }
        }

        // 5. Pass to next middleware and attach security headers
        $response = $next($request);

        return $this->addSecurityHeaders($response);
    }

    /**
     * Add security headers to the outgoing response.
     */
    private function addSecurityHeaders(mixed $response): mixed
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );
        $response->headers->remove('X-Powered-By');

        return $response;
    }

    /**
     * Scan URL, query string, and body for known attack patterns.
     * Returns a description string on detection, null if clean.
     */
    private function detectMaliciousPatterns(Request $request): ?string
    {
        $targets = [
            'uri'   => $request->getRequestUri(),
            'query' => urldecode($request->getQueryString() ?? ''),
            'body'  => $this->flattenInput($request->all()),
        ];

        foreach ($targets as $source => $value) {
            foreach ($this->sqlPatterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    return "SQL injection detected in {$source}";
                }
            }
            foreach ($this->xssPatterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    return "XSS detected in {$source}";
                }
            }
            foreach ($this->traversalPatterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    return "Path traversal detected in {$source}";
                }
            }
        }

        return null;
    }

    /**
     * Flatten nested input array to a single string for pattern scanning.
     */
    private function flattenInput(array $input): string
    {
        $parts = [];
        array_walk_recursive($input, function ($value) use (&$parts) {
            if (is_string($value)) {
                $parts[] = $value;
            }
        });

        return implode(' ', $parts);
    }
}

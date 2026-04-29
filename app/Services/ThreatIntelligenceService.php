<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ThreatIntelligenceService
{
    // ---------------------------------------------------------------------------
    // Core analysis
    // ---------------------------------------------------------------------------

    /**
     * Analyze an incoming request and return a risk assessment.
     *
     * @return array{risk_score: int, action: string, factors: array}
     */
    public function analyzeRequest(Request $request): array
    {
        $ip          = $request->ip();
        $ua          = $request->userAgent();
        $path        = $request->path();
        $method      = $request->method();
        $fingerprint = $this->generateFingerprint($request);
        $factors     = [];
        $totalScore  = 0;

        // --- Signal 1: Request burst (weight 25%) ---
        try {
            $burstCount = DB::table('threat_patterns')
                ->where('ip', $ip)
                ->where('created_at', '>=', now()->subSeconds(60))
                ->count();

            $burstScore = 0;
            if ($burstCount > 60) {
                $burstScore = 25;
                $factors[]  = ['signal' => 'request_burst', 'value' => $burstCount, 'level' => 'critical', 'contribution' => 25];
            } elseif ($burstCount > 30) {
                $burstScore = 15;
                $factors[]  = ['signal' => 'request_burst', 'value' => $burstCount, 'level' => 'high', 'contribution' => 15];
            } else {
                $factors[] = ['signal' => 'request_burst', 'value' => $burstCount, 'level' => 'normal', 'contribution' => 0];
            }
            $totalScore += $burstScore;
        } catch (\Exception $e) {
            Log::channel('single')->error('[ThreatIntelligence] burst check failed: ' . $e->getMessage());
        }

        // --- Signal 2: Geographic anomaly (weight 20%) ---
        try {
            $geoScore   = 0;
            $geoRecord  = DB::table('geo_ip_cache')->where('ip', $ip)->first();
            $currentCountry = $geoRecord ? $geoRecord->country_code : null;

            if ($currentCountry) {
                // Find the most recent country seen for this IP in patterns meta
                $recentPattern = DB::table('threat_patterns')
                    ->where('ip', $ip)
                    ->whereNotNull('meta')
                    ->orderByDesc('created_at')
                    ->first();

                if ($recentPattern && $recentPattern->meta) {
                    $meta = json_decode($recentPattern->meta, true);
                    $prevCountry = $meta['country'] ?? null;
                    if ($prevCountry && $prevCountry !== $currentCountry) {
                        $geoScore  = 20;
                        $factors[] = ['signal' => 'geo_anomaly', 'value' => "{$prevCountry}->{$currentCountry}", 'level' => 'suspicious', 'contribution' => 20];
                    } else {
                        $factors[] = ['signal' => 'geo_anomaly', 'value' => $currentCountry, 'level' => 'normal', 'contribution' => 0];
                    }
                }
            }
            $totalScore += $geoScore;
        } catch (\Exception $e) {
            Log::channel('single')->error('[ThreatIntelligence] geo check failed: ' . $e->getMessage());
        }

        // --- Signal 3: Time anomaly (weight 10%) ---
        try {
            $hour       = (int) now()->format('G'); // 0-23
            $timeScore  = 0;
            if ($hour >= 2 && $hour < 5) {
                // Check frequency during unusual hours
                $lateCount = DB::table('threat_patterns')
                    ->where('ip', $ip)
                    ->where('created_at', '>=', now()->subMinutes(10))
                    ->count();

                if ($lateCount > 20) {
                    $timeScore = 10;
                    $factors[] = ['signal' => 'time_anomaly', 'value' => "hour={$hour},count={$lateCount}", 'level' => 'suspicious', 'contribution' => 10];
                } else {
                    $factors[] = ['signal' => 'time_anomaly', 'value' => "hour={$hour}", 'level' => 'low', 'contribution' => 0];
                }
            } else {
                $factors[] = ['signal' => 'time_anomaly', 'value' => "hour={$hour}", 'level' => 'normal', 'contribution' => 0];
            }
            $totalScore += $timeScore;
        } catch (\Exception $e) {
            Log::channel('single')->error('[ThreatIntelligence] time check failed: ' . $e->getMessage());
        }

        // --- Signal 4: User-Agent consistency (weight 20%) ---
        try {
            $uaScore      = 0;
            $recentUas    = DB::table('threat_patterns')
                ->where('ip', $ip)
                ->whereNotNull('user_agent')
                ->orderByDesc('created_at')
                ->limit(10)
                ->pluck('user_agent')
                ->unique()
                ->values()
                ->toArray();

            if (!empty($recentUas) && $ua && !in_array($ua, $recentUas)) {
                $uaScore   = 20;
                $factors[] = ['signal' => 'ua_inconsistency', 'value' => substr($ua, 0, 100), 'level' => 'suspicious', 'contribution' => 20];
            } else {
                $factors[] = ['signal' => 'ua_inconsistency', 'value' => 'consistent', 'level' => 'normal', 'contribution' => 0];
            }
            $totalScore += $uaScore;
        } catch (\Exception $e) {
            Log::channel('single')->error('[ThreatIntelligence] UA check failed: ' . $e->getMessage());
        }

        // --- Signal 5: Path scanning (weight 15%) ---
        try {
            $pathScore    = 0;
            $distinctPaths = DB::table('threat_patterns')
                ->where('ip', $ip)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->distinct()
                ->count('path');

            if ($distinctPaths > 20) {
                $pathScore = 15;
                $factors[] = ['signal' => 'path_scanning', 'value' => $distinctPaths, 'level' => 'suspicious', 'contribution' => 15];
            } else {
                $factors[] = ['signal' => 'path_scanning', 'value' => $distinctPaths, 'level' => 'normal', 'contribution' => 0];
            }
            $totalScore += $pathScore;
        } catch (\Exception $e) {
            Log::channel('single')->error('[ThreatIntelligence] path scan check failed: ' . $e->getMessage());
        }

        // --- Signal 6: Known bad fingerprint (weight 10%) ---
        try {
            $fpScore = 0;
            if ($this->isFingerPrintBlocked($fingerprint)) {
                $fpScore   = 10;
                $factors[] = ['signal' => 'blocked_fingerprint', 'value' => substr($fingerprint, 0, 16) . '...', 'level' => 'blocked', 'contribution' => 10];
            } else {
                $factors[] = ['signal' => 'blocked_fingerprint', 'value' => 'clear', 'level' => 'normal', 'contribution' => 0];
            }
            $totalScore += $fpScore;
        } catch (\Exception $e) {
            Log::channel('single')->error('[ThreatIntelligence] fingerprint check failed: ' . $e->getMessage());
        }

        $riskScore = min(100, $totalScore);
        $action    = $this->scoreToAction($riskScore);

        // Record this request pattern
        $this->recordPattern($ip, $action, $path, $method, $ua, [
            'fingerprint' => $fingerprint,
            'country'     => optional(DB::table('geo_ip_cache')->where('ip', $ip)->first())->country_code,
            'risk_score'  => $riskScore,
        ]);

        // Update threat score in DB
        if ($riskScore > 0) {
            $this->updateThreatScore($ip, (int) round($riskScore / 10), 'request_analysis');
        }

        return [
            'risk_score'  => $riskScore,
            'action'      => $action,
            'factors'     => $factors,
            'fingerprint' => $fingerprint,
        ];
    }

    // ---------------------------------------------------------------------------
    // IP threat scoring
    // ---------------------------------------------------------------------------

    /**
     * Get composite threat score (0-100) for an IP.
     */
    public function getIpThreatScore(string $ip): int
    {
        try {
            $record = DB::table('threat_scores')->where('ip', $ip)->first();
            return $record ? min(100, max(0, (int) $record->score)) : 0;
        } catch (\Exception $e) {
            Log::channel('single')->error('[ThreatIntelligence] getIpThreatScore failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Update (or create) a threat score entry for an IP.
     */
    public function updateThreatScore(string $ip, int $delta, string $reason): void
    {
        try {
            $existing = DB::table('threat_scores')->where('ip', $ip)->first();

            if ($existing) {
                $newScore = min(100, max(0, (int) $existing->score + $delta));
                $factors  = json_decode($existing->factors ?? '[]', true);
                $factors[] = ['delta' => $delta, 'reason' => $reason, 'at' => now()->toDateTimeString()];
                // Keep only the last 50 factor entries
                if (count($factors) > 50) {
                    $factors = array_slice($factors, -50);
                }

                DB::table('threat_scores')->where('ip', $ip)->update([
                    'score'      => $newScore,
                    'factors'    => json_encode($factors),
                    'last_seen'  => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $score   = min(100, max(0, $delta));
                $factors = [['delta' => $delta, 'reason' => $reason, 'at' => now()->toDateTimeString()]];

                DB::table('threat_scores')->insert([
                    'ip'         => $ip,
                    'score'      => $score,
                    'factors'    => json_encode($factors),
                    'fingerprint'=> '',
                    'first_seen' => now(),
                    'last_seen'  => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('single')->error('[ThreatIntelligence] updateThreatScore failed: ' . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------------------
    // Fingerprinting
    // ---------------------------------------------------------------------------

    /**
     * Generate a SHA-256 browser/request fingerprint.
     */
    public function generateFingerprint(Request $request): string
    {
        $components = [
            $request->userAgent() ?? '',
            $request->header('Accept') ?? '',
            $request->header('Accept-Language') ?? '',
            $request->header('Accept-Encoding') ?? '',
            $request->header('Connection') ?? '',
            $request->header('DNT') ?? '',
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Block a fingerprint for a given number of hours.
     */
    public function blockFingerprint(string $fp, string $reason, int $hours = 24): void
    {
        try {
            $expiresAt = now()->addHours($hours);

            $existing = DB::table('threat_blocked_fingerprints')->where('fingerprint', $fp)->first();
            if ($existing) {
                DB::table('threat_blocked_fingerprints')->where('fingerprint', $fp)->update([
                    'reason'     => $reason,
                    'blocked_at' => now(),
                    'expires_at' => $expiresAt,
                ]);
            } else {
                DB::table('threat_blocked_fingerprints')->insert([
                    'fingerprint' => $fp,
                    'reason'      => $reason,
                    'blocked_at'  => now(),
                    'expires_at'  => $expiresAt,
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('single')->error('[ThreatIntelligence] blockFingerprint failed: ' . $e->getMessage());
        }
    }

    /**
     * Check whether a fingerprint is currently blocked.
     */
    public function isFingerPrintBlocked(string $fp): bool
    {
        try {
            return DB::table('threat_blocked_fingerprints')
                ->where('fingerprint', $fp)
                ->where('expires_at', '>', now())
                ->exists();
        } catch (\Exception $e) {
            Log::channel('single')->error('[ThreatIntelligence] isFingerPrintBlocked failed: ' . $e->getMessage());
            return false;
        }
    }

    // ---------------------------------------------------------------------------
    // Pattern recording
    // ---------------------------------------------------------------------------

    /**
     * Record a request pattern for future analysis.
     */
    public function recordPattern(
        string  $ip,
        string  $action,
        string  $path,
        string  $method,
        ?string $ua,
        ?array  $meta = null
    ): void {
        try {
            DB::table('threat_patterns')->insert([
                'ip'         => $ip,
                'fingerprint'=> '',
                'action'     => $action,
                'path'       => substr($path, 0, 500),
                'method'     => strtoupper($method),
                'user_agent' => $ua ? substr($ua, 0, 500) : null,
                'meta'       => $meta ? json_encode($meta) : null,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::channel('single')->error('[ThreatIntelligence] recordPattern failed: ' . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------------------
    // Incidents
    // ---------------------------------------------------------------------------

    /**
     * Create a new threat incident record.
     */
    public function createIncident(
        string  $type,
        string  $severity,
        string  $description,
        ?string $ip   = null,
        ?array  $data = null
    ): void {
        try {
            $allowedSeverities = ['low', 'medium', 'high', 'critical'];
            $severity          = in_array($severity, $allowedSeverities) ? $severity : 'medium';

            DB::table('threat_incidents')->insert([
                'type'        => $type,
                'severity'    => $severity,
                'status'      => 'open',
                'description' => $description,
                'ip'          => $ip,
                'data'        => $data ? json_encode($data) : null,
                'created_at'  => now(),
                'resolved_at' => null,
                'resolution'  => null,
            ]);
        } catch (\Exception $e) {
            Log::channel('single')->error('[ThreatIntelligence] createIncident failed: ' . $e->getMessage());
        }
    }

    /**
     * Mark an incident as resolved.
     */
    public function resolveIncident(int $id, string $resolution): void
    {
        try {
            DB::table('threat_incidents')->where('id', $id)->update([
                'status'      => 'resolved',
                'resolved_at' => now(),
                'resolution'  => $resolution,
            ]);
        } catch (\Exception $e) {
            Log::channel('single')->error('[ThreatIntelligence] resolveIncident failed: ' . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------------------

    /**
     * Return aggregated stats for the admin security dashboard.
     */
    public function getDashboardStats(): array
    {
        $stats = [];

        try {
            $stats['total_threats']         = DB::table('threat_scores')->where('score', '>', 50)->count();
            $stats['high_risk_ips']         = DB::table('threat_scores')->where('score', '>=', 71)->count();
            $stats['blocked_fingerprints']  = DB::table('threat_blocked_fingerprints')->where('expires_at', '>', now())->count();
            $stats['open_incidents']        = DB::table('threat_incidents')->where('status', 'open')->count();
            $stats['critical_incidents']    = DB::table('threat_incidents')->where('severity', 'critical')->where('status', 'open')->count();
            $stats['patterns_last_hour']    = DB::table('threat_patterns')->where('created_at', '>=', now()->subHour())->count();
            $stats['patterns_last_24h']     = DB::table('threat_patterns')->where('created_at', '>=', now()->subDay())->count();
            $stats['top_threat_ips']        = DB::table('threat_scores')
                ->orderByDesc('score')
                ->limit(10)
                ->get(['ip', 'score', 'last_seen'])
                ->toArray();
            $stats['recent_incidents']      = DB::table('threat_incidents')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'type', 'severity', 'status', 'ip', 'created_at'])
                ->toArray();
            $stats['action_breakdown']      = DB::table('threat_patterns')
                ->where('created_at', '>=', now()->subDay())
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->pluck('count', 'action')
                ->toArray();
        } catch (\Exception $e) {
            Log::channel('single')->error('[ThreatIntelligence] getDashboardStats failed: ' . $e->getMessage());
        }

        return $stats;
    }

    // ---------------------------------------------------------------------------
    // Cleanup
    // ---------------------------------------------------------------------------

    /**
     * Delete old records and return the total count removed.
     */
    public function cleanup(int $daysToKeep = 30): int
    {
        $deleted   = 0;
        $cutoff    = now()->subDays($daysToKeep);

        $tableCutoffs = [
            'threat_patterns'             => ['created_at', $cutoff],
            'threat_blocked_fingerprints' => ['expires_at', now()],   // expired blocks
        ];

        foreach ($tableCutoffs as $table => [$col, $date]) {
            try {
                $deleted += DB::table($table)->where($col, '<', $date)->delete();
            } catch (\Exception $e) {
                Log::channel('single')->error("[ThreatIntelligence] cleanup {$table} failed: " . $e->getMessage());
            }
        }

        // Resolved incidents older than daysToKeep
        try {
            $deleted += DB::table('threat_incidents')
                ->where('status', 'resolved')
                ->where('created_at', '<', $cutoff)
                ->delete();
        } catch (\Exception $e) {
            Log::channel('single')->error('[ThreatIntelligence] cleanup threat_incidents failed: ' . $e->getMessage());
        }

        return $deleted;
    }

    // ---------------------------------------------------------------------------
    // Auto-response
    // ---------------------------------------------------------------------------

    /**
     * Automatically respond to a risk score — returns the action taken.
     */
    public function autoRespond(int $riskScore, string $ip): string
    {
        if ($riskScore <= 30) {
            return 'allow';
        }

        if ($riskScore <= 50) {
            // Monitor only — pattern already recorded in analyzeRequest
            return 'monitor';
        }

        if ($riskScore <= 70) {
            // Challenge — log as warning
            try {
                Log::channel('single')->warning("[ThreatIntelligence] Challenge issued for IP {$ip}, score={$riskScore}");
                $this->createIncident(
                    'challenge_issued',
                    'medium',
                    "Challenge triggered for IP {$ip} with risk score {$riskScore}",
                    $ip,
                    ['risk_score' => $riskScore]
                );
            } catch (\Exception $e) {
                Log::channel('single')->error('[ThreatIntelligence] autoRespond challenge log failed: ' . $e->getMessage());
            }
            return 'challenge';
        }

        // 71+: block
        $banHours = 1;
        $severity = 'medium';
        if ($riskScore >= 90) {
            $banHours = 24;
            $severity = 'critical';
        } elseif ($riskScore >= 80) {
            $banHours = 6;
            $severity = 'high';
        }

        try {
            $expiresAt = now()->addHours($banHours);

            $existing = DB::table('security_ip_rules')
                ->where('ip', $ip)
                ->where('rule_type', 'blacklist')
                ->where('expires_at', '>', now())
                ->first();

            if (!$existing) {
                DB::table('security_ip_rules')->insert([
                    'ip'         => $ip,
                    'rule_type'  => 'blacklist',
                    'reason'     => "Auto-blocked by ThreatIntelligence (score={$riskScore})",
                    'expires_at' => $expiresAt,
                    'created_at' => now(),
                ]);
            }

            $this->createIncident(
                'auto_block',
                $severity,
                "IP {$ip} auto-blocked for {$banHours}h (score={$riskScore})",
                $ip,
                ['risk_score' => $riskScore, 'ban_hours' => $banHours]
            );

            Log::channel('single')->warning("[ThreatIntelligence] Auto-blocked IP {$ip} for {$banHours}h (score={$riskScore})");
        } catch (\Exception $e) {
            Log::channel('single')->error('[ThreatIntelligence] autoRespond block failed: ' . $e->getMessage());
        }

        return 'block';
    }

    // ---------------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------------

    private function scoreToAction(int $score): string
    {
        if ($score >= 71) return 'block';
        if ($score >= 51) return 'challenge';
        if ($score >= 31) return 'monitor';
        return 'allow';
    }
}

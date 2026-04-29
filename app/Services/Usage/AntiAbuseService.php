<?php

namespace App\Services\Usage;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AntiAbuseService — stops Free-tier multi-account abuse.
 *
 * Threat model
 * ------------
 * A Free tier costs the platform ~$0.06/user/month at average usage.
 * One bad actor opening 100 accounts via Gmail "+aliases" or burner
 * IPs costs $6/month and takes 100 quota slots from honest users.
 * Multi-account abuse is the cheapest way to break a freemium SaaS.
 *
 * What this service does
 * ----------------------
 *   1. On signup, computes a risk score 0-100 from:
 *        • Same hashed IP in last 24h (each previous +20)
 *        • Same email "stem" (foo+1@x = foo@x) in last 24h (+30)
 *        • Same device fingerprint in last 24h (+25)
 *        • Email domain blacklist (mailinator, guerrillamail, …) (+50)
 *   2. Records the signal in signup_signals (90-day retention).
 *   3. Returns a decision:
 *        • OK              — proceed
 *        • REQUIRE_VERIFY  — force email verification + Turnstile
 *        • BLOCK           — refuse signup
 *
 * What this service does NOT do
 * -----------------------------
 *   • Detect VPN/Tor — that's an IPQualityScore / MaxMind job. We hash
 *     the IP, not query a reputation service, so we miss sophisticated
 *     proxies. Document this and budget accordingly.
 *   • Catch device-farm abuse with rotating fingerprints. A determined
 *     attacker WILL bypass this. The goal is making it expensive
 *     enough that it's not worth their time.
 */
class AntiAbuseService
{
    public const DECISION_OK             = 'ok';
    public const DECISION_REQUIRE_VERIFY = 'require_verify';
    public const DECISION_BLOCK          = 'block';

    /** Disposable email domains we refuse outright. */
    private const BLACKLISTED_DOMAINS = [
        'mailinator.com', 'guerrillamail.com', '10minutemail.com',
        'temp-mail.org', 'throwaway.email', 'tempmail.dev',
        'yopmail.com', 'getairmail.com', 'sharklasers.com',
    ];

    /**
     * Evaluate a signup attempt. Returns the decision and the score so
     * the caller can log/audit.
     *
     * @return array{decision:string, score:int, reasons:array<int,string>}
     */
    public function evaluateSignup(?string $email, ?string $ip, ?string $deviceFingerprint = null): array
    {
        if (!config('usage.anti_abuse.enabled', true)) {
            return ['decision' => self::DECISION_OK, 'score' => 0, 'reasons' => []];
        }

        $score   = 0;
        $reasons = [];
        $now     = now();

        // ── Hard rules first (skip the score math when an outright block fires)
        if ($email) {
            $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
            if ($domain && in_array($domain, self::BLACKLISTED_DOMAINS, true)) {
                return [
                    'decision' => self::DECISION_BLOCK,
                    'score'    => 100,
                    'reasons'  => ["Disposable email provider ({$domain})"],
                ];
            }
        }

        // ── IP velocity
        $ipHash = $this->hash($ip);
        if ($ipHash) {
            $hitsByIp = (int) DB::table('signup_signals')
                ->where('ip_hash', $ipHash)
                ->where('created_at', '>=', $now->copy()->subDay())
                ->count();
            if ($hitsByIp >= (int) config('usage.anti_abuse.max_per_ip_per_day', 3)) {
                $score    += 30 + min(40, $hitsByIp * 5);
                $reasons[] = "{$hitsByIp} prior signups from this IP in the last 24h";
            } elseif ($hitsByIp > 0) {
                $score    += $hitsByIp * 10;
                $reasons[] = "{$hitsByIp} prior signups from this IP";
            }
        }

        // ── Email stem reuse (Gmail/Outlook strip "+xxx" — we collapse it)
        if ($email) {
            $stem     = $this->emailStem($email);
            $stemHash = $this->hash($stem);
            $hitsByStem = (int) DB::table('signup_signals')
                ->where('email_hash', $stemHash)
                ->where('created_at', '>=', $now->copy()->subDay())
                ->count();
            if ($hitsByStem > 0) {
                $score    += 30 + min(40, $hitsByStem * 10);
                $reasons[] = "{$hitsByStem} prior signups with the same email stem";
            }
        }

        // ── Device fingerprint reuse
        if ($deviceFingerprint) {
            $hitsByFp = (int) DB::table('signup_signals')
                ->where('device_fingerprint', $deviceFingerprint)
                ->where('created_at', '>=', $now->copy()->subDay())
                ->count();
            $maxFp = (int) config('usage.anti_abuse.max_per_fingerprint_day', 2);
            if ($hitsByFp >= $maxFp) {
                $score    += 25 + min(35, $hitsByFp * 5);
                $reasons[] = "{$hitsByFp} prior signups from the same device fingerprint";
            }
        }

        $blockAt = (int) config('usage.anti_abuse.block_at_risk_score', 80);
        $flagAt  = (int) config('usage.anti_abuse.flag_at_risk_score', 50);

        $decision = match (true) {
            $score >= $blockAt => self::DECISION_BLOCK,
            $score >= $flagAt  => self::DECISION_REQUIRE_VERIFY,
            default            => self::DECISION_OK,
        };

        // Best-effort log of the signal — failure here MUST NOT block signup.
        try {
            DB::table('signup_signals')->insert([
                'email_hash'         => $this->hash($email ? $this->emailStem($email) : null),
                'ip_hash'            => $ipHash,
                'device_fingerprint' => $deviceFingerprint,
                'risk_score'         => min(100, $score),
                'flagged'            => $decision !== self::DECISION_OK,
                'metadata'           => json_encode(['reasons' => $reasons]),
                'created_at'         => $now,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AntiAbuseService: failed to record signup signal', [
                'error' => $e->getMessage(),
            ]);
        }

        return ['decision' => $decision, 'score' => min(100, $score), 'reasons' => $reasons];
    }

    /**
     * Link a previously-recorded signal row to the user_id it became.
     * Called from RegisterController after the user account is committed
     * so subsequent abuse can correlate the early signal back to the
     * resulting account.
     */
    public function linkSignalToUser(?string $email, ?string $ip, int $userId): void
    {
        $emailHash = $email ? $this->hash($this->emailStem($email)) : null;
        $ipHash    = $this->hash($ip);
        if (!$emailHash && !$ipHash) return;

        try {
            DB::table('signup_signals')
                ->where(function ($q) use ($emailHash, $ipHash) {
                    if ($emailHash) $q->orWhere('email_hash', $emailHash);
                    if ($ipHash)    $q->orWhere('ip_hash',    $ipHash);
                })
                ->whereNull('user_id')
                ->where('created_at', '>=', now()->subMinutes(10))
                ->update(['user_id' => $userId]);
        } catch (\Throwable $e) {
            Log::warning('AntiAbuseService: linkSignalToUser failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Collapse a Gmail-style email to its canonical stem.
     *
     *   foo+abc@gmail.com → foogmail.com
     *   FOO@gmail.com     → foogmail.com   (case-insensitive)
     *   foo.bar@gmail.com → foo.bargmail.com   (we DON'T strip dots —
     *     too aggressive; Gmail-only and would false-positive elsewhere)
     */
    private function emailStem(string $email): string
    {
        $email = strtolower(trim($email));
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) return $email;
        [$local, $domain] = $parts;
        // Strip "+suffix" Gmail-style aliases — works on every major provider
        $local = explode('+', $local)[0];
        return $local . '@' . $domain;
    }

    private function hash(?string $value): ?string
    {
        if ($value === null || $value === '') return null;
        $salt = (string) config('usage.anti_abuse.hash_salt', '');
        return hash('sha256', $salt . $value);
    }
}

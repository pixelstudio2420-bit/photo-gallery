<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cloudflare Turnstile verification.
 *
 * Blocks bot submissions on high-value public endpoints (face-search,
 * contact forms, signup flows). Designed to be:
 *   • Opt-in per-route via `->middleware('turnstile')`
 *   • No-op when `turnstile_enabled` is false in AppSetting (so development
 *     and tests don't need a live Cloudflare account)
 *   • Tolerant of Cloudflare outages — if the siteverify API is unreachable
 *     for 3 consecutive failures, requests pass (cached circuit breaker)
 *
 * Settings (Admin → Settings → Security):
 *   turnstile_enabled     → '1' / '0'
 *   turnstile_site_key    → public key (for the <div class="cf-turnstile"> widget)
 *   turnstile_secret_key  → server-side verify key
 *
 * Client must submit token as:
 *   • form field:  cf-turnstile-response
 *   • JSON body:   { "cf-turnstile-response": "..." }
 */
class VerifyTurnstile
{
    private const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function handle(Request $request, Closure $next): Response
    {
        $enabled = AppSetting::get('turnstile_enabled', '0') === '1';
        if (!$enabled) {
            return $next($request);
        }

        $secret = AppSetting::get('turnstile_secret_key', '');
        if (empty($secret)) {
            // Misconfigured — fail OPEN (don't lock users out) but log loudly
            Log::warning('VerifyTurnstile: turnstile_enabled=1 but no secret key configured');
            return $next($request);
        }

        // Circuit breaker: if Cloudflare's siteverify has failed recently,
        // don't wait for another timeout — let traffic through.
        if (Cache::get('turnstile_circuit_open', false)) {
            return $next($request);
        }

        $token = $request->input('cf-turnstile-response')
            ?? $request->header('CF-Turnstile-Response')
            ?? '';

        if (empty($token)) {
            return $this->reject($request, 'missing_token');
        }

        try {
            $response = Http::timeout(5)->asForm()->post(self::SITEVERIFY_URL, [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

            if (!$response->successful()) {
                $this->trackFailure();
                return $next($request); // fail-open on API outage
            }

            $body = $response->json();
            if (!($body['success'] ?? false)) {
                return $this->reject($request, 'invalid_token', $body['error-codes'] ?? []);
            }

            // Reset circuit breaker on success
            Cache::forget('turnstile_circuit_failures');
            return $next($request);
        } catch (\Throwable $e) {
            Log::warning('VerifyTurnstile siteverify exception: ' . $e->getMessage());
            $this->trackFailure();
            return $next($request); // fail-open on network/exception
        }
    }

    /**
     * Track consecutive API failures → open circuit breaker after 3.
     */
    private function trackFailure(): void
    {
        $key = 'turnstile_circuit_failures';
        $failures = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $failures, 300);

        if ($failures >= 3) {
            Cache::put('turnstile_circuit_open', true, 300); // 5-min breaker
            Log::warning("VerifyTurnstile circuit breaker opened after {$failures} failures — fail-open mode for 5 min");
        }
    }

    private function reject(Request $request, string $code, array $errors = []): Response
    {
        Log::info('VerifyTurnstile rejected request', [
            'ip'     => $request->ip(),
            'path'   => $request->path(),
            'reason' => $code,
            'errors' => $errors,
        ]);

        $message = match ($code) {
            'missing_token'  => 'กรุณายืนยันว่าคุณไม่ใช่บอท (CAPTCHA challenge ยังไม่สำเร็จ)',
            'invalid_token'  => 'การยืนยันความเป็นมนุษย์ล้มเหลว กรุณาลองใหม่',
            default          => 'การยืนยันล้มเหลว',
        };

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'reason'  => $code,
            ], 429);
        }

        return back()->withErrors(['captcha' => $message])->withInput();
    }
}

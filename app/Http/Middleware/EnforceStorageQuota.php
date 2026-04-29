<?php

namespace App\Http\Middleware;

use App\Models\PhotographerProfile;
use App\Services\CreditService;
use App\Services\StorageQuotaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block photo uploads that would push the photographer past their quota.
 *
 * Runs BEFORE the controller's validate() so the 413 is raised before we
 * copy the temp file anywhere. Intentionally lenient:
 *   • If the request has no 'photo' field, pass through (controller will
 *     handle the validation error).
 *   • If the user has no PhotographerProfile, pass through (middleware
 *     elsewhere handles that authorization).
 *   • If enforcement is globally disabled, short-circuit on
 *     StorageQuotaService::canUpload().
 *
 * Response shape is identical for both JSON and HTML callers — the existing
 * photographer UI uses fetch() + JSON, so a 413 with a `message` key is the
 * right primitive. 413 Payload Too Large is the HTTP-spec-compliant code
 * for "request is larger than quota" (see RFC 9110 §15.5.14).
 */
class EnforceStorageQuota
{
    public function __construct(
        private StorageQuotaService $quota,
        private CreditService $credits,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = Auth::id();
        if (!$userId) {
            // No auth = let downstream auth middleware deal with it.
            return $next($request);
        }

        // We need the PhotographerProfile (for the counter + tier lookup).
        // Cached on the request so repeated middlewares don't re-query.
        $profile = $request->attributes->get('photographer_profile')
            ?? PhotographerProfile::where('user_id', $userId)->first();

        if (!$profile) {
            // No profile means they can't upload anyway — don't short-circuit,
            // let the photographer-auth middleware render its proper error.
            return $next($request);
        }

        // Pin on the request so controllers + observers can reuse.
        $request->attributes->set('photographer_profile', $profile);

        $file = $request->file('photo');
        if (!$file || !$file->isValid()) {
            return $next($request);
        }

        $bytes = (int) $file->getSize();

        // ────────────────────────────────────────────────────────────────
        // Credits mode — one credit = one upload, regardless of size.
        // Storage quota is deprioritized but still enforced as a ceiling.
        // ────────────────────────────────────────────────────────────────
        if ($profile->isCreditsMode() && $this->credits->systemEnabled()) {
            if (!$this->credits->canUpload($profile, 1)) {
                return $this->refuse($request, $this->credits->refusalMessage($profile), [
                    'mode'           => 'credits',
                    'balance'        => $this->credits->balance((int) $profile->user_id),
                    'credits_needed' => 1,
                ]);
            }
            // Fall through to storage check — still protects against a single
            // gigantic file from blowing up R2 even if the credit system allows it.
        }

        // ────────────────────────────────────────────────────────────────
        // Storage quota — always enforced when globally enabled.
        // (Legacy commission-mode photographers rely on this exclusively.)
        // ────────────────────────────────────────────────────────────────
        if ($this->quota->enforcementEnabled() && $bytes > 0) {
            if (!$this->quota->canUpload($profile, $bytes)) {
                return $this->refuse($request, $this->quota->refusalMessage($profile, $bytes), [
                    'mode'        => 'storage',
                    'used_bytes'  => (int) $profile->storage_used_bytes,
                    'quota_bytes' => $this->quota->quotaFor($profile),
                    'tier'        => $profile->tier,
                ]);
            }
        }

        return $next($request);
    }

    /** Build a 413 JSON / back() response with a consistent payload. */
    private function refuse(Request $request, string $message, array $details): Response
    {
        if ($request->expectsJson() || $request->is('api/*') || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'details' => $details,
            ], 413);
        }

        return back()->withErrors(['photo' => $message])->withInput();
    }
}

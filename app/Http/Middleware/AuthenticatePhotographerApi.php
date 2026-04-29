<?php

namespace App\Http\Middleware;

use App\Models\PhotographerApiKey;
use App\Models\PhotographerProfile;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token auth for the public photographer API (Studio plan).
 *
 * Header format:
 *   Authorization: Bearer pgk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
 *
 * Lookup is by token_prefix (indexed) → bcrypt verify → key model loaded
 * onto the request as `request->attributes['api_key']`. The owning
 * photographer's profile is also exposed as `request->attributes['photographer_profile']`
 * so downstream controllers can read scopes / enforce ownership without
 * re-querying.
 *
 * If the photographer's plan no longer includes `api_access` (e.g. they
 * downgraded from Studio), the key is rejected even when valid — same
 * behaviour as RequireSubscriptionFeature for web routes.
 */
class AuthenticatePhotographerApi
{
    public function __construct(private SubscriptionService $subs) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractBearer($request);
        if (!$token) {
            return $this->fail('Missing Bearer token', 401);
        }

        $prefix = substr($token, 0, 8);
        $candidates = PhotographerApiKey::where('token_prefix', $prefix)
            ->whereNull('revoked_at')
            ->get();

        $key = $candidates->first(fn (PhotographerApiKey $k) => Hash::check($token, $k->token_hash));
        if (!$key) {
            return $this->fail('Invalid token', 401);
        }
        if ($key->isExpired()) {
            return $this->fail('Token expired', 401);
        }

        $profile = PhotographerProfile::where('user_id', $key->photographer_id)->first();
        if (!$profile) {
            return $this->fail('Profile not found', 404);
        }

        // Plan gate — Studio only.
        if (!$this->subs->canAccessFeature($profile, 'api_access')) {
            return $this->fail('API access requires Studio plan', 403);
        }

        // Touch usage stamps (cheap update, no model events).
        $key->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ])->save();

        $request->attributes->set('api_key', $key);
        $request->attributes->set('photographer_profile', $profile);

        return $next($request);
    }

    private function extractBearer(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return $m[1];
        }
        return null;
    }

    private function fail(string $message, int $status): Response
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}

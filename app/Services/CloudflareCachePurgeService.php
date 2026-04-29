<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around Cloudflare's "Purge Cache" HTTP API.
 *
 * Config is stored in AppSetting (not .env) so ops can rotate the token from
 * the admin UI without redeploying. Required keys:
 *   - cloudflare_enabled    ('1' | '0')
 *   - cloudflare_zone_id
 *   - cloudflare_api_token  (scoped token: "Zone: Cache Purge")
 *   - cloudflare_base_url   (optional; defaults to api.cloudflare.com/client/v4)
 *
 * API reference: POST /zones/{zone_id}/purge_cache
 *   - {"purge_everything": true}
 *   - {"files": ["https://x.com/y"]}
 *   - {"tags": ["news"]}
 *   - {"prefixes": ["example.com/img"]}
 *   - {"hosts": ["www.example.com"]}
 */
class CloudflareCachePurgeService
{
    private const DEFAULT_BASE = 'https://api.cloudflare.com/client/v4';

    public function isEnabled(): bool
    {
        return (string) AppSetting::get('cloudflare_enabled', '0') === '1'
            && AppSetting::get('cloudflare_zone_id') !== null
            && AppSetting::get('cloudflare_api_token') !== null;
    }

    public function config(): array
    {
        return [
            'enabled'   => $this->isEnabled(),
            'zone_id'   => (string) AppSetting::get('cloudflare_zone_id', ''),
            'api_token' => (string) AppSetting::get('cloudflare_api_token', ''),
            'base_url'  => rtrim((string) AppSetting::get('cloudflare_base_url', self::DEFAULT_BASE), '/'),
        ];
    }

    /** Convenience: purge everything in the zone. DESTRUCTIVE. */
    public function purgeEverything(): array
    {
        return $this->call(['purge_everything' => true]);
    }

    /** Purge a list of absolute URLs. Max 30 per request per Cloudflare docs. */
    public function purgeFiles(array $urls): array
    {
        $urls = array_values(array_filter(array_map('trim', $urls)));
        if (empty($urls)) {
            return ['ok' => false, 'error' => 'No URLs provided'];
        }
        // Cloudflare caps at 30 files/call; chunk & aggregate
        $results = [];
        foreach (array_chunk($urls, 30) as $batch) {
            $results[] = $this->call(['files' => $batch]);
        }
        return $this->aggregate($results, ['files_purged' => count($urls)]);
    }

    /** Purge by cache-tag. Enterprise-only but we expose it anyway. */
    public function purgeTags(array $tags): array
    {
        $tags = array_values(array_filter(array_map('trim', $tags)));
        if (empty($tags)) return ['ok' => false, 'error' => 'No tags provided'];
        return $this->call(['tags' => $tags]);
    }

    /** Purge by URL prefix (Enterprise). */
    public function purgePrefixes(array $prefixes): array
    {
        $prefixes = array_values(array_filter(array_map('trim', $prefixes)));
        if (empty($prefixes)) return ['ok' => false, 'error' => 'No prefixes provided'];
        return $this->call(['prefixes' => $prefixes]);
    }

    /** Purge by host. */
    public function purgeHosts(array $hosts): array
    {
        $hosts = array_values(array_filter(array_map('trim', $hosts)));
        if (empty($hosts)) return ['ok' => false, 'error' => 'No hosts provided'];
        return $this->call(['hosts' => $hosts]);
    }

    /**
     * Issue the HTTP call. Returns ['ok', 'status', 'body', 'error' (opt)].
     */
    protected function call(array $payload): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'error' => 'Cloudflare integration is not enabled', 'status' => 0];
        }
        $cfg = $this->config();
        $url = $cfg['base_url'] . '/zones/' . $cfg['zone_id'] . '/purge_cache';

        try {
            /** @var Response $res */
            $res = Http::withToken($cfg['api_token'])
                ->timeout(15)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

            $body = $res->json() ?? [];
            $ok   = $res->successful() && ($body['success'] ?? false);

            if (!$ok) {
                Log::warning('Cloudflare purge failed', [
                    'status' => $res->status(),
                    'body'   => $body,
                    'payload'=> $payload,
                ]);
            }

            return [
                'ok'     => $ok,
                'status' => $res->status(),
                'body'   => $body,
                'error'  => $ok ? null : ($body['errors'][0]['message'] ?? "HTTP {$res->status()}"),
            ];
        } catch (\Throwable $e) {
            Log::error('Cloudflare purge exception', ['err' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage(), 'status' => 0];
        }
    }

    /**
     * Verify that the current token + zone can access the zone.
     * Calls GET /zones/{id} which requires only Zone.Read.
     */
    public function verify(): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'error' => 'Not configured'];
        }
        $cfg = $this->config();
        try {
            $res = Http::withToken($cfg['api_token'])
                ->timeout(10)
                ->acceptJson()
                ->get($cfg['base_url'] . '/zones/' . $cfg['zone_id']);

            $body = $res->json() ?? [];
            $ok   = $res->successful() && ($body['success'] ?? false);

            return [
                'ok'         => $ok,
                'zone_name'  => $body['result']['name']   ?? null,
                'zone_status'=> $body['result']['status'] ?? null,
                'plan'       => $body['result']['plan']['name'] ?? null,
                'error'      => $ok ? null : ($body['errors'][0]['message'] ?? "HTTP {$res->status()}"),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    protected function aggregate(array $results, array $extra = []): array
    {
        $ok      = collect($results)->every(fn ($r) => $r['ok'] ?? false);
        $errors  = collect($results)->pluck('error')->filter()->values()->all();
        return array_merge([
            'ok'       => $ok,
            'batches'  => count($results),
            'errors'   => $errors,
        ], $extra);
    }
}

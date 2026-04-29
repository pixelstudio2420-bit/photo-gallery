<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Services\CloudflareCachePurgeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Asynchronously purges Cloudflare CDN cache for a list of R2 object keys.
 *
 * Why a queued job rather than an inline HTTP call?
 *   - The Cloudflare purge API takes 200-2000ms. Doing it inline on a
 *     delete-photo request blocks the user's PHP-FPM worker for that
 *     long, which translates to hard request-latency spikes when an
 *     event with thousands of photos is wiped.
 *   - Cloudflare can be temporarily unreachable. A queued job retries
 *     up to 5 times with backoff instead of surfacing the error to the
 *     end-user.
 *
 * Why ShouldBeUnique?
 *   - When a user deletes 1000 photos in 5 seconds the model observers
 *     could enqueue 1000 purge jobs. With ShouldBeUnique keyed on the
 *     event/folder we collapse them to a single bulk purge call —
 *     Cloudflare's per-batch limit (30 URLs) is hit way before that
 *     anyway, so the saving compounds.
 *
 * Failure semantics
 *   - Cloudflare unreachable / disabled / not configured → log warning,
 *     don't throw. Stale cache is preferable to throwing user errors;
 *     the cache TTL will expire on its own anyway.
 *   - The R2 object is the source of truth; the CDN is just a
 *     read-through cache. If purge fails, the next request hits the
 *     origin and Cloudflare re-caches the (now-deleted) 404 / new
 *     content.
 */
class PurgeR2CdnCacheJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Retry up to 5 times on transient Cloudflare API errors */
    public int $tries = 5;

    /** @var array<int, int> Exponential backoff in seconds */
    public array $backoff = [10, 30, 60, 180, 600];

    /** @var int Drop the job if it cannot finish in 60s (Cloudflare timeout itself is 15s). */
    public int $timeout = 60;

    /**
     * @param  array<int, string>  $keys  R2 object keys (no leading slash).
     *                                    The job converts them to absolute URLs
     *                                    using the configured CDN domain.
     */
    public function __construct(
        public readonly array $keys,
        public readonly ?string $uniqueTag = null,
    ) {}

    /**
     * Coalesce purge bursts on the same resource. We hash the resource tag
     * (or the first key) so 1000 deletes for one event become one job.
     */
    public function uniqueId(): string
    {
        return $this->uniqueTag ?? md5(($this->keys[0] ?? '') . '|' . count($this->keys));
    }

    /** Hold the lock for at most 30s so a stuck job doesn't suppress real work. */
    public int $uniqueFor = 30;

    public function handle(CloudflareCachePurgeService $cf): void
    {
        if (!$cf->isEnabled()) {
            // No CDN in front of R2 — nothing to purge. Don't fail the job;
            // delete operations should not be coupled to CDN configuration.
            return;
        }
        if (count($this->keys) === 0) {
            return;
        }

        $cdnDomain = $this->cdnDomain();
        if ($cdnDomain === '') {
            // Cloudflare enabled but no public domain configured — we can't
            // build absolute URLs, so a tag-based purge would be needed.
            // Fall back to no-op + log.
            Log::info('PurgeR2CdnCacheJob: Cloudflare enabled but no CDN domain set. Skipping.', [
                'key_count' => count($this->keys),
            ]);
            return;
        }

        $urls = array_map(
            fn (string $key) => $cdnDomain . '/' . ltrim($key, '/'),
            $this->keys,
        );

        $result = $cf->purgeFiles($urls);

        if (!($result['ok'] ?? false)) {
            // Throw → triggers backoff retry. After tries=5 the job lands in
            // failed_jobs; ops can replay it from Horizon if needed.
            throw new \RuntimeException(
                'Cloudflare purge failed: ' . ($result['error'] ?? 'unknown error')
            );
        }

        Log::info('R2 CDN cache purged', [
            'count'   => count($urls),
            'batches' => $result['batches'] ?? 1,
        ]);
    }

    /**
     * Resolve the public CDN domain that fronts R2 (in priority order):
     *   1. AppSetting `r2_custom_domain`     — admin-configurable
     *   2. AppSetting `r2_public_url`        — bucket public URL
     *   3. config filesystems.disks.r2.url   — env-configured
     */
    private function cdnDomain(): string
    {
        $custom = (string) AppSetting::get('r2_custom_domain', '');
        if ($custom !== '') {
            return rtrim($this->ensureScheme($custom), '/');
        }
        $public = (string) (AppSetting::get('r2_public_url', '') ?: config('filesystems.disks.r2.url', ''));
        return rtrim($this->ensureScheme($public), '/');
    }

    private function ensureScheme(string $domain): string
    {
        if ($domain === '') return '';
        if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
            return $domain;
        }
        return 'https://' . $domain;
    }
}

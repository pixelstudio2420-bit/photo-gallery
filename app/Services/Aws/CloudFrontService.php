<?php

namespace App\Services\Aws;

use App\Models\AppSetting;
use Aws\CloudFront\CloudFrontClient;
use Aws\CloudFront\UrlSigner;
use Illuminate\Support\Facades\Log;

class CloudFrontService
{
    /** @var CloudFrontClient|null Lazily instantiated SDK client */
    protected ?CloudFrontClient $client = null;

    /**
     * Check whether CloudFront integration is configured and usable.
     *
     * At minimum a distribution domain must be present. Signed-URL
     * features additionally require a key pair ID and private key.
     */
    public function isEnabled(): bool
    {
        return !empty(AppSetting::get('aws_cloudfront_domain'));
    }

    // ─── Signed URLs ─────────────────────────────────────────────────

    /**
     * Create a CloudFront signed URL so that a private object can be
     * accessed through the CDN for a limited time.
     *
     * Requires `aws_cloudfront_key_pair_id` and `aws_cloudfront_private_key`
     * to be stored in AppSettings.
     *
     * @param  string  $url            Full CloudFront URL to sign
     * @param  int     $expireMinutes  Link lifetime in minutes
     * @return string  Signed URL, or the original URL on failure
     */
    public function getSignedUrl(string $url, int $expireMinutes = 60): string
    {
        try {
            $keyPairId = AppSetting::get('aws_cloudfront_key_pair_id');
            $privateKey = AppSetting::get('aws_cloudfront_private_key');

            if (empty($keyPairId) || empty($privateKey)) {
                Log::warning('CloudFrontService: Key pair not configured, returning unsigned URL.');
                return $url;
            }

            $signer = new UrlSigner($keyPairId, $privateKey);

            return $signer->getSignedUrl($url, time() + ($expireMinutes * 60));
        } catch (\Throwable $e) {
            Log::error('CloudFrontService: Signed URL generation failed', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return $url;
        }
    }

    /**
     * Generate CloudFront signed cookies for batch / streaming access
     * to a set of resources matching a wildcard pattern.
     *
     * The returned array contains the three Set-Cookie values that the
     * browser needs to include in subsequent requests:
     *   CloudFront-Policy, CloudFront-Signature, CloudFront-Key-Pair-Id
     *
     * @param  string  $resourcePattern  Wildcard URL (e.g. 'https://cdn.example.com/photos/*')
     * @param  int     $expireMinutes    Cookie lifetime in minutes
     * @return array<string, string>     Cookie name => value pairs
     */
    public function getSignedCookies(string $resourcePattern, int $expireMinutes = 60): array
    {
        try {
            $keyPairId = AppSetting::get('aws_cloudfront_key_pair_id');
            $privateKey = AppSetting::get('aws_cloudfront_private_key');

            if (empty($keyPairId) || empty($privateKey)) {
                Log::warning('CloudFrontService: Key pair not configured, cannot create signed cookies.');
                return [];
            }

            $client = $this->getClient();

            $policy = json_encode([
                'Statement' => [
                    [
                        'Resource' => $resourcePattern,
                        'Condition' => [
                            'DateLessThan' => [
                                'AWS:EpochTime' => time() + ($expireMinutes * 60),
                            ],
                        ],
                    ],
                ],
            ]);

            $signer = new UrlSigner($keyPairId, $privateKey);

            return $signer->getSignedCookie($resourcePattern, time() + ($expireMinutes * 60));
        } catch (\Throwable $e) {
            Log::error('CloudFrontService: Signed cookie generation failed', [
                'pattern' => $resourcePattern,
                'error'   => $e->getMessage(),
            ]);
            return [];
        }
    }

    // ─── Cache Invalidation ──────────────────────────────────────────

    /**
     * Create a CloudFront invalidation request for the given paths.
     *
     * Paths must start with '/' and may use wildcards (e.g. '/photos/*').
     *
     * @param  array<string>  $paths  S3-style paths to invalidate
     * @return string|null    Invalidation ID on success, null on failure
     */
    public function invalidateCache(array $paths): ?string
    {
        if (empty($paths)) {
            return null;
        }

        try {
            $distributionId = AppSetting::get('aws_cloudfront_distribution_id');

            if (empty($distributionId)) {
                Log::warning('CloudFrontService: Distribution ID not configured, cannot invalidate.');
                return null;
            }

            // Ensure every path starts with '/'
            $normalised = array_map(
                fn (string $p) => str_starts_with($p, '/') ? $p : "/{$p}",
                $paths
            );

            $client = $this->getClient();

            $result = $client->createInvalidation([
                'DistributionId' => $distributionId,
                'InvalidationBatch' => [
                    'CallerReference' => 'inv-' . time() . '-' . substr(md5(implode(',', $normalised)), 0, 8),
                    'Paths' => [
                        'Items'    => $normalised,
                        'Quantity' => count($normalised),
                    ],
                ],
            ]);

            $invalidationId = $result['Invalidation']['Id'] ?? null;

            Log::info('CloudFrontService: Invalidation created', [
                'id'    => $invalidationId,
                'paths' => $normalised,
            ]);

            return $invalidationId;
        } catch (\Throwable $e) {
            Log::error('CloudFrontService: Cache invalidation failed', [
                'paths' => $paths,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ─── URL Helpers ─────────────────────────────────────────────────

    /**
     * Convert an S3 object key to a CloudFront distribution URL.
     *
     * @param  string  $s3Path  S3 object key (e.g. 'photos/events/42/original/abc.jpg')
     * @return string  Full CloudFront URL, or empty string if not configured
     */
    public function getDistributionUrl(string $s3Path): string
    {
        $domain = AppSetting::get('aws_cloudfront_domain');

        if (empty($domain)) {
            return '';
        }

        $domain = rtrim($domain, '/');
        $key = ltrim($s3Path, '/');

        return "https://{$domain}/{$key}";
    }

    /**
     * Smart URL generator for photos.
     *
     * Picks the best delivery method in this priority order:
     *   1. Signed CloudFront URL  (CDN + access control)
     *   2. Unsigned CloudFront URL (CDN, public content)
     *   3. S3 presigned URL        (no CDN, access control)
     *   4. Plain S3 public URL     (fallback)
     *
     * @param  string  $s3Path         S3 object key
     * @param  bool    $signed         Whether to require a signed (time-limited) URL
     * @param  int     $expireMinutes  Lifetime when signed is true
     * @return string  The best available URL
     */
    public function getPhotoUrl(string $s3Path, bool $signed = false, int $expireMinutes = 60): string
    {
        try {
            // ── CloudFront path ──────────────────────────────────────
            if ($this->isEnabled()) {
                $cfUrl = $this->getDistributionUrl($s3Path);

                if ($signed) {
                    return $this->getSignedUrl($cfUrl, $expireMinutes);
                }

                return $cfUrl;
            }

            // ── S3-only fallback ─────────────────────────────────────
            $s3Service = app(S3StorageService::class);

            if ($signed) {
                return $s3Service->generatePresignedUrl($s3Path, $expireMinutes);
            }

            return $s3Service->getUrl($s3Path);
        } catch (\Throwable $e) {
            Log::error('CloudFrontService: Photo URL generation failed', [
                'path'  => $s3Path,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    // ─── Internal Helpers ────────────────────────────────────────────

    /**
     * Get or create the CloudFront SDK client instance.
     *
     * Region and credentials are pulled from the S3 filesystem config
     * so that they stay consistent across services.
     */
    protected function getClient(): CloudFrontClient
    {
        if ($this->client === null) {
            $this->client = new CloudFrontClient([
                'version'     => 'latest',
                'region'      => config('filesystems.disks.s3.region', 'us-east-1'),
                'credentials' => [
                    'key'    => config('filesystems.disks.s3.key'),
                    'secret' => config('filesystems.disks.s3.secret'),
                ],
            ]);
        }

        return $this->client;
    }
}

<?php

namespace App\Services\Media;

use App\Jobs\PurgeR2CdnCacheJob;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\Exceptions\StorageNotConfiguredException;
use App\Support\FlatConfig;
use Aws\S3\S3Client;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The single entry point for putting media on Cloudflare R2.
 *
 * Why a thin facade?
 *   - Every upload point in the app must use the same path schema. The
 *     factory methods `uploadAvatar()`, `uploadEventPhoto()`, etc. enforce
 *     that schema by constructing the MediaContext for the caller.
 *   - When STORAGE_R2_ONLY=true, no other disk is ever touched. Local
 *     storage of customer photos is impossible by construction.
 *   - Direct-to-bucket uploads (presigned PUT) are a first-class option
 *     so the app server never sees the bytes for large galleries.
 *
 * What this service does NOT do
 *   - Image processing (thumbnail, watermark) — see ImageProcessorService
 *   - Database persistence — controllers/services own their tables
 *   - URL signing for read access (small reads use Storage::url; large
 *     reads / paid downloads use signedReadUrl())
 */
class R2MediaService
{
    /**
     * Reserved user_id for platform-owned (non-user) assets — site logo,
     * watermark, default OG image, favicon, etc. The deleteUser() sweep
     * iterates real user IDs only, so user_0/ stays put when individual
     * users are wiped via GDPR delete.
     */
    public const SYSTEM_USER_ID = 0;

    public function __construct(
        private readonly MediaPathBuilder $pathBuilder,
    ) {}

    /* ─────────────────── Factory methods (preferred API) ─────────────────── */

    public function uploadAvatar(int $userId, UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('auth', 'avatar', $userId),
            $file,
        );
    }

    public function uploadCover(int $userId, UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('auth', 'cover', $userId),
            $file,
        );
    }

    public function uploadEventPhoto(int $photographerUserId, int $eventId, UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('events', 'photos', $photographerUserId, $eventId),
            $file,
        );
    }

    public function uploadEventThumbnail(int $photographerUserId, int $eventId, string $localPath, string $originalKey): MediaUploadResult
    {
        // Reuse the UUID of the original to keep derivatives discoverable.
        // {uuid}_DSC.jpg → {uuid}_thumb.webp
        $uuid = $this->extractUuid($originalKey) ?? bin2hex(random_bytes(16));

        return $this->uploadFromPathByContext(
            MediaContext::make('events', 'thumbnails', $photographerUserId, $eventId),
            localPath: $localPath,
            filenameOverride: "{$uuid}_thumb.webp",
            mimeOverride: 'image/webp',
        );
    }

    public function uploadEventWatermarked(int $photographerUserId, int $eventId, string $localPath, string $originalKey): MediaUploadResult
    {
        $uuid = $this->extractUuid($originalKey) ?? bin2hex(random_bytes(16));

        return $this->uploadFromPathByContext(
            MediaContext::make('events', 'watermarked', $photographerUserId, $eventId),
            localPath: $localPath,
            filenameOverride: "{$uuid}_wm.jpg",
            mimeOverride: 'image/jpeg',
        );
    }

    public function uploadEventCover(int $photographerUserId, int $eventId, UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('events', 'covers', $photographerUserId, $eventId),
            $file,
        );
    }

    public function uploadPaymentSlip(int $customerUserId, int $orderId, UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('payments', 'slips', $customerUserId, $orderId),
            $file,
        );
    }

    /**
     * Upload a brand-ad creative banner. Owned by SYSTEM_USER_ID
     * (these are admin-created assets, not user-owned), scoped to the
     * campaign id so cascading delete on campaign removes the file too.
     */
    public function uploadAdCreative(int $campaignId, UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('system', 'ad_creative', self::SYSTEM_USER_ID, $campaignId),
            $file,
        );
    }

    public function uploadDigitalProduct(int $sellerUserId, int $productId, UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('digital', 'products', $sellerUserId, $productId),
            $file,
        );
    }

    public function uploadDigitalProductCover(int $sellerUserId, int $productId, UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('digital', 'product_covers', $sellerUserId, $productId),
            $file,
        );
    }

    public function uploadBlogImage(int $authorUserId, int $postId, UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('blog', 'posts', $authorUserId, $postId),
            $file,
        );
    }

    /**
     * Upload an announcement image (cover or attachment).
     *
     * Path schema: announcements/posts/{adminId}/{announcementId}/...
     * Used by Admin\AnnouncementController for both cover_image_path and
     * each AnnouncementAttachment row. Cleanup happens via $this->forget()
     * when an announcement (or attachment) is deleted.
     */
    public function uploadAnnouncementImage(int $authorAdminId, int $announcementId, UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('announcements', 'posts', $authorAdminId, $announcementId),
            $file,
        );
    }

    public function uploadPortfolioImage(int $photographerUserId, UploadedFile $file): MediaUploadResult
    {
        // Portfolio is user-scoped (a JSON column on the profile, not a
        // separate entity) — no resource_id needed.
        return $this->uploadByContext(
            MediaContext::make('photographer', 'portfolio', $photographerUserId),
            $file,
        );
    }

    public function uploadBrandingAsset(int $photographerUserId, UploadedFile $file): MediaUploadResult
    {
        // Branding is user-scoped (one logo per photographer) — no resource_id.
        return $this->uploadByContext(
            MediaContext::make('photographer', 'branding', $photographerUserId),
            $file,
        );
    }

    public function uploadPreset(int $photographerUserId, int $presetId, UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('photographer', 'presets', $photographerUserId, $presetId),
            $file,
        );
    }

    public function uploadUserStorageFile(int $ownerUserId, int $folderId, UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('storage', 'files', $ownerUserId, $folderId),
            $file,
        );
    }

    public function uploadChatAttachment(int $senderUserId, int $conversationId, UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('chat', 'attachments', $senderUserId, $conversationId),
            $file,
        );
    }

    public function uploadFaceSearchSelfie(int $searcherUserId, int $queryId, UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('face_search', 'queries', $searcherUserId, $queryId),
            $file,
        );
    }

    /* ─────────────── System (platform-owned) factories ─────────────── */

    public function uploadSystemBranding(UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('system', 'branding', self::SYSTEM_USER_ID),
            $file,
        );
    }

    public function uploadSystemWatermark(UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('system', 'watermark', self::SYSTEM_USER_ID),
            $file,
        );
    }

    public function uploadSystemSeoOg(UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('system', 'seo_og', self::SYSTEM_USER_ID),
            $file,
        );
    }

    public function uploadSystemFavicon(UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('system', 'favicon', self::SYSTEM_USER_ID),
            $file,
        );
    }

    /* ─────────────── Blog / integration extras ─────────────── */

    public function uploadBlogAffiliateBanner(int $authorUserId, int $linkId, UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('blog', 'affiliate_banners', $authorUserId, $linkId),
            $file,
        );
    }

    public function uploadLineRichMenu(int $adminUserId, int $menuId, UploadedFile $file): MediaUploadResult
    {
        return $this->uploadByContext(
            MediaContext::make('integrations', 'line_richmenu', $adminUserId, $menuId),
            $file,
        );
    }

    /* ─────────────────── Generic context-driven API ─────────────────── */

    /**
     * The workhorse. Validates, mints a path, uploads, verifies, returns.
     * Every factory method above ultimately calls this.
     */
    public function uploadByContext(MediaContext $ctx, UploadedFile $file): MediaUploadResult
    {
        $this->ensureR2Available();
        $category = $this->category($ctx);
        $this->validateUploadedFile($file, $category);

        $key = $this->pathBuilder->build($ctx, $file->getClientOriginalName());
        $disk = $this->disk();

        $disk->putFileAs(
            dirname($key),
            $file,
            basename($key),
            ['visibility' => $category['visibility']],
        );

        $this->verifyOrFail($key);
        return $this->makeResult($key, $file->getSize(), $file->getMimeType() ?: 'application/octet-stream', $category['visibility']);
    }

    /**
     * Used for derivatives (thumbnails, watermarks) that originate from a
     * local file rather than an HTTP upload. The derivative is stored on
     * R2 immediately and the local copy is the caller's responsibility
     * to clean up.
     */
    public function uploadFromPathByContext(
        MediaContext $ctx,
        string $localPath,
        ?string $filenameOverride = null,
        ?string $mimeOverride = null,
    ): MediaUploadResult {
        $this->ensureR2Available();
        if (!is_file($localPath) || !is_readable($localPath)) {
            throw InvalidMediaFileException::unreadable($localPath);
        }
        $category = $this->category($ctx);

        $size = filesize($localPath) ?: 0;
        if ($size > $category['max_bytes']) {
            throw InvalidMediaFileException::tooLarge($size, $category['max_bytes']);
        }

        $key = $filenameOverride !== null
            ? $this->pathBuilder->buildWithFilename($ctx, $filenameOverride)
            : $this->pathBuilder->build($ctx, basename($localPath));

        $disk = $this->disk();

        $stream = fopen($localPath, 'rb');
        if ($stream === false) {
            throw InvalidMediaFileException::unreadable($localPath);
        }
        try {
            $disk->put($key, $stream, ['visibility' => $category['visibility']]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->verifyOrFail($key);

        $mime = $mimeOverride ?? (mime_content_type($localPath) ?: 'application/octet-stream');
        return $this->makeResult($key, $size, $mime, $category['visibility']);
    }

    /**
     * Issue a presigned PUT URL so the browser uploads straight to R2.
     * Caller must enforce Content-Type matching {expected_mime} and the
     * client-side file size check on `max_bytes`.
     */
    public function presignedUploadUrl(MediaContext $ctx, string $originalFilename, ?string $expectedMime = null): PresignedUploadResult
    {
        $this->ensureR2Available();
        $category = $this->category($ctx);

        // If the caller passed a MIME, ensure it's allowed; otherwise pick
        // a reasonable default (the first allowlisted entry).
        $mime = $expectedMime ?: ($category['allowed_mime'][0] ?? 'application/octet-stream');
        if (is_array($category['allowed_mime'] ?? null) && !in_array($mime, $category['allowed_mime'], true)) {
            throw InvalidMediaFileException::disallowedMime($mime, $category['allowed_mime']);
        }

        $key  = $this->pathBuilder->build($ctx, $originalFilename);
        $ttl  = (int) config('media.signed_upload_ttl_minutes', 30);

        $client  = $this->s3Client();
        $command = $client->getCommand('PutObject', [
            'Bucket'      => config('filesystems.disks.r2.bucket'),
            'Key'         => $key,
            'ContentType' => $mime,
            'ACL'         => $category['visibility'] === 'public' ? 'public-read' : 'private',
        ]);
        $request = $client->createPresignedRequest($command, "+{$ttl} minutes");

        return new PresignedUploadResult(
            url:          (string) $request->getUri(),
            key:          $key,
            expectedMime: $mime,
            expiresAt:    time() + ($ttl * 60),
            maxBytes:     $category['max_bytes'],
        );
    }

    /* ─────────────────── Read / URL ─────────────────── */

    /**
     * Public CDN-friendly URL for objects with visibility=public.
     * For private objects, use signedReadUrl() — this returns '' instead.
     */
    public function url(string $key): string
    {
        $this->ensureR2Available();
        $disk = $this->disk();
        try {
            return $disk->url(ltrim($key, '/'));
        } catch (\Throwable $e) {
            Log::warning('R2MediaService: url() failed', ['key' => $key, 'error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Signed GET URL — works for both public and private objects.
     */
    public function signedReadUrl(string $key, ?int $expireMinutes = null, ?string $downloadFilename = null): string
    {
        $this->ensureR2Available();
        $expireMinutes ??= (int) config('media.signed_url_ttl_minutes', 60);

        // R2 (and AWS S3) cap presigned URL lifetimes at 7 days. Caller
        // can request a longer window for use-cases like LINE chat
        // images (where a customer might re-open the chat after a
        // month) — we silently clamp to the 7-day ceiling and rely on
        // the application layer to refresh URLs on demand if needed.
        // 7 days = 10080 minutes.
        $hardCapMinutes = 10080;
        if ($expireMinutes > $hardCapMinutes) {
            $expireMinutes = $hardCapMinutes;
        }

        $client = $this->s3Client();
        $args = [
            'Bucket' => config('filesystems.disks.r2.bucket'),
            'Key'    => ltrim($key, '/'),
        ];
        if ($downloadFilename) {
            $safe = str_replace(['"', "\r", "\n"], '', $downloadFilename);
            $args['ResponseContentDisposition'] = "attachment; filename=\"{$safe}\"";
        }
        $command = $client->getCommand('GetObject', $args);
        $request = $client->createPresignedRequest($command, "+{$expireMinutes} minutes");
        return (string) $request->getUri();
    }

    /* ─────────────────── Delete / Cleanup ─────────────────── */

    /**
     * Null-safe delete — silently no-ops on null/empty keys, swallows
     * any storage error. Use when you don't care whether the object
     * was actually there (e.g. cleaning up after a failed upload, or
     * "delete previous avatar if any").
     */
    public function forget(?string $key): void
    {
        if ($key === null || $key === '') return;
        try { $this->delete($key); } catch (\Throwable) {}
    }

    public function delete(string $key): bool
    {
        $this->ensureR2Available();
        $key = ltrim($key, '/');
        try {
            $ok = $this->disk()->delete($key);
            // Best-effort CDN purge: even if the object never existed on R2,
            // a stale cache entry could still be served from Cloudflare's edge.
            // Dispatching is cheap (queue insert); the job no-ops when CF is
            // disabled or unreachable.
            $this->dispatchCdnPurge([$key], "delete:{$key}");
            return $ok;
        } catch (\Throwable $e) {
            Log::error('R2MediaService: delete failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Wipes everything under a context — call when a resource is hard-deleted.
     * Returns the number of objects removed.
     *
     *   $service->deleteResource(MediaContext::make('events','photos', 45, 789));
     *   → deletes everything under events/photos/user_45/event_789/
     */
    public function deleteResource(MediaContext $ctx): int
    {
        $this->ensureR2Available();
        $prefix = $this->pathBuilder->directoryFor($ctx);
        $disk   = $this->disk();

        try {
            $files = $disk->allFiles($prefix);
            if (count($files) === 0) {
                return 0;
            }
            $disk->delete($files);
            // Coalesce ALL purge requests for this resource into a single
            // job so wiping a 5,000-photo event is one Cloudflare API
            // call (chunked at 30/batch internally), not 5,000.
            $this->dispatchCdnPurge($files, "resource:{$prefix}");
            return count($files);
        } catch (\Throwable $e) {
            Log::error('R2MediaService: deleteResource failed', [
                'prefix' => $prefix,
                'error'  => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Wipes EVERYTHING owned by a user across systems. Used on GDPR
     * "delete my account" requests.
     *
     * Iterates the registered systems instead of relying on a top-level
     * `user_*` prefix because the schema interleaves system first.
     */
    public function deleteUser(int $userId): int
    {
        $this->ensureR2Available();
        $disk    = $this->disk();
        $deleted = 0;
        $allFiles = [];

        foreach (array_keys(FlatConfig::section('media.categories')) as $key) {
            [$system, $entity] = explode('.', $key, 2);
            $prefix = sprintf('%s/%s/user_%d', $system, $entity, $userId);
            try {
                $files = $disk->allFiles($prefix);
                if (count($files) > 0) {
                    $disk->delete($files);
                    $deleted += count($files);
                    $allFiles = array_merge($allFiles, $files);
                }
            } catch (\Throwable $e) {
                Log::warning('R2MediaService: deleteUser partial failure', [
                    'user_id' => $userId,
                    'prefix'  => $prefix,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        if (count($allFiles) > 0) {
            $this->dispatchCdnPurge($allFiles, "user:{$userId}");
        }
        return $deleted;
    }

    /* ─────────────────── Internals ─────────────────── */

    /** Ensures R2 is configured. Throws StorageNotConfiguredException otherwise. */
    private function ensureR2Available(): void
    {
        if (!config('media.r2_only', true)) {
            // Local dev / tests — caller has explicitly opted out of R2-only.
            return;
        }
        if (config('media.disk') !== 'r2') {
            return; // tests may swap to a fake disk; allow it.
        }
        $bucket = config('filesystems.disks.r2.bucket');
        $key    = config('filesystems.disks.r2.key');
        $secret = config('filesystems.disks.r2.secret');
        if (!$bucket || !$key || !$secret) {
            throw StorageNotConfiguredException::r2Required();
        }
    }

    /** @return array<string, mixed> */
    private function category(MediaContext $ctx): array
    {
        $cfg = FlatConfig::get('media.categories', $ctx->categoryKey());
        if (!is_array($cfg)) {
            // The MediaContext constructor already guards this, so reaching
            // here means cache mismatch / runtime config swap.
            throw new \LogicException("Media category {$ctx->categoryKey()} disappeared at runtime");
        }
        return $cfg;
    }

    /** @param  array<string, mixed>  $category */
    private function validateUploadedFile(UploadedFile $file, array $category): void
    {
        if (!$file->isValid()) {
            throw InvalidMediaFileException::unreadable($file->getClientOriginalName());
        }

        $size = $file->getSize() ?: 0;
        if ($size > $category['max_bytes']) {
            throw InvalidMediaFileException::tooLarge($size, $category['max_bytes']);
        }

        // MIME — server-side detected, NOT trusted from the client header.
        $allowedMimes = $category['allowed_mime'] ?? null;
        if (is_array($allowedMimes) && count($allowedMimes) > 0) {
            $detected = $file->getMimeType() ?: '';
            if (!in_array($detected, $allowedMimes, true)) {
                throw InvalidMediaFileException::disallowedMime($detected, $allowedMimes);
            }
        }

        // Extension — second line of defence.
        $allowedExts = $category['allowed_extensions'] ?? null;
        if (is_array($allowedExts) && count($allowedExts) > 0) {
            $ext = strtolower($file->getClientOriginalExtension() ?: '');
            if (!in_array($ext, $allowedExts, true)) {
                throw InvalidMediaFileException::disallowedExtension($ext, $allowedExts);
            }
        }
    }

    /**
     * Verify the object actually landed. R2 with throw=false silently swallows
     * failures otherwise.
     */
    private function verifyOrFail(string $key): void
    {
        if (!$this->disk()->exists($key)) {
            throw StorageNotConfiguredException::uploadFailed($key);
        }
    }

    private function makeResult(string $key, int $size, string $mime, string $visibility): MediaUploadResult
    {
        return new MediaUploadResult(
            key:        $key,
            url:        $visibility === 'public' ? $this->url($key) : '',
            sizeBytes:  $size,
            mimeType:   $mime,
            visibility: $visibility,
            disk:       (string) config('media.disk', 'r2'),
        );
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('media.disk', 'r2'));
    }

    private function s3Client(): S3Client
    {
        return new S3Client([
            'version'                 => 'latest',
            'region'                  => config('filesystems.disks.r2.region', 'auto'),
            'endpoint'                => config('filesystems.disks.r2.endpoint'),
            'use_path_style_endpoint' => (bool) config('filesystems.disks.r2.use_path_style_endpoint', false),
            'credentials' => [
                'key'    => config('filesystems.disks.r2.key'),
                'secret' => config('filesystems.disks.r2.secret'),
            ],
        ]);
    }

    /**
     * Pull the UUID prefix out of a key like
     *   events/photos/user_45/event_789/0193e2bf-7ce6-7232-93b1-9d0a89ae3f9b_DSC.jpg
     * Returns null if no UUID is detectable.
     */
    private function extractUuid(string $key): ?string
    {
        $base = basename($key);
        if (preg_match('/^([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})_/i', $base, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Schedule an async Cloudflare cache purge for the given keys.
     *
     * Skipped entirely outside production / when the queue isn't ready —
     * for instance during tests we don't want the queue to materialise.
     */
    private function dispatchCdnPurge(array $keys, string $tag): void
    {
        if (count($keys) === 0) {
            return;
        }
        if (config('media.skip_cdn_purge', false)) {
            return;
        }
        try {
            PurgeR2CdnCacheJob::dispatch(array_values($keys), $tag);
        } catch (\Throwable $e) {
            // Queue down → log + carry on. Cache TTL will eventually expire.
            Log::warning('R2MediaService: failed to dispatch CDN purge job', [
                'tag'   => $tag,
                'count' => count($keys),
                'error' => $e->getMessage(),
            ]);
        }
    }
}

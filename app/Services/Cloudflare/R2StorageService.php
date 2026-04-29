<?php

namespace App\Services\Cloudflare;

use App\Models\AppSetting;
use Aws\S3\S3Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cloudflare R2 storage service.
 *
 * R2 is S3-compatible, so this uses Laravel's S3 driver with the 'r2' disk.
 * Configuration can come from either .env or AppSetting (admin panel).
 * AppSetting values take priority when present, allowing runtime changes
 * without redeploying.
 */
class R2StorageService
{
    protected string $disk = 'r2';

    /**
     * Boot R2 config from AppSetting if available (overrides .env at runtime).
     */
    public function __construct()
    {
        $this->applyDynamicConfig();
    }

    /**
     * Override filesystem config with DB-stored credentials when present.
     */
    protected function applyDynamicConfig(): void
    {
        $map = [
            'r2_access_key_id'     => 'key',
            'r2_secret_access_key' => 'secret',
            'r2_bucket'            => 'bucket',
            'r2_endpoint'          => 'endpoint',
            'r2_public_url'        => 'url',
        ];

        foreach ($map as $settingKey => $configKey) {
            $value = AppSetting::get($settingKey, '');
            if ($value !== '') {
                config(["filesystems.disks.r2.{$configKey}" => $value]);
            }
        }
    }

    /**
     * Check if R2 storage is configured and enabled.
     */
    public function isEnabled(): bool
    {
        if (AppSetting::get('r2_enabled', '0') !== '1') {
            return false;
        }

        return !empty(config('filesystems.disks.r2.bucket'))
            && !empty(config('filesystems.disks.r2.key'))
            && !empty(config('filesystems.disks.r2.secret'))
            && !empty(config('filesystems.disks.r2.endpoint'));
    }

    // ─── Upload Operations ───────────────────────────────────────────

    /**
     * Upload an HTTP file to R2.
     */
    public function upload(UploadedFile $file, string $folder, array $options = []): array
    {
        if (!$this->isEnabled()) {
            Log::warning('R2StorageService: R2 is not configured, upload skipped.');
            return ['path' => '', 'url' => '', 'size' => 0, 'mime' => ''];
        }

        try {
            $filename = $options['filename'] ?? (Str::uuid() . '.' . ($file->getClientOriginalExtension() ?: 'bin'));
            $folder = rtrim($folder, '/');
            $fullPath = "{$folder}/{$filename}";

            $storageOptions = ['visibility' => $options['visibility'] ?? 'public'];

            // Flysystem returns null on putFileAs regardless of success — but
            // when `throw=false` in the disk config (which is our default, to
            // keep unrelated disks quiet) failures are silently swallowed and
            // we'd return a "success" path pointing at a file that never
            // landed on R2. Verify the write explicitly so callers actually
            // find out when writes fail (e.g. AccessDenied on the API token).
            $disk = Storage::disk($this->disk);
            $disk->putFileAs($folder, $file, $filename, $storageOptions);

            if (!$disk->exists($fullPath)) {
                throw new \RuntimeException(
                    "R2 upload returned success but the object is missing at {$fullPath} — "
                    . "likely an AccessDenied on the API token (missing Object Write permission) "
                    . "or the token is scoped to the wrong bucket."
                );
            }

            return [
                'path' => $fullPath,
                'url'  => $this->getUrl($fullPath),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ];
        } catch (\Throwable $e) {
            Log::error('R2StorageService: Upload failed', [
                'folder' => $folder,
                'error'  => $e->getMessage(),
            ]);
            // Re-throw so the controller can surface a real error to the
            // user instead of blindly committing a DB record that points
            // at a non-existent file.
            throw new \RuntimeException('R2 upload failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Upload raw binary content to R2.
     */
    public function put(string $path, string $contents, array $options = []): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $storageOptions = ['visibility' => $options['visibility'] ?? 'public'];
            return Storage::disk($this->disk)->put($path, $contents, $storageOptions);
        } catch (\Throwable $e) {
            Log::error('R2StorageService: Put failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Upload a local file to R2 by filesystem path.
     */
    public function uploadFromPath(string $localPath, string $r2Path, array $options = []): array
    {
        if (!$this->isEnabled()) {
            return ['path' => '', 'url' => '', 'size' => 0, 'mime' => ''];
        }

        try {
            if (!file_exists($localPath)) {
                Log::error('R2StorageService: Local file not found', ['path' => $localPath]);
                return ['path' => '', 'url' => '', 'size' => 0, 'mime' => ''];
            }

            $storageOptions = ['visibility' => $options['visibility'] ?? 'public'];
            Storage::disk($this->disk)->put($r2Path, file_get_contents($localPath), $storageOptions);

            return [
                'path' => $r2Path,
                'url'  => $this->getUrl($r2Path),
                'size' => filesize($localPath),
                'mime' => mime_content_type($localPath) ?: 'application/octet-stream',
            ];
        } catch (\Throwable $e) {
            Log::error('R2StorageService: Upload from path failed', [
                'localPath' => $localPath,
                'r2Path'    => $r2Path,
                'error'     => $e->getMessage(),
            ]);
            return ['path' => '', 'url' => '', 'size' => 0, 'mime' => ''];
        }
    }

    // ─── File Operations ─────────────────────────────────────────────

    public function delete(string $path): bool
    {
        if (!$this->isEnabled() || empty($path)) {
            return false;
        }

        try {
            return Storage::disk($this->disk)->delete($path);
        } catch (\Throwable $e) {
            Log::error('R2StorageService: Delete failed', ['path' => $path, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function deleteFolder(string $folder): int
    {
        if (!$this->isEnabled() || empty($folder)) {
            return 0;
        }

        try {
            $files = Storage::disk($this->disk)->allFiles($folder);
            if (count($files) > 0) {
                Storage::disk($this->disk)->delete($files);
            }
            return count($files);
        } catch (\Throwable $e) {
            Log::error('R2StorageService: Delete folder failed', ['folder' => $folder, 'error' => $e->getMessage()]);
            return 0;
        }
    }

    public function exists(string $path): bool
    {
        if (!$this->isEnabled() || empty($path)) {
            return false;
        }

        try {
            return Storage::disk($this->disk)->exists($path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function copy(string $from, string $to): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            return Storage::disk($this->disk)->copy($from, $to);
        } catch (\Throwable $e) {
            Log::error('R2StorageService: Copy failed', ['from' => $from, 'to' => $to, 'error' => $e->getMessage()]);
            return false;
        }
    }

    // ─── URL & Metadata ──────────────────────────────────────────────

    /**
     * Get the public URL for an R2 object.
     *
     * Priority: custom domain > R2 public URL > Storage::url()
     */
    public function getUrl(string $path): string
    {
        if (!$this->isEnabled() || empty($path)) {
            return '';
        }

        try {
            // 1) Custom domain (e.g. cdn.example.com via Cloudflare Worker or custom domain on R2)
            $customDomain = AppSetting::get('r2_custom_domain', '');
            if (!empty($customDomain)) {
                $domain = rtrim($customDomain, '/');
                $key = ltrim($path, '/');
                return "https://{$domain}/{$key}";
            }

            // 2) R2 public bucket URL (e.g. pub-xxx.r2.dev)
            $publicUrl = AppSetting::get('r2_public_url', '') ?: config('filesystems.disks.r2.url', '');
            if (!empty($publicUrl)) {
                $base = rtrim($publicUrl, '/');
                $key = ltrim($path, '/');
                return "{$base}/{$key}";
            }

            // 3) Fallback to Laravel Storage URL
            return Storage::disk($this->disk)->url($path);
        } catch (\Throwable $e) {
            Log::error('R2StorageService: URL generation failed', ['path' => $path, 'error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Generate a presigned URL for a (possibly private) R2 object.
     *
     * R2 uses the same presign protocol as S3, so we reuse the underlying
     * AWS SDK. Customers can hit this URL directly — zero application-server
     * bandwidth, zero Laravel worker time.
     *
     * @param  string  $path            Object key
     * @param  int     $expireMinutes   Lifetime in minutes (default 60)
     * @return string                   Presigned URL or '' on failure
     */
    public function generatePresignedUrl(string $path, int $expireMinutes = 60, ?string $downloadFilename = null): string
    {
        if (!$this->isEnabled() || empty($path)) {
            return '';
        }

        try {
            $client = $this->getR2Client();

            // Bake `ResponseContentDisposition` into the GetObject command so
            // it's part of the signature. Appending `response-content-disposition`
            // to an already-signed URL invalidates the v4 signature (R2 returns
            // 403 SignatureDoesNotMatch).
            $commandArgs = [
                'Bucket' => config('filesystems.disks.r2.bucket'),
                'Key'    => ltrim($path, '/'),
            ];
            if ($downloadFilename !== null && $downloadFilename !== '') {
                // Strip quotes/newlines that would break the header
                $safe = str_replace(['"', "\r", "\n"], '', $downloadFilename);
                $commandArgs['ResponseContentDisposition'] = "attachment; filename=\"{$safe}\"";
            }

            $command = $client->getCommand('GetObject', $commandArgs);
            $request = $client->createPresignedRequest($command, "+{$expireMinutes} minutes");
            return (string) $request->getUri();
        } catch (\Throwable $e) {
            Log::error('R2StorageService: Presigned URL generation failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Lightweight health probe — verifies bucket access by listing a single file.
     */
    public function healthCheck(): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'reason' => 'not enabled'];
        }

        try {
            // `files('')` with limit isn't supported, so list root and take first.
            $files = Storage::disk($this->disk)->files('', false);
            return [
                'ok'     => true,
                'bucket' => config('filesystems.disks.r2.bucket'),
                'probe'  => 'list ok (' . count($files) . ' items at root)',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => $e->getMessage()];
        }
    }

    public function getMetadata(string $path): array
    {
        if (!$this->isEnabled() || empty($path)) {
            return [];
        }

        try {
            return [
                'size'         => Storage::disk($this->disk)->size($path),
                'lastModified' => Storage::disk($this->disk)->lastModified($path),
                'mimeType'     => Storage::disk($this->disk)->mimeType($path),
                'path'         => $path,
            ];
        } catch (\Throwable $e) {
            Log::error('R2StorageService: Metadata retrieval failed', ['path' => $path, 'error' => $e->getMessage()]);
            return [];
        }
    }

    public function listFiles(string $folder, bool $recursive = false): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            return $recursive
                ? Storage::disk($this->disk)->allFiles($folder)
                : Storage::disk($this->disk)->files($folder);
        } catch (\Throwable $e) {
            Log::error('R2StorageService: List files failed', ['folder' => $folder, 'error' => $e->getMessage()]);
            return [];
        }
    }

    // ─── Path Helpers ────────────────────────────────────────────────

    public function getPhotoPath(int $eventId, string $filename, string $type = 'original'): string
    {
        $allowedTypes = ['original', 'thumbnail', 'watermarked', 'preview'];
        $type = in_array($type, $allowedTypes, true) ? $type : 'original';

        return "photos/events/{$eventId}/{$type}/{$filename}";
    }

    /**
     * Get the R2 disk name for use with Storage::disk().
     */
    public function getDiskName(): string
    {
        return $this->disk;
    }

    /**
     * Get the underlying AWS SDK S3Client bound to the R2 endpoint.
     * Needed for presigned URL generation.
     *
     * We used to reach into `Storage::disk('r2')->getAdapter()->getClient()`,
     * but newer Flysystem S3 adapters no longer expose `getClient()` — the
     * adapter wraps the client privately. Building the `S3Client` directly
     * from the same config values the disk uses gives us a stable handle
     * regardless of Flysystem version.
     */
    protected function getR2Client(): S3Client
    {
        return new S3Client([
            'version'                 => 'latest',
            'region'                  => config('filesystems.disks.r2.region', 'auto'),
            'endpoint'                => config('filesystems.disks.r2.endpoint'),
            'use_path_style_endpoint' => (bool) config('filesystems.disks.r2.use_path_style_endpoint', true),
            'credentials'             => [
                'key'    => config('filesystems.disks.r2.key'),
                'secret' => config('filesystems.disks.r2.secret'),
            ],
        ]);
    }
}

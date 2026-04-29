<?php

namespace App\Services\Aws;

use App\Models\AppSetting;
use Aws\S3\S3Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class S3StorageService
{
    /** @var string S3 disk identifier for Laravel Storage */
    protected string $disk = 's3';

    /**
     * Check if S3 storage is properly configured and usable.
     *
     * Verifies that both bucket name and access credentials exist
     * in the environment configuration.
     */
    public function isEnabled(): bool
    {
        // Master switch from admin — default to 1 so existing deployments
        // keep working if the setting row isn't seeded yet.
        if (AppSetting::get('storage_s3_enabled', '1') !== '1') {
            return false;
        }

        return !empty(config('filesystems.disks.s3.bucket'))
            && !empty(config('filesystems.disks.s3.key'))
            && !empty(config('filesystems.disks.s3.secret'));
    }

    /**
     * Lightweight health probe — verifies bucket credentials + read access.
     */
    public function healthCheck(): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'reason' => 'not enabled'];
        }

        try {
            $files = Storage::disk($this->disk)->files('', false);
            return [
                'ok'     => true,
                'bucket' => config('filesystems.disks.s3.bucket'),
                'probe'  => 'list ok (' . count($files) . ' items at root)',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => $e->getMessage()];
        }
    }

    // ─── Upload Operations ───────────────────────────────────────────

    /**
     * Upload a file from an HTTP request to S3.
     *
     * Generates a unique filename, stores the file on the configured S3
     * disk, and returns metadata about the uploaded object.
     *
     * @param  UploadedFile  $file     The uploaded file from a form request
     * @param  string        $folder   Destination folder (e.g. 'events/123/originals')
     * @param  array         $options  Optional overrides:
     *   - visibility: 'public' | 'private' (default: 'private')
     *   - filename:   Custom filename (auto-generated if omitted)
     *   - acl:        S3 ACL string (overrides visibility)
     *   - metadata:   Associative array of custom S3 metadata
     * @return array{path: string, url: string, size: int, mime: string}
     */
    public function upload(UploadedFile $file, string $folder, array $options = []): array
    {
        if (!$this->isEnabled()) {
            Log::warning('S3StorageService: S3 is not configured, upload skipped.');
            return ['path' => '', 'url' => '', 'size' => 0, 'mime' => ''];
        }

        try {
            $filename = $options['filename'] ?? $this->generateFilename($file);
            $visibility = $options['visibility'] ?? 'private';
            $folder = rtrim($folder, '/');
            $fullPath = "{$folder}/{$filename}";

            $storageOptions = ['visibility' => $visibility];

            // Apply explicit ACL if provided (overrides visibility)
            if (isset($options['acl'])) {
                $storageOptions['ACL'] = $options['acl'];
            }

            // Attach custom S3 metadata headers
            if (!empty($options['metadata'])) {
                $storageOptions['Metadata'] = $options['metadata'];
            }

            Storage::disk($this->disk)->putFileAs(
                $folder,
                $file,
                $filename,
                $storageOptions
            );

            return [
                'path' => $fullPath,
                'url'  => $this->getUrl($fullPath),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ];
        } catch (\Throwable $e) {
            Log::error('S3StorageService: Upload failed', [
                'folder' => $folder,
                'error'  => $e->getMessage(),
            ]);
            return ['path' => '', 'url' => '', 'size' => 0, 'mime' => ''];
        }
    }

    /**
     * Upload a local file to S3 by its filesystem path.
     *
     * Reads the file from disk and streams it to the specified S3 key.
     *
     * @param  string  $localPath  Absolute path on the local filesystem
     * @param  string  $s3Path     Destination key inside the S3 bucket
     * @param  array   $options    Same options as {@see upload()}
     * @return array{path: string, url: string, size: int, mime: string}
     */
    public function uploadFromPath(string $localPath, string $s3Path, array $options = []): array
    {
        if (!$this->isEnabled()) {
            Log::warning('S3StorageService: S3 is not configured, upload skipped.');
            return ['path' => '', 'url' => '', 'size' => 0, 'mime' => ''];
        }

        try {
            if (!file_exists($localPath)) {
                Log::error('S3StorageService: Local file not found', ['path' => $localPath]);
                return ['path' => '', 'url' => '', 'size' => 0, 'mime' => ''];
            }

            $visibility = $options['visibility'] ?? 'private';
            $storageOptions = ['visibility' => $visibility];

            if (isset($options['acl'])) {
                $storageOptions['ACL'] = $options['acl'];
            }
            if (!empty($options['metadata'])) {
                $storageOptions['Metadata'] = $options['metadata'];
            }

            $contents = file_get_contents($localPath);
            Storage::disk($this->disk)->put($s3Path, $contents, $storageOptions);

            return [
                'path' => $s3Path,
                'url'  => $this->getUrl($s3Path),
                'size' => filesize($localPath),
                'mime' => mime_content_type($localPath) ?: 'application/octet-stream',
            ];
        } catch (\Throwable $e) {
            Log::error('S3StorageService: Upload from path failed', [
                'localPath' => $localPath,
                's3Path'    => $s3Path,
                'error'     => $e->getMessage(),
            ]);
            return ['path' => '', 'url' => '', 'size' => 0, 'mime' => ''];
        }
    }

    // ─── Presigned URL Operations ────────────────────────────────────

    /**
     * Generate a presigned URL for downloading / viewing a private S3 object.
     *
     * Used for paid photos so that only authorised buyers can access the file
     * within the given time window.
     *
     * @param  string  $path           S3 object key
     * @param  int     $expireMinutes  Link lifetime in minutes (default 60)
     * @return string  Presigned URL, or empty string on failure
     */
    public function generatePresignedUrl(string $path, int $expireMinutes = 60, ?string $downloadFilename = null): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        try {
            $client = $this->getS3Client();

            // Bake `ResponseContentDisposition` into the GetObject command so
            // it's signed in. Appending the query param after signing breaks
            // the v4 signature.
            $commandArgs = [
                'Bucket' => config('filesystems.disks.s3.bucket'),
                'Key'    => $path,
            ];
            if ($downloadFilename !== null && $downloadFilename !== '') {
                $safe = str_replace(['"', "\r", "\n"], '', $downloadFilename);
                $commandArgs['ResponseContentDisposition'] = "attachment; filename=\"{$safe}\"";
            }

            $command = $client->getCommand('GetObject', $commandArgs);

            $request = $client->createPresignedRequest(
                $command,
                "+{$expireMinutes} minutes"
            );

            return (string) $request->getUri();
        } catch (\Throwable $e) {
            Log::error('S3StorageService: Presigned URL generation failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Generate a presigned POST URL for direct browser-to-S3 uploads.
     *
     * Returns a form action URL plus the form fields that the browser must
     * include in a multipart POST request. This keeps large photo files off
     * the application server entirely.
     *
     * @param  string  $path           Destination S3 key
     * @param  string  $mimeType       Expected Content-Type (e.g. 'image/jpeg')
     * @param  int     $expireMinutes  Form validity window (default 30)
     * @return array{url: string, fields: array<string, string>}
     */
    public function generatePresignedUploadUrl(
        string $path,
        string $mimeType,
        int $expireMinutes = 30
    ): array {
        if (!$this->isEnabled()) {
            return ['url' => '', 'fields' => []];
        }

        try {
            $client = $this->getS3Client();
            $bucket = config('filesystems.disks.s3.bucket');

            $formInputs = [
                'Content-Type' => $mimeType,
                'key'          => $path,
            ];

            $options = [
                ['bucket' => $bucket],
                ['starts-with', '$Content-Type', $mimeType],
                ['content-length-range', 1, 50 * 1024 * 1024], // max 50 MB
            ];

            $postObject = new \Aws\S3\PostObjectV4(
                $client,
                $bucket,
                $formInputs,
                $options,
                "+{$expireMinutes} minutes"
            );

            return [
                'url'    => $postObject->getFormAttributes()['action'],
                'fields' => $postObject->getFormInputs(),
            ];
        } catch (\Throwable $e) {
            Log::error('S3StorageService: Presigned upload URL generation failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return ['url' => '', 'fields' => []];
        }
    }

    // ─── File Operations ─────────────────────────────────────────────

    /**
     * Delete a single file from S3.
     *
     * @param  string  $path  S3 object key
     * @return bool    True on success or if the file did not exist
     */
    public function delete(string $path): bool
    {
        if (!$this->isEnabled() || empty($path)) {
            return false;
        }

        try {
            return Storage::disk($this->disk)->delete($path);
        } catch (\Throwable $e) {
            Log::error('S3StorageService: Delete failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete every file inside a folder (prefix) on S3.
     *
     * Useful for cleaning up all photos when an event is removed.
     *
     * @param  string  $folder  S3 prefix (e.g. 'photos/events/42')
     * @return int     Number of objects deleted
     */
    public function deleteFolder(string $folder): int
    {
        if (!$this->isEnabled() || empty($folder)) {
            return 0;
        }

        try {
            $files = Storage::disk($this->disk)->allFiles($folder);
            $count = count($files);

            if ($count > 0) {
                Storage::disk($this->disk)->delete($files);
            }

            return $count;
        } catch (\Throwable $e) {
            Log::error('S3StorageService: Delete folder failed', [
                'folder' => $folder,
                'error'  => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Check whether a file exists on S3.
     *
     * @param  string  $path  S3 object key
     */
    public function exists(string $path): bool
    {
        if (!$this->isEnabled() || empty($path)) {
            return false;
        }

        try {
            return Storage::disk($this->disk)->exists($path);
        } catch (\Throwable $e) {
            Log::error('S3StorageService: Exists check failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Copy a file within S3.
     *
     * @param  string  $from  Source key
     * @param  string  $to    Destination key
     */
    public function copy(string $from, string $to): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            return Storage::disk($this->disk)->copy($from, $to);
        } catch (\Throwable $e) {
            Log::error('S3StorageService: Copy failed', [
                'from'  => $from,
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ─── URL & Metadata ──────────────────────────────────────────────

    /**
     * Get the public URL for an S3 object.
     *
     * If a CloudFront domain is configured in AppSettings, the returned
     * URL uses that domain instead of the raw S3 endpoint.
     *
     * @param  string  $path  S3 object key
     * @return string  Full URL
     */
    public function getUrl(string $path): string
    {
        if (!$this->isEnabled() || empty($path)) {
            return '';
        }

        try {
            // Prefer CloudFront domain when available (faster CDN delivery)
            $cloudfrontDomain = AppSetting::get('aws_cloudfront_domain');

            if (!empty($cloudfrontDomain)) {
                $domain = rtrim($cloudfrontDomain, '/');
                $key = ltrim($path, '/');
                return "https://{$domain}/{$key}";
            }

            return Storage::disk($this->disk)->url($path);
        } catch (\Throwable $e) {
            Log::error('S3StorageService: URL generation failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Retrieve metadata for an S3 object.
     *
     * @param  string  $path  S3 object key
     * @return array{size: int, lastModified: int, mimeType: string, path: string}
     */
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
            Log::error('S3StorageService: Metadata retrieval failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * List files inside an S3 folder.
     *
     * @param  string  $folder     S3 prefix
     * @param  bool    $recursive  Whether to descend into sub-folders
     * @return array<string>       Array of S3 keys
     */
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
            Log::error('S3StorageService: List files failed', [
                'folder' => $folder,
                'error'  => $e->getMessage(),
            ]);
            return [];
        }
    }

    // ─── Path Helpers ────────────────────────────────────────────────

    /**
     * Build the standardised S3 key for an event photo.
     *
     * Pattern: photos/events/{eventId}/{type}/{filename}
     *
     * @param  int     $eventId   Event identifier
     * @param  string  $filename  Target filename (e.g. 'abc123.jpg')
     * @param  string  $type      One of: original, thumbnail, watermarked, preview
     */
    public function getPhotoPath(int $eventId, string $filename, string $type = 'original'): string
    {
        $allowedTypes = ['original', 'thumbnail', 'watermarked', 'preview'];
        $type = in_array($type, $allowedTypes, true) ? $type : 'original';

        return "photos/events/{$eventId}/{$type}/{$filename}";
    }

    /**
     * Build the standardised S3 key for a digital product file.
     *
     * Pattern: products/{productId}/{filename}
     *
     * @param  int     $productId  Product identifier
     * @param  string  $filename   Target filename
     */
    public function getProductPath(int $productId, string $filename): string
    {
        return "products/{$productId}/{$filename}";
    }

    // ─── Internal Helpers ────────────────────────────────────────────

    /**
     * Generate a unique, collision-resistant filename that preserves the
     * original extension.
     *
     * @param  UploadedFile  $file
     * @return string  e.g. 'a1b2c3d4e5f6_1712764800.jpg'
     */
    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        return Str::random(16) . '_' . time() . '.' . $extension;
    }

    /**
     * Obtain the underlying AWS S3Client instance from the Storage driver.
     *
     * Needed for operations that the Storage facade does not expose
     * (presigned URLs, PostObject, etc.).
     *
     * @return S3Client
     */
    protected function getS3Client(): S3Client
    {
        // Build the S3Client directly from config. We used to reach in via
        // `Storage::disk('s3')->getAdapter()->getClient()`, but newer Flysystem
        // S3 adapters no longer expose `getClient()`. Constructing from the
        // same config keys gives us a stable handle across Flysystem versions.
        return new S3Client([
            'version'                 => 'latest',
            'region'                  => config('filesystems.disks.s3.region') ?: 'us-east-1',
            'endpoint'                => config('filesystems.disks.s3.endpoint') ?: null,
            'use_path_style_endpoint' => (bool) config('filesystems.disks.s3.use_path_style_endpoint', false),
            'credentials'             => [
                'key'    => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
        ]);
    }
}

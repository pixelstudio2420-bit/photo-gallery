<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\EventPhoto;
use App\Services\Aws\S3StorageService;
use App\Services\Cloudflare\R2StorageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Unified Storage Manager — settings-driven multi-driver orchestrator.
 *
 * Drivers supported:  r2 | s3 | drive | public
 *
 * Behaviour is governed entirely by AppSettings so operators can switch drivers
 * at runtime without redeploying:
 *
 *   storage_primary_driver    Where to READ from (auto = R2 > S3 > public)
 *   storage_upload_driver     Where new uploads LAND (auto = same as primary)
 *   storage_zip_disk          Where built ZIPs are staged
 *   storage_mirror_enabled    Copy every upload to N additional drivers
 *   storage_mirror_targets    JSON array e.g. ["s3","drive"]
 *   storage_use_signed_urls   Hand the browser a signed URL (direct to cloud)
 *   storage_signed_url_ttl    Lifetime for those URLs (seconds)
 *
 * Why this matters at scale
 * -------------------------
 * For 5,000+ concurrent customer downloads/day, Google Drive hits hard API
 * and per-file quotas. R2 (Cloudflare) has zero egress fees and effectively
 * unlimited concurrency — so the flow becomes:
 *
 *   browser  ─signed URL─►  R2 / S3 (direct, zero app-server bandwidth)
 *
 * Drive remains as an optional mirror for archival / photographer workflows.
 */
class StorageManager
{
    public const DRIVER_R2     = 'r2';
    public const DRIVER_S3     = 's3';
    public const DRIVER_DRIVE  = 'drive';
    public const DRIVER_PUBLIC = 'public';

    protected R2StorageService $r2;
    protected S3StorageService $s3;

    public function __construct(R2StorageService $r2, S3StorageService $s3)
    {
        $this->r2 = $r2;
        $this->s3 = $s3;
    }

    // ─── Driver Resolution ─────────────────────────────────────────────

    /**
     * Auto-resolve preferred disk: R2 > S3 > public.
     */
    public function preferredDisk(): string
    {
        if ($this->r2->isEnabled()) return self::DRIVER_R2;
        if ($this->s3->isEnabled()) return self::DRIVER_S3;
        return self::DRIVER_PUBLIC;
    }

    /**
     * Resolve primary READ driver (where downloads should come from).
     *
     * Honours admin-configured storage_primary_driver setting; falls back
     * to auto-resolution if set to "auto" or chosen driver is disabled.
     */
    public function primaryDriver(): string
    {
        $configured = AppSetting::get('storage_primary_driver', 'auto');
        if ($configured === 'auto' || !$this->driverIsEnabled($configured)) {
            return $this->preferredDisk();
        }
        return $configured;
    }

    /**
     * Resolve driver where NEW uploads should be stored.
     */
    public function uploadDriver(): string
    {
        $configured = AppSetting::get('storage_upload_driver', 'auto');
        if ($configured === 'auto' || !$this->driverIsEnabled($configured)) {
            return $this->primaryDriver();
        }
        return $configured;
    }

    /**
     * Resolve driver where ZIP archives should be staged.
     */
    public function zipDisk(): string
    {
        $configured = AppSetting::get('storage_zip_disk', 'auto');
        if ($configured === 'auto' || !$this->driverIsEnabled($configured)) {
            // Prefer cloud so large ZIPs don't bloat the app server disk.
            return $this->preferredDisk();
        }
        return $configured;
    }

    /**
     * Mirror targets when storage_mirror_enabled=1.
     *
     * @return array<int,string>
     */
    public function mirrorTargets(): array
    {
        if (AppSetting::get('storage_mirror_enabled', '0') !== '1') {
            return [];
        }
        $raw = AppSetting::get('storage_mirror_targets', '[]');
        $list = is_string($raw) ? (json_decode($raw, true) ?: []) : (array) $raw;

        // Filter to enabled drivers only and exclude the upload driver itself.
        $upload = $this->uploadDriver();
        return array_values(array_filter(
            array_map('strval', $list),
            fn($d) => $d !== $upload && $this->driverIsEnabled($d)
        ));
    }

    /**
     * Is the given driver configured + enabled?
     */
    public function driverIsEnabled(string $driver): bool
    {
        return match ($driver) {
            self::DRIVER_R2     => $this->r2->isEnabled(),
            self::DRIVER_S3     => $this->s3->isEnabled(),
            self::DRIVER_DRIVE  => AppSetting::get('storage_drive_enabled', '1') === '1',
            self::DRIVER_PUBLIC => true,
            default             => false,
        };
    }

    /**
     * List of driver names currently enabled.
     *
     * @return array<int,string>
     */
    public function availableDrivers(): array
    {
        return array_values(array_filter(
            [self::DRIVER_R2, self::DRIVER_S3, self::DRIVER_DRIVE, self::DRIVER_PUBLIC],
            fn($d) => $this->driverIsEnabled($d)
        ));
    }

    public function isCloudEnabled(): bool
    {
        return $this->r2->isEnabled() || $this->s3->isEnabled();
    }

    // ─── Store Operations ──────────────────────────────────────────────

    /**
     * Store binary content to the configured UPLOAD driver.
     */
    public function put(string $path, string $contents, array $options = []): bool
    {
        $disk = $this->uploadDriver();

        try {
            return $this->putToDriver($disk, $path, $contents, $options);
        } catch (\Throwable $e) {
            Log::error("StorageManager::put failed on disk [{$disk}]", [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Put to a specific driver (used by mirror logic).
     */
    public function putToDriver(string $disk, string $path, string $contents, array $options = []): bool
    {
        if (!$this->driverIsEnabled($disk)) {
            return false;
        }

        if ($disk === self::DRIVER_R2) {
            return $this->r2->put($path, $contents, $options);
        }

        // Drive upload from raw bytes is not modeled here — mirroring to Drive
        // happens through GoogleDriveService::uploadFile from file path.
        if ($disk === self::DRIVER_DRIVE) {
            Log::debug('StorageManager::putToDriver — Drive put by content not supported, skip');
            return false;
        }

        $storageOptions = ['visibility' => $options['visibility'] ?? 'public'];
        return Storage::disk($disk)->put($path, $contents, $storageOptions);
    }

    /**
     * Store an uploaded file to the preferred disk.
     *
     * @return array{path:string,url:string,disk:string}
     */
    public function storeUploadedFile($file, string $folder, string $filename): array
    {
        $disk = $this->uploadDriver();
        $fullPath = rtrim($folder, '/') . '/' . $filename;

        try {
            if ($disk === self::DRIVER_R2) {
                $result = $this->r2->upload($file, $folder, ['filename' => $filename]);
                return ['path' => $fullPath, 'url' => $result['url'], 'disk' => self::DRIVER_R2];
            }

            if ($disk === self::DRIVER_S3) {
                $result = $this->s3->upload($file, $folder, ['filename' => $filename]);
                return ['path' => $fullPath, 'url' => $result['url'], 'disk' => self::DRIVER_S3];
            }

            // Local fallback (Drive uploads happen elsewhere via GoogleDriveService)
            $file->storeAs($folder, $filename, 'public');
            return [
                'path' => $fullPath,
                'url'  => Storage::disk('public')->url($fullPath),
                'disk' => self::DRIVER_PUBLIC,
            ];
        } catch (\Throwable $e) {
            Log::error("StorageManager::storeUploadedFile failed on disk [{$disk}]", [
                'folder' => $folder,
                'error'  => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Store a local temp file to the preferred upload disk.
     */
    public function storeFromPath(string $localPath, string $remotePath): bool
    {
        if (!file_exists($localPath)) {
            return false;
        }
        return $this->put($remotePath, file_get_contents($localPath));
    }

    // ─── URL Resolution ────────────────────────────────────────────────

    /**
     * Public URL for a path on a given disk (or preferred disk).
     */
    public function url(string $path, ?string $disk = null): string
    {
        $disk = $disk ?? $this->primaryDriver();

        try {
            if ($disk === self::DRIVER_R2) return $this->r2->getUrl($path);
            if ($disk === self::DRIVER_S3) return $this->s3->getUrl($path);
            if ($disk === self::DRIVER_DRIVE) return ''; // Drive uses its own URL scheme
            return Storage::disk('public')->url($path);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Smart public-URL resolver for paths whose owning disk is unknown.
     *
     * Every model accessor that displays a stored asset (avatar, cover image,
     * logo, blog thumbnail, ...) used to inline one of two patterns:
     *
     *   1. `Storage::disk('public')->url($raw)` — breaks on R2 files
     *   2. `$sm->url($raw, $sm->primaryDriver())` — breaks on legacy public files
     *
     * Both cases produce a 404 for part of the fleet whenever the primary
     * driver changes (public → R2 rollout). This helper probes the primary
     * driver first (the common case) and only walks other drivers when the
     * file actually isn't there — so hot-path reads stay single-request and
     * legacy rows self-heal without a batch migration.
     *
     *   • http(s):// and protocol-relative URLs pass through untouched
     *     (OAuth avatars, externally-hosted assets).
     *   • Leading slash / `storage/` prefixes are stripped so raw keys,
     *     `/storage/foo.jpg`, and `storage/foo.jpg` all resolve.
     *   • When no driver reports the file (e.g. just-uploaded to R2 and cache
     *     is stale), we still return the primary driver URL so newly-uploaded
     *     files display instantly.
     */
    public function resolveUrl(?string $path): string
    {
        if (empty($path)) return '';
        if (preg_match('#^(https?:)?//#i', $path)) return $path;

        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        $primary = $this->primaryDriver();

        try {
            if ($this->exists($path, $primary)) {
                return $this->url($path, $primary);
            }
        } catch (\Throwable) {
            // fall through to sweep
        }

        foreach ($this->availableDrivers() as $disk) {
            if ($disk === $primary || $disk === self::DRIVER_DRIVE) continue;
            try {
                if ($this->exists($path, $disk)) {
                    return $this->url($path, $disk);
                }
            } catch (\Throwable) {
                // keep trying other drivers
            }
        }

        // Nothing existed anywhere — return the primary-driver URL so a just-
        // uploaded object (not yet visible to HEAD) still renders instead of
        // flashing a broken icon.
        return $this->url($path, $primary);
    }

    /**
     * Signed download URL — preferred path for customer downloads.
     *
     * For R2/S3 returns a short-lived presigned URL; customers hit the CDN
     * directly with zero application server bandwidth. For Drive returns
     * the Drive API download URL. For local/public returns the Storage URL.
     *
     * @param  string      $path             Object key
     * @param  string      $disk             Target driver
     * @param  int|null    $ttl              Override TTL in seconds
     * @param  string|null $downloadFilename When set on R2/S3, bakes
     *                                        `Content-Disposition: attachment;
     *                                        filename="..."` into the signed URL
     *                                        so the browser saves with a branded
     *                                        name instead of the raw object key.
     * @return string                        URL or '' on failure
     */
    public function signedUrl(string $path, string $disk, ?int $ttl = null, ?string $downloadFilename = null): string
    {
        if (empty($path)) return '';
        $ttl = $ttl ?? (int) AppSetting::get('storage_signed_url_ttl', 3600);
        $minutes = max(1, intdiv($ttl, 60));

        try {
            if ($disk === self::DRIVER_R2) {
                return $this->r2->generatePresignedUrl($path, $minutes, $downloadFilename);
            }
            if ($disk === self::DRIVER_S3) {
                return $this->s3->generatePresignedUrl($path, $minutes, $downloadFilename);
            }
            if ($disk === self::DRIVER_DRIVE) {
                // Drive files rely on bearer token auth; caller handles that.
                return "https://drive.google.com/uc?id={$path}&export=download";
            }
            // Local — plain public URL
            return Storage::disk('public')->url($path);
        } catch (\Throwable $e) {
            Log::warning('StorageManager::signedUrl failed', [
                'path' => $path, 'disk' => $disk, 'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Best-effort download URL resolver for an EventPhoto.
     *
     * Priority:
     *   1. storage_disk (whichever cloud driver has it) — signed URL
     *   2. Any mirror in storage_mirrors
     *   3. Drive fallback (if enabled + drive_file_id present)
     *   4. Empty string
     *
     * @param  EventPhoto  $photo
     * @param  string      $variant           original | thumbnail | watermarked
     * @param  int|null    $ttl
     * @param  string|null $downloadFilename  Optional branded filename to bake
     *                                         into the signed URL (R2/S3 only)
     * @return array{url:string,disk:string,direct:bool}
     */
    public function resolvePhotoDownload(EventPhoto $photo, string $variant = 'original', ?int $ttl = null, ?string $downloadFilename = null): array
    {
        $pathField = match ($variant) {
            'thumbnail'   => 'thumbnail_path',
            'watermarked' => 'watermarked_path',
            default       => 'original_path',
        };
        $path = $photo->{$pathField};

        // 1) Try the disk currently marked as primary for this photo
        if (!empty($path) && !empty($photo->storage_disk) && $this->driverIsEnabled($photo->storage_disk)) {
            $url = $this->signedUrl($path, $photo->storage_disk, $ttl, $downloadFilename);
            if ($url) {
                return ['url' => $url, 'disk' => $photo->storage_disk, 'direct' => true];
            }
        }

        // 2) Try any mirror
        $mirrors = is_array($photo->storage_mirrors) ? $photo->storage_mirrors : [];
        foreach ($mirrors as $mirrorDisk) {
            if (!$this->driverIsEnabled($mirrorDisk)) continue;
            if ($mirrorDisk === self::DRIVER_DRIVE) continue; // handled below
            $url = $this->signedUrl($path, $mirrorDisk, $ttl, $downloadFilename);
            if ($url) {
                return ['url' => $url, 'disk' => $mirrorDisk, 'direct' => true];
            }
        }

        // 3) Drive fallback
        $driveFallbackOn = AppSetting::get('storage_drive_read_fallback', '1') === '1';
        if ($driveFallbackOn && !empty($photo->drive_file_id)) {
            return [
                'url'    => "https://drive.google.com/uc?id={$photo->drive_file_id}&export=download",
                'disk'   => self::DRIVER_DRIVE,
                'direct' => false, // needs bearer auth, cannot redirect browser blindly
            ];
        }

        return ['url' => '', 'disk' => '', 'direct' => false];
    }

    // ─── Mirror & Copy ─────────────────────────────────────────────────

    /**
     * Copy an object from one driver to another.
     * Returns true if both read + write succeeded.
     */
    public function copyBetweenDrivers(string $path, string $from, string $to): bool
    {
        if ($from === $to) return true;
        if (!$this->driverIsEnabled($from) || !$this->driverIsEnabled($to)) {
            return false;
        }

        try {
            $contents = $this->readFromDriver($from, $path);
            if ($contents === null) return false;
            return $this->putToDriver($to, $path, $contents);
        } catch (\Throwable $e) {
            Log::error('StorageManager::copyBetweenDrivers failed', [
                'path' => $path, 'from' => $from, 'to' => $to, 'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Read raw bytes from a driver.
     */
    public function readFromDriver(string $disk, string $path): ?string
    {
        try {
            if ($disk === self::DRIVER_DRIVE) {
                return null; // Drive reads go through GoogleDriveService with auth
            }
            if (!Storage::disk($disk)->exists($path)) return null;
            return Storage::disk($disk)->get($path);
        } catch (\Throwable $e) {
            Log::warning('StorageManager::readFromDriver failed', [
                'disk' => $disk, 'path' => $path, 'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ─── Delete / Exists ───────────────────────────────────────────────

    public function delete(string $path, ?string $disk = null): bool
    {
        $disk = $disk ?? $this->primaryDriver();

        try {
            if ($disk === self::DRIVER_R2) return $this->r2->delete($path);
            if ($disk === self::DRIVER_S3) return $this->s3->delete($path);
            if ($disk === self::DRIVER_DRIVE) return false; // Drive deletes go through GoogleDriveService
            // Honour the actual disk name when it's a configured Laravel disk
            // (`public`, `private`, etc.) instead of silently forcing 'public'
            // — that was swallowing deletes targeted at the private disk and
            // leaving stale KYC / slip files around.
            return Storage::disk($disk)->delete($path);
        } catch (\Throwable $e) {
            Log::error('StorageManager::delete failed', ['path' => $path, 'disk' => $disk]);
            return false;
        }
    }

    public function exists(string $path, ?string $disk = null): bool
    {
        $disk = $disk ?? $this->primaryDriver();

        try {
            if ($disk === self::DRIVER_R2) return $this->r2->exists($path);
            if ($disk === self::DRIVER_DRIVE) return false;
            return Storage::disk($disk)->exists($path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Smart asset delete — the one replacement call site we want everywhere
     * that used to do `Storage::disk('public')->delete($path)`.
     *
     * Historically, every admin upload (logo / watermark / SEO / cover / avatar)
     * was hard-coded to the local `public` disk. Once R2 went live some rows
     * started living there instead, so the old pattern silently orphaned
     * cloud files on replace. This helper:
     *
     *   1. Ignores empty paths and absolute URLs (social-login avatars, etc.)
     *   2. Deletes from the caller-supplied disk if one is known (the normal
     *      case — the DB knows where the file lives).
     *   3. Falls back to sweeping every enabled driver when the disk is
     *      unknown — cheap and idempotent because missing keys just no-op.
     *
     * Returns the number of drivers that reported a successful delete so
     * callers can log "nothing to delete" vs. "deleted N copies" if they
     * care. Never throws — a cleanup failure must not block the surrounding
     * DB update.
     */
    public function deleteAsset(?string $path, ?string $disk = null): int
    {
        if (empty($path)) return 0;

        // Full URLs are not ours to touch (OAuth avatars, CDN-hosted assets).
        if (preg_match('/^https?:\/\//i', $path)) return 0;

        // Strip any accidental `/storage/` prefix so callers can pass either
        // the raw key or a URL fragment and still hit the right object.
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if ($disk !== null && $disk !== '') {
            try {
                return $this->delete($path, $disk) ? 1 : 0;
            } catch (\Throwable $e) {
                Log::warning('StorageManager::deleteAsset single-disk failed', [
                    'path' => $path, 'disk' => $disk, 'error' => $e->getMessage(),
                ]);
                // Fall through to the sweep so we at least try elsewhere.
            }
        }

        return $this->deleteEverywhere($path);
    }

    // ─── Path Helpers ──────────────────────────────────────────────────

    /**
     * Canonical per-entity path resolver.
     *
     * One central rule-set so we never mix slip photos with event covers or
     * bury admin avatars under the same folder as customer avatars. Every
     * upload call site should route through this method so the resulting
     * folder tree stays predictable and easy to purge on cascade delete.
     *
     * Layout:
     *
     *   events/{id}/cover/{filename}
     *   events/{id}/photos/original/{filename}
     *   events/{id}/photos/thumbnails/{filename}
     *   events/{id}/photos/watermarked/{filename}
     *   users/{id}/{avatar|...}/{filename}
     *   customers/{id}/{avatar|...}/{filename}
     *   photographers/{id}/{profile|portfolio|docs|...}/{filename}
     *   admins/{id}/{avatar|...}/{filename}
     *   orders/{id}/slips/{filename}                           (payment slips)
     *   digital-products/{id}/{cover|gallery|files}/{filename}
     *   blog/posts/{id}/{featured|og}/{filename}
     *   blog/affiliates/{id}/{filename}
     *   seo/{og|favicon|...}/{filename}
     *   system/{watermark|misc|...}/{filename}
     *
     * @param  string           $entity   events|users|customers|photographers|admins|orders|digital-products|blog|seo|system
     * @param  int|string|null  $id       Owning record id; pass null for global/system assets
     * @param  string           $variant  Sub-folder under the entity (cover, avatar, slip, ...)
     * @param  string|null      $filename Defaults to a UUID with .jpg extension
     */
    public function pathFor(string $entity, int|string|null $id, string $variant, ?string $filename = null): string
    {
        $name = $filename ?: (Str::uuid() . '.jpg');
        $id   = $id !== null ? (string) $id : null;

        // Slug both entity + variant so typos / casing never create sibling
        // folders like "Events/1/Cover" next to "events/1/cover".
        $entity  = strtolower(trim($entity));
        $variant = strtolower(trim($variant));

        return match ($entity) {
            'events' => match ($variant) {
                'cover'       => "events/{$id}/cover/{$name}",
                'original'    => "events/{$id}/photos/original/{$name}",
                'thumbnail'   => "events/{$id}/photos/thumbnails/{$name}",
                'watermarked' => "events/{$id}/photos/watermarked/{$name}",
                default       => "events/{$id}/{$variant}/{$name}",
            },
            'users'         => "users/{$id}/{$variant}/{$name}",
            'customers'     => "customers/{$id}/{$variant}/{$name}",
            'photographers' => "photographers/{$id}/{$variant}/{$name}",
            'admins'        => "admins/{$id}/{$variant}/{$name}",
            'orders' => match ($variant) {
                'slip'  => "orders/{$id}/slips/{$name}",
                default => "orders/{$id}/{$variant}/{$name}",
            },
            'digital-products', 'products' => match ($variant) {
                'cover'   => "digital-products/{$id}/cover/{$name}",
                'gallery' => "digital-products/{$id}/gallery/{$name}",
                'file'    => "digital-products/{$id}/files/{$name}",
                default   => "digital-products/{$id}/{$variant}/{$name}",
            },
            'blog' => match ($variant) {
                'featured'  => "blog/posts/{$id}/featured/{$name}",
                'og'        => "blog/posts/{$id}/og/{$name}",
                'affiliate' => "blog/affiliates/{$id}/{$name}",
                default     => "blog/{$variant}/{$name}",
            },
            'seo'    => "seo/{$variant}/{$name}",
            'system' => "system/{$variant}/{$name}",
            default  => "misc/{$entity}/{$variant}/{$name}",
        };
    }

    /**
     * Folder-only variant of pathFor() — the directory a caller should pass
     * to `$file->store(...)` or `ImageProcessorService::processUpload()`.
     * Equivalent to `dirname(pathFor(..., 'x.jpg'))` but expressed directly
     * so we don't generate a throwaway filename.
     */
    public function directoryFor(string $entity, int|string|null $id, string $variant): string
    {
        return dirname($this->pathFor($entity, $id, $variant, 'placeholder.jpg'));
    }

    /**
     * Directory that owns every file for a single entity record.
     * Used by cascade-delete logic to purge a whole tree at once.
     */
    public function entityDirectory(string $entity, int|string $id): string
    {
        $entity = strtolower(trim($entity));
        return match ($entity) {
            'digital-products', 'products' => "digital-products/{$id}",
            'blog'                         => "blog/posts/{$id}",
            default                        => "{$entity}/{$id}",
        };
    }

    /**
     * Back-compat convenience wrapper returning the three photo paths at once.
     * Internally delegates to pathFor() so the folder schema stays consistent.
     */
    public function photoPaths(int $eventId, ?string $filename = null, ?string $ext = 'jpg'): array
    {
        $name = $filename ?? (Str::uuid() . '.' . $ext);

        return [
            'filename'         => $name,
            'original_path'    => $this->pathFor('events', $eventId, 'original', $name),
            'thumbnail_path'   => $this->pathFor('events', $eventId, 'thumbnail', $name),
            'watermarked_path' => $this->pathFor('events', $eventId, 'watermarked', $name),
        ];
    }

    // ─── Bulk / Cascade Delete Helpers ─────────────────────────────────

    /**
     * Delete a list of paths on one disk. Missing / unreadable entries are
     * silently skipped — the goal is to free whatever storage we can without
     * blocking the surrounding database delete.
     */
    public function deleteMany(array $paths, ?string $disk = null): int
    {
        $disk = $disk ?? $this->primaryDriver();
        $count = 0;
        foreach (array_filter($paths) as $path) {
            try {
                if ($this->delete($path, $disk)) {
                    $count++;
                }
            } catch (\Throwable $e) {
                // Already logged in delete(); keep iterating.
            }
        }
        return $count;
    }

    /**
     * Delete a path on every enabled driver — covers the case where a photo
     * was written to primary + mirrors and we want the delete to sweep all
     * copies. Safe to call for paths that may not exist everywhere.
     */
    public function deleteEverywhere(string $path): int
    {
        $count = 0;
        foreach ($this->availableDrivers() as $disk) {
            if ($disk === self::DRIVER_DRIVE) continue; // Drive handled by GoogleDriveService
            try {
                if ($this->delete($path, $disk)) $count++;
            } catch (\Throwable) {
                // ignore per-driver errors; keep trying the rest
            }
        }
        return $count;
    }

    /**
     * Recursively purge every file under a directory across all enabled
     * drivers. Used by cascade-delete hooks to wipe a whole entity folder
     * (e.g. when an Event is deleted, nuke `events/{id}` tree on R2 + public).
     */
    public function purgeDirectory(string $directory): int
    {
        $directory = trim($directory, '/');
        if ($directory === '') return 0;

        $count = 0;
        foreach ($this->availableDrivers() as $disk) {
            if ($disk === self::DRIVER_DRIVE) continue;
            try {
                // R2/S3 services usually expose deleteDirectory; fall back
                // to Storage facade walk for everything else.
                if ($disk === self::DRIVER_R2 && method_exists($this->r2, 'deleteDirectory')) {
                    if ($this->r2->deleteDirectory($directory)) $count++;
                    continue;
                }
                if ($disk === self::DRIVER_S3 && method_exists($this->s3, 'deleteDirectory')) {
                    if ($this->s3->deleteDirectory($directory)) $count++;
                    continue;
                }
                $files = Storage::disk($disk)->allFiles($directory);
                foreach ($files as $f) {
                    if (Storage::disk($disk)->delete($f)) $count++;
                }
                Storage::disk($disk)->deleteDirectory($directory);
            } catch (\Throwable $e) {
                Log::warning("StorageManager::purgeDirectory failed on [{$disk}]", [
                    'directory' => $directory, 'error' => $e->getMessage(),
                ]);
            }
        }
        return $count;
    }

    // ─── Health ────────────────────────────────────────────────────────

    /**
     * Driver health snapshot — used by admin UI.
     *
     * @return array<string,array{enabled:bool,ok:bool,detail:string}>
     */
    public function health(): array
    {
        $out = [];

        foreach ([self::DRIVER_R2, self::DRIVER_S3, self::DRIVER_DRIVE, self::DRIVER_PUBLIC] as $d) {
            $enabled = $this->driverIsEnabled($d);
            $probe = ['ok' => false, 'reason' => $enabled ? 'not probed' : 'disabled'];

            if ($enabled) {
                if ($d === self::DRIVER_R2) $probe = $this->r2->healthCheck();
                elseif ($d === self::DRIVER_S3) $probe = $this->s3->healthCheck();
                elseif ($d === self::DRIVER_PUBLIC) $probe = ['ok' => true, 'reason' => 'local filesystem'];
                elseif ($d === self::DRIVER_DRIVE) {
                    try {
                        app(GoogleDriveService::class);
                        $probe = ['ok' => true, 'reason' => 'service loaded'];
                    } catch (\Throwable $e) {
                        $probe = ['ok' => false, 'reason' => $e->getMessage()];
                    }
                }
            }

            $out[$d] = [
                'enabled' => $enabled,
                'ok'      => (bool) ($probe['ok'] ?? false),
                'detail'  => (string) ($probe['reason'] ?? $probe['probe'] ?? ''),
            ];
        }

        return $out;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EventPhoto extends Model
{
    protected $table = 'event_photos';

    protected $fillable = [
        'event_id', 'uploaded_by', 'source',
        'filename', 'original_filename', 'mime_type', 'file_size', 'width', 'height',
        'storage_disk', 'storage_mirrors', 'last_mirror_check',
        'original_path', 'thumbnail_path', 'watermarked_path',
        'rekognition_face_id',
        'drive_file_id', 'thumbnail_link',
        'sort_order', 'status',
        // Upload-integrity columns (added 2026-04-28). content_hash dedupes
        // identical bytes; idempotency_key dedupes retried POSTs.
        'content_hash', 'idempotency_key',
        // Moderation columns are managed by ModeratePhotoJob + admin actions,
        // not by user-facing code; leaving them out of $fillable would work,
        // but including them lets admin controllers use update() cleanly.
        'moderation_status', 'moderation_score', 'moderation_labels',
        'moderation_reviewed_by', 'moderation_reviewed_at', 'moderation_reject_reason',
        // Quality ranking (System 9)
        'quality_score', 'rank_position', 'quality_signals', 'quality_scored_at',
    ];

    protected $casts = [
        'file_size'              => 'integer',
        'width'                  => 'integer',
        'height'                 => 'integer',
        'sort_order'             => 'integer',
        'storage_mirrors'        => 'array',
        'last_mirror_check'      => 'datetime',
        'moderation_score'       => 'decimal:2',
        'moderation_labels'      => 'array',
        'moderation_reviewed_at' => 'datetime',
        // Quality ranking
        'quality_score'          => 'decimal:2',
        'rank_position'          => 'integer',
        'quality_signals'        => 'array',
        'quality_scored_at'      => 'datetime',
    ];

    /**
     * Model lifecycle hooks.
     *
     * When a photo is deleted from the database we also try to remove its face
     * from the corresponding AWS Rekognition collection so we don't pay for
     * abandoned face metadata. Failures are logged but never prevent the
     * delete — AWS reachability must not block routine admin actions.
     */
    protected static function booted(): void
    {
        // ── Gallery cache invalidation ───────────────────────────────
        // The public `/api/drive/{eventId}` endpoint caches its payload for
        // `photo_gallery_cache_seconds` so hot events don't re-query the DB
        // on every visitor. Any write to a photo invalidates that cache so
        // new uploads / deletes / status changes show up immediately without
        // waiting for the TTL.
        $invalidate = function (self $photo) {
            if (!empty($photo->event_id)) {
                \Illuminate\Support\Facades\Cache::forget('gallery_photos_v1_' . $photo->event_id);
            }
        };
        static::saved($invalidate);
        static::deleted($invalidate);

        static::deleting(function (self $photo) {
            // 1) Rekognition cleanup — unchanged from before.
            if (!empty($photo->rekognition_face_id) && !empty($photo->event_id)) {
                try {
                    app(\App\Services\FaceSearchService::class)
                        ->deleteFace('event-' . $photo->event_id, $photo->rekognition_face_id);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        "EventPhoto#{$photo->id} delete: Rekognition cleanup skipped: " . $e->getMessage()
                    );
                }
            }

            // 2) Physical file cleanup — we keep three derivatives per photo
            //    (original / thumbnail / watermarked). Delete all of them on
            //    whichever disk the row said it lives on plus every mirror so
            //    abandoned bytes never pile up. For cascade deletes via the
            //    FK (`event_events` ON DELETE CASCADE → event_photos), this
            //    hook won't fire — Event::deleting already calls
            //    StorageManager::purgeDirectory() to wipe the whole
            //    events/{id} tree, so we're covered in both directions.
            $paths = array_filter([
                $photo->original_path,
                $photo->thumbnail_path,
                $photo->watermarked_path,
            ]);
            if (empty($paths)) {
                return;
            }

            try {
                $storage = app(\App\Services\StorageManager::class);

                // Primary disk first
                if (!empty($photo->storage_disk)) {
                    $storage->deleteMany($paths, $photo->storage_disk);
                }

                // Mirrors — ignore failures, they may not hold every copy
                $mirrors = is_array($photo->storage_mirrors) ? $photo->storage_mirrors : [];
                foreach ($mirrors as $mirrorDisk) {
                    if ($mirrorDisk === $photo->storage_disk) continue;
                    $storage->deleteMany($paths, $mirrorDisk);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    "EventPhoto#{$photo->id} file cleanup failed: " . $e->getMessage()
                );
            }
        });

        // New uploads enter the moderation pipeline automatically. We only
        // dispatch when the row is first created with no prior moderation
        // status — edits/recreates don't re-moderate unless the admin runs
        // `photos:remoderate` explicitly. Guarded by app setting so tests
        // and unconfigured environments don't enqueue dead jobs.
        static::created(function (self $photo) {
            if (!empty($photo->moderation_status) && $photo->moderation_status !== 'pending') {
                return;
            }
            if (\App\Models\AppSetting::get('moderation_enabled', '1') !== '1') {
                return;
            }
            try {
                \App\Jobs\ModeratePhotoJob::dispatch($photo->id)->afterCommit();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    "EventPhoto#{$photo->id} moderation dispatch failed: " . $e->getMessage()
                );
            }
        });
    }

    // ── Moderation Scopes ──

    public function scopeFlaggedForReview($q)
    {
        return $q->where('moderation_status', 'flagged');
    }

    public function scopePendingModeration($q)
    {
        return $q->where('moderation_status', 'pending');
    }

    public function scopeRejected($q)
    {
        return $q->where('moderation_status', 'rejected');
    }

    /** Photos the public should actually see: active + not blocked by moderation. */
    public function scopeVisibleToPublic($q)
    {
        return $q->where('status', 'active')
                 ->whereIn('moderation_status', ['approved', 'skipped']);
    }

    // ── Relationships ──

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function uploader()
    {
        // `uploaded_by` references auth_users.id — the User model (table=auth_users)
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }

    // ── Scopes ──

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeUploaded($q)
    {
        return $q->where('source', 'upload');
    }

    public function scopeFromDrive($q)
    {
        return $q->where('source', 'drive');
    }

    // ── Accessors ──

    public function getOriginalUrlAttribute(): string
    {
        if ($this->storage_disk === 'r2') {
            return app(\App\Services\Cloudflare\R2StorageService::class)->getUrl($this->original_path);
        }
        if ($this->storage_disk === 's3') {
            return app(\App\Services\Aws\S3StorageService::class)->getUrl($this->original_path);
        }
        return Storage::disk('public')->url($this->original_path);
    }

    public function getThumbnailUrlAttribute(): string
    {
        if ($this->thumbnail_link) {
            return $this->thumbnail_link;
        }
        if (!$this->thumbnail_path) {
            // SECURITY: previously fell through to $this->original_url —
            // a full-size, un-watermarked copy. If the upload pipeline
            // failed or the row was inserted before processing finished,
            // the gallery silently exposed the raw original to anyone
            // who could see the page. Returning '' forces consumers to
            // route through DriveController::proxyImage, which applies
            // an inline watermark when the baked variant is missing
            // (see proxyImage size-≤500 branch).
            return '';
        }
        if ($this->storage_disk === 'r2') {
            return app(\App\Services\Cloudflare\R2StorageService::class)->getUrl($this->thumbnail_path);
        }
        if ($this->storage_disk === 's3') {
            return app(\App\Services\Aws\S3StorageService::class)->getUrl($this->thumbnail_path);
        }
        return Storage::disk('public')->url($this->thumbnail_path);
    }

    public function getWatermarkedUrlAttribute(): string
    {
        if (!$this->watermarked_path) {
            // SECURITY: same defense as getThumbnailUrlAttribute —
            // never leak the un-watermarked original through this
            // accessor, which feeds the lightbox and face-search
            // result previews. Empty string sends consumers to the
            // proxy, which composites a watermark inline.
            return '';
        }
        if ($this->storage_disk === 'r2') {
            return app(\App\Services\Cloudflare\R2StorageService::class)->getUrl($this->watermarked_path);
        }
        if ($this->storage_disk === 's3') {
            return app(\App\Services\Aws\S3StorageService::class)->getUrl($this->watermarked_path);
        }
        return Storage::disk('public')->url($this->watermarked_path);
    }

    /**
     * Resolve the best customer-download URL using the multi-driver
     * StorageManager. Returns a short-lived signed URL on R2/S3, or the
     * Drive download URL as a fallback.
     *
     * @param  string    $variant  original | thumbnail | watermarked
     * @param  int|null  $ttl      Override TTL (seconds)
     * @return array{url:string,disk:string,direct:bool}
     */
    public function downloadUrl(string $variant = 'original', ?int $ttl = null): array
    {
        return app(\App\Services\StorageManager::class)
            ->resolvePhotoDownload($this, $variant, $ttl);
    }

    /**
     * True when this photo has a usable cloud copy (R2/S3 on primary or mirror).
     */
    public function hasCloudCopy(): bool
    {
        if (in_array($this->storage_disk, ['r2', 's3'], true)) return true;
        $mirrors = is_array($this->storage_mirrors) ? $this->storage_mirrors : [];
        return (bool) array_intersect($mirrors, ['r2', 's3']);
    }

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}

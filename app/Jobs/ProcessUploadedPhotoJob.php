<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\EventPhoto;
use App\Services\FaceSearchService;
use App\Services\ImageProcessorService;
use App\Services\StorageManager;
use App\Services\WatermarkService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Process an uploaded photo asynchronously:
 *   1) Download original from storage to temp
 *   2) Optionally re-encode original (photo_compress_* admin settings)
 *   3) Generate thumbnail (photo_thumbnail_size, photo_thumbnail_quality)
 *   4) Apply watermark + resize preview (photo_preview_max, photo_preview_quality)
 *   5) Upload thumbnail & watermarked to storage
 *   6) Update EventPhoto status → 'active'
 *
 * All sizes/qualities are admin-configurable via /admin/settings/photo-performance.
 * Used for Case B (Direct Upload) in the Hybrid Architecture.
 */
class ProcessUploadedPhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300; // 5 minutes per photo

    public int $backoff = 30;  // retry after 30s

    public function __construct(
        public int $photoId
    ) {}

    public function handle(): void
    {
        $photo = EventPhoto::find($this->photoId);

        if (!$photo) {
            return;
        }

        if ($photo->status === 'deleted') {
            Log::info("ProcessUploadedPhotoJob: photo #{$this->photoId} is deleted, skipping.");
            return;
        }

        // Critical correctness fix: previously this branch bailed whenever
        // status === 'active', on the assumption that "active = processing
        // succeeded". That's wrong for photos whose row was inserted via a
        // pre-pipeline path (older code paths, manual SQL inserts, half-
        // completed migrations) — they end up active=true but with NULL
        // thumbnail_path/watermarked_path, and the previous early-return
        // meant ReprocessPhotosCommand + the proxy's auto-reprocess
        // dispatch (DriveController.php) silently became no-ops.
        //
        // Now: only short-circuit when status='active' AND BOTH variants
        // are already generated. If either is missing, fall through to
        // the rest of the handler and bake them.
        if ($photo->status === 'active'
            && !empty($photo->thumbnail_path)
            && !empty($photo->watermarked_path)) {
            return; // truly processed — nothing to do
        }

        $storage  = app(StorageManager::class);
        $imgProc  = new ImageProcessorService();
        $tmpFiles = [];

        try {
            // ─── Read admin-configured pipeline settings ───────────────
            // Wrapped in max()/min() so a corrupt or blank setting can never
            // cause GD to throw — falls back to sane defaults on every read.
            $compressEnabled = (string) AppSetting::get('photo_compress_enabled',   '1') === '1';
            $thumbSize       = max(100, min(800,  (int) AppSetting::get('photo_thumbnail_size',    400)));
            $thumbQuality    = max(50,  min(100,  (int) AppSetting::get('photo_thumbnail_quality', 75)));
            $previewMax      = max(600, min(4000, (int) AppSetting::get('photo_preview_max',       1600)));
            $previewQuality  = max(50,  min(100,  (int) AppSetting::get('photo_preview_quality',   82)));

            // 1) Download original to temp file
            $originalContents = $this->downloadOriginal($photo);
            $tmpOriginal = tempnam(sys_get_temp_dir(), 'photo_orig_');
            file_put_contents($tmpOriginal, $originalContents);
            $tmpFiles[] = $tmpOriginal;

            // 2) Optionally compress + re-encode the original in-place.
            // Only writes back to storage when the re-encoded file is
            // actually smaller — avoids upscaling artifacts or wasting a
            // write when the source was already well-compressed.
            if ($compressEnabled) {
                $result = $imgProc->compressOriginal($tmpOriginal);
                if (!empty($result['compressed']) && strlen($result['bytes']) > 0 && strlen($result['bytes']) < strlen($originalContents)) {
                    $storage->put($photo->original_path, $result['bytes']);
                    // Rewrite temp so downstream thumb/preview use the smaller image
                    file_put_contents($tmpOriginal, $result['bytes']);
                    $photo->file_size = strlen($result['bytes']);
                    Log::info("ProcessUploadedPhotoJob: photo #{$this->photoId} re-encoded", [
                        'before' => strlen($originalContents),
                        'after'  => strlen($result['bytes']),
                        'saved'  => strlen($originalContents) - strlen($result['bytes']),
                    ]);
                }
            }

            $watermark = new WatermarkService();
            $watermarkEnabled = $watermark->isEnabled();

            // 3) Generate thumbnail (admin-configured size + quality).
            //    When admin has watermarking on, we ALSO composite a
            //    watermark onto the small thumbnail. Previously thumbs
            //    were left clean — buyers could right-click any gallery
            //    card (id="gallery-grid" img) and save a free 400px copy
            //    of the photo, completely undermining the watermark on
            //    the larger preview. Watermarking the thumb itself
            //    closes that hole. The GD apply() call on a 400px JPEG
            //    is sub-50ms so this doesn't slow the pipeline.
            $thumbData = $imgProc->thumbnailWithQuality($tmpOriginal, $thumbSize, $thumbQuality);
            if ($thumbData && $photo->thumbnail_path) {
                if ($watermarkEnabled) {
                    $tmpThumb = tempnam(sys_get_temp_dir(), 'photo_thumb_');
                    file_put_contents($tmpThumb, $thumbData);
                    $tmpFiles[] = $tmpThumb;
                    $thumbWatermarked = $watermark->apply($tmpThumb);
                    // apply() returns the original bytes on any GD
                    // failure path so we never end up with an empty
                    // file — but be defensive and only swap when we
                    // actually got non-empty bytes back.
                    if (!empty($thumbWatermarked)) {
                        $thumbData = $thumbWatermarked;
                    }
                }
                $storage->put($photo->thumbnail_path, $thumbData);
            }

            // 4) Generate watermarked preview (admin-configured preview size + quality).
            //    Same pipeline as before — apply watermark to original,
            //    then resize to preview dimensions. Preserved verbatim
            //    so the lightbox / face-search-result behaviour doesn't
            //    change.
            if ($watermarkEnabled) {
                $wmData = $watermark->apply($tmpOriginal);
                $tmpWm = tempnam(sys_get_temp_dir(), 'photo_wm_');
                file_put_contents($tmpWm, $wmData);
                $tmpFiles[] = $tmpWm;

                $wmResized = $imgProc->resizeWithQuality($tmpWm, $previewMax, $previewMax, $previewQuality);
                if ($wmResized && $photo->watermarked_path) {
                    $storage->put($photo->watermarked_path, $wmResized);
                }
            } else {
                $previewData = $imgProc->resizeWithQuality($tmpOriginal, $previewMax, $previewMax, $previewQuality);
                if ($previewData && $photo->watermarked_path) {
                    $storage->put($photo->watermarked_path, $previewData);
                }
            }

            // 5) Get dimensions if not set
            if (!$photo->width || !$photo->height) {
                $dims = $imgProc->getDimensions($tmpOriginal);
                $photo->width  = $dims['width']  ?? 0;
                $photo->height = $dims['height'] ?? 0;
            }

            // 6) Mark as active
            $photo->status = 'active';
            $photo->save();

            // 7) Index face into AWS Rekognition collection (best-effort, non-fatal).
            //    Silently skips when AWS is not configured or the photo contains no face.
            //    Any failure is logged but never fails the surrounding job — image
            //    processing has already succeeded by this point and must be preserved.
            try {
                app(FaceSearchService::class)->indexPhoto($photo, $tmpOriginal);
            } catch (\Throwable $e) {
                Log::warning("ProcessUploadedPhotoJob: face indexing skipped for photo #{$this->photoId}", [
                    'error' => $e->getMessage(),
                ]);
            }

            // 8) Queue moderation scan. The EventPhoto::created hook already
            //    fires one, but that runs before the image was moved to final
            //    storage on cloud-backed setups — dispatching again here is
            //    idempotent (the job reads current DB state) and makes sure
            //    the scan happens AFTER the final original is in place.
            try {
                if ((string) AppSetting::get('moderation_enabled', '1') === '1') {
                    \App\Jobs\ModeratePhotoJob::dispatch($photo->id);
                }
            } catch (\Throwable $e) {
                Log::warning("ProcessUploadedPhotoJob: moderation dispatch skipped for photo #{$this->photoId}", [
                    'error' => $e->getMessage(),
                ]);
            }

            // 9) Auto-apply default Lightroom preset if the uploading
            //    photographer has one set. Best-effort — failure here
            //    must not roll back the upload (the original is already
            //    saved + thumbnailed). The PresetService writes a sibling
            //    file at <originalDir>/preset/<filename>.jpg and stamps
            //    event_photos.preset_applied_path so the gallery can
            //    serve the toned version when present.
            try {
                $event = $photo->event;
                if ($event && $event->photographer_id) {
                    $profile = \App\Models\PhotographerProfile::where('user_id', $event->photographer_id)->first();
                    if ($profile && $profile->default_preset_id) {
                        $subs = app(\App\Services\SubscriptionService::class);
                        if ($subs->canAccessFeature($profile, 'presets')) {
                            $preset = \App\Models\PhotographerPreset::active()
                                ->forPhotographer($profile->user_id)
                                ->find($profile->default_preset_id);
                            if ($preset) {
                                app(\App\Services\PresetService::class)->applyTo($photo, $preset);
                                Log::info("ProcessUploadedPhotoJob: applied default preset '{$preset->name}' to photo #{$this->photoId}");
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("ProcessUploadedPhotoJob: preset auto-apply skipped for photo #{$this->photoId}", [
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info("ProcessUploadedPhotoJob: photo #{$this->photoId} processed successfully.");

        } catch (\Throwable $e) {
            Log::error("ProcessUploadedPhotoJob: photo #{$this->photoId} failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark as failed if all retries exhausted
            if ($this->attempts() >= $this->tries) {
                $photo->update([
                    'status' => 'failed',
                ]);
            }

            throw $e; // Let Laravel handle retry
        } finally {
            // Cleanup temp files
            foreach ($tmpFiles as $tmp) {
                @unlink($tmp);
            }
        }
    }

    /**
     * Download the original photo content from whichever disk it's on.
     */
    private function downloadOriginal(EventPhoto $photo): string
    {
        $disk = $photo->storage_disk ?? 'public';
        $path = $photo->original_path;

        $contents = Storage::disk($disk)->get($path);

        if ($contents === null || $contents === false) {
            throw new \RuntimeException("Cannot read original file: {$disk}:{$path}");
        }

        return $contents;
    }

    /**
     * Handle permanent failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessUploadedPhotoJob permanently failed for photo #{$this->photoId}", [
            'error' => $exception->getMessage(),
        ]);

        try {
            EventPhoto::where('id', $this->photoId)->update(['status' => 'failed']);
        } catch (\Throwable) {
            // Silently ignore
        }
    }
}

<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Photographer\UploadPhotoRequest;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Jobs\ProcessUploadedPhotoJob;
use App\Services\ImageProcessorService;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\R2MediaService;
use App\Services\StorageManager;
use App\Services\WatermarkService;
use App\Services\Aws\S3StorageService;
use App\Services\Cloudflare\R2StorageService;
use App\Support\PlanResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoController extends Controller
{
    /**
     * Show photo management page for an event.
     *
     * Supports three URL params the redesigned UI needs:
     *   • `q`      — filename substring (case-insensitive)
     *   • `status` — all | active | processing | failed
     *   • `sort`   — order|newest|oldest|name|size
     * Stats are computed in a single aggregated pass so the header cards
     * don't cost one query per status bucket.
     */
    public function index(Request $request, Event $event)
    {
        $this->authorizeEvent($event);

        $q      = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $sort   = (string) $request->query('sort', 'order');

        $query = $event->photos()->where('status', '!=', 'deleted');

        if ($status !== 'all' && in_array($status, ['active', 'processing', 'failed'], true)) {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $query->where('original_filename', 'ilike', '%' . $q . '%');
        }

        // Sort presets map to ORDER BY clauses. `order` matches the manual
        // sort_order used by the drag-reorder UI; the others are natural
        // axes a photographer typically filters by.
        switch ($sort) {
            case 'newest': $query->orderByDesc('created_at'); break;
            case 'oldest': $query->orderBy('created_at');     break;
            case 'name':   $query->orderBy('original_filename'); break;
            case 'size':   $query->orderByDesc('file_size');  break;
            case 'order':
            default:       $query->orderBy('sort_order')->orderByDesc('created_at');
        }

        $photos = $query->paginate(60)->withQueryString();

        // Aggregated stats — one query returns every counter we need so the
        // stats cards don't add one DB round trip per bucket.
        // Postgres-correct: use FILTER + single quotes (double quotes in PG
        // mean identifier, not string literal).
        $statsRow = $event->photos()
            ->where('status', '!=', 'deleted')
            ->selectRaw("
                COUNT(*)                                  as total,
                COUNT(*) FILTER (WHERE status = 'active')     as active,
                COUNT(*) FILTER (WHERE status = 'processing') as processing,
                COUNT(*) FILTER (WHERE status = 'failed')     as failed,
                COALESCE(SUM(file_size), 0)               as size_bytes
            ")
            ->first();

        $stats = [
            'total'      => (int) ($statsRow->total      ?? 0),
            'active'     => (int) ($statsRow->active     ?? 0),
            'processing' => (int) ($statsRow->processing ?? 0),
            'failed'     => (int) ($statsRow->failed     ?? 0),
            'size_bytes' => (int) ($statsRow->size_bytes ?? 0),
        ];

        return view('photographer.photos.index', compact(
            'event', 'photos', 'stats', 'q', 'status', 'sort'
        ));
    }

    /**
     * Show upload page.
     */
    public function create(Event $event)
    {
        $this->authorizeEvent($event);

        $photoCount = $event->photos()->where('status', 'active')->count();

        return view('photographer.photos.upload', compact('event', 'photoCount'));
    }

    /**
     * Handle AJAX file upload (single file per request for progress tracking).
     *
     * Architecture: Upload original → create record with status='processing'
     *   → dispatch ProcessUploadedPhotoJob → return immediately.
     * The queue job handles thumbnail/watermark generation asynchronously.
     *
     * For small files or when queue is 'sync', processing happens inline.
     */
    /**
     * Cross-driver unique-constraint detection. Postgres raises SQLSTATE
     * 23505; MySQL raises 23000 with errno 1062; SQLite raises 23000 with
     * "UNIQUE constraint failed". We don't want to reach into the SQLSTATE
     * directly because the Laravel exception wraps the PDOException
     * differently per driver, so we string-match the message — a small
     * compromise for portability.
     */
    private function isUniqueViolation(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        return $e->getCode() === '23505'                 // pgsql
            || $e->getCode() === '23000'                 // mysql/sqlite SQLSTATE
            || str_contains($msg, 'duplicate')
            || str_contains($msg, 'unique constraint');
    }

    public function store(UploadPhotoRequest $request, Event $event, R2MediaService $media)
    {
        // Authorization + validation handled by UploadPhotoRequest.
        $file             = $request->file('photo');
        $originalFilename = $file->getClientOriginalName();

        // ── Idempotency: client may pass a UUID via header or body ─────
        // Header is preferred (RFC-style "Idempotency-Key"); body field is
        // a fallback for clients that can't easily set headers (some
        // mobile uploaders). If a row already exists with this key for
        // this event, return it instead of creating a duplicate. The
        // unique partial index uniq_event_photos_idempotency enforces
        // this at the DB level too, but checking here lets us return 200
        // (cached response) instead of catching a constraint violation.
        $idempotencyKey = $request->header('Idempotency-Key')
            ?: (string) $request->input('idempotency_key', '');
        $idempotencyKey = trim($idempotencyKey) ?: null;

        if ($idempotencyKey) {
            $existing = EventPhoto::where('event_id', $event->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return response()->json([
                    'success'  => true,
                    'replayed' => true,
                    'photo'    => [
                        'id'              => $existing->id,
                        'filename'        => $existing->original_filename,
                        'thumbnail_url'   => $existing->status === 'active' ? $existing->thumbnail_url : null,
                        'file_size_human' => $existing->file_size_human,
                        'width'           => $existing->width,
                        'height'          => $existing->height,
                        'status'          => $existing->status,
                    ],
                ]);
            }
        }

        // ── Content hash: dedupe identical bytes across retries ────────
        // Computed once on disk before the bytes leave the worker. SHA-256
        // is overkill collision-wise but cheap (~150 MB/s on a single
        // core). The unique partial index drops dupes at the DB layer too.
        $contentHash = hash_file('sha256', $file->getRealPath());

        $hashDuplicate = EventPhoto::where('event_id', $event->id)
            ->where('content_hash', $contentHash)
            ->where('status', '!=', 'deleted')
            ->first();
        if ($hashDuplicate) {
            return response()->json([
                'success'        => true,
                'duplicate_hash' => true,
                'photo'          => [
                    'id'              => $hashDuplicate->id,
                    'filename'        => $hashDuplicate->original_filename,
                    'thumbnail_url'   => $hashDuplicate->status === 'active' ? $hashDuplicate->thumbnail_url : null,
                    'file_size_human' => $hashDuplicate->file_size_human,
                    'width'           => $hashDuplicate->width,
                    'height'          => $hashDuplicate->height,
                    'status'          => $hashDuplicate->status,
                ],
            ]);
        }

        // Image dimensions while the file is still local (R2 upload moves it).
        $imgProcessor = new ImageProcessorService();
        $dims = $imgProcessor->getDimensions($file->getRealPath());

        try {
            // Step 1: Upload to R2 ONLY. Path is enforced by R2MediaService:
            //   events/photos/user_{photographer_id}/event_{event_id}/{uuid}_{name}.{ext}
            // Note: photographer_id == photographer profile id, not user id.
            // The fork's domain treats the photographer profile as the upload owner.
            $photographerId = (int) $event->photographer_id;
            $upload = $media->uploadEventPhoto($photographerId, (int) $event->id, $file);

            // Step 2: Create the photo record. We store the R2 KEY (not URL) so
            // that read-time URL signing/CDN swap-out is one place to change.
            // Derivative paths (thumbnail/watermarked) are filled in by the
            // async job once it produces them on R2.
            $maxSort = $event->photos()->max('sort_order') ?? 0;

            try {
                $photo = EventPhoto::create([
                    'event_id'          => $event->id,
                    'uploaded_by'       => Auth::id(),
                    'source'            => 'upload',
                    'filename'          => basename($upload->key),
                    'original_filename' => $originalFilename,
                    'mime_type'         => $upload->mimeType,
                    'file_size'         => $upload->sizeBytes,
                    'width'             => $dims['width']  ?? 0,
                    'height'            => $dims['height'] ?? 0,
                    'storage_disk'      => $upload->disk,   // always 'r2' under R2-only mode
                    'original_path'     => $upload->key,
                    'thumbnail_path'    => null,            // populated by ProcessUploadedPhotoJob
                    'watermarked_path'  => null,            // populated by ProcessUploadedPhotoJob
                    'sort_order'        => $maxSort + 1,
                    'status'            => 'processing',
                    'content_hash'      => $contentHash,
                    'idempotency_key'   => $idempotencyKey,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Concurrent retry won the race against our pre-check —
                // the unique index fired. Fetch the winner and return it
                // so the client gets the cached result instead of a 500.
                if ($this->isUniqueViolation($e)) {
                    $winner = EventPhoto::where('event_id', $event->id)
                        ->where(function ($q) use ($idempotencyKey, $contentHash) {
                            if ($idempotencyKey) {
                                $q->where('idempotency_key', $idempotencyKey);
                            }
                            $q->orWhere('content_hash', $contentHash);
                        })
                        ->where('status', '!=', 'deleted')
                        ->first();
                    if ($winner) {
                        // Best-effort: also delete the orphaned R2 object
                        // we just uploaded — the winner has its own object.
                        $media->forget($upload->key);
                        return response()->json([
                            'success'      => true,
                            'race_replayed'=> true,
                            'photo'        => [
                                'id'       => $winner->id,
                                'filename' => $winner->original_filename,
                                'status'   => $winner->status,
                            ],
                        ]);
                    }
                }
                throw $e;
            }

            // Step 3: Process the photo (thumbnail + watermark generation).
            //
            // We run inline by default (`dispatchSync`) instead of pushing
            // to a queue lane, because Laravel Cloud apps without an
            // explicit Worker resource don't have a queue runner — async
            // dispatch would leave the photo stuck at status='processing'
            // forever, and the photographer would see "กำลังประมวลผล" with
            // no thumbnail.
            //
            // To opt back into async dispatch (faster upload response,
            // requires a running queue worker), set the AppSetting
            // `photo_processing_async = 1` from /admin/settings.
            $async = \App\Models\AppSetting::get('photo_processing_async', '0') === '1';
            if ($async) {
                $queueLane = app(\App\Services\SubscriptionService::class)
                    ->uploadQueueFor(Auth::user()?->photographerProfile);
                ProcessUploadedPhotoJob::dispatch($photo->id)->onQueue($queueLane);
            } else {
                // Inline path — adds 1-3 seconds per upload (image resize +
                // watermark) but the photographer sees the result
                // immediately. The job has its own try/catch so a
                // single bad image won't fail the whole upload request.
                try {
                    ProcessUploadedPhotoJob::dispatchSync($photo->id);
                } catch (\Throwable $e) {
                    Log::warning('Inline photo processing failed', [
                        'photo_id' => $photo->id,
                        'error'    => $e->getMessage(),
                    ]);
                    // Photo stays at status='processing' — the
                    // `php artisan photos:reprocess` command can retry it
                    // later, and the API still returns success because
                    // the original file did upload to R2.
                }
            }

            // Step 4: Record metered usage so the daily upload quota +
            // lifetime storage quota stay in sync with what's actually on
            // R2. We record AFTER the queue dispatch — a failed upload
            // would have thrown above; getting here means R2 has the file
            // and the DB has the row. Two ledger lines per upload:
            //   • photo.upload  — count, gated by plan_caps.photo.upload
            //   • storage.bytes — bytes, gated by plan_caps.storage.bytes
            $userId   = (int) Auth::id();
            $planCode = PlanResolver::photographerCode(Auth::user());
            \App\Services\Usage\UsageMeter::record(
                userId:    $userId,
                planCode:  $planCode,
                resource:  'photo.upload',
                units:     1,
                metadata:  ['event_id' => $event->id, 'photo_id' => $photo->id],
            );
            \App\Services\Usage\UsageMeter::record(
                userId:    $userId,
                planCode:  $planCode,
                resource:  'storage.bytes',
                units:     (int) $upload->sizeBytes,
                metadata:  ['event_id' => $event->id, 'photo_id' => $photo->id, 'r2_key' => $upload->key],
            );

            // Refresh to get the latest status (might be 'active' if sync driver)
            $photo->refresh();

            return response()->json([
                'success' => true,
                'photo'   => [
                    'id'              => $photo->id,
                    'filename'        => $photo->original_filename,
                    'thumbnail_url'   => $photo->status === 'active'
                                         ? $photo->thumbnail_url
                                         : null,
                    'file_size_human' => $photo->file_size_human,
                    'width'           => $photo->width,
                    'height'          => $photo->height,
                    'status'          => $photo->status,
                ],
            ]);
        } catch (InvalidMediaFileException $e) {
            // User-actionable error — surface the message (it's safe).
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Photo upload failed', [
                'event_id' => $event->id,
                'user_id'  => Auth::id(),
                'file'     => $originalFilename,
                'size'     => $file?->getSize(),
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);

            // Internal failure — don't leak details (could expose bucket name,
            // path, or DB schema). Operator will see full context in logs.
            return response()->json([
                'success' => false,
                'message' => 'อัพโหลดไม่สำเร็จ กรุณาลองใหม่อีกครั้ง',
            ], 500);
        }
    }

    /**
     * Delete a photo.
     *
     * Hard delete — we ROW-remove and let `EventPhoto::deleting` cascade
     * to R2 / S3 / public (+ mirrors) via StorageManager::deleteMany.
     * The previous soft-delete (`status='deleted'`) was leaving the three
     * derivatives (original / thumbnail / watermarked) on Cloudflare R2
     * forever, which burned storage quota with no way for the photographer
     * to free it from the UI. We now delete the row so the model hook
     * actually fires — the hook already knows about the photo's primary
     * disk + every mirror and sweeps them all.
     *
     * Rekognition face metadata, gallery cache, and derivative files are
     * all cleaned up by the same hook. Orders / downloads already captured
     * signed URLs before purchase, so active downloads remain unaffected.
     */
    public function destroy(Event $event, EventPhoto $photo)
    {
        $this->authorizeEvent($event);

        if ($photo->event_id !== $event->id) {
            abort(403);
        }

        // Capture size BEFORE delete — the model is about to vanish.
        $sizeBytes = (int) $photo->file_size;

        try {
            $photo->delete();
        } catch (\Throwable $e) {
            \Log::error('PhotoController::destroy failed', [
                'event_id' => $event->id,
                'photo_id' => $photo->id,
                'error'    => $e->getMessage(),
            ]);

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ลบรูปภาพไม่สำเร็จ: ' . $e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'ลบรูปภาพไม่สำเร็จ');
        }

        // Free up the storage.bytes counter so the photographer's lifetime
        // quota reflects the new on-disk reality. We DON'T reverse
        // photo.upload — the daily cap is a rate-limit, not a balance.
        if ($sizeBytes > 0) {
            $planCode = PlanResolver::photographerCode(Auth::user());
            \App\Services\Usage\UsageMeter::reverse(
                userId:    (int) Auth::id(),
                planCode:  $planCode,
                resource:  'storage.bytes',
                units:     $sizeBytes,
                metadata:  ['event_id' => $event->id, 'photo_id' => $photo->id, 'reason' => 'destroy'],
            );
        }

        // If AJAX request
        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'ลบรูปภาพสำเร็จ');
    }

    /**
     * Bulk delete photos.
     *
     * We fetch each row and call ->delete() one at a time (rather than a
     * mass ->whereIn()->delete()) specifically so the `EventPhoto::deleting`
     * hook fires per row. The hook is what sweeps the three derivatives off
     * R2, so a mass delete would skip storage cleanup and silently leak.
     * Failures per photo are logged + counted, never aborting the batch —
     * one bad row shouldn't prevent the others from freeing their files.
     */
    public function bulkDelete(Request $request, Event $event)
    {
        $this->authorizeEvent($event);

        $ids = $request->input('photo_ids', []);
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'ไม่ได้เลือกรูปภาพ'], 422);
        }

        $photos = EventPhoto::where('event_id', $event->id)
            ->whereIn('id', $ids)
            ->get();

        $deleted        = 0;
        $failed         = 0;
        $reclaimedBytes = 0;
        foreach ($photos as $photo) {
            $size = (int) $photo->file_size;
            try {
                $photo->delete();
                $deleted++;
                $reclaimedBytes += $size;
            } catch (\Throwable $e) {
                $failed++;
                \Log::warning("PhotoController::bulkDelete photo#{$photo->id} failed: " . $e->getMessage());
            }
        }

        // Single bulk reverse for the storage counter — cheaper than one
        // reverse() call per photo (which would do N transactions).
        if ($reclaimedBytes > 0) {
            $planCode = PlanResolver::photographerCode(Auth::user());
            \App\Services\Usage\UsageMeter::reverse(
                userId:    (int) Auth::id(),
                planCode:  $planCode,
                resource:  'storage.bytes',
                units:     $reclaimedBytes,
                metadata:  ['event_id' => $event->id, 'photo_count' => $deleted, 'reason' => 'bulk_destroy'],
            );
        }

        $message = "ลบรูปภาพ {$deleted} รูปสำเร็จ";
        if ($failed > 0) {
            $message .= " (ล้มเหลว {$failed} รูป)";
        }

        return response()->json([
            'success' => $deleted > 0,
            'message' => $message,
            'deleted' => $deleted,
            'failed'  => $failed,
        ]);
    }

    /**
     * Update sort order via AJAX.
     */
    public function reorder(Request $request, Event $event)
    {
        $this->authorizeEvent($event);

        $order = $request->input('order', []);

        foreach ($order as $i => $photoId) {
            EventPhoto::where('id', $photoId)
                ->where('event_id', $event->id)
                ->update(['sort_order' => $i]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Set a photo as the event cover.
     *
     * Previously we stored just the storage key (e.g. "events/3/photos/
     * thumbnails/abc.jpg"). That worked only when every disk was local —
     * once uploads moved to R2 the Event accessor happily prefixed it with
     * `/storage/` which 404s because the file doesn't live on the local
     * disk. We now store the fully-resolved URL straight from the photo's
     * disk-aware accessor so the Event doesn't need to guess later.
     *
     * `thumbnail_url` (and `original_url` as fallback) already knows how
     * to talk to R2 / S3 / public correctly via the photo's storage_disk
     * column, so this is the single source of truth.
     */
    public function setCover(Event $event, EventPhoto $photo)
    {
        $this->authorizeEvent($event);

        if ($photo->event_id !== $event->id) {
            abort(403);
        }

        $url = $photo->thumbnail_url ?: $photo->original_url;

        if (empty($url)) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถสร้าง URL รูปปกได้ — ตรวจสอบการตั้งค่า storage',
            ], 422);
        }

        $event->update(['cover_image' => $url]);

        return response()->json([
            'success'   => true,
            'message'   => 'ตั้งเป็นรูปปกสำเร็จ',
            'cover_url' => $url,
            'photo_id'  => $photo->id,
        ]);
    }

    /**
     * Ensure the photographer owns this event.
     */
    private function authorizeEvent(Event $event): void
    {
        if ((int) $event->photographer_id !== (int) Auth::id()) {
            abort(403, 'คุณไม่มีสิทธิ์จัดการอีเวนต์นี้');
        }
    }
}

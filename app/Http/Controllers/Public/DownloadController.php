<?php
namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Jobs\BuildOrderZipJob;
use App\Models\AppSetting;
use App\Models\DownloadToken;
use App\Models\EventPhoto;
use App\Models\OrderItem;
use App\Services\GoogleDriveService;
use App\Services\StorageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DownloadController extends Controller
{
    /**
     * Build a clean, branded filename slug.
     * e.g. "XPhoto Gallery" → "XPhoto-Gallery"
     */
    private function brandSlug(): string
    {
        $name = AppSetting::get('site_name', config('app.name', 'Photo'));
        return Str::slug($name);
    }

    /**
     * Build a clean event name slug.
     * e.g. "Water Fight 2026 Cam2 Full" → "Water-Fight-2026-Cam2-Full"
     */
    private function eventSlug(?string $eventName): string
    {
        if (!$eventName) return 'photos';
        return Str::slug($eventName) ?: 'photos';
    }
    /**
     * GET /download/{token}
     * Show download confirmation page.
     */
    public function showDownload($token)
    {
        $dl = DownloadToken::where('token', $token)
            ->with(['order.event', 'order.items', 'user'])
            ->firstOrFail();

        $isExpired      = $dl->expires_at && $dl->expires_at->isPast();
        $limitReached   = $dl->max_downloads && $dl->download_count >= $dl->max_downloads;
        $isActive       = !$isExpired && !$limitReached;

        // Determine downloadable items
        if ($dl->photo_id) {
            // Single-photo token
            $items = $dl->order->items->where('photo_id', $dl->photo_id)->values();
            $tokenType = 'single';
        } else {
            // All-photos token
            $items = $dl->order->items;
            $tokenType = 'all';
        }

        $remaining = $dl->max_downloads ? max(0, $dl->max_downloads - $dl->download_count) : null;
        $progress  = $dl->max_downloads ? min(100, round(($dl->download_count / $dl->max_downloads) * 100)) : 0;

        // Load individual photo tokens so each photo uses its own download counter
        $photoTokens = collect();
        if ($tokenType === 'all') {
            $photoTokens = DownloadToken::where('order_id', $dl->order_id)
                ->where('user_id', $dl->user_id)
                ->whereNotNull('photo_id')
                ->get()
                ->keyBy('photo_id');
        }

        // Brand info for filenames
        $brandSlug = $this->brandSlug();
        $eventSlug = $this->eventSlug($dl->order->event->name ?? null);

        return view('public.download.show', compact(
            'dl', 'items', 'tokenType', 'isExpired', 'limitReached', 'isActive',
            'remaining', 'progress', 'photoTokens', 'brandSlug', 'eventSlug'
        ));
    }

    /**
     * POST /download/{token}
     * Perform the actual download.
     */
    public function processDownload(Request $request, $token)
    {
        $dl = DownloadToken::where('token', $token)
            ->with(['order.event', 'order.items'])
            ->firstOrFail();

        if ($dl->expires_at && $dl->expires_at->isPast()) {
            return redirect()->route('download.show', $token)
                ->with('error', 'ลิงก์ดาวน์โหลดหมดอายุแล้ว');
        }
        if ($dl->max_downloads && $dl->download_count >= $dl->max_downloads) {
            return redirect()->route('download.show', $token)
                ->with('error', 'ถึงจำนวนดาวน์โหลดสูงสุดแล้ว');
        }

        // Portfolio-mode guard: once an event's originals have been purged
        // (retention expired → photographer's showcase only) no amount of
        // token validity can resurrect the bytes. Fail loud instead of
        // serving a 404.
        $event = $dl->order->event ?? null;
        if ($event && $event->isPortfolioOnly()) {
            Log::info('Download blocked: event is portfolio-only', [
                'event_id' => $event->id, 'token' => $token,
            ]);
            return redirect()->route('download.show', $token)
                ->with('error', 'อีเวนต์นี้เก็บถาวรเป็นผลงานแล้ว ไม่สามารถดาวน์โหลดไฟล์ต้นฉบับได้');
        }

        // Increment download count
        $dl->increment('download_count');

        // Determine which photo to download
        $photoFileId = $request->input('photo_id', $dl->photo_id);
        $eventName   = $dl->order->event->name ?? null;

        // Find photo index in order for numbering
        $photoIndex = 1;
        if ($photoFileId && $dl->order) {
            foreach ($dl->order->items as $i => $item) {
                if ($item->photo_id === $photoFileId) {
                    $photoIndex = $i + 1;
                    break;
                }
            }
        }

        Log::info('Download processed', [
            'token'        => $token,
            'photo_id'     => $photoFileId,
            'order_id'     => $dl->order_id,
            'user_id'      => $dl->user_id,
            'download_count' => $dl->download_count,
        ]);

        // If no specific photo_id (e.g. "Download All" button) → create ZIP of all photos
        if (!$photoFileId && $dl->order && $dl->order->items->count() > 0) {
            return $this->downloadAllAsZip($dl);
        }

        // If Google Drive file ID available, download with branded filename
        if ($photoFileId) {
            return $this->downloadSinglePhoto($photoFileId, $eventName, $photoIndex, $request);
        }

        abort(404, 'ไม่พบไฟล์');
    }

    /**
     * GET /downloads
     * Show user's download history.
     */
    public function downloadHistory()
    {
        $tokens = DownloadToken::where('user_id', Auth::id())
            ->with(['order.event', 'order.items'])
            ->orderByDesc('created_at')
            ->paginate(12);

        // Group tokens by order_id for display
        $grouped = $tokens->getCollection()->groupBy('order_id');

        return view('public.download.history', compact('tokens', 'grouped'));
    }

    /**
     * Resolve the opaque "photo_id" stored on OrderItem to a real EventPhoto.
     *
     * Historical polymorphism we have to live with:
     *   • R2-native uploads (current flow) store the EventPhoto PRIMARY KEY
     *     as a numeric string — `drive_file_id` on that row is NULL.
     *   • Legacy Drive-only orders store the Google Drive file ID string
     *     (alphanumeric, ~28 chars).
     *
     * Numeric-first lookup is safe: Drive IDs are never purely digits, so
     * there's no collision risk. Falling back to `drive_file_id` keeps
     * pre-R2 orders downloadable.
     */
    private function findEventPhoto(?string $identifier): ?EventPhoto
    {
        if ($identifier === null || $identifier === '') return null;

        if (ctype_digit((string) $identifier)) {
            $byPk = EventPhoto::find((int) $identifier);
            if ($byPk) return $byPk;
        }

        return EventPhoto::where('drive_file_id', (string) $identifier)->first();
    }

    /**
     * Pick a file extension for the branded filename. Prefer whatever the
     * original upload carried (mirrors what customers uploaded); fall back
     * to mime_type → .jpg so the branded name is always sensible.
     */
    private function extensionForPhoto(EventPhoto $photo): string
    {
        if (!empty($photo->original_path)) {
            $ext = strtolower(pathinfo($photo->original_path, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic', 'gif'], true)) {
                return $ext === 'jpeg' ? 'jpg' : $ext;
            }
        }
        return match (true) {
            str_contains((string) $photo->mime_type, 'png')  => 'png',
            str_contains((string) $photo->mime_type, 'webp') => 'webp',
            str_contains((string) $photo->mime_type, 'heic') => 'heic',
            default                                          => 'jpg',
        };
    }

    /**
     * Download a single photo with branded filename.
     *
     * Preferred path (scales to 5,000+/day):
     *   1. Find EventPhoto (by PK first, then drive_file_id for legacy orders).
     *   2. If on R2/S3 → redirect to signed URL. Browser fetches direct from
     *      Cloudflare/AWS. Zero app-server bandwidth.
     *   3. If on R2/S3 but signed URLs disabled → proxy-stream via Storage::readStream.
     *   4. Legacy Drive orders → proxy from Google Drive with bearer auth.
     *   5. Final fallback → redirect to public Drive download URL.
     *
     * Format: {Brand}_{Event}_IMG-{number}.jpg
     */
    private function downloadSinglePhoto(string $photoFileId, ?string $eventName = null, int $index = 1, ?Request $request = null)
    {
        $brand = $this->brandSlug();
        $event = $this->eventSlug($eventName);
        $num   = str_pad((string) $index, 3, '0', STR_PAD_LEFT);

        $photo = $this->findEventPhoto($photoFileId);

        // ── 1. Cloud-direct signed URL (preferred for R2/S3) ────────────
        $useSigned = AppSetting::get('storage_use_signed_urls', '1') === '1';
        $mode      = AppSetting::get('storage_download_mode', 'redirect'); // redirect|proxy|auto

        if ($photo && $photo->hasCloudCopy() && $useSigned && $mode !== 'proxy') {
            $ext      = $this->extensionForPhoto($photo);
            $fileName = "{$brand}_{$event}_IMG-{$num}.{$ext}";

            // Bake the branded filename into the signature via
            // ResponseContentDisposition. Appending `response-content-disposition`
            // after signing breaks v4 auth (R2/S3 return 403 SignatureDoesNotMatch).
            $resolved = app(StorageManager::class)
                ->resolvePhotoDownload($photo, 'original', null, $fileName);

            if (!empty($resolved['url']) && $resolved['direct']) {
                Log::info('Download: cloud signed URL', [
                    'photo_id' => $photo->id,
                    'disk'     => $resolved['disk'],
                ]);

                // fetch()/XHR callers: browser fetch CANNOT follow a 302 to
                // a cross-origin R2 presigned URL because R2 doesn't serve
                // CORS headers — the fetch throws "Failed to fetch". Return
                // JSON so the JS can trigger a native browser download via
                // a hidden <a> click, which handles cross-origin fine.
                if ($request && ($request->expectsJson() || $request->ajax() || $request->wantsJson())) {
                    return response()->json([
                        'direct'   => true,
                        'url'      => $resolved['url'],
                        'filename' => $fileName,
                    ]);
                }

                return redirect()->away($resolved['url'], 302);
            }

            Log::warning('Download: resolvePhotoDownload returned no URL; falling back to proxy', [
                'photo_id' => $photo->id,
            ]);
        }

        // ── 2. Proxy-stream from whichever cloud disk holds the file ────
        // Used when signed URLs are disabled OR the signed URL handshake
        // failed (revoked key, clock skew, bucket policy). Slower — bytes
        // flow through the app server — but always works as long as the
        // app can read the object.
        if ($photo && $photo->hasCloudCopy() && !empty($photo->original_path)) {
            try {
                $disk = $photo->storage_disk ?: 'r2';
                if (\Illuminate\Support\Facades\Storage::disk($disk)->exists($photo->original_path)) {
                    $ext      = $this->extensionForPhoto($photo);
                    $fileName = "{$brand}_{$event}_IMG-{$num}.{$ext}";
                    $stream   = \Illuminate\Support\Facades\Storage::disk($disk)->readStream($photo->original_path);

                    Log::info('Download: cloud proxy stream', [
                        'photo_id' => $photo->id, 'disk' => $disk,
                    ]);

                    return response()->streamDownload(function () use ($stream) {
                        if ($stream) {
                            fpassthru($stream);
                            fclose($stream);
                        }
                    }, $fileName, [
                        'Content-Type' => $photo->mime_type ?: 'image/jpeg',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Cloud proxy stream failed: ' . $e->getMessage());
            }
        }

        // ── 3. Drive proxy (legacy Drive-only orders) ───────────────────
        // Only attempt Drive if we actually have a Drive file ID — either
        // from a legacy order (photo_id literally is the Drive ID) or from
        // a mirrored EventPhoto row that still carries drive_file_id.
        $driveFileId = $photo?->drive_file_id
            ?: (ctype_digit((string) $photoFileId) ? null : $photoFileId);
        $driveApiKey = AppSetting::get('google_drive_api_key', '');

        if ($driveFileId && $driveApiKey) {
            try {
                $driveService = new GoogleDriveService();
                $downloadUrl  = $driveService->downloadUrl($driveFileId);

                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => "Bearer {$driveApiKey}",
                ])->get($downloadUrl);

                if ($response->successful()) {
                    $contentType = $response->header('Content-Type') ?? 'image/jpeg';
                    $ext = match (true) {
                        str_contains($contentType, 'png')  => 'png',
                        str_contains($contentType, 'webp') => 'webp',
                        default                            => 'jpg',
                    };
                    $fileName = "{$brand}_{$event}_IMG-{$num}.{$ext}";

                    return response($response->body(), 200, [
                        'Content-Type'        => $contentType,
                        'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
                        'Content-Length'      => strlen($response->body()),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Drive proxy failed, falling back to direct redirect: ' . $e->getMessage());
            }
        }

        // ── 4. Legacy public Drive URL (last resort) ────────────────────
        if ($driveFileId) {
            return redirect("https://drive.google.com/uc?id={$driveFileId}&export=download");
        }

        Log::warning('Download failed: no resolution path', [
            'photo_id_input' => $photoFileId,
            'photo_model_id' => $photo?->id,
            'has_cloud_copy' => $photo?->hasCloudCopy(),
            'original_path'  => $photo?->original_path,
        ]);
        abort(404, 'ไม่พบไฟล์');
    }

    /**
     * Maximum photos allowed in a single synchronous ZIP request.
     * Above this threshold we dispatch BuildOrderZipJob (queue: "downloads")
     * which builds the ZIP on R2/S3 and emails the customer a signed URL —
     * zero application-server bandwidth and no HTTP timeout risk.
     */
    private const MAX_SYNC_ZIP_PHOTOS = 50;

    /**
     * Download all order photos as a ZIP file with branded naming.
     * ZIP name:  {Brand}_{Event}_{Count}-Photos.zip
     * Files in:  {Brand}_{Event}_IMG-001.jpg, IMG-002.jpg, ...
     *
     * Scalability:
     * - ≤ MAX_SYNC_ZIP_PHOTOS: built inline, streamed to disk (Http::sink),
     *   ZipArchive::addFile (disk ref, not in-memory). `set_time_limit(0)` +
     *   `ignore_user_abort(false)` lets long downloads finish but aborts as
     *   soon as the client disconnects. Temp files cleaned in finally.
     * - > MAX_SYNC_ZIP_PHOTOS: dispatched to BuildOrderZipJob (see class
     *   doc). Customer is notified and emailed the download link on
     *   completion.
     */
    private function downloadAllAsZip(DownloadToken $dl)
    {
        $order     = $dl->order;
        $brand     = $this->brandSlug();
        $event     = $this->eventSlug($order->event->name ?? null);
        $itemCount = $order->items->count();

        if ($itemCount > self::MAX_SYNC_ZIP_PHOTOS) {
            // Dispatch async ZIP job; email the user when ready.
            try {
                BuildOrderZipJob::dispatch($dl->id);
                Log::info('BuildOrderZipJob dispatched', [
                    'download_token_id' => $dl->id,
                    'order_id'          => $dl->order_id,
                    'photo_count'       => $itemCount,
                ]);
                return redirect()->route('download.show', $dl->token)
                    ->with('success',
                        "คำสั่งซื้อมี {$itemCount} รูป กำลังสร้างไฟล์ ZIP ในเบื้องหลัง " .
                        'ระบบจะส่งลิงก์ดาวน์โหลดไปยังอีเมลของคุณเมื่อพร้อม (ปกติใช้เวลา 1-5 นาที)'
                    );
            } catch (\Throwable $e) {
                Log::error('Failed to dispatch BuildOrderZipJob: ' . $e->getMessage());
                return redirect()->route('download.show', $dl->token)
                    ->with('error',
                        "คำสั่งซื้อมี {$itemCount} รูป (เกิน " . self::MAX_SYNC_ZIP_PHOTOS . " รูป) " .
                        'ไม่สามารถสร้างไฟล์ ZIP ได้ในขณะนี้ กรุณาดาวน์โหลดทีละรูปหรือลองใหม่ภายหลัง'
                    );
            }
        }

        // Don't let the PHP worker die mid-ZIP; do abort if client leaves.
        @set_time_limit(0);
        ignore_user_abort(false);

        $zipName = "{$brand}_{$event}_{$itemCount}-Photos.zip";
        $tempDir = storage_path('app/temp/zip-' . \Illuminate\Support\Str::random(16));
        $zipPath = storage_path('app/temp/' . $zipName);

        if (!is_dir($tempDir))            mkdir($tempDir, 0755, true);
        if (!is_dir(dirname($zipPath)))   mkdir(dirname($zipPath), 0755, true);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->rrmdir($tempDir);
            return redirect()->route('download.show', $dl->token)
                ->with('error', 'ไม่สามารถสร้างไฟล์ ZIP ได้');
        }

        $tempFiles   = [];
        $fileCount   = 0;
        $driveApiKey = AppSetting::get('google_drive_api_key', '');

        try {
            foreach ($order->items as $idx => $item) {
                $fileId = $item->photo_id;
                if (!$fileId) continue;

                $num      = str_pad($idx + 1, 3, '0', STR_PAD_LEFT);
                $tempFile = $tempDir . DIRECTORY_SEPARATOR . "img_{$num}.bin";

                $contentType = null;
                $ok          = false;

                $photo = $this->findEventPhoto($fileId);

                // Attempt A: R2 / S3 direct stream — current flow.
                // Read from the cloud disk through Storage::readStream, copy
                // into the temp file. ZipArchive::addFile references it by
                // path and only reads at close() time, so the stream handle
                // is already closed — no leaked handles.
                if ($photo && $photo->hasCloudCopy() && !empty($photo->original_path)) {
                    try {
                        $disk = $photo->storage_disk ?: 'r2';
                        if (\Illuminate\Support\Facades\Storage::disk($disk)->exists($photo->original_path)) {
                            $src = \Illuminate\Support\Facades\Storage::disk($disk)->readStream($photo->original_path);
                            if ($src) {
                                $dst = fopen($tempFile, 'wb');
                                if ($dst) {
                                    stream_copy_to_stream($src, $dst);
                                    fclose($dst);
                                    fclose($src);
                                    $ok          = true;
                                    $contentType = $photo->mime_type ?: 'image/jpeg';
                                } else {
                                    fclose($src);
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning("ZIP cloud stream failed for photo {$photo->id}: " . $e->getMessage());
                    }
                }

                // Legacy Drive ID for fallbacks — either from the EventPhoto
                // row (mirrored uploads) or the raw OrderItem.photo_id when
                // it's non-numeric (pre-R2 Drive-only orders).
                $driveFileId = $photo?->drive_file_id
                    ?: (ctype_digit((string) $fileId) ? null : $fileId);

                // Attempt B: authenticated Drive download (stream to disk)
                if (!$ok && $driveFileId && $driveApiKey) {
                    try {
                        $driveService = new GoogleDriveService();
                        $downloadUrl  = $driveService->downloadUrl($driveFileId);

                        $response = \Illuminate\Support\Facades\Http::timeout(60)
                            ->withHeaders(['Authorization' => "Bearer {$driveApiKey}"])
                            ->sink($tempFile)
                            ->get($downloadUrl);

                        if ($response->successful()) {
                            $contentType = $response->header('Content-Type', 'image/jpeg');
                            $ok = true;
                        }
                    } catch (\Throwable $e) {
                        Log::warning("ZIP Drive download failed for {$driveFileId}: " . $e->getMessage());
                    }
                }

                // Attempt C: public Drive URL (still stream to disk)
                if (!$ok && $driveFileId) {
                    try {
                        $response = \Illuminate\Support\Facades\Http::timeout(60)
                            ->sink($tempFile)
                            ->get("https://drive.google.com/uc?id={$driveFileId}&export=download");
                        if ($response->successful()) {
                            $contentType = $response->header('Content-Type', 'image/jpeg');
                            $ok = true;
                        }
                    } catch (\Throwable $e) {
                        Log::warning("ZIP fallback failed for {$driveFileId}: " . $e->getMessage());
                    }
                }

                if (!$ok || !is_file($tempFile) || filesize($tempFile) === 0) {
                    @unlink($tempFile);
                    continue;
                }

                // Prefer the photo's own extension (mirrors what the user
                // uploaded) over content-type guessing — gives customers a
                // sensible .png/.heic instead of forcing everything to .jpg.
                $ext = $photo
                    ? $this->extensionForPhoto($photo)
                    : match (true) {
                        str_contains((string) $contentType, 'png')  => 'png',
                        str_contains((string) $contentType, 'webp') => 'webp',
                        default                                     => 'jpg',
                    };
                $innerName = "{$brand}_{$event}_IMG-{$num}.{$ext}";

                $zip->addFile($tempFile, $innerName);
                $tempFiles[] = $tempFile;
                $fileCount++;
            }

            $zip->close();
        } finally {
            // ZipArchive holds file handles until close(); remove temps afterwards.
            foreach ($tempFiles as $tf) {
                @unlink($tf);
            }
            @rmdir($tempDir);
        }

        if ($fileCount === 0) {
            @unlink($zipPath);
            return redirect()->route('download.show', $dl->token)
                ->with('error', 'ไม่สามารถดาวน์โหลดรูปภาพได้ กรุณาลองดาวน์โหลดทีละรูป');
        }

        return response()->download($zipPath, $zipName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /** Recursive rmdir helper (kept small; used only on early-exit). */
    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = $dir . DIRECTORY_SEPARATOR . $f;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    /**
     * Legacy single-action download (kept for backward compatibility).
     */
    public function download($token)
    {
        return $this->showDownload($token);
    }
}

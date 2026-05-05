<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\GoogleDriveService;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DriveController extends Controller
{
    public function listPhotos($eventId) {
        $event = Event::findOrFail($eventId);

        // Admin-tunable client cache window (defaults to 60s).
        // Uses stale-while-revalidate at 2× to keep the UX snappy while
        // still picking up new uploads soon after they happen.
        $cacheSeconds = max(0, min(3600, (int) AppSetting::get('photo_gallery_cache_seconds', 60)));
        $swrSeconds   = max(30, $cacheSeconds * 2);

        // ── Try Google Drive first (if folder is linked + SA configured) ──
        if ($event->drive_folder_id) {
            $drive = new GoogleDriveService();
            if ($drive->hasServiceAccount()) {
                try {
                    $result  = $drive->listFolderFilesWithSWR($event->drive_folder_id, $event->id);
                    $source  = $result['source'] ?? 'live';
                    $headers = $drive->getCacheHeaders($source);

                    $photos = array_map(fn($p) => [
                        'id'            => $p['id'] ?? '',
                        'name'          => $p['name'] ?? '',
                        'thumbnailLink' => $p['thumbnailLink'] ?? $p['thumbnail'] ?? '',
                        'fallback'      => $p['fallback'] ?? '',
                        'source'        => 'drive',
                    ], $result['photos'] ?? []);

                    if (!empty($photos)) {
                        return response()->json([
                            'files'  => $photos,
                            'count'  => $result['total'] ?? count($photos),
                            'source' => $source,
                        ], 200, $headers);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Drive API failed, falling back to DB: ' . $e->getMessage());
                }
            }
        }

        // ── Fallback: load from event_photos table (uploaded / imported) ──
        // Cache the transformed payload briefly so a spike of concurrent
        // gallery viewers doesn't all hit the DB — the public show page
        // fires this endpoint on every visit. Cache TTL is tied to the
        // admin's photo_gallery_cache_seconds so "clear cache" from the
        // settings page gives a predictable invalidation window.
        $cacheKey = "gallery_photos_v1_{$event->id}";
        $ttl      = max(5, $cacheSeconds);

        $photos = Cache::remember($cacheKey, $ttl, function () use ($event) {
            return \App\Models\EventPhoto::where('event_id', $event->id)
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($p) => [
                    'id'            => (string) $p->id,
                    'name'          => $p->original_filename ?? $p->filename,
                    'thumbnailLink' => $p->thumbnail_url,
                    'watermarked'   => $p->watermarked_url,
                    'fallback'      => $p->thumbnail_url,
                    'source'        => $p->source ?? 'upload',
                    'width'         => $p->width,
                    'height'        => $p->height,
                ])
                ->values()
                ->toArray();
        });

        return response()->json([
            'files'  => $photos,
            'count'  => count($photos),
            'source' => 'database',
        ], 200, [
            'Cache-Control' => "public, max-age={$cacheSeconds}, stale-while-revalidate={$swrSeconds}",
            'X-Cache-TTL'   => (string) $ttl,
        ]);
    }

    public function proxyImage(Request $request, $fileId) {
        $size = (int) $request->input('sz', 400);
        $size = max(100, min(1600, $size));

        // Watermark policy for this proxy:
        //   • Apply watermark when admin has it enabled (watermark_enabled=1)
        //     AND the requested size is "preview-class" (>500px). This is
        //     the size the lightbox (id="lb-img") asks for — a buyer
        //     should never see the un-watermarked original until they pay.
        //   • Skip watermark for thumbnails (≤500px) — they're tiny enough
        //     that copying them isn't valuable, and watermarking every
        //     gallery card 1:1 would burn CPU on every page paint.
        //   • EventPhoto rows with a baked `watermarked_path` already
        //     short-circuit via the `redirect()->away()` branch below;
        //     this on-the-fly path is for Drive-sourced events that
        //     don't have a stored watermarked variant.
        $watermarkSvc       = app(\App\Services\WatermarkService::class);
        $applyWatermarkHere = $size > 500 && $watermarkSvc->isEnabled();

        // ── 1. R2 / S3 / local — numeric ID ⇒ EventPhoto PK ─────────────
        // The endpoint name is historical ("drive proxy"); it's now the
        // universal image-resolver for gallery & download-page previews,
        // so it also has to handle R2-native uploads where the ID the
        // client passes is the EventPhoto primary key (drive_file_id is
        // NULL on those rows).
        //
        // Small sizes → thumbnail (cheaper, CDN-friendly). Larger sizes
        // → watermarked variant (customer-safe preview on the public
        // gallery). Falls back to original only when neither exists.
        if (ctype_digit((string) $fileId)) {
            $photo = \App\Models\EventPhoto::find((int) $fileId);
            if ($photo) {
                // Strategy: ALWAYS redirect to a baked variant on the CDN.
                // Never fetch + watermark + return bytes inline — that
                // path was tried in commit f8a58ff and 502'd under load
                // because Drive/R2 fetch + GD compositing blew past the
                // PHP-FPM memory + edge timeouts.
                //
                // Resolution priority by size:
                //   ≤500px (gallery thumb)  → thumbnail → watermarked
                //   >500px (lightbox/etc)   → watermarked → thumbnail
                //
                // Originals are NEVER served from this endpoint — that
                // route is reserved for the buyer's signed download
                // token (see DownloadController). If the only file
                // available is the original, we serve a tiny placeholder
                // GIF and log so the admin can re-run reprocess.
                $url = $size <= 500
                    ? ($photo->thumbnail_url ?: $photo->watermarked_url)
                    : ($photo->watermarked_url ?: $photo->thumbnail_url);

                if (!empty($url)) {
                    // Aggressive cache so a packed gallery scrolls
                    // smoothly and a re-visit hits the browser cache.
                    //   max-age   = 1 day in browser
                    //   s-maxage  = 7 days at the CDN edge — the
                    //               variant URL itself never changes
                    //               for an event_photo row, so the CDN
                    //               can serve it for as long as we let
                    //               it (admin reprocessing creates a
                    //               new path, breaking the cache key).
                    //   immutable = tells the browser not to revalidate
                    //               on F5; saves a 304 round-trip.
                    return redirect()->away($url, 302)->header(
                        'Cache-Control',
                        'public, max-age=86400, s-maxage=604800, immutable'
                    );
                }

                // Variants missing — JIT bake them right now in this
                // request. Dispatches ProcessUploadedPhotoJob synchronously
                // (same pipeline as the upload-time bake), then redirects
                // to the freshly-written watermarked thumbnail.
                //
                // First request per photo: ~5-10s (download original from
                // R2 → resize → watermark → upload back to R2 → update
                // DB row). Subsequent requests: instant — the redirect
                // branch above hits and the response is fully CDN-cached.
                //
                // Concurrency safety: a soft single-flight via Cache lock.
                // If another worker is already baking the same photo, we
                // return the placeholder immediately rather than
                // double-bake (same R2 path, last write wins, but no
                // point burning two workers' memory on the same image).
                $bakeLockKey = "proxyimg_baking_{$photo->id}";
                $alreadyBaking = \Illuminate\Support\Facades\Cache::has($bakeLockKey);

                if (!$alreadyBaking) {
                    \Illuminate\Support\Facades\Cache::put($bakeLockKey, 1, 60); // 60s lock
                    try {
                        // Bump runtime caps for this request only — the bake
                        // decodes a multi-MB JPEG into RGBA pixels (memory
                        // peak ~width × height × 4 bytes) and the GD
                        // composite + reencode dominates the wall-clock.
                        // 256M / 45s comfortably fits a 4K original.
                        @ini_set('memory_limit', '256M');
                        @set_time_limit(45);

                        if (class_exists(\App\Jobs\ProcessUploadedPhotoJob::class)) {
                            \App\Jobs\ProcessUploadedPhotoJob::dispatchSync($photo->id);
                        }

                        // Pick up the newly-written paths.
                        $photo->refresh();
                        $bakedUrl = $size <= 500
                            ? ($photo->thumbnail_url ?: $photo->watermarked_url)
                            : ($photo->watermarked_url ?: $photo->thumbnail_url);

                        if (!empty($bakedUrl)) {
                            return redirect()->away($bakedUrl, 302)->withHeaders([
                                'Cache-Control' => 'public, max-age=86400, s-maxage=604800, immutable',
                                'X-Source'      => 'jit-baked',
                            ]);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('proxyImage: JIT bake failed, returning placeholder', [
                            'photo_id' => $photo->id,
                            'error'    => $e->getMessage(),
                        ]);
                    } finally {
                        \Illuminate\Support\Facades\Cache::forget($bakeLockKey);
                    }
                }

                // Placeholder fallback when:
                //   • Another worker is already baking this photo (skip,
                //     they'll write the variant; next visit gets it)
                //   • The bake itself failed (caught above; logged for
                //     ops; admin can re-run `photos:reprocess` manually)
                //   • The job ran but somehow didn't populate the paths
                //     (shouldn't happen; same diagnostic value as above)
                //
                // 60s cache so the moment any of the above clears, the
                // next gallery visit gets the real watermarked thumbnail.
                return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200, [
                    'Content-Type'  => 'image/gif',
                    'Cache-Control' => 'public, max-age=60',
                    'X-Source'      => 'placeholder-bake-deferred',
                ]);
            }
        }

        // ── 2. Legacy Google Drive path ─────────────────────────────────
        // Cache key disambiguates watermarked vs raw bodies — without the
        // _wm suffix a previous unwatermarked cache hit would override
        // the new watermarked render after admin enables watermarking.
        $cacheKey = "drive_thumb_{$fileId}_{$size}" . ($applyWatermarkHere ? '_wm' : '');

        // Return from cache if available (cached for 1 hour)
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached) {
            return response($cached['body'], 200, [
                'Content-Type'  => $cached['type'],
                'Cache-Control' => 'public, max-age=3600',
                'X-Cache'       => 'HIT',
            ]);
        }

        $drive = new GoogleDriveService();

        // Try authenticated thumbnail via Google Drive API
        if ($drive->hasServiceAccount()) {
            try {
                $token = $drive->getAccessToken();
                $url   = "https://www.googleapis.com/drive/v3/files/{$fileId}?fields=thumbnailLink";
                $meta  = \Illuminate\Support\Facades\Http::withToken($token)->get($url);

                if ($meta->ok() && !empty($meta->json('thumbnailLink'))) {
                    $thumbUrl = preg_replace('/=s\d+/', '=s' . $size, $meta->json('thumbnailLink'));
                    $imgResp  = \Illuminate\Support\Facades\Http::timeout(10)->get($thumbUrl);

                    if ($imgResp->ok()) {
                        $body = $imgResp->body();
                        $type = $imgResp->header('Content-Type', 'image/jpeg');

                        // Apply watermark inline. The service writes via GD
                        // and returns JPEG bytes — independent of the source
                        // type, so we normalise content-type to image/jpeg.
                        if ($applyWatermarkHere && !empty($body)) {
                            $tmp = tempnam(sys_get_temp_dir(), 'drvwm_');
                            try {
                                file_put_contents($tmp, $body);
                                $watermarked = $watermarkSvc->apply($tmp);
                                if (!empty($watermarked)) {
                                    $body = $watermarked;
                                    $type = 'image/jpeg';
                                }
                            } catch (\Throwable $e) {
                                Log::warning('Drive proxy: watermark apply failed', [
                                    'file_id' => $fileId,
                                    'error'   => $e->getMessage(),
                                ]);
                                // Fall through with the raw body — better
                                // to show the un-watermarked image than
                                // break the gallery on a transient GD glitch.
                            } finally {
                                if (is_string($tmp) && is_file($tmp)) @unlink($tmp);
                            }
                        }

                        // Cache for 1 hour (only cache small sizes to save memory)
                        if ($size <= 800 && strlen($body) < 500000) {
                            \Illuminate\Support\Facades\Cache::put($cacheKey, ['body' => $body, 'type' => $type], 3600);
                        }

                        return response($body, 200, [
                            'Content-Type'  => $type,
                            'Cache-Control' => 'public, max-age=3600',
                            'X-Cache'       => 'MISS',
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Drive proxy auth failed: ' . $e->getMessage());
            }
        }

        // Fallback: redirect to public thumbnail URL (legacy Drive-only).
        // We can't apply watermark on a redirect (we don't see the bytes),
        // so this path bypasses watermarking — only triggers when the
        // service account isn't configured, which should be the
        // exception, not the rule, in any production install.
        return redirect("https://drive.google.com/thumbnail?id={$fileId}&sz=w{$size}");
    }

    /**
     * Handle Google Drive push notification webhooks.
     *
     * Google sends push notifications with these headers:
     *   X-Goog-Channel-ID     — channel ID registered during watch()
     *   X-Goog-Resource-ID    — opaque resource ID
     *   X-Goog-Resource-State — 'sync' (initial handshake) or 'update' / 'add' / 'remove' / 'trash'
     *   X-Goog-Resource-URI   — URI of the resource that changed
     *   X-Goog-Message-Number — incrementing message counter
     *   X-Goog-Channel-Token  — optional token set during watch() for verification
     */
    public function webhook(Request $request)
    {
        $channelId     = $request->header('X-Goog-Channel-ID');
        $resourceId    = $request->header('X-Goog-Resource-ID');
        $resourceState = $request->header('X-Goog-Resource-State');
        $resourceUri   = $request->header('X-Goog-Resource-URI');
        $messageNumber = $request->header('X-Goog-Message-Number');
        $channelToken  = $request->header('X-Goog-Channel-Token');

        Log::info('Google Drive webhook received', [
            'channel_id'     => $channelId,
            'resource_id'    => $resourceId,
            'resource_state' => $resourceState,
            'resource_uri'   => $resourceUri,
            'message_number' => $messageNumber,
            'channel_token'  => $channelToken,
            'body'           => $request->getContent(),
        ]);

        // 'sync' is just Google's initial handshake — acknowledge and return
        if ($resourceState === 'sync') {
            Log::info('Google Drive webhook: sync handshake acknowledged', ['channel_id' => $channelId]);
            return response()->json(['ok' => true, 'state' => 'sync']);
        }

        // For update/add/remove/trash, find the event by channel ID and queue a re-sync
        if (in_array($resourceState, ['update', 'add', 'remove', 'trash'], true)) {
            // channel_id was set as the event's drive_folder_id (or a dedicated channel token)
            // Try to find a matching event
            $event = null;

            if ($channelToken) {
                // Token may be "event_{id}" or just the event id
                $tokenId = str_replace('event_', '', $channelToken);
                $event   = Event::find((int) $tokenId);
            }

            if (!$event && $channelId) {
                // Fall back: channel ID may equal drive_folder_id
                $event = Event::where('drive_folder_id', $channelId)->first();
            }

            if ($event) {
                // Queue a re-sync job via the sync_queue table
                // Schema: job_type, event_id, folder_id, status, priority, attempts, max_attempts, created_at, updated_at
                try {
                    $existing = DB::table('sync_queue')
                        ->where('event_id', $event->id)
                        ->whereIn('status', ['pending', 'running'])
                        ->exists();

                    if (!$existing) {
                        DB::table('sync_queue')->insert([
                            'job_type'     => 'drive_sync',
                            'event_id'     => $event->id,
                            'folder_id'    => $event->drive_folder_id,
                            'status'       => 'pending',
                            'priority'     => 1,
                            'attempts'     => 0,
                            'max_attempts' => 3,
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]);
                        Log::info("Google Drive webhook: queued re-sync for event #{$event->id}", [
                            'state'     => $resourceState,
                            'folder_id' => $event->drive_folder_id,
                        ]);
                    } else {
                        Log::info("Google Drive webhook: re-sync already pending for event #{$event->id}, skipping");
                    }
                } catch (\Throwable $e) {
                    Log::error('Google Drive webhook: failed to queue sync job', ['error' => $e->getMessage()]);
                }
            } else {
                Log::warning('Google Drive webhook: could not resolve event from channel', [
                    'channel_id'    => $channelId,
                    'channel_token' => $channelToken,
                ]);
            }
        }

        return response()->json(['ok' => true, 'state' => $resourceState]);
    }
}

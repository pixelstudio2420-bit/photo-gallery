<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\EventPriceResolver;
use App\Services\FaceSearchBudget;
use App\Services\FaceSearchService;
use App\Services\StorageManager;
use App\Support\PlanResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FaceSearchController extends Controller
{
    protected FaceSearchService $faceSearch;
    protected EventPriceResolver $prices;
    protected FaceSearchBudget $budget;

    public function __construct(
        FaceSearchService  $faceSearch,
        EventPriceResolver $prices,
        FaceSearchBudget   $budget
    ) {
        $this->faceSearch = $faceSearch;
        $this->prices     = $prices;
        $this->budget     = $budget;
    }

    /**
     * Show face search page for a specific event.
     */
    public function show($eventId)
    {
        $event = Event::findOrFail($eventId);

        // Per-event toggle: admins can disable face-search for privacy-
        // sensitive events. Hide the whole page when disabled — the public
        // link is removed on the event page too, but this guard stops anyone
        // who bookmarked or shared the URL.
        if (!$event->face_search_enabled) {
            abort(404, 'Face search is disabled for this event.');
        }

        $configured = $this->faceSearch->isConfigured();

        return view('public.events.face-search', compact('event', 'configured'));
    }

    /**
     * Process face search: upload selfie, compare against event photos.
     *
     * PDPA notes (Thailand Personal Data Protection Act, B.E. 2562):
     *   • A selfie is biometric data — processing requires explicit consent (§26).
     *   • We require a 'consent' field set to a truthy value from the form.
     *   • The uploaded image bytes are held in memory only for the duration of
     *     this request; we never write them to disk beyond PHP's ephemeral
     *     tmp upload, which the framework removes at request end.
     *   • We purge the local variable explicitly after use as defense-in-depth.
     *
     * Response shape:
     *   Each `matches[]` row carries event_id / file_id / price / name so the
     *   frontend can POST directly to /cart/add-bulk and /orders/express
     *   (same payload shape as public/events/show.blade.php uses).
     */
    public function search(Request $request, $eventId)
    {
        $request->validate([
            'selfie'  => 'required|image|max:10240', // 10MB max
            'consent' => 'required|accepted',        // PDPA biometric-data consent
        ], [
            'consent.required' => 'กรุณายินยอมเงื่อนไข PDPA ก่อนเริ่มค้นหา',
            'consent.accepted' => 'กรุณายินยอมเงื่อนไข PDPA ก่อนเริ่มค้นหา',
        ]);

        $event = Event::findOrFail($eventId);

        // Per-event toggle: reject the search outright if admins have disabled
        // face-search for this event. Same guard as the show() page.
        if (!$event->face_search_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'ฟีเจอร์ค้นหาด้วยใบหน้าปิดใช้งานสำหรับงานนี้',
            ], 403);
        }

        if (!$this->faceSearch->isConfigured()) {
            // Generic message — do not expose the specific cloud vendor
            // to a public API consumer. The reason is logged server-side
            // for ops; the user just sees "service unavailable".
            return response()->json([
                'success' => false,
                'message' => 'ระบบ AI วิเคราะห์ภาพยังไม่พร้อมใช้งาน · กรุณาติดต่อผู้ดูแลระบบ',
            ], 503);
        }

        // ═══════════════════════════════════════════════════════════════
        // COST CONTROLS — run BEFORE any AWS API call is issued.
        //   Every cap lives in FaceSearchBudget so FaceSearchController
        //   stays a thin coordinator. See FaceSearchBudget::preflight()
        //   for ordering rationale (cheapest checks first).
        // ═══════════════════════════════════════════════════════════════
        $preflight = $this->budget->preflight($request, (int) $event->id);
        if (!$preflight['allowed']) {
            $this->budget->logResult([
                'event_id'    => $event->id,
                'user_id'     => Auth::id(),
                'ip_address'  => $request->ip(),
                'selfie_hash' => '',          // no selfie processed yet
                'search_type' => 'collection',
                'api_calls'   => 0,
                'status'      => $preflight['status'],
                'notes'       => 'preflight-denied',
            ]);
            return response()->json([
                'success' => false,
                'message' => $preflight['message'],
            ], 429);
        }

        $uploadedPath = $request->file('selfie')->getRealPath();
        $selfieBytes  = file_get_contents($uploadedPath);

        // Hash the bytes BEFORE anything else — used for dedup cache and
        // logging. Cheap (~1ms) and worth doing once up front so we don't
        // have to keep the raw bytes around for the cache key alone.
        $selfieHash   = hash('sha256', $selfieBytes);
        $requestStart = microtime(true);

        try {
            // ── Cache short-circuit: identical (event + selfie) served in-
            // memory. We still log it so per-user/IP quotas catch repeat
            // abuse, but 0 AWS calls means 0 cost.
            $cached = $this->budget->cacheLookup((int) $event->id, $selfieHash);
            if ($cached !== null) {
                $this->budget->logResult([
                    'event_id'    => $event->id,
                    'user_id'     => Auth::id(),
                    'ip_address'  => $request->ip(),
                    'selfie_hash' => $selfieHash,
                    'search_type' => 'cache',
                    'api_calls'   => 0,
                    'face_count'  => 1,
                    'match_count' => count($cached),
                    'duration_ms' => (int) ((microtime(true) - $requestStart) * 1000),
                    'status'      => 'cache_hit',
                ]);
                return response()->json([
                    'success'     => true,
                    'face_count'  => 1,
                    'match_count' => count($cached),
                    'matches'     => $cached,
                    'cached'      => true,
                    'privacy'     => [
                        'stored'        => false,
                        'retained_for'  => 'request-only',
                        'purged_at'     => now()->toIso8601String(),
                    ],
                ]);
            }

            // First verify the selfie contains a face (1 API call).
            // detectFaces() now returns a structured payload so we can
            // tell "AWS errored" (config / IAM / network — admin issue)
            // apart from "no face in image" (user uploaded wrong file).
            $detectResult = $this->faceSearch->detectFaces($selfieBytes);
            $faces        = $detectResult['faces']      ?? [];
            $errorCode    = $detectResult['error_code'] ?? null;

            // ── AWS infrastructure error (auth/IAM/region/network) ──
            // Was hiding behind 422 "no face" before. Surface as 503 so
            // the buyer sees a "service unavailable" message + admin
            // gets the real error from the Log::error in FaceSearchService.
            if ($errorCode === 'aws_error' || $errorCode === 'unconfigured') {
                $this->budget->logResult([
                    'event_id'    => $event->id,
                    'user_id'     => Auth::id(),
                    'ip_address'  => $request->ip(),
                    'selfie_hash' => $selfieHash,
                    'search_type' => 'collection',
                    'api_calls'   => 0,
                    'face_count'  => 0,
                    'duration_ms' => (int) ((microtime(true) - $requestStart) * 1000),
                    'status'      => 'aws_error',
                    'notes'       => substr((string) ($detectResult['error'] ?? $errorCode), 0, 240),
                ]);
                $payload = [
                    'success' => false,
                    'message' => 'ระบบ AI วิเคราะห์ภาพไม่พร้อมใช้งานชั่วคราว · กรุณาลองอีกครั้งภายหลัง หรือติดต่อผู้ดูแลระบบ',
                    'code'    => $errorCode,
                ];
                // For authenticated admins/photographers, include the actual
                // AWS error code + class + diagnostic link in the response so
                // they can debug from the browser DevTools Network tab without
                // needing SSH access to laravel.log on Laravel Cloud. Public
                // buyers (unauthenticated) see only the generic message.
                $user = Auth::user();
                $isStaff = $user && (
                    (method_exists($user, 'isAdmin')        && $user->isAdmin())
                 || (method_exists($user, 'isPhotographer') && $user->isPhotographer())
                 || ($user->role ?? null) === 'admin'
                 || ($user->role ?? null) === 'photographer'
                );
                if ($isStaff || config('app.debug')) {
                    $payload['debug'] = [
                        'aws_error_code'  => $detectResult['aws_error_code'] ?? null,
                        'error_class'     => $detectResult['error_class']    ?? null,
                        'message'         => $detectResult['error']          ?? null,
                        'diagnostic_url'  => url('/admin/diagnostics/aws'),
                        'hint'            => 'เปิด diagnostic_url เพื่อดูรายละเอียดเต็ม + ขั้นตอนแก้',
                    ];
                }
                return response()->json($payload, 503);
            }

            // ── Genuine no-face — selfie tip ──
            if (empty($faces)) {
                $this->budget->logResult([
                    'event_id'    => $event->id,
                    'user_id'     => Auth::id(),
                    'ip_address'  => $request->ip(),
                    'selfie_hash' => $selfieHash,
                    'search_type' => 'collection',
                    'api_calls'   => 1,     // detectFaces ran, even though no face
                    'face_count'  => 0,
                    'duration_ms' => (int) ((microtime(true) - $requestStart) * 1000),
                    'status'      => 'no_face',
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบใบหน้าในรูปที่อัพโหลด — ลองรูปที่เห็นใบหน้าชัดเจน หันหน้าตรง แสงเพียงพอ ไม่มีแว่นกันแดดหรือหน้ากากบดบัง',
                ], 422);
            }

            // Get event photos
            $eventPhotos = $this->getEventPhotos($event);
            if (empty($eventPhotos)) {
                $this->budget->logResult([
                    'event_id'    => $event->id,
                    'user_id'     => Auth::id(),
                    'ip_address'  => $request->ip(),
                    'selfie_hash' => $selfieHash,
                    'search_type' => 'collection',
                    'api_calls'   => 1,
                    'face_count'  => count($faces),
                    'duration_ms' => (int) ((microtime(true) - $requestStart) * 1000),
                    'status'      => 'error',
                    'notes'       => 'no_photos_in_event',
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบรูปภาพในงานนี้',
                ], 404);
            }

            $pricePerPhoto = (float) $this->prices->perPhoto($event->id);

            // Try collection-based search first (1 API call regardless of
            // event size — always safe).
            $collectionId = 'event-' . $event->id;
            $results = $this->faceSearch->searchByFaceInCollection($selfieBytes, $collectionId, 80.0, 50);

            $searchType = 'collection';
            $apiCalls   = 1 /*detectFaces*/ + 1 /*searchFacesByImage*/;

            if (!empty($results)) {
                // Map face results back to photo data
                $matchedPhotos = $this->mapCollectionResults($results, $eventPhotos, $event->id, $pricePerPhoto);
            } else {
                // Fallback: direct comparison. Cost is LINEAR in photo count
                // (1 compareFaces per photo) so we cap it aggressively —
                // admin settings let you raise/lower the photo ceiling.
                $refuse = $this->budget->shouldRefuseFallback(count($eventPhotos));
                if ($refuse !== null) {
                    $this->budget->logResult([
                        'event_id'    => $event->id,
                        'user_id'     => Auth::id(),
                        'ip_address'  => $request->ip(),
                        'selfie_hash' => $selfieHash,
                        'search_type' => 'fallback',
                        'api_calls'   => $apiCalls, // detectFaces + searchFacesByImage already ran
                        'face_count'  => count($faces),
                        'duration_ms' => (int) ((microtime(true) - $requestStart) * 1000),
                        'status'      => $refuse['status'],
                        'notes'       => 'photos=' . count($eventPhotos),
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => $refuse['message'],
                    ], 429);
                }

                $raw = $this->faceSearch->searchByFace($selfieBytes, $eventPhotos, 80.0);
                $matchedPhotos = $this->enrichFallbackMatches($raw, $eventPhotos, $event->id, $pricePerPhoto);
                $searchType = 'fallback';
                $apiCalls  += count($eventPhotos); // 1 compareFaces per photo
            }

            // Persist to cache so identical-selfie repeats skip AWS entirely.
            $this->budget->cacheStore((int) $event->id, $selfieHash, $matchedPhotos);

            Log::info("FaceSearch completed for event #{$event->id}", [
                'face_count'  => count($faces),
                'match_count' => count($matchedPhotos),
                'api_calls'   => $apiCalls,
                'search_type' => $searchType,
                'consent'     => true,
            ]);

            $this->budget->logResult([
                'event_id'    => $event->id,
                'user_id'     => Auth::id(),
                'ip_address'  => $request->ip(),
                'selfie_hash' => $selfieHash,
                'search_type' => $searchType,
                'api_calls'   => $apiCalls,
                'face_count'  => count($faces),
                'match_count' => count($matchedPhotos),
                'duration_ms' => (int) ((microtime(true) - $requestStart) * 1000),
                'status'      => 'success',
            ]);

            // ── Usage metering + circuit-breaker accounting ────────────────
            // Record AFTER success so a failed Rekognition call doesn't
            // burn the user's quota. The meter increments usage_counters
            // atomically; CircuitBreaker.charge accumulates platform-wide
            // spend. Both are best-effort — failures here MUST NOT bubble
            // up to the user (they got their result already).
            $planCode = PlanResolver::photographerCode(Auth::user());
            \App\Services\Usage\UsageMeter::record(
                userId:    (int) Auth::id(),
                planCode:  $planCode,
                resource:  'ai.face_search',
                units:     $apiCalls,
                metadata:  ['event_id' => $event->id, 'search_type' => $searchType, 'cache_hit' => false],
            );
            // Charge the breaker in THB — Rekognition is $0.001/call → ~฿0.035.
            // Using the same conversion as PlanCostCalculator for consistency.
            $usdToThb = (float) config('usage.usd_to_thb_rate', 35.0);
            app(\App\Services\Usage\CircuitBreakerService::class)
                ->charge('ai.face_search', $apiCalls * 0.001 * $usdToThb);

            return response()->json([
                'success'     => true,
                'face_count'  => count($faces),
                'match_count' => count($matchedPhotos),
                'matches'     => $matchedPhotos,
                'privacy'     => [
                    'stored'        => false,
                    'retained_for'  => 'request-only',
                    'purged_at'     => now()->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            // Surface a clean JSON error to the client instead of letting a 500
            // HTML page reach the browser — the UI only shows a generic toast
            // when it can't parse JSON, which made bugs in this path invisible.
            Log::error('FaceSearch failed: ' . $e->getMessage(), [
                'event_id'  => $event->id,
                'exception' => get_class($e),
                'file'      => $e->getFile() . ':' . $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ]);

            // Attribute the error to the dashboard so spikes in failures are
            // visible alongside the usage counters — can't fix what you can't see.
            $this->budget->logResult([
                'event_id'    => $event->id,
                'user_id'     => Auth::id(),
                'ip_address'  => $request->ip(),
                'selfie_hash' => $selfieHash ?? '',
                'search_type' => $searchType ?? 'collection',
                'api_calls'   => $apiCalls   ?? 0,
                'duration_ms' => isset($requestStart) ? (int) ((microtime(true) - $requestStart) * 1000) : 0,
                'status'      => 'error',
                'notes'       => substr(get_class($e) . ': ' . $e->getMessage(), 0, 240),
            ]);

            $payload = [
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการประมวลผล กรุณาลองใหม่อีกครั้ง',
            ];
            // In APP_DEBUG=true show the exception class + message so the dev
            // console has enough info to diagnose without tailing logs.
            if (config('app.debug')) {
                $payload['debug'] = [
                    'exception' => get_class($e),
                    'message'   => $e->getMessage(),
                    'at'        => basename($e->getFile()) . ':' . $e->getLine(),
                ];
            }
            return response()->json($payload, 500);
        } finally {
            // PDPA defense-in-depth: overwrite and free the selfie bytes immediately
            // after the search completes, even if an exception bubbled up.
            if (isset($selfieBytes)) {
                $selfieBytes = str_repeat("\0", 16);
                unset($selfieBytes);
            }
            // The uploaded file is cleaned up by PHP at request end, but we also
            // defensively zero it out if still present.
            if ($uploadedPath && is_file($uploadedPath)) {
                @unlink($uploadedPath);
            }
        }
    }

    /**
     * Get event photos as array with id, path, url, thumbnail.
     *
     * URL resolution goes through EventPhoto accessors (`watermarked_url`,
     * `thumbnail_url`) which delegate to StorageManager. For R2/S3-backed
     * photos this returns short-lived signed URLs so matched results can't be
     * deep-linked by others or indexed by crawlers.
     */
    private function getEventPhotos(Event $event): array
    {
        // Try event_photos table first (uploaded photos).
        // NOTE: event_photos uses a `status` enum ('active'|'processing'|'failed'|
        // 'deleted'), not an `is_active` boolean — earlier code had `is_active`
        // which errors on MySQL (Unknown column) and prevented results returning.
        $photos = EventPhoto::where('event_id', $event->id)
            ->where('status', 'active')
            ->limit(200) // Limit for API cost
            ->get();

        if ($photos->isNotEmpty()) {
            return $photos->map(function (EventPhoto $p) {
                return [
                    'id'           => $p->id,
                    'path'         => $p->original_path,
                    // `storage_disk` + `original_url` are passed for
                    // SERVER-side use only — FaceSearchService::getPhotoBytes
                    // needs to read the un-watermarked original to feed
                    // AWS Rekognition's compareFaces, otherwise the
                    // watermark overlay obscures the face and matches
                    // drop to ~0%. These fields never leave the server
                    // (the public response uses `url` / `thumbnail`
                    // below, which are the watermarked variants).
                    'storage_disk' => $p->storage_disk,
                    'original_url' => $p->original_url,
                    // `watermarked_url` / `thumbnail_url` → StorageManager →
                    // presigned URLs on R2/S3, public asset URLs on the
                    // local disk. Both are variant-aware (no leaking originals).
                    'url'       => $p->watermarked_url,
                    'thumbnail' => $p->thumbnail_url,
                    // Display name for cart rows — prefer the original
                    // filename, fall back to "Photo #id".
                    'name'      => $p->original_filename ?: ('Photo #' . $p->id),
                ];
            })->toArray();
        }

        // Fallback: event_photos_cache (Google Drive synced)
        $cached = DB::table('event_photos_cache')
            ->where('event_id', $event->id)
            ->select('id', 'file_id', 'thumbnail_url', 'file_name')
            ->limit(200)
            ->get();

        return $cached->map(fn($p) => [
            'id'        => 'cache_' . $p->id,
            'path'      => '',
            'url'       => $p->thumbnail_url ?: "https://drive.google.com/thumbnail?id={$p->file_id}&sz=w800",
            'thumbnail' => $p->thumbnail_url ?: "https://drive.google.com/thumbnail?id={$p->file_id}&sz=w400",
            'name'      => $p->file_name ?: ('Photo cache_' . $p->id),
        ])->toArray();
    }

    /**
     * Map Rekognition collection results back to photo data.
     *
     * Each returned row carries everything the frontend needs to build a
     * cart payload (event_id + file_id + price + name + thumbnail) without
     * another round-trip to the server — the shape matches what
     * public/events/show.blade.php → buildCartItems() produces so the same
     * /cart/add-bulk and /orders/express handlers accept both.
     *
     * `external_id` is the EventPhoto `id` (or `cache_{id}`) we stored when
     * indexing — see FaceSearchService::indexFace().
     */
    private function mapCollectionResults(array $results, array $eventPhotos, int $eventId, float $pricePerPhoto): array
    {
        // Collection keys may be int or string depending on the photo source;
        // cast both sides to string so the lookup is consistent.
        $photoMap = collect($eventPhotos)->keyBy(fn ($p) => (string) $p['id']);
        $matched = [];

        foreach ($results as $r) {
            $photoId = (string) ($r['external_id'] ?? '');
            $photo   = $photoMap->get($photoId);
            if (!$photo) {
                continue;
            }
            $matched[] = [
                'photo_id'   => (string) $photo['id'],
                'file_id'    => (string) $photo['id'],
                'event_id'   => $eventId,
                'name'       => $photo['name'] ?? ('Photo #' . $photo['id']),
                'price'      => $pricePerPhoto,
                'photo_url'  => $photo['url'],
                'thumbnail'  => $photo['thumbnail'],
                'confidence' => $r['confidence'] ?? 0,
            ];
        }

        return $matched;
    }

    /**
     * Enrich fallback (compareFaces) results with the same fields
     * mapCollectionResults produces, so the frontend handles both code paths
     * identically.
     */
    private function enrichFallbackMatches(array $matches, array $eventPhotos, int $eventId, float $pricePerPhoto): array
    {
        $photoMap = collect($eventPhotos)->keyBy(fn ($p) => (string) $p['id']);

        return collect($matches)->map(function (array $m) use ($photoMap, $eventId, $pricePerPhoto) {
            $photoId = (string) ($m['photo_id'] ?? '');
            $photo   = $photoMap->get($photoId);

            return [
                'photo_id'   => $photoId,
                'file_id'    => $photoId,
                'event_id'   => $eventId,
                'name'       => $photo['name'] ?? ('Photo #' . $photoId),
                'price'      => $pricePerPhoto,
                'photo_url'  => $m['photo_url']  ?? ($photo['url']       ?? ''),
                'thumbnail'  => $m['thumbnail']  ?? ($photo['thumbnail'] ?? ''),
                'confidence' => $m['confidence'] ?? 0,
            ];
        })->toArray();
    }
}

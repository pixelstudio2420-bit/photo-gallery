<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Media\Exceptions\InvalidMediaCategoryException;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\MediaContext;
use App\Services\Media\R2MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Issues short-lived presigned PUT URLs for direct browser → R2 uploads.
 *
 * Why direct upload?
 *   - The PHP-FPM worker is freed during the actual file transfer (which
 *     can be ~minutes for a 25MB photo on mobile). Single worker can
 *     handle many concurrent users.
 *   - No proxying through the app server saves bandwidth & egress cost.
 *   - 25MB+ files don't have to fit in PHP's `upload_max_filesize`.
 *
 * Two-step protocol
 *   1. POST /api/uploads/sign          { category, resource_id, filename, mime, size }
 *      → { url, key, expected_mime, expires_at, max_bytes, headers }
 *   2. Browser does PUT {url} with the file
 *   3. POST /api/uploads/confirm       { key, original_name, byte_size }
 *      → server records the key against the resource (per-category logic)
 *
 * Authorization
 *   - Step 1 enforces ownership of the resource (e.g. event.photographer_id
 *     == auth user). Without this, an attacker could mint URLs for any
 *     other user's resources.
 *   - Step 3 also re-checks ownership before persisting the key.
 */
class PresignedUploadController extends Controller
{
    public function __construct(
        private readonly R2MediaService $media,
    ) {}

    /**
     * POST /api/uploads/sign
     *
     * Body:
     *   category      string  e.g. "events.photos", "auth.avatar"
     *   resource_id   int|null
     *   filename      string  user-chosen name (sanitised by the path builder)
     *   mime          string  the Content-Type the browser will send (must match exactly)
     *   size          int     for client-side validation only (the URL itself can't enforce size)
     *
     * Response: PresignedUploadResult::toArray() (URL, key, expiry, max_bytes)
     */
    public function sign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category'    => ['required', 'string', 'max:64'],
            'resource_id' => ['nullable', 'integer', 'min:1'],
            'filename'    => ['required', 'string', 'max:255'],
            'mime'        => ['required', 'string', 'max:128'],
            'size'        => ['required', 'integer', 'min:1', 'max:1073741824'], // 1 GB sanity ceiling
        ]);

        $userId = (int) Auth::id();
        if ($userId === 0) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        // Authorisation — must own the resource for this category.
        try {
            $this->authoriseCategory($data['category'], $data['resource_id'] ?? null, $userId);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        try {
            [$system, $entity] = explode('.', $data['category'], 2) + [null, null];
            if (!$system || !$entity) {
                return response()->json(['error' => 'Invalid category format'], 422);
            }

            $ctx = MediaContext::make(
                system:     $system,
                entityType: $entity,
                userId:     $userId,
                resourceId: $data['resource_id'] ?? null,
            );

            $presigned = $this->media->presignedUploadUrl(
                $ctx,
                $data['filename'],
                $data['mime'],
            );

            // Server-side cap check against the category's max_bytes
            if ($data['size'] > $presigned->maxBytes) {
                return response()->json([
                    'error'     => 'File exceeds the maximum size for this category',
                    'max_bytes' => $presigned->maxBytes,
                ], 413);
            }

            return response()->json($presigned->toArray());
        } catch (InvalidMediaCategoryException | InvalidMediaFileException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Presigned upload sign failed', [
                'user_id'  => $userId,
                'category' => $data['category'],
                'error'    => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Could not issue upload URL'], 500);
        }
    }

    /**
     * POST /api/uploads/confirm
     *
     * Called after the browser successfully PUTs the file to R2. We verify
     * the object actually landed (browser could lie / network could glitch)
     * and dispatch any post-upload work that's category-specific.
     *
     * Body:
     *   key             string  R2 object key returned from /sign
     *   category        string  e.g. "events.photos"
     *   resource_id     int|null
     *   original_name   string
     *   byte_size       int
     */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key'           => ['required', 'string', 'max:512'],
            'category'      => ['required', 'string', 'max:64'],
            'resource_id'   => ['nullable', 'integer', 'min:1'],
            'original_name' => ['required', 'string', 'max:255'],
            'byte_size'     => ['required', 'integer', 'min:1'],
        ]);

        $userId = (int) Auth::id();
        if ($userId === 0) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        // Re-authorise — the ownership of the resource_id might have
        // changed since /sign was called (very unlikely, but cheap).
        try {
            $this->authoriseCategory($data['category'], $data['resource_id'] ?? null, $userId);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        // Defence-in-depth: validate the key looks like one WE minted (so an
        // attacker can't ask us to "confirm" arbitrary R2 keys they
        // discovered, e.g. via the public bucket listing if misconfigured).
        $expectedPrefix = sprintf('%s/user_%d', str_replace('.', '/', $data['category']), $userId);
        if (!str_starts_with($data['key'], $expectedPrefix)) {
            return response()->json([
                'error'    => 'Key does not match the expected ownership prefix',
                'expected' => $expectedPrefix,
            ], 403);
        }

        // Verify the file actually exists on R2 — never trust the client.
        $disk = \Illuminate\Support\Facades\Storage::disk((string) config('media.disk', 'r2'));
        if (!$disk->exists($data['key'])) {
            return response()->json([
                'error' => 'Object not found on R2 — upload may have failed',
            ], 404);
        }

        // We DON'T persist to a domain table here — that's the controller's
        // responsibility (event_photos, auth_users.avatar, etc). The caller
        // takes the validated key and writes it to its own row in a separate
        // request. Keeping persistence out of this generic confirm endpoint
        // means we don't reproduce the per-category business logic twice.
        return response()->json([
            'ok'  => true,
            'key' => $data['key'],
        ]);
    }

    /**
     * Cross-cutting authorisation. Each category that uses the presigned
     * flow must check that $userId actually owns $resourceId.
     *
     * Categories not listed here can still call presigned upload but only
     * for a user-scoped (non-resource) destination — currently
     * `auth.avatar` and `auth.cover`.
     */
    private function authoriseCategory(string $category, ?int $resourceId, int $userId): void
    {
        switch ($category) {
            case 'auth.avatar':
            case 'auth.cover':
                // No resource_id → no further check needed
                if ($resourceId !== null) {
                    throw new \DomainException('This category does not accept a resource_id');
                }
                return;

            case 'events.photos':
                if (!$resourceId) {
                    throw new \DomainException('event_id is required for events.photos');
                }
                $event = Event::find($resourceId);
                if (!$event) {
                    throw new \DomainException('Event not found');
                }
                $profile = Auth::user()?->photographerProfile;
                if (!$profile || (int) $event->photographer_id !== (int) $profile->id) {
                    throw new \DomainException('You are not the owner of this event');
                }
                return;

            case 'payments.slips':
                if (!$resourceId) {
                    throw new \DomainException('order_id is required for payments.slips');
                }
                $owns = \App\Models\Order::where('id', $resourceId)
                    ->where('user_id', $userId)
                    ->exists();
                if (!$owns) {
                    throw new \DomainException('You are not the owner of this order');
                }
                return;

            case 'photographer.portfolio':
            case 'photographer.branding':
            case 'photographer.presets':
            case 'digital.products':
            case 'digital.product_covers':
            case 'blog.posts':
            case 'storage.files':
            case 'chat.attachments':
            case 'face_search.queries':
                // These need their own ownership checks — for now require the
                // controller to issue presigned URLs through its own endpoint
                // and not the generic /api/uploads/sign route.
                throw new \DomainException(
                    "Category '{$category}' must use its dedicated upload endpoint, not the generic presigned API"
                );

            default:
                throw new \DomainException("Unknown category: {$category}");
        }
    }
}

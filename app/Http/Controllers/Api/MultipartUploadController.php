<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Media\MediaContext;
use App\Services\Upload\MultipartUploadService;
use App\Services\Upload\UploadSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * HTTP entry points for chunked / resumable uploads.
 *
 * The flow the browser drives
 * ===========================
 *   1. POST /api/uploads/session                 (optional, batch only)
 *      → {sessionToken}
 *
 *   2. POST /api/uploads/multipart/init
 *      body: {category, resourceId, filename, mime, totalBytes, sessionToken?}
 *      → {uploadId, key, partSize, totalParts, expiresAt}
 *
 *   3. for each partNumber:
 *        POST /api/uploads/multipart/sign-part
 *        body: {uploadId, partNumber}
 *        → {url, expiresAt}
 *
 *        Browser does PUT {url} with the chunk → R2 returns ETag
 *
 *        POST /api/uploads/multipart/record-part
 *        body: {uploadId, partNumber, etag, sizeBytes}
 *        → {ok: true, completedParts, totalParts}
 *
 *   4. POST /api/uploads/multipart/complete
 *      body: {uploadId, parts: [{partNumber, etag, sizeBytes}]}
 *      → {key, sizeBytes}
 *
 *   5. (on cancel/error) POST /api/uploads/multipart/abort
 *      body: {uploadId}
 *      → {ok: true}
 *
 *   6. (after each file finishes) POST /api/uploads/session/{token}/progress
 *      body: {success: true, bytes: 12345}
 *
 *   7. POST /api/uploads/session/{token}/complete
 *
 * Resume scenario
 * ---------------
 * If the browser disconnects mid-upload, on reconnect it calls:
 *
 *   GET /api/uploads/multipart/{uploadId}/parts
 *
 * to get the list of already-confirmed parts, then resumes from the
 * first missing partNumber. The ETags are stored in upload_chunks.parts
 * so the resume call can avoid re-uploading anything.
 *
 * Authorisation
 * -------------
 * The same authoriseCategory() rules from PresignedUploadController apply
 * here — single source of truth for who-can-upload-where. We extract
 * those rules into a shared trait/service if a third entry point is ever
 * added.
 */
class MultipartUploadController extends Controller
{
    public function __construct(
        private readonly MultipartUploadService $multipart,
        private readonly UploadSessionService $sessions,
    ) {}

    // ─── Session lifecycle ─────────────────────────────────────────────

    public function openSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category'       => ['required', 'string', 'max:64'],
            'resource_id'    => ['nullable', 'integer', 'min:1'],
            'expected_files' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'meta'           => ['nullable', 'array'],
        ]);

        $userId = (int) Auth::id();
        if ($userId === 0) {
            return response()->json(['error' => 'Authentication required'], 401);
        }
        try {
            $this->authoriseCategory($data['category'], $data['resource_id'] ?? null, $userId);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        $session = $this->sessions->open(
            userId:        $userId,
            eventId:       $data['resource_id'] ?? null,
            category:      $data['category'],
            expectedFiles: $data['expected_files'] ?? 0,
            meta:          $data['meta'] ?? [],
        );

        return response()->json($session, 201);
    }

    public function sessionProgress(Request $request, string $token): JsonResponse
    {
        $userId = (int) Auth::id();
        if ($userId === 0) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $data = $request->validate([
            'success' => ['required', 'boolean'],
            'bytes'   => ['nullable', 'integer', 'min:0'],
        ]);

        if ($data['success']) {
            $this->sessions->recordSuccess($token, $userId, (int) ($data['bytes'] ?? 0));
        } else {
            $this->sessions->recordFailure($token, $userId);
        }

        $session = $this->sessions->find($token, $userId);
        return response()->json([
            'ok'              => true,
            'completed_files' => (int) ($session->completed_files ?? 0),
            'failed_files'    => (int) ($session->failed_files ?? 0),
            'total_bytes'     => (int) ($session->total_bytes ?? 0),
        ]);
    }

    public function sessionComplete(Request $request, string $token): JsonResponse
    {
        $userId = (int) Auth::id();
        $ok = $this->sessions->complete($token, $userId);
        return response()->json(['ok' => $ok]);
    }

    public function sessionStatus(Request $request, string $token): JsonResponse
    {
        $userId = (int) Auth::id();
        $session = $this->sessions->find($token, $userId);
        if (!$session) {
            return response()->json(['error' => 'Session not found'], 404);
        }
        return response()->json([
            'session_token'   => $session->session_token,
            'status'          => $session->status,
            'expected_files'  => (int) $session->expected_files,
            'completed_files' => (int) $session->completed_files,
            'failed_files'    => (int) $session->failed_files,
            'total_bytes'     => (int) $session->total_bytes,
            'expires_at'      => $session->expires_at,
        ]);
    }

    // ─── Multipart lifecycle ───────────────────────────────────────────

    public function initMultipart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category'       => ['required', 'string', 'max:64'],
            'resource_id'    => ['nullable', 'integer', 'min:1'],
            'filename'       => ['required', 'string', 'max:255'],
            'mime'           => ['required', 'string', 'max:128'],
            'total_bytes'    => ['required', 'integer', 'min:1', 'max:5368709120'], // 5 GB ceiling
            'part_size'      => ['nullable', 'integer'],
            'session_token'  => ['nullable', 'string', 'size:36'],
        ]);

        $userId = (int) Auth::id();
        if ($userId === 0) {
            return response()->json(['error' => 'Authentication required'], 401);
        }
        try {
            $this->authoriseCategory($data['category'], $data['resource_id'] ?? null, $userId);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        // Optional session — caller may upload outside any batch.
        $sessionId = null;
        if (!empty($data['session_token'])) {
            $session = $this->sessions->find($data['session_token'], $userId);
            if (!$session) {
                return response()->json(['error' => 'Invalid session token'], 422);
            }
            $sessionId = (int) $session->id;
        }

        try {
            [$system, $entity] = explode('.', $data['category'], 2) + [null, null];
            $ctx = MediaContext::make(
                system:     $system,
                entityType: $entity,
                userId:     $userId,
                resourceId: $data['resource_id'] ?? null,
            );

            $result = $this->multipart->init(
                ctx:              $ctx,
                originalFilename: $data['filename'],
                mimeType:         $data['mime'],
                totalBytes:       $data['total_bytes'],
                partSize:         $data['part_size'] ?? null,
                userId:           $userId,
                sessionId:        $sessionId,
            );
            return response()->json($result, 201);
        } catch (\InvalidArgumentException | \DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('MultipartUploadController.initMultipart failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Could not open multipart upload'], 500);
        }
    }

    public function signPart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'upload_id'   => ['required', 'string', 'max:256'],
            'part_number' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $userId = (int) Auth::id();
        try {
            $result = $this->multipart->signPart($data['upload_id'], $data['part_number'], $userId);
            return response()->json($result);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('MultipartUploadController.signPart failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Could not sign part'], 500);
        }
    }

    public function recordPart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'upload_id'   => ['required', 'string', 'max:256'],
            'part_number' => ['required', 'integer', 'min:1', 'max:10000'],
            'etag'        => ['required', 'string', 'max:128'],
            'size_bytes'  => ['required', 'integer', 'min:0'],
        ]);

        $userId = (int) Auth::id();
        try {
            $this->multipart->recordPart(
                $data['upload_id'],
                $userId,
                $data['part_number'],
                trim($data['etag'], '"'),
                $data['size_bytes'],
            );
            $parts = $this->multipart->listParts($data['upload_id'], $userId);
            return response()->json(['ok' => true, 'completed_parts' => count($parts)]);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function listParts(Request $request, string $uploadId): JsonResponse
    {
        $userId = (int) Auth::id();
        try {
            $parts = $this->multipart->listParts($uploadId, $userId);
            return response()->json(['parts' => $parts]);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function completeMultipart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'upload_id'        => ['required', 'string', 'max:256'],
            'parts'            => ['required', 'array', 'min:1'],
            'parts.*.partNumber' => ['required', 'integer', 'min:1'],
            'parts.*.etag'       => ['required', 'string'],
        ]);
        $userId = (int) Auth::id();
        try {
            $result = $this->multipart->complete($data['upload_id'], $userId, $data['parts']);
            return response()->json($result);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('MultipartUploadController.completeMultipart failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Could not assemble multipart upload'], 500);
        }
    }

    public function abortMultipart(Request $request): JsonResponse
    {
        $data = $request->validate(['upload_id' => ['required', 'string']]);
        $userId = (int) Auth::id();
        $ok = $this->multipart->abort($data['upload_id'], $userId);
        return response()->json(['ok' => $ok]);
    }

    /* ─────────────────── shared authorisation ─────────────────── */

    private function authoriseCategory(string $category, ?int $resourceId, int $userId): void
    {
        switch ($category) {
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

            case 'auth.avatar':
            case 'auth.cover':
                return;

            default:
                // Conservative default — any new category should be added
                // explicitly with its ownership check, NOT silently allowed.
                throw new \DomainException("Category '{$category}' is not enabled for multipart uploads");
        }
    }
}

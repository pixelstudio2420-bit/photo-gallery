<?php

namespace App\Services;

use App\Models\AppSetting;
use Aws\Rekognition\RekognitionClient;
use Illuminate\Support\Facades\Log;

class FaceSearchService
{
    private ?RekognitionClient $client = null;

    /**
     * Get configured Rekognition client.
     *
     * Credential source priority:
     *   1. AppSetting (primary) — what the admin UI at /admin/settings/aws
     *      writes. Uses `aws_access_key_id` / `aws_secret_access_key` /
     *      `aws_default_region`, matching the field names on that form.
     *   2. AppSetting legacy keys (`aws_key` / `aws_secret` / `aws_region`)
     *      — historical naming used by the earlier bootstrap of this service;
     *      kept as fallback so installs predating the unified admin form
     *      still resolve credentials without requiring a data migration.
     *   3. config('services.aws.*') — .env fallback for local dev.
     */
    private function getClient(): RekognitionClient
    {
        if ($this->client) {
            return $this->client;
        }

        if (!$this->isConfigured()) {
            throw new \RuntimeException('AWS Rekognition is not configured. Please set AWS credentials in admin settings.');
        }

        $this->client = new RekognitionClient([
            'version' => 'latest',
            'region'  => $this->resolveRegion(),
            'credentials' => [
                'key'    => $this->resolveKey(),
                'secret' => $this->resolveSecret(),
            ],
        ]);

        return $this->client;
    }

    /**
     * Check if AWS Rekognition is properly configured.
     */
    public function isConfigured(): bool
    {
        return $this->resolveKey() !== '' && $this->resolveSecret() !== '';
    }

    private function resolveKey(): string
    {
        return (string) (AppSetting::get('aws_access_key_id', '')
            ?: AppSetting::get('aws_key', '')
            ?: config('services.aws.key', ''));
    }

    private function resolveSecret(): string
    {
        return (string) (AppSetting::get('aws_secret_access_key', '')
            ?: AppSetting::get('aws_secret', '')
            ?: config('services.aws.secret', ''));
    }

    private function resolveRegion(): string
    {
        return (string) (AppSetting::get('aws_default_region', '')
            ?: AppSetting::get('aws_region', '')
            ?: config('services.aws.region', 'ap-southeast-1'));
    }

    /**
     * Detect faces in a user-uploaded photo.
     *
     * Returns a structured result so callers can distinguish "AWS
     * call succeeded but the image had no face" (legitimate user
     * input error — show "please upload a clearer selfie") from
     * "AWS itself errored" (auth, region, IAM, network — service
     * unavailable, admin must fix). The previous version returned
     * an empty array for BOTH cases, which made the public API
     * return 422 "ไม่พบใบหน้า..." when the actual cause was an
     * IAM/credentials misconfiguration in prod — admin had no way
     * to tell from the user's bug report.
     *
     * @return array{
     *   faces: array,
     *   error: ?string,
     *   error_code: ?string,
     *   error_class: ?string,
     *   aws_error_code: ?string
     * }
     *   `faces`          — array of FaceDetail objects (empty if none)
     *   `error`          — human-readable error (null on success)
     *   `error_code`     — short machine-readable code:
     *                      null            → success (faces may be empty)
     *                      'aws_error'     → SDK threw (auth/region/network)
     *                      'unconfigured'  → AWS keys not set
     *   `error_class`    — fully-qualified exception class (e.g.
     *                      Aws\Rekognition\Exception\RekognitionException) or null.
     *                      Useful for diagnostics — admins can tell SDK errors
     *                      from generic RuntimeException ("not configured").
     *   `aws_error_code` — AWS-side error code (e.g. AccessDeniedException,
     *                      InvalidSignatureException) when the SDK populated
     *                      one; null otherwise.
     */
    public function detectFaces(string $imageBytes): array
    {
        if (!$this->isConfigured()) {
            return [
                'faces'          => [],
                'error'          => 'AWS Rekognition not configured',
                'error_code'     => 'unconfigured',
                'error_class'    => null,
                'aws_error_code' => null,
            ];
        }

        try {
            $result = $this->getClient()->detectFaces([
                'Image' => ['Bytes' => $imageBytes],
                'Attributes' => ['DEFAULT'],
            ]);

            return [
                'faces'          => $result->get('FaceDetails') ?? [],
                'error'          => null,
                'error_code'     => null,
                'error_class'    => null,
                'aws_error_code' => null,
            ];
        } catch (\Throwable $e) {
            // Log the FULL exception for admin debugging — this is
            // what was hidden before. Common causes:
            //   • InvalidSignatureException — wrong/expired AWS keys
            //   • AccessDeniedException     — IAM missing rekognition:DetectFaces
            //   • ResourceNotFoundException — wrong region (collection lives elsewhere)
            //   • EndpointConnectionError    — VPC / network / Rekognition not in this region
            $awsCode = method_exists($e, 'getAwsErrorCode') ? $e->getAwsErrorCode() : null;
            Log::error('Rekognition detectFaces SDK error', [
                'class'          => get_class($e),
                'aws_error_code' => $awsCode,
                'message'        => $e->getMessage(),
                'region'         => $this->resolveRegion(),
            ]);
            return [
                'faces'          => [],
                'error'          => 'AWS Rekognition error: ' . $e->getMessage(),
                'error_code'     => 'aws_error',
                'error_class'    => get_class($e),
                'aws_error_code' => $awsCode,
            ];
        }
    }

    /**
     * Compare a selfie face against all photos in an event.
     * Returns matched photo IDs with confidence scores.
     *
     * @param string $selfieBytes  Raw image bytes of the selfie
     * @param array  $eventPhotos  Array of ['id' => ..., 'path' => ..., 'url' => ...]
     * @param float  $threshold    Minimum similarity (0-100)
     * @return array  Matched photos with confidence
     */
    public function searchByFace(string $selfieBytes, array $eventPhotos, float $threshold = 80.0): array
    {
        $client = $this->getClient();
        $matches = [];

        foreach ($eventPhotos as $photo) {
            try {
                $targetBytes = $this->getPhotoBytes($photo);
                if (!$targetBytes) {
                    continue;
                }

                $result = $client->compareFaces([
                    'SourceImage' => ['Bytes' => $selfieBytes],
                    'TargetImage' => ['Bytes' => $targetBytes],
                    'SimilarityThreshold' => $threshold,
                ]);

                $faceMatches = $result->get('FaceMatches') ?? [];
                if (!empty($faceMatches)) {
                    $bestMatch = collect($faceMatches)->sortByDesc('Similarity')->first();
                    $matches[] = [
                        'photo_id'   => $photo['id'],
                        'photo_url'  => $photo['url'] ?? '',
                        'thumbnail'  => $photo['thumbnail'] ?? '',
                        'confidence' => round($bestMatch['Similarity'] ?? 0, 1),
                    ];
                }
            } catch (\Throwable $e) {
                // Skip individual photo comparison failures
                Log::debug("Face compare skipped photo {$photo['id']}: " . $e->getMessage());
                continue;
            }
        }

        // Sort by confidence descending
        usort($matches, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        return $matches;
    }

    /**
     * Search by face using Rekognition collection (indexed faces).
     * This is the faster approach when photos are pre-indexed.
     */
    public function searchByFaceInCollection(string $selfieBytes, string $collectionId, float $threshold = 80.0, int $maxFaces = 50): array
    {
        try {
            $result = $this->getClient()->searchFacesByImage([
                'CollectionId'       => $collectionId,
                'Image'              => ['Bytes' => $selfieBytes],
                'FaceMatchThreshold' => $threshold,
                'MaxFaces'           => $maxFaces,
            ]);

            return collect($result->get('FaceMatches') ?? [])->map(fn($m) => [
                'face_id'    => $m['Face']['FaceId'] ?? '',
                'external_id'=> $m['Face']['ExternalImageId'] ?? '',
                'confidence' => round($m['Similarity'] ?? 0, 1),
            ])->toArray();
        } catch (\Throwable $e) {
            Log::error('Rekognition searchFacesByImage failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Index an EventPhoto into its event's Rekognition collection (best-effort).
     *
     * Idempotent: returns the existing face_id if the photo already has one.
     * Silently skips when AWS is not configured or the source image is unreadable.
     * Persists the resulting face_id back to the EventPhoto model so subsequent
     * calls short-circuit and the face can be removed from the collection later.
     *
     * @param  \App\Models\EventPhoto  $photo       The photo record to index
     * @param  string|null             $imagePath   Absolute path to the original image
     *                                              bytes. When null, the method tries to
     *                                              fetch bytes from the configured disk.
     * @return string|null   The Rekognition Face ID (stored on the model), or null on skip/failure.
     */
    public function indexPhoto(\App\Models\EventPhoto $photo, ?string $imagePath = null): ?string
    {
        // 1) Already indexed → no-op
        if (!empty($photo->rekognition_face_id)) {
            return $photo->rekognition_face_id;
        }

        // 2) AWS not configured → silently skip
        if (!$this->isConfigured()) {
            return null;
        }

        // 3) Event must exist (we need event_id for the collection name)
        if (empty($photo->event_id) || empty($photo->id)) {
            return null;
        }

        // 4) PLAN GATE — does the OWNER (event's photographer) have AI
        //    available + monthly credit headroom right now? Skipping
        //    here means:
        //      • Free-plan photographers don't burn AWS credits we
        //        promised the buyer they'd get.
        //      • Expired-plan photographers stop indexing immediately
        //        instead of waiting for the nightly cron.
        //      • Over-cap photographers stop being indexed mid-month
        //        instead of going unbounded.
        //    We log the deny reason so the photographer can see it on
        //    /photographer/subscription/index ("100/100 AI ใช้แล้ว").
        $photo->loadMissing('event');
        $photographerId = (int) (optional($photo->event)->photographer_id ?? 0);
        if ($photographerId > 0) {
            $gate = \App\Support\PlanGate::canUseAi($photographerId, \App\Support\PlanGate::FEAT_FACE_SEARCH);
            if (!$gate['allowed']) {
                Log::info("FaceSearchService::indexPhoto blocked by plan gate for photo #{$photo->id}", [
                    'photographer_id' => $photographerId,
                    'reason'          => $gate['reason'],
                    'plan_code'       => $gate['plan_code'] ?? null,
                    'used'            => $gate['used']      ?? null,
                    'cap'             => $gate['cap']       ?? null,
                ]);
                return null;
            }
        }

        // 4) Resolve image bytes — from the provided path first, then from the disk
        $bytes = null;
        if ($imagePath && is_file($imagePath)) {
            $bytes = @file_get_contents($imagePath);
        }

        if ($bytes === null || $bytes === false || $bytes === '') {
            try {
                $disk = $photo->storage_disk ?? 'public';
                if (!empty($photo->original_path)) {
                    $bytes = \Illuminate\Support\Facades\Storage::disk($disk)->get($photo->original_path);
                }
            } catch (\Throwable $e) {
                Log::debug("FaceSearchService::indexPhoto could not read disk for photo #{$photo->id}: " . $e->getMessage());
                return null;
            }
        }

        if (empty($bytes)) {
            return null;
        }

        // 5) Call Rekognition IndexFaces
        $faces = $this->indexFace($bytes, 'event-' . $photo->event_id, (string) $photo->id);
        $faceId = $faces[0]['face_id'] ?? null;

        if (!$faceId) {
            // No face detected or indexing failed — log and move on
            Log::info("FaceSearchService: no face indexed for photo #{$photo->id} (no face detected or API failure).");
            return null;
        }

        // 5b) Bill the photographer's AI quota for this index call so the
        //     monthly_ai_credits cap counts the indexing too (not just buyer
        //     searches). Without this the photographer could side-step the
        //     cap by uploading thousands of photos and paying $0 in AI even
        //     though Rekognition charged us per call.
        if ($photographerId > 0) {
            try {
                $planCode = \App\Support\PlanResolver::photographerCode(
                    \App\Models\User::find($photographerId)
                );
                \App\Services\Usage\UsageMeter::record(
                    userId:   $photographerId,
                    planCode: $planCode,
                    resource: 'ai.face_index',
                    units:    1,
                    metadata: ['photo_id' => $photo->id, 'event_id' => $photo->event_id],
                );
            } catch (\Throwable $e) {
                Log::warning('UsageMeter::record(ai.face_index) failed: ' . $e->getMessage());
            }
        }

        // 6) Persist face_id (use forceFill to stay safe even if $fillable changes)
        try {
            $photo->forceFill(['rekognition_face_id' => $faceId])->save();
        } catch (\Throwable $e) {
            Log::warning("FaceSearchService: indexed photo #{$photo->id} but failed to persist face_id: " . $e->getMessage());
            // Even if save fails, we still return the face_id — it's indexed in AWS
        }

        return $faceId;
    }

    /**
     * Index a photo's faces into a Rekognition collection.
     */
    public function indexFace(string $imageBytes, string $collectionId, string $externalImageId): array
    {
        try {
            // Ensure collection exists
            $this->ensureCollection($collectionId);

            $result = $this->getClient()->indexFaces([
                'CollectionId'    => $collectionId,
                'Image'           => ['Bytes' => $imageBytes],
                'ExternalImageId' => $externalImageId,
                'DetectionAttributes' => ['DEFAULT'],
            ]);

            return collect($result->get('FaceRecords') ?? [])->map(fn($r) => [
                'face_id' => $r['Face']['FaceId'] ?? '',
                'external_id' => $r['Face']['ExternalImageId'] ?? '',
            ])->toArray();
        } catch (\Throwable $e) {
            Log::error("Rekognition indexFaces failed for {$externalImageId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Remove a single face from a Rekognition collection (cleanup on photo delete).
     *
     * Silently returns false when AWS is not configured or the collection/face
     * does not exist — callers shouldn't block a photo delete on AWS availability.
     *
     * @return bool  true when AWS acknowledged the delete, false otherwise.
     */
    public function deleteFace(string $collectionId, string $faceId): bool
    {
        if (!$this->isConfigured() || $collectionId === '' || $faceId === '') {
            return false;
        }

        try {
            $this->getClient()->deleteFaces([
                'CollectionId' => $collectionId,
                'FaceIds'      => [$faceId],
            ]);
            return true;
        } catch (\Aws\Exception\AwsException $e) {
            // Treat "collection gone" / "face gone" as success — cleanup is idempotent
            $code = $e->getAwsErrorCode();
            if (in_array($code, ['ResourceNotFoundException', 'InvalidParameterException'], true)) {
                Log::debug("FaceSearchService::deleteFace skipped ({$code}) for face {$faceId} in {$collectionId}");
                return true;
            }
            Log::warning("FaceSearchService::deleteFace failed for face {$faceId} in {$collectionId}: " . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::warning("FaceSearchService::deleteFace unexpected error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ensure a Rekognition collection exists.
     */
    private function ensureCollection(string $collectionId): void
    {
        try {
            $this->getClient()->createCollection(['CollectionId' => $collectionId]);
        } catch (\Aws\Exception\AwsException $e) {
            if ($e->getAwsErrorCode() !== 'ResourceAlreadyExistsException') {
                throw $e;
            }
        }
    }

    /**
     * List all collections in the Rekognition account.
     *
     * Returns an array of collection IDs (strings). Used by the cleanup cron
     * to find `event-{id}` collections whose photos no longer exist.
     *
     * @return string[]
     */
    public function listCollections(): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $client = $this->getClient();
            $collections = [];
            $nextToken = null;

            do {
                $params = ['MaxResults' => 100];
                if ($nextToken) {
                    $params['NextToken'] = $nextToken;
                }
                $result = $client->listCollections($params);

                foreach ($result->get('CollectionIds') ?? [] as $id) {
                    $collections[] = $id;
                }
                $nextToken = $result->get('NextToken');
            } while ($nextToken);

            return $collections;
        } catch (\Throwable $e) {
            Log::error('Rekognition listCollections failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * List all faces in a single Rekognition collection.
     *
     * Returns [{'face_id' => ..., 'external_id' => ...}, ...] with pagination
     * handled internally so the caller doesn't deal with NextToken.
     */
    public function listFaces(string $collectionId, int $pageSize = 1000): array
    {
        if (!$this->isConfigured() || $collectionId === '') {
            return [];
        }

        try {
            $client = $this->getClient();
            $faces = [];
            $nextToken = null;

            do {
                $params = [
                    'CollectionId' => $collectionId,
                    'MaxResults'   => $pageSize,
                ];
                if ($nextToken) {
                    $params['NextToken'] = $nextToken;
                }

                $result = $client->listFaces($params);

                foreach ($result->get('Faces') ?? [] as $f) {
                    $faces[] = [
                        'face_id'     => $f['FaceId'] ?? '',
                        'external_id' => $f['ExternalImageId'] ?? '',
                    ];
                }

                $nextToken = $result->get('NextToken');
            } while ($nextToken);

            return $faces;
        } catch (\Aws\Exception\AwsException $e) {
            // Collection gone → nothing to list
            if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                return [];
            }
            Log::warning("Rekognition listFaces failed for {$collectionId}: " . $e->getMessage());
            return [];
        } catch (\Throwable $e) {
            Log::warning("Rekognition listFaces unexpected error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete a Rekognition collection wholesale (used when an event is purged).
     */
    public function deleteCollection(string $collectionId): bool
    {
        if (!$this->isConfigured() || $collectionId === '') {
            return false;
        }

        try {
            $this->getClient()->deleteCollection(['CollectionId' => $collectionId]);
            return true;
        } catch (\Aws\Exception\AwsException $e) {
            if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                return true; // idempotent
            }
            Log::warning("Rekognition deleteCollection failed for {$collectionId}: " . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            Log::warning('Rekognition deleteCollection unexpected error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get photo bytes for face comparison. Resolution order (most → least
     * preferred):
     *
     *   1. Local filesystem at `storage/app/public/{path}` — only hits
     *      for legacy `disk=public` uploads that haven't been migrated.
     *   2. Storage::disk({storage_disk})->get({path}) — the canonical
     *      path for R2 / S3 uploads. Returns the un-watermarked
     *      original bytes, which is what compareFaces needs to find the
     *      buyer's face accurately. Without this branch the function
     *      fell through to step 4 (watermarked URL) and Rekognition
     *      saw a watermark text overlay AND a downsized JPEG — face
     *      detection accuracy on that combination drops to near-zero,
     *      which is what was making `/events/3/face-search` return
     *      "no matches" for events whose photos were on R2.
     *   3. Direct HTTP fetch of `original_url` — fallback when the
     *      Storage SDK isn't configured or the disk read fails. Still
     *      returns the un-watermarked original (R2 public CDN).
     *   4. Direct HTTP fetch of `url` (watermarked variant) — last
     *      resort, only used if every other path failed. Match
     *      accuracy is degraded but the search at least returns
     *      something instead of zero.
     */
    private function getPhotoBytes(array $photo): ?string
    {
        // 1) Local file (legacy public disk)
        if (!empty($photo['path'])) {
            $localPath = storage_path('app/public/' . $photo['path']);
            if (file_exists($localPath)) {
                return file_get_contents($localPath);
            }
            $publicPath = public_path('storage/' . $photo['path']);
            if (file_exists($publicPath)) {
                return file_get_contents($publicPath);
            }
        }

        // 2) Storage SDK read (R2 / S3) — preferred for cloud-stored
        //    originals. Reads the un-watermarked file directly.
        if (!empty($photo['path']) && !empty($photo['storage_disk']) && $photo['storage_disk'] !== 'public') {
            try {
                $bytes = \Illuminate\Support\Facades\Storage::disk($photo['storage_disk'])->get($photo['path']);
                if (!empty($bytes)) {
                    return $bytes;
                }
            } catch (\Throwable $e) {
                Log::debug("getPhotoBytes Storage::disk read failed for photo {$photo['id']}: " . $e->getMessage());
                // fall through to URL fetch
            }
        }

        // 3) HTTP fetch of original_url (un-watermarked CDN URL —
        //    server-side only, never returned to public callers).
        if (!empty($photo['original_url'])) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(10)->get($photo['original_url']);
                if ($response->successful()) {
                    return $response->body();
                }
            } catch (\Throwable $e) {
                Log::debug("getPhotoBytes original_url fetch failed for photo {$photo['id']}: " . $e->getMessage());
                // fall through to watermarked URL
            }
        }

        // 4) Last-resort: HTTP fetch of watermarked `url`. Match
        //    accuracy degraded — kept only so the function returns
        //    something instead of null on a fully-broken row.
        if (!empty($photo['url'])) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(10)->get($photo['url']);
                if ($response->successful()) {
                    return $response->body();
                }
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        return null;
    }
}

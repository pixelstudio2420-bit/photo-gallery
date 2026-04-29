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
     * Returns array of face details.
     */
    public function detectFaces(string $imageBytes): array
    {
        try {
            $result = $this->getClient()->detectFaces([
                'Image' => ['Bytes' => $imageBytes],
                'Attributes' => ['DEFAULT'],
            ]);

            return $result->get('FaceDetails') ?? [];
        } catch (\Throwable $e) {
            Log::error('Rekognition detectFaces failed: ' . $e->getMessage());
            return [];
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
     * Get photo bytes from various sources.
     */
    private function getPhotoBytes(array $photo): ?string
    {
        // Try local file first
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

        // Try URL
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

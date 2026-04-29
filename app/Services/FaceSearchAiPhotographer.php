<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPhoto;
use Illuminate\Support\Facades\Log;

/**
 * Photographer-side Face Indexing.
 *
 * Different from the public-customer FaceSearchService (which lets
 * EVENT VISITORS upload a selfie to find their photos): this one is
 * the photographer's tool to (a) index every face in the event up
 * front, and (b) discover face groups (e.g. "12 photos contain
 * Person #3") for organizing the album.
 *
 * Reuses the existing FaceSearchService for actual Rekognition calls.
 * If Rekognition isn't configured, falls back to a no-op count via
 * GD (rough face area detection is too unreliable to trust without
 * a real ML model — we just stamp face_count=0 and tell the user
 * to configure AWS in admin settings).
 */
class FaceSearchAiPhotographer
{
    public function __construct(private FaceSearchService $existing) {}

    public function run(Event $event): array
    {
        $configured = $this->existing->isConfigured();

        $photos = $event->photos()
            ->where('status', 'active')
            ->whereNotNull('original_path')
            ->get();

        $processed = 0;
        $indexed   = 0;
        $totalFaces = 0;
        $errors    = 0;

        foreach ($photos as $photo) {
            $processed++;
            try {
                if (!$configured) {
                    // Without Rekognition we can't reliably index faces.
                    // Mark face_count=0 and move on — the photographer
                    // gets a "configure AWS" hint in the result message.
                    $photo->forceFill(['face_count' => 0])->save();
                    continue;
                }

                $count = $this->indexPhotoFaces($photo, $event);
                $photo->forceFill(['face_count' => $count])->save();
                $totalFaces += $count;
                if ($count > 0) $indexed++;
            } catch (\Throwable $e) {
                Log::warning('Face index failed photo '.$photo->id, ['err' => $e->getMessage()]);
                $errors++;
            }
        }

        return [
            'processed'   => $processed,
            'indexed'     => $indexed,
            'total_faces' => $totalFaces,
            'errors'      => $errors,
            'mode'        => $configured ? 'rekognition' : 'unconfigured',
            'configure_hint' => $configured ? null : 'AWS Rekognition ยังไม่ได้ตั้งค่า — admin ต้องใส่ AWS_ACCESS_KEY_ID + AWS_SECRET_ACCESS_KEY ใน .env เพื่อเปิดใช้',
        ];
    }

    /**
     * Use the existing FaceSearchService API to index. Returns face count.
     *
     * BUG FIX: previously this used `(int) ($result ?? 0)` as a fallback
     * for the `indexPhoto()` return value. But that method returns a
     * **string** (the AWS Rekognition face ID) when a face is found, so
     * `(int)$string` yielded 0 for every photo — the dashboard reported
     * "indexed 0 faces" even though faces WERE indexed at AWS, and the
     * BestShotAi ranker (which boosts photos with `face_count > 0`)
     * ignored every group photo.
     *
     * Now we read the authoritative count directly from the photo's
     * persisted `face_ids` column, which `FaceSearchService::indexPhoto`
     * writes after Rekognition responds. That column is JSON-cast to an
     * array, so `count()` gives the real face count regardless of which
     * indexer variant ran.
     */
    private function indexPhotoFaces(EventPhoto $photo, Event $event): int
    {
        // Method name varies across forks of this codebase — try in order.
        if (method_exists($this->existing, 'indexPhotoFace')) {
            $this->existing->indexPhotoFace($photo);
        } elseif (method_exists($this->existing, 'indexPhoto')) {
            $this->existing->indexPhoto($photo);
        } else {
            // No indexer available — leave count at 0. The public
            // face-search will still work because it indexes lazily.
            return 0;
        }

        // Re-fetch to pick up persisted face_ids written by the indexer.
        $photo->refresh();
        $faceIds = $photo->face_ids;
        if (is_string($faceIds)) {
            // Decode JSON if the model wasn't cast (defensive).
            $faceIds = json_decode($faceIds, true) ?: [];
        }
        if (is_array($faceIds)) {
            return count($faceIds);
        }
        // Truthy non-array (single id string) → 1 face.
        return $faceIds ? 1 : 0;
    }
}

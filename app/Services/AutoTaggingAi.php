<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPhoto;
use Aws\Rekognition\RekognitionClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Auto-tagging — assign content labels (e.g. "Wedding", "Beach", "Group")
 * to each photo so the photographer / customer can filter by category.
 *
 * Two paths:
 *   A. AWS Rekognition `DetectLabels` — when AWS_ACCESS_KEY_ID is set
 *      and aws/aws-sdk-php is installed. Pulls top 10 labels with > 70%
 *      confidence; stores as event_photos.ai_tags.
 *   B. Heuristic fallback — when AWS isn't configured, tags are derived
 *      from the event's category + filename keywords (e.g. "_beach_",
 *      "_indoor_") so the feature still produces SOMETHING and the
 *      photographer isn't staring at empty UI.
 *
 * Both paths produce the same shape, so callers don't care which ran.
 */
class AutoTaggingAi
{
    public function isConfigured(): bool
    {
        return !empty(env('AWS_ACCESS_KEY_ID'))
            && !empty(env('AWS_SECRET_ACCESS_KEY'))
            && class_exists(RekognitionClient::class);
    }

    public function run(Event $event): array
    {
        $photos = $event->photos()
            ->where('status', 'active')
            ->whereNotNull('original_path')
            ->get();

        $processed = 0;
        $tagged    = 0;
        $errors    = 0;
        $usedAws   = $this->isConfigured();

        $client = $usedAws ? $this->makeClient() : null;

        foreach ($photos as $photo) {
            $processed++;
            try {
                $tags = $client
                    ? $this->detectViaRekognition($client, $photo)
                    : $this->heuristicTags($photo, $event);

                if ($tags) {
                    $photo->forceFill(['ai_tags' => $tags])->save();
                    $tagged++;
                }
            } catch (\Throwable $e) {
                Log::warning('Auto-tag failed photo '.$photo->id, ['err' => $e->getMessage()]);
                $errors++;
            }
        }

        return [
            'processed' => $processed,
            'tagged'    => $tagged,
            'errors'    => $errors,
            'mode'      => $usedAws ? 'rekognition' : 'heuristic',
        ];
    }

    private function makeClient(): RekognitionClient
    {
        return new RekognitionClient([
            'region'      => env('AWS_DEFAULT_REGION', 'ap-southeast-1'),
            'version'     => 'latest',
            'credentials' => [
                'key'    => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);
    }

    private function detectViaRekognition(RekognitionClient $client, EventPhoto $photo): array
    {
        $disk = $photo->storage_disk ?? 'public';
        $bytes = Storage::disk($disk)->get($photo->original_path);
        if (!$bytes) return [];

        $resp = $client->detectLabels([
            'Image'         => ['Bytes' => $bytes],
            'MaxLabels'     => 10,
            'MinConfidence' => 70,
        ]);

        $labels = [];
        foreach (($resp['Labels'] ?? []) as $L) {
            $labels[] = $L['Name'];
        }
        return $labels;
    }

    /**
     * Heuristic fallback — use event category + filename keywords.
     * Crude but keeps the feature usable without external services.
     */
    private function heuristicTags(EventPhoto $photo, Event $event): array
    {
        $tags = [];

        // Event category becomes the first tag
        if ($event->category && $event->category->name ?? null) {
            $tags[] = $event->category->name;
        }

        // Filename keywords
        $name = strtolower($photo->original_filename ?? $photo->filename ?? '');
        $keywords = [
            'beach' => 'Beach',
            'wedding' => 'Wedding',
            'portrait' => 'Portrait',
            'group' => 'Group',
            'sport' => 'Sports',
            'concert' => 'Concert',
            'food' => 'Food',
            'nature' => 'Nature',
            'street' => 'Street',
            'sunset' => 'Sunset',
        ];
        foreach ($keywords as $needle => $label) {
            if (str_contains($name, $needle)) $tags[] = $label;
        }

        // Image dimensions hint
        if ($photo->width > $photo->height) $tags[] = 'Landscape';
        elseif ($photo->height > $photo->width) $tags[] = 'Portrait';

        return array_values(array_unique($tags));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\FaceSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DiagnosticsController extends Controller
{
    /**
     * Report AWS Rekognition index coverage for a given event. Used by the admin
     * event detail page to show "X / Y photos indexed (Z%)" at a glance.
     *
     * Returns 404 when the event does not exist, 200 otherwise — even when
     * Rekognition is not configured (so the widget can tell the admin to set
     * it up instead of silently showing 0%).
     */
    public function eventFaceCoverage(int $eventId, FaceSearchService $faceSearch): JsonResponse
    {
        $event = Event::find($eventId);
        if (!$event) {
            return response()->json(['error' => 'event_not_found'], 404);
        }

        $counts = EventPhoto::where('event_id', $eventId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_total,
                SUM(CASE WHEN status='active' AND rekognition_face_id IS NOT NULL THEN 1 ELSE 0 END) as indexed,
                SUM(CASE WHEN status='active' AND rekognition_face_id IS NULL THEN 1 ELSE 0 END) as pending
            ")
            ->first();

        $active  = (int) ($counts->active_total ?? 0);
        $indexed = (int) ($counts->indexed ?? 0);
        $pending = (int) ($counts->pending ?? 0);
        $pct     = $active > 0 ? round(($indexed / $active) * 100, 1) : 0.0;

        return response()->json([
            'event_id'             => $eventId,
            'event_name'           => $event->name,
            'rekognition_ready'    => $faceSearch->isConfigured(),
            'face_search_enabled'  => (bool) ($event->face_search_enabled ?? true),
            'collection_id'        => 'event-' . $eventId,
            'total_photos'         => (int) ($counts->total ?? 0),
            'active_photos'        => $active,
            'indexed_photos'       => $indexed,
            'pending_photos'       => $pending,
            'coverage_pct'         => $pct,
            'reindex_cmd'          => "php artisan rekognition:reindex-event {$eventId}",
        ]);
    }

    public function awsRekognition(FaceSearchService $faceSearch): JsonResponse
    {
        $configured = $faceSearch->isConfigured();

        if (!$configured) {
            return response()->json([
                'configured' => false,
                'connected'  => false,
                'region'     => null,
                'error'      => 'AWS credentials not configured. Set aws_key and aws_secret in Settings > AWS.',
            ]);
        }

        try {
            $region = \App\Models\AppSetting::get('aws_region', config('services.aws.region', 'ap-southeast-1'));

            $client = new \Aws\Rekognition\RekognitionClient([
                'version' => 'latest',
                'region'  => $region,
                'credentials' => [
                    'key'    => \App\Models\AppSetting::get('aws_key', config('services.aws.key', '')),
                    'secret' => \App\Models\AppSetting::get('aws_secret', config('services.aws.secret', '')),
                ],
            ]);

            $result = $client->listCollections(['MaxResults' => 1]);

            return response()->json([
                'configured'  => true,
                'connected'   => true,
                'region'      => $region,
                'collections' => count($result['CollectionIds'] ?? []),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'configured' => true,
                'connected'  => false,
                'region'     => $region ?? null,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}

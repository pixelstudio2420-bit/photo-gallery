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

    /**
     * Diagnose AWS Rekognition connectivity end-to-end.
     *
     * Runs TWO independent probes:
     *   1. ListCollections  — verifies credentials, region, and the
     *                         basic IAM permission `rekognition:ListCollections`.
     *   2. DetectFaces      — verifies the SPECIFIC permission used
     *                         by the public face-search endpoint
     *                         (`rekognition:DetectFaces`). Surprisingly common
     *                         to have ListCollections OK but DetectFaces 403
     *                         when an IAM policy was hand-crafted.
     *
     * For each failure surfaces the exception **class** and (when present)
     * the AWS error code — so the admin sees `AccessDeniedException`
     * vs `InvalidSignatureException` vs `UnrecognizedClientException`
     * without needing SSH access to laravel.log on Laravel Cloud.
     *
     * Credential lookup matches FaceSearchService priority:
     *   aws_access_key_id (new admin form) → aws_key (legacy) → config/.env
     */
    public function awsRekognition(FaceSearchService $faceSearch): JsonResponse
    {
        $key    = (string) (\App\Models\AppSetting::get('aws_access_key_id', '')
                  ?: \App\Models\AppSetting::get('aws_key', '')
                  ?: config('services.aws.key', ''));
        $secret = (string) (\App\Models\AppSetting::get('aws_secret_access_key', '')
                  ?: \App\Models\AppSetting::get('aws_secret', '')
                  ?: config('services.aws.secret', ''));
        $region = (string) (\App\Models\AppSetting::get('aws_default_region', '')
                  ?: \App\Models\AppSetting::get('aws_region', '')
                  ?: config('services.aws.region', 'ap-southeast-1'));

        $configured = $key !== '' && $secret !== '';
        $keyHint    = $key !== '' ? substr($key, 0, 4) . '...' . substr($key, -4) : null;

        if (!$configured) {
            return response()->json([
                'configured' => false,
                'connected'  => false,
                'region'     => $region,
                'key_hint'   => null,
                'error'      => 'AWS credentials not configured. Set aws_access_key_id and aws_secret_access_key in Settings > AWS.',
                'tests'      => [
                    'list_collections' => ['ok' => null, 'skipped' => 'unconfigured'],
                    'detect_faces'     => ['ok' => null, 'skipped' => 'unconfigured'],
                ],
            ]);
        }

        $client = new \Aws\Rekognition\RekognitionClient([
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => ['key' => $key, 'secret' => $secret],
        ]);

        $tests = [
            'list_collections' => $this->probeListCollections($client),
            'detect_faces'     => $this->probeDetectFaces($client),
        ];

        $allOk = ($tests['list_collections']['ok'] ?? false)
              && ($tests['detect_faces']['ok'] ?? false);

        return response()->json([
            'configured' => true,
            'connected'  => $allOk,
            'region'     => $region,
            'key_hint'   => $keyHint,
            'tests'      => $tests,
            'hint'       => $allOk ? null : $this->hintFor($tests),
        ]);
    }

    /**
     * Probe rekognition:ListCollections — auth + region + basic IAM.
     */
    private function probeListCollections(\Aws\Rekognition\RekognitionClient $client): array
    {
        try {
            $result = $client->listCollections(['MaxResults' => 1]);
            return [
                'ok'          => true,
                'collections' => count($result['CollectionIds'] ?? []),
            ];
        } catch (\Throwable $e) {
            return $this->errorPayload($e);
        }
    }

    /**
     * Probe rekognition:DetectFaces — the EXACT call the public face-search
     * endpoint makes. A 100x100 grey JPEG is plenty for AWS to accept the
     * request and either return 0 faces (success) or reject on permissions.
     */
    private function probeDetectFaces(\Aws\Rekognition\RekognitionClient $client): array
    {
        if (!function_exists('imagecreatetruecolor')) {
            return [
                'ok'      => false,
                'skipped' => 'gd_not_loaded',
                'message' => 'PHP GD extension is not loaded — cannot synthesize a test image.',
            ];
        }

        $img = imagecreatetruecolor(100, 100);
        imagefill($img, 0, 0, imagecolorallocate($img, 128, 128, 128));
        ob_start();
        imagejpeg($img, null, 80);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        try {
            $result = $client->detectFaces([
                'Image'      => ['Bytes' => $bytes],
                'Attributes' => ['DEFAULT'],
            ]);
            return [
                'ok'    => true,
                'faces' => count($result->get('FaceDetails') ?? []),
                'note'  => 'Tested with a 100x100 grey image. 0 faces is the correct result.',
            ];
        } catch (\Throwable $e) {
            return $this->errorPayload($e);
        }
    }

    /**
     * Normalize an AWS exception into a UI-friendly payload.
     */
    private function errorPayload(\Throwable $e): array
    {
        $awsCode = method_exists($e, 'getAwsErrorCode') ? $e->getAwsErrorCode() : null;
        $awsMsg  = method_exists($e, 'getAwsErrorMessage') ? $e->getAwsErrorMessage() : null;
        $http    = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : null;

        return [
            'ok'             => false,
            'class'          => get_class($e),
            'aws_error_code' => $awsCode,
            'aws_error_msg'  => $awsMsg,
            'http_status'    => $http,
            'message'        => $e->getMessage(),
        ];
    }

    /**
     * Map common AWS error codes to actionable hints (admin sees this
     * inline so they don't need to Google the error).
     */
    private function hintFor(array $tests): ?string
    {
        foreach ($tests as $probe) {
            $code = $probe['aws_error_code'] ?? null;
            if (!$code) continue;
            switch ($code) {
                case 'InvalidSignatureException':
                case 'SignatureDoesNotMatch':
                    return 'aws_secret_access_key ผิด — ตรวจสอบและบันทึกใหม่ใน Settings > AWS.';
                case 'UnrecognizedClientException':
                case 'InvalidAccessKeyId':
                    return 'aws_access_key_id ไม่มีอยู่จริงหรือถูกลบ — สร้าง access key ใหม่ใน IAM แล้วบันทึก.';
                case 'AccessDeniedException':
                    return 'IAM policy ไม่มี rekognition:DetectFaces / ListCollections — เพิ่ม policy "AmazonRekognitionFullAccess" ให้ user นี้.';
                case 'ExpiredToken':
                case 'TokenRefreshRequired':
                    return 'AWS keys หมดอายุ — สร้างใหม่ใน IAM.';
                case 'ResourceNotFoundException':
                    return 'region อาจตั้งผิด collection อยู่อีก region — ตรวจสอบ aws_default_region.';
                case 'ThrottlingException':
                    return 'AWS rate-limit — รอสักครู่แล้วลองใหม่.';
            }
        }
        return 'ดู class/message ด้านบนเพื่อตรวจสอบ — มักเป็น keys/region/IAM.';
    }
}

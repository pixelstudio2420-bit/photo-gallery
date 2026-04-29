<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Event;
use App\Services\GoogleDriveService;
use App\Services\LineNotifyService;
use App\Services\MailService;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminApiController extends Controller
{
    /**
     * POST /api/admin/email-test
     *
     * Supported actions:
     *   test_connection — attempt a TCP connection to the configured SMTP server
     *   send_test       — dispatch a test email via MailService
     */
    public function emailTest(Request $request): JsonResponse
    {
        $action = $request->input('action', 'send_test');

        if ($action === 'test_connection') {
            return $this->handleTestConnection();
        }

        if ($action === 'send_test') {
            return $this->handleSendTestEmail($request);
        }

        return response()->json([
            'success' => false,
            'message' => "Unknown action '{$action}'. Use 'test_connection' or 'send_test'.",
        ], 422);
    }

    private function handleTestConnection(): JsonResponse
    {
        try {
            $driver     = AppSetting::get('mail_driver', config('mail.default', 'log'));
            $host       = AppSetting::get('smtp_host', '127.0.0.1');
            $port       = (int) AppSetting::get('smtp_port', 587);
            $encryption = AppSetting::get('smtp_encryption', 'tls');
            $username   = AppSetting::get('smtp_username', '');
            $password   = AppSetting::get('smtp_password', '');

            // Override config so the Mailer manager uses DB-sourced values
            config([
                'mail.default'                 => $driver,
                'mail.mailers.smtp.host'       => $host,
                'mail.mailers.smtp.port'       => $port,
                'mail.mailers.smtp.encryption' => $encryption ?: null,
                'mail.mailers.smtp.username'   => $username,
                'mail.mailers.smtp.password'   => $password,
            ]);

            if ($driver === 'smtp') {
                $timeout = 5;
                $errno   = 0;
                $errstr  = '';
                $scheme  = $encryption === 'ssl' ? 'ssl://' : '';
                $fp      = @fsockopen("{$scheme}{$host}", $port, $errno, $errstr, $timeout);

                if ($fp === false) {
                    return response()->json([
                        'success' => false,
                        'message' => "Cannot reach SMTP server {$host}:{$port} — {$errstr} (error {$errno})",
                    ]);
                }

                fclose($fp);

                return response()->json([
                    'success' => true,
                    'message' => "Successfully connected to SMTP server {$host}:{$port}.",
                    'driver'  => $driver,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Mail driver '{$driver}' is configured. No TCP test required.",
                'driver'  => $driver,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ]);
        }
    }

    private function handleSendTestEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $to          = $request->input('email');
        $mailService = app(MailService::class);
        $result      = $mailService->sendTestEmail($to);

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => "Test email dispatched successfully to {$to}.",
            ]);
        }

        $enabled = AppSetting::get('mail_enabled', '0');
        if ($enabled !== '1') {
            return response()->json([
                'success' => false,
                'message' => 'Mail is disabled in settings (mail_enabled ≠ 1). Email was skipped.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to send the test email. Check the Email Logs for details.',
        ]);
    }

    public function lineTest(Request $request)
    {
        $lineService = app(LineNotifyService::class);
        $action = $request->input('action');

        // 'notify' / 'admin' — both route to the admin-alerts test path.
        // Historically this used LINE Notify; now it pushes to admin
        // user IDs via Messaging API multicast (Notify died 31 Mar 2025).
        if ($action === 'notify' || $action === 'admin') {
            $result = $lineService->testNotify();
            return response()->json($result);
        }

        if ($action === 'messaging') {
            // ── Direct mode ────────────────────────────────────────────────
            // Admin pastes a raw LINE User ID (U...) → push directly without
            // requiring prior OAuth linking. Useful when the channel is a
            // Messaging API channel (no LINE Login support) so auth_social_logins
            // will never be populated via /auth/line.
            $directLineUserId = trim((string) $request->input('line_user_id', ''));
            if ($directLineUserId !== '') {
                $result = $lineService->testMessagingDirect($directLineUserId);
                return response()->json($result);
            }

            // ── Linked-user mode ───────────────────────────────────────────
            // Helper: verify a user actually has a LINE account linked.
            $hasLineLink = function ($uid) {
                if (!is_numeric($uid)) return false;
                return DB::table('auth_social_logins')
                    ->where('provider', 'line')
                    ->where('user_id', (int) $uid)
                    ->exists();
            };

            // Resolve target user — ONLY accept a user who actually has LINE linked.
            //   1. Explicit user_id from the request payload (if linked)
            //   2. The currently logged-in admin (if linked)
            //   3. Any user that has a linked LINE account in auth_social_logins
            $userId = null;

            $candidate = $request->input('user_id');
            if ($hasLineLink($candidate)) {
                $userId = (int) $candidate;
            }

            if (!$userId) {
                $adminId = optional($request->user('admin'))->id
                        ?? optional($request->user())->id;
                if ($hasLineLink($adminId)) {
                    $userId = (int) $adminId;
                }
            }

            if (!$userId) {
                $linkedLineUserId = DB::table('auth_social_logins')
                    ->where('provider', 'line')
                    ->orderBy('user_id')
                    ->value('user_id');

                if ($linkedLineUserId) {
                    $userId = (int) $linkedLineUserId;
                }
            }

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบผู้ใช้ที่เชื่อมต่อ LINE — กรอก "LINE User ID (U...)" เพื่อทดสอบแบบตรง หรือ Login ด้วย LINE อย่างน้อย 1 ครั้งก่อน',
                ], 422);
            }

            $result = $lineService->testMessaging($userId);
            return response()->json($result);
        }

        return response()->json(['success' => false, 'message' => 'Invalid action']);
    }

    // ─── AWS S3 Connection Test ──────────────────────────────────────────────

    public function awsTest(Request $request): JsonResponse
    {
        $action = $request->input('action', 's3');

        if ($action === 's3') {
            return $this->handleS3Test();
        }

        if ($action === 'ses') {
            return $this->handleSesTest($request);
        }

        if ($action === 'cloudfront') {
            return $this->handleCloudFrontTest();
        }

        return response()->json(['success' => false, 'message' => "Unknown action: {$action}"]);
    }

    private function handleS3Test(): JsonResponse
    {
        try {
            $bucket = AppSetting::get('aws_s3_bucket', '') ?: config('filesystems.disks.s3.bucket');
            $region = AppSetting::get('aws_default_region', '') ?: config('filesystems.disks.s3.region', 'us-east-1');
            $key    = AppSetting::get('aws_access_key_id', '') ?: config('filesystems.disks.s3.key');
            $secret = AppSetting::get('aws_secret_access_key', '') ?: config('filesystems.disks.s3.secret');

            if (empty($bucket) || empty($key) || empty($secret)) {
                return response()->json([
                    'success' => false,
                    'message' => 'กรุณากรอก AWS Access Key, Secret Key และ Bucket Name ก่อนทดสอบ',
                ]);
            }

            // Apply runtime config
            config([
                'filesystems.disks.s3.key'    => $key,
                'filesystems.disks.s3.secret' => $secret,
                'filesystems.disks.s3.region' => $region,
                'filesystems.disks.s3.bucket' => $bucket,
            ]);

            // Write a test file
            $testPath = '_test/connection-test-' . time() . '.txt';
            Storage::disk('s3')->put($testPath, 'Connection test from ' . config('app.name'));

            // Verify it exists
            $exists = Storage::disk('s3')->exists($testPath);

            // Clean up
            Storage::disk('s3')->delete($testPath);

            if ($exists) {
                return response()->json([
                    'success' => true,
                    'message' => "เชื่อมต่อ S3 สำเร็จ! Bucket: {$bucket}, Region: {$region}",
                    'bucket'  => $bucket,
                    'region'  => $region,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'เขียนไฟล์ทดสอบไม่สำเร็จ ตรวจสอบ Permissions ของ Bucket',
            ]);
        } catch (\Throwable $e) {
            Log::error('S3 test failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'S3 connection failed: ' . $e->getMessage(),
            ]);
        }
    }

    private function handleSesTest(Request $request): JsonResponse
    {
        try {
            $toEmail = $request->input('email', AppSetting::get('aws_ses_from_email', ''));
            if (empty($toEmail)) {
                return response()->json([
                    'success' => false,
                    'message' => 'กรุณากรอกอีเมลสำหรับทดสอบ',
                ]);
            }

            $region = AppSetting::get('aws_ses_region', '') ?: AppSetting::get('aws_default_region', 'us-east-1');
            $key    = AppSetting::get('aws_access_key_id', '');
            $secret = AppSetting::get('aws_secret_access_key', '');

            if (empty($key) || empty($secret)) {
                return response()->json([
                    'success' => false,
                    'message' => 'กรุณากรอก AWS Credentials ก่อนทดสอบ SES',
                ]);
            }

            config([
                'mail.default'      => 'ses',
                'services.ses.key'    => $key,
                'services.ses.secret' => $secret,
                'services.ses.region' => $region,
            ]);

            $fromEmail = AppSetting::get('aws_ses_from_email', config('mail.from.address'));
            $fromName  = AppSetting::get('aws_ses_from_name', config('mail.from.name'));

            if ($fromEmail) {
                config(['mail.from.address' => $fromEmail, 'mail.from.name' => $fromName]);
            }

            \Illuminate\Support\Facades\Mail::raw(
                'ทดสอบส่งอีเมลจาก ' . config('app.name') . ' ผ่าน AWS SES สำเร็จ!',
                function ($message) use ($toEmail) {
                    $message->to($toEmail)->subject('[Test] AWS SES - ' . config('app.name'));
                }
            );

            return response()->json([
                'success' => true,
                'message' => "ส่งอีเมลทดสอบไปที่ {$toEmail} สำเร็จ!",
            ]);
        } catch (\Throwable $e) {
            Log::error('SES test failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'SES test failed: ' . $e->getMessage(),
            ]);
        }
    }

    private function handleCloudFrontTest(): JsonResponse
    {
        try {
            $domain = AppSetting::get('aws_cloudfront_domain', '');
            $distId = AppSetting::get('aws_cloudfront_distribution_id', '');

            if (empty($domain)) {
                return response()->json([
                    'success' => false,
                    'message' => 'กรุณากรอก CloudFront Domain ก่อนทดสอบ',
                ]);
            }

            // Test with a simple HTTP request to the domain
            $testUrl = "https://{$domain}/";
            $ch = curl_init($testUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_NOBODY => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 500) {
                return response()->json([
                    'success' => true,
                    'message' => "CloudFront เชื่อมต่อสำเร็จ! Domain: {$domain} (HTTP {$httpCode})",
                    'distribution_id' => $distId,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $error ?: "CloudFront returned HTTP {$httpCode}",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'CloudFront test failed: ' . $e->getMessage(),
            ]);
        }
    }

    // ─── Google Drive Connection Test ───────────────────────────────────────

    /**
     * POST /api/admin/drive-test
     *
     * Test Google Drive API connection by verifying the configured API key.
     */
    public function driveTest(Request $request): JsonResponse
    {
        try {
            $drive  = new GoogleDriveService();
            $result = $drive->testConnection();

            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('Drive test failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Drive test failed: ' . $e->getMessage(),
            ]);
        }
    }

    // ─── Google Drive Queue Management ──────────────────────────────────────

    /**
     * GET|POST /api/admin/drive-queue
     *
     * Handle queue management AJAX calls.
     *
     * Actions:
     *   status    — Return queue stats + recent jobs
     *   process   — Process 1 job from queue
     *   sync      — Add sync job for a specific event_id
     *   sync_all  — Queue sync for all active events with drive_folder_id
     *   retry     — Retry a specific failed job
     *   retry_all — Retry all failed jobs
     *   cancel    — Cancel a pending/failed job
     *   clear     — Delete completed jobs older than 7 days
     */
    public function driveQueue(Request $request): JsonResponse
    {
        $action = $request->input('action', 'status');
        $queue  = new QueueService();

        try {
            switch ($action) {

                // ─── Status: queue stats + recent jobs ──────────────
                case 'status':
                    $stats = $queue->getStatus();
                    $jobs  = $queue->getRecentJobs(20);

                    return response()->json([
                        'success' => true,
                        'stats'   => $stats,
                        'jobs'    => $jobs,
                    ]);

                // ─── Process: run the next pending job ──────────────
                case 'process':
                    $processed = $queue->processNext();

                    return response()->json([
                        'success' => true,
                        'processed' => $processed,
                        'message'   => $processed
                            ? 'Processed 1 job from queue.'
                            : 'No pending jobs in queue.',
                    ]);

                // ─── Sync: queue sync for a specific event ──────────
                case 'sync':
                    $eventId = (int) $request->input('event_id', 0);
                    if (!$eventId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Missing event_id parameter.',
                        ], 422);
                    }

                    $event = Event::find($eventId);
                    if (!$event || empty($event->drive_folder_id)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Event not found or has no Drive folder configured.',
                        ], 404);
                    }

                    $jobId = $queue->dispatch('sync_photos', $eventId, [
                        'drive_folder_id' => $event->drive_folder_id,
                    ]);

                    return response()->json([
                        'success' => $jobId > 0,
                        'job_id'  => $jobId,
                        'message' => $jobId > 0
                            ? "Sync job queued for event #{$eventId}."
                            : 'Failed to queue sync job (may already be pending).',
                    ]);

                // ─── Sync All: queue sync for every active event ────
                case 'sync_all':
                    $events = Event::whereNotNull('drive_folder_id')
                        ->where('drive_folder_id', '!=', '')
                        ->whereIn('status', ['active', 'published'])
                        ->get(['id', 'drive_folder_id']);

                    $queued = 0;
                    foreach ($events as $event) {
                        $jobId = $queue->dispatch('sync_photos', $event->id, [
                            'drive_folder_id' => $event->drive_folder_id,
                        ]);
                        if ($jobId > 0) {
                            $queued++;
                        }
                    }

                    return response()->json([
                        'success' => true,
                        'queued'  => $queued,
                        'total'   => $events->count(),
                        'message' => "Queued sync for {$queued} of {$events->count()} active events.",
                    ]);

                // ─── Retry: retry a specific failed job ─────────────
                case 'retry':
                    $jobId = (int) $request->input('job_id', 0);
                    if (!$jobId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Missing job_id parameter.',
                        ], 422);
                    }

                    $retried = $queue->retry($jobId);

                    return response()->json([
                        'success' => $retried,
                        'message' => $retried
                            ? "Job #{$jobId} has been re-queued."
                            : "Failed to retry job #{$jobId} (may not be in 'failed' status).",
                    ]);

                // ─── Retry All: retry all failed jobs ───────────────
                case 'retry_all':
                    if (!Schema::hasTable('sync_queue')) {
                        return response()->json(['success' => true, 'retried' => 0]);
                    }

                    $retried = DB::table('sync_queue')
                        ->where('status', 'failed')
                        ->update([
                            'status'        => 'pending',
                            'attempts'      => 0,
                            'error_message' => null,
                        ]);

                    return response()->json([
                        'success' => true,
                        'retried' => $retried,
                        'message' => "Re-queued {$retried} failed job(s).",
                    ]);

                // ─── Cancel: delete a pending/failed job ────────────
                case 'cancel':
                    $jobId = (int) $request->input('job_id', 0);
                    if (!$jobId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Missing job_id parameter.',
                        ], 422);
                    }

                    if (!Schema::hasTable('sync_queue')) {
                        return response()->json(['success' => false, 'message' => 'Queue table not found.']);
                    }

                    $deleted = DB::table('sync_queue')
                        ->where('id', $jobId)
                        ->whereIn('status', ['pending', 'failed'])
                        ->delete();

                    return response()->json([
                        'success' => $deleted > 0,
                        'message' => $deleted > 0
                            ? "Job #{$jobId} cancelled."
                            : "Could not cancel job #{$jobId} (may be processing or already completed).",
                    ]);

                // ─── Clear: delete old completed jobs ───────────────
                case 'clear':
                    $days    = (int) $request->input('days', 7);
                    $cleaned = $queue->cleanup($days);

                    return response()->json([
                        'success' => true,
                        'cleaned' => $cleaned,
                        'message' => "Deleted {$cleaned} completed job(s) older than {$days} days.",
                    ]);

                default:
                    return response()->json([
                        'success' => false,
                        'message' => "Unknown action: {$action}. Supported: status, process, sync, sync_all, retry, retry_all, cancel, clear.",
                    ], 422);
            }
        } catch (\Throwable $e) {
            Log::error("driveQueue action '{$action}' failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Queue operation failed: " . $e->getMessage(),
            ]);
        }
    }
}

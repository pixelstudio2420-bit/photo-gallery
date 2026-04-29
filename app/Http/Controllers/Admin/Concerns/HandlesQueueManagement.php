<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\AppSetting;
use App\Services\QueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QUEUE MANAGEMENT (sync_queue + failed job retry)
 *
 * Extracted from SettingsController. Method signatures and route names
 * unchanged — trait is `use`d by the parent controller.
 *
 * Routes touched:
 *   • admin.settings.queue           — queue()
 *   • admin.settings.queue.update    — updateQueue()
 *   • admin.settings.queue.process   — processQueue()
 *   • admin.settings.queue.retry     — retryJob()
 *   • admin.settings.queue.clear     — clearQueue()
 */
trait HandlesQueueManagement
{
    public function queue()
    {
        $settings = AppSetting::getAll();

        $queueService = app(QueueService::class);
        $rawStats     = $queueService->getStatus();

        // Map 'processing' key; keep backward compat with view which uses 'running'
        $queueStats = [
            'pending'   => $rawStats['pending'],
            'running'   => $rawStats['processing'],
            'completed' => $rawStats['completed'],
            'failed'    => $rawStats['failed'],
        ];

        $recentJobs = $queueService->getRecentJobs(50);

        return view('admin.settings.queue', compact('settings', 'queueStats', 'recentJobs'));
    }

    public function updateQueue(Request $request)
    {
        // Save settings (collect present keys, then bulk-write).
        $settingKeys = ['queue_auto_sync', 'queue_sync_interval_minutes', 'queue_max_retries'];
        $items = [];
        foreach ($settingKeys as $key) {
            if ($request->has($key)) {
                $items[$key] = $request->input($key) ?? '';
            }
        }
        if ($items) {
            AppSetting::setMany($items);
        }

        // Handle special actions
        $action = $request->input('action');

        try {
            if ($action === 'retry_all' && Schema::hasTable('sync_queue')) {
                DB::table('sync_queue')->where('status', 'failed')->update(['status' => 'pending', 'updated_at' => now()]);
                return back()->with('success', 'รีเซ็ต jobs ที่ failed ทั้งหมดเป็น pending แล้ว');
            }

            if ($action === 'clear_old' && Schema::hasTable('sync_queue')) {
                DB::table('sync_queue')
                    ->where('status', 'completed')
                    ->where('created_at', '<', now()->subDays(30))
                    ->delete();
                return back()->with('success', 'ลบ jobs ที่เสร็จแล้วและเก่ากว่า 30 วันแล้ว');
            }

            if ($action === 'sync_event' && Schema::hasTable('sync_queue')) {
                $eventId = $request->input('event_id');
                if ($eventId) {
                    DB::table('sync_queue')->insert([
                        'event_id'   => $eventId,
                        'job_type'   => 'sync',
                        'status'     => 'pending',
                        'attempts'   => 0,
                        'result'     => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    return back()->with('success', 'เพิ่ม sync job สำหรับ event แล้ว');
                }
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        return back()->with('success', 'บันทึก Queue settings แล้ว');
    }

    /**
     * Trigger manual processing of pending queue jobs.
     */
    public function processQueue(Request $request)
    {
        try {
            $queueService = app(QueueService::class);
            $limit        = (int) $request->input('limit', 10);
            $processed    = 0;

            for ($i = 0; $i < $limit; $i++) {
                if (!$queueService->processNext()) {
                    break;
                }
                $processed++;
            }

            return back()->with('success', "Processed {$processed} job(s) successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Error processing queue: ' . $e->getMessage());
        }
    }

    /**
     * Retry a specific failed job.
     */
    public function retryJob(int $id)
    {
        try {
            $queueService = app(QueueService::class);
            $result = $queueService->retry($id);

            if ($result) {
                return back()->with('success', "Job #{$id} has been reset to pending.");
            }

            return back()->with('error', "Job #{$id} not found or is not in failed status.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Error retrying job: ' . $e->getMessage());
        }
    }

    /**
     * Clear completed jobs older than 7 days.
     */
    public function clearQueue()
    {
        try {
            $queueService = app(QueueService::class);
            $deleted = $queueService->cleanup(7);

            return back()->with('success', "Cleared {$deleted} completed job(s) older than 7 days.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Error clearing queue: ' . $e->getMessage());
        }
    }
}

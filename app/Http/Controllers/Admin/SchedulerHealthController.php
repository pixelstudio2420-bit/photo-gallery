<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SchedulerHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SchedulerHealthController extends Controller
{
    public function __construct(private SchedulerHealthService $svc) {}

    public function index()
    {
        $snapshot = $this->svc->snapshot();
        return view('admin.scheduler.index', compact('snapshot'));
    }

    public function retry(Request $request, string $uuid)
    {
        $request->validate(['uuid' => 'nullable']);
        try {
            \Artisan::call('queue:retry', ['id' => [$uuid]]);
            return back()->with('success', 'Requeued ' . $uuid);
        } catch (\Throwable $e) {
            Log::error('Queue retry failed', ['uuid' => $uuid, 'err' => $e->getMessage()]);
            return back()->with('error', 'Retry failed: ' . $e->getMessage());
        }
    }

    public function retryAll()
    {
        try {
            \Artisan::call('queue:retry', ['id' => ['all']]);
            return back()->with('success', 'Requeued all failed jobs');
        } catch (\Throwable $e) {
            return back()->with('error', 'Retry all failed: ' . $e->getMessage());
        }
    }

    public function forget(string $uuid)
    {
        if (!Schema::hasTable('failed_jobs')) return back()->with('error', 'failed_jobs table missing');
        DB::table('failed_jobs')->where('uuid', $uuid)->delete();
        return back()->with('success', 'Removed from failed jobs');
    }

    public function flushFailed()
    {
        if (!Schema::hasTable('failed_jobs')) return back()->with('error', 'failed_jobs table missing');
        $n = DB::table('failed_jobs')->count();
        DB::table('failed_jobs')->truncate();
        return back()->with('success', 'ล้าง failed_jobs แล้ว (' . $n . ' rows)');
    }
}

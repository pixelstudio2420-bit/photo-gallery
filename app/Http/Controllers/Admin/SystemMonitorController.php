<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin → System Monitor
 *
 * Serves two pages + two JSON APIs:
 *   GET  /admin/system               Dashboard (storage, server, data, downloads)
 *   GET  /admin/system/readiness     Production-readiness scorecard
 *   GET  /admin/system/api/snapshot  Full metrics JSON (dashboard polls this)
 *   GET  /admin/system/api/readiness Readiness JSON
 */
class SystemMonitorController extends Controller
{
    public function dashboard(SystemMonitorService $mon)
    {
        $snapshot = $mon->snapshot();
        return view('admin.system.dashboard', compact('snapshot'));
    }

    public function readiness(SystemMonitorService $mon)
    {
        $readiness = $mon->readiness();
        return view('admin.system.readiness', compact('readiness'));
    }

    public function apiSnapshot(SystemMonitorService $mon): JsonResponse
    {
        return response()->json($mon->snapshot());
    }

    public function apiReadiness(SystemMonitorService $mon): JsonResponse
    {
        return response()->json($mon->readiness());
    }

    /**
     * Flush the sysmon cache — forces next dashboard call to re-aggregate.
     */
    public function refresh(Request $request)
    {
        foreach (['sysmon:db', 'sysmon:storage', 'sysmon:downloads', 'sysmon:data'] as $key) {
            cache()->forget($key);
        }
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', 'รีเฟรชข้อมูลแล้ว');
    }
}

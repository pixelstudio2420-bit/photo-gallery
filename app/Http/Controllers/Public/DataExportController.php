<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\DataExportRequest;
use App\Support\PlanResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DataExportController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        $requests = DataExportRequest::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return view('public.data-export.index', compact('requests'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        $data = $request->validate([
            'request_type' => 'required|in:export,delete',
            'reason'       => 'nullable|string|max:500',
        ]);

        $hasOpen = DataExportRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        if ($hasOpen) {
            return back()->with('error', 'คุณมีคำขอที่ยังค้างอยู่แล้ว — รออนุมัติก่อน');
        }

        $req = DataExportRequest::create([
            'user_id'      => $user->id,
            'request_type' => $data['request_type'],
            'reason'       => $data['reason'] ?? null,
            'status'       => 'pending',
        ]);

        // Record the metered request so plan_caps.export.run enforces the
        // monthly cap (e.g. 1 for Free, 10 for Starter, 50 for Pro).
        // Note: this fires for the REQUEST submission — the actual export
        // happens async in an admin-approved job. We meter on submission
        // so even cancelled requests count against the cap (prevents
        // burst-cancel abuse where a user spams 1000 cancelled requests
        // to hide one real one).
        // DataExport bills BOTH photographer + consumer-storage users —
        // PlanResolver::resolveCode handles the photographer-first /
        // storage-second fallback chain in one place.
        $planCode = PlanResolver::resolveCode($user);
        \App\Services\Usage\UsageMeter::record(
            userId:   (int) $user->id,
            planCode: $planCode,
            resource: 'export.run',
            units:    1,
            metadata: ['request_id' => $req->id, 'request_type' => $data['request_type']],
        );

        return back()->with('success', 'ส่งคำขอแล้ว — แอดมินจะดำเนินการภายใน 7 วัน');
    }

    /**
     * User-facing download via the one-time token.
     */
    public function download(Request $request, string $token)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        $req = DataExportRequest::where('user_id', $user->id)
            ->where('download_token', $token)
            ->firstOrFail();

        if (!$req->isReady()) {
            return back()->with('error', 'ไฟล์หมดอายุแล้ว');
        }

        $disk = $req->file_disk ?: 'local';
        if (!Storage::disk($disk)->exists($req->file_path)) {
            return back()->with('error', 'ไฟล์หาย');
        }
        return Storage::disk($disk)->download($req->file_path);
    }

    public function cancel(DataExportRequest $request)
    {
        $user = Auth::user();
        if (!$user || $request->user_id !== $user->id) abort(403);
        if ($request->status !== 'pending') return back()->with('error', 'ไม่สามารถยกเลิกคำขอที่ดำเนินการไปแล้ว');
        $request->update(['status' => 'cancelled']);
        return back()->with('success', 'ยกเลิกคำขอแล้ว');
    }
}

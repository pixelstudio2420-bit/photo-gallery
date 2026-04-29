<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataExportRequest;
use App\Services\DataExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DataExportController extends Controller
{
    public function __construct(private DataExportService $svc) {}

    public function index()
    {
        $requests = DataExportRequest::with(['user:id,email,first_name,last_name'])
            ->orderByDesc('created_at')
            ->paginate(25);

        $counts = [
            'pending'    => DataExportRequest::where('status', 'pending')->count(),
            'processing' => DataExportRequest::where('status', 'processing')->count(),
            'ready'      => DataExportRequest::where('status', 'ready')->count(),
            'rejected'   => DataExportRequest::where('status', 'rejected')->count(),
        ];

        return view('admin.data-export.index', compact('requests', 'counts'));
    }

    public function show(DataExportRequest $request)
    {
        $request->load('user', 'processor');
        return view('admin.data-export.show', compact('request'));
    }

    public function process(Request $httpReq, DataExportRequest $request)
    {
        if (in_array($request->status, ['ready', 'rejected', 'cancelled'], true)) {
            return back()->with('error', 'คำขอนี้ดำเนินการไปแล้ว');
        }
        $adminId = Auth::guard('admin')->id();
        $this->svc->process($request, $adminId);
        return back()->with('success', 'สร้างไฟล์ export เรียบร้อย');
    }

    public function reject(Request $httpReq, DataExportRequest $request)
    {
        $data = $httpReq->validate([
            'admin_note' => ['required', 'string', 'max:500'],
        ]);
        $request->update([
            'status'       => 'rejected',
            'admin_note'   => $data['admin_note'],
            'processed_at' => now(),
            'processed_by' => Auth::guard('admin')->id(),
        ]);
        return back()->with('success', 'ปฏิเสธคำขอแล้ว');
    }

    public function download(DataExportRequest $request)
    {
        if (!$request->isReady()) {
            return back()->with('error', 'ไฟล์ยังไม่พร้อมหรือหมดอายุแล้ว');
        }
        $disk = $request->file_disk ?: 'local';
        if (!Storage::disk($disk)->exists($request->file_path)) {
            return back()->with('error', 'ไฟล์หาย — กรุณาสร้างใหม่');
        }
        return Storage::disk($disk)->download($request->file_path);
    }

    public function destroy(DataExportRequest $request)
    {
        $this->svc->deleteFile($request);
        $request->delete();
        return redirect()->route('admin.data-export.index')->with('success', 'ลบคำขอแล้ว');
    }
}

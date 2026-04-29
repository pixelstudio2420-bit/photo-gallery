<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserFile;
use App\Services\FileManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Admin monitoring for user-uploaded files.
 *
 * Operators need this for:
 *   • abuse investigation (file is CSAM / DMCA / malware)
 *   • storage cost diagnostics (what's actually stored?)
 *   • account support ("I can't find my file")
 *
 * Admins can soft-delete (takedown) or permanently purge files but can't
 * view content directly — they get a download URL same as the owner.
 */
class UserFilesController extends Controller
{
    public function __construct(private FileManagerService $fm) {}

    public function index(Request $request): View
    {
        $search  = trim((string) $request->string('q'));
        $type    = trim((string) $request->string('type'));
        $trashed = (bool) $request->boolean('trashed');
        $shared  = (bool) $request->boolean('shared');

        $q = UserFile::query()
            ->when($trashed, fn($qq) => $qq->onlyTrashed(), fn($qq) => $qq->whereNull('deleted_at'))
            ->with(['user:id,first_name,last_name,email'])
            ->orderByDesc('id');

        if ($search !== '') {
            $like = "%{$search}%";
            $q->where(function ($qq) use ($like) {
                $qq->where('original_name', 'ilike', $like)
                   ->orWhere('filename', 'ilike', $like)
                   ->orWhereHas('user', function ($uq) use ($like) {
                       $uq->where('email', 'ilike', $like)
                          ->orWhere('first_name', 'ilike', $like)
                          ->orWhere('last_name', 'ilike', $like);
                   });
            });
        }

        if ($type !== '') {
            $q->where('mime_type', 'ilike', $type . '%');
        }

        if ($shared) {
            $q->whereNotNull('share_token');
        }

        $files = $q->paginate(50)->withQueryString();

        $stats = [
            'total_active' => UserFile::whereNull('deleted_at')->count(),
            'total_bytes'  => (int) UserFile::whereNull('deleted_at')->sum('size_bytes'),
            'total_shared' => UserFile::whereNotNull('share_token')->count(),
            'total_trashed'=> UserFile::onlyTrashed()->count(),
        ];

        // Top 10 file extensions for an at-a-glance breakdown
        $topExt = UserFile::whereNull('deleted_at')
            ->selectRaw('LOWER(regexp_replace(original_name, \'^.*\\.\', \'\')) as ext, COUNT(*) as cnt, SUM(size_bytes) as bytes')
            ->groupBy('ext')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        return view('admin.user-storage.files.index', [
            'files'   => $files,
            'stats'   => $stats,
            'topExt'  => $topExt,
            'search'  => $search,
            'type'    => $type,
            'trashed' => $trashed,
            'shared'  => $shared,
        ]);
    }

    public function takedown(UserFile $file)
    {
        $file->delete();

        Log::warning('user_storage.admin.takedown', [
            'file_id'  => $file->id,
            'user_id'  => $file->user_id,
            'filename' => $file->original_name,
            'admin_id' => optional(auth('admin')->user())->id,
        ]);

        return back()->with('success', 'Take down ไฟล์เรียบร้อย (ย้ายเข้าถังขยะ)');
    }

    public function purge(UserFile $file)
    {
        // Find the trashed record — route model binding on soft-deleted model
        // needs withTrashed() to resolve, so we re-fetch.
        $target = UserFile::withTrashed()->findOrFail($file->id);

        $this->fm->purge($target);

        Log::warning('user_storage.admin.purge', [
            'file_id'  => $target->id,
            'user_id'  => $target->user_id,
            'filename' => $target->original_name,
            'admin_id' => optional(auth('admin')->user())->id,
        ]);

        return back()->with('success', 'ลบไฟล์ถาวรเรียบร้อย');
    }

    public function unshare(UserFile $file)
    {
        $this->fm->unshare($file);

        Log::info('user_storage.admin.unshare', [
            'file_id'  => $file->id,
            'user_id'  => $file->user_id,
            'admin_id' => optional(auth('admin')->user())->id,
        ]);

        return back()->with('success', 'ยกเลิกลิงก์แชร์เรียบร้อย');
    }

    public function download(UserFile $file)
    {
        $url = $this->fm->downloadUrl($file);
        if (!$url) {
            return back()->with('error', 'ไม่สามารถสร้างลิงก์ดาวน์โหลดได้');
        }
        return redirect()->away($url);
    }
}

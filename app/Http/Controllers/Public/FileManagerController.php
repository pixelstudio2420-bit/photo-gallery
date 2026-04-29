<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\UserFile;
use App\Models\UserFolder;
use App\Services\FileManagerService;
use App\Services\UserStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Web file manager UI — the "Google Drive-lite" that replaces the FTP
 * feature the user originally asked for. Runs entirely in the browser
 * against the FileManagerService on the server.
 *
 * Routes:
 *   GET  /storage/files[/{folder}]     → folder view (breadcrumbs + grid)
 *   POST /storage/files/upload         → upload a file (multipart)
 *   POST /storage/files/folders        → create folder
 *   POST /storage/files/{file}/rename  → rename
 *   POST /storage/files/{file}/move    → move to another folder
 *   DEL  /storage/files/{file}         → soft-delete (trash)
 *   POST /storage/files/{file}/share   → share link create/update
 *   DEL  /storage/files/{file}/share   → revoke share
 *   GET  /storage/files/{file}/download → signed download redirect
 *
 * All routes are gated by CheckStorageSystemEnabled middleware so turning
 * `user_storage_enabled` off 404s the whole surface.
 *
 * Ownership is enforced in every handler via UserFile::forUser() / find
 * against Auth::id() — never trust the route param alone.
 */
class FileManagerController extends Controller
{
    public function __construct(
        private FileManagerService $fm,
        private UserStorageService $svc,
    ) {}

    public function show(Request $request, ?int $folder = null): View
    {
        $user = Auth::user();
        $data = $this->fm->listFolder($user, $folder);

        return view('storage.files.index', array_merge($data, [
            'summary' => $this->svc->dashboardSummary($user),
        ]));
    }

    public function upload(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'file'      => 'required|file',
            'folder_id' => 'nullable|integer',
        ]);

        $user = Auth::user();

        try {
            $file = $this->fm->upload($user, $request->file('file'), $request->integer('folder_id') ?: null);
        } catch (\RuntimeException $e) {
            $msg = match ($e->getMessage()) {
                'system_disabled'      => 'ระบบพื้นที่เก็บข้อมูลยังไม่เปิดใช้งาน',
                'quota_exceeded'       => 'พื้นที่เต็มแล้ว — อัปเกรดแผนหรือลบไฟล์เก่าออกก่อน',
                'file_too_large'       => 'ไฟล์ใหญ่เกินขีดจำกัดของแผนปัจจุบัน',
                'file_count_exceeded'  => 'จำนวนไฟล์เกินขีดจำกัดของแผน',
                default                => 'อัปโหลดไม่สำเร็จ: ' . $e->getMessage(),
            };

            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'error' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok'   => true,
                'file' => [
                    'id'            => $file->id,
                    'name'          => $file->original_name,
                    'size'          => $file->size_bytes,
                    'human_size'    => $file->human_size,
                    'icon'          => $file->icon,
                    'uploaded_at'   => $file->created_at->toIso8601String(),
                ],
            ]);
        }

        return back()->with('success', "อัปโหลด {$file->original_name} สำเร็จ");
    }

    public function createFolder(Request $request): RedirectResponse
    {
        $request->validate([
            'name'      => 'required|string|max:120',
            'parent_id' => 'nullable|integer',
        ]);

        $this->fm->createFolder(Auth::user(), $request->input('name'), $request->integer('parent_id') ?: null);
        return back()->with('success', 'สร้างโฟลเดอร์เรียบร้อย');
    }

    public function renameFolder(Request $request, UserFolder $folder): RedirectResponse
    {
        $this->authoriseOwnership($folder->user_id);
        $request->validate(['name' => 'required|string|max:120']);
        $this->fm->renameFolder($folder, $request->input('name'));
        return back()->with('success', 'เปลี่ยนชื่อเรียบร้อย');
    }

    public function deleteFolder(UserFolder $folder): RedirectResponse
    {
        $this->authoriseOwnership($folder->user_id);
        $this->fm->deleteFolder($folder, cascade: true);
        return back()->with('success', 'ลบโฟลเดอร์เรียบร้อย');
    }

    public function rename(Request $request, UserFile $file): RedirectResponse
    {
        $this->authoriseOwnership($file->user_id);
        $request->validate(['name' => 'required|string|max:200']);
        $this->fm->rename($file, $request->input('name'));
        return back()->with('success', 'เปลี่ยนชื่อไฟล์เรียบร้อย');
    }

    public function move(Request $request, UserFile $file): RedirectResponse
    {
        $this->authoriseOwnership($file->user_id);
        $request->validate(['folder_id' => 'nullable|integer']);
        $this->fm->move($file, $request->integer('folder_id') ?: null);
        return back()->with('success', 'ย้ายไฟล์เรียบร้อย');
    }

    public function destroy(UserFile $file): RedirectResponse
    {
        $this->authoriseOwnership($file->user_id);
        $this->fm->delete($file);
        return back()->with('success', 'ย้ายไปถังขยะเรียบร้อย');
    }

    public function share(Request $request, UserFile $file): RedirectResponse
    {
        $this->authoriseOwnership($file->user_id);
        $request->validate([
            'expires_at' => 'nullable|date|after:now',
            'password'   => 'nullable|string|min:4|max:60',
        ]);
        $this->fm->share(
            $file,
            $request->input('expires_at'),
            $request->input('password') ?: null
        );
        return back()->with('success', 'สร้างลิงก์แชร์เรียบร้อย');
    }

    public function unshare(UserFile $file): RedirectResponse
    {
        $this->authoriseOwnership($file->user_id);
        $this->fm->unshare($file);
        return back()->with('success', 'ยกเลิกลิงก์แชร์เรียบร้อย');
    }

    public function download(UserFile $file)
    {
        $this->authoriseOwnership($file->user_id);
        $url = $this->fm->downloadUrl($file);
        if (!$url) abort(404);
        return redirect()->away($url);
    }

    private function authoriseOwnership(int $ownerId): void
    {
        if ($ownerId !== (int) Auth::id()) {
            abort(403);
        }
    }
}

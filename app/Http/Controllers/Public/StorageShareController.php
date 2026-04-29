<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\UserFile;
use App\Services\FileManagerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public endpoint for unauthenticated users to open a shared file link.
 *
 *   GET  /s/{token}              → view preview page
 *   POST /s/{token}/verify       → submit password (if required)
 *   GET  /s/{token}/download     → signed download redirect
 *
 * Password-protected shares require the user to submit a password first;
 * we cache the "verified" flag in the session keyed by the token so
 * they don't need to retype on refresh.
 */
class StorageShareController extends Controller
{
    public function __construct(private FileManagerService $fm) {}

    public function show(Request $request, string $token): View
    {
        $file = $this->resolve($token);
        $needsPassword = !empty($file->share_password_hash);
        $verified      = session()->get("share_verified_{$token}", false);

        return view('storage.files.share', [
            'file'          => $file,
            'needsPassword' => $needsPassword,
            'verified'      => $verified,
            'token'         => $token,
        ]);
    }

    public function verify(Request $request, string $token)
    {
        $file     = $this->resolve($token);
        $password = $request->input('password');

        if (!$this->fm->verifySharePassword($file, $password)) {
            return back()->with('error', 'รหัสผ่านไม่ถูกต้อง');
        }

        session()->put("share_verified_{$token}", true);
        return redirect()->route('storage.share.show', ['token' => $token])
            ->with('success', 'ยืนยันรหัสผ่านเรียบร้อย');
    }

    public function download(Request $request, string $token)
    {
        $file = $this->resolve($token);

        if ($file->share_password_hash && !session()->get("share_verified_{$token}", false)) {
            return redirect()->route('storage.share.show', ['token' => $token])
                ->with('error', 'โปรดใส่รหัสผ่านก่อนดาวน์โหลด');
        }

        $url = $this->fm->downloadUrl($file);
        if (!$url) abort(404);
        return redirect()->away($url);
    }

    private function resolve(string $token): UserFile
    {
        $file = UserFile::where('share_token', $token)->first();
        if (!$file || !$file->isShareActive()) {
            abort(404);
        }
        return $file;
    }
}

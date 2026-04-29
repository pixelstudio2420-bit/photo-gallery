<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\PhotographerApiKey;
use App\Models\PhotographerProfile;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Photographer-facing API key management for Studio plan.
 *
 * Mirrors the GitHub PAT UX:
 *   - Create → plaintext token shown ONCE in a flash session, then gone
 *   - Index  → list of label + prefix + last-used + revoke action
 *   - Revoke → soft-delete via revoked_at
 */
class ApiKeyController extends Controller
{
    public function __construct(private SubscriptionService $subs) {}

    public function index(): View
    {
        $profile = $this->profile();
        $allowed = $this->subs->canAccessFeature($profile, 'api_access');

        $keys = $allowed
            ? PhotographerApiKey::where('photographer_id', $profile->user_id)
                ->orderBy('id', 'desc')
                ->get()
            : collect();

        return view('photographer.api-keys.index', [
            'keys'       => $keys,
            'allowed'    => $allowed,
            'plainToken' => session('plain_api_token'), // shown once, then gone
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $profile = $this->profile();
        if (!$this->subs->canAccessFeature($profile, 'api_access')) {
            return back()->with('error', 'API access เปิดสำหรับแผน Studio เท่านั้น');
        }

        $data = $request->validate([
            'label'  => 'required|string|max:80',
            'scopes' => 'nullable|string|max:200',
        ]);

        [, $plain] = PhotographerApiKey::generate(
            $profile->user_id,
            $data['label'],
            $data['scopes'] ?? 'events:read,photos:read'
        );

        return back()
            ->with('success', 'สร้าง API key เรียบร้อย — บันทึก token ด้านล่างเพราะแสดงครั้งเดียว')
            ->with('plain_api_token', $plain);
    }

    public function revoke(int $key): RedirectResponse
    {
        $profile = $this->profile();
        $row = PhotographerApiKey::where('photographer_id', $profile->user_id)
            ->where('id', $key)
            ->first();

        if (!$row) return back()->with('error', 'ไม่พบ API key');
        $row->revoke();

        return back()->with('success', 'ยกเลิก API key เรียบร้อย');
    }

    private function profile(): PhotographerProfile
    {
        $profile = Auth::user()?->photographerProfile;
        abort_unless($profile instanceof PhotographerProfile, 403, 'ไม่พบโปรไฟล์ช่างภาพ');
        return $profile;
    }
}

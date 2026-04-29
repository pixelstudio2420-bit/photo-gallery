<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\PhotographerProfile;
use App\Services\BrandingService;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\R2MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Photographer-facing settings page for the Custom Branding feature
 * (Business+) and White-label (Studio).
 *
 * The controller is dumb — it just persists whatever the form posted to
 * photographer_branding. BrandingService gates whether each field is
 * actually used at render time, so disabling a feature in the plan
 * doesn't require purging stored data.
 */
class BrandingController extends Controller
{
    public function __construct(private BrandingService $branding) {}

    public function edit(): View
    {
        $profile  = $this->profile();
        $settings = $this->branding->settingsFor($profile);

        return view('photographer.branding.edit', [
            'profile'      => $profile,
            'settings'     => $settings,
            'canCustom'    => $this->branding->canCustomBrand($profile),
            'canWhiteLabel'=> $this->branding->canWhiteLabel($profile),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $profile = $this->profile();

        // Hard-block if plan doesn't support any branding — defends
        // against users hitting POST directly.
        if (!$this->branding->canCustomBrand($profile)) {
            return back()->with('error', 'แผนปัจจุบันยังไม่รองรับการกำหนดแบรนด์ — กรุณาอัปเกรดเป็น Business ขึ้นไป');
        }

        $validated = $request->validate([
            'accent_hex'            => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'watermark_text'        => 'nullable|string|max:80',
            'watermark_enabled'     => 'nullable|boolean',
            'hide_platform_credits' => 'nullable|boolean',
            'custom_domain'         => 'nullable|string|max:120',
            'logo'                  => 'nullable|image|max:2048|mimes:png,jpg,jpeg,svg,webp',
        ], [
            'accent_hex.regex' => 'รหัสสีต้องเป็นรูปแบบ #RRGGBB',
            'logo.max'         => 'ไฟล์โลโก้ต้องไม่เกิน 2 MB',
        ]);

        $settings = $this->branding->settingsFor($profile);

        // Logo upload (replace existing). The old logo on R2 is wiped first
        // so we never leave orphan objects when the user replaces it.
        if ($request->hasFile('logo')) {
            $media = app(R2MediaService::class);
            if ($settings->logo_path) {
                try { $media->delete($settings->logo_path); } catch (\Throwable) {}
            }
            try {
                $upload = $media->uploadBrandingAsset((int) $profile->user_id, $request->file('logo'));
                $settings->logo_path = $upload->key;
            } catch (InvalidMediaFileException $e) {
                return back()->withErrors(['logo' => $e->getMessage()]);
            }
        }

        $settings->fill([
            'photographer_id'       => $profile->user_id,
            'accent_hex'            => $validated['accent_hex'] ?? null,
            'watermark_text'        => $validated['watermark_text'] ?? null,
            'watermark_enabled'     => (bool) ($validated['watermark_enabled'] ?? false),
            // White-label-only: only honour these when the plan grants it.
            'hide_platform_credits' => $this->branding->canWhiteLabel($profile)
                ? (bool) ($validated['hide_platform_credits'] ?? false)
                : false,
            'custom_domain'         => $this->branding->canWhiteLabel($profile)
                ? ($validated['custom_domain'] ?? null)
                : null,
        ])->save();

        return back()->with('success', 'บันทึกการตั้งค่าแบรนด์เรียบร้อย');
    }

    public function removeLogo(R2MediaService $media): RedirectResponse
    {
        $profile  = $this->profile();
        $settings = $this->branding->settingsFor($profile);

        if ($settings->logo_path) {
            try { $media->delete($settings->logo_path); } catch (\Throwable) {}
            $settings->forceFill(['logo_path' => null])->save();
        }

        return back()->with('success', 'ลบโลโก้เรียบร้อย');
    }

    private function profile(): PhotographerProfile
    {
        $profile = Auth::user()?->photographerProfile;
        abort_unless($profile instanceof PhotographerProfile, 403, 'ไม่พบโปรไฟล์ช่างภาพ');
        return $profile;
    }
}

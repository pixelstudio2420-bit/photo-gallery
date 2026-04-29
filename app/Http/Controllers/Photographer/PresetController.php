<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\PhotographerPreset;
use App\Models\PhotographerProfile;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\R2MediaService;
use App\Services\PresetService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Photographer-facing preset library.
 *
 * Endpoints:
 *   GET    /photographer/presets                  → list (system + own)
 *   GET    /photographer/presets/create           → manual edit form
 *   POST   /photographer/presets                  → save new preset
 *   GET    /photographer/presets/{p}/edit         → edit form (own only)
 *   PUT    /photographer/presets/{p}              → update (own only)
 *   DELETE /photographer/presets/{p}              → delete (own only)
 *   POST   /photographer/presets/import           → upload .xmp file
 *   POST   /photographer/presets/{p}/duplicate    → clone (any → own)
 *   POST   /photographer/presets/{p}/set-default  → mark as auto-apply
 *   POST   /photographer/presets/clear-default    → unset auto-apply
 *   POST   /photographer/presets/{p}/apply/{event} → bulk apply to all event photos
 *   POST   /photographer/presets/preview          → AJAX live preview (returns JPEG bytes)
 */
class PresetController extends Controller
{
    public function __construct(
        private PresetService $presets,
        private SubscriptionService $subs,
    ) {}

    public function index(): View
    {
        $profile = $this->profile();
        $items = PhotographerPreset::active()
            ->forPhotographer($profile->user_id)
            ->ordered()
            ->get();

        return view('photographer.presets.index', [
            'profile'         => $profile,
            'presets'         => $items,
            'defaultPresetId' => $profile->default_preset_id,
            'allowed'         => $this->subs->canAccessFeature($profile, 'presets'),
        ]);
    }

    public function create(): View
    {
        $this->ensureAllowed();
        return view('photographer.presets.edit', [
            'preset'        => new PhotographerPreset(['settings' => PhotographerPreset::DEFAULTS]),
            'mode'          => 'create',
            'recentPhotos'  => $this->recentPhotosForPicker(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAllowed();
        $data = $this->validatePresetForm($request);

        PhotographerPreset::create([
            'photographer_id' => $this->profile()->user_id,
            'name'            => $data['name'],
            'description'     => $data['description'] ?? null,
            'settings'        => $data['settings'],
            'is_system'       => false,
            'is_active'       => true,
        ]);

        return redirect()
            ->route('photographer.presets.index')
            ->with('success', 'สร้าง preset เรียบร้อย');
    }

    public function edit(int $preset): View
    {
        $p = $this->ownedOrFail($preset);
        return view('photographer.presets.edit', [
            'preset'        => $p,
            'mode'          => 'edit',
            'recentPhotos'  => $this->recentPhotosForPicker(),
        ]);
    }

    public function update(Request $request, int $preset): RedirectResponse
    {
        $this->ensureAllowed();
        $p = $this->ownedOrFail($preset);
        $data = $this->validatePresetForm($request);

        $p->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'settings'    => $data['settings'],
        ]);

        return redirect()
            ->route('photographer.presets.index')
            ->with('success', 'อัปเดต preset เรียบร้อย');
    }

    public function destroy(int $preset): RedirectResponse
    {
        $p = $this->ownedOrFail($preset);
        $profile = $this->profile();

        // If this was the default, clear it from the profile too.
        if ($profile->default_preset_id == $p->id) {
            $profile->forceFill(['default_preset_id' => null])->save();
        }

        $p->delete();
        return back()->with('success', 'ลบ preset เรียบร้อย');
    }

    public function import(Request $request, R2MediaService $media): RedirectResponse
    {
        $this->ensureAllowed();
        $request->validate([
            'name'    => 'required|string|max:100',
            'xmp'     => 'required|file|mimes:xmp,xml,txt|max:1024',
        ], [
            'xmp.mimes' => 'ไฟล์ต้องเป็น .xmp (Lightroom preset)',
        ]);

        $xmpFile = $request->file('xmp');
        $content = file_get_contents($xmpFile->getRealPath());
        $settings = $this->presets->parseXmp($content);

        if (empty($settings)) {
            return back()->with('error', 'ไม่พบค่า adjustment ในไฟล์ — ตรวจสอบว่าเป็น Lightroom .xmp ถูกต้อง');
        }

        // Two-step: create the preset first to get its ID (the R2 path schema
        // requires it), then upload the XMP into the preset's own folder, then
        // patch the preset row with the canonical R2 key.
        $userId = (int) $this->profile()->user_id;

        $preset = PhotographerPreset::create([
            'photographer_id' => $userId,
            'name'            => $request->input('name'),
            'description'     => 'นำเข้าจาก Lightroom .xmp',
            'settings'        => $settings,
            'source_xmp_path' => null,
            'is_system'       => false,
            'is_active'       => true,
        ]);

        try {
            $upload = $media->uploadPreset($userId, (int) $preset->id, $xmpFile);
            $preset->forceFill(['source_xmp_path' => $upload->key])->save();
        } catch (InvalidMediaFileException $e) {
            // The settings were valid but the XMP source itself failed
            // upload. Keep the preset (it's usable) but flag that the
            // source isn't archived for re-export.
            \Illuminate\Support\Facades\Log::warning('Preset XMP archive upload failed', [
                'preset_id' => $preset->id,
                'reason'    => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('photographer.presets.edit', $preset->id)
            ->with('success', 'นำเข้า preset เรียบร้อย ('.count($settings).' ค่า) — ตรวจสอบและบันทึกเพื่อใช้งาน');
    }

    public function duplicate(int $preset): RedirectResponse
    {
        $this->ensureAllowed();
        $original = PhotographerPreset::active()
            ->forPhotographer($this->profile()->user_id)
            ->findOrFail($preset);

        $copy = PhotographerPreset::create([
            'photographer_id' => $this->profile()->user_id,
            'name'            => $original->name.' (copy)',
            'description'     => $original->description,
            'settings'        => $original->settings,
            'is_system'       => false,
            'is_active'       => true,
        ]);

        return redirect()
            ->route('photographer.presets.edit', $copy->id)
            ->with('success', 'คัดลอก preset เรียบร้อย — แก้ไขแล้วบันทึก');
    }

    public function setDefault(int $preset): RedirectResponse
    {
        $this->ensureAllowed();
        $p = PhotographerPreset::active()
            ->forPhotographer($this->profile()->user_id)
            ->findOrFail($preset);

        $profile = $this->profile();
        $profile->forceFill(['default_preset_id' => $p->id])->save();

        return back()->with('success',
            "ตั้งค่า '{$p->name}' เป็น preset เริ่มต้น — รูปที่อัปโหลดใหม่จะใช้ preset นี้อัตโนมัติ"
        );
    }

    public function clearDefault(): RedirectResponse
    {
        $this->profile()->forceFill(['default_preset_id' => null])->save();
        return back()->with('success', 'ยกเลิก preset เริ่มต้นแล้ว — รูปใหม่จะไม่ผ่าน preset อัตโนมัติ');
    }

    /**
     * Bulk-apply preset to every active photo in an event.
     * Sync render — for events with hundreds of photos this could
     * timeout; future improvement is to dispatch a job per chunk.
     */
    public function applyToEvent(int $preset, int $event): RedirectResponse
    {
        $this->ensureAllowed();
        $profile = $this->profile();

        $p = PhotographerPreset::active()
            ->forPhotographer($profile->user_id)
            ->findOrFail($preset);

        $ev = Event::where('id', $event)
            ->where('photographer_id', $profile->user_id)
            ->firstOrFail();

        $applied = 0; $errors = 0;
        foreach ($ev->photos()->where('status', 'active')->limit(500)->get() as $photo) {
            try {
                if ($this->presets->applyTo($photo, $p)) {
                    $applied++;
                } else {
                    $errors++;
                }
            } catch (\Throwable $e) {
                $errors++;
            }
        }

        return back()->with('success',
            "ใช้ preset '{$p->name}' กับ {$applied} รูปเรียบร้อย"
            .($errors > 0 ? " (ผิดพลาด {$errors} รูป)" : '')
        );
    }

    /**
     * AJAX live preview — accept settings JSON + source identifier,
     * return rendered JPEG bytes. Source can be:
     *   - "synthetic"          — built-in gradient + colour swatches
     *   - "photo:N"            — a real EventPhoto row owned by the user
     *   - "sample:TOKEN"       — a custom image the user uploaded via
     *                            uploadSample() (cached, 2h TTL)
     *   - (legacy) photo_id=N  — back-compat with old callers
     *
     * The size param caps the long edge of the render to keep slider
     * drag responsive on huge originals.
     */
    public function preview(Request $request): Response
    {
        $this->ensureAllowed();

        $settings = (array) $request->input('settings', []);
        $source   = (string) $request->input('source', '');
        $size     = max(200, min(1200, (int) $request->input('size', 600)));

        // Back-compat: if `photo_id` provided, build "photo:N" source
        if ($source === '' && $request->filled('photo_id')) {
            $source = 'photo:'.(int) $request->input('photo_id');
        }

        $sourceBytes = $this->resolveSampleBytes($source);
        if (!$sourceBytes) {
            return response('No sample image available', 404);
        }

        $bytes = $this->presets->previewBytes($settings, $sourceBytes, $size);
        if (!$bytes) {
            return response('Preview render failed', 500);
        }

        return response($bytes, 200, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Upload a custom image to use as the live-preview source.
     *
     * Stored in cache (2h TTL) keyed by photographer_id + token so the
     * file auto-expires without us having to babysit cleanup. Returns
     * the source token the frontend should pass back in subsequent
     * preview() calls.
     */
    public function uploadSample(Request $request): JsonResponse
    {
        $this->ensureAllowed();
        $request->validate([
            'image' => 'required|image|max:10240', // 10 MB
        ], [
            'image.image' => 'ไฟล์ต้องเป็นรูปภาพ (jpg/png/webp)',
            'image.max'   => 'ไฟล์ต้องไม่เกิน 10 MB',
        ]);

        $bytes = file_get_contents($request->file('image')->getRealPath());

        // Resize down to max 1200px on the long edge so the cache row
        // and subsequent renders aren't fighting a 24-MP RAW.
        $bytes = $this->shrinkForPreview($bytes, 1200);
        if (!$bytes) {
            return response()->json(['success' => false, 'message' => 'อ่านไฟล์ภาพไม่สำเร็จ'], 422);
        }

        $token = bin2hex(random_bytes(12));
        $key   = $this->sampleCacheKey($this->profile()->user_id, $token);
        Cache::put($key, $bytes, now()->addHours(2));

        return response()->json([
            'success'    => true,
            'token'      => $token,
            'source'     => 'sample:'.$token,
            'size_bytes' => strlen($bytes),
        ]);
    }

    private function resolveSampleBytes(string $source): ?string
    {
        $profile = $this->profile();

        // 1) Custom uploaded sample (cached)
        if (str_starts_with($source, 'sample:')) {
            $token = preg_replace('/[^a-f0-9]/', '', substr($source, 7));
            $bytes = Cache::get($this->sampleCacheKey($profile->user_id, $token));
            if ($bytes) return $bytes;
            // Fall through if the sample expired
        }

        // 2) Specific photo by id
        if (str_starts_with($source, 'photo:')) {
            $photoId = (int) substr($source, 6);
            $photo = EventPhoto::whereHas('event', fn ($q) => $q->where('photographer_id', $profile->user_id))
                ->find($photoId);
            if ($photo) {
                $disk = $photo->storage_disk ?? 'public';
                if (Storage::disk($disk)->exists($photo->original_path)) {
                    return $this->shrinkForPreview(
                        Storage::disk($disk)->get($photo->original_path),
                        1200
                    );
                }
            }
        }

        // 3) Synthetic fallback — always works on a fresh account
        if ($source === '' || $source === 'synthetic') {
            return $this->syntheticSample();
        }

        // 4) Last resort: most recent uploaded photo
        $recent = EventPhoto::whereHas('event', fn ($q) => $q->where('photographer_id', $profile->user_id))
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();
        if ($recent) {
            $disk = $recent->storage_disk ?? 'public';
            if (Storage::disk($disk)->exists($recent->original_path)) {
                return $this->shrinkForPreview(
                    Storage::disk($disk)->get($recent->original_path),
                    1200
                );
            }
        }

        return $this->syntheticSample();
    }

    private function sampleCacheKey(int $photographerId, string $token): string
    {
        return "preset:sample:{$photographerId}:{$token}";
    }

    /**
     * Resize image bytes to fit within $maxSize on the long edge.
     * Returns JPEG bytes. Returns null on decode failure.
     */
    private function shrinkForPreview(string $bytes, int $maxSize): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'shrink_');
        file_put_contents($tmp, $bytes);
        $info = @getimagesize($tmp);
        if (!$info) { @unlink($tmp); return null; }

        $w = $info[0]; $h = $info[1];
        if (max($w, $h) <= $maxSize) {
            @unlink($tmp);
            return $bytes;
        }

        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
            IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
            IMAGETYPE_WEBP => @imagecreatefromwebp($tmp),
            default        => null,
        };
        @unlink($tmp);
        if (!$img) return null;

        $scale = $maxSize / max($w, $h);
        $sw = (int) ($w * $scale);
        $sh = (int) ($h * $scale);
        $resized = imagecreatetruecolor($sw, $sh);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $sw, $sh, $w, $h);
        imagedestroy($img);

        ob_start();
        imagejpeg($resized, null, 88);
        $out = ob_get_clean();
        imagedestroy($resized);
        return $out;
    }

    /**
     * Recent photos to show as quick-pick thumbnails in the editor's
     * sample-picker strip. We surface up to 6 — anything more clutters
     * the UI on small screens.
     */
    private function recentPhotosForPicker(int $limit = 6): array
    {
        return EventPhoto::query()
            ->whereHas('event', fn ($q) => $q->where('photographer_id', $this->profile()->user_id))
            ->where('status', 'active')
            ->whereNotNull('original_path')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'thumbnail_path', 'original_path', 'storage_disk', 'width', 'height'])
            ->map(function ($p) {
                $thumbUrl = null;
                $disk = $p->storage_disk ?? 'public';
                $thumbPath = $p->thumbnail_path ?: $p->original_path;
                try {
                    $thumbUrl = Storage::disk($disk)->url($thumbPath);
                } catch (\Throwable $e) {
                    $thumbUrl = null;
                }
                return [
                    'id'    => $p->id,
                    'thumb' => $thumbUrl,
                    'label' => $p->width.'×'.$p->height,
                ];
            })
            ->all();
    }

    /**
     * Tiny hand-crafted gradient + colour swatches so the preview slider
     * has something to render against on a fresh account with zero photos.
     */
    private function syntheticSample(): string
    {
        $w = 800; $h = 500;
        $img = imagecreatetruecolor($w, $h);

        // Sky gradient
        for ($y = 0; $y < $h * 0.6; $y++) {
            $t = $y / ($h * 0.6);
            $r = (int) (110 + $t * 80);
            $g = (int) (180 + $t * 40);
            $b = (int) (220 - $t * 20);
            $col = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, $w, $y, $col);
        }
        // Ground
        for ($y = (int) ($h * 0.6); $y < $h; $y++) {
            $t = ($y - $h * 0.6) / ($h * 0.4);
            $r = (int) (60 + $t * 30);
            $g = (int) (100 - $t * 40);
            $b = (int) (40);
            $col = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, $w, $y, $col);
        }

        // Colour swatches
        $swatches = [
            [255, 80, 80],   // red
            [80, 200, 80],   // green
            [80, 120, 240],  // blue
            [240, 220, 60],  // yellow
            [160, 80, 220],  // purple
            [240, 160, 80],  // orange
        ];
        foreach ($swatches as $i => [$r, $g, $b]) {
            $col = imagecolorallocate($img, $r, $g, $b);
            imagefilledrectangle($img, 50 + $i * 110, 40, 130 + $i * 110, 120, $col);
        }

        // Skin-tone patch
        $skin = imagecolorallocate($img, 232, 195, 168);
        imagefilledellipse($img, $w / 2, (int) ($h * 0.7), 240, 200, $skin);

        ob_start();
        imagejpeg($img, null, 88);
        $bytes = ob_get_clean();
        imagedestroy($img);
        return $bytes;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function profile(): PhotographerProfile
    {
        $profile = Auth::user()?->photographerProfile;
        abort_unless($profile instanceof PhotographerProfile, 403, 'ไม่พบโปรไฟล์ช่างภาพ');
        return $profile;
    }

    /**
     * Gate every preset-mutating endpoint behind the `presets` feature.
     *
     * Instead of throwing a harsh 402 error page (which made the editor's
     * live preview pop a giant red HTML payload into the JS console),
     * we differentiate by request type:
     *
     *   • XHR / JSON request  → 402 with a structured JSON payload the
     *                           frontend can parse and render as a
     *                           friendly modal/toast.
     *   • Standard form POST  → redirect to the index page with a flash
     *                           query param (?upgrade=presets) so the
     *                           page can auto-open the upgrade modal.
     *
     * The required-plans list mirrors the seed migration above; if you
     * add a new tier that includes presets, drop its code in here too.
     */
    private function ensureAllowed(): void
    {
        if ($this->subs->canAccessFeature($this->profile(), 'presets')) {
            return;
        }

        $request = request();
        $payload = [
            'success'         => false,
            'code'            => 'plan_required',
            'feature'         => 'presets',
            'feature_label'   => 'Lightroom Presets',
            'required_plans'  => ['starter', 'pro', 'business', 'studio'],
            'message'         => 'ฟีเจอร์ Lightroom Presets เปิดใช้สำหรับแผน Starter, Pro, Business หรือ Studio เท่านั้น',
            'upgrade_url'     => route('photographer.subscription.plans'),
        ];

        if ($request && ($request->expectsJson() || $request->ajax() || $request->wantsJson())) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json($payload, 402)
            );
        }

        // Standard browser POST — bounce back to the presets index so the
        // index-page modal can pop the upgrade card. We pass the feature
        // code through the query string so the listener knows which modal
        // to show.
        $back = redirect()
            ->route('photographer.presets.index', ['upgrade' => 'presets'])
            ->with('preset_upgrade_required', $payload);

        throw new \Illuminate\Http\Exceptions\HttpResponseException($back);
    }

    private function ownedOrFail(int $presetId): PhotographerPreset
    {
        $p = PhotographerPreset::find($presetId);
        abort_unless($p, 404, 'ไม่พบ preset');
        if (!$p->isOwnedBy($this->profile()->user_id)) {
            abort(403, 'แก้ไข preset ของระบบไม่ได้ — ใช้ duplicate เพื่อสร้างเวอร์ชั่นของคุณเอง');
        }
        return $p;
    }

    private function validatePresetForm(Request $request): array
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:250',

            // Settings — each in its own field for the slider UI.
            'exposure'    => 'nullable|numeric|min:-2|max:2',
            'contrast'    => 'nullable|numeric|min:-100|max:100',
            'highlights'  => 'nullable|numeric|min:-100|max:100',
            'shadows'     => 'nullable|numeric|min:-100|max:100',
            'whites'      => 'nullable|numeric|min:-100|max:100',
            'blacks'      => 'nullable|numeric|min:-100|max:100',
            'vibrance'    => 'nullable|numeric|min:-100|max:100',
            'saturation'  => 'nullable|numeric|min:-100|max:100',
            'temperature' => 'nullable|numeric|min:-100|max:100',
            'tint'        => 'nullable|numeric|min:-100|max:100',
            'clarity'     => 'nullable|numeric|min:-100|max:100',
            'sharpness'   => 'nullable|numeric|min:0|max:100',
            'vignette'    => 'nullable|numeric|min:-100|max:100',
            'grayscale'   => 'nullable|boolean',
        ]);

        $settings = [];
        foreach (array_keys(PhotographerPreset::DEFAULTS) as $k) {
            if ($k === 'grayscale') {
                $settings[$k] = (bool) ($data[$k] ?? false);
            } else {
                $settings[$k] = (float) ($data[$k] ?? 0);
            }
        }

        return [
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'settings'    => $settings,
        ];
    }
}

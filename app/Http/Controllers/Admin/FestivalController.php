<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Festival;
use App\Services\FestivalThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Admin festival management — CRUD for themed seasonal popups.
 *
 * Originally seeded canonical festivals (Songkran, Loy Krathong,
 * NYE, etc.) via FestivalsSeeder so admin only tweaked content.
 * Phase 2 added full CRUD: admin can now create custom festivals
 * (e.g. one-off promotions, regional events) + delete obsolete
 * ones + duplicate an existing festival to bootstrap a new variant.
 *
 * Routes (registered in routes/festivals.php):
 *   GET    /admin/festivals                 index   — list + stats
 *   POST   /admin/festivals                 store   — create new
 *   PUT    /admin/festivals/{id}            update  — save edit
 *   DELETE /admin/festivals/{id}            destroy — remove (soft)
 *   POST   /admin/festivals/{id}/toggle     toggle  — flip enabled
 *   POST   /admin/festivals/{id}/bump-year  bumpYear — annual rotate
 *   POST   /admin/festivals/{id}/duplicate  duplicate — clone preset
 */
class FestivalController extends Controller
{
    public function index()
    {
        $festivals = Festival::orderByDesc('enabled')
            ->orderBy('starts_at')
            ->get();

        $svc = app(FestivalThemeService::class);
        $stats = [
            'total'           => $festivals->count(),
            'enabled'         => $festivals->where('enabled', true)->count(),
            'currently_live'  => $svc->currentlyActive()->count(),
            'upcoming_30d'    => $svc->upcoming(30)->count(),
        ];

        $themes = FestivalThemeService::THEMES;

        return view('admin.festivals.index', compact('festivals', 'stats', 'themes'));
    }

    /**
     * Create a new festival. Admin uses this for one-off custom
     * events that aren't covered by the canonical seeder (e.g. local
     * provincial festivals, brand promotions, anniversary sales).
     *
     * Slug is auto-generated from the name if not provided — admin
     * doesn't need to think about URL safety.
     */
    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        // Auto-generate slug from name if not provided. Append a 4-char
        // random suffix to dodge slug collisions on similar names
        // (e.g. two "Songkran 2026 special" rows in different years).
        $slug = $validated['slug'] ?? null;
        if (!$slug) {
            $slug = Str::slug($validated['name']) ?: 'festival';
        }
        // Ensure uniqueness — append numbers if collision
        $base = $slug;
        $i = 1;
        while (Festival::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        $validated['slug']      = $slug;
        $validated['enabled']   = $request->boolean('enabled', true);
        $validated['is_recurring'] = $request->boolean('is_recurring', false);

        $festival = Festival::create($validated);

        $this->flushFestivalCaches();

        return redirect()->route('admin.festivals.index')
            ->with('success', "✓ เพิ่มเทศกาล '{$festival->name}' สำเร็จ");
    }

    /**
     * Update a festival in-place. Validates dates, headline, theme.
     * Cache flush ensures the next page load on any user picks up
     * the change.
     */
    public function update(Request $request, int $id)
    {
        $festival = Festival::findOrFail($id);

        $validated = $this->validatePayload($request, $id);
        $validated['enabled']      = $request->boolean('enabled');
        $validated['is_recurring'] = $request->boolean('is_recurring', $festival->is_recurring);

        $festival->update($validated);
        $this->flushFestivalCaches();

        return back()->with('success', '✓ บันทึก "' . $festival->name . '" สำเร็จ');
    }

    /**
     * Soft-delete a festival. Uses Eloquent's SoftDeletes (via the
     * model's deleted_at column) so the row stays in DB for audit —
     * if admin deletes by mistake they can restore via raw SQL.
     */
    public function destroy(int $id)
    {
        $festival = Festival::findOrFail($id);
        $name = $festival->name;
        $festival->delete();

        $this->flushFestivalCaches();

        return back()->with('success', "✓ ลบเทศกาล '{$name}' สำเร็จ");
    }

    /**
     * Duplicate an existing festival as a new draft. Useful for:
     *   • Spinning up next year's variant before the seeder runs
     *   • Creating a regional version (e.g. Songkran-Phuket)
     *   • Building on top of a working template instead of from scratch
     *
     * Copy is created DISABLED by default so admin can edit before
     * users see it. New slug appends -copy + counter.
     */
    public function duplicate(int $id)
    {
        $original = Festival::findOrFail($id);

        $newSlug = $original->slug . '-copy';
        $i = 1;
        while (Festival::where('slug', $newSlug)->exists()) {
            $newSlug = $original->slug . '-copy-' . (++$i);
        }

        $copy = $original->replicate();
        $copy->slug = $newSlug;
        $copy->name = $original->name . ' (สำเนา)';
        $copy->enabled = false;   // start disabled — admin reviews before going live
        $copy->save();

        return redirect()->route('admin.festivals.index')
            ->with('success', "✓ ทำสำเนา '{$original->name}' เป็น '{$copy->name}' (ปิดอยู่ — แก้ไขแล้วเปิด)");
    }

    /**
     * Toggle enabled flag — used by the inline switch in the index list
     * for one-click on/off without opening the edit form.
     */
    public function toggle(Request $request, int $id)
    {
        $festival = Festival::findOrFail($id);
        $festival->enabled = !$festival->enabled;
        $festival->save();

        $this->flushFestivalCaches();

        $msg = $festival->enabled
            ? "✓ เปิด popup '{$festival->short_name}' แล้ว"
            : "✗ ปิด popup '{$festival->short_name}' แล้ว";

        return back()->with('success', $msg);
    }

    /**
     * Bump dates by one year — quick action for annually-recurring
     * festivals after the date passes. Admin sees "Songkran 2026
     * (passed)" → click "Bump to 2027" → done in one click.
     */
    public function bumpYear(int $id)
    {
        $festival = Festival::findOrFail($id);
        if (!$festival->is_recurring) {
            return back()->with('error', 'เทศกาลนี้ไม่ใช่ประเภทประจำปี — แก้ไขด้วยตนเองในฟอร์ม');
        }

        $festival->starts_at = $festival->starts_at->copy()->addYear();
        $festival->ends_at   = $festival->ends_at->copy()->addYear();
        $festival->save();

        $this->flushFestivalCaches();

        return back()->with('success',
            "✓ '{$festival->short_name}' ปรับเป็นปีถัดไป: " .
            $festival->starts_at->format('d M Y') . ' — ' . $festival->ends_at->format('d M Y'));
    }

    /**
     * Shared validation rules for store + update. The optional $id
     * lets update() exempt itself from the slug uniqueness check on
     * its own row (otherwise edit would fail with "slug taken").
     */
    private function validatePayload(Request $request, ?int $id = null): array
    {
        $rules = [
            'name'             => 'required|string|max:200',
            'short_name'       => 'nullable|string|max:80',
            'slug'             => 'nullable|string|max:80|regex:/^[a-z0-9\-]+$/',
            'theme_variant'    => 'required|string|max:40',
            'emoji'            => 'nullable|string|max:30',
            'starts_at'        => 'required|date',
            'ends_at'          => 'required|date|after_or_equal:starts_at',
            'popup_lead_days'  => 'required|integer|min:0|max:90',
            'headline'         => 'required|string|max:250',
            'body_md'          => 'nullable|string|max:5000',
            'cta_label'        => 'nullable|string|max:80',
            'cta_url'          => 'nullable|string|max:500',
            'show_priority'    => 'required|integer|min:0|max:255',
            'target_province_id' => 'nullable|integer|exists:thai_provinces,id',
            'enabled'          => 'nullable|boolean',
            'is_recurring'     => 'nullable|boolean',
        ];

        // Slug uniqueness — exempt own row on edit
        if ($id) {
            $rules['slug'] .= '|unique:festivals,slug,' . $id;
        } else {
            $rules['slug'] .= '|unique:festivals,slug';
        }

        $validated = $request->validate($rules);

        // Verify theme variant exists in the service whitelist
        if (!isset(FestivalThemeService::THEMES[$validated['theme_variant']])) {
            abort(422, 'ธีมไม่ถูกต้อง');
        }

        // Coerce empty province FK to null so we don't violate FK
        if (empty($validated['target_province_id'])) {
            $validated['target_province_id'] = null;
        }

        return $validated;
    }

    /**
     * Per-user cache keys aren't enumerable without scanning Redis
     * (or whatever cache backend we have), so we flush the entire
     * cache. Pragmatic — the marketplace is small enough that this
     * is fine, and festival edits are infrequent.
     */
    private function flushFestivalCaches(): void
    {
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            \Log::warning('FestivalController cache flush failed: ' . $e->getMessage());
        }
    }
}

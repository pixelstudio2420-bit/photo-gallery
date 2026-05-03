<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Festival;
use App\Services\FestivalThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Admin festival management — list of seeded festivals with inline
 * enable/disable + edit. Festivals are seeded from FestivalsSeeder
 * (auto-run on deploy) so admin doesn't create from scratch — they
 * tweak the existing rows for content/copy/dates.
 *
 * Why not full CRUD: festivals are a fixed canonical list (Songkran,
 * Loy Krathong, etc.). Admins shouldn't invent new festivals on a
 * whim — that path leads to popup spam. If they need a one-off promo,
 * the announcements system is the right tool.
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
     * Update a festival in-place. Validates dates, headline, theme.
     * Cache flush ensures the next page load on any user picks up
     * the change.
     */
    public function update(Request $request, int $id)
    {
        $festival = Festival::findOrFail($id);

        $validated = $request->validate([
            'name'             => 'required|string|max:200',
            'short_name'       => 'nullable|string|max:80',
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
            'enabled'          => 'nullable|boolean',
        ]);

        // Verify theme variant exists in the service whitelist —
        // prevents typo'd variants that would fall back to water-blue
        // silently (admin would think they saved a theme that doesn't
        // exist).
        if (!isset(FestivalThemeService::THEMES[$validated['theme_variant']])) {
            return back()->with('error', 'ธีมไม่ถูกต้อง — เลือกจากรายการ');
        }

        $validated['enabled'] = $request->boolean('enabled');
        $festival->update($validated);

        // Bust any cached popup decisions — global flush is overkill,
        // but festival cache keys are per-user and we don't track them
        // individually. Cheap on small audiences.
        $this->flushFestivalCaches();

        return back()->with('success', '✓ บันทึก "' . $festival->name . '" สำเร็จ');
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
     * Per-user cache keys aren't enumerable without scanning Redis
     * (or whatever cache backend we have), so we flush the entire
     * cache. Pragmatic — the marketplace is small enough that this
     * is fine, and festival edits are infrequent.
     */
    private function flushFestivalCaches(): void
    {
        try {
            // We only want to bust festival_popup_user_* keys but
            // the cache driver doesn't support pattern delete.
            // Settling for clearing on Redis-backed setups; on
            // file/array drivers this is a no-op anyway.
            Cache::flush();
        } catch (\Throwable $e) {
            // Cache flush failure is recoverable — popups will
            // self-correct after the 60s TTL expires.
            \Log::warning('FestivalController cache flush failed: ' . $e->getMessage());
        }
    }
}

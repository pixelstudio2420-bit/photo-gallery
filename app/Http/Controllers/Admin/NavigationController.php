<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NavMenuItem;
use Illuminate\Http\Request;

/**
 * Admin CRUD for the global nav_menu_items table.
 *
 *   GET  /admin/navigation              → list all (sortable, filterable)
 *   GET  /admin/navigation/create       → blank form
 *   POST /admin/navigation              → create
 *   GET  /admin/navigation/{id}/edit    → edit form
 *   PUT  /admin/navigation/{id}         → update
 *   DELETE /admin/navigation/{id}       → delete (no soft-delete; admin
 *                                          can toggle is_active=false instead)
 *   POST /admin/navigation/reorder      → batch sort_order update from
 *                                          the drag-drop UI
 *
 * Cache invalidation is handled by NavMenuItem::booted() which calls
 * NavigationService::flushCache() on every save / delete — admin
 * doesn't need to manually clear anything.
 */
class NavigationController extends Controller
{
    public function index()
    {
        // Group by location for the list view — admin can quickly
        // see what's where without filter clicks.
        $items = NavMenuItem::orderBy('location')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('location');

        return view('admin.navigation.index', [
            'itemsByLocation' => $items,
            'locations'       => NavMenuItem::LOCATIONS,
            'audiences'       => NavMenuItem::AUDIENCES,
            'ctaStyles'       => NavMenuItem::CTA_STYLES,
            'badgeColors'     => NavMenuItem::BADGE_COLORS,
        ]);
    }

    public function create()
    {
        $item = new NavMenuItem([
            'location'    => 'navbar',
            'audience'    => 'public',
            'cta_style'   => 'default',
            'is_active'   => true,
            'sort_order'  => (NavMenuItem::max('sort_order') ?? 0) + 10,
        ]);

        return view('admin.navigation.edit', [
            'item'         => $item,
            'isCreate'     => true,
            'locations'    => NavMenuItem::LOCATIONS,
            'audiences'    => NavMenuItem::AUDIENCES,
            'ctaStyles'    => NavMenuItem::CTA_STYLES,
            'badgeColors'  => NavMenuItem::BADGE_COLORS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $item = NavMenuItem::create($data);

        return redirect()->route('admin.navigation.index')
            ->with('success', 'เพิ่มเมนู "' . $item->label . '" เรียบร้อย');
    }

    public function edit(NavMenuItem $navigation)
    {
        return view('admin.navigation.edit', [
            'item'         => $navigation,
            'isCreate'     => false,
            'locations'    => NavMenuItem::LOCATIONS,
            'audiences'    => NavMenuItem::AUDIENCES,
            'ctaStyles'    => NavMenuItem::CTA_STYLES,
            'badgeColors'  => NavMenuItem::BADGE_COLORS,
        ]);
    }

    public function update(Request $request, NavMenuItem $navigation)
    {
        $data = $this->validateData($request, $navigation->id);
        $navigation->update($data);

        return redirect()->route('admin.navigation.index')
            ->with('success', 'อัพเดทเมนู "' . $navigation->label . '" เรียบร้อย');
    }

    public function destroy(NavMenuItem $navigation)
    {
        $label = $navigation->label;
        $navigation->delete();

        return redirect()->route('admin.navigation.index')
            ->with('success', "ลบเมนู \"{$label}\" เรียบร้อย");
    }

    /**
     * Batch sort_order update from a drag-drop reorder. The admin UI
     * sends an ordered array of IDs per location; we map them to
     * sort_order values 10, 20, 30… (gaps of 10 so future inserts
     * fit between without renumbering).
     *
     * Body shape:
     *   {
     *     "location": "navbar",
     *     "ids": [3, 1, 5, 2]
     *   }
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'location' => 'required|in:' . implode(',', array_keys(NavMenuItem::LOCATIONS)),
            'ids'      => 'required|array',
            'ids.*'    => 'integer|exists:nav_menu_items,id',
        ]);

        foreach ($request->input('ids', []) as $idx => $id) {
            NavMenuItem::where('id', $id)->update([
                'sort_order' => ($idx + 1) * 10,
                'location'   => $request->location,
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Quick-toggle the is_active flag from the list view without
     * opening the full edit form — the most common admin action.
     */
    public function toggle(NavMenuItem $navigation)
    {
        $navigation->update(['is_active' => !$navigation->is_active]);
        return back()->with('success',
            ($navigation->is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน')
            . ' เมนู "' . $navigation->label . '"'
        );
    }

    /**
     * Validate + cast the form payload. Used by both store() and
     * update() to keep the rules in one place.
     */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $allowedLocations  = array_keys(NavMenuItem::LOCATIONS);
        $allowedAudiences  = array_keys(NavMenuItem::AUDIENCES);
        $allowedCtaStyles  = array_keys(NavMenuItem::CTA_STYLES);
        $allowedBadges     = array_keys(NavMenuItem::BADGE_COLORS);

        $validated = $request->validate([
            'label'            => 'required|string|max:80',
            'url'              => 'required|string|max:500',
            'icon'             => 'nullable|string|max:60',
            'location'         => 'required|in:' . implode(',', $allowedLocations),
            'audience'         => 'required|in:' . implode(',', $allowedAudiences),
            'cta_style'        => 'required|in:' . implode(',', $allowedCtaStyles),
            'badge_text'       => 'nullable|string|max:20',
            'badge_color'      => 'nullable|in:' . implode(',', $allowedBadges),
            'open_in_new_tab'  => 'nullable|boolean',
            'is_active'        => 'nullable|boolean',
            'sort_order'       => 'nullable|integer|min:0|max:9999',
            'visibility_route_pattern' => 'nullable|string|max:200',
        ]);

        // Normalize checkbox booleans (unchecked = absent in form data).
        $validated['open_in_new_tab'] = $request->boolean('open_in_new_tab');
        $validated['is_active']       = $request->boolean('is_active', true);
        $validated['sort_order']      = (int) ($validated['sort_order'] ?? 0);

        // Strip leading "bi-" if admin pasted the full class name
        // (the renderer adds it back). Lets paste-from-icon-picker
        // work without manual editing.
        if (!empty($validated['icon'])) {
            $validated['icon'] = preg_replace('/^bi-/', '', $validated['icon']);
        }

        return $validated;
    }
}

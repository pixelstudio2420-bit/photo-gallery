<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AddonItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Admin CRUD for the photographer self-serve store catalog.
 *
 * Routes (under admin/monetization/addons):
 *   GET    /                index      list (filterable by category)
 *   GET    /create          create     blank form
 *   POST   /                store      create
 *   GET    /{id}/edit       edit       populated form
 *   PUT    /{id}            update     save changes
 *   POST   /{id}/toggle     toggle     flip is_active (AJAX)
 *   DELETE /{id}            destroy    soft-delete (with usage warning)
 *
 * SKU is immutable post-creation — the activation handler in
 * AddonService dispatches by category, and historical
 * photographer_addon_purchases.snapshot rows hold the SKU as a string,
 * so renaming would orphan past purchases. The form disables the SKU
 * field on edit; deletion is soft (the row stays for audit, but the
 * catalog query filters on is_active so customers don't see it).
 */
class AddonItemController extends Controller
{
    public function index(Request $request)
    {
        $q = AddonItem::query();

        if ($request->filled('category')) {
            $q->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $kw = '%' . $request->search . '%';
            $q->where(fn ($w) => $w->where('label', 'ilike', $kw)
                                   ->orWhere('sku', 'ilike', $kw));
        }
        if ($request->boolean('only_inactive')) {
            $q->where('is_active', false);
        }

        $items = $q->orderBy('category')->orderBy('sort_order')->orderBy('id')->paginate(30)->withQueryString();

        // Each item's purchase count — surfaced in the index so admins
        // know which rows are "in use" (don't delete) vs orphaned. One
        // lightweight aggregate query rather than per-row N+1.
        $usage = DB::table('photographer_addon_purchases')
            ->select('sku', DB::raw('COUNT(*) as purchase_count'))
            ->groupBy('sku')
            ->pluck('purchase_count', 'sku');

        $stats = [
            'total'       => AddonItem::count(),
            'active'      => AddonItem::where('is_active', true)->count(),
            'inactive'    => AddonItem::where('is_active', false)->count(),
            'categories'  => AddonItem::distinct()->count('category'),
        ];

        $categories = AddonItem::categories();

        return view('admin.monetization.addons.index', compact('items', 'usage', 'stats', 'categories'));
    }

    public function create()
    {
        $item = new AddonItem([
            'category'   => AddonItem::CATEGORY_PROMOTION,
            'is_active'  => true,
            'sort_order' => (int) (AddonItem::max('sort_order') ?? 0) + 10,
        ]);
        $categories = AddonItem::categories();
        // Empty placeholders so the sidebar partials don't have to
        // null-check every field.
        $purchaseStats = (object) [
            'total' => 0, 'activated' => 0, 'pending' => 0, 'expired' => 0, 'gross_revenue' => 0,
        ];
        $recentBuyers = collect();
        return view('admin.monetization.addons.form', compact(
            'item', 'categories', 'purchaseStats', 'recentBuyers',
        ));
    }

    public function store(Request $request)
    {
        $data = $this->validateInput($request);
        $data['meta'] = $this->extractMeta($request);
        $data['is_active'] = $request->boolean('is_active', true);

        $item = AddonItem::create($data);

        return redirect()->route('admin.monetization.addons.edit', $item->id)
            ->with('success', "เพิ่ม \"{$item->label}\" เรียบร้อย — SKU: {$item->sku}");
    }

    public function edit($id)
    {
        $item = AddonItem::findOrFail($id);
        $categories = AddonItem::categories();

        // Sidebar context: purchase aggregates + last 5 buyers so admin
        // can see real impact at a glance without opening another page.
        $purchaseStats = DB::table('photographer_addon_purchases')
            ->where('sku', $item->sku)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'activated' THEN 1 ELSE 0 END) as activated,
                SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'expired'   THEN 1 ELSE 0 END) as expired,
                COALESCE(SUM(price_thb), 0) as gross_revenue
            ")
            ->first();

        $recentBuyers = DB::table('photographer_addon_purchases as ap')
            ->leftJoin('auth_users as u', 'u.id', '=', 'ap.photographer_id')
            ->leftJoin('photographer_profiles as p', 'p.user_id', '=', 'ap.photographer_id')
            ->where('ap.sku', $item->sku)
            ->orderByDesc('ap.created_at')
            ->limit(5)
            ->select(
                'ap.id', 'ap.status', 'ap.created_at', 'ap.expires_at',
                'p.display_name', 'u.first_name', 'u.email',
            )
            ->get();

        return view('admin.monetization.addons.form', compact(
            'item', 'categories', 'purchaseStats', 'recentBuyers',
        ));
    }

    public function update(Request $request, $id)
    {
        $item = AddonItem::findOrFail($id);
        $data = $this->validateInput($request, $item);

        // SKU + category are immutable post-create. Strip from payload
        // even if the request smuggled them in via a tampered form.
        unset($data['sku'], $data['category']);

        $data['meta'] = $this->extractMeta($request);
        $data['is_active'] = $request->boolean('is_active');

        $item->update($data);

        return redirect()->route('admin.monetization.addons.edit', $item->id)
            ->with('success', 'บันทึกการเปลี่ยนแปลงเรียบร้อย');
    }

    public function toggle($id): JsonResponse
    {
        $item = AddonItem::findOrFail($id);
        $item->update(['is_active' => !$item->is_active]);
        return response()->json(['ok' => true, 'is_active' => $item->is_active]);
    }

    public function destroy($id)
    {
        $item = AddonItem::findOrFail($id);

        $purchaseCount = DB::table('photographer_addon_purchases')
            ->where('sku', $item->sku)
            ->count();

        if ($purchaseCount > 0) {
            // Don't hard-delete a SKU with historical purchases —
            // refund/audit flows still resolve them via findBySku.
            // Soft-delete via is_active=false instead so the row stays
            // findable by purchase records but disappears from the
            // public store.
            $item->update(['is_active' => false]);
            $item->delete(); // soft-delete (uses SoftDeletes trait)

            return redirect()->route('admin.monetization.addons.index')
                ->with('success', "Soft-delete \"{$item->label}\" แล้ว — มีประวัติการซื้อ {$purchaseCount} รายการ ยังคงอ้างอิงอยู่");
        }

        // No purchases yet → safe to fully remove
        $item->forceDelete();
        return redirect()->route('admin.monetization.addons.index')
            ->with('success', "ลบ \"{$item->label}\" แล้ว");
    }

    /* ───────────── Helpers ───────────── */

    private function validateInput(Request $request, ?AddonItem $existing = null): array
    {
        $rules = [
            'sku'        => [
                'required', 'string', 'max:60',
                'regex:/^[a-z0-9_\.]+$/',
            ],
            'category'   => 'required|in:' . implode(',', array_keys(AddonItem::categories())),
            'label'      => 'required|string|max:120',
            'tagline'    => 'nullable|string|max:200',
            'price_thb'  => 'required|numeric|min:0|max:999999',
            'badge'      => 'nullable|string|max:30',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ];

        if ($existing) {
            $rules['sku'][] = 'unique:addon_items,sku,' . $existing->id;
        } else {
            $rules['sku'][] = 'unique:addon_items,sku';
        }

        return $request->validate($rules);
    }

    /**
     * Pull category-specific fields out of the request into the meta
     * JSON. The form uses different field names per category, so this
     * is a switch on the submitted category.
     */
    private function extractMeta(Request $request): array
    {
        $meta = [];

        switch ($request->input('category')) {
            case AddonItem::CATEGORY_PROMOTION:
                $meta = [
                    'kind'         => $request->input('meta_kind') ?: 'boost',
                    'cycle'        => $request->input('meta_cycle') ?: 'monthly',
                    'boost_score'  => (int) $request->input('meta_boost_score', 10),
                ];
                break;
            case AddonItem::CATEGORY_STORAGE:
                $meta = [
                    'storage_gb' => (int) $request->input('meta_storage_gb', 50),
                ];
                break;
            case AddonItem::CATEGORY_AI_CREDITS:
                $meta = [
                    'credits' => (int) $request->input('meta_credits', 5000),
                ];
                break;
            case AddonItem::CATEGORY_BRANDING:
                $oneTime = $request->boolean('meta_one_time');
                $meta = $oneTime
                    ? ['one_time' => true]
                    : ['cycle' => $request->input('meta_cycle') ?: 'monthly'];
                break;
            case AddonItem::CATEGORY_PRIORITY:
                $meta = [
                    'cycle' => $request->input('meta_cycle') ?: 'monthly',
                ];
                break;
        }

        return $meta;
    }
}

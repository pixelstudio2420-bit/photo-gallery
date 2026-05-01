<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\PricingPackage;
use App\Services\Pricing\BundleService;
use App\Services\EventPriceResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Photographer-facing CRUD for an event's photo bundles.
 *
 * Routes (registered in routes/web.php under prefix /photographer):
 *   GET    /events/{event}/packages              index — list & manage
 *   POST   /events/{event}/packages              store — add custom bundle
 *   PUT    /events/{event}/packages/{pkg}        update — edit bundle
 *   DELETE /events/{event}/packages/{pkg}        destroy — delete
 *   POST   /events/{event}/packages/template     applyTemplate — overwrite with preset
 *   POST   /events/{event}/packages/{pkg}/feature toggleFeatured — pin "best value"
 *
 * Authorization: every action verifies the event belongs to the current
 * photographer. We don't lean on a policy class because the rule is a
 * one-liner — but we centralize it via `$this->authorizeOwnership($event)`
 * so changes ripple through automatically.
 */
class EventPackageController extends Controller
{
    public function __construct(
        private readonly BundleService $bundles,
        private readonly EventPriceResolver $priceResolver,
    ) {}

    /* ───────── Index ───────── */

    public function index(Event $event)
    {
        $this->authorizeOwnership($event);

        $packages = PricingPackage::where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('photo_count')
            ->get();

        $perPhoto = $this->priceResolver->perPhoto($event->id);
        $templates = $this->bundles->templates();
        $suggestedTemplate = $this->bundles->templateKeyForCategory(optional($event->category)->slug);

        // Comprehensive stats — drives the dashboard panels.
        $stats = $this->computeStats($event, $packages);

        // Detect bundles whose price has drifted from what the current
        // per-photo + discount would yield. Drives the warning banner +
        // "Recalculate" CTA in the view.
        $priceDrift = $this->bundles->detectPriceDrift($event);

        return view('photographer.events.packages.index', compact(
            'event', 'packages', 'perPhoto', 'templates', 'suggestedTemplate', 'stats', 'priceDrift'
        ));
    }

    /* ───────── Store (custom bundle) ───────── */

    public function store(Request $request, Event $event)
    {
        $this->authorizeOwnership($event);

        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'bundle_type'     => 'required|in:count,face_match,event_all',
            'photo_count'     => 'nullable|integer|min:1|max:10000',
            'price'           => 'required|numeric|min:0',
            'original_price'  => 'nullable|numeric|min:0',
            'discount_pct'    => 'nullable|numeric|min:0|max:100',
            'max_price'       => 'nullable|numeric|min:0',
            'description'     => 'nullable|string|max:500',
            'bundle_subtitle' => 'nullable|string|max:200',
            'badge'           => 'nullable|string|max:50',
            'is_featured'     => 'nullable|boolean',
            'sort_order'      => 'nullable|integer',
        ]);

        // Defensive validation: face_match needs discount_pct + max_price.
        if ($validated['bundle_type'] === 'face_match') {
            $validated['discount_pct'] = $validated['discount_pct'] ?? 50;
            $validated['max_price']    = $validated['max_price']    ?? 1500;
            $validated['photo_count']  = null;
        }

        // event_all: photo_count is informational; if not supplied, count
        // all photos in the event.
        if ($validated['bundle_type'] === 'event_all' && empty($validated['photo_count'])) {
            $validated['photo_count'] = max(1, \App\Models\EventPhoto::where('event_id', $event->id)->count());
        }

        $validated['event_id']    = $event->id;
        $validated['is_active']   = true;
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['sort_order']  = $validated['sort_order'] ?? PricingPackage::where('event_id', $event->id)->max('sort_order') + 1;

        PricingPackage::create($validated);

        return back()->with('success', 'เพิ่มแพ็กเกจสำเร็จ');
    }

    /* ───────── Update ───────── */

    public function update(Request $request, Event $event, PricingPackage $package)
    {
        $this->authorizeOwnership($event);
        $this->authorizePackage($event, $package);

        // Anti-tamper: photographer can't change a bundle while there
        // are unpaid orders referencing it. Buyers expect the price
        // they saw at checkout to hold; allowing edits mid-transaction
        // creates a dispute vector AND a fraud vector (flip price,
        // sell, flip back). Order/OrderItem already snapshot prices
        // so paid orders are safe — but the warning saves us from
        // legitimate-looking confusion in the pending window.
        if ($package->hasPendingOrders()) {
            return back()->with('error',
                'ไม่สามารถแก้ไขได้ขณะมีออเดอร์ค้างชำระ ' . $package->pendingOrderCount() .
                ' รายการ — กรุณารอให้ลูกค้าจ่ายเงินหรือยกเลิกก่อน'
            );
        }

        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'photo_count'     => 'nullable|integer|min:1|max:10000',
            'price'           => 'required|numeric|min:0',
            'original_price'  => 'nullable|numeric|min:0',
            'discount_pct'    => 'nullable|numeric|min:0|max:100',
            'max_price'       => 'nullable|numeric|min:0',
            'description'     => 'nullable|string|max:500',
            'bundle_subtitle' => 'nullable|string|max:200',
            'badge'           => 'nullable|string|max:50',
            'is_featured'     => 'nullable|boolean',
            'is_active'       => 'nullable|boolean',
            'sort_order'      => 'nullable|integer',
        ]);

        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active']   = $request->boolean('is_active', true);

        // Tag the audit-log row that the observer will write so the
        // /admin/packages/audit page can show "Pat updated bundle X
        // (manual edit on event page)" rather than a bare row diff.
        $package->auditReason = 'photographer manual edit via packages page';
        $package->auditRole   = 'photographer';
        $package->update($validated);

        return back()->with('success', 'อัปเดตแพ็กเกจสำเร็จ');
    }

    /* ───────── Destroy ───────── */

    public function destroy(Event $event, PricingPackage $package)
    {
        $this->authorizeOwnership($event);
        $this->authorizePackage($event, $package);

        // Same anti-tamper rule on delete: a pending order's
        // OrderItem row references this package_id, and even though
        // the price snapshot survives the row delete (FK is
        // nullOnDelete on the audit log; orders are unaffected),
        // letting a photographer delete the row mid-checkout makes
        // for a confusing "what did I just buy" UX for the customer.
        if ($package->hasPendingOrders()) {
            return back()->with('error',
                'ไม่สามารถลบได้ขณะมีออเดอร์ค้างชำระ ' . $package->pendingOrderCount() .
                ' รายการ — กรุณารอให้ลูกค้าจ่ายเงินหรือยกเลิกก่อน'
            );
        }

        $package->auditReason = 'photographer manual delete via packages page';
        $package->auditRole   = 'photographer';
        $package->delete();

        return back()->with('success', 'ลบแพ็กเกจสำเร็จ');
    }

    /* ───────── Apply template (overwrite) ───────── */

    public function applyTemplate(Request $request, Event $event)
    {
        $this->authorizeOwnership($event);

        $request->validate([
            'template' => 'required|string|in:standard,sports,wedding,concert,corporate',
        ]);

        $created = $this->bundles->applyTemplate($event, $request->template);

        return back()->with('success', "ใช้เทมเพลตสำเร็จ — สร้าง {$created} แพ็กเกจใหม่");
    }

    /* ───────── Recalculate prices from current per-photo ───────── */

    /**
     * Re-derive bundle prices from the event's current per-photo price.
     *
     * Use case: photographer originally created the event at ฿100/photo,
     * the seeder set 3 รูป=฿270 etc, then they bumped per-photo to ฿200.
     * Without recalc the buyer still pays ฿270 for 3 photos (= ฿90/photo,
     * 55% discount instead of the intended 10%). This action brings every
     * count + event_all bundle back in line, preserving each row's
     * existing discount_pct so manual percentage tweaks stay intact.
     */
    public function recalculate(Event $event)
    {
        $this->authorizeOwnership($event);

        [$updated, $skipped] = $this->bundles->recalculatePrices($event);

        if ($updated === 0 && $skipped === 0) {
            return back()->with('error', 'ยังไม่ได้ตั้งราคา/รูปสำหรับอีเวนต์นี้ — กรุณาตั้งราคาก่อน');
        }

        return back()->with('success', "ปรับราคาแพ็กเกจสำเร็จ ({$updated} ปรับแล้ว, {$skipped} ข้าม)");
    }

    /* ───────── Toggle featured ───────── */

    public function toggleFeatured(Event $event, PricingPackage $package)
    {
        $this->authorizeOwnership($event);
        $this->authorizePackage($event, $package);

        // Unset other featured packages — only one can be the "best value".
        if (!$package->is_featured) {
            PricingPackage::where('event_id', $event->id)
                ->where('id', '!=', $package->id)
                ->update(['is_featured' => false]);
        }

        $package->update(['is_featured' => !$package->is_featured]);

        return back()->with('success', $package->is_featured ? 'ตั้งเป็น "ขายดีที่สุด" สำเร็จ' : 'ยกเลิก "ขายดีที่สุด" สำเร็จ');
    }

    /* ───────── Stats computation ───────── */

    /**
     * Build the dashboard stats payload for the photographer's bundle
     * management page. Pulls from:
     *   - pricing_packages.purchase_count (denormalized counter from
     *     OrderObserver — incremented on every paid order with a
     *     package_id matching one of these bundles)
     *   - orders + order_items joined to compute revenue & AOV
     *   - pricing_package_logs for change-frequency anomalies
     *
     * All queries are scoped to the event being managed so a
     * photographer with many events doesn't see noise from others.
     */
    private function computeStats(Event $event, $packages): array
    {
        // Total purchases + revenue from the denormalized counter — fast
        // even on accounts with thousands of orders.
        $totalPurchases = (int) $packages->sum('purchase_count');
        $totalRevenue   = (float) $packages->sum(
            fn ($p) => (int) $p->purchase_count * (float) $p->price
        );

        $bestSeller = $packages
            ->where('purchase_count', '>', 0)
            ->sortByDesc('purchase_count')
            ->first();

        // Real-time pulses — fresh order data over the last week so the
        // photographer can see "is anyone buying right now?".
        $weekStart = now()->subDays(7);
        $recentOrders = \App\Models\Order::query()
            ->where('event_id', $event->id)
            ->whereIn('package_id', $packages->pluck('id'))
            ->where('status', 'paid')
            ->where('paid_at', '>=', $weekStart)
            ->orderByDesc('paid_at')
            ->limit(20)
            ->get(['id', 'package_id', 'total', 'paid_at']);

        // Revenue trend — last 14 days grouped per day. Returns 14 buckets
        // even when a day has 0 sales so the spark line renders cleanly.
        $trend = collect();
        for ($i = 13; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $next = $day->copy()->addDay();
            $row = \App\Models\Order::query()
                ->where('event_id', $event->id)
                ->whereIn('package_id', $packages->pluck('id'))
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$day, $next])
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as rev')
                ->first();
            $trend->push([
                'date'     => $day->format('d/m'),
                'count'    => (int) ($row->cnt ?? 0),
                'revenue' => (float) ($row->rev ?? 0),
            ]);
        }

        // Conversion proxy — bundles purchased ÷ event view_count. Not a
        // perfect funnel metric (no per-view tracking on package cards
        // specifically) but useful for relative comparisons across
        // bundles + a sanity check on whether the event has buyer
        // traffic at all.
        $views = max(1, (int) ($event->view_count ?? 0));
        $conversionPct = $totalPurchases > 0
            ? round(($totalPurchases / $views) * 100, 2)
            : 0.0;

        // Per-bundle breakdown — drives the "which bundle is winning?"
        // table at the bottom of the dashboard.
        $perBundle = $packages->map(function ($p) use ($totalPurchases) {
            $purchases = (int) $p->purchase_count;
            $revenue   = $purchases * (float) $p->price;
            $share     = $totalPurchases > 0 ? round($purchases / $totalPurchases * 100, 1) : 0;
            return [
                'id'        => $p->id,
                'name'      => $p->name,
                'type'      => $p->bundle_type,
                'price'     => (float) $p->price,
                'purchases' => $purchases,
                'revenue'   => $revenue,
                'share_pct' => $share,
                'is_featured' => (bool) $p->is_featured,
            ];
        })->sortByDesc('purchases')->values();

        // Recent admin/system price changes — surfaces unusual activity
        // the photographer may want to know about (e.g. an admin tweaked
        // pricing, or auto-recalc fired).
        $recentChanges = \App\Models\PricingPackageLog::query()
            ->where('event_id', $event->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'action', 'changed_by_role', 'reason', 'created_at']);

        return [
            'total_purchases' => $totalPurchases,
            'total_revenue'   => $totalRevenue,
            'best_seller'     => $bestSeller,
            'recent_orders'   => $recentOrders,
            'trend'           => $trend,
            'conversion_pct'  => $conversionPct,
            'per_bundle'      => $perBundle,
            'recent_changes'  => $recentChanges,
            'avg_order_value' => $totalPurchases > 0
                ? round($totalRevenue / $totalPurchases, 2)
                : 0,
        ];
    }

    /* ───────── Auth helpers ───────── */

    private function authorizeOwnership(Event $event): void
    {
        $userId = Auth::id();
        if ($event->photographer_id !== $userId) {
            abort(403, 'อีเวนต์นี้ไม่ใช่ของคุณ');
        }
    }

    private function authorizePackage(Event $event, PricingPackage $package): void
    {
        if ($package->event_id !== $event->id) {
            abort(404);
        }
    }
}

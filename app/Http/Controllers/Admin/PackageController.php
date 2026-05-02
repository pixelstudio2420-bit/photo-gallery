<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PricingPackage;
use App\Models\Event;
use App\Services\Pricing\SmartPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        // Order by event first then sort_order so packages from the same
        // event sit next to each other in the list — eliminates the
        // "5 names repeating across the table" appearance the buyer
        // reported when each event has a default 5-bundle template.
        $query = PricingPackage::query()
            ->with('event:id,name')
            ->orderBy('event_id')
            ->orderBy('sort_order')
            ->orderBy('photo_count');

        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $packages = $query->paginate(50);
        $events = Event::orderBy('name')->get(['id', 'name']);

        // Stats banner — gives the admin context that 15 rows is "5
        // bundles × 3 events", not actual duplicate data.
        $stats = [
            'total'    => PricingPackage::count(),
            'events'   => PricingPackage::distinct()->count('event_id'),
            'global'   => PricingPackage::whereNull('event_id')->count(),
            'featured' => PricingPackage::where('is_featured', true)->count(),
        ];

        return view('admin.packages.index', compact('packages', 'events', 'stats'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'photo_count' => 'required|integer|min:1',
            'price'       => 'required|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'event_id'    => 'nullable|exists:events,id',
            'is_active'   => 'nullable|boolean',
        ]);

        PricingPackage::create([
            'name'        => $request->name,
            'photo_count' => $request->photo_count,
            'price'       => $request->price,
            'description' => $request->description,
            'event_id'    => $request->event_id,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'เพิ่มแพ็คเกจสำเร็จ');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'photo_count' => 'required|integer|min:1',
            'price'       => 'required|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'event_id'    => 'nullable|exists:events,id',
            'is_active'   => 'nullable|boolean',
        ]);

        $pkg = PricingPackage::findOrFail($id);
        $pkg->update([
            'name'        => $request->name,
            'photo_count' => $request->photo_count,
            'price'       => $request->price,
            'description' => $request->description,
            'event_id'    => $request->event_id,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'แก้ไขแพ็คเกจสำเร็จ');
    }

    public function destroy($id)
    {
        PricingPackage::findOrFail($id)->delete();
        return back()->with('success', 'ลบแพ็คเกจสำเร็จ');
    }

    /* ════════════════════════════════════════════════════════════════
     * Auto-calculate price (Smart Pricing) — read endpoint for the
     * "auto" button next to the Price input in the modal. Returns the
     * suggested price + breakdown given (event_id, photo_count,
     * is_featured) so the admin can preview the discount + savings
     * before deciding to use it. The price stays editable on the form.
     * ════════════════════════════════════════════════════════════════ */
    public function calculatePrice(Request $request, SmartPricingService $smart)
    {
        $validated = $request->validate([
            'event_id'    => 'nullable|exists:event_events,id',
            'photo_count' => 'required|integer|min:1|max:1000',
            'is_featured' => 'nullable|boolean',
        ]);

        // Per-photo price drives the entire SmartPricing curve.
        // Priority: chosen event's price_per_photo → fallback to the
        // platform-wide average (so global / no-event packages still
        // get a reasonable suggestion). The min-floor 100 keeps the
        // tier classifier (low/mid/premium) honest if the platform
        // somehow has no events priced.
        $perPhoto = 0.0;
        $source   = 'platform_avg';
        if (!empty($validated['event_id'])) {
            $event = Event::find($validated['event_id']);
            if ($event && $event->price_per_photo > 0) {
                $perPhoto = (float) $event->price_per_photo;
                $source   = 'event';
            }
        }
        if ($perPhoto <= 0) {
            $avg = (float) Event::where('price_per_photo', '>', 0)->avg('price_per_photo');
            $perPhoto = max(100.0, $avg ?: 100.0);
        }

        $result = $smart->computeBundlePrice(
            (int)  $validated['photo_count'],
            $perPhoto,
            (bool) ($validated['is_featured'] ?? false),
        );

        return response()->json([
            'price'               => $result['price'],
            'original_price'      => $result['original_price'],
            'discount_pct'        => $result['discount_pct'],
            'savings'             => $result['savings'],
            'effective_per_photo' => $result['effective_per_photo'],
            'floor_applied'       => $result['floor_applied'],
            'per_photo'           => round($perPhoto, 2),
            'tier'                => $smart->priceTier($perPhoto),
            'source'              => $source,
        ]);
    }

    /* ════════════════════════════════════════════════════════════════
     * Bulk recalculate — sweep through all event-bound non-face_match
     * packages and overwrite their price using SmartPricing's curve
     * for the host event's per_photo. Skips global packages (no host
     * event = no per_photo to base the calc on) and face_match (price
     * is variable per match-count, computed at runtime).
     *
     * Honours an optional ?event_id filter so the admin can target a
     * single event (e.g. after editing the event's per_photo price).
     * ════════════════════════════════════════════════════════════════ */
    public function recalculateAll(Request $request, SmartPricingService $smart)
    {
        $validated = $request->validate([
            'event_id' => 'nullable|exists:event_events,id',
        ]);

        $query = PricingPackage::query()
            ->whereNotNull('event_id')
            ->whereNotNull('photo_count')
            ->where('bundle_type', '!=', 'face_match')
            ->with('event:id,price_per_photo');

        if (!empty($validated['event_id'])) {
            $query->where('event_id', $validated['event_id']);
        }

        $updated = 0;
        $skipped = 0;
        DB::beginTransaction();
        try {
            foreach ($query->cursor() as $pkg) {
                $perPhoto = (float) ($pkg->event?->price_per_photo ?? 0);
                if ($perPhoto <= 0) { $skipped++; continue; }

                $r = $smart->computeBundlePrice(
                    (int)  $pkg->photo_count,
                    $perPhoto,
                    (bool) ($pkg->is_featured ?? false),
                );

                $pkg->update([
                    'price'          => $r['price'],
                    'original_price' => $r['original_price'],
                    'discount_pct'   => $r['discount_pct'],
                ]);
                $updated++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'คำนวณไม่สำเร็จ: ' . $e->getMessage());
        }

        $msg = "คำนวณราคาใหม่ {$updated} แพ็กเกจ";
        if ($skipped > 0) $msg .= " (ข้าม {$skipped} แพ็กเกจที่ไม่มีราคา/ภาพ)";
        return back()->with('success', $msg);
    }
}

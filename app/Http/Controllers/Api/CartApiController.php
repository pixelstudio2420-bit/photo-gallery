<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\PricingPackage;
use App\Services\CartService;
use App\Services\Pricing\BundleService;
use Illuminate\Http\Request;

class CartApiController extends Controller
{
    protected CartService $cartService;
    protected BundleService $bundles;

    public function __construct(CartService $cartService, BundleService $bundles)
    {
        $this->cartService = $cartService;
        $this->bundles     = $bundles;
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'items'   => $this->cartService->getItems(),
            'total'   => $this->cartService->getTotal(),
            'count'   => $this->cartService->count(),
        ]);
    }

    public function add(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|exists:event_events,id',
            'photo_id' => 'required|string',
            'name'     => 'nullable|string|max:255',
            'price'    => 'required|numeric|min:0',
            'thumbnail'=> 'nullable|string',
            'file_id'  => 'nullable|string',
        ]);

        $this->cartService->add([
            'event_id'  => $validated['event_id'],
            'photo_id'  => $validated['photo_id'],
            'name'      => $validated['name'] ?? 'Photo',
            'price'     => $validated['price'],
            'thumbnail' => $validated['thumbnail'] ?? '',
            'file_id'   => $validated['file_id'] ?? null,
            'quantity'  => 1,
        ]);

        return response()->json([
            'success' => true,
            'count'   => $this->cartService->count(),
            'total'   => $this->cartService->getTotal(),
        ]);
    }

    public function remove($item)
    {
        $this->cartService->remove((string) $item);

        return response()->json([
            'success' => true,
            'count'   => $this->cartService->count(),
            'total'   => $this->cartService->getTotal(),
        ]);
    }

    /**
     * Add a package bundle to cart (buy N photos at package price).
     */
    public function addBundle(Request $request)
    {
        $validated = $request->validate([
            'event_id'   => 'required|integer|exists:event_events,id',
            'package_id' => 'required|integer|exists:pricing_packages,id',
            'photo_ids'  => 'required|array|min:1',
            'photo_ids.*'=> 'string',
        ]);

        $package = \App\Models\PricingPackage::where('id', $validated['package_id'])->where('is_active', true)->first();

        if (!$package) {
            return response()->json(['success' => false, 'message' => 'ไม่พบแพ็กเกจ'], 404);
        }

        $photoIds = array_slice($validated['photo_ids'], 0, $package->photo_count ?? count($validated['photo_ids']));

        // Clear existing items from same event
        $items = $this->cartService->getItems();
        foreach ($items as $key => $item) {
            if (($item['event_id'] ?? null) == $validated['event_id']) {
                $this->cartService->remove($key);
            }
        }

        // Add bundle as a single cart item
        $bundleKey = 'bundle_' . $validated['event_id'] . '_' . $validated['package_id'];
        $event = \App\Models\Event::find($validated['event_id']);

        $this->cartService->add([
            'photo_id'   => $bundleKey,
            'event_id'   => $validated['event_id'],
            'name'       => ($event->name ?? 'Event') . ' — ' . ($package->name ?? 'Package'),
            'price'      => (float)$package->price,
            'price_type' => 'bundle',
            'thumbnail'  => '',
            'quantity'    => 1,
            'bundle_photo_ids' => $photoIds,
            'package_id' => $validated['package_id'],
        ]);

        return response()->json([
            'success'      => true,
            'count'        => $this->cartService->count(),
            'total'        => $this->cartService->getTotal(),
            'photo_count'  => count($photoIds),
            'package_name' => $package->name,
        ]);
    }

    /* ═══════════════════════════════════════════════════════════════
     * NEW: Smart upsell + Face bundle endpoints (2026-05-01)
     * ═══════════════════════════════════════════════════════════════ */

    /**
     * Given the buyer's current cart for a given event, return the best
     * upsell suggestion (next bundle that gives more value per photo).
     *
     * Used by the cart-sidebar widget to nudge the buyer:
     *   "เพิ่มอีก 2 รูป = ฿480 (ประหยัด ฿120!)"
     */
    public function upsellSuggestion(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|exists:event_events,id',
        ]);

        $event = Event::find($validated['event_id']);

        // Count items in cart that belong to this event AND are individual
        // photos (not already-bundled rows).
        $items = $this->cartService->getItems();
        $cartCount = 0;
        $cartTotal = 0;
        foreach ($items as $item) {
            if (($item['event_id'] ?? null) == $event->id && ($item['price_type'] ?? null) !== 'bundle') {
                $cartCount++;
                $cartTotal += (float) ($item['price'] ?? 0);
            }
        }

        if ($cartCount < 1) {
            return response()->json(['success' => true, 'suggestion' => null]);
        }

        $suggestion = $this->bundles->findBestUpsell($event, $cartCount, $cartTotal);

        return response()->json([
            'success'    => true,
            'suggestion' => $suggestion,
            'cart_count' => $cartCount,
            'cart_total' => $cartTotal,
        ]);
    }

    /**
     * Quote a face_match bundle price for the given photo IDs that face
     * search returned. Doesn't mutate the cart — only returns the breakdown
     * so the modal can show "found 23 photos, ฿1,150" before the buyer
     * decides to add.
     */
    public function faceBundleQuote(Request $request)
    {
        $validated = $request->validate([
            'event_id'   => 'required|integer|exists:event_events,id',
            'package_id' => 'required|integer|exists:pricing_packages,id',
            'photo_ids'  => 'required|array|min:1',
            'photo_ids.*'=> 'string',
        ]);

        $event   = Event::find($validated['event_id']);
        $package = PricingPackage::where('id', $validated['package_id'])
            ->where('is_active', true)
            ->faceMatch()
            ->first();

        if (!$package) {
            return response()->json(['success' => false, 'message' => 'แพ็กเกจไม่พร้อมใช้'], 404);
        }

        $quote = $this->bundles->calculateFaceBundle($event, count($validated['photo_ids']), $package);
        if (!$quote) {
            return response()->json(['success' => false, 'message' => 'คำนวณราคาไม่ได้'], 422);
        }

        return response()->json([
            'success' => true,
            'quote'   => $quote,
        ]);
    }

    /**
     * Add a face_match bundle to the cart with the buyer's specific
     * photo IDs (the ones face-search returned for them).
     *
     * The bundle is stored as a single cart row with price = computed
     * face-bundle price. We replace any existing items from the same event
     * to avoid double-charging.
     */
    public function addFaceBundle(Request $request)
    {
        $validated = $request->validate([
            'event_id'   => 'required|integer|exists:event_events,id',
            'package_id' => 'required|integer|exists:pricing_packages,id',
            'photo_ids'  => 'required|array|min:1',
            'photo_ids.*'=> 'string',
        ]);

        $event   = Event::find($validated['event_id']);
        $package = PricingPackage::where('id', $validated['package_id'])
            ->where('is_active', true)
            ->faceMatch()
            ->first();

        if (!$package) {
            return response()->json(['success' => false, 'message' => 'แพ็กเกจไม่พร้อมใช้'], 404);
        }

        $quote = $this->bundles->calculateFaceBundle($event, count($validated['photo_ids']), $package);
        if (!$quote) {
            return response()->json(['success' => false, 'message' => 'คำนวณราคาไม่ได้'], 422);
        }

        // Wipe other items from the same event — buying the face bundle is
        // an "all-in" decision, no point keeping individual selections.
        $items = $this->cartService->getItems();
        foreach ($items as $key => $item) {
            if (($item['event_id'] ?? null) == $event->id) {
                $this->cartService->remove($key);
            }
        }

        $bundleKey = 'face_bundle_' . $event->id . '_' . $package->id;
        $this->cartService->add([
            'photo_id'         => $bundleKey,
            'event_id'         => $event->id,
            'name'             => ($event->name ?? 'Event') . ' — เหมารูปตัวเอง (' . count($validated['photo_ids']) . ' รูป)',
            'price'            => $quote['price'],
            'price_type'       => 'bundle',
            'thumbnail'        => '',
            'quantity'         => 1,
            'bundle_photo_ids' => $validated['photo_ids'],
            'package_id'       => $package->id,
        ]);

        return response()->json([
            'success'     => true,
            'count'       => $this->cartService->count(),
            'total'       => $this->cartService->getTotal(),
            'photo_count' => count($validated['photo_ids']),
            'price'       => $quote['price'],
            'savings'     => $quote['savings'],
        ]);
    }
}

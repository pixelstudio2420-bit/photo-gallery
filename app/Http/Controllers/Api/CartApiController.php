<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartApiController extends Controller
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
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
}

<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\EventPriceResolver;
use Illuminate\Http\Request;

class CartController extends Controller
{
    private CartService $cart;
    private EventPriceResolver $prices;

    public function __construct(CartService $cart, EventPriceResolver $prices)
    {
        $this->cart   = $cart;
        $this->prices = $prices;
    }

    public function index()
    {
        $cartItems = $this->cart->getItems();
        $total = $this->cart->getTotal();

        // Re-evaluate any saved coupon / referral codes against the current cart
        // total — codes can become invalid (expired, exhausted) between visits,
        // and the user may have edited quantities since they applied them.
        [$couponCode, $couponDiscount] = $this->resolveCouponDiscount($total);
        [$referralCode, $referralDiscount] = $this->resolveReferralDiscount($total);

        $discount = round(min($couponDiscount + $referralDiscount, $total), 2);

        return view('public.cart.index', compact(
            'cartItems', 'total', 'discount',
            'couponCode', 'couponDiscount',
            'referralCode', 'referralDiscount'
        ));
    }

    /**
     * Re-validate the session-stored coupon against the current cart total.
     * Drops the code from session if it's no longer valid (cart shrunk below
     * min_order, coupon expired, etc.) so stale state doesn't leak forward.
     *
     * @return array{0:?string,1:float} [couponCode, discountAmount]
     */
    private function resolveCouponDiscount(float $total): array
    {
        $code = session('cart_coupon_code');
        if (!$code) return [null, 0.0];

        $coupon = \App\Models\Coupon::where('code', $code)->first();
        if (!$coupon || !$coupon->isValid()) {
            session()->forget(['cart_coupon_code', 'cart_coupon_discount']);
            return [null, 0.0];
        }

        $discount = (float) $coupon->calculateDiscount($total);
        session()->put('cart_coupon_discount', $discount);

        return [$coupon->code, $discount];
    }

    /**
     * Re-validate the session-stored referral code against the current cart.
     * Same defensive re-check as coupons.
     *
     * @return array{0:?string,1:float} [referralCode, discountAmount]
     */
    private function resolveReferralDiscount(float $total): array
    {
        $code = session('referral_code');
        if (!$code) return [null, 0.0];

        try {
            $svc = app(\App\Services\Marketing\ReferralService::class);
            $result = $svc->apply($code, $total, auth()->id());
            if (!empty($result['ok']) && !empty($result['code'])) {
                $discount = (float) $result['discount'];
                session()->put('referral_discount', $discount);
                return [$result['code']->code, $discount];
            }
            session()->forget(['referral_code', 'referral_discount']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('cart.referral_resolve_failed', [
                'code' => $code, 'err' => $e->getMessage(),
            ]);
        }
        return [null, 0.0];
    }

    public function add(Request $request)
    {
        $request->validate([
            'file_id'  => 'required|string',
            'name'     => 'required|string|max:255',
            'event_id' => 'nullable|integer',
        ]);

        // Never trust client-submitted prices — look it up server-side. Keeps
        // the cart UI total honest (it already was at checkout, but users
        // would see a different number in the cart vs the order page).
        $price = $this->prices->perPhoto($request->integer('event_id'));

        $this->cart->add([
            'photo_id'  => $request->file_id,
            'file_id'   => $request->file_id,
            'event_id'  => $request->event_id,
            'name'      => $request->name,
            'thumbnail' => $request->thumbnail ?? '',
            'price'     => $price,
            'quantity'  => 1,
        ]);

        return back()->with('success', 'Added to cart');
    }

    public function remove(Request $request)
    {
        $this->cart->remove($request->key);
        return back()->with('success', 'Removed from cart');
    }

    public function update(Request $request)
    {
        $this->cart->updateQuantity($request->key, (int)$request->quantity);
        return back();
    }

    /**
     * Bulk add items to cart (single request instead of N sequential requests).
     */
    public function addBulk(Request $request)
    {
        $request->validate([
            'items'           => 'required|array|min:1',
            'items.*.file_id' => 'required|string',
            'items.*.name'    => 'required|string|max:255',
        ]);

        // Per-event price cache so we don't hit the DB once per item when
        // 50 photos from the same gallery are bulk-added.
        $priceByEvent = [];
        $resolve = function (?int $eventId) use (&$priceByEvent): float {
            if (!$eventId) return 0.0;
            return $priceByEvent[$eventId]
                ??= $this->prices->perPhoto($eventId);
        };

        $added = 0;
        foreach ($request->items as $item) {
            $eventId = isset($item['event_id']) ? (int) $item['event_id'] : null;
            $this->cart->add([
                'photo_id'  => $item['file_id'],
                'file_id'   => $item['file_id'],
                'event_id'  => $eventId,
                'name'      => $item['name'],
                'thumbnail' => $item['thumbnail'] ?? '',
                'price'     => $resolve($eventId),
                'quantity'  => 1,
            ]);
            $added++;
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'added' => $added]);
        }
        return back()->with('success', "เพิ่ม {$added} รายการลงตะกร้าแล้ว");
    }
}

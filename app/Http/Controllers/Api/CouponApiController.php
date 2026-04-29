<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\CartService;
use App\Services\Marketing\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CouponApiController extends Controller
{
    /**
     * Apply a coupon code against the current cart.
     *
     * Accepts either:
     *   - JSON payload with cart_total (legacy API client)
     *   - form POST with just `coupon_code` (cart UI) — we look up the cart
     *     ourselves so the user doesn't have to send a tampered total.
     *
     * On success, the validated coupon code is also stashed in session so
     * subsequent page renders + the order create call know to apply it.
     */
    public function apply(Request $request)
    {
        $code = trim((string) $request->input('coupon_code', $request->input('code', '')));
        if ($code === '') {
            return $this->respond($request, false, 'กรุณากรอกโค้ดส่วนลด');
        }
        $code = strtoupper($code);

        // Determine cart_total: prefer client-supplied (for API callers), else
        // resolve it from the current cart session (for cart UI).
        $cartTotal = $request->has('cart_total')
            ? (float) $request->input('cart_total')
            : app(CartService::class)->getTotal();

        if ($cartTotal <= 0) {
            return $this->respond($request, false, 'ตะกร้าว่างเปล่า');
        }

        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon) {
            return $this->respond($request, false, 'ไม่พบคูปองนี้');
        }

        if (!$coupon->isValid()) {
            return $this->respond($request, false, 'คูปองนี้หมดอายุหรือใช้งานไม่ได้');
        }

        // Check minimum order requirement
        if ($coupon->min_order && $cartTotal < (float) $coupon->min_order) {
            return $this->respond($request, false,
                'ยอดสั่งซื้อขั้นต่ำ ' . number_format($coupon->min_order, 0) . ' บาท'
            );
        }

        // Check per-user limit
        if ($coupon->per_user_limit && Auth::check()) {
            $userUsageCount = $coupon->usages()->where('user_id', Auth::id())->count();
            if ($userUsageCount >= $coupon->per_user_limit) {
                return $this->respond($request, false, 'คุณใช้คูปองนี้ครบจำนวนครั้งแล้ว');
            }
        }

        $discount = (float) $coupon->calculateDiscount($cartTotal);
        $newTotal = max(0, $cartTotal - $discount);

        // Persist into session so cart re-renders + order creation see it.
        session()->put('cart_coupon_code',     $coupon->code);
        session()->put('cart_coupon_discount', round($discount, 2));

        return $this->respond($request, true,
            'ใช้คูปองสำเร็จ ลด ' . number_format($discount, 0) . ' บาท',
            [
                'coupon_code'     => $coupon->code,
                'coupon_id'       => $coupon->id,
                'discount_amount' => round($discount, 2),
                'new_total'       => round($newTotal, 2),
            ]
        );
    }

    /**
     * Remove the currently-applied coupon from the cart.
     */
    public function remove(Request $request)
    {
        session()->forget(['cart_coupon_code', 'cart_coupon_discount']);
        return $this->respond($request, true, 'ยกเลิกโค้ดส่วนลดแล้ว');
    }

    /**
     * Apply a referral code to the current cart.
     */
    public function applyReferral(Request $request)
    {
        $code = trim((string) $request->input('referral_code', ''));
        if ($code === '') {
            return $this->respond($request, false, 'กรุณากรอกรหัสแนะนำ');
        }

        $cartTotal = app(CartService::class)->getTotal();
        if ($cartTotal <= 0) {
            return $this->respond($request, false, 'ตะกร้าว่างเปล่า');
        }

        try {
            $result = app(ReferralService::class)->apply($code, $cartTotal, Auth::id());
        } catch (\Throwable $e) {
            return $this->respond($request, false, 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }

        if (empty($result['ok'])) {
            return $this->respond($request, false, $result['message'] ?? 'รหัสแนะนำไม่ถูกต้อง');
        }

        $ref = $result['code'];
        $discount = (float) $result['discount'];

        session()->put('referral_code',     $ref->code);
        session()->put('referral_discount', round($discount, 2));

        return $this->respond($request, true,
            'ใช้รหัสแนะนำสำเร็จ ลด ' . number_format($discount, 0) . ' บาท',
            [
                'referral_code'   => $ref->code,
                'discount_amount' => round($discount, 2),
                'new_total'       => round(max(0, $cartTotal - $discount), 2),
            ]
        );
    }

    /**
     * Remove the currently-applied referral code from the cart.
     */
    public function removeReferral(Request $request)
    {
        session()->forget(['referral_code', 'referral_discount']);
        return $this->respond($request, true, 'ยกเลิกรหัสแนะนำแล้ว');
    }

    /**
     * Unified response: JSON for API clients (Accept: application/json or
     * X-Requested-With: XMLHttpRequest), redirect-back with flash for
     * vanilla form submissions.
     */
    private function respond(Request $request, bool $ok, string $message, array $extra = [])
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(array_merge([
                'success' => $ok,
                'message' => $message,
            ], $extra));
        }
        return back()->with($ok ? 'success' : 'error', $message);
    }
}

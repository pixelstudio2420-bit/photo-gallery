<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\Order;
use App\Services\GiftCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GiftCardController extends Controller
{
    public function __construct(protected GiftCardService $svc) {}

    /**
     * Public landing page to purchase a gift card.
     */
    public function index()
    {
        $presets = [500, 1000, 2000, 3000, 5000, 10000];
        return view('public.gift-cards.index', compact('presets'));
    }

    /**
     * Initiate a gift card purchase.
     *
     * SECURITY-CRITICAL FLOW (was a free-money exploit before this fix):
     *   1. Mint the gift card with status='pending' (no balance is
     *      redeemable until activated).
     *   2. Create an Order of type=gift_card linked to the card via
     *      source_order_id.
     *   3. Redirect the user to /payment/checkout/{order} just like
     *      any photo-package purchase. Payment is required.
     *   4. When the order flips to paid (webhook / admin slip approval
     *      / auto-approve), OrderFulfillmentService::fulfill() calls
     *      GiftCardService::activateFromPaidOrder() which is the only
     *      path that promotes 'pending' → 'active'.
     *
     * Login is required (route is gated by 'auth' middleware in web.php)
     * so we always have a real Order owner — guests can't mint cards.
     */
    public function purchase(Request $request)
    {
        $data = $request->validate([
            'amount'            => 'required|numeric|min:100|max:50000',
            'purchaser_name'    => 'required|string|max:120',
            'purchaser_email'   => 'required|email|max:160',
            'recipient_name'    => 'nullable|string|max:120',
            'recipient_email'   => 'nullable|email|max:160',
            'personal_message'  => 'nullable|string|max:1000',
        ]);

        $userId = Auth::id();
        if (!$userId) {
            return redirect()
                ->route('login.show')
                ->with('warning', 'กรุณาเข้าสู่ระบบเพื่อซื้อบัตรของขวัญ');
        }

        $amount = (float) $data['amount'];

        $result = DB::transaction(function () use ($data, $userId, $amount) {
            // 1) Create the order first so we have an id to bind the
            //    gift card to (source_order_id) before any payment.
            $order = Order::create([
                'user_id'        => $userId,
                'order_number'   => 'GC-' . now()->format('ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'order_type'     => Order::TYPE_GIFT_CARD,
                'subtotal'       => $amount,
                'total'          => $amount,
                'status'         => 'pending_payment',
                'note'           => "Gift card ฿" . number_format($amount, 0)
                                  . " for " . ($data['recipient_name'] ?: $data['purchaser_name']),
            ]);

            // 2) Mint the card in 'pending' state — purchase source
            //    triggers the security-aware default in GiftCardService::issue.
            $gc = $this->svc->issue([
                'amount'           => $amount,
                'purchaser_user_id'=> $userId,
                'purchaser_name'   => $data['purchaser_name'],
                'purchaser_email'  => $data['purchaser_email'],
                'recipient_name'   => $data['recipient_name']  ?? null,
                'recipient_email'  => $data['recipient_email'] ?? null,
                'personal_message' => $data['personal_message']?? null,
                'source'           => 'purchase',
                'source_order_id'  => $order->id,
                'expires_at'       => now()->addYear(),
                // status defaults to 'pending' for purchase source
            ]);

            return ['order' => $order, 'gift_card' => $gc];
        });

        // 3) Send the buyer to checkout — same flow as any other order.
        return redirect()
            ->route('payment.checkout', ['order' => $result['order']->id])
            ->with('success', 'สร้างคำสั่งซื้อบัตรของขวัญเรียบร้อย กรุณาชำระเงินเพื่อรับรหัส');
    }

    /**
     * Quick AJAX lookup used by the cart to validate a code.
     *
     * Now also explicitly rejects 'pending' cards. `isRedeemable()`
     * already enforced status === 'active' so pending cards couldn't
     * be redeemed, but rejecting upfront with a clearer message
     * (instead of "ไม่สามารถใช้งานได้") helps diagnose accidental
     * lookups while a card is awaiting payment.
     */
    public function lookup(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:40']);
        $gc = $this->svc->lookup($data['code']);

        if (!$gc) {
            return response()->json(['ok' => false, 'error' => 'ไม่พบรหัสนี้'], 404);
        }
        if ($gc->status === 'pending') {
            return response()->json([
                'ok' => false,
                'error' => 'บัตรนี้ยังไม่ได้ชำระเงิน รอการยืนยันการชำระก่อนใช้งาน'
            ], 422);
        }
        if (!$gc->isRedeemable()) {
            return response()->json(['ok' => false, 'error' => 'บัตรนี้ไม่สามารถใช้งานได้'], 422);
        }

        return response()->json([
            'ok'      => true,
            'code'    => $gc->code,
            'balance' => number_format((float) $gc->balance, 2, '.', ''),
            'expires' => optional($gc->expires_at)->toIso8601String(),
        ]);
    }
}

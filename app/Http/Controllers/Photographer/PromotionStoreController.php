<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Order;
use App\Models\PhotographerProfile;
use App\Models\SubscriptionPlan;
use App\Services\Monetization\AddonService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Photographer self-serve store.
 *
 * Routes (under /photographer/store):
 *   GET  /                 — catalog (promotions + storage + credits + branding)
 *   POST /buy/{sku}        — create pending purchase + Order, redirect to checkout.
 *                              The webhook → OrderFulfillmentService → AddonService
 *                              activation happens AFTER payment clears.
 *   GET  /history          — purchase history (full ledger)
 *   GET  /status           — "my plan + add-ons + usage" — comprehensive view of
 *                              what the photographer is paying for and how much
 *                              they've consumed.
 *
 * V2 payment flow (current)
 * -------------------------
 * Wired to the same payment-gateway loop as credit packages and
 * subscriptions. The buy() handler now creates a pending Order
 * (type='addon', addon_purchase_id pointing at the matching purchase
 * row) and redirects to /payment/checkout — exactly like
 * CreditController::buy() and SubscriptionController::subscribe().
 *
 * The activation handler (AddonService::activate) only runs once the
 * Order flips to 'paid' via OrderFulfillmentService::fulfill(). This
 * means a photographer cannot use an add-on without paying first — the
 * V1 instant-activate hack is gone.
 */
class PromotionStoreController extends Controller
{
    public function __construct(
        private readonly AddonService $addons,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function index(): View
    {
        $catalog = $this->addons->catalog();

        // Recent purchase history — shown as a strip on the catalog page so
        // photographers can re-buy/renew quickly. Full paginated view at
        // /photographer/store/history.
        $history = DB::table('photographer_addon_purchases')
            ->where('photographer_id', (int) Auth::id())
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('photographer.store.index', compact('catalog', 'history'));
    }

    /**
     * Start a purchase. Creates the pending photographer_addon_purchases
     * row + a matching Order, then redirects to payment.checkout — same
     * shared screen credit-package and subscription buys use.
     *
     * The activation runs only after OrderFulfillmentService observes the
     * Order flip to 'paid'. Photographer sees a "purchase pending —
     * complete payment" status until the webhook lands.
     */
    public function buy(Request $request, string $sku): RedirectResponse
    {
        $item = $this->addons->findBySku($sku);
        if (!$item) abort(404, 'Addon not found');

        try {
            $result = DB::transaction(function () use ($sku, $item) {
                // 1. Create the addon-purchase row (status=pending). The
                //    AddonService keeps this in sync with the snapshot
                //    column so refunds can compare prices later.
                $purchase = $this->addons->buy((int) Auth::id(), $sku);

                // 2. Create the matching Order. Same pattern as credit
                //    packages: order_type='addon', total = catalog price,
                //    addon_purchase_id linking back. The Order stays
                //    pending_payment until the webhook lands.
                $order = Order::create([
                    'user_id'           => Auth::id(),
                    'event_id'          => null,
                    'package_id'        => null,
                    'order_number'      => $this->generateOrderNumber(),
                    'total'             => (float) $item['price_thb'],
                    'subtotal'          => (float) $item['price_thb'],
                    'status'            => 'pending_payment',
                    'note'              => "Add-on: {$item['label']} (SKU {$sku})",
                    // Addon orders have no photos to deliver — use 'auto'
                    // which is the schema's "no specific channel" sentinel
                    // (enum allows web/line/email/auto only). The
                    // OrderFulfillmentService addon branch ignores this
                    // field entirely.
                    'delivery_method'   => 'auto',
                    'delivery_status'   => 'pending',
                    'order_type'        => Order::TYPE_ADDON,
                    'addon_purchase_id' => $purchase['purchase_id'],
                ]);

                // 3. Cross-link: write the order_id back onto the addon
                //    purchase row so admin tools can find both directions.
                DB::table('photographer_addon_purchases')
                    ->where('id', $purchase['purchase_id'])
                    ->update(['order_id' => $order->id, 'updated_at' => now()]);

                return ['order' => $order, 'item' => $item];
            });
        } catch (\Throwable $e) {
            Log::error('PromotionStoreController::buy — failed', [
                'user_id' => Auth::id(),
                'sku'     => $sku,
                'error'   => $e->getMessage(),
            ]);
            return back()->with('error', 'ไม่สามารถสร้างคำสั่งซื้อได้ กรุณาลองใหม่อีกครั้ง');
        }

        // 4. Hand off to the shared payment checkout. After payment clears,
        //    OrderFulfillmentService::fulfill() spots order_type='addon' and
        //    runs AddonService::activate() — see the class doc.
        return redirect()
            ->route('payment.checkout', ['order' => $result['order']->id])
            ->with('success',
                "สั่งซื้อ \"{$result['item']['label']}\" แล้ว · ฿"
                . number_format($result['item']['price_thb'], 0)
                . ' — กรุณาชำระเงินเพื่อเปิดใช้งาน');
    }

    public function history(): View
    {
        $history = DB::table('photographer_addon_purchases')
            ->where('photographer_id', (int) Auth::id())
            ->orderByDesc('id')
            ->paginate(20);

        return view('photographer.store.history', compact('history'));
    }

    /**
     * Comprehensive "what am I paying for and how much have I used"
     * status page — collects the photographer's plan, all active add-ons,
     * and their consumption ratios into one screen. Drives the
     * dashboard's "my plan" link.
     */
    public function status(): View
    {
        $userId  = (int) Auth::id();
        $profile = PhotographerProfile::where('user_id', $userId)->first();

        // Plan + usage summary (the existing rich method on
        // SubscriptionService — covers storage / events / AI credits /
        // commission / renewal date).
        $summary = $profile
            ? $this->subscriptions->dashboardSummary($profile)
            : [];

        // Active add-ons grouped by category. Active = status='activated'
        // AND (expires_at IS NULL OR expires_at > now()). Expired rows
        // still show in the history page for audit; here we only show
        // what's currently buying the photographer something.
        $now = now();
        $activeAddons = DB::table('photographer_addon_purchases')
            ->where('photographer_id', $userId)
            ->where('status', 'activated')
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->orderBy('category')
            ->orderByDesc('id')
            ->get();

        // Group + decode the snapshot for label/price/cycle display.
        $byCategory = [];
        foreach ($activeAddons as $row) {
            $snap = json_decode((string) $row->snapshot, true) ?: [];
            $byCategory[$row->category][] = (object) [
                'id'           => $row->id,
                'sku'          => $row->sku,
                'label'        => $snap['label']   ?? $row->sku,
                'tagline'      => $snap['tagline'] ?? null,
                'price_thb'    => (float) $row->price_thb,
                'activated_at' => $row->activated_at,
                'expires_at'   => $row->expires_at,
                'snapshot'     => $snap,
            ];
        }

        // Pending purchases — order created but payment not yet received.
        // Photographer needs to know they have a half-finished checkout.
        $pendingPurchases = DB::table('photographer_addon_purchases as ap')
            ->leftJoin('orders as o', 'o.id', '=', 'ap.order_id')
            ->where('ap.photographer_id', $userId)
            ->whereIn('ap.status', ['pending', 'paid'])   // paid-but-not-yet-activated edge
            ->whereNotIn('o.status', ['paid', 'cancelled', 'refunded'])
            ->select(
                'ap.id', 'ap.sku', 'ap.snapshot', 'ap.price_thb',
                'ap.created_at', 'ap.order_id', 'o.status as order_status',
            )
            ->orderByDesc('ap.id')
            ->get()
            ->map(function ($row) {
                $row->snapshot_decoded = json_decode((string) $row->snapshot, true) ?: [];
                return $row;
            });

        // Branding / priority flags persist as app_settings entries — query
        // them so the status page can show them as "active features"
        // alongside the ephemeral purchases.
        $brandingFlags = [];
        foreach (['branding.custom_watermark', 'priority.upload_lane'] as $sku) {
            $key = "addon_flag:{$userId}:{$sku}";
            if (AppSetting::get($key, '0') === '1') {
                $brandingFlags[] = $sku;
            }
        }

        return view('photographer.store.status', compact(
            'profile', 'summary', 'byCategory',
            'pendingPurchases', 'brandingFlags',
        ));
    }

    /**
     * Generate an order_number formatted like other photographer-facing
     * orders. Mirrors CreditController::generateOrderNumber so all the
     * photographer's orders read consistently in admin tools.
     */
    private function generateOrderNumber(): string
    {
        return 'AD-' . now()->format('ymd') . '-' . strtoupper(Str::random(6));
    }
}

<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Event;
use App\Models\UserNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PricingPackage;
use App\Services\CartService;
use App\Services\EventPriceResolver;
use App\Services\InvoiceService;
use App\Services\Marketing\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::where('user_id', Auth::id())
            ->withCount('items')
            ->orderByDesc('created_at')
            ->paginate(20);

        // Mark which paid orders the customer has already reviewed.
        // Used by the view to decide between "เขียนรีวิว" (no review yet) vs
        // "✓ รีวิวแล้ว" (already reviewed) buttons next to each paid row.
        $reviewedOrderIds = \App\Models\Review::where('user_id', Auth::id())
            ->whereIn('order_id', $orders->pluck('id'))
            ->pluck('order_id')
            ->all();

        return view('public.orders.index', compact('orders', 'reviewedOrderIds'));
    }

    public function show($id)
    {
        $order = Order::where('id', $id)
            ->where('user_id', Auth::id())
            ->with('items')
            ->firstOrFail();

        // Has the customer already reviewed this paid order? If yes, show
        // the existing review (or "review submitted" status). If no, show
        // the prominent "เขียนรีวิว" CTA.
        $existingReview = $order->status === 'paid'
            ? \App\Models\Review::where('user_id', Auth::id())->where('order_id', $order->id)->first()
            : null;

        // Pre-load photo metadata so the view can show real filenames /
        // thumbnails rather than "Photo" placeholders. OrderItem stores only
        // photo_id + thumbnail_url + price; the human-readable filename
        // lives on EventPhoto.original_filename.
        $photoIds = $order->items->pluck('photo_id')
            ->filter()
            ->map(fn ($v) => is_numeric($v) ? (int) $v : null)
            ->filter()
            ->all();
        $photoLookup = !empty($photoIds)
            ? \App\Models\EventPhoto::whereIn('id', $photoIds)
                ->get(['id', 'original_filename', 'thumbnail_path', 'storage_disk'])
                ->keyBy('id')
            : collect();

        // Map photo_id → DownloadToken for per-row "ดาวน์โหลด" buttons.
        // Tokens are written by PhotoDeliveryService when the order is paid;
        // an unpaid order has none and the buttons stay hidden in the view.
        $tokenLookup = collect();
        if ($order->status === 'paid' && \Illuminate\Support\Facades\Schema::hasTable('download_tokens')) {
            $tokenLookup = \App\Models\DownloadToken::where('order_id', $order->id)
                ->whereNotNull('photo_id')
                ->get(['photo_id', 'token'])
                ->keyBy('photo_id');
        }

        return view('public.orders.show', compact(
            'order', 'existingReview', 'photoLookup', 'tokenLookup'
        ));
    }

    public function store(Request $request)
    {
        $cart = app(CartService::class);
        $items = $cart->getItems();

        if (empty($items)) {
            return back()->with('error', 'Cart is empty');
        }

        $packageId      = $request->input('package_id');
        $deliveryMethod = $this->sanitizeDeliveryMethod($request->input('delivery_method'));
        $couponCode     = trim((string) $request->input('coupon_code', ''));
        $referralCode   = trim((string) $request->input('referral_code', session('referral_code', '')));

        $order = DB::transaction(function () use ($items, $cart, $packageId, $deliveryMethod, $couponCode, $referralCode) {
            // Resolve each item's event_id + that event's per-photo price
            // SERVER-SIDE (never trust the price the client put in the cart).
            // This handles multi-event carts correctly — a buyer can put
            // photos from Event A and Event B in the same cart and pay
            // Event A's price for A photos and Event B's price for B
            // photos. Without this loop the whole order was priced as if
            // every photo came from the first item's event.
            //
            // Bundle rows (added via /api/cart/face-bundle/add) are an
            // exception: they store one synthetic row at the bundle's total
            // price plus a list of `bundle_photo_ids`. We trust the bundle
            // row's price (it was already validated when added) and remember
            // the photo IDs so the OrderItem creation loop below can expand
            // the bundle into per-photo rows with real thumbnails — without
            // that expansion the payment checkout view shows a single line
            // with no thumbnail, and the bundle's discount disappeared
            // because the old code overrode every row's price with the
            // per-photo rate.
            $itemEventPrices  = [];
            $subtotal         = 0.0;
            $eventIds         = [];
            $appliedPackageId = null;

            foreach ($items as $idx => $item) {
                $itemEventId   = !empty($item['event_id']) ? (int) $item['event_id'] : null;
                $bundlePhotoIds = (array) ($item['bundle_photo_ids'] ?? []);
                $isBundleRow    = !empty($bundlePhotoIds)
                               || (($item['price_type'] ?? '') === 'bundle');

                if ($isBundleRow) {
                    $bundlePrice = (float) ($item['price'] ?? 0);
                    $itemEventPrices[$idx] = [
                        'event_id'        => $itemEventId,
                        'price'           => $bundlePrice,
                        'is_bundle'       => true,
                        'bundle_photo_ids'=> $bundlePhotoIds,
                    ];
                    $subtotal += $bundlePrice;
                    // The cart row already carries the package_id for the
                    // bundle; promote it onto the order header so payment
                    // checkout / receipts know this was a bundle purchase.
                    if (!empty($item['package_id']) && !$appliedPackageId) {
                        $appliedPackageId = (int) $item['package_id'];
                    }
                } else {
                    $itemPrice = $itemEventId ? $this->getServerPrice($itemEventId) : 0.0;
                    $itemEventPrices[$idx] = [
                        'event_id'  => $itemEventId,
                        'price'     => $itemPrice,
                        'is_bundle' => false,
                    ];
                    $subtotal += $itemPrice;
                }
                if ($itemEventId) $eventIds[$itemEventId] = true;
            }

            // Pick a "primary" event_id for the order header — the first
            // event_id present. Per-item event_id is what fulfillment reads
            // for payout splitting; this header column is for back-compat
            // (analytics queries that group by orders.event_id).
            $primaryEventId = !empty($eventIds) ? array_key_first($eventIds) : null;

            // Apply explicit package_id from the request when no bundle row
            // already supplied one. Same dual-shape support as expressCheckout:
            //   • count / event_all bundle — fixed N for ฿X
            //   • face_match  bundle      — variable N, percentage off
            // Packages are only valid for SINGLE-event carts.
            if ($packageId && !$appliedPackageId && count($eventIds) <= 1) {
                $package = PricingPackage::where('id', $packageId)->where('is_active', true)->first();
                if ($package) {
                    if ($package->bundle_type === PricingPackage::TYPE_FACE_MATCH && $primaryEventId) {
                        $event = \App\Models\Event::find($primaryEventId);
                        if ($event) {
                            $quote = app(\App\Services\Pricing\BundleService::class)
                                ->calculateFaceBundle($event, count($items), $package);
                            if ($quote && isset($quote['price'])) {
                                $subtotal = (float) $quote['price'];
                                $appliedPackageId = $package->id;
                                $perItemPrice = round($subtotal / max(1, count($items)), 2);
                                foreach ($itemEventPrices as $k => &$v) { $v['price'] = $perItemPrice; }
                                unset($v);
                            }
                        }
                    } elseif ((int) $package->photo_count > 0
                           && count($items) <= (int) $package->photo_count) {
                        $subtotal = (float) $package->price;
                        $appliedPackageId = $package->id;
                        $perItemPrice = round($subtotal / max(1, count($items)), 2);
                        foreach ($itemEventPrices as $k => &$v) { $v['price'] = $perItemPrice; }
                        unset($v);
                    }
                }
            }

            // Apply coupon + referral discounts on top of $subtotal
            $discount = $this->applyDiscounts($subtotal, $couponCode, $referralCode, Auth::id());
            $total = max(0.0, round($subtotal - $discount['amount'], 2));

            // Generate unique order number
            $orderNumber = 'ORD-' . strtoupper(Str::random(12));

            $order = Order::create([
                'user_id'          => Auth::id(),
                'event_id'         => $primaryEventId,
                'package_id'       => $appliedPackageId,
                'order_number'     => $orderNumber,
                'subtotal'         => $subtotal,
                'discount_amount'  => $discount['amount'],
                'total'            => $total,
                'coupon_id'        => $discount['coupon_id'],
                'coupon_code'      => $discount['coupon_code'],
                'referral_code_id' => $discount['referral_code_id'],
                'status'           => 'pending_payment',
                'delivery_method'  => $deliveryMethod,
                'delivery_status'  => 'pending',
            ]);

            // Pre-fetch photos referenced by any bundle row so we can stamp
            // real thumbnails onto the per-photo OrderItems instead of the
            // empty string the bundle row carries. One query per event
            // regardless of bundle size.
            $bundlePhotoLookup = [];
            foreach ($itemEventPrices as $iep) {
                if (empty($iep['is_bundle']) || empty($iep['bundle_photo_ids'])) continue;
                $eid = $iep['event_id'];
                if (!$eid) continue;
                $ids = array_map('intval', $iep['bundle_photo_ids']);
                if (empty($ids)) continue;
                $rows = \App\Models\EventPhoto::whereIn('id', $ids)
                    ->where('event_id', $eid)
                    ->get(['id', 'thumbnail_path', 'storage_disk', 'original_filename']);
                foreach ($rows as $p) {
                    $bundlePhotoLookup[$eid][$p->id] = [
                        'thumbnail' => (string) ($p->thumbnail_url ?? ''),
                        'name'      => (string) ($p->original_filename ?: ('Photo #' . $p->id)),
                    ];
                }
            }

            foreach ($items as $idx => $item) {
                $iep = $itemEventPrices[$idx];

                if (!empty($iep['is_bundle']) && !empty($iep['bundle_photo_ids'])) {
                    // Expand bundle row into N per-photo OrderItems so the
                    // payment checkout can render thumbnails + the customer
                    // sees what's actually in the bundle. Per-row price is
                    // bundle_price/N so summing OrderItems still equals
                    // order.total (helps invoicing + reconciliation).
                    $bundleIds   = $iep['bundle_photo_ids'];
                    $bundleCount = max(1, count($bundleIds));
                    $perItem     = round((float) $iep['price'] / $bundleCount, 2);
                    foreach ($bundleIds as $photoId) {
                        $meta = $bundlePhotoLookup[$iep['event_id']][$photoId] ?? null;
                        OrderItem::create([
                            'order_id'      => $order->id,
                            'event_id'      => $iep['event_id'],
                            'photo_id'      => (string) $photoId,
                            'thumbnail_url' => $meta['thumbnail'] ?? '',
                            'price'         => $perItem,
                        ]);
                    }
                    continue;
                }

                OrderItem::create([
                    'order_id'      => $order->id,
                    'event_id'      => $iep['event_id'],
                    'photo_id'      => $item['file_id'] ?? $item['photo_id'] ?? '',
                    'thumbnail_url' => $item['thumbnail'] ?? null,
                    'price'         => $iep['price'],
                ]);
            }

            // Track coupon usage + referral redemption now that order exists
            $this->recordDiscountUsage($order, $discount);

            $cart->clear();

            return $order;
        });

        // Clear referral session so it doesn't apply to future unrelated orders
        session()->forget('referral_code');

        // Mark abandoned cart as recovered + log timeline
        try {
            app(\App\Services\AbandonedCartService::class)->markRecovered(
                Auth::id(),
                session()->getId(),
                $order->id
            );
        } catch (\Throwable $e) {}

        try {
            app(\App\Services\OrderTimelineService::class)->log(
                $order, 'pending_payment',
                'คำสั่งซื้อถูกสร้าง รอการชำระเงิน',
                'user', null, Auth::id()
            );
        } catch (\Throwable $e) {}

        // Notify customer (fast: just a DB insert)
        try {
            if (Schema::hasTable('user_notifications')) {
                UserNotification::create([
                    'user_id'    => Auth::id(),
                    'type'       => 'order',
                    'title'      => 'สร้างคำสั่งซื้อสำเร็จ',
                    'message'    => "คำสั่งซื้อ {$order->order_number} ยอด ฿" . number_format($order->total, 0) . " รอการชำระเงิน",
                    'is_read'    => false,
                    'action_url' => "payment/checkout/{$order->id}",
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('User notification failed: ' . $e->getMessage());
        }

        // External I/O (LINE webhook + emails) is deferred to after-
        // response so the user gets redirected to checkout immediately.
        // Previously these ran synchronously inside the request and
        // could add 1-2s of tail latency on a slow LINE/SMTP call.
        // dispatch_sync_after_response runs in the same PHP process
        // after fastcgi_finish_request, so we don't need a queue worker
        // running for it to fire.
        $orderId = $order->id;
        \Illuminate\Support\Facades\App::terminating(function () use ($orderId) {
            try {
                $o = \App\Models\Order::find($orderId);
                if (!$o) return;
                app(\App\Services\LineNotifyService::class)->notifyNewOrder([
                    'order_number' => $o->order_number,
                    'total_amount' => $o->total,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Order LINE notify deferred failed: ' . $e->getMessage());
            }
            try {
                $controller = app(self::class);
                $reflection = new \ReflectionMethod(self::class, 'sendOrderCreatedEmails');
                $reflection->setAccessible(true);
                $reflection->invoke($controller, \App\Models\Order::find($orderId));
            } catch (\Throwable $e) {
                Log::warning('Order emails deferred failed: ' . $e->getMessage());
            }
        });

        return redirect()->route('payment.checkout', $order->id)
            ->with('success', 'Order created. Please proceed to payment.');
    }

    /**
     * Send order-created emails: confirmation to customer + alert to admin.
     */
    protected function sendOrderCreatedEmails($order): void
    {
        try {
            $order->load(['user', 'event', 'items']);
            $user = $order->user;
            if (!$user || !$user->email) return;

            $mail = app(\App\Services\MailService::class);

            // Build items array for template
            $items = [];
            foreach ($order->items as $item) {
                $items[] = [
                    'name'     => $item->photo_id ? ('ภาพ #' . $item->photo_id) : 'ภาพถ่าย',
                    'quantity' => 1,
                    'price'    => (float) $item->price,
                    'event_name' => $order->event?->title,
                ];
            }

            // Customer order confirmation
            $mail->orderConfirmation([
                'id'           => $order->id,
                'order_number' => $order->order_number,
                'email'        => $user->email,
                'name'         => $user->first_name ?? $user->name ?? 'ลูกค้า',
                'total_amount' => (float) $order->total,
                'created_at'   => $order->created_at?->format('d/m/Y H:i'),
                'payment_url'  => url('/payment/checkout/' . $order->id),
            ], $items);

            // Admin alert
            $adminEmail = \App\Models\AppSetting::get('admin_notification_email', \App\Models\AppSetting::get('mail_from_email'));
            if ($adminEmail) {
                $mail->adminNewOrderAlert($adminEmail, [
                    'id'             => $order->id,
                    'order_number'   => $order->order_number,
                    'total'          => (float) $order->total,
                    'customer_name'  => $user->first_name ?? 'N/A',
                    'customer_email' => $user->email,
                    'customer_phone' => $user->phone ?? 'N/A',
                    'payment_method' => 'รอเลือก',
                    'status_label'   => 'รอการชำระเงิน',
                    'order_date'     => $order->created_at?->format('d/m/Y H:i'),
                    'admin_url'      => url('/admin/orders/' . $order->id),
                ], $items);
            }
        } catch (\Throwable $e) {
            Log::warning('Order email failed: ' . $e->getMessage());
        }
    }

    /**
     * Express checkout — accept items directly, create order & redirect to payment.
     * Skips the cart page entirely for maximum speed.
     */
    public function expressCheckout(Request $request)
    {
        $request->validate([
            'items'           => 'required|array|min:1',
            'items.*.file_id' => 'required|string',
            'items.*.name'    => 'required|string|max:255',
            'package_id'      => 'nullable|integer',
            'delivery_method' => 'nullable|string|in:web,line,email,auto',
            'coupon_code'     => 'nullable|string|max:64',
            'referral_code'   => 'nullable|string|max:64',
        ]);

        $items          = $request->items;
        $packageId      = $request->input('package_id');
        $deliveryMethod = $this->sanitizeDeliveryMethod($request->input('delivery_method'));
        $couponCode     = trim((string) $request->input('coupon_code', ''));
        $referralCode   = trim((string) $request->input('referral_code', session('referral_code', '')));

        $order = DB::transaction(function () use ($items, $packageId, $deliveryMethod, $couponCode, $referralCode) {
            // Determine event_id from submitted items
            $eventId = null;
            foreach ($items as $item) {
                if (!empty($item['event_id'])) {
                    $eventId = (int) $item['event_id'];
                    break;
                }
            }

            // Server-side price lookup — never trust client-submitted prices
            $serverPrice = $eventId ? $this->getServerPrice($eventId) : 0.0;
            $subtotal = count($items) * $serverPrice;

            // Apply package pricing if selected
            //
            // Three bundle shapes — pick the right pricing path:
            //   • count       — fixed N photos for ฿X (existing logic)
            //   • face_match  — variable N, discount_pct off the per-photo
            //                   total, capped at max_price. Used by the
            //                   "เหมารูปตัวเอง" flow on /events/{id}/face-search
            //                   so the buyer pays the bundle rate even though
            //                   each line carries the discounted per-photo
            //                   amount client-side. We RECOMPUTE the price
            //                   server-side using BundleService so the JS
            //                   can't lie about the discount.
            //   • event_all   — flat fee (existing fallback path applies)
            $appliedPackageId = null;
            if ($packageId) {
                $package = PricingPackage::where('id', $packageId)->where('is_active', true)->first();
                if ($package) {
                    if ($package->bundle_type === PricingPackage::TYPE_FACE_MATCH && $eventId) {
                        $event = \App\Models\Event::find($eventId);
                        if ($event) {
                            $quote = app(\App\Services\Pricing\BundleService::class)
                                ->calculateFaceBundle($event, count($items), $package);
                            if ($quote && isset($quote['price'])) {
                                $subtotal         = (float) $quote['price'];
                                $appliedPackageId = $package->id;
                            }
                        }
                    } elseif ((int) $package->photo_count > 0
                           && count($items) <= (int) $package->photo_count) {
                        // count / event_all bundle (existing behaviour)
                        $subtotal         = (float) $package->price;
                        $appliedPackageId = $package->id;
                    }
                }
            }

            // Apply coupon + referral discounts on top of $subtotal
            $discount = $this->applyDiscounts($subtotal, $couponCode, $referralCode, Auth::id());
            $total = max(0.0, round($subtotal - $discount['amount'], 2));

            $orderNumber = 'ORD-' . strtoupper(Str::random(12));

            $order = Order::create([
                'user_id'          => Auth::id(),
                'event_id'         => $eventId,
                'package_id'       => $appliedPackageId,
                'order_number'     => $orderNumber,
                'subtotal'         => $subtotal,
                'discount_amount'  => $discount['amount'],
                'total'            => $total,
                'coupon_id'        => $discount['coupon_id'],
                'coupon_code'      => $discount['coupon_code'],
                'referral_code_id' => $discount['referral_code_id'],
                'status'           => 'pending_payment',
                'delivery_method'  => $deliveryMethod,
                'delivery_status'  => 'pending',
            ]);

            // When a package is applied, OrderItems share the bundle price
            // equally so the per-row sum equals order.subtotal. Otherwise
            // each photo carries the per-photo server price.
            $perItemPrice = $appliedPackageId
                ? round($subtotal / max(1, count($items)), 2)
                : $serverPrice;
            foreach ($items as $item) {
                OrderItem::create([
                    'order_id'      => $order->id,
                    'event_id'      => !empty($item['event_id']) ? (int) $item['event_id'] : null,
                    'photo_id'      => $item['file_id'] ?? '',
                    'thumbnail_url' => $item['thumbnail'] ?? null,
                    'price'         => $perItemPrice,
                ]);
            }

            // Track coupon usage + referral redemption now that order exists
            $this->recordDiscountUsage($order, $discount);

            // Clear cart to avoid stale items
            app(CartService::class)->clear();

            return $order;
        });

        // Clear referral session so it doesn't apply to future unrelated orders
        session()->forget('referral_code');

        // Mark abandoned cart as recovered + timeline log
        try {
            app(\App\Services\AbandonedCartService::class)->markRecovered(
                Auth::id(), session()->getId(), $order->id
            );
        } catch (\Throwable $e) {}

        try {
            app(\App\Services\OrderTimelineService::class)->log(
                $order, 'pending_payment',
                'คำสั่งซื้อ (Express) ถูกสร้าง รอการชำระเงิน',
                'user', null, Auth::id()
            );
        } catch (\Throwable $e) {}

        // Notify admin
        // NB: AdminNotification::newOrder is no longer called here — the
        // bell-icon notification is now fired by AdminNotificationObserver
        // on model::created, so calling it directly produced duplicates.
        // We keep the LINE side-effect because it's a separate channel
        // (push to admin's LINE account, not the bell).
        try {
            app(\App\Services\LineNotifyService::class)->notifyNewOrder([
                'order_number' => $order->order_number,
                'total_amount' => $order->total,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Order notification failed: ' . $e->getMessage());
        }

        // Notify customer
        try {
            if (Schema::hasTable('user_notifications')) {
                UserNotification::create([
                    'user_id'    => Auth::id(),
                    'type'       => 'order',
                    'title'      => 'สร้างคำสั่งซื้อสำเร็จ',
                    'message'    => "คำสั่งซื้อ {$order->order_number} ยอด ฿" . number_format($order->total, 0) . " รอการชำระเงิน",
                    'is_read'    => false,
                    'action_url' => "payment/checkout/{$order->id}",
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('User notification failed: ' . $e->getMessage());
        }

        // Send email notifications
        $this->sendOrderCreatedEmails($order);

        return response()->json([
            'success'  => true,
            'order_id' => $order->id,
            'redirect' => route('payment.checkout', $order->id),
        ]);
    }

    /**
     * Download invoice PDF.
     */
    public function invoice($id, InvoiceService $invoiceService)
    {
        $order = Order::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['items', 'user', 'event'])
            ->firstOrFail();

        $invoiceNo = 'INV-' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
        $pdf = $invoiceService->generatePdf($order);

        return $pdf->download("{$invoiceNo}.pdf");
    }

    /**
     * Send invoice to customer email.
     */
    public function sendInvoice($id, InvoiceService $invoiceService)
    {
        $order = Order::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['items', 'user', 'event'])
            ->firstOrFail();

        $sent = $invoiceService->sendEmail($order);

        return back()->with(
            $sent ? 'success' : 'error',
            $sent ? 'ส่งใบเสร็จไปทางอีเมลเรียบร้อยแล้ว' : 'ไม่สามารถส่งอีเมลได้ กรุณาลองใหม่'
        );
    }

    /**
     * Normalize the delivery_method submitted from checkout. Falls back to the
     * admin-configured default when the value is missing or not on the allow
     * list (defends against old form submissions + tampering).
     */
    private function sanitizeDeliveryMethod($raw): string
    {
        $allowed = ['web', 'line', 'email', 'auto'];
        $value   = is_string($raw) ? strtolower(trim($raw)) : '';

        if (in_array($value, $allowed, true)) {
            return $value;
        }

        // Fallback: admin default, or 'auto' if no config.
        $default = \App\Models\AppSetting::get('delivery_default_method', 'auto');
        $default = is_string($default) ? strtolower(trim($default)) : 'auto';

        return in_array($default, $allowed, true) ? $default : 'auto';
    }

    /**
     * Look up the authoritative price per photo for a given event.
     * Delegates to {@see EventPriceResolver} so the cart UI and the order
     * charge share the same lookup logic (single source of truth).
     */
    private function getServerPrice(int $eventId): float
    {
        return app(EventPriceResolver::class)->perPhoto($eventId);
    }

    /**
     * Resolve coupon + referral discounts against $subtotal.
     *
     * Returns a uniform shape used by both store() and expressCheckout():
     *   - amount           : combined discount THB (rounded)
     *   - coupon_id        : ?int  (the validated, applied coupon)
     *   - coupon_code      : ?string (denormalised for display)
     *   - coupon           : ?Coupon model (for usage tracking)
     *   - referral_code_id : ?int (the applied referral code row id)
     *   - referral         : ?ReferralCode model (for redemption tracking)
     *
     * Coupon and referral are applied INDEPENDENTLY on the subtotal — they
     * do NOT stack on each other (i.e. referral isn't applied to the
     * post-coupon amount). This keeps numbers easy for the customer to
     * reason about and matches what most Thai e-commerce sites do.
     *
     * Server-side validation here is the source of truth — UI quotes are
     * informational only.
     */
    private function applyDiscounts(float $subtotal, string $couponCode, string $referralCode, ?int $userId): array
    {
        $result = [
            'amount'           => 0.0,
            'coupon_id'        => null,
            'coupon_code'      => null,
            'coupon'           => null,
            'coupon_discount'  => 0.0,
            'referral_code_id' => null,
            'referral'         => null,
            'referral_discount' => 0.0,
        ];

        // ── Coupon ──────────────────────────────────────────
        if ($couponCode !== '') {
            try {
                $coupon = Coupon::where('code', strtoupper($couponCode))->first();
                if ($coupon && $coupon->isValid()) {
                    // Per-user limit check (if configured)
                    $allow = true;
                    if ($coupon->per_user_limit && $userId) {
                        $usedByUser = CouponUsage::where('coupon_id', $coupon->id)
                            ->where('user_id', $userId)
                            ->count();
                        if ($usedByUser >= (int) $coupon->per_user_limit) {
                            $allow = false;
                        }
                    }
                    if ($allow) {
                        $cd = (float) $coupon->calculateDiscount($subtotal);
                        if ($cd > 0) {
                            $result['coupon_discount'] = $cd;
                            $result['coupon_id']       = $coupon->id;
                            $result['coupon_code']     = $coupon->code;
                            $result['coupon']          = $coupon;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('coupon.apply_failed', ['code' => $couponCode, 'err' => $e->getMessage()]);
            }
        }

        // ── Referral ────────────────────────────────────────
        if ($referralCode !== '') {
            try {
                $svc = app(ReferralService::class);
                $apply = $svc->apply($referralCode, $subtotal, $userId);
                if (!empty($apply['ok']) && !empty($apply['code'])) {
                    $rd = (float) $apply['discount'];
                    if ($rd > 0) {
                        $result['referral_discount'] = $rd;
                        $result['referral_code_id']  = $apply['code']->id;
                        $result['referral']          = $apply['code'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('referral.apply_failed', ['code' => $referralCode, 'err' => $e->getMessage()]);
            }
        }

        // Combined amount, capped at subtotal so total never goes negative.
        $combined = $result['coupon_discount'] + $result['referral_discount'];
        $result['amount'] = round(min($combined, $subtotal), 2);

        return $result;
    }

    /**
     * After the Order row is created, persist the coupon usage row and
     * record the referral redemption so admin reports + reward listeners
     * have something to query.
     */
    private function recordDiscountUsage(Order $order, array $discount): void
    {
        // Coupon: bump usage_count atomically + log per-user usage row.
        if (!empty($discount['coupon']) && $discount['coupon_discount'] > 0) {
            try {
                $coupon = $discount['coupon'];
                CouponUsage::create([
                    'coupon_id'       => $coupon->id,
                    'user_id'         => Auth::id(),
                    'order_id'        => $order->id,
                    'discount_amount' => $discount['coupon_discount'],
                    'used_at'         => now(),
                ]);
                Coupon::where('id', $coupon->id)->increment('usage_count');
            } catch (\Throwable $e) {
                Log::warning('coupon.usage_failed', ['order' => $order->id, 'err' => $e->getMessage()]);
            }
        }

        // Referral: create redemption row (status=pending) — reward grant
        // happens later via the Order::paid event listener.
        if (!empty($discount['referral']) && $discount['referral_discount'] > 0) {
            try {
                app(ReferralService::class)->recordRedemption(
                    $discount['referral'],
                    $order->id,
                    Auth::id(),
                    $discount['referral_discount']
                );
            } catch (\Throwable $e) {
                Log::warning('referral.record_failed', ['order' => $order->id, 'err' => $e->getMessage()]);
            }
        }
    }

    /**
     * Download all order photos as ZIP.
     *
     * Reads each photo's bytes from whichever disk it lives on
     * (`event_photos.storage_disk` — typically `r2` in production, `public`
     * locally) via StorageManager. The previous implementation only looked
     * at the local filesystem (`storage/app/public/...`), so any order
     * containing R2-backed photos returned 0 files and surfaced
     * "ไม่พบไฟล์รูปภาพสำหรับดาวน์โหลด" — exactly the bug the buyer hit
     * after a face_match bundle purchase, since those photos are stored on
     * R2 alongside every other event photo.
     *
     * Falls back through original → watermarked when the original is
     * missing (e.g. if originals were purged for retention) so buyers
     * always get SOMETHING rather than an empty ZIP. Watermarked variant
     * is still gated by `paid` status above so this isn't a leak.
     */
    public function downloadZip($id)
    {
        $order = Order::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['items', 'event'])
            ->firstOrFail();

        if ($order->status !== 'paid') {
            return back()->with('error', 'กรุณาชำระเงินก่อนดาวน์โหลด');
        }

        $zipName = 'order-' . $order->id . '-photos.zip';
        $zipPath = storage_path('app/temp/' . $zipName);

        // Ensure temp directory exists
        if (!is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'ไม่สามารถสร้างไฟล์ ZIP ได้');
        }

        $storage   = app(\App\Services\StorageManager::class);
        $fileCount = 0;
        // Track failures separately so a single dead photo doesn't poison the
        // whole ZIP — the buyer still gets the ones that worked.
        $failed = [];

        // Pre-load every event_photo row referenced by the order in one
        // query so we don't N+1 on a large order.
        $photoIds = $order->items->pluck('photo_id')
            ->filter()
            ->map(fn ($v) => is_numeric($v) ? (int) $v : $v)
            ->filter(fn ($v) => is_int($v))
            ->all();
        $photos = !empty($photoIds)
            ? \App\Models\EventPhoto::whereIn('id', $photoIds)->get()->keyBy('id')
            : collect();

        $usedNames = []; // dedupe duplicate filenames inside the ZIP
        foreach ($order->items as $item) {
            $photoId = is_numeric($item->photo_id) ? (int) $item->photo_id : null;
            $photo   = $photoId ? $photos->get($photoId) : null;
            if (!$photo) {
                $failed[] = "photo_id={$item->photo_id} (no event_photos row)";
                continue;
            }

            $disk = (string) ($photo->storage_disk ?: 'public');
            $bytes = null;

            // 1) Original — best quality, what the buyer paid for.
            if (!empty($photo->original_path)) {
                $bytes = $storage->readFromDriver($disk, $photo->original_path);
            }
            // 2) Mirror disks (R2/S3 split) — try each in turn.
            if ($bytes === null && !empty($photo->original_path)) {
                $mirrors = is_array($photo->storage_mirrors) ? $photo->storage_mirrors : [];
                foreach ($mirrors as $mirrorDisk) {
                    if ($mirrorDisk === $disk) continue;
                    $bytes = $storage->readFromDriver($mirrorDisk, $photo->original_path);
                    if ($bytes !== null) break;
                }
            }
            // 3) Watermarked fallback — better than failing the ZIP entirely
            //    if originals were purged. Buyer is paid so this still
            //    respects the licensing model.
            if ($bytes === null && !empty($photo->watermarked_path)) {
                $bytes = $storage->readFromDriver($disk, $photo->watermarked_path);
            }

            if ($bytes === null || $bytes === '') {
                $failed[] = "id={$photo->id} disk={$disk} path=" . ($photo->original_path ?? '?');
                continue;
            }

            // Build a friendly name. Prefer original_filename ("DSC_0042.jpg")
            // then fall back to a numbered slot. Dedupe by appending (2),
            // (3), … so ZipArchive doesn't silently overwrite duplicates.
            $base = $photo->original_filename ?: ('photo_' . ($fileCount + 1) . '.jpg');
            $base = preg_replace('/[\\/\?\*:|<>"]/', '_', $base) ?: 'photo.jpg';
            $name = $base;
            $i = 2;
            while (isset($usedNames[$name])) {
                $info = pathinfo($base);
                $name = ($info['filename'] ?? 'photo') . " ({$i})." . ($info['extension'] ?? 'jpg');
                $i++;
            }
            $usedNames[$name] = true;

            $zip->addFromString($name, $bytes);
            $fileCount++;
        }

        $zip->close();

        if ($fileCount === 0) {
            @unlink($zipPath);
            \Illuminate\Support\Facades\Log::warning('downloadZip: no files added', [
                'order_id'    => $order->id,
                'item_count'  => $order->items->count(),
                'photo_count' => $photos->count(),
                'failed'      => $failed,
            ]);
            return back()->with('error', 'ไม่พบไฟล์รูปภาพสำหรับดาวน์โหลด — กรุณาติดต่อผู้ดูแลระบบ');
        }

        if (!empty($failed)) {
            \Illuminate\Support\Facades\Log::info('downloadZip: partial success', [
                'order_id'  => $order->id,
                'succeeded' => $fileCount,
                'failed'    => $failed,
            ]);
        }

        return response()->download($zipPath, $zipName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }
}

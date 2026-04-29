<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\PhotographerPayout;
use App\Models\PhotographerProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * OrderFulfillmentService
 * ───────────────────────
 * Single entry point for "the order just flipped to paid, now what?"
 *
 * Historically every gateway webhook + every slip-approve path had to
 * remember to:
 *   • call CreditService for credit_package orders
 *   • call SubscriptionService for subscription orders
 *   • call PhotoDeliveryService for photo_package orders
 *
 * That's 4+ places with copy-pasted if/elseif trees, and it's how bugs
 * sneak in (e.g. "we added subscriptions but forgot to hook it into the
 * admin bulk-approve path"). This service owns the dispatch so every
 * caller just does `OrderFulfillmentService::fulfill($order)`.
 *
 * All downstream handlers are idempotent — running `fulfill()` twice on
 * the same order is safe.
 */
class OrderFulfillmentService
{
    public function __construct(
        private CreditService $credits,
        private SubscriptionService $subscriptions,
        private UserStorageService $userStorage,
        private PhotoDeliveryService $delivery,
        private GiftCardService $giftCards,
    ) {}

    /**
     * Dispatch a just-paid order to its post-payment handler.
     * Swallows exceptions per-branch so one failure (e.g. email dispatch)
     * never blocks the other payment bookkeeping. Logs what happened to
     * payment_logs for admin visibility.
     */
    public function fulfill(Order $order): void
    {
        try {
            if ($order->isCreditPackageOrder()) {
                $this->credits->issueFromPaidOrder($order);
                $this->logPaymentEvent($order->id, 'credits_issued', 'CreditService::issueFromPaidOrder invoked');
                return;
            }

            if ($order->isSubscriptionOrder()) {
                $sub = $this->subscriptions->activateFromPaidInvoice($order);
                $this->logPaymentEvent(
                    $order->id,
                    'subscription_activated',
                    'SubscriptionService::activateFromPaidInvoice invoked'
                        . ($sub ? " (sub_id={$sub->id}, plan={$sub->plan?->code})" : '')
                );
                return;
            }

            if ($order->isUserStorageOrder()) {
                $sub = $this->userStorage->activateFromPaidInvoice($order);
                $this->logPaymentEvent(
                    $order->id,
                    'user_storage_activated',
                    'UserStorageService::activateFromPaidInvoice invoked'
                        . ($sub ? " (sub_id={$sub->id}, plan={$sub->plan?->code})" : '')
                );
                return;
            }

            // Photographer addon (storage top-up, AI credits pack, promotion
            // boost, branding/priority flag). The Order carries
            // addon_purchase_id; AddonService::activate() is idempotent
            // so webhook retries don't double-credit storage / promotions.
            if ($order->isAddonOrder()) {
                $purchaseId = (int) ($order->addon_purchase_id ?? 0);
                if ($purchaseId > 0) {
                    $ok = app(\App\Services\Monetization\AddonService::class)
                        ->activate($purchaseId);
                    $this->logPaymentEvent(
                        $order->id,
                        'addon_activated',
                        $ok
                            ? "AddonService::activate(purchase_id={$purchaseId}) succeeded"
                            : "AddonService::activate(purchase_id={$purchaseId}) FAILED — see addon.activate.failed log",
                    );
                } else {
                    $this->logPaymentEvent(
                        $order->id,
                        'addon_activation_skipped',
                        'addon order paid but no addon_purchase_id linked — orphan order'
                    );
                }
                return;
            }

            if ($order->isGiftCardOrder()) {
                // Activate the pending gift card minted at purchase time.
                // Idempotent — re-running on a webhook retry is a no-op.
                // Without this branch the user paid but the card stayed
                // 'pending' forever (the previous bug was the inverse:
                // the card was active without payment).
                $gc = $this->giftCards->activateFromPaidOrder($order->id);
                $this->logPaymentEvent(
                    $order->id,
                    'gift_card_activated',
                    'GiftCardService::activateFromPaidOrder invoked'
                        . ($gc ? " (gift_card_id={$gc->id}, code={$gc->code}, status={$gc->status})" : ' (no card found)')
                );
                // Email the recipient (or purchaser) the activated code.
                try {
                    $this->sendGiftCardEmail($gc);
                } catch (\Throwable $e) {
                    Log::warning('Gift card email failed: '.$e->getMessage());
                }
                return;
            }

            // Default: photo package → deliver photos via preferred channel.
            $this->delivery->deliver($order->fresh());

            // Record the photographer's earnings split. This MUST run on
            // every paid photo-package order — webhooks (Omise, Stripe,
            // PromptPay), admin slip approval, and admin manual fulfill
            // all funnel through here. Without this row the photographer
            // never sees the order in their earnings dashboard and the
            // disbursement cron has nothing to settle.
            $this->createPhotographerPayout($order);
        } catch (\Throwable $e) {
            Log::warning('OrderFulfillmentService::fulfill failed', [
                'order_id'   => $order->id,
                'order_type' => $order->order_type,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create one PhotographerPayout row PER unique photographer in the
     * order. Single-event orders → 1 payout row. Multi-event orders
     * (cart with photos from different photographers) → N rows, each
     * sized to that photographer's portion of the order.
     *
     * Idempotent: if any payout row exists for this order we skip the
     * whole pass — webhook retries don't double-create.
     *
     * Discount allocation:
     *   The order's `discount_amount` is allocated proportionally to
     *   each photographer's gross_amount. e.g. Photographer A's items
     *   total ฿200 + Photographer B's ฿100 + ฿30 coupon → A bears
     *   ฿20 of the discount (A is 2/3 of pre-discount total),
     *   B bears ฿10. Each photographer's split runs against their
     *   AFTER-discount gross.
     *
     * Commission split (per photographer):
     *   - Read profile.commission_rate (their KEEP %, kept in sync
     *     with plan.commission_pct by SubscriptionService::syncProfileCache).
     *   - Fall back to (100 - AppSetting('platform_commission', 20))
     *     for legacy photographers without a profile.
     */
    private function createPhotographerPayout(Order $order): void
    {
        try {
            if (!Schema::hasTable('photographer_payouts')) {
                return;
            }
            if (DB::table('photographer_payouts')->where('order_id', $order->id)->exists()) {
                return;
            }

            $total = (float) $order->total;
            if ($total <= 0) return;

            // Group items by event_id, then resolve each event's photographer.
            // Falls back to single-event behaviour when items don't have
            // event_id (legacy orders before the order_items.event_id column).
            $items = $order->items;
            $byPhotographer = []; // [photographer_id => ['gross_pre_discount' => float, 'event_ids' => [...] ]]

            $totalPreDiscount = 0.0;
            foreach ($items as $item) {
                $eventId = $item->event_id ?? $order->event_id;
                if (!$eventId) continue;
                $event = \App\Models\Event::find($eventId);
                if (!$event || !$event->photographer_id) continue;

                $pid = (int) $event->photographer_id;
                $byPhotographer[$pid] ??= ['gross_pre_discount' => 0.0, 'event_ids' => []];
                $byPhotographer[$pid]['gross_pre_discount'] += (float) $item->price;
                $byPhotographer[$pid]['event_ids'][$eventId] = true;
                $totalPreDiscount += (float) $item->price;
            }

            // Legacy fallback: if items have no event_id at all, use order.event_id
            if (empty($byPhotographer) && $order->event_id) {
                $event = \App\Models\Event::find($order->event_id);
                if ($event && $event->photographer_id) {
                    $byPhotographer[$event->photographer_id] = [
                        'gross_pre_discount' => $total,  // assume order.total is pre-discount in this branch
                        'event_ids' => [$order->event_id => true],
                    ];
                    $totalPreDiscount = $total;
                }
            }

            if (empty($byPhotographer) || $totalPreDiscount <= 0) {
                return;
            }

            // Compute discount allocation factor — what each ฿ of pre-discount
            // gross becomes after the coupon. E.g. pre=฿300, total=฿270 → factor=0.9
            $factor = $total / $totalPreDiscount;

            foreach ($byPhotographer as $photographerId => $info) {
                // This photographer's share of the FINAL paid amount
                $photographerGross = round($info['gross_pre_discount'] * $factor, 2);
                if ($photographerGross <= 0) continue;

                // Tier-aware commission resolution. Priority:
                //   1. Highest of (active tier rate matched by lifetime
                //      revenue, profile.commission_rate VIP override).
                //   2. Profile rate alone if no tier system configured.
                //   3. Global default = 100 - platform_commission setting.
                // Replaces the old "always read profile.commission_rate"
                // path so a photographer who's earned past a tier threshold
                // automatically gets the better rate without admin
                // touching their profile.
                $keepRate = app(\App\Services\Payout\CommissionResolver::class)
                    ->resolveKeepRate((int) $photographerId);

                $platformRate = max(0, 100 - $keepRate);
                $platformFee  = round($photographerGross * $platformRate / 100, 2);
                $photographerAmount = round($photographerGross - $platformFee, 2);

                PhotographerPayout::create([
                    'photographer_id' => $photographerId,
                    'order_id'        => $order->id,
                    'gross_amount'    => $photographerGross,
                    'commission_rate' => $keepRate,
                    'payout_amount'   => $photographerAmount,
                    'platform_fee'    => $platformFee,
                    'status'          => 'pending',
                ]);

                $this->logPaymentEvent($order->id, 'payout_created',
                    "PhotographerPayout #{$photographerId}: gross={$photographerGross} keep={$photographerAmount} platform={$platformFee} ({$platformRate}%)"
                );

                // Post a double-entry journal entry for this earnings split.
                // Best-effort: failure here does NOT block the legacy payout
                // row creation. The reconciliation cron (finance:reconcile)
                // catches drift between the legacy table and the ledger.
                $this->postLedgerEntry(
                    order:              $order,
                    photographerUserId: (int) $photographerId,
                    photographerGross:  $photographerGross,
                    platformFee:        $platformFee,
                );

                // Notify the photographer that they earned a sale.
                try {
                    \App\Models\UserNotification::newSale($photographerId, $photographerAmount, $order);
                } catch (\Throwable $e) {
                    Log::warning('newSale notification failed', [
                        'photographer_id' => $photographerId,
                        'order_id'        => $order->id,
                        'error'           => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('OrderFulfillmentService::createPhotographerPayout failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Shadow the legacy payout row with a double-entry journal entry.
     *
     * Why a "shadow"? The legacy `photographer_payouts` table is what
     * the existing dashboards / payout cron already read. The ledger
     * entry duplicates the same money flow into App\Finance — it's
     * additive, not replacing. The reconciliation command
     * (`finance:reconcile`) screams when the two ever drift, which is
     * the safe migration path: existing flow keeps working, the ledger
     * accumulates the same data, and we cut over only after weeks of
     * green reconciliation runs.
     *
     * Failure modes are logged + swallowed — a ledger glitch must
     * NEVER block a real payout from landing.
     */
    private function postLedgerEntry(
        Order $order,
        int $photographerUserId,
        float $photographerGross,
        float $platformFee,
    ): void {
        try {
            // The migration set may not have run yet on this environment
            // (Postgres deploy still pending). Bail silently on missing
            // tables instead of poisoning every paid-order path.
            if (!Schema::hasTable('financial_journal_entries')
                || !Schema::hasTable('financial_accounts')) {
                return;
            }

            // Convert decimals to integer satang. The ledger expresses
            // everything in minor units to avoid float drift.
            $grossSatang = (int) round($photographerGross * 100);
            if ($grossSatang <= 0) return;

            // Per-plan VAT rate — keep at 0 until the platform is
            // VAT-registered (no VAT collected → no VAT line posted).
            $vatBps = (int) (\App\Models\AppSetting::get('finance_vat_bps', '0') ?: 0);
            $platformFeeBps = $photographerGross > 0
                ? (int) round(($platformFee / $photographerGross) * 10_000)
                : 0;

            $useCase = app(\App\Finance\UseCases\RecordOrderPaid::class);
            $useCase->record(
                orderId:             (string) $order->id,
                grossPaid:           \App\Finance\Money::thb($grossSatang),
                platformFeeBps:      $platformFeeBps,
                vatBps:              $vatBps,
                photographerUserId:  $photographerUserId,
                gatewayCode:         \App\Finance\ChartOfAccounts::OMISE_RECEIVABLE, // generic; refined per-gateway later
                metadata:            [
                    'order_number'   => $order->order_number,
                    'photographer'   => $photographerUserId,
                    'order_total'    => (float) $order->total,
                    'gross_share'    => $photographerGross,
                    'platform_fee'   => $platformFee,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('Ledger shadow-post failed (legacy payout still recorded)', [
                'order_id'        => $order->id,
                'photographer_id' => $photographerUserId,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    private function logPaymentEvent(int $orderId, string $event, string $note): void
    {
        try {
            DB::table('payment_logs')->insert([
                'order_id'   => $orderId,
                'event_type' => $event,
                'note'       => $note,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::debug('OrderFulfillmentService: payment_logs insert failed: ' . $e->getMessage());
        }
    }

    /**
     * Email the activated gift card code to the recipient (falls back to
     * the purchaser if no recipient email is on file). Uses MailService
     * if available, otherwise falls back to Laravel Mail with a simple
     * inline body so the buyer always gets the code.
     *
     * Best-effort: failures are logged at the call site, never block
     * fulfillment.
     */
    private function sendGiftCardEmail(?\App\Models\GiftCard $gc): void
    {
        if (!$gc || $gc->status !== 'active') return;

        $to = $gc->recipient_email ?: $gc->purchaser_email;
        if (!$to) return;

        try {
            $mail = app(\App\Services\MailService::class);
            // Use generic notification helper if a dedicated method
            // doesn't exist — falls back to plain Mail::raw at worst.
            if (method_exists($mail, 'giftCardCode')) {
                $mail->giftCardCode($to, $gc);
                return;
            }
        } catch (\Throwable $e) {
            // Service not available — fall through to raw mail.
        }

        try {
            $code = $gc->code;
            $bal = number_format((float) $gc->balance, 0);
            $expires = $gc->expires_at?->format('d M Y') ?? 'ไม่มีกำหนด';
            $msg = $gc->personal_message ? "\n\nข้อความจากผู้ซื้อ:\n{$gc->personal_message}" : '';
            \Illuminate\Support\Facades\Mail::raw(
                "🎁 บัตรของขวัญพร้อมใช้งานแล้ว\n\n"
                . "รหัส: {$code}\nยอด: ฿{$bal}\nหมดอายุ: {$expires}\n"
                . "ใช้ที่หน้าตะกร้าตอนชำระเงิน — ระบบจะหักจากยอดบัตรอัตโนมัติ"
                . $msg,
                fn ($m) => $m->to($to)->subject("บัตรของขวัญของคุณ {$code}")
            );
        } catch (\Throwable $e) {
            Log::warning('Gift card raw mail failed: '.$e->getMessage());
        }
    }
}

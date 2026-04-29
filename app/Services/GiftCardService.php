<?php

namespace App\Services;

use App\Models\GiftCard;
use App\Models\GiftCardTransaction;
use Illuminate\Support\Facades\DB;

class GiftCardService
{
    /**
     * Issue a brand-new gift card.
     *
     * Status semantics:
     *   - admin/promo/refund sources → 'active' immediately (no payment needed)
     *   - purchase source → 'pending' until payment confirmed via
     *     activateFromPaidOrder(). Caller can override with
     *     `data['status']` if needed.
     *
     * SECURITY: Previously this method always created cards as 'active',
     * which let any logged-in user POST to /gift-cards and instantly
     * receive a usable balance up to 50,000 THB without paying. Now
     * `purchase`-source cards are minted in 'pending' state with zero
     * balance display (isRedeemable returns false until activated).
     *
     * Records the initial "issue" transaction either way.
     */
    public function issue(array $data): GiftCard
    {
        return DB::transaction(function () use ($data) {
            $amount = (float) ($data['amount'] ?? 0);
            if ($amount <= 0) {
                throw new \InvalidArgumentException('Gift card amount must be greater than zero');
            }

            $source = $data['source'] ?? 'admin';
            // Default status: 'pending' for purchase flows (require
            // payment), 'active' for admin/promo/refund grants.
            $defaultStatus = $source === 'purchase' ? 'pending' : 'active';
            $status = $data['status'] ?? $defaultStatus;

            $gc = GiftCard::create([
                'code'                => $data['code'] ?? GiftCard::generateCode(),
                'initial_amount'      => $amount,
                'balance'             => $amount,
                'currency'            => $data['currency'] ?? 'THB',
                'purchaser_user_id'   => $data['purchaser_user_id'] ?? null,
                'purchaser_email'    => $data['purchaser_email'] ?? null,
                'purchaser_name'      => $data['purchaser_name'] ?? null,
                'recipient_name'      => $data['recipient_name'] ?? null,
                'recipient_email'     => $data['recipient_email'] ?? null,
                'personal_message'    => $data['personal_message'] ?? null,
                'source'              => $source,
                'source_order_id'     => $data['source_order_id'] ?? null,
                'status'              => $status,
                'expires_at'          => $data['expires_at'] ?? now()->addYear(),
                // activated_at is null for pending cards — set on activation.
                'activated_at'        => $status === 'active' ? now() : null,
                'issued_by_admin_id'  => $data['issued_by_admin_id'] ?? null,
                'admin_note'          => $data['admin_note'] ?? null,
            ]);

            GiftCardTransaction::create([
                'gift_card_id'  => $gc->id,
                'type'          => 'issue',
                'amount'        => $amount,
                'balance_after' => $amount,
                'admin_id'      => $data['issued_by_admin_id'] ?? null,
                'note'          => $data['admin_note'] ?? ($status === 'pending'
                    ? 'Issued (pending payment)'
                    : 'Initial issue'),
                'meta'          => ['source' => $gc->source, 'status_at_issue' => $status],
            ]);

            return $gc;
        });
    }

    /**
     * Activate a pending gift card after its order is paid.
     *
     * Idempotent: re-running on an already-active card is a no-op.
     * Called from OrderFulfillmentService::fulfill() when the linked
     * Order flips to 'paid'. We look up the gift card by source_order_id
     * because the Order itself doesn't carry a direct foreign key.
     *
     * Returns the activated gift card, or null if no pending gift card
     * was found for the given order.
     */
    public function activateFromPaidOrder(int $orderId): ?GiftCard
    {
        return DB::transaction(function () use ($orderId) {
            $gc = GiftCard::where('source_order_id', $orderId)
                ->where('source', 'purchase')
                ->lockForUpdate()
                ->first();

            if (!$gc) return null;

            // Idempotent — already activated by an earlier webhook hit.
            if ($gc->status === 'active') return $gc;

            // Defensive: reject anything other than 'pending' (avoid
            // re-activating a voided/expired card).
            if ($gc->status !== 'pending') return $gc;

            $gc->status = 'active';
            $gc->activated_at = now();
            $gc->save();

            GiftCardTransaction::create([
                'gift_card_id'  => $gc->id,
                'type'          => 'activate',
                'amount'        => 0,
                'balance_after' => (float) $gc->balance,
                'note'          => "Activated by paid order #{$orderId}",
                'meta'          => ['order_id' => $orderId],
            ]);

            return $gc;
        });
    }

    /**
     * Deduct amount from balance when used against an order.
     * Returns the amount actually redeemed (clipped to available balance).
     */
    public function redeem(GiftCard $gc, float $requested, ?int $orderId = null, ?int $userId = null, ?string $note = null): float
    {
        if (!$gc->isRedeemable()) {
            throw new \DomainException('Gift card is not redeemable');
        }
        if ($requested <= 0) {
            throw new \InvalidArgumentException('Redeem amount must be positive');
        }

        return DB::transaction(function () use ($gc, $requested, $orderId, $userId, $note) {
            $gc->refresh();
            $amount = min($requested, (float) $gc->balance);
            $newBal = (float) $gc->balance - $amount;

            $gc->balance = $newBal;

            if ($userId && !$gc->redeemed_by_user_id) {
                $gc->redeemed_by_user_id = $userId;
            }
            if ($newBal <= 0.001) {
                $gc->status = 'redeemed';
            }
            $gc->save();

            GiftCardTransaction::create([
                'gift_card_id'  => $gc->id,
                'type'          => 'redeem',
                'amount'        => -$amount,
                'balance_after' => $newBal,
                'user_id'       => $userId,
                'order_id'      => $orderId,
                'note'          => $note ?? 'Order redemption',
            ]);

            return $amount;
        });
    }

    /**
     * Restore balance (e.g. when an order is refunded).
     */
    public function refund(GiftCard $gc, float $amount, ?int $orderId = null, ?int $adminId = null, ?string $note = null): void
    {
        DB::transaction(function () use ($gc, $amount, $orderId, $adminId, $note) {
            $gc->refresh();
            $newBal = (float) $gc->balance + $amount;
            // Cap at initial_amount so we never go over the original face value
            if ($newBal > (float) $gc->initial_amount) {
                $newBal = (float) $gc->initial_amount;
            }
            $gc->balance = $newBal;
            if ($gc->status === 'redeemed' && $newBal > 0) {
                $gc->status = 'active';
            }
            $gc->save();

            GiftCardTransaction::create([
                'gift_card_id'  => $gc->id,
                'type'          => 'refund',
                'amount'        => $amount,
                'balance_after' => $newBal,
                'order_id'      => $orderId,
                'admin_id'      => $adminId,
                'note'          => $note ?? 'Refund',
            ]);
        });
    }

    /**
     * Void a card outright (e.g. fraud, lost). Balance set to zero.
     */
    public function void(GiftCard $gc, ?int $adminId = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($gc, $adminId, $reason) {
            $prevBal = (float) $gc->balance;
            $gc->balance = 0;
            $gc->status  = 'voided';
            $gc->save();

            GiftCardTransaction::create([
                'gift_card_id'  => $gc->id,
                'type'          => 'void',
                'amount'        => -$prevBal,
                'balance_after' => 0,
                'admin_id'      => $adminId,
                'note'          => $reason ?? 'Voided by admin',
            ]);
        });
    }

    /**
     * Adjust balance up or down by $delta, for corrections.
     */
    public function adjust(GiftCard $gc, float $delta, ?int $adminId = null, ?string $note = null): void
    {
        DB::transaction(function () use ($gc, $delta, $adminId, $note) {
            $gc->refresh();
            $newBal = max(0, (float) $gc->balance + $delta);
            $gc->balance = $newBal;
            if ($newBal <= 0.001 && $gc->status === 'active') {
                $gc->status = 'redeemed';
            } elseif ($newBal > 0 && $gc->status === 'redeemed') {
                $gc->status = 'active';
            }
            $gc->save();

            GiftCardTransaction::create([
                'gift_card_id'  => $gc->id,
                'type'          => 'adjust',
                'amount'        => $delta,
                'balance_after' => $newBal,
                'admin_id'      => $adminId,
                'note'          => $note ?? 'Admin adjustment',
            ]);
        });
    }

    /**
     * Look up a redeemable gift card by code (case-insensitive, whitespace-trimmed).
     */
    public function lookup(string $code): ?GiftCard
    {
        $norm = strtoupper(trim($code));
        return GiftCard::where('code', $norm)->first();
    }

    /**
     * Nightly sweep: flag expired cards and zero-out their balance.
     * Returns count of cards transitioned.
     */
    public function expireDue(): int
    {
        $due = GiftCard::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $n = 0;
        foreach ($due as $gc) {
            DB::transaction(function () use ($gc) {
                $prev = (float) $gc->balance;
                $gc->status  = 'expired';
                $gc->balance = 0;
                $gc->save();

                GiftCardTransaction::create([
                    'gift_card_id'  => $gc->id,
                    'type'          => 'expire',
                    'amount'        => -$prev,
                    'balance_after' => 0,
                    'note'          => 'Auto-expired by scheduler',
                ]);
            });
            $n++;
        }
        return $n;
    }

    /**
     * KPI snapshot for admin dashboard.
     */
    public function kpis(): array
    {
        $liab = (float) GiftCard::where('status', 'active')->sum('balance');
        return [
            'total_cards'     => (int) GiftCard::count(),
            'active_cards'    => (int) GiftCard::where('status', 'active')->count(),
            'redeemed_cards'  => (int) GiftCard::where('status', 'redeemed')->count(),
            'expired_cards'   => (int) GiftCard::where('status', 'expired')->count(),
            'voided_cards'    => (int) GiftCard::where('status', 'voided')->count(),
            'liability_total' => $liab,
            'issued_total'    => (float) GiftCard::sum('initial_amount'),
            'redeemed_total'  => (float) GiftCard::sum(DB::raw('initial_amount - balance')),
        ];
    }
}

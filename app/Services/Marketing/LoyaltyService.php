<?php

namespace App\Services\Marketing;

use App\Models\AppSetting;
use App\Models\Marketing\LoyaltyAccount;
use App\Models\Marketing\LoyaltyTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Loyalty points ledger.
 *
 * Settings:
 *   marketing_loyalty_earn_rate          (points per 1 THB, default 1)
 *   marketing_loyalty_redeem_rate        (points per 1 THB discount, default 10)
 *   marketing_loyalty_min_redeem         (default 100)
 *   marketing_loyalty_tier_silver_spend  (3000)
 *   marketing_loyalty_tier_gold_spend    (15000)
 *   marketing_loyalty_tier_platinum_spend (50000)
 *
 * Points are stored as unsigned integers on the account; transactions table
 * holds signed values for full audit trail.
 */
class LoyaltyService
{
    public function __construct(protected MarketingService $marketing) {}

    public function enabled(): bool
    {
        return $this->marketing->enabled('loyalty');
    }

    // ── Account access ───────────────────────────────────────

    public function getOrCreate(int $userId): LoyaltyAccount
    {
        return LoyaltyAccount::firstOrCreate(
            ['user_id' => $userId],
            [
                'points_balance'        => 0,
                'points_earned_total'   => 0,
                'points_redeemed_total' => 0,
                'lifetime_spend'        => 0,
                'tier'                  => 'bronze',
            ]
        );
    }

    /**
     * Earn points from a purchase.
     * Typical usage: call after order is paid, with order_amount.
     */
    public function earnFromOrder(int $userId, float $orderAmount, int $orderId): ?LoyaltyTransaction
    {
        if (!$this->enabled()) return null;
        $rate = (float) AppSetting::get('marketing_loyalty_earn_rate', 1);
        $points = (int) floor($orderAmount * $rate);
        if ($points <= 0) return null;

        return $this->adjust($userId, $points, 'earn', 'order_purchase', $orderId, $orderAmount);
    }

    /**
     * Redeem points for discount. Returns the discount amount granted (THB),
     * or 0 if redemption fails.
     */
    public function redeem(int $userId, int $points, ?int $orderId = null): array
    {
        if (!$this->enabled()) {
            return ['ok' => false, 'discount' => 0, 'message' => 'Loyalty program ปิดอยู่'];
        }
        $minRedeem = (int) AppSetting::get('marketing_loyalty_min_redeem', 100);
        if ($points < $minRedeem) {
            return ['ok' => false, 'discount' => 0, 'message' => "ใช้ขั้นต่ำ {$minRedeem} points"];
        }
        $acc = $this->getOrCreate($userId);
        if ($acc->points_balance < $points) {
            return ['ok' => false, 'discount' => 0, 'message' => 'แต้มคงเหลือไม่พอ'];
        }

        $redeemRate = (float) AppSetting::get('marketing_loyalty_redeem_rate', 10);
        $discount = round($points / max($redeemRate, 1), 2);

        $tx = $this->adjust($userId, -$points, 'redeem', 'order_redeem', $orderId, $discount);
        return ['ok' => true, 'discount' => $discount, 'transaction' => $tx];
    }

    /**
     * Core ledger mutation — handles all point changes.
     * Positive points = credit; negative = debit.
     */
    public function adjust(int $userId, int $points, string $type, ?string $reason = null, ?int $orderId = null, ?float $relatedAmount = null): ?LoyaltyTransaction
    {
        if ($points === 0) return null;

        try {
            return DB::transaction(function () use ($userId, $points, $type, $reason, $orderId, $relatedAmount) {
                $acc = $this->getOrCreate($userId);

                // Update balances
                if ($points > 0) {
                    $acc->increment('points_balance', $points);
                    $acc->increment('points_earned_total', $points);
                } else {
                    $abs = abs($points);
                    $acc->decrement('points_balance', min($abs, $acc->points_balance));
                    if ($type === 'redeem') {
                        $acc->increment('points_redeemed_total', $abs);
                    }
                }

                // Lifetime spend update for tier calculation (only on earn-from-order)
                if ($type === 'earn' && $relatedAmount && $relatedAmount > 0) {
                    $acc->increment('lifetime_spend', $relatedAmount);
                }

                $tx = LoyaltyTransaction::create([
                    'account_id'     => $acc->id,
                    'user_id'        => $userId,
                    'type'           => $type,
                    'points'         => $points,
                    'related_amount' => $relatedAmount,
                    'reason'         => $reason,
                    'order_id'       => $orderId,
                ]);

                // Recalculate tier after mutation
                $this->recalculateTier($acc->fresh());

                return $tx;
            });
        } catch (\Throwable $e) {
            Log::error('loyalty.adjust_failed', ['user' => $userId, 'points' => $points, 'err' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Determine tier based on lifetime_spend thresholds and persist if changed.
     */
    public function recalculateTier(LoyaltyAccount $acc): string
    {
        $thresholds = [
            'platinum' => (float) AppSetting::get('marketing_loyalty_tier_platinum_spend', 50000),
            'gold'     => (float) AppSetting::get('marketing_loyalty_tier_gold_spend',     15000),
            'silver'   => (float) AppSetting::get('marketing_loyalty_tier_silver_spend',    3000),
        ];
        $spend = (float) $acc->lifetime_spend;
        $tier = 'bronze';
        if ($spend >= $thresholds['platinum']) $tier = 'platinum';
        elseif ($spend >= $thresholds['gold'])   $tier = 'gold';
        elseif ($spend >= $thresholds['silver']) $tier = 'silver';

        if ($acc->tier !== $tier) {
            $acc->update(['tier' => $tier]);
        }
        return $tier;
    }

    // ── Reverse on refund ───────────────────────────────────

    public function reverseOnRefund(int $orderId): void
    {
        if (!$this->enabled()) return;

        // Find earn txs for this order, create reverse entries
        $txs = LoyaltyTransaction::where('order_id', $orderId)->whereIn('type', ['earn', 'redeem'])->get();
        foreach ($txs as $t) {
            $this->adjust($t->user_id, -$t->points, 'reverse', 'refund_' . $t->type, $orderId, $t->related_amount);
        }
    }

    // ── Stats ────────────────────────────────────────────────

    public function summary(): array
    {
        $totalAccounts = DB::table('marketing_loyalty_accounts')->count();
        $totalPoints   = (int) DB::table('marketing_loyalty_accounts')->sum('points_balance');
        $totalSpent    = (float) DB::table('marketing_loyalty_accounts')->sum('lifetime_spend');
        $tierBreakdown = DB::table('marketing_loyalty_accounts')
            ->selectRaw('tier, COUNT(*) as n')
            ->groupBy('tier')
            ->pluck('n', 'tier')
            ->all();
        return compact('totalAccounts', 'totalPoints', 'totalSpent', 'tierBreakdown');
    }
}

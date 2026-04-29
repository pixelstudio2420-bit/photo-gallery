<?php

namespace App\Services\Marketing;

use App\Models\AppSetting;
use App\Models\Marketing\ReferralCode;
use App\Models\Marketing\ReferralRedemption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Referral code lifecycle:
 *   getOrCreateForUser → user shares → apply(code) on checkout → rewardOnOrder
 *
 * Defaults from app_settings:
 *   marketing_referral_discount_type   (percent|fixed, default 'percent')
 *   marketing_referral_discount_value  (default 10)
 *   marketing_referral_reward_value    (default 50, THB — what owner gets)
 *   marketing_referral_cooldown_days   (default 0 — re-use restriction)
 *   marketing_referral_points_per_baht (default 1 — N points awarded per 1 THB of reward_value)
 */
class ReferralService
{
    public function __construct(protected MarketingService $marketing) {}

    public function enabled(): bool
    {
        return $this->marketing->enabled('referral');
    }

    /**
     * Get (or lazily create) the personal referral code for a user.
     */
    public function getOrCreateForUser(User $user): ?ReferralCode
    {
        if (!$this->enabled()) return null;

        $code = ReferralCode::where('owner_user_id', $user->id)->first();
        if ($code) return $code;

        return ReferralCode::create([
            'code'           => ReferralCode::generateUniqueCode(8),
            'owner_user_id'  => $user->id,
            'discount_type'  => AppSetting::get('marketing_referral_discount_type', 'percent'),
            'discount_value' => (float) AppSetting::get('marketing_referral_discount_value', 10),
            'reward_value'   => (float) AppSetting::get('marketing_referral_reward_value', 50),
            'max_uses'       => 0,
            'is_active'      => true,
        ]);
    }

    /**
     * Validate + apply a code at checkout.
     * Does NOT mark as used until order completes.
     *
     * @return array{ok:bool, code:?ReferralCode, discount:float, message:string}
     */
    public function apply(string $code, float $subtotal, ?int $redeemerUserId = null): array
    {
        if (!$this->enabled()) {
            return ['ok' => false, 'code' => null, 'discount' => 0, 'message' => 'Referral program ปิดอยู่'];
        }
        $code = trim(strtoupper($code));
        $ref = ReferralCode::where('code', $code)->first();
        if (!$ref) {
            return ['ok' => false, 'code' => null, 'discount' => 0, 'message' => 'ไม่พบรหัสแนะนำนี้'];
        }
        if (!$ref->isUsable()) {
            return ['ok' => false, 'code' => $ref, 'discount' => 0, 'message' => 'รหัสหมดอายุหรือใช้เกินโควต้า'];
        }
        if ($redeemerUserId && $ref->owner_user_id === $redeemerUserId) {
            return ['ok' => false, 'code' => $ref, 'discount' => 0, 'message' => 'ใช้รหัสของตัวเองไม่ได้'];
        }

        $cooldown = (int) AppSetting::get('marketing_referral_cooldown_days', 0);
        if ($cooldown > 0 && $redeemerUserId) {
            $recent = ReferralRedemption::where('redeemer_user_id', $redeemerUserId)
                ->where('created_at', '>=', now()->subDays($cooldown))
                ->exists();
            if ($recent) {
                return ['ok' => false, 'code' => $ref, 'discount' => 0, 'message' => "ใช้รหัสแนะนำได้ทุก {$cooldown} วัน"];
            }
        }

        return [
            'ok' => true,
            'code' => $ref,
            'discount' => $ref->discountAmount($subtotal),
            'message' => 'ใช้รหัสแนะนำสำเร็จ',
        ];
    }

    /**
     * Record redemption + increment uses_count when order is placed.
     * Call this AFTER order is created but BEFORE reward is granted.
     */
    public function recordRedemption(ReferralCode $code, int $orderId, ?int $redeemerUserId, float $discountApplied): ReferralRedemption
    {
        $redemption = ReferralRedemption::create([
            'referral_code_id'   => $code->id,
            'redeemer_user_id'   => $redeemerUserId,
            'order_id'           => $orderId,
            'discount_applied'   => $discountApplied,
            'reward_granted'     => 0,
            'status'             => 'pending',
        ]);
        $code->increment('uses_count');
        return $redemption;
    }

    /**
     * Grant reward to code owner AFTER order is paid/confirmed.
     * Reward is stored as a redeemable balance for the owner (via loyalty points or coupon).
     */
    public function rewardOnOrder(int $orderId): array
    {
        if (!$this->enabled()) {
            return ['ok' => false, 'message' => 'Referral program ปิดอยู่'];
        }
        $redemption = ReferralRedemption::where('order_id', $orderId)
            ->where('status', 'pending')
            ->first();
        if (!$redemption) {
            return ['ok' => false, 'message' => 'ไม่พบ redemption สำหรับ order นี้'];
        }
        $code = $redemption->code;
        if (!$code) {
            return ['ok' => false, 'message' => 'Referral code ถูกลบไปแล้ว'];
        }
        $reward = (float) $code->reward_value;

        // Conversion rate: how many loyalty points per 1 THB of reward.
        // Lets admin tune the "perceived value" of the reward independently
        // of the bookkeeping figure stored in `reward_granted`.
        $pointsPerBaht = (float) AppSetting::get('marketing_referral_points_per_baht', 1);
        $points        = (int) floor($reward * $pointsPerBaht);

        try {
            DB::beginTransaction();
            $redemption->update([
                'reward_granted' => $reward,
                'status'         => 'rewarded',
                'rewarded_at'    => now(),
            ]);

            // If loyalty is enabled, credit as points; else future: coupon/wallet
            if ($this->marketing->enabled('loyalty') && $points > 0) {
                $loyalty = app(LoyaltyService::class);
                $loyalty->adjust($code->owner_user_id, $points, 'adjust', 'referral_reward', null, (float) $reward);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('referral.reward_failed', ['order' => $orderId, 'err' => $e->getMessage()]);
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        return ['ok' => true, 'reward' => $reward];
    }

    public function reverseOnRefund(int $orderId): void
    {
        $r = ReferralRedemption::where('order_id', $orderId)->first();
        if (!$r || $r->status !== 'rewarded') return;
        $r->update(['status' => 'reversed']);

        if ($this->marketing->enabled('loyalty') && $r->reward_granted > 0 && $r->code) {
            // Reverse using the SAME conversion rate as the original grant —
            // protects us if admin tweaks `points_per_baht` between grant
            // and refund (otherwise we'd over- or under-reverse).
            $pointsPerBaht = (float) AppSetting::get('marketing_referral_points_per_baht', 1);
            $points        = (int) floor((float) $r->reward_granted * $pointsPerBaht);
            if ($points > 0) {
                app(LoyaltyService::class)->adjust(
                    $r->code->owner_user_id,
                    -$points,
                    'reverse',
                    'referral_reversed',
                    $orderId,
                    (float) $r->reward_granted
                );
            }
        }
    }

    public function statsForUser(User $user): array
    {
        $code = ReferralCode::where('owner_user_id', $user->id)->first();
        if (!$code) {
            return ['code' => null, 'uses' => 0, 'rewarded' => 0, 'total_reward' => 0];
        }
        $stats = DB::table('marketing_referral_redemptions')
            ->where('referral_code_id', $code->id)
            ->selectRaw("COUNT(*) as uses, SUM(CASE WHEN status='rewarded' THEN 1 ELSE 0 END) as rewarded, COALESCE(SUM(reward_granted),0) as total_reward")
            ->first();
        return [
            'code' => $code,
            'uses' => (int) ($stats->uses ?? 0),
            'rewarded' => (int) ($stats->rewarded ?? 0),
            'total_reward' => (float) ($stats->total_reward ?? 0),
        ];
    }
}

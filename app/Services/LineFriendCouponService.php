<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Issues + delivers the welcome coupon promised by the LINE friend
 * popup ("ส่วนลด ฿100 ครั้งถัดไป" or whatever admin configured as the
 * popup carrot).
 *
 * Flow:
 *   LINE OA webhook fires `follow` → LineOaWebhookController matches
 *   the userId to an auth_users row → calls issueWelcomeCoupon().
 *
 *   1. Idempotency check — has this user already received a welcome
 *      coupon? If yes, just re-send the existing code (LINE add-then-
 *      remove-then-add cycles shouldn't double-grant).
 *   2. Generate a personal code: "LINE-WELCOME-{userId}-{random6}"
 *   3. Create a Coupon row scoped to per_user_limit=1, fixed-value
 *      discount, configurable expiry.
 *   4. Push the code to the user via LINE Messaging API (pushToUser).
 *      Failure to push is logged but doesn't roll back coupon
 *      creation — admin can read it from /admin/coupons and re-send.
 *
 * Admin tunables (all in app_settings, all optional):
 *   line_friend_welcome_coupon_value         default 100  (THB)
 *   line_friend_welcome_coupon_type          default 'fixed'  (fixed | percentage)
 *   line_friend_welcome_coupon_min_order     default 0
 *   line_friend_welcome_coupon_validity_days default 30
 *   line_friend_welcome_coupon_enabled       default '1'  ('0' to disable entirely)
 */
class LineFriendCouponService
{
    public function __construct(
        private readonly LineNotifyService $line,
    ) {}

    /**
     * Issue (or reissue) the welcome coupon for a user who just added
     * our LINE OA. Returns the Coupon — caller can use this for
     * audit/logging if needed.
     */
    public function issueWelcomeCoupon(User $user): ?Coupon
    {
        if (AppSetting::get('line_friend_welcome_coupon_enabled', '1') !== '1') {
            return null;
        }
        if (empty($user->line_user_id)) {
            // Webhook fired before LINE Login captured their userId —
            // can't push a message anywhere. Skip silently.
            return null;
        }

        $coupon = $this->findOrCreateCoupon($user);
        if (!$coupon) {
            return null;
        }

        // Push the code via LINE Messaging API. Wrapped in try so a
        // delivery failure doesn't blow up the whole webhook (we'd
        // rather have an issued-but-unpushed coupon than a 500 to LINE
        // — LINE will retry the webhook + we'd issue a duplicate
        // coupon on the retry, which the idempotency check above
        // prevents).
        try {
            $this->pushCodeMessage($user->line_user_id, $coupon, $user);
        } catch (\Throwable $e) {
            Log::warning('LineFriendCoupon push failed', [
                'user_id'     => $user->id,
                'coupon_code' => $coupon->code,
                'error'       => $e->getMessage(),
            ]);
        }

        return $coupon;
    }

    /**
     * Find an existing welcome coupon for this user, or mint a new one.
     * Codes follow `LINE-WELCOME-{userId}-{6-char-random}` so the same
     * user never gets a fresh code on follow→unfollow→follow cycles.
     */
    private function findOrCreateCoupon(User $user): ?Coupon
    {
        $prefix = 'LINE-WELCOME-' . $user->id . '-';

        // Idempotent — return the existing coupon if we already issued
        // one for this user (any time in the past).
        $existing = Coupon::where('code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();
        if ($existing) {
            return $existing;
        }

        $value      = (float) AppSetting::get('line_friend_welcome_coupon_value', 100);
        $type       = (string) AppSetting::get('line_friend_welcome_coupon_type', 'fixed');
        $minOrder   = (float) AppSetting::get('line_friend_welcome_coupon_min_order', 0);
        $validDays  = (int) AppSetting::get('line_friend_welcome_coupon_validity_days', 30);
        $code       = $prefix . strtoupper(Str::random(6));

        try {
            return Coupon::create([
                'code'           => $code,
                'name'           => 'LINE Welcome Bonus — ' . $user->id,
                'description'    => 'ส่วนลดต้อนรับสำหรับเพื่อนใหม่ LINE OA · ใช้ได้ครั้งเดียว',
                'type'           => $type,
                'value'          => $value,
                'min_order'      => $minOrder,
                'usage_limit'    => 1,    // single redemption (this user only)
                'usage_count'    => 0,
                'per_user_limit' => 1,
                'start_date'     => now(),
                'end_date'       => now()->addDays($validDays),
                'is_active'      => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('LineFriendCoupon create failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Compose + push the welcome message to the user's LINE.
     * Plain text (no flex template) so it works even on basic clients.
     */
    private function pushCodeMessage(string $lineUserId, Coupon $coupon, User $user): void
    {
        $valueLabel = $coupon->type === 'percentage'
            ? rtrim(rtrim(number_format($coupon->value, 0), '0'), '.') . '%'
            : '฿' . number_format($coupon->value, 0);

        $expiresLabel = $coupon->end_date
            ? \Carbon\Carbon::parse($coupon->end_date)->format('d M Y')
            : 'ไม่จำกัด';

        $firstName = $user->first_name ?: 'คุณ';

        $msg = "🎉 ยินดีต้อนรับ {$firstName}!\n\n"
             . "ขอบคุณที่เพิ่ม Loadroop เป็นเพื่อนใน LINE\n\n"
             . "🎁 รับโค้ดส่วนลด {$valueLabel}\n"
             . "📋 โค้ด: {$coupon->code}\n"
             . "📅 ใช้ได้ถึง: {$expiresLabel}\n"
             . ($coupon->min_order > 0
                ? "💰 ขั้นต่ำ ฿" . number_format($coupon->min_order, 0) . "\n"
                : '')
             . "\n"
             . "✨ วิธีใช้:\n"
             . "1. ไปที่หน้าชำระเงินที่ loadroop.com\n"
             . "2. ใส่โค้ดในช่องส่วนลด\n"
             . "3. รับส่วนลดทันที\n\n"
             . "📸 หากมีคำสั่งซื้อใหม่ เราจะส่งรูปและสถานะมาทาง LINE นี้";

        $this->line->pushToUser($lineUserId, $msg);
    }
}

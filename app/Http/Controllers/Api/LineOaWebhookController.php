<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * LINE OA follow/unfollow webhook.
 *
 * Configure in LINE Console:
 *   Messaging API channel → Webhook URL
 *     https://loadroop.com/api/webhooks/line-oa
 *   Toggle "Use webhook" ON
 *   "Auto-reply messages" can be off — we don't reply here.
 *
 * Event lifecycle:
 *   - User taps "Add Friend" on the OA → LINE sends "follow" event.
 *     Body has `events[*].source.userId` = the LINE userId.
 *   - User taps "Block" or "Delete Friend" → LINE sends "unfollow".
 *
 * Matching strategy:
 *   We match the incoming userId against auth_users.line_user_id, which
 *   was captured during LINE Login OAuth. If no match (user added the OA
 *   without ever doing LINE Login), we ignore — there's nothing to update.
 *   These users will be matched on their NEXT LINE Login.
 *
 * Security:
 *   X-Line-Signature header carries an HMAC-SHA256 of the raw body using
 *   the channel secret. We verify with hash_equals to prevent timing
 *   attacks. Missing/wrong signature → 403, no DB writes.
 */
class LineOaWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Settings page saves Messaging API credentials under line_channel_*;
        // marketing_line_channel_secret is a backwards-compat fallback for
        // any older config rows.
        $secret = (string) AppSetting::get('line_channel_secret', '')
               ?: (string) AppSetting::get('marketing_line_channel_secret', '');
        if ($secret === '') {
            // Channel not configured — we MUST refuse rather than silently
            // accept anything, since unsigned events would let any caller
            // flip line_is_friend for arbitrary userIds.
            Log::warning('line_oa.webhook.no_secret_configured');
            return response()->json(['error' => 'webhook not configured'], 503);
        }

        $rawBody   = $request->getContent();
        $signature = $request->header('X-Line-Signature', '');
        $expected  = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        if ($signature === '' || !hash_equals($expected, $signature)) {
            Log::warning('line_oa.webhook.bad_signature', [
                'has_header' => $signature !== '',
            ]);
            return response()->json(['error' => 'invalid signature'], 403);
        }

        $payload = json_decode($rawBody, true);
        $events  = $payload['events'] ?? [];
        if (!is_array($events) || empty($events)) {
            // LINE pings the URL with empty events to verify it's reachable —
            // return 200 so the verification check passes.
            return response()->json(['ok' => true]);
        }

        $matched = 0;
        foreach ($events as $event) {
            $type   = $event['type']                   ?? '';
            $userId = $event['source']['userId']       ?? '';
            if ($userId === '') continue;

            // We only act on follow/unfollow. Other event types (message,
            // postback, etc.) are out of scope for this controller — they
            // get logged and ignored for future routing.
            if (!in_array($type, ['follow', 'unfollow'], true)) {
                continue;
            }

            $isFriend = ($type === 'follow');

            // Match against auth_users.line_user_id (set during LINE Login
            // OAuth). When no match exists, we don't have a way to map the
            // LINE userId to one of our app users yet — they'll get
            // matched on their next OAuth login.
            $user = User::where('line_user_id', $userId)->first();
            if (!$user) {
                Log::info('line_oa.webhook.unmatched_user', [
                    'event'       => $type,
                    'line_userid' => substr($userId, 0, 8) . '...',
                ]);
                continue;
            }

            // Idempotent: only flip + bump timestamp when the value
            // actually changes. Avoids touching updated_at on every
            // webhook retry.
            $wasFriend = (bool) $user->line_is_friend;
            if ($wasFriend !== $isFriend) {
                $user->forceFill([
                    'line_is_friend'         => $isFriend,
                    'line_friend_changed_at' => now(),
                ])->save();
                $matched++;

                // ─── Welcome coupon delivery (the popup's promise) ───
                // When user transitions from non-friend → friend, issue
                // the welcome coupon promised by the friend-add popup
                // ("ส่วนลด ฿100 ครั้งถัดไป") and push the code to their
                // LINE. The service handles idempotency so re-following
                // (after an unfollow) just resends the existing code,
                // never grants a fresh discount.
                //
                // Wrapped so a coupon-side failure (e.g. coupons table
                // schema drift) doesn't 500 the webhook + cause LINE to
                // retry → flip the friend flag again. The webhook returns
                // 200 even if coupon issuance fails; admin can re-send
                // manually from /admin/coupons if needed.
                if ($isFriend) {
                    try {
                        app(\App\Services\LineFriendCouponService::class)
                            ->issueWelcomeCoupon($user);
                    } catch (\Throwable $e) {
                        Log::warning('line_oa.webhook.coupon_issue_failed', [
                            'user_id' => $user->id,
                            'error'   => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        return response()->json(['ok' => true, 'matched' => $matched]);
    }
}

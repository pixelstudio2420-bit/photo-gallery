<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Order;
use App\Models\User;
use App\Services\LineNotifyService;
use App\Services\PhotoDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Admin — LINE Messaging Test Console
 * ───────────────────────────────────
 * Verifies the LINE Messaging API end-to-end before relying on it for
 * production photo delivery. Each test is independent and reports the
 * exact LINE API response so admins can diagnose:
 *
 *   • token misconfigured       (401 from LINE)
 *   • channel disabled          (403)
 *   • user not following OA     (400 "Failed to send messages")
 *   • image URL not HTTPS       (400 invalid)
 *   • image too large           (400 image_invalid)
 *
 * Three test modes:
 *
 *   1. /admin/settings/line-test/diagnostics — health check, no message sent.
 *      Validates token presence, hits /v2/bot/info to verify auth.
 *
 *   2. POST /admin/settings/line-test/send-text — send a plain text to a
 *      LINE userId. Smallest possible message; failure here means token
 *      or follow-state is wrong.
 *
 *   3. POST /admin/settings/line-test/send-photo — send an image message
 *      with the URL provided (or a default sample). Reproduces the
 *      production photo-delivery flow.
 *
 *   4. POST /admin/settings/line-test/replay-order — pick a real paid
 *      order and re-run the LINE delivery on it. Useful for diagnosing
 *      "this specific order didn't deliver" support tickets.
 */
class LineMessagingTestController extends Controller
{
    public function __construct(
        private LineNotifyService $line,
        private PhotoDeliveryService $delivery,
    ) {}

    /**
     * Test page — form + diagnostics summary.
     */
    public function index(Request $request)
    {
        $diagnostics = $this->runDiagnostics();

        // Optional: auto-fill user lookup if ?user_id=N
        $selectedUser = null;
        $selectedLineId = null;
        if ($request->filled('user_id')) {
            $selectedUser = User::find((int) $request->query('user_id'));
            if ($selectedUser) {
                $selectedLineId = $this->lookupLineId($selectedUser->id);
            }
        }

        // Recent orders that were marked delivered via LINE (or attempted)
        // — admin can pick one to replay.
        $recentLineOrders = collect();
        try {
            $recentLineOrders = Order::where('delivery_method', 'line')
                ->where('status', 'paid')
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'order_number', 'user_id', 'delivery_status', 'delivered_at']);
        } catch (\Throwable $e) {}

        return view('admin.settings.line-test', [
            'diagnostics'      => $diagnostics,
            'selectedUser'     => $selectedUser,
            'selectedLineId'   => $selectedLineId,
            'recentLineOrders' => $recentLineOrders,
            'channelInfo'      => $diagnostics['channel_info'] ?? null,
        ]);
    }

    /**
     * Health check — verify LINE channel access token + reach the
     * `/v2/bot/info` endpoint. No message sent.
     */
    public function diagnostics()
    {
        return response()->json($this->runDiagnostics());
    }

    /**
     * Send a text message to a LINE userId for smoke testing.
     */
    public function sendText(Request $request)
    {
        $data = $request->validate([
            'line_user_id' => 'required_without:user_id|nullable|string|max:64',
            'user_id'      => 'required_without:line_user_id|nullable|integer',
            'text'         => 'required|string|max:1000',
        ]);

        [$lineUserId, $error] = $this->resolveLineUserId($data);
        if ($error) {
            return back()->with('test_result', ['ok' => false, 'kind' => 'text', 'error' => $error]);
        }

        $token = AppSetting::get('line_channel_access_token', '');
        if (!$token) {
            return back()->with('test_result', [
                'ok' => false, 'kind' => 'text',
                'error' => 'ไม่ได้ตั้ง line_channel_access_token ในแอดมิน',
            ]);
        }

        try {
            $resp = Http::withToken($token)
                ->timeout(10)
                ->post('https://api.line.me/v2/bot/message/push', [
                    'to'       => $lineUserId,
                    'messages' => [['type' => 'text', 'text' => $data['text']]],
                ]);

            return back()->with('test_result', [
                'ok'      => $resp->successful(),
                'kind'    => 'text',
                'status'  => $resp->status(),
                'body'    => $resp->body(),
                'message' => $resp->successful()
                    ? 'ส่งข้อความสำเร็จ — ตรวจดู LINE chat ของผู้ใช้ที่ส่งให้'
                    : $this->interpretLineError($resp->status(), $resp->body()),
                'sent_to' => substr($lineUserId, 0, 8) . '…',
            ]);
        } catch (\Throwable $e) {
            return back()->with('test_result', [
                'ok' => false, 'kind' => 'text',
                'error' => 'Exception: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a single image message. Either accept a URL or fall back to
     * a known-good public sample image so admin can verify the path
     * even before they have a real photo URL ready.
     */
    public function sendPhoto(Request $request)
    {
        $data = $request->validate([
            'line_user_id'  => 'required_without:user_id|nullable|string|max:64',
            'user_id'       => 'required_without:line_user_id|nullable|integer',
            'image_url'     => 'nullable|url|max:2000',
            'caption'       => 'nullable|string|max:1000',
        ]);

        [$lineUserId, $error] = $this->resolveLineUserId($data);
        if ($error) {
            return back()->with('test_result', ['ok' => false, 'kind' => 'photo', 'error' => $error]);
        }

        // Default to a known-good HTTPS test image when none provided.
        // Picsum returns a real JPEG that meets LINE's preview/original
        // size requirements (≤4096×4096, ≤10 MB).
        $url = $data['image_url'] ?: 'https://picsum.photos/seed/photogallery-test/1200/800';

        if (!str_starts_with($url, 'https://')) {
            return back()->with('test_result', [
                'ok' => false, 'kind' => 'photo',
                'error' => 'LINE บังคับใช้ HTTPS — URL ที่ส่งมาขึ้นต้นด้วย http://',
            ]);
        }

        try {
            $ok = $this->line->pushPhotos(
                userId:  $this->userIdForLineId($lineUserId) ?? 0,
                images:  [['original_url' => $url, 'preview_url' => $url]],
                caption: $data['caption'] ?: '🧪 Test photo from admin console'
            );

            // pushPhotos returns false on any internal failure (token missing,
            // user not friend, etc.) — we hit the LINE API directly to capture
            // the raw response for diagnostics.
            $token = AppSetting::get('line_channel_access_token', '');
            $rawResp = null;
            if ($token) {
                $rawResp = Http::withToken($token)
                    ->timeout(10)
                    ->post('https://api.line.me/v2/bot/message/push', [
                        'to'       => $lineUserId,
                        'messages' => [
                            ['type' => 'image',
                             'originalContentUrl' => $url,
                             'previewImageUrl'    => $url],
                        ],
                    ]);
            }

            return back()->with('test_result', [
                'ok'      => $rawResp ? $rawResp->successful() : $ok,
                'kind'    => 'photo',
                'status'  => $rawResp?->status(),
                'body'    => $rawResp?->body(),
                'message' => ($rawResp && $rawResp->successful())
                    ? 'ส่งภาพสำเร็จ — ตรวจดู LINE chat ของผู้ใช้'
                    : ($rawResp ? $this->interpretLineError($rawResp->status(), $rawResp->body()) : 'pushPhotos returned false'),
                'sent_to' => substr($lineUserId, 0, 8) . '…',
                'image_url' => $url,
            ]);
        } catch (\Throwable $e) {
            return back()->with('test_result', [
                'ok' => false, 'kind' => 'photo',
                'error' => 'Exception: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Re-run LINE delivery for a real paid order. Useful for support
     * tickets where the customer claims they didn't receive their
     * photos. Idempotent — PhotoDeliveryService is safe to re-call.
     */
    public function replayOrder(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
        ]);

        $order = Order::with('items')->find($data['order_id']);
        if (!$order) {
            return back()->with('test_result', ['ok' => false, 'kind' => 'replay', 'error' => 'Order not found']);
        }
        if ($order->status !== 'paid') {
            return back()->with('test_result', [
                'ok' => false, 'kind' => 'replay',
                'error' => "Order #{$order->order_number} not paid (status: {$order->status})",
            ]);
        }

        // Force-set delivery_method to line for this replay, then call deliver().
        $original = $order->delivery_method;
        $order->delivery_method = 'line';

        try {
            $result = $this->delivery->deliver($order);
            // Restore the original choice (unless the buyer originally chose line).
            if ($original !== 'line') {
                $order->delivery_method = $original;
                $order->save();
            }

            return back()->with('test_result', [
                'ok'      => ($result['status'] ?? null) === 'delivered',
                'kind'    => 'replay',
                'status'  => 200,
                'body'    => json_encode($result, JSON_UNESCAPED_UNICODE),
                'message' => $result['message'] ?? 'Delivery attempted',
                'sent_to' => 'order #' . $order->order_number,
            ]);
        } catch (\Throwable $e) {
            return back()->with('test_result', [
                'ok' => false, 'kind' => 'replay',
                'error' => 'Exception: ' . $e->getMessage(),
            ]);
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────

    /**
     * Run the health-check sequence and return a structured array.
     */
    private function runDiagnostics(): array
    {
        $token = (string) AppSetting::get('line_channel_access_token', '');
        $messagingEnabled = AppSetting::get('line_messaging_enabled', '0') === '1';
        $userPushEnabled = AppSetting::get('line_user_push_enabled', '1') === '1';
        $deliverPhotos = AppSetting::get('delivery_line_send_photos', '0') === '1';

        $checks = [
            ['name' => 'line_messaging_enabled (toggle)', 'ok' => $messagingEnabled,
             'hint' => $messagingEnabled ? null : 'เปิดที่ /admin/settings/line'],
            ['name' => 'line_user_push_enabled (toggle)',  'ok' => $userPushEnabled,
             'hint' => $userPushEnabled ? null : 'เปิดที่ /admin/settings/line'],
            ['name' => 'line_channel_access_token (present)', 'ok' => $token !== '',
             'hint' => $token ? null : 'กรอกที่ /admin/settings/line'],
            ['name' => 'delivery_line_send_photos (auto-deliver photos toggle)', 'ok' => $deliverPhotos,
             'hint' => $deliverPhotos ? null : 'เปิดถ้าต้องการให้ระบบส่งรูปลูกค้าเข้า LINE หลังจ่ายเงิน'],
        ];

        // Bot info — only attempt if token present
        $channelInfo = null;
        $botCheck = ['name' => 'LINE channel auth', 'ok' => false, 'hint' => 'ต้องมี token ก่อน'];
        if ($token) {
            try {
                $resp = Http::withToken($token)
                    ->timeout(10)
                    ->get('https://api.line.me/v2/bot/info');
                if ($resp->successful()) {
                    $channelInfo = $resp->json();
                    $botCheck = ['name' => 'LINE channel auth', 'ok' => true, 'hint' => null];
                } else {
                    $botCheck = [
                        'name' => 'LINE channel auth',
                        'ok'   => false,
                        'hint' => "HTTP {$resp->status()} — " . $this->interpretLineError($resp->status(), $resp->body()),
                    ];
                }
            } catch (\Throwable $e) {
                $botCheck = ['name' => 'LINE channel auth', 'ok' => false, 'hint' => 'Network: '.$e->getMessage()];
            }
        }
        $checks[] = $botCheck;

        // Quota / friends count from /info → numbers come from /messages/quota.
        $quota = null;
        if ($token && $channelInfo) {
            try {
                $q = Http::withToken($token)->timeout(10)->get('https://api.line.me/v2/bot/message/quota');
                $u = Http::withToken($token)->timeout(10)->get('https://api.line.me/v2/bot/message/quota/consumption');
                $f = Http::withToken($token)->timeout(10)
                    ->get('https://api.line.me/v2/bot/insight/followers?date=' . now()->subDay()->format('Ymd'));
                $quota = [
                    'limit'      => $q->ok() ? ($q->json('value') ?? 'N/A') : 'N/A',
                    'consumed'   => $u->ok() ? ($u->json('totalUsage') ?? 0) : 0,
                    'followers'  => $f->ok() ? ($f->json('followers') ?? 0) : 0,
                ];
            } catch (\Throwable $e) {}
        }

        $allOk = collect($checks)->every(fn($c) => $c['ok']);

        return [
            'all_ok'       => $allOk,
            'checks'       => $checks,
            'channel_info' => $channelInfo,
            'quota'        => $quota,
        ];
    }

    /**
     * Resolve a LINE userId from the form input. Either accept the raw
     * LINE userId (starts with 'U' for users, 32 chars), or look it up
     * from a platform user_id via auth_social_logins.
     *
     * Returns [lineUserId|null, error|null]
     */
    private function resolveLineUserId(array $data): array
    {
        if (!empty($data['line_user_id'])) {
            $id = trim($data['line_user_id']);
            if (!preg_match('/^U[a-f0-9]{32}$/i', $id)) {
                return [null, 'LINE userId ต้องขึ้นต้นด้วย U + 32 ตัวอักษร hex (ตรวจอีกครั้ง)'];
            }
            return [$id, null];
        }
        if (!empty($data['user_id'])) {
            $id = $this->lookupLineId((int) $data['user_id']);
            if (!$id) {
                return [null, "User #{$data['user_id']} ยังไม่ได้ link LINE — ลูกค้าต้อง login ด้วย LINE ก่อน"];
            }
            return [$id, null];
        }
        return [null, 'ต้องระบุ LINE userId หรือ User ID'];
    }

    private function lookupLineId(int $userId): ?string
    {
        if (!Schema::hasTable('auth_social_logins')) return null;
        $row = DB::table('auth_social_logins')
            ->where('user_id', $userId)
            ->where('provider', 'line')
            ->first();
        return $row?->provider_id;
    }

    private function userIdForLineId(string $lineUserId): ?int
    {
        if (!Schema::hasTable('auth_social_logins')) return null;
        $row = DB::table('auth_social_logins')
            ->where('provider_id', $lineUserId)
            ->where('provider', 'line')
            ->first();
        return $row ? (int) $row->user_id : null;
    }

    /**
     * Convert a LINE error response to a friendly Thai message.
     */
    private function interpretLineError(int $status, string $body): string
    {
        if ($status === 401) {
            return 'Token ไม่ถูกต้อง — ตรวจ line_channel_access_token (อาจหมดอายุหรือพิมพ์ผิด)';
        }
        if ($status === 403) {
            return 'Channel ไม่มีสิทธิ์ส่ง — ตรวจ Messaging API ใน LINE Developer Console';
        }
        if ($status === 400 && str_contains($body, 'Failed to send messages')) {
            return 'ลูกค้ายังไม่ได้ add LINE OA เป็นเพื่อน — ขอให้ลูกค้า scan QR หรือกด add friend';
        }
        if ($status === 400 && str_contains($body, 'image')) {
            return 'รูปไม่ตรงสเปก — ต้องเป็น HTTPS, JPG/PNG, ≤10MB, ≤4096×4096';
        }
        if ($status === 400) {
            return 'Bad request — ' . substr($body, 0, 200);
        }
        if ($status === 429) {
            return 'Rate limited — รอสักครู่แล้วลองใหม่';
        }
        return "HTTP {$status} — " . substr($body, 0, 200);
    }
}

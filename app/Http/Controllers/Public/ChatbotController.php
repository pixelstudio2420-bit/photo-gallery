<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate(['message' => 'required|string|max:500']);
        $rawMessage = trim($request->message);
        $message = mb_strtolower($rawMessage);

        // 1) Try keyword-based intent first — for "search events" /
        //    "order status" we want to query the DB directly because
        //    the LLM has no access to the catalogue. Specific intents
        //    return early with structured `data` for the UI to render.
        $response = $this->processMessage($message, $request);

        // 2) If the keyword pass returned the generic fallback, try an
        //    LLM (OpenAI or Anthropic) for a free-form answer. Falls
        //    back to the original "ขอโทษครับ ไม่เข้าใจ" when no API
        //    keys are configured — installs without LLM keys still
        //    work, just without the smart-fallback behaviour.
        if ($this->isFallbackResponse($response['text'])) {
            $llm = $this->askLlm($rawMessage);
            if ($llm) {
                $response = ['text' => $llm, 'type' => 'text'];
            }
        }

        return response()->json([
            'reply' => $response['text'],
            'type' => $response['type'] ?? 'text',
            'data' => $response['data'] ?? null,
        ]);
    }

    /**
     * Detect the "I don't understand" fallback so we know when to
     * escalate to the LLM. Match a stable substring that doesn't
     * change across translations of the message.
     */
    private function isFallbackResponse(string $text): bool
    {
        return str_contains($text, 'ผมไม่เข้าใจ');
    }

    /**
     * Free-form LLM fallback. Returns null if no provider is configured
     * or the call fails for any reason — the caller stays with the
     * keyword fallback in that case.
     *
     * Cheap-and-good: uses the smallest reasonable models (gpt-4o-mini
     * for OpenAI, claude-3-5-haiku for Anthropic) at low temperature
     * so support answers stay grounded.
     */
    private function askLlm(string $userMessage): ?string
    {
        $systemPrompt = "คุณเป็นผู้ช่วยซัพพอร์ตของเว็บขายรูปจากงานอีเวนต์ "
            . "ตอบเป็นภาษาไทยอย่างกระชับ สุภาพ และตรงประเด็น ใน 2-4 ประโยคต่อข้อความ "
            . "หากผู้ใช้ถามเรื่องที่ต้องตรวจสอบข้อมูลส่วนตัว (สถานะออเดอร์เฉพาะ, ยอดเงิน) "
            . "ให้แนะนำให้ติดต่อทีมงานหรือเข้าหน้า My Orders ของระบบ ห้ามเดาข้อมูลส่วนตัว";

        try {
            if (!empty(env('OPENAI_API_KEY'))) {
                $resp = Http::withToken(env('OPENAI_API_KEY'))
                    ->timeout(15)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model'       => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
                        'temperature' => 0.3,
                        'max_tokens'  => 220,
                        'messages'    => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user',   'content' => $userMessage],
                        ],
                    ]);
                if ($resp->ok()) {
                    return trim((string) ($resp->json('choices.0.message.content') ?? ''));
                }
                Log::warning('Chatbot OpenAI HTTP ' . $resp->status());
            }

            if (!empty(env('ANTHROPIC_API_KEY'))) {
                $resp = Http::withHeaders([
                    'x-api-key'        => env('ANTHROPIC_API_KEY'),
                    'anthropic-version'=> '2023-06-01',
                ])
                    ->timeout(15)
                    ->post('https://api.anthropic.com/v1/messages', [
                        'model'      => env('ANTHROPIC_CHAT_MODEL', 'claude-3-5-haiku-20241022'),
                        'max_tokens' => 220,
                        'system'     => $systemPrompt,
                        'messages'   => [
                            ['role' => 'user', 'content' => $userMessage],
                        ],
                    ]);
                if ($resp->ok()) {
                    return trim((string) ($resp->json('content.0.text') ?? ''));
                }
                Log::warning('Chatbot Anthropic HTTP ' . $resp->status());
            }
        } catch (\Throwable $e) {
            Log::warning('Chatbot LLM error: ' . $e->getMessage());
        }

        return null;
    }

    protected function processMessage(string $message, Request $request): array
    {
        // Greeting
        if (preg_match('/^(สวัสดี|hello|hi|หวัดดี|ดีครับ|ดีค่ะ|hey)/u', $message)) {
            return [
                'text' => "สวัสดีครับ! ยินดีให้บริการ ผมช่วยอะไรได้บ้างครับ? เช่น:\n• ค้นหาอีเวนต์\n• ตรวจสอบสถานะออร์เดอร์\n• สอบถามข้อมูลทั่วไป",
                'type' => 'text',
            ];
        }

        // Search events
        if (preg_match('/(ค้นหา|หา|search|อีเวนต์|event|งาน|ถ่ายรูป|ถ่ายภาพ)/u', $message)) {
            $keyword = preg_replace('/(ค้นหา|หา|อีเวนต์|งาน|search|event)/u', '', $message);
            $keyword = trim($keyword);

            $query = Event::where('status', 'active');
            if ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'ilike', "%{$keyword}%")
                      ->orWhere('location', 'ilike', "%{$keyword}%");
                });
            }
            $events = $query->orderByDesc('shoot_date')->limit(5)->get();

            if ($events->isEmpty()) {
                return [
                    'text' => 'ไม่พบอีเวนต์ที่ตรงกับ "' . ($keyword ?: 'ทั้งหมด') . '" ลองค้นหาด้วยคำอื่นดูครับ',
                    'type' => 'text',
                ];
            }

            $eventList = $events->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'date' => $e->shoot_date?->format('d/m/Y'),
                'location' => $e->location,
                'price' => $e->price_per_photo,
                'slug' => $e->slug,
            ])->toArray();

            return [
                'text' => 'พบ ' . count($eventList) . ' อีเวนต์ครับ:',
                'type' => 'events',
                'data' => $eventList,
            ];
        }

        // Check order status
        if (preg_match('/(ออร์เดอร์|order|สถานะ|status|คำสั่งซื้อ|ตรวจสอบ)/u', $message)) {
            if (!Auth::guard('web')->check()) {
                return [
                    'text' => 'กรุณาเข้าสู่ระบบก่อนเพื่อตรวจสอบสถานะออร์เดอร์ครับ',
                    'type' => 'text',
                ];
            }

            // Try to extract order ID
            preg_match('/\d+/', $message, $matches);
            if (!empty($matches[0])) {
                $order = Order::where('id', $matches[0])
                    ->where('user_id', Auth::guard('web')->id())
                    ->with('event')
                    ->first();
                if ($order) {
                    $statusTh = match ($order->status) {
                        'pending', 'pending_payment' => 'รอชำระเงิน',
                        'pending_review'             => 'รอตรวจสอบ',
                        'paid', 'completed'          => 'ชำระเงินแล้ว',
                        'cancelled'                  => 'ยกเลิก',
                        default                      => $order->status,
                    };
                    $eventName = $order->event->name ?? '-';
                    return [
                        'text' => "ออร์เดอร์ #{$order->id}\nอีเวนต์: {$eventName}\nยอดรวม: ฿" . number_format($order->total, 2) . "\nสถานะ: {$statusTh}\nวันที่: {$order->created_at->format('d/m/Y H:i')}",
                        'type' => 'text',
                    ];
                }
                return [
                    'text' => 'ไม่พบออร์เดอร์หมายเลข #' . $matches[0] . ' ครับ',
                    'type' => 'text',
                ];
            }

            // Show recent orders
            $orders = Order::where('user_id', Auth::guard('web')->id())
                ->with('event')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();
            if ($orders->isEmpty()) {
                return [
                    'text' => 'คุณยังไม่มีคำสั่งซื้อครับ ลองดูอีเวนต์ที่น่าสนใจได้เลย!',
                    'type' => 'text',
                ];
            }
            $orderList = $orders->map(fn ($o) => "#{$o->id} - " . ($o->event->name ?? '-') . " - ฿" . number_format($o->total, 2) . " ({$o->status})")->join("\n");
            return [
                'text' => "ออร์เดอร์ล่าสุดของคุณ:\n{$orderList}\n\nพิมพ์หมายเลขออร์เดอร์เพื่อดูรายละเอียดครับ",
                'type' => 'text',
            ];
        }

        // FAQ - Price
        if (preg_match('/(ราคา|price|ค่าใช้จ่าย|แพง|ถูก|เท่าไหร่|เท่าไร)/u', $message)) {
            $minPrice = AppSetting::get('min_event_price', '100');
            return [
                'text' => "ราคารูปภาพขึ้นอยู่กับแต่ละอีเวนต์ครับ\n• ราคาเริ่มต้นที่ ฿{$minPrice} ต่อรูป\n• บางอีเวนต์อาจเปิดให้ดาวน์โหลดฟรี\n• สามารถดูราคาได้ที่หน้ารายละเอียดอีเวนต์ครับ",
                'type' => 'text',
            ];
        }

        // FAQ - How to buy / download
        if (preg_match('/(ซื้อ|buy|download|ดาวน์โหลด|โหลด|สั่ง|วิธี)/u', $message)) {
            return [
                'text' => "วิธีซื้อรูปภาพ:\n1. เลือกอีเวนต์ที่ต้องการ\n2. เลือกรูปภาพที่ชอบ\n3. เพิ่มลงตะกร้า\n4. ชำระเงิน (โอนเงิน/บัตรเครดิต)\n5. ดาวน์โหลดรูปภาพคุณภาพสูง\n\nมีคำถามเพิ่มเติมไหมครับ?",
                'type' => 'text',
            ];
        }

        // FAQ - Contact
        if (preg_match('/(ติดต่อ|contact|โทร|เบอร์|อีเมล|email|line)/u', $message)) {
            $email = AppSetting::get('company_email', 'support@photogallery.com');
            $phone = AppSetting::get('company_phone', '');
            $line = AppSetting::get('line_official_id', '');
            $text = "ช่องทางติดต่อ:\n• อีเมล: {$email}";
            if ($phone) {
                $text .= "\n• โทร: {$phone}";
            }
            if ($line) {
                $text .= "\n• LINE: @{$line}";
            }
            return ['text' => $text, 'type' => 'text'];
        }

        // FAQ - Refund
        if (preg_match('/(คืนเงิน|refund|เงินคืน)/u', $message)) {
            return [
                'text' => "นโยบายคืนเงิน:\n• สามารถขอคืนเงินได้ภายใน 7 วันหลังชำระเงิน\n• กรณีรูปภาพมีปัญหา สามารถแจ้งได้ทันที\n• ติดต่อทีมงานเพื่อดำเนินการคืนเงิน",
                'type' => 'text',
            ];
        }

        // Default - don't understand
        return [
            'text' => "ขอโทษครับ ผมไม่เข้าใจคำถามนี้\n\nลองถามเกี่ยวกับ:\n• ค้นหาอีเวนต์\n• ตรวจสอบสถานะออร์เดอร์\n• ราคารูปภาพ\n• วิธีซื้อรูปภาพ\n• ช่องทางติดต่อ\n• นโยบายคืนเงิน",
            'type' => 'text',
        ];
    }
}

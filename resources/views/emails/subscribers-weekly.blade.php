{{--
    Weekly subscriber digest — anonymous newsletter list.

    Receives:
      $subscriber     — Subscriber model (used for greeting + unsubscribe)
      $content        — ['events' => Collection, 'promotions' => Collection, 'tip' => array, 'has_content' => bool]
      $unsubscribe    — full URL to /newsletter/unsubscribe?email=...
      $weekLabel      — "สัปดาห์ที่ 23 · 2026"
      $siteName       — config('app.name')
      $siteUrl        — url('/')

    Inline CSS only — Gmail / Outlook strip <style> in <head>.
--}}
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $weekLabel }} — {{ $siteName }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Sarabun',sans-serif;color:#0f172a;">
<div style="max-width:600px;margin:0 auto;background:#fff;">

    {{-- ═══════════ HEADER ═══════════ --}}
    <div style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#ec4899 100%);padding:32px 24px;color:#fff;text-align:center;">
        <p style="margin:0 0 6px;font-size:11px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;opacity:.85;">
            📬 จดหมายข่าวประจำสัปดาห์
        </p>
        <h1 style="margin:0 0 6px;font-size:24px;font-weight:800;line-height:1.25;">
            {{ $weekLabel }}
        </h1>
        <p style="margin:0;opacity:.9;font-size:13px;">
            สรุปอีเวนต์ใหม่ + โปรโมชั่น + เคล็ดลับสำหรับสัปดาห์นี้
        </p>
    </div>

    {{-- Personal greeting (avoids "Dear Customer" — uses name if we have it) --}}
    <div style="padding:20px 24px 0;">
        <p style="margin:0;font-size:14px;color:#475569;">
            สวัสดี {{ $subscriber->name ?: 'คุณ' }} 👋 — เลือกอ่านส่วนที่สนใจได้เลย
        </p>
    </div>

    {{-- ═══════════ NEW EVENTS ═══════════ --}}
    @if($content['events']->isNotEmpty())
        <div style="padding:24px;border-bottom:1px solid #e2e8f0;">
            <h2 style="margin:0 0 16px;font-size:18px;color:#1e293b;font-weight:700;">
                📸 อีเวนต์ใหม่สัปดาห์นี้
                <span style="font-size:12px;font-weight:500;color:#64748b;">({{ $content['events']->count() }} รายการ)</span>
            </h2>
            @foreach($content['events'] as $e)
                @php
                    $coverUrl = $e->cover_image_url;
                    $eventUrl = url('/events/' . ($e->slug ?: $e->id));
                @endphp
                <a href="{{ $eventUrl }}"
                   style="display:block;padding:12px;border:1px solid #e2e8f0;border-radius:12px;margin-bottom:10px;text-decoration:none;color:inherit;">
                    <table cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            @if($coverUrl)
                                <td width="64" style="vertical-align:top;padding-right:12px;">
                                    <img src="{{ $coverUrl }}" alt="" width="64" height="64"
                                         style="display:block;border-radius:10px;background:#f1f5f9;object-fit:cover;width:64px;height:64px;">
                                </td>
                            @endif
                            <td style="vertical-align:top;">
                                <div style="font-size:14px;font-weight:600;color:#0f172a;margin:0 0 4px;line-height:1.35;">
                                    {{ $e->name }}
                                </div>
                                @if($e->shoot_date)
                                    <p style="margin:0;font-size:12px;color:#64748b;">
                                        📅 ถ่าย: {{ \Carbon\Carbon::parse($e->shoot_date)->format('d M Y') }}
                                    </p>
                                @endif
                                <p style="margin:6px 0 0;font-size:12px;color:#6366f1;font-weight:600;">
                                    ดูภาพในงาน →
                                </p>
                            </td>
                        </tr>
                    </table>
                </a>
            @endforeach
            <div style="text-align:center;margin-top:14px;">
                <a href="{{ url('/events') }}"
                   style="display:inline-block;padding:10px 22px;background:#6366f1;color:#fff;border-radius:10px;text-decoration:none;font-size:13px;font-weight:600;">
                    ดูอีเวนต์ทั้งหมด
                </a>
            </div>
        </div>
    @endif

    {{-- ═══════════ PROMOTIONS ═══════════ --}}
    @if($content['promotions']->isNotEmpty())
        <div style="padding:24px;border-bottom:1px solid #e2e8f0;background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%);">
            <h2 style="margin:0 0 16px;font-size:18px;color:#854d0e;font-weight:700;">
                🎁 โปรโมชั่นที่ใช้ได้ตอนนี้
            </h2>
            @foreach($content['promotions'] as $promo)
                @php
                    $valueLabel = $promo->type === 'percent'
                        ? rtrim(rtrim(number_format((float) $promo->value, 2), '0'), '.') . '%'
                        : '฿' . number_format((float) $promo->value, 0);
                    $endsLabel = $promo->end_date
                        ? 'หมดเขต ' . \Carbon\Carbon::parse($promo->end_date)->format('d M Y')
                        : null;
                @endphp
                <div style="background:#fff;border:1.5px dashed #d97706;border-radius:12px;padding:14px 16px;margin-bottom:10px;">
                    <div style="font-size:11px;font-weight:700;color:#92400e;letter-spacing:.06em;text-transform:uppercase;margin:0 0 4px;">
                        ลด {{ $valueLabel }}@if((float)$promo->min_order > 0) · ขั้นต่ำ ฿{{ number_format((float) $promo->min_order, 0) }}@endif
                    </div>
                    <div style="font-size:15px;font-weight:700;color:#0f172a;margin:0 0 6px;">
                        {{ $promo->name ?: $promo->description ?: 'โปรโมชั่นพิเศษ' }}
                    </div>
                    <div style="display:inline-block;font-family:'Courier New',monospace;background:#0f172a;color:#fde68a;padding:6px 12px;border-radius:6px;font-size:14px;font-weight:700;letter-spacing:.05em;">
                        {{ $promo->code }}
                    </div>
                    @if($endsLabel)
                        <p style="margin:8px 0 0;font-size:11px;color:#92400e;">⏰ {{ $endsLabel }}</p>
                    @endif
                </div>
            @endforeach
            <p style="margin:10px 0 0;font-size:11px;color:#78350f;text-align:center;">
                ใช้รหัสเมื่อชำระเงิน — ใช้ได้ครั้งเดียวต่อบัญชี เว้นแต่ระบุไว้ในรายละเอียด
            </p>
        </div>
    @endif

    {{-- ═══════════ TIP OF THE WEEK ═══════════ --}}
    @if(!empty($content['tip']))
        <div style="padding:24px;border-bottom:1px solid #e2e8f0;">
            <h2 style="margin:0 0 12px;font-size:18px;color:#1e293b;font-weight:700;">
                💡 เคล็ดลับสัปดาห์นี้
            </h2>
            <div style="background:#eef2ff;border-left:4px solid #6366f1;border-radius:8px;padding:14px 18px;">
                <div style="font-size:15px;font-weight:700;color:#312e81;margin:0 0 6px;line-height:1.4;">
                    {{ $content['tip']['icon'] ?? '💡' }} {{ $content['tip']['title'] }}
                </div>
                <p style="margin:0;font-size:13px;color:#3730a3;line-height:1.65;">
                    {{ $content['tip']['body'] }}
                </p>
            </div>
        </div>
    @endif

    {{-- ═══════════ FOOTER ═══════════ --}}
    <div style="padding:20px 24px;background:#f8fafc;text-align:center;font-size:11px;color:#64748b;line-height:1.6;">
        <p style="margin:0 0 6px;">
            คุณได้รับอีเมลฉบับนี้เพราะลงทะเบียนรับข่าวสารผ่านเว็บไซต์ของเรา —
            เนื้อหาทั้งหมดมาจากตารางในระบบจริง ไม่มีการสร้างขึ้นเพื่อโฆษณา
        </p>
        <p style="margin:8px 0 0;">
            <a href="{{ $siteUrl }}" style="color:#6366f1;text-decoration:none;">เข้าเว็บไซต์</a>
            ·
            <a href="{{ $unsubscribe }}" style="color:#6366f1;text-decoration:none;">ยกเลิกการรับอีเมล</a>
        </p>
        <p style="margin:12px 0 0;color:#94a3b8;">
            &copy; {{ date('Y') }} {{ $siteName }}
        </p>
    </div>

</div>
</body>
</html>

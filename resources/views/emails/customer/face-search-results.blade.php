@extends('emails.layout', ['title' => 'พบภาพของคุณ', 'preheader' => 'ผลการค้นหาด้วยใบหน้าในอีเวนต์ของคุณ'])

@section('slot')
<h2>พบภาพของคุณแล้ว! 📸</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>เราพบภาพที่น่าจะเป็นคุณจำนวน <strong>{{ $photoCount }} ภาพ</strong> ในอีเวนต์ <strong>{{ $eventName }}</strong></p>

<div class="info-box highlight">
  <div class="info-row">
    <span class="label">อีเวนต์</span>
    <span class="value">{{ $eventName }}</span>
  </div>
  <div class="info-row">
    <span class="label">จำนวนภาพที่พบ</span>
    <span class="value">{{ $photoCount }} ภาพ</span>
  </div>
  <div class="info-row">
    <span class="label">วันที่ค้นหา</span>
    <span class="value">{{ $searchedAt }}</span>
  </div>
</div>

@if($eventUrl)
<div class="btn-wrap">
  <a href="{{ $eventUrl }}" class="btn">🖼️ ดูภาพทั้งหมดในอีเวนต์</a>
</div>
@endif

@if(!empty($matches))
<h3>🎯 ตัวอย่างภาพที่จับคู่ได้</h3>

<div class="info-box">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
    <tr>
      @foreach(array_slice($matches, 0, 6) as $i => $m)
        @if($i > 0 && $i % 3 === 0)
          </tr><tr>
        @endif
        <td width="33%" style="padding:6px; text-align:center; vertical-align:top;">
          @if(!empty($m['thumbnail_url']))
            <a href="{{ $m['view_url'] ?? $eventUrl }}" style="display:block;">
              <img src="{{ $m['thumbnail_url'] }}" alt="ภาพที่ {{ $i + 1 }}"
                   style="width:100%; max-width:160px; height:auto; border-radius:8px; border:1px solid #e9ecef;">
            </a>
          @endif
          @if(!empty($m['similarity']))
            <div style="font-size:11px; color:#6b7280; margin-top:4px;">
              ความคล้าย {{ number_format((float) $m['similarity'], 1) }}%
            </div>
          @endif
        </td>
      @endforeach
    </tr>
  </table>
</div>

@if($photoCount > 6)
<p style="text-align:center; font-size:13px; color:#6b7280;">
  และอีก <strong>{{ $photoCount - 6 }} ภาพ</strong> กดปุ่มด้านบนเพื่อดูทั้งหมด
</p>
@endif
@endif

<div class="alert-box warning">
  <p>💡 <strong>เคล็ดลับ:</strong> หากยังไม่พบภาพที่ต้องการ ลองอัพโหลดภาพเซลฟี่ใหม่ในมุมที่ชัดเจนกว่าเดิม</p>
</div>

<h3>🔐 ความเป็นส่วนตัวของคุณ</h3>

<div class="info-box">
  <p style="margin:4px 0;">✅ <strong>ไม่มีการจัดเก็บภาพเซลฟี่</strong> — เราลบเวกเตอร์ใบหน้าที่ใช้ค้นหาทันทีหลังการประมวลผล</p>
  <p style="margin:4px 0;">✅ <strong>ข้อมูลถูกส่งผ่าน HTTPS</strong> — เข้ารหัสตลอดเส้นทาง</p>
  <p style="margin:4px 0;">✅ <strong>สิทธิ์ของคุณตาม PDPA §26</strong> — <a href="{{ url('/legal/biometric-data-privacy') }}">อ่านนโยบาย</a></p>
</div>

<p style="font-size:13px;color:#6b7280;">
  📧 อีเมลนี้ส่งเพราะคุณติ๊กเลือกให้เราส่งผลการค้นหามาทางอีเมล<br>
  🔒 ลิงก์ภาพในอีเมลนี้เป็นลิงก์ส่วนตัว กรุณาอย่าแชร์ให้ผู้อื่น
</p>

<p>ขอบคุณที่ใช้บริการ <strong>{{ $siteName }}</strong>! ❤️</p>
@endsection

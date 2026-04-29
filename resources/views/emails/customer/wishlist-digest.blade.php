@extends('emails.layout', ['title' => 'อีเวนต์ใหม่ที่คุณน่าจะชอบ', 'preheader' => 'คัดสรรอีเวนต์ใหม่จากหมวดโปรดของคุณ'])

@section('slot')
<h2>อีเวนต์ใหม่ที่คุณน่าจะชอบ ✨</h2>

<p>สวัสดี <strong>{{ $name }}</strong>,</p>

<p>เราคัดสรรอีเวนต์ใหม่ที่เข้ากับหมวดหมู่ที่คุณสนใจในรายการโปรดของคุณ
มาดูกันว่ามีอะไรน่าสนใจบ้าง!</p>

@if(!empty($events))
  @foreach($events as $event)
    <div class="product-card" style="display:block;padding:16px;margin:14px 0;">
      <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          @if(!empty($event['cover']))
            <td width="90" valign="top" style="padding-right:14px;">
              <img src="{{ $event['cover'] }}" alt="{{ $event['name'] }}" class="product-thumb" style="width:80px;height:80px;border-radius:10px;object-fit:cover;">
            </td>
          @endif
          <td valign="top">
            <p class="product-name" style="margin:0 0 4px 0;font-weight:600;color:#111827;font-size:15px;">
              {{ $event['name'] }}
            </p>
            @if(!empty($event['category']))
              <span class="badge badge-purple" style="font-size:11px;">{{ $event['category'] }}</span>
            @endif
            <p class="product-meta" style="margin:6px 0 0 0;font-size:12px;color:#6b7280;">
              @if(!empty($event['shoot_date']))
                📅 {{ $event['shoot_date'] }}
              @endif
              @if(!empty($event['is_free']))
                &nbsp;·&nbsp;<span style="color:#16a34a;font-weight:600;">ฟรี</span>
              @elseif(!empty($event['price']))
                &nbsp;·&nbsp;<span style="color:#6366f1;font-weight:600;">฿{{ number_format($event['price'], 0) }}/ภาพ</span>
              @endif
            </p>
            <div style="margin-top:10px;">
              <a href="{{ $event['url'] }}" class="btn" style="padding:8px 18px;font-size:13px;">ดูอีเวนต์</a>
            </div>
          </td>
        </tr>
      </table>
    </div>
  @endforeach
@else
  <div class="alert-box">
    <p>ยังไม่มีอีเวนต์ใหม่ในหมวดโปรดของคุณในช่วงนี้ เราจะแจ้งให้ทราบทันทีที่มีของใหม่!</p>
  </div>
@endif

<div class="divider"></div>

<div class="btn-wrap">
  <a href="{{ $wishlistUrl ?? url('/wishlist') }}" class="btn btn-outline">ดูรายการโปรดของฉัน</a>
</div>

<p style="font-size:13px;color:#6b7280;margin-top:24px;">
  💡 <strong>ไม่อยากรับอีเมลแนะนำ?</strong> คุณสามารถ
  <a href="{{ $preferencesUrl ?? url('/profile/notification-preferences') }}" style="color:#6366f1;">ปรับการแจ้งเตือน</a>
  ได้ตลอดเวลา
</p>
@endsection

@extends('emails.layout', ['title' => $headline, 'preheader' => $message->shortBody])

@section('slot')
@php
    /**
     * Generic lifecycle email — receives a LifecycleMessage instance and
     * renders the canonical headline/body/bullets/CTA.
     *
     * One template for every event kind keeps the visual design
     * consistent (photographer learns the layout once, looks for the
     * same pieces in every email). The accent colour shifts by severity
     * so the reader can tell critical from informational at a glance.
     */
    $accent = match ($severity ?? 'info') {
        'critical' => '#dc2626',
        'warn'     => '#f59e0b',
        default    => '#4f46e5',
    };
@endphp

<div style="border-left:4px solid {{ $accent }};padding-left:16px;margin-bottom:18px;">
  <h2 style="margin:0 0 6px 0;color:#0f172a;">{{ $headline }}</h2>
</div>

<p>สวัสดีช่างภาพ <strong>{{ $name }}</strong>,</p>

<p>{!! nl2br(e($body)) !!}</p>

@if(!empty($bullets))
<div class="info-box">
  @foreach($bullets as $b)
    <div class="info-row">
      @php
        // Bullet format from formatter is "Label: Value" — split for
        // visual hierarchy. Falls back to single-cell if no colon.
        $parts = explode(':', $b, 2);
      @endphp
      @if(count($parts) === 2)
        <span class="label">{{ trim($parts[0]) }}</span>
        <span class="value">{{ trim($parts[1]) }}</span>
      @else
        <span class="value" style="font-weight:600;">{{ $b }}</span>
      @endif
    </div>
  @endforeach
</div>
@endif

@if(!empty($cta['url'] ?? '') && !empty($cta['label'] ?? ''))
<div style="margin:24px 0;text-align:center;">
  <a href="{{ $cta['url'] }}"
     style="display:inline-block;padding:12px 32px;border-radius:8px;
            background:{{ $accent }};color:#ffffff;font-weight:700;
            text-decoration:none;font-size:15px;">
    {{ $cta['label'] }} →
  </a>
</div>
@endif

<p style="margin-top:24px;color:#64748b;font-size:13px;">
  หากมีคำถามเพิ่มเติมเกี่ยวกับแผน/บริการเสริมของคุณ
  <a href="{{ url('/photographer/store/status') }}" style="color:{{ $accent }};">ดูสถานะแผน &amp; การใช้งาน</a>
  ได้เสมอ หรือติดต่อทีมสนับสนุน
</p>
@endsection

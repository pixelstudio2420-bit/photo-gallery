{{--
  Reusable page hero — matches auth-flow theme (indigo→purple→pink gradient).
  Usage:
    @include('photographer.partials.page-hero', [
      'icon'     => 'bi-calendar-event',
      'eyebrow'  => 'การทำงาน',
      'title'    => 'อีเวนต์ของฉัน',
      'subtitle' => 'จัดการอีเวนต์ทั้งหมด สร้างใหม่ได้ทันที',
      'actions'  => '<a href="..." class="pg-btn-primary"><i class="bi bi-plus-lg"></i> สร้างใหม่</a>',
    ])
--}}
@php
  $icon     = $icon     ?? null;
  $eyebrow  = $eyebrow  ?? null;
  $title    = $title    ?? '';
  $subtitle = $subtitle ?? null;
  $actions  = $actions  ?? null;
@endphp

<div class="pg-hero pg-anim">
  <div class="flex items-start gap-3 min-w-0 flex-1">
    @if($icon)
      <div class="pg-hero-icon"><i class="bi {{ $icon }}"></i></div>
    @endif
    <div class="min-w-0 flex-1">
      @if($eyebrow)
        <p class="pg-hero-eyebrow">
          <i class="bi bi-stars"></i>{{ $eyebrow }}
        </p>
      @endif
      <h1 class="pg-hero-title">{{ $title }}</h1>
      @if($subtitle)
        <p class="pg-hero-subtitle">{{ $subtitle }}</p>
      @endif
    </div>
  </div>
  @if($actions)
    <div class="pg-hero-actions">
      {!! $actions !!}
    </div>
  @endif
</div>

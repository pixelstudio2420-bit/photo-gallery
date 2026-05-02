{{--
  Shareable QR Card — used by photographer + admin event QR pages.

  Required props:
    $event       — Event model (uses ->name, ->shoot_date)
    $eventUrl    — public URL the QR encodes
    $qrUrl       — branded QR PNG endpoint (route('qr.branded', ...))
    $qrFallback  — unbranded fallback URL (used by <img> onerror)

  Optional props:
    $brandName   — defaults to siteName / config('app.name')
    $brandLogo   — pre-resolved logo URL (default: from $siteLogo
                   shared by ViewServiceProvider)

  The whole card is wrapped in #qr-card so html2canvas can target
  only the card (not the action buttons below it). The two outer
  buttons are positioned OUTSIDE that wrapper on purpose.
--}}
@php
  $_brandName = $brandName ?? ($siteName ?? config('app.name', 'Loadroop'));
  $_brandHost = preg_replace('/^www\./i', '', parse_url(config('app.url', 'https://loadroop.com'), PHP_URL_HOST) ?: 'loadroop.com');

  // Resolve logo to a URL once, with a graceful fallback so a broken
  // upload never leaves a blank header. Uses StorageManager::resolveUrl
  // because $siteLogo is a relative storage_key, not a URL.
  $_brandLogoUrl = null;
  $_logoKey = $brandLogo ?? ($siteLogo ?? null);
  if (!empty($_logoKey)) {
      try {
          $_brandLogoUrl = app(\App\Services\StorageManager::class)->resolveUrl($_logoKey);
      } catch (\Throwable) { /* fall through to icon mark */ }
  }
@endphp

<div class="pg-qr-stage">
  {{-- THE CARD — html2canvas captures everything from #qr-card down --}}
  <div id="qr-card" class="pg-qr-card">
    {{-- Header band: site brand + "scan me" eyebrow --}}
    <div class="pg-qr-card-header">
      <div class="pg-qr-brand">
        <span class="pg-qr-brand-mark">
          @if($_brandLogoUrl)
            <img src="{{ $_brandLogoUrl }}" alt="{{ $_brandName }}"
                 onerror="this.replaceWith(Object.assign(document.createElement('i'),{className:'bi bi-camera2'}));">
          @else
            <i class="bi bi-camera2"></i>
          @endif
        </span>
        <span>{{ $_brandName }}</span>
      </div>
      <div class="pg-qr-brand-tag">
        <i class="bi bi-qr-code-scan"></i>&nbsp;Scan to view photos
      </div>
    </div>

    {{-- Body: framed QR + event meta --}}
    <div class="pg-qr-card-body">
      <div class="pg-qr-frame">
        <span class="pg-qr-frame-corner tl"></span>
        <span class="pg-qr-frame-corner tr"></span>
        <span class="pg-qr-frame-corner bl"></span>
        <span class="pg-qr-frame-corner br"></span>
        <img id="qr-image"
             src="{{ $qrUrl }}"
             data-fallback="{{ $qrFallback ?? '' }}"
             alt="QR Code — {{ $event->name }}"
             crossorigin="anonymous"
             onerror="if(!this.dataset.triedFallback && this.dataset.fallback){this.dataset.triedFallback='1';this.src=this.dataset.fallback;}">
      </div>

      <div class="pg-qr-meta">
        @if($event->shoot_date)
          <span class="pg-qr-eyebrow">
            <i class="bi bi-calendar3"></i>
            {{ \Carbon\Carbon::parse($event->shoot_date)->format('d M Y') }}
          </span>
        @endif
        <h2 class="pg-qr-event-name">{{ $event->name }}</h2>
        <p class="pg-qr-cta">สแกนเพื่อดูและสั่งซื้อรูปภาพของคุณ</p>
      </div>
    </div>

    {{-- Footer ribbon: brand domain --}}
    <div class="pg-qr-card-footer">
      <i class="bi bi-globe2"></i>
      <span>{{ $_brandHost }}</span>
    </div>
  </div>

  {{-- URL chip (NOT inside card so it's not in the saved image) --}}
  <div class="pg-qr-url-chip no-print" title="{{ $eventUrl }}">
    <i class="bi bi-link-45deg"></i>
    <code id="qr-event-url">{{ $eventUrl }}</code>
    <button type="button" onclick="
      navigator.clipboard.writeText('{{ $eventUrl }}');
      this.innerHTML='<i class=&quot;bi bi-check2&quot;></i>';
      setTimeout(()=>{ this.innerHTML='<i class=&quot;bi bi-clipboard&quot;></i>'; }, 1500);
    " title="คัดลอก URL"><i class="bi bi-clipboard"></i></button>
  </div>

  {{-- Action row (NOT inside card) --}}
  <div class="pg-qr-actions no-print">
    <button type="button" id="qr-save-btn" class="pg-qr-action-btn is-primary"
            onclick="saveQrCard(this)">
      <i class="bi bi-download"></i>
      <span>บันทึกการ์ด</span>
    </button>
    <button type="button" class="pg-qr-action-btn is-ghost" onclick="window.print()">
      <i class="bi bi-printer"></i>
      <span>พิมพ์</span>
    </button>
    <a href="{{ $eventUrl }}" target="_blank" rel="noopener"
       class="pg-qr-action-btn is-ghost">
      <i class="bi bi-box-arrow-up-right"></i>
      <span>เปิดหน้าอีเวนต์</span>
    </a>
  </div>
</div>

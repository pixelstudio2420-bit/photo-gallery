@extends('layouts.photographer')

@section('title', 'QR Code — ' . $event->name)

@push('styles')
<style>
  @media print {
    .no-print { display: none !important; }
    body { background: #fff !important; }
  }
</style>
@endpush

@section('content')
<div class="no-print">
  @include('photographer.partials.page-hero', [
    'icon'     => 'bi-qr-code',
    'eyebrow'  => 'การทำงาน',
    'title'    => 'QR Code อีเวนต์',
    'subtitle' => 'พิมพ์ QR Code นี้แล้วติดที่งาน — ลูกค้าสแกนเพื่อดูรูปและซื้อ',
    'actions'  => '<a href="'.route('photographer.events.index').'" class="pg-btn-ghost"><i class="bi bi-arrow-left"></i> กลับ</a>',
  ])
</div>

<div class="flex justify-center">
  <div class="w-full max-w-md">
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 text-center">
      <div class="p-8">

        {{-- Event Name --}}
        <h5 class="font-bold text-lg mb-1">{{ $event->name }}</h5>
        @if($event->shoot_date)
          <p class="text-gray-500 mb-6 text-sm">
            <i class="bi bi-calendar3 mr-1"></i>
            {{ \Carbon\Carbon::parse($event->shoot_date)->format('d/m/Y') }}
          </p>
        @else
          <div class="mb-6"></div>
        @endif

        {{-- QR Code Image --}}
        {{-- Google Chart API (chart.googleapis.com) was shut down on 14 Mar 2019.
             We now use api.qrserver.com as the primary provider, with
             quickchart.io as a client-side fallback on load error. --}}
        @php
          $eventUrl = route('events.show', $event->slug ?: $event->id);
          $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'size'   => '300x300',
            'data'   => $eventUrl,
            'ecc'    => 'M',
            'margin' => '10',
            'format' => 'png',
          ]);
          $qrUrlFallback = 'https://quickchart.io/qr?' . http_build_query([
            'text'    => $eventUrl,
            'size'    => 300,
            'ecLevel' => 'M',
            'margin'  => 2,
          ]);
        @endphp

        <div class="flex justify-center mb-6">
          <div class="p-3 rounded-xl border-2 border-gray-200 inline-block bg-white">
            <img id="qr-image"
               src="{{ $qrUrl }}"
               data-fallback="{{ $qrUrlFallback }}"
               alt="QR Code สำหรับ {{ $event->name }}"
               width="300"
               height="300"
               class="block rounded"
               onerror="if(!this.dataset.triedFallback){this.dataset.triedFallback='1';this.src=this.dataset.fallback;}else{this.style.display='none';document.getElementById('qr-fallback').style.display='flex';}">
            <div id="qr-fallback" style="display:none;width:300px;height:300px;" class="items-center justify-center flex-col text-gray-400">
              <i class="bi bi-qr-code text-5xl"></i>
              <small class="mt-2">ไม่สามารถโหลด QR Code</small>
            </div>
          </div>
        </div>

        {{-- Event URL --}}
        <p class="text-gray-500 mb-1 text-xs uppercase tracking-wider font-medium">ลิงก์อีเวนต์</p>
        <div class="flex items-center justify-center gap-2 mb-6">
          <code class="text-sm px-3 py-2 rounded-lg break-all" style="background:rgba(99,102,241,0.06);color:#4f46e5;">
            {{ $eventUrl }}
          </code>
        </div>

        {{-- Instructions --}}
        <div class="py-3 px-4 rounded-xl mb-6" style="background:rgba(99,102,241,0.05);border:1px solid rgba(99,102,241,0.1);">
          <p class="text-sm" style="color:#4f46e5;">
            <i class="bi bi-info-circle mr-1"></i>
            แสดง QR Code นี้ให้ผู้เข้าร่วมงานสแกนเพื่อดูภาพถ่าย
          </p>
        </div>

        {{-- Action Buttons --}}
        <div class="flex gap-2 justify-center flex-wrap no-print">
          <button onclick="window.print()" class="font-medium px-4 py-2 rounded-lg transition inline-flex items-center gap-1" style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;">
            <i class="bi bi-printer mr-1"></i>พิมพ์
          </button>

          <a id="download-btn" href="{{ $qrUrl }}" download="qrcode-{{ Str::slug($event->name) }}.png"
            class="font-medium px-4 py-2 rounded-lg transition inline-flex items-center gap-1"
            style="background:rgba(99,102,241,0.1);color:#6366f1;"
            onclick="downloadQR(event, this)">
            <i class="bi bi-download mr-1"></i>บันทึก QR Code
          </a>

          <a href="{{ $eventUrl }}" target="_blank" class="font-medium px-4 py-2 rounded-lg transition inline-flex items-center gap-1" style="background:rgba(16,185,129,0.1);color:#10b981;">
            <i class="bi bi-box-arrow-up-right mr-1"></i>เปิดหน้าอีเวนต์
          </a>
        </div>

      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
function downloadQR(e, link) {
  e.preventDefault();
  const qrSrc = document.getElementById('qr-image').src;
  const canvas = document.createElement('canvas');
  canvas.width = 300;
  canvas.height = 300;
  const ctx = canvas.getContext('2d');
  const img = new Image();
  img.crossOrigin = 'anonymous';
  img.onload = function() {
    ctx.drawImage(img, 0, 0);
    const a = document.createElement('a');
    a.href   = canvas.toDataURL('image/png');
    a.download = link.getAttribute('download') || 'qrcode.png';
    a.click();
  };
  img.onerror = function() {
    // Fallback: open in new tab for manual save
    window.open(qrSrc, '_blank');
  };
  img.src = qrSrc;
}
</script>
@endpush

@endsection

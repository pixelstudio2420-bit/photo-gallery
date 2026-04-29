@extends('layouts.admin')

@section('title', 'QR Code - ' . $event->name)

@push('styles')
<style>
  @media print {
    .no-print { display: none !important; }
    body { background: #fff !important; }
  }
</style>
@endpush

@section('content')
{{-- QR Code for admin event page.
     Previously displayed only a placeholder icon — now renders a real QR
     image via api.qrserver.com with quickchart.io as a client-side fallback. --}}
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

<div class="flex justify-between items-center mb-4 no-print">
  <h4 class="font-bold mb-0 tracking-tight">
    <i class="bi bi-qr-code mr-2 text-indigo-500"></i>QR Code
  </h4>
  <a href="{{ route('admin.events.index') }}" class="bg-indigo-50 text-indigo-600 rounded-lg font-medium px-5 py-2 inline-flex items-center gap-1 transition hover:bg-indigo-100">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

<div class="flex justify-center">
  <div class="w-full max-w-md">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 text-center">
      <div class="p-8">
        <h5 class="font-bold mb-1">{{ $event->name }}</h5>
        @if($event->shoot_date)
          <p class="text-gray-500 mb-5 text-sm">
            <i class="bi bi-calendar3 mr-1"></i>
            {{ \Carbon\Carbon::parse($event->shoot_date)->format('d/m/Y') }}
          </p>
        @else
          <div class="mb-5"></div>
        @endif

        <div class="flex justify-center mb-5">
          <div class="p-3 rounded-2xl border-2 border-gray-200 inline-block bg-white">
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

        <p class="text-gray-500 text-sm mb-2">ลิงก์สำหรับดูอีเวนต์</p>
        <div class="flex max-w-[400px] mx-auto mb-5">
          <input id="event-url" type="text" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-l-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" value="{{ $eventUrl }}" readonly>
          <button type="button" class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white px-4 py-2.5 rounded-r-lg transition hover:from-indigo-600 hover:to-indigo-700 whitespace-nowrap" onclick="navigator.clipboard.writeText(document.getElementById('event-url').value);this.innerHTML='<i class=\'bi bi-check\'></i> คัดลอกแล้ว';">
            <i class="bi bi-clipboard"></i> คัดลอก
          </button>
        </div>

        <div class="flex gap-2 justify-center flex-wrap no-print">
          <button onclick="window.print()" class="font-medium px-4 py-2 rounded-lg transition inline-flex items-center gap-1" style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;">
            <i class="bi bi-printer mr-1"></i>พิมพ์
          </button>

          <a href="{{ $qrUrl }}" download="qrcode-{{ \Illuminate\Support\Str::slug($event->name) }}.png"
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

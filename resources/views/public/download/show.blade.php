@extends('layouts.app')

@section('title', 'ดาวน์โหลดรูปภาพ')

@push('styles')
<style>
/* Download overlay */
.dl-overlay {
  position: fixed; inset: 0; z-index: 9999;
  background: rgba(15,23,42,0.75);
  backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; pointer-events: none;
  transition: opacity 0.3s ease;
}
.dl-overlay.active { opacity: 1; pointer-events: all; }
.dl-modal {
  background: #fff; border-radius: 20px;
  padding: 2.5rem 2rem; width: 90%; max-width: 420px;
  box-shadow: 0 25px 60px rgba(0,0,0,0.3);
  text-align: center;
  transform: translateY(20px) scale(0.96);
  transition: transform 0.35s cubic-bezier(0.16,1,0.3,1);
}
.dark .dl-modal { background: rgb(30 41 59); color: #fff; }
.dl-overlay.active .dl-modal { transform: translateY(0) scale(1); }
.dl-icon {
  width: 72px; height: 72px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 1.2rem; font-size: 1.8rem;
  transition: all 0.4s ease;
}
.dl-icon.loading { background: rgba(99,102,241,0.15); color: #6366f1; }
.dl-icon.success { background: rgba(16,185,129,0.15); color: #10b981; }
.dl-icon.error   { background: rgba(239,68,68,0.15); color: #ef4444; }
.dl-progress-bar.indeterminate {
  width: 100% !important;
  background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 50%, #6366f1 100%);
  background-size: 200% 100%;
  animation: dl-shimmer 1.5s infinite;
}
@keyframes dl-shimmer {
  0%  { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
@keyframes dl-spin { to { transform: rotate(360deg); } }
.dl-spinner {
  width: 14px; height: 14px;
  border: 2px solid currentColor; border-top-color: transparent;
  border-radius: 50%; animation: dl-spin 0.7s linear infinite;
  display: inline-block; vertical-align: middle;
}
</style>
@endpush

@section('content')
<div class="max-w-5xl mx-auto px-4 md:px-6 py-6">

  {{-- Breadcrumb --}}
  <nav class="mb-4 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 flex-wrap">
    <a href="{{ route('orders.index') }}" class="hover:text-indigo-500 transition">คำสั่งซื้อ</a>
    <i class="bi bi-chevron-right text-[10px]"></i>
    <span class="text-slate-700 dark:text-slate-300">ดาวน์โหลด</span>
  </nav>

  @if(session('error'))
    <div class="mb-4 p-4 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 text-rose-800 dark:text-rose-300 text-sm flex items-start gap-2">
      <i class="bi bi-exclamation-circle-fill mt-0.5"></i> {{ session('error') }}
    </div>
  @endif

  {{-- ═══════════════ TOKEN INFO CARD ═══════════════ --}}
  <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden mb-6">
    {{-- Status Banner --}}
    @if($isActive)
      <div class="px-5 py-2.5 bg-emerald-50 dark:bg-emerald-500/10 border-b border-emerald-200 dark:border-emerald-500/20 flex items-center gap-2">
        <i class="bi bi-check-circle-fill text-emerald-600 dark:text-emerald-400"></i>
        <span class="font-medium text-sm text-emerald-700 dark:text-emerald-300">ลิงก์ใช้งานได้</span>
      </div>
    @elseif($isExpired)
      <div class="px-5 py-2.5 bg-rose-50 dark:bg-rose-500/10 border-b border-rose-200 dark:border-rose-500/20 flex items-center gap-2">
        <i class="bi bi-x-circle-fill text-rose-600 dark:text-rose-400"></i>
        <span class="font-medium text-sm text-rose-700 dark:text-rose-300">ลิงก์หมดอายุแล้ว</span>
      </div>
    @else
      <div class="px-5 py-2.5 bg-amber-50 dark:bg-amber-500/10 border-b border-amber-200 dark:border-amber-500/20 flex items-center gap-2">
        <i class="bi bi-slash-circle-fill text-amber-600 dark:text-amber-400"></i>
        <span class="font-medium text-sm text-amber-700 dark:text-amber-300">ถึงจำนวนดาวน์โหลดสูงสุดแล้ว</span>
      </div>
    @endif

    <div class="p-5">
      <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex-1 min-w-0">
          <h1 class="text-lg md:text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-1">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md flex-shrink-0">
              <i class="bi bi-download text-sm"></i>
            </span>
            {{ $dl->order->event->name ?? 'รูปภาพ' }}
          </h1>
          <p class="text-sm text-slate-500 dark:text-slate-400">
            คำสั่งซื้อ <span class="font-mono text-slate-700 dark:text-slate-300">#{{ $dl->order->order_number ?? $dl->order_id }}</span>
            <span class="mx-1">·</span>
            {{ $tokenType === 'all' ? 'ดาวน์โหลดทั้งหมด' : 'ดาวน์โหลดรูปเดียว' }}
          </p>
        </div>
        @if($dl->expires_at)
        <div class="text-right flex-shrink-0">
          <div class="text-xs text-slate-500 dark:text-slate-400"><i class="bi bi-clock mr-1"></i>หมดอายุ</div>
          <div class="text-sm font-semibold {{ $isExpired ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white' }}">
            {{ $dl->expires_at->format('d/m/Y H:i') }}
          </div>
          @if(!$isExpired)
            <div class="text-xs text-slate-500 dark:text-slate-400">อีก {{ $dl->expires_at->diffForHumans() }}</div>
          @endif
        </div>
        @endif
      </div>

      {{-- Progress bar --}}
      @if($dl->max_downloads)
      <div class="mt-5">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-xs text-slate-500 dark:text-slate-400">จำนวนดาวน์โหลด</span>
          <span class="text-xs font-semibold text-slate-700 dark:text-slate-300" id="global-dl-count">
            {{ $dl->download_count }} / {{ $dl->max_downloads }} ครั้ง
            @if($remaining !== null && $isActive)
              <span class="text-slate-500 dark:text-slate-400 font-normal">(เหลือ {{ $remaining }})</span>
            @endif
          </span>
        </div>
        <div class="w-full h-2 bg-slate-100 dark:bg-white/10 rounded-full overflow-hidden">
          <div id="global-dl-bar"
               class="h-full rounded-full transition-all {{ $isActive ? 'bg-gradient-to-r from-indigo-500 to-purple-600' : 'bg-slate-400' }}"
               style="width:{{ $progress }}%;"></div>
        </div>
      </div>
      @endif

      {{-- Warnings --}}
      @if($isActive)
        @if($remaining !== null && $remaining <= 2 && $remaining > 0)
          <div class="mt-3 p-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 flex items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill text-amber-600 dark:text-amber-400"></i>
            <p class="text-xs text-amber-800 dark:text-amber-300">เหลือจำนวนดาวน์โหลดน้อยมาก กรุณาบันทึกไฟล์ให้ครบ</p>
          </div>
        @endif
        @if($dl->expires_at && $dl->expires_at->diffInHours() < 24)
          <div class="mt-3 p-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 flex items-center gap-2">
            <i class="bi bi-clock-fill text-rose-600 dark:text-rose-400"></i>
            <p class="text-xs text-rose-800 dark:text-rose-300">ลิงก์นี้จะหมดอายุเร็วๆ นี้ ดาวน์โหลดก่อนหมดเวลา</p>
          </div>
        @endif
      @endif
    </div>
  </div>

  {{-- ═══════════════ DOWNLOAD ITEMS ═══════════════ --}}
  <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3 flex items-center gap-2">
    <i class="bi bi-images text-indigo-500"></i> รายการรูปภาพ
    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-xs">{{ $items->count() }} รายการ</span>
  </h3>

  @if($items->isEmpty())
    <div class="text-center py-12 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm">
      <i class="bi bi-image text-4xl text-slate-300 dark:text-slate-600"></i>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-3">ไม่พบรายการรูปภาพ</p>
    </div>
  @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      @foreach($items as $item)
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden hover:shadow-md transition">
        {{-- Thumbnail --}}
        <div class="relative h-40 bg-slate-100 dark:bg-slate-900 overflow-hidden">
          @if($item->photo_id)
            <img src="https://drive.google.com/thumbnail?id={{ $item->photo_id }}&sz=w400"
                 alt="Photo"
                 class="w-full h-full object-cover"
                 loading="lazy"
                 onerror="this.onerror=null;this.src='{{ route('api.drive.image', $item->photo_id) }}?sz=400';">
          @else
            <div class="flex items-center justify-center h-full text-slate-400 dark:text-slate-600">
              <i class="bi bi-image text-4xl"></i>
            </div>
          @endif
        </div>

        <div class="p-3">
          <p class="font-semibold text-sm text-slate-900 dark:text-white mb-0.5">รูปภาพ #{{ $item->id }}</p>
          <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">ราคา: {{ number_format($item->price, 0) }} ฿</p>

          @php
            $pToken      = isset($photoTokens) && $item->photo_id ? ($photoTokens[$item->photo_id] ?? null) : null;
            $useToken    = $pToken ?? $dl;
            $pExpired    = $useToken->expires_at && $useToken->expires_at->isPast();
            $pLimit      = $useToken->max_downloads && $useToken->download_count >= $useToken->max_downloads;
            $pActive     = !$pExpired && !$pLimit;
            $pRemaining  = $useToken->max_downloads ? max(0, $useToken->max_downloads - $useToken->download_count) : null;
          @endphp

          @if($pActive)
            @php $photoNum = str_pad($loop->iteration, 3, '0', STR_PAD_LEFT); @endphp
            <button type="button"
                    class="dl-btn w-full py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white text-sm font-semibold shadow-sm transition flex items-center justify-center gap-1.5"
                    data-dl-url="{{ route('download.process', $useToken->token) }}"
                    data-photo-id="{{ $item->photo_id }}"
                    data-token="{{ $useToken->token }}"
                    data-remaining="{{ $pRemaining }}"
                    data-max="{{ $useToken->max_downloads }}"
                    data-type="single"
                    data-name="{{ $brandSlug }}_{{ $eventSlug }}_IMG-{{ $photoNum }}"
                    onclick="startDownload(this)">
              <i class="bi bi-download"></i> ดาวน์โหลด
              @if($pToken && $pToken->max_downloads)
                <span class="opacity-75 text-xs dl-counter">({{ $pRemaining }}/{{ $pToken->max_downloads }})</span>
              @endif
            </button>
          @else
            <button disabled
                    class="w-full py-2 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-400 dark:text-slate-500 text-sm font-medium cursor-not-allowed flex items-center justify-center gap-1.5">
              <i class="bi bi-x-circle"></i>
              {{ $pExpired ? 'หมดอายุ' : 'ครบจำนวนแล้ว' }}
            </button>
          @endif
        </div>
      </div>
      @endforeach
    </div>

    @if($tokenType === 'all' && $isActive && $items->count() > 1)
      <div class="mt-6 text-center">
        <button type="button"
                class="dl-btn inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-lg hover:shadow-xl transition-all"
                data-dl-url="{{ route('download.process', $dl->token) }}"
                data-photo-id=""
                data-token="{{ $dl->token }}"
                data-type="zip"
                data-name="{{ $brandSlug }}_{{ $eventSlug }}_{{ $items->count() }}-Photos"
                onclick="startDownload(this)">
          <i class="bi bi-file-earmark-zip"></i> ดาวน์โหลดทั้งหมด ({{ $items->count() }} รูป)
        </button>
      </div>
    @endif
  @endif

  {{-- Back --}}
  <div class="mt-8 text-center">
    @auth
      <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
        <i class="bi bi-arrow-left"></i> กลับไปยังคำสั่งซื้อ
      </a>
    @else
      <a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
        <i class="bi bi-house"></i> กลับหน้าแรก
      </a>
    @endauth
  </div>
</div>

{{-- Download Progress Overlay --}}
<div class="dl-overlay" id="dl-overlay">
  <div class="dl-modal">
    <div class="dl-icon loading" id="dl-icon">
      <i class="bi bi-download" id="dl-icon-i"></i>
    </div>
    <div class="text-lg font-bold text-slate-900 dark:text-white mb-1" id="dl-title">กำลังเตรียมไฟล์...</div>
    <div class="text-sm text-slate-500 dark:text-slate-400 mb-5" id="dl-subtitle">กรุณารอสักครู่</div>

    <div class="h-2.5 bg-slate-100 dark:bg-white/10 rounded-full overflow-hidden mb-2">
      <div class="dl-progress-bar indeterminate h-full bg-gradient-to-r from-indigo-500 to-purple-600 rounded-full transition-all" id="dl-progress" style="width:0%"></div>
    </div>
    <div class="flex justify-between text-xs text-slate-500 dark:text-slate-400 mb-4">
      <span id="dl-size">--</span>
      <span id="dl-percent">กำลังเชื่อมต่อ...</span>
    </div>

    <button id="dl-close" onclick="closeOverlay()" style="display:none;"
            class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold transition">
      ตกลง
    </button>
  </div>
</div>
@endsection

@push('scripts')
<script>
const CSRF = '{{ csrf_token() }}';

function formatBytes(bytes) {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function showOverlay() { document.getElementById('dl-overlay').classList.add('active'); }
function closeOverlay() {
  const o = document.getElementById('dl-overlay');
  o.classList.remove('active');
  setTimeout(() => {
    setOverlayState('loading', 'กำลังเตรียมไฟล์...', 'กรุณารอสักครู่');
    document.getElementById('dl-progress').style.width = '0%';
    document.getElementById('dl-progress').classList.add('indeterminate');
    document.getElementById('dl-size').textContent = '--';
    document.getElementById('dl-percent').textContent = 'กำลังเชื่อมต่อ...';
    document.getElementById('dl-close').style.display = 'none';
  }, 300);
}
function setOverlayState(state, title, subtitle) {
  const icon = document.getElementById('dl-icon');
  const iconI = document.getElementById('dl-icon-i');
  icon.className = 'dl-icon ' + state;
  iconI.className = state === 'success' ? 'bi bi-check-lg' : state === 'error' ? 'bi bi-exclamation-triangle' : 'bi bi-download';
  document.getElementById('dl-title').textContent = title;
  document.getElementById('dl-subtitle').textContent = subtitle;
}

async function startDownload(btn) {
  const url = btn.dataset.dlUrl;
  const photoId = btn.dataset.photoId;
  const isZip = btn.dataset.type === 'zip';
  const fileName = btn.dataset.name || 'download';

  const origHTML = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="dl-spinner"></span> กำลังโหลด...';

  showOverlay();
  if (isZip) setOverlayState('loading', 'กำลังสร้างไฟล์ ZIP...', 'รวมรูปภาพทั้งหมด อาจใช้เวลาสักครู่');
  else setOverlayState('loading', 'กำลังดาวน์โหลด...', 'เตรียมไฟล์รูปภาพ');

  try {
    const fd = new FormData();
    fd.append('_token', CSRF);
    if (photoId) fd.append('photo_id', photoId);
    // Signal to the server: "I'm an AJAX caller, hand me JSON when the file
    // lives off-origin so I can trigger the download via an <a> click instead
    // of chasing a 302 into a CORS wall."
    const res = await fetch(url, {
      method: 'POST',
      body: fd,
      headers: { 'Accept': 'application/json, */*', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!res.ok) {
      if (res.redirected || res.status === 302) throw new Error('ถึงจำนวนดาวน์โหลดสูงสุด หรือลิงก์หมดอายุ');
      const text = await res.text();
      if (text.includes('หมดอายุ') || text.includes('สูงสุด')) throw new Error('ถึงจำนวนดาวน์โหลดสูงสุด หรือลิงก์หมดอายุ');
      throw new Error('เกิดข้อผิดพลาด (' + res.status + ')');
    }
    const contentType = res.headers.get('Content-Type') || '';

    // JSON direct-download envelope — the server resolved a cross-origin
    // signed URL (R2/S3). Trigger the browser's own download flow so the
    // redirect chain + CORS isn't our problem.
    if (contentType.includes('application/json')) {
      const data = await res.json();
      if (data && data.direct && data.url) {
        const a = document.createElement('a');
        a.href = data.url;
        a.download = data.filename || fileName + '.jpg';
        // target=_blank keeps the current page alive if the browser decides
        // to open the image inline instead of downloading; rel protects us
        // from window.opener references.
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);

        const progressBar = document.getElementById('dl-progress');
        progressBar.classList.remove('indeterminate');
        progressBar.style.width = '100%';
        progressBar.style.background = 'linear-gradient(90deg, #10b981, #059669)';
        document.getElementById('dl-percent').textContent = '100%';
        document.getElementById('dl-size').textContent = data.filename || '';
        setOverlayState('success', 'เริ่มดาวน์โหลดแล้ว', data.filename || 'ไฟล์กำลังถูกบันทึกลงเครื่อง');
        document.getElementById('dl-close').style.display = 'inline-block';

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> ดาวน์โหลดแล้ว';
        btn.classList.remove('from-indigo-600', 'to-purple-600', 'hover:from-indigo-700', 'hover:to-purple-700');
        btn.classList.add('from-emerald-500', 'to-teal-500');
        const remaining = parseInt(btn.dataset.remaining || '0') - 1;
        const max = parseInt(btn.dataset.max || '0');
        if (remaining > 0) {
          setTimeout(() => {
            btn.classList.remove('from-emerald-500', 'to-teal-500');
            btn.classList.add('from-indigo-600', 'to-purple-600', 'hover:from-indigo-700', 'hover:to-purple-700');
            btn.dataset.remaining = remaining;
            btn.innerHTML = `<i class="bi bi-download"></i> ดาวน์โหลด <span class="opacity-75 text-xs dl-counter">(${remaining}/${max})</span>`;
          }, 3000);
        } else if (remaining <= 0 && max > 0) {
          setTimeout(() => { btn.disabled = true; btn.innerHTML = '<i class="bi bi-x-circle"></i> ครบจำนวนแล้ว'; }, 3000);
        }
        return;
      }
      throw new Error(data?.error || 'เกิดข้อผิดพลาดในการดาวน์โหลด');
    }

    if (contentType.includes('text/html')) {
      const html = await res.text();
      if (html.includes('หมดอายุ') || html.includes('สูงสุด')) throw new Error('ถึงจำนวนดาวน์โหลดสูงสุด หรือลิงก์หมดอายุ');
      throw new Error('เกิดข้อผิดพลาดในการดาวน์โหลด');
    }
    const total = parseInt(res.headers.get('Content-Length') || '0');
    const progressBar = document.getElementById('dl-progress');
    const sizeEl = document.getElementById('dl-size');
    const pctEl = document.getElementById('dl-percent');
    let loaded = 0;
    const reader = res.body.getReader();
    const chunks = [];
    if (total > 0) progressBar.classList.remove('indeterminate');
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      chunks.push(value);
      loaded += value.length;
      if (total > 0) {
        const pct = Math.round((loaded / total) * 100);
        progressBar.style.width = pct + '%';
        pctEl.textContent = pct + '%';
        sizeEl.textContent = formatBytes(loaded) + ' / ' + formatBytes(total);
        setOverlayState('loading', isZip ? 'กำลังดาวน์โหลด ZIP...' : 'กำลังดาวน์โหลด...', formatBytes(loaded) + ' จาก ' + formatBytes(total));
      } else {
        sizeEl.textContent = formatBytes(loaded);
        pctEl.textContent = 'กำลังดาวน์โหลด...';
      }
    }
    const blob = new Blob(chunks);
    const disposition = res.headers.get('Content-Disposition') || '';
    let saveName = isZip ? (fileName + '.zip') : (fileName + '.jpg');
    const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
    if (match && match[1]) saveName = match[1].replace(/['"]/g, '');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = saveName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(a.href);
    progressBar.classList.remove('indeterminate');
    progressBar.style.width = '100%';
    progressBar.style.background = 'linear-gradient(90deg, #10b981, #059669)';
    pctEl.textContent = '100%';
    sizeEl.textContent = formatBytes(loaded);
    setOverlayState('success', 'ดาวน์โหลดสำเร็จ!', saveName + ' (' + formatBytes(loaded) + ')');
    document.getElementById('dl-close').style.display = 'inline-block';
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> ดาวน์โหลดแล้ว';
    btn.classList.remove('from-indigo-600', 'to-purple-600', 'hover:from-indigo-700', 'hover:to-purple-700');
    btn.classList.add('from-emerald-500', 'to-teal-500');
    const remaining = parseInt(btn.dataset.remaining || '0') - 1;
    const max = parseInt(btn.dataset.max || '0');
    if (remaining > 0) {
      setTimeout(() => {
        btn.classList.remove('from-emerald-500', 'to-teal-500');
        btn.classList.add('from-indigo-600', 'to-purple-600', 'hover:from-indigo-700', 'hover:to-purple-700');
        btn.dataset.remaining = remaining;
        btn.innerHTML = `<i class="bi bi-download"></i> ดาวน์โหลด <span class="opacity-75 text-xs dl-counter">(${remaining}/${max})</span>`;
      }, 3000);
    } else if (remaining <= 0 && max > 0) {
      setTimeout(() => { btn.disabled = true; btn.innerHTML = '<i class="bi bi-x-circle"></i> ครบจำนวนแล้ว'; }, 3000);
    }
  } catch (err) {
    const progressBar = document.getElementById('dl-progress');
    progressBar.classList.remove('indeterminate');
    progressBar.style.width = '100%';
    progressBar.style.background = '#ef4444';
    document.getElementById('dl-percent').textContent = 'ล้มเหลว';
    setOverlayState('error', 'ดาวน์โหลดไม่สำเร็จ', err.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่');
    document.getElementById('dl-close').style.display = 'inline-block';
    btn.disabled = false;
    btn.innerHTML = origHTML;
  }
}
</script>
@endpush

@extends('layouts.admin')

@section('title', 'LINE Rich Menu — ตั้งค่าเมนูในแชท')

{{-- =======================================================================
     LINE OA RICH MENU MANAGER
     -------------------------------------------------------------------
     Lets admin deploy a 6-button storefront menu to LINE OA in one shot:
       1. Upload a 2500×1686 PNG/JPEG image
       2. Configure URLs + labels for each cell
       3. Click Deploy → service creates menu + uploads image + sets default
     ====================================================================== --}}

@push('styles')
<style>
  /* ── Hero panel ─────────────────────────────────────────────────── */
  .rm-hero {
    position: relative;
    border-radius: 28px; overflow: hidden;
    background: linear-gradient(135deg, #06C755 0%, #00b04f 50%, #047a45 100%);
    color: #fff;
    box-shadow: 0 20px 60px -20px rgba(6,199,85,0.45);
  }
  .dark .rm-hero {
    background: linear-gradient(135deg, #064e2c 0%, #065f3a 50%, #047a45 100%);
  }
  .rm-hero__pattern {
    position: absolute; inset: 0; pointer-events: none; opacity: 0.5;
    background-image:
      radial-gradient(circle at 20% 100%, rgba(255,255,255,0.18), transparent 45%),
      radial-gradient(circle at 90% 0%,  rgba(255,255,255,0.14), transparent 50%);
  }
  .rm-blob {
    position: absolute; border-radius: 50%; filter: blur(48px); pointer-events: none;
  }
  @keyframes rm-float {
    0%, 100% { transform: translate(0,0) scale(1); }
    50%      { transform: translate(30px,-20px) scale(1.05); }
  }
  .rm-blob-1 { width:300px; height:300px; background:radial-gradient(circle,rgba(255,255,255,0.4),transparent 70%); top:-100px; right:-60px; animation:rm-float 18s ease-in-out infinite alternate; }
  .rm-blob-2 { width:200px; height:200px; background:radial-gradient(circle,rgba(160,255,200,0.3),transparent 70%); bottom:-80px; left:30%; animation:rm-float 22s ease-in-out infinite alternate-reverse; }

  /* ── Card ───────────────────────────────────────────────────────── */
  .rm-card {
    background: #fff; border: 1px solid rgb(226 232 240);
    border-radius: 22px; overflow: hidden;
    box-shadow: 0 1px 3px rgba(15,23,42,0.04), 0 12px 28px rgba(15,23,42,0.05);
  }
  .dark .rm-card {
    background: rgb(15 23 42); border-color: rgba(255,255,255,0.08);
    box-shadow: 0 1px 3px rgba(0,0,0,0.4), 0 16px 36px rgba(0,0,0,0.25);
  }
  .rm-card::before {
    content: ''; position: absolute; left:0; top:0; right:0; height:4px;
    background: linear-gradient(90deg, #06C755, #00b04f);
  }
  .rm-card { position: relative; }

  /* ── Cell preview ───────────────────────────────────────────────── */
  .rm-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    grid-template-rows: repeat(2, 1fr);
    gap: 4px;
    aspect-ratio: 2500 / 1686;
    border-radius: 14px;
    overflow: hidden;
    background: #1f2937;
    padding: 4px;
  }
  .rm-cell {
    background: linear-gradient(135deg, #6366f1, #a855f7);
    border-radius: 10px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    color: #fff; padding: 0.75rem;
    text-align: center;
  }
  .rm-cell:nth-child(1) { background: linear-gradient(135deg, #06C755, #00b04f); }
  .rm-cell:nth-child(2) { background: linear-gradient(135deg, #6366f1, #7c3aed); }
  .rm-cell:nth-child(3) { background: linear-gradient(135deg, #ec4899, #db2777); }
  .rm-cell:nth-child(4) { background: linear-gradient(135deg, #f59e0b, #d97706); }
  .rm-cell:nth-child(5) { background: linear-gradient(135deg, #06b6d4, #0891b2); }
  .rm-cell:nth-child(6) { background: linear-gradient(135deg, #ef4444, #dc2626); }
  .rm-cell-icon { font-size: 1.5rem; margin-bottom: 0.4rem; }
  .rm-cell-label { font-size: 0.75rem; font-weight: 700; line-height: 1.1; }

  /* ── Status badge ───────────────────────────────────────────────── */
  .rm-status {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.4rem 0.85rem; border-radius: 9999px;
    font-size: 0.75rem; font-weight: 700;
    backdrop-filter: blur(8px);
  }
  .rm-status::before {
    content:''; width: 7px; height: 7px; border-radius:50%; background: currentColor;
    animation: pulse-rm 2s ease-in-out infinite;
  }
  @keyframes pulse-rm {
    0%,100% { opacity: 1; transform: scale(1); }
    50%     { opacity: 0.6; transform: scale(0.85); }
  }
  .rm-status.is-active   { background:rgba(255,255,255,0.20); color:#fff; }
  .rm-status.is-inactive { background:rgba(255,255,255,0.12); color:rgba(255,255,255,0.85); }

  /* ── Drop zone ──────────────────────────────────────────────────── */
  .rm-drop {
    border: 2px dashed rgb(203 213 225);
    border-radius: 16px;
    padding: 2rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    background: rgb(248 250 252);
  }
  .dark .rm-drop {
    border-color: rgba(255,255,255,0.15);
    background: rgba(255,255,255,0.03);
  }
  .rm-drop:hover {
    border-color: #06C755;
    background: rgba(6,199,85,0.05);
  }
  .rm-drop.is-active {
    border-color: #06C755;
    background: rgba(6,199,85,0.1);
  }

  /* ── Stagger fade-in ────────────────────────────────────────────── */
  @keyframes rm-fade {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .rm-anim { animation: rm-fade .5s cubic-bezier(0.34,1.56,0.64,1) both; }
  .rm-anim.d1 { animation-delay: .05s; }
  .rm-anim.d2 { animation-delay: .12s; }
  .rm-anim.d3 { animation-delay: .19s; }
  .rm-anim.d4 { animation-delay: .26s; }

  /* ── Sticky preview (lg+ only — keeps preview in view while filling form) ── */
  @media (min-width: 1024px) {
    .rm-sticky { position: sticky; top: 1.25rem; }
  }

  /* ── Cell input box (compact) ──────────────────────────────────── */
  .cell-box {
    border-radius: 12px; padding: 0.75rem;
    background: rgb(248 250 252);
    border: 1px solid rgb(226 232 240);
  }
  .dark .cell-box { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.08); }
  .cell-box:focus-within { border-color: #06C755; box-shadow: 0 0 0 3px rgba(6,199,85,0.10); }

  /* ── Compact text input ────────────────────────────────────────── */
  .ti {
    width: 100%; padding: 0.5rem 0.75rem; font-size: 0.8125rem;
    border-radius: 8px; border: 1px solid rgb(226 232 240);
    background: white; color: rgb(30 41 59);
    transition: border-color .15s, box-shadow .15s;
  }
  .dark .ti { background: rgb(30 41 59); border-color: rgba(255,255,255,0.10); color: rgb(241 245 249); }
  .ti:focus { outline: none; border-color: #06C755; box-shadow: 0 0 0 3px rgba(6,199,85,0.12); }
  .ti.is-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.7rem; }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- ════════════════════════════════════════════════════════════════════
       1. HERO PANEL — compact green LINE banner
       ════════════════════════════════════════════════════════════════════ --}}
  <div class="rm-hero rm-anim mb-5 px-5 md:px-7 py-5 md:py-6">
    <div class="rm-hero__pattern"></div>
    <div class="rm-blob rm-blob-1"></div>
    <div class="rm-blob rm-blob-2"></div>

    <div class="relative z-10 flex items-center justify-between gap-4 flex-wrap">
      <div class="flex items-center gap-3.5 min-w-0 flex-1">
        <div class="w-11 h-11 rounded-xl bg-white/20 backdrop-blur-md border border-white/30 flex items-center justify-center text-xl shrink-0 shadow-lg shadow-black/20">
          <i class="bi bi-list"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-[10px] uppercase tracking-[0.25em] text-white/75 font-bold mb-1 flex items-center gap-1.5">
            <span class="inline-block w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
            <span>LINE Messaging API · Rich Menu</span>
          </div>
          <h1 class="font-bold text-xl md:text-2xl tracking-tight leading-tight">
            <span class="bg-gradient-to-r from-yellow-100 via-white to-emerald-100 bg-clip-text text-transparent">เมนูในแชท</span> · 6 ปุ่ม
          </h1>
        </div>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        @if($configured)
          @if($defaultId)
            <span class="rm-status is-active">
              <i class="bi bi-check-circle-fill"></i> Default · {{ Str::limit($defaultId, 10) }}
            </span>
          @else
            <span class="rm-status is-inactive">
              <i class="bi bi-info-circle"></i> ยังไม่ได้ตั้ง default
            </span>
          @endif
        @else
          <span class="rm-status is-inactive">
            <i class="bi bi-exclamation-triangle"></i> Token ยังไม่ตั้ง
          </span>
        @endif
        <a href="{{ route('admin.settings.line') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold
                  bg-white/15 hover:bg-white/25 backdrop-blur-md border border-white/30 text-white transition">
          <i class="bi bi-arrow-left"></i> กลับ
        </a>
      </div>
    </div>
  </div>

  @if(session('success'))
    <div class="mb-5 rm-anim d1 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 flex items-start gap-2.5">
      <i class="bi bi-check-circle-fill text-lg shrink-0 mt-0.5"></i>
      <span class="text-sm">{{ session('success') }}</span>
    </div>
  @endif
  @if(session('error'))
    <div class="mb-5 rm-anim d1 p-4 rounded-2xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 flex items-start gap-2.5">
      <i class="bi bi-exclamation-circle-fill text-lg shrink-0 mt-0.5"></i>
      <span class="text-sm">{{ session('error') }}</span>
    </div>
  @endif
  @if($tokenHint)
    <div class="mb-5 rm-anim d1 p-4 rounded-2xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-amber-800 dark:text-amber-200 flex items-start gap-2.5">
      <i class="bi bi-info-circle-fill text-lg shrink-0 mt-0.5"></i>
      <span class="text-sm">{{ $tokenHint }}</span>
    </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-5 mb-5">
    {{-- ════════════════════════════════════════════════════════════════════
         2. PREVIEW PANEL — sticky on lg+, takes 5/12 of width (~42%)
         ════════════════════════════════════════════════════════════════════ --}}
    <div class="lg:col-span-5">
      <div class="rm-card rm-sticky rm-anim d2 p-4 lg:p-5">
        <div class="flex items-center gap-2 mb-3">
          <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-green-600 flex items-center justify-center shadow-md shadow-emerald-500/30 text-white text-sm">
            <i class="bi bi-eye"></i>
          </span>
          <div class="flex-1 min-w-0">
            <h3 class="font-bold text-sm text-slate-900 dark:text-white leading-tight">ตัวอย่างเมนู</h3>
            <p class="text-[10px] text-slate-500 dark:text-slate-400">2500 × 1686 px · 3 คอลัมน์ × 2 แถว</p>
          </div>
        </div>

        <div class="rm-grid mb-3">
          <div class="rm-cell"><div class="rm-cell-icon">📷</div><div class="rm-cell-label">อีเวนต์</div></div>
          <div class="rm-cell"><div class="rm-cell-icon">🛍️</div><div class="rm-cell-label">ออเดอร์</div></div>
          <div class="rm-cell"><div class="rm-cell-icon">🔍</div><div class="rm-cell-label">ค้นหาด้วยใบหน้า</div></div>
          <div class="rm-cell"><div class="rm-cell-icon">✨</div><div class="rm-cell-label">จุดเด่น</div></div>
          <div class="rm-cell"><div class="rm-cell-icon">❓</div><div class="rm-cell-label">ช่วยเหลือ</div></div>
          <div class="rm-cell"><div class="rm-cell-icon">📞</div><div class="rm-cell-label">ติดต่อเรา</div></div>
        </div>

        <div class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed bg-amber-50/60 dark:bg-amber-500/10 p-2.5 rounded-lg border border-amber-200/60 dark:border-amber-500/20 flex gap-2">
          <i class="bi bi-info-circle-fill text-amber-600 dark:text-amber-400 mt-0.5 shrink-0"></i>
          <span><strong class="text-amber-800 dark:text-amber-200">Preview เท่านั้น</strong> — Deploy ต้องอัปโหลดรูปจริงที่มีไอคอน/ข้อความ</span>
        </div>
      </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════
         3. DEPLOY FORM — takes 7/12 of width (~58%)
         ════════════════════════════════════════════════════════════════════ --}}
    <div class="rm-card rm-anim d3 p-4 lg:p-5 lg:col-span-7"
         x-data="{
           dropActive: false,
           fileName: '',
           imagePreview: '',
           fileSize: '',
           handleFile(e) {
             const f = e.target.files[0];
             if (!f) { this.fileName = ''; this.imagePreview = ''; return; }
             this.fileName = f.name;
             this.fileSize = (f.size / 1024).toFixed(1) + ' KB';
             const reader = new FileReader();
             reader.onload = ev => { this.imagePreview = ev.target.result; };
             reader.readAsDataURL(f);
           }
         }">
      <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-100 dark:border-white/5">
        <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-violet-500 to-fuchsia-600 flex items-center justify-center shadow-md shadow-violet-500/30 text-white text-sm">
          <i class="bi bi-rocket-takeoff"></i>
        </span>
        <div class="flex-1 min-w-0">
          <h3 class="font-bold text-sm text-slate-900 dark:text-white leading-tight">Deploy Rich Menu</h3>
          <p class="text-[10px] text-slate-500 dark:text-slate-400">อัปโหลดรูป → ตั้งค่าปุ่ม → Deploy ทันที</p>
        </div>
      </div>

      <form action="{{ route('admin.settings.line-richmenu.deploy') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf

        {{-- Image upload (compact) --}}
        <div>
          <label class="flex items-center justify-between mb-1.5">
            <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">
              <i class="bi bi-image"></i> รูปภาพเมนู
            </span>
            <span class="text-[10px] uppercase tracking-wider text-rose-600 dark:text-rose-400 font-bold">จำเป็น</span>
          </label>
          <label :class="dropActive ? 'rm-drop is-active' : 'rm-drop'"
                 @dragover.prevent="dropActive = true"
                 @dragleave.prevent="dropActive = false"
                 @drop.prevent="dropActive = false; $refs.imgInput.files = $event.dataTransfer.files; handleFile({target: $refs.imgInput})"
                 style="padding: 1.25rem 1rem;">
            <input type="file" name="image" accept="image/png,image/jpeg" required
                   class="hidden" x-ref="imgInput" @change="handleFile">
            <template x-if="!fileName">
              <div>
                <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-gradient-to-br from-emerald-500 to-green-600 flex items-center justify-center text-white text-lg shadow-md shadow-emerald-500/30">
                  <i class="bi bi-cloud-upload"></i>
                </div>
                <div class="font-semibold text-slate-700 dark:text-slate-200 text-xs mb-0.5">คลิกหรือลากไฟล์มาวาง</div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400">PNG / JPEG · 2500×1686 · ≤ 1 MB</div>
              </div>
            </template>
            <template x-if="fileName">
              <div>
                <img :src="imagePreview" class="max-h-32 mx-auto rounded-lg shadow-md mb-2" alt="preview">
                <div class="font-semibold text-slate-700 dark:text-slate-200 text-xs" x-text="fileName"></div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400" x-text="fileSize"></div>
                <div class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-0.5">คลิกเพื่อเปลี่ยนรูป</div>
              </div>
            </template>
          </label>
          @error('image')
            <div class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</div>
          @enderror
        </div>

        {{-- Menu metadata (2-col) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-200 mb-1">ชื่อเมนู (ภายใน)</label>
            <input type="text" name="menu_name" maxlength="300"
                   placeholder="Storefront Menu — {{ now()->format('Y-m-d') }}"
                   value="{{ old('menu_name') }}"
                   class="ti">
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-200 mb-1">Chat Bar Text <span class="text-slate-400">(≤14 ตัว)</span></label>
            <input type="text" name="chat_bar_text" maxlength="14"
                   placeholder="เมนู"
                   value="{{ old('chat_bar_text', 'เมนู') }}"
                   class="ti">
          </div>
        </div>

        {{-- 6 cells: 2 cols on sm/md, 3 cols on xl --}}
        <div>
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-xs font-bold text-slate-700 dark:text-slate-200">
              <i class="bi bi-link-45deg"></i> ปลายทาง 6 ปุ่ม
            </h4>
            <span class="text-[10px] text-slate-500 dark:text-slate-400">เว้นว่างไว้ = ใช้ค่า default</span>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2.5">
            @php
              $cells = [
                ['key' => 'events',  'icon' => 'bi-calendar-event',     'color' => 'emerald', 'label' => '📷 อีเวนต์',         'url' => url('/events')],
                ['key' => 'orders',  'icon' => 'bi-bag-check',          'color' => 'indigo',  'label' => '🛍️ ออเดอร์',         'url' => url('/orders')],
                ['key' => 'face',    'icon' => 'bi-person-bounding-box','color' => 'pink',    'label' => '🔍 ค้นหาด้วยใบหน้า', 'url' => url('/events?face_search=1')],
                ['key' => 'promo',   'icon' => 'bi-stars',              'color' => 'amber',   'label' => '✨ จุดเด่น',          'url' => url('/promo')],
                ['key' => 'help',    'icon' => 'bi-question-circle',    'color' => 'cyan',    'label' => '❓ ช่วยเหลือ',         'url' => url('/help')],
                ['key' => 'contact', 'icon' => 'bi-telephone',          'color' => 'rose',    'label' => '📞 ติดต่อเรา',        'url' => url('/contact')],
              ];
            @endphp
            @foreach($cells as $c)
              <div class="cell-box">
                <div class="flex items-center gap-1.5 mb-1.5">
                  <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] text-white shadow-sm
                               bg-gradient-to-br from-{{ $c['color'] }}-500 to-{{ $c['color'] }}-600">
                    <i class="bi {{ $c['icon'] }}"></i>
                  </span>
                  <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200">Cell {{ $loop->iteration }}</span>
                </div>
                <input type="text" name="label_{{ $c['key'] }}" maxlength="20"
                       placeholder="{{ $c['label'] }}"
                       value="{{ old('label_'.$c['key'], $c['label']) }}"
                       class="ti mb-1.5">
                <input type="url" name="url_{{ $c['key'] }}"
                       placeholder="{{ $c['url'] }}"
                       value="{{ old('url_'.$c['key'], $c['url']) }}"
                       class="ti is-mono">
              </div>
            @endforeach
          </div>
        </div>

        {{-- Set as default + submit row (combined for compactness) --}}
        <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3 items-stretch pt-1">
          <label class="flex items-center gap-2 px-3.5 py-2.5 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 cursor-pointer hover:bg-emerald-100 dark:hover:bg-emerald-500/15 transition">
            <input type="checkbox" name="set_as_default" value="1" checked
                   class="w-4 h-4 text-emerald-600 rounded shrink-0">
            <div class="flex-1 min-w-0">
              <div class="text-xs font-semibold text-emerald-800 dark:text-emerald-200 leading-tight">ตั้งเป็น default ทันที</div>
              <div class="text-[10px] text-emerald-700 dark:text-emerald-300/80 leading-tight mt-0.5">ทุก follower เห็นเมนูนี้หลัง deploy</div>
            </div>
          </label>
          <button type="submit"
                  @if(!$configured) disabled @endif
                  class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 rounded-lg text-sm font-bold text-white whitespace-nowrap transition
                         bg-gradient-to-br from-emerald-500 via-green-600 to-teal-600
                         shadow-md shadow-emerald-500/40
                         hover:shadow-lg hover:shadow-emerald-500/50 hover:-translate-y-0.5
                         active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed">
            <i class="bi bi-rocket-takeoff-fill"></i> Deploy
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════════════════
       4. EXISTING MENUS LIST — compact
       ════════════════════════════════════════════════════════════════════ --}}
  <div class="rm-card rm-anim d4 p-4 lg:p-5">
    <div class="flex items-center justify-between mb-3 pb-3 border-b border-slate-100 dark:border-white/5 flex-wrap gap-2">
      <div class="flex items-center gap-2">
        <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-slate-500 to-slate-700 flex items-center justify-center shadow-md text-white text-sm">
          <i class="bi bi-collection"></i>
        </span>
        <div>
          <h3 class="font-bold text-sm text-slate-900 dark:text-white leading-tight">Rich Menu ที่มีอยู่</h3>
          <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ count($menus) }} รายการ</p>
        </div>
      </div>
      @if($configured && $defaultId)
        <form action="{{ route('admin.settings.line-richmenu.clear') }}" method="POST"
              onsubmit="return confirm('ยืนยันลบการตั้ง default? Chat bar ทุกคนจะกลับเป็น Tap me');">
          @csrf
          <button type="submit"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-medium
                         bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30
                         text-amber-700 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-500/15 transition">
            <i class="bi bi-x-circle"></i> Clear Default
          </button>
        </form>
      @endif
    </div>

    @if($listError)
      <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 text-xs">
        <i class="bi bi-exclamation-circle-fill"></i> {{ $listError }}
      </div>
    @elseif(empty($menus))
      <div class="py-6 rounded-lg bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-center">
        <i class="bi bi-inbox text-2xl text-slate-400 dark:text-slate-500 mb-1 block"></i>
        <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">ยังไม่มี Rich Menu</div>
        <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">สร้างเมนูแรกจากฟอร์มด้านบน</div>
      </div>
    @else
      <div class="space-y-2">
        @foreach($menus as $menu)
          @php
            $isDefault = ($menu['richMenuId'] ?? null) === $defaultId;
            $size = $menu['size'] ?? [];
            $areas = $menu['areas'] ?? [];
          @endphp
          <div class="rounded-lg p-3 bg-slate-50 dark:bg-white/5 border {{ $isDefault ? 'border-emerald-300 dark:border-emerald-500/40 bg-emerald-50/50 dark:bg-emerald-500/5' : 'border-slate-200 dark:border-white/10' }}">
            <div class="flex items-center justify-between gap-3 flex-wrap">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                  <span class="font-semibold text-xs text-slate-900 dark:text-white truncate">{{ $menu['name'] ?? 'Untitled' }}</span>
                  @if($isDefault)
                    <span class="inline-flex items-center gap-1 px-1.5 py-0 rounded-full text-[9px] font-bold uppercase tracking-wide
                                 bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30">
                      <i class="bi bi-check-circle-fill text-[8px]"></i> Default
                    </span>
                  @endif
                </div>
                <div class="flex items-center gap-2.5 text-[10px] text-slate-500 dark:text-slate-400 flex-wrap">
                  <span class="font-mono">{{ Str::limit($menu['richMenuId'] ?? '-', 18) }}</span>
                  <span class="text-slate-300 dark:text-white/20">·</span>
                  <span><i class="bi bi-aspect-ratio"></i> {{ ($size['width'] ?? 0) }}×{{ ($size['height'] ?? 0) }}</span>
                  <span><i class="bi bi-grid-3x3"></i> {{ count($areas) }} cells</span>
                  <span class="hidden sm:inline truncate"><i class="bi bi-chat-square-text"></i> "{{ Str::limit($menu['chatBarText'] ?? '-', 12) }}"</span>
                </div>
              </div>
              <div class="flex items-center gap-1.5 shrink-0">
                @if(!$isDefault)
                  <form action="{{ route('admin.settings.line-richmenu.set-default') }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="id" value="{{ $menu['richMenuId'] }}">
                    <button type="submit"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-semibold
                                   bg-emerald-500 hover:bg-emerald-600 text-white transition">
                      <i class="bi bi-check-circle"></i> Set Default
                    </button>
                  </form>
                @endif
                <form action="{{ route('admin.settings.line-richmenu.delete') }}" method="POST" class="inline"
                      onsubmit="return confirm('ลบ Rich Menu นี้? การกระทำนี้ย้อนกลับไม่ได้');">
                  @csrf
                  <input type="hidden" name="id" value="{{ $menu['richMenuId'] }}">
                  <button type="submit" title="ลบ"
                          class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[11px]
                                 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30
                                 text-rose-700 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-500/15 transition">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</div>
@endsection

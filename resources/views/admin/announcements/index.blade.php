@extends('layouts.admin')

@section('title', 'ประกาศ / ข่าวสาร')

@section('content')
<div x-data="{ confirmDeleteId: null }">

  {{-- ═══════════════════════════════════════════════════════════════
       HEADER — matches the festival/products admin design language
       ═══════════════════════════════════════════════════════════════ --}}
  <div class="flex items-start justify-between mb-6 flex-wrap gap-3">
    <div class="flex items-center gap-3">
      <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center shadow-md shadow-indigo-500/30">
        <i class="bi bi-megaphone-fill text-xl"></i>
      </div>
      <div>
        <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">ประกาศและข่าวสาร</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400">จัดการ banner / popup / ข่าวสำหรับช่างภาพและลูกค้า</p>
      </div>
    </div>
    <a href="{{ route('admin.announcements.create') }}"
       class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white px-4 py-2 text-sm font-medium shadow-sm shadow-indigo-500/25 transition">
      <i class="bi bi-plus-lg"></i>
      <span>สร้างประกาศใหม่</span>
    </a>
  </div>

  {{-- Flash messages --}}
  @if(session('success'))
  <div class="mb-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 px-4 py-3 text-sm flex items-start justify-between gap-3">
    <div class="flex items-start gap-2"><i class="bi bi-check-circle-fill mt-0.5"></i><span>{{ session('success') }}</span></div>
    <button type="button" class="text-emerald-600/80 hover:text-emerald-700" onclick="this.parentElement.remove()"><i class="bi bi-x-lg"></i></button>
  </div>
  @endif
  @if(session('error'))
  <div class="mb-4 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 px-4 py-3 text-sm flex items-start justify-between gap-3">
    <div class="flex items-start gap-2"><i class="bi bi-exclamation-triangle-fill mt-0.5"></i><span>{{ session('error') }}</span></div>
    <button type="button" class="text-rose-600/80 hover:text-rose-700" onclick="this.parentElement.remove()"><i class="bi bi-x-lg"></i></button>
  </div>
  @endif

  {{-- ═══════════════════════════════════════════════════════════════
       STATS — 4 KPI cards mirroring the festival page
       ═══════════════════════════════════════════════════════════════ --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-slate-100 dark:bg-white/5 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-collection text-xl text-slate-500"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-slate-900 dark:text-slate-100">{{ number_format($stats['total']) }}</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">ทั้งหมด</div>
        </div>
      </div>
    </div>
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-broadcast text-xl text-emerald-500"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-emerald-600 dark:text-emerald-400">{{ number_format($stats['live']) }}</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">กำลังเผยแพร่</div>
        </div>
      </div>
    </div>
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-amber-500/10 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-pencil-square text-xl text-amber-500"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-amber-600 dark:text-amber-400">{{ number_format($stats['draft']) }}</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">ฉบับร่าง</div>
        </div>
      </div>
    </div>
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-slate-100 dark:bg-white/5 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-archive text-xl text-slate-500"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-slate-900 dark:text-slate-100">{{ number_format($stats['archived']) }}</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">เก็บแล้ว</div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════════
       FILTERS — single-row form with chips for current filters
       ═══════════════════════════════════════════════════════════════ --}}
  <form method="GET" class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm p-4 mb-4">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
      <div class="md:col-span-5">
        <div class="relative">
          <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
          <input type="text" name="search" value="{{ request('search') }}" placeholder="ค้นหาจากหัวข้อ / เกริ่นนำ / slug..."
                 class="w-full pl-10 pr-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
        </div>
      </div>
      <div class="md:col-span-3">
        <select name="audience" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
          <option value="">— ทุกกลุ่มเป้าหมาย —</option>
          <option value="all" @selected(request('audience')==='all')>ทั้งหมด (ช่าง + ลูกค้า)</option>
          <option value="photographer" @selected(request('audience')==='photographer')>เฉพาะช่างภาพ</option>
          <option value="customer" @selected(request('audience')==='customer')>เฉพาะลูกค้า</option>
        </select>
      </div>
      <div class="md:col-span-2">
        <select name="status" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
          <option value="">— ทุกสถานะ —</option>
          <option value="published" @selected(request('status')==='published')>เผยแพร่</option>
          <option value="draft"     @selected(request('status')==='draft')>ฉบับร่าง</option>
          <option value="archived"  @selected(request('status')==='archived')>เก็บแล้ว</option>
        </select>
      </div>
      <div class="md:col-span-2 flex gap-2">
        <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-2 text-sm font-medium shadow-sm transition">
          <i class="bi bi-funnel"></i> กรอง
        </button>
        @if(request()->anyFilled(['search','audience','status','only_trashed']))
        <a href="{{ route('admin.announcements.index') }}" title="ล้างตัวกรอง"
           class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 dark:bg-white/5 text-slate-600 hover:bg-slate-200 transition">
          <i class="bi bi-x-lg"></i>
        </a>
        @endif
      </div>
    </div>
    <label class="mt-3 inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400 cursor-pointer">
      <input type="checkbox" name="only_trashed" value="1" {{ request('only_trashed') ? 'checked' : '' }}
             onchange="this.form.submit()" class="rounded border-slate-300 text-rose-500 focus:ring-rose-500">
      <span>แสดงเฉพาะที่ลบแล้ว (soft-deleted)</span>
    </label>
  </form>

  {{-- ═══════════════════════════════════════════════════════════════
       LIST — card-based instead of table; matches site rhythm
       ═══════════════════════════════════════════════════════════════ --}}
  @if($announcements->isEmpty())
    <div class="rounded-2xl bg-white dark:bg-slate-900 border-2 border-dashed border-slate-200 dark:border-white/10 p-10 text-center">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100 dark:bg-white/5 mb-3">
        <i class="bi bi-megaphone text-3xl text-slate-400"></i>
      </div>
      <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-1">ยังไม่มีประกาศ</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">สร้างประกาศแรกเพื่อเริ่มแจ้งข่าวให้ผู้ใช้</p>
      <a href="{{ route('admin.announcements.create') }}"
         class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white px-4 py-2 text-sm font-medium shadow-sm transition">
        <i class="bi bi-plus-lg"></i> สร้างประกาศแรก
      </a>
    </div>
  @else
    <div class="space-y-3">
      @foreach($announcements as $a)
        @php
          $priorityMap = [
              'high'   => ['label' => 'สูง',  'class' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300', 'icon' => 'bi-arrow-up-circle-fill'],
              'low'    => ['label' => 'ต่ำ',   'class' => 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-400', 'icon' => 'bi-arrow-down-circle'],
              'normal' => ['label' => 'ปกติ', 'class' => 'bg-slate-100 text-slate-700 dark:bg-white/5 dark:text-slate-300', 'icon' => 'bi-circle'],
          ];
          $audMap = [
              'photographer' => ['label' => 'ช่างภาพ', 'class' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300', 'icon' => 'bi-camera'],
              'customer'     => ['label' => 'ลูกค้า',  'class' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/20 dark:text-cyan-300', 'icon' => 'bi-person'],
              'all'          => ['label' => 'ทั้งหมด', 'class' => 'bg-slate-100 text-slate-700 dark:bg-white/5 dark:text-slate-300', 'icon' => 'bi-people'],
          ];
          $p   = $priorityMap[$a->priority] ?? $priorityMap['normal'];
          $aud = $audMap[$a->audience] ?? $audMap['all'];
          $coverUrl = '';
          if (!empty($a->cover_image_path)) {
              try { $coverUrl = app(\App\Services\StorageManager::class)->resolveUrl($a->cover_image_path); } catch (\Throwable) {}
          }
        @endphp
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden
                    {{ $a->trashed() ? 'opacity-60' : '' }}
                    transition-all hover:shadow-md">
          <div class="p-4 flex items-start gap-4 flex-wrap md:flex-nowrap">
            {{-- Cover --}}
            <div class="relative shrink-0">
              @if($coverUrl)
                <img src="{{ $coverUrl }}" alt="" class="w-20 h-20 rounded-xl object-cover bg-slate-100 dark:bg-slate-800">
              @else
                <div class="w-20 h-20 rounded-xl bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-500/20 dark:to-purple-500/20 flex items-center justify-center">
                  <i class="bi bi-image text-2xl text-indigo-400 dark:text-indigo-300"></i>
                </div>
              @endif
              @if($a->is_pinned)
                <span title="ปักหมุด" class="absolute -top-1 -right-1 w-6 h-6 rounded-full bg-rose-500 text-white flex items-center justify-center shadow-md">
                  <i class="bi bi-pin-angle-fill text-xs"></i>
                </span>
              @endif
            </div>

            {{-- Body --}}
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap mb-1">
                <h3 class="font-semibold text-slate-900 dark:text-slate-100 truncate">{{ $a->title }}</h3>

                {{-- Status pill --}}
                @if($a->trashed())
                  <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300 px-2 py-0.5 text-[10px] font-semibold">
                    <i class="bi bi-trash-fill"></i> ลบแล้ว
                  </span>
                @elseif($a->status === 'published' && method_exists($a, 'isLive') && $a->isLive())
                  <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 px-2 py-0.5 text-[10px] font-semibold">
                    <span class="relative flex h-1.5 w-1.5">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                    </span>
                    LIVE
                  </span>
                @elseif($a->status === 'published')
                  <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 px-2 py-0.5 text-[10px] font-semibold">
                    <i class="bi bi-clock"></i> นอกช่วง
                  </span>
                @elseif($a->status === 'draft')
                  <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400 px-2 py-0.5 text-[10px] font-semibold">
                    <i class="bi bi-pencil"></i> ร่าง
                  </span>
                @else
                  <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-white/5 text-slate-600 dark:text-slate-400 px-2 py-0.5 text-[10px] font-semibold">
                    <i class="bi bi-archive"></i> เก็บแล้ว
                  </span>
                @endif

                {{-- Audience --}}
                <span class="inline-flex items-center gap-1 rounded-full {{ $aud['class'] }} px-2 py-0.5 text-[10px] font-semibold">
                  <i class="bi {{ $aud['icon'] }}"></i> {{ $aud['label'] }}
                </span>

                {{-- Priority --}}
                <span class="inline-flex items-center gap-1 rounded-full {{ $p['class'] }} px-2 py-0.5 text-[10px] font-semibold">
                  <i class="bi {{ $p['icon'] }}"></i> {{ $p['label'] }}
                </span>

                {{-- Popup flag --}}
                @if(!empty($a->show_as_popup))
                  <span class="inline-flex items-center gap-1 rounded-full bg-purple-100 dark:bg-purple-500/20 text-purple-700 dark:text-purple-300 px-2 py-0.5 text-[10px] font-semibold">
                    <i class="bi bi-window-stack"></i> Popup
                  </span>
                @endif

                {{-- Targeting indicator --}}
                @if(!empty($a->target_province_id))
                  <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 px-2 py-0.5 text-[10px] font-semibold border border-amber-200 dark:border-amber-500/20">
                    <i class="bi bi-geo-alt-fill"></i>
                    {{ optional(\DB::table('thai_provinces')->find($a->target_province_id))->name_th ?? '#' . $a->target_province_id }}
                  </span>
                @endif
              </div>

              <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-1 mb-2">
                {{ $a->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($a->body ?: ''), 100) }}
              </p>

              <div class="flex items-center gap-3 flex-wrap text-[11px] text-slate-500 dark:text-slate-400">
                <span class="font-mono">{{ $a->slug }}</span>
                @if($a->starts_at || $a->ends_at)
                  <span class="text-slate-300">·</span>
                  <span>
                    <i class="bi bi-calendar3"></i>
                    @if($a->starts_at){{ $a->starts_at->format('d/m/y H:i') }}@else—@endif
                    →
                    @if($a->ends_at){{ $a->ends_at->format('d/m/y H:i') }}@else∞@endif
                  </span>
                @endif
                <span class="text-slate-300">·</span>
                <span><i class="bi bi-eye"></i> {{ number_format($a->view_count) }}</span>
              </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-2 shrink-0">
              @if($a->trashed())
                <form method="POST" action="{{ route('admin.announcements.restore', $a->id) }}" class="contents">
                  @csrf
                  <button type="submit"
                          class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 px-3 py-1.5 text-xs font-medium transition">
                    <i class="bi bi-arrow-counterclockwise"></i> กู้คืน
                  </button>
                </form>
              @else
                @if($a->status !== 'published')
                  <form method="POST" action="{{ route('admin.announcements.publish', $a->id) }}" class="contents">
                    @csrf
                    <button type="submit" title="เผยแพร่"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 px-2.5 py-1.5 text-xs font-medium transition">
                      <i class="bi bi-broadcast"></i>
                    </button>
                  </form>
                @else
                  <form method="POST" action="{{ route('admin.announcements.archive', $a->id) }}" class="contents">
                    @csrf
                    <button type="submit" title="เก็บ (archive)"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 hover:bg-amber-200 px-2.5 py-1.5 text-xs font-medium transition">
                      <i class="bi bi-archive"></i>
                    </button>
                  </form>
                @endif

                <a href="{{ route('admin.announcements.edit', $a->id) }}" title="แก้ไข"
                   class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-200 px-3 py-1.5 text-xs font-medium transition">
                  <i class="bi bi-pencil"></i> แก้ไข
                </a>

                <button type="button" @click="confirmDeleteId = {{ $a->id }}" title="ลบ"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border border-rose-200 dark:border-rose-500/30 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 px-2.5 py-1.5 text-xs font-medium transition">
                  <i class="bi bi-trash"></i>
                </button>
              @endif
            </div>
          </div>

          {{-- Inline delete confirmation --}}
          <div x-show="confirmDeleteId === {{ $a->id }}" x-cloak x-collapse>
            <div class="border-t border-slate-200 dark:border-white/10 bg-rose-50 dark:bg-rose-500/10 px-4 py-3 flex items-center justify-between gap-3 flex-wrap">
              <div class="text-xs text-rose-800 dark:text-rose-200">
                <strong><i class="bi bi-exclamation-triangle-fill"></i> ยืนยันลบ "{{ $a->title }}"?</strong>
                <span class="opacity-90">ลบแบบ soft-delete — กู้คืนได้จากตัวกรอง "แสดงเฉพาะที่ลบแล้ว"</span>
              </div>
              <div class="flex gap-2">
                <button type="button" @click="confirmDeleteId = null"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 px-3 py-1.5 text-xs font-medium transition">
                  ยกเลิก
                </button>
                <form method="POST" action="{{ route('admin.announcements.destroy', $a->id) }}" class="contents">
                  @csrf @method('DELETE')
                  <button type="submit"
                          class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white px-3 py-1.5 text-xs font-medium shadow-sm transition">
                    <i class="bi bi-trash"></i> ลบเลย
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>

    @if($announcements->hasPages())
      <div class="mt-6">{{ $announcements->withQueryString()->links() }}</div>
    @endif
  @endif
</div>
@endsection

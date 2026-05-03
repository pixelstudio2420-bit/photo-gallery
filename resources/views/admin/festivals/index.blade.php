@extends('layouts.admin')

@section('title', 'เทศกาล / Festival popups')

@section('content')
<div x-data="{ openId: null, themePreviewId: null }">

  {{-- ═══════════════════════════════════════════════════════════════
       HEADER — matches the products/digital-orders admin design
       (icon-on-left, title + subtitle stack, no breadcrumb because
       sidebar already shows location)
       ═══════════════════════════════════════════════════════════════ --}}
  <div class="flex items-start justify-between mb-6 flex-wrap gap-3">
    <div class="flex items-center gap-3">
      <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-pink-500 to-orange-500 text-white flex items-center justify-center shadow-md shadow-pink-500/30">
        <i class="bi bi-calendar-heart text-xl"></i>
      </div>
      <div>
        <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">เทศกาล / Festival popups</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400">Popup ตามเทศกาลพร้อมธีมสี — สงกรานต์, ลอยกระทง, ปีใหม่ และอื่นๆ</p>
      </div>
    </div>
    <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 px-3 py-1.5 text-xs font-medium border border-indigo-100 dark:border-indigo-500/20">
      <i class="bi bi-info-circle"></i>
      ระบบ seed เทศกาลใหม่อัตโนมัติทุกครั้งที่ deploy
    </span>
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
       STATS — 4 KPI cards, same shape as digital-orders/index
       ═══════════════════════════════════════════════════════════════ --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    {{-- Total --}}
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-slate-100 dark:bg-white/5 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-collection text-xl text-slate-500"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-slate-900 dark:text-slate-100">{{ $stats['total'] }}</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">เทศกาลทั้งหมด</div>
        </div>
      </div>
    </div>
    {{-- Enabled --}}
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-toggle-on text-xl text-emerald-500"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-emerald-600 dark:text-emerald-400">{{ $stats['enabled'] }}</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">เปิดใช้งาน</div>
        </div>
      </div>
    </div>
    {{-- Currently live --}}
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm relative overflow-hidden
                {{ $stats['currently_live'] > 0 ? 'ring-2 ring-rose-200 dark:ring-rose-500/30' : '' }}">
      @if($stats['currently_live'] > 0)
        <span class="absolute top-2 right-2 flex h-2 w-2">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
        </span>
      @endif
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-rose-500/10 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-broadcast text-xl text-rose-500"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-rose-600 dark:text-rose-400">{{ $stats['currently_live'] }}</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">โชว์ตอนนี้</div>
        </div>
      </div>
    </div>
    {{-- Upcoming --}}
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
      <div class="p-4 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-indigo-500/10 flex items-center justify-center flex-shrink-0">
          <i class="bi bi-clock-history text-xl text-indigo-500"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-2xl font-bold leading-none text-indigo-600 dark:text-indigo-400">{{ $stats['upcoming_30d'] }}</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">ใน 30 วัน</div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════════════════════════════════════════════════
       FESTIVAL CARDS
       Grid of cards. Each card has:
         • Theme color strip (top edge) — instant visual sort by mood
         • Big emoji + name + status pill row
         • Headline preview
         • Date range + popup window meta
         • Action toolbar (toggle / bump-year / edit)
         • Inline edit form (Alpine collapse)
       ═══════════════════════════════════════════════════════════════ --}}

  @if($festivals->isEmpty())
    <div class="rounded-2xl bg-white dark:bg-slate-900 border-2 border-dashed border-slate-200 dark:border-white/10 p-10 text-center">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-100 dark:bg-white/5 mb-3">
        <i class="bi bi-calendar-x text-3xl text-slate-400"></i>
      </div>
      <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-1">ยังไม่มีเทศกาลในระบบ</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400">
        ระบบจะ seed อัตโนมัติเมื่อ deploy หรือรัน
        <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-white/5 text-[11px]">php artisan db:seed --class=FestivalsSeeder</code>
      </p>
    </div>
  @else
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      @foreach($festivals as $f)
        @php
          $theme       = \App\Services\FestivalThemeService::theme($f->theme_variant);
          $now         = now()->startOfDay();
          $popupStart  = $f->starts_at?->copy()->subDays($f->popup_lead_days);
          $isLiveNow   = $f->enabled && $popupStart && $now->between($popupStart, $f->ends_at);
          $isPast      = $f->ends_at->lt($now);
          $isUpcoming  = $popupStart && $popupStart->gt($now);
          $daysToShow  = $isUpcoming ? $now->diffInDays($popupStart) : null;
          $daysLeftEnd = $isLiveNow ? $now->diffInDays($f->ends_at) : null;
        @endphp

        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden
                    {{ $isLiveNow ? 'ring-2 ring-offset-2 ring-rose-200 dark:ring-rose-500/30 dark:ring-offset-slate-950' : '' }}
                    {{ !$f->enabled ? 'opacity-70' : '' }}
                    transition-all">

          {{-- Theme color strip — instantly conveys the festival mood --}}
          <div class="h-1.5" style="background: {{ $theme['gradient_css'] }};"></div>

          {{-- Header row --}}
          <div class="p-5">
            <div class="flex items-start gap-3 mb-3">
              {{-- Emoji avatar with theme gradient bg --}}
              <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl shrink-0 shadow-sm"
                   style="background: {{ $theme['gradient_css'] }};">
                <span style="filter: drop-shadow(0 1px 2px rgba(0,0,0,0.2));">{{ $f->emoji ?: '✨' }}</span>
              </div>

              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                  <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-base leading-tight truncate">
                    {{ $f->name }}
                  </h3>
                  {{-- Status pill --}}
                  @if(!$f->enabled)
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 px-2 py-0.5 text-[10px] font-semibold">
                      <i class="bi bi-pause-fill"></i> ปิดอยู่
                    </span>
                  @elseif($isLiveNow)
                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300 px-2 py-0.5 text-[10px] font-semibold">
                      <span class="relative flex h-1.5 w-1.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-rose-500"></span>
                      </span>
                      LIVE
                    </span>
                  @elseif($isPast)
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 px-2 py-0.5 text-[10px] font-semibold">
                      <i class="bi bi-hourglass-bottom"></i> ผ่านมาแล้ว
                    </span>
                  @elseif($isUpcoming)
                    <span class="inline-flex items-center gap-1 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 px-2 py-0.5 text-[10px] font-semibold">
                      <i class="bi bi-clock"></i>
                      {{ $daysToShow }} วันถึงโชว์
                    </span>
                  @endif

                  {{-- Theme chip --}}
                  <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 dark:bg-white/5 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-white/10 px-2 py-0.5 text-[10px] font-medium">
                    🎨 {{ $theme['label'] }}
                  </span>
                </div>

                {{-- Headline preview --}}
                <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 leading-snug">
                  {{ $f->headline }}
                </p>
              </div>
            </div>

            {{-- Meta row: dates + priority --}}
            <div class="grid grid-cols-2 gap-2 mb-4 text-[11px]">
              <div class="rounded-xl bg-slate-50 dark:bg-white/5 px-3 py-2">
                <div class="text-slate-400 dark:text-slate-500 mb-0.5"><i class="bi bi-calendar-event"></i> เทศกาล</div>
                <div class="font-semibold text-slate-900 dark:text-slate-100 text-xs">
                  {{ $f->starts_at->format('d M Y') }}
                  @if(!$f->starts_at->isSameDay($f->ends_at))
                    <span class="text-slate-400">→</span>
                    {{ $f->ends_at->format('d M Y') }}
                  @endif
                </div>
              </div>
              <div class="rounded-xl bg-slate-50 dark:bg-white/5 px-3 py-2">
                <div class="text-slate-400 dark:text-slate-500 mb-0.5"><i class="bi bi-megaphone"></i> Popup เริ่ม</div>
                <div class="font-semibold text-slate-900 dark:text-slate-100 text-xs">
                  {{ $popupStart->format('d M Y') }}
                  <span class="text-slate-400 font-normal">({{ $f->popup_lead_days }} วันก่อน)</span>
                </div>
              </div>
            </div>

            {{-- Live extra info: days remaining --}}
            @if($isLiveNow && $daysLeftEnd >= 0)
            <div class="mb-4 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 px-3 py-2 text-xs text-rose-800 dark:text-rose-200">
              <i class="bi bi-fire"></i>
              กำลังโชว์อยู่ —
              @if($daysLeftEnd === 0)
                <strong>วันสุดท้าย</strong>
              @else
                เหลืออีก <strong>{{ $daysLeftEnd }}</strong> วันก่อนปิด
              @endif
            </div>
            @endif

            {{-- Priority + targeting indicator --}}
            <div class="flex items-center gap-2 flex-wrap text-[11px] text-slate-500 dark:text-slate-400 mb-4">
              <span class="inline-flex items-center gap-1">
                <i class="bi bi-arrow-up-right-circle"></i>
                Priority <strong class="text-slate-700 dark:text-slate-200 font-mono">{{ $f->show_priority }}</strong>
              </span>
              @if($f->target_province_id)
                <span class="text-slate-300">·</span>
                <span class="inline-flex items-center gap-1">
                  <i class="bi bi-geo-alt-fill text-amber-500"></i>
                  เฉพาะ
                  <strong class="text-slate-700 dark:text-slate-200">
                    {{ optional(\DB::table('thai_provinces')->find($f->target_province_id))->name_th ?? '#' . $f->target_province_id }}
                  </strong>
                </span>
              @else
                <span class="text-slate-300">·</span>
                <span class="inline-flex items-center gap-1">
                  <i class="bi bi-globe2 text-emerald-500"></i>
                  ทั่วประเทศ
                </span>
              @endif
              @if($f->is_recurring)
                <span class="text-slate-300">·</span>
                <span class="inline-flex items-center gap-1">
                  <i class="bi bi-arrow-repeat"></i> รายปี
                </span>
              @endif
            </div>

            {{-- Action toolbar --}}
            <div class="flex items-center gap-2 flex-wrap">
              {{-- Toggle on/off --}}
              <form method="POST" action="{{ route('admin.festivals.toggle', $f->id) }}" class="contents">
                @csrf
                <button type="submit"
                        title="{{ $f->enabled ? 'กดเพื่อปิด popup' : 'กดเพื่อเปิด popup' }}"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium transition
                               {{ $f->enabled
                                   ? 'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200'
                                   : 'bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 hover:bg-slate-200' }}">
                  <i class="bi {{ $f->enabled ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                  {{ $f->enabled ? 'เปิดอยู่' : 'ปิดอยู่' }}
                </button>
              </form>

              {{-- Bump year for past + recurring only --}}
              @if($isPast && $f->is_recurring)
              <form method="POST" action="{{ route('admin.festivals.bump-year', $f->id) }}" class="contents"
                    onsubmit="return confirm('ปรับวันที่ของ &quot;{{ $f->short_name ?: $f->name }}&quot; เป็นปีถัดไปเลยไหม?')">
                @csrf
                <button type="submit"
                        title="ปรับวันที่เป็นปีถัดไปทันที"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-200 px-3 py-1.5 text-xs font-medium transition">
                  <i class="bi bi-arrow-clockwise"></i> ปรับเป็นปีหน้า
                </button>
              </form>
              @endif

              {{-- Edit toggle --}}
              <button type="button"
                      @click="openId = openId === {{ $f->id }} ? null : {{ $f->id }}"
                      class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/10 px-3 py-1.5 text-xs font-medium transition ml-auto">
                <i class="bi" :class="openId === {{ $f->id }} ? 'bi-x-lg' : 'bi-pencil'"></i>
                <span x-text="openId === {{ $f->id }} ? 'ปิด' : 'แก้ไข'"></span>
              </button>
            </div>
          </div>

          {{-- ─── Inline edit form ─── --}}
          <div x-show="openId === {{ $f->id }}" x-cloak x-collapse>
            <div class="border-t border-slate-200 dark:border-white/10 bg-slate-50/50 dark:bg-white/[0.02] p-5">
              <form method="POST" action="{{ route('admin.festivals.update', $f->id) }}" class="space-y-4">
                @csrf @method('PUT')

                {{-- Name + short --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                  <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">หัวข้อเทศกาล</label>
                    <input type="text" name="name" value="{{ old('name', $f->name) }}" required maxlength="200"
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">ชื่อย่อ</label>
                    <input type="text" name="short_name" value="{{ old('short_name', $f->short_name) }}" maxlength="80"
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                </div>

                {{-- Emoji + theme + priority + enabled --}}
                <div class="grid grid-cols-2 md:grid-cols-12 gap-3">
                  <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Emoji</label>
                    <input type="text" name="emoji" value="{{ old('emoji', $f->emoji) }}" maxlength="30"
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-center text-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                  <div class="md:col-span-6">
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">ธีมสี</label>
                    <select name="theme_variant"
                            class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                      @foreach($themes as $key => $themeOpt)
                        <option value="{{ $key }}" @selected($f->theme_variant === $key)>{{ $themeOpt['label'] }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Priority</label>
                    <input type="number" name="show_priority" value="{{ old('show_priority', $f->show_priority) }}" min="0" max="255"
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                  <div class="md:col-span-2 flex items-end">
                    <label class="flex items-center gap-2 cursor-pointer w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-white/5 transition">
                      <input type="hidden" name="enabled" value="0">
                      <input type="checkbox" name="enabled" value="1" {{ $f->enabled ? 'checked' : '' }}
                             class="rounded border-slate-300 text-emerald-500 focus:ring-emerald-500">
                      <span class="text-xs font-medium text-slate-700 dark:text-slate-300">ใช้งาน</span>
                    </label>
                  </div>
                </div>

                {{-- Dates --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">เริ่มเทศกาล</label>
                    <input type="date" name="starts_at" value="{{ old('starts_at', $f->starts_at->format('Y-m-d')) }}" required
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">จบเทศกาล</label>
                    <input type="date" name="ends_at" value="{{ old('ends_at', $f->ends_at->format('Y-m-d')) }}" required
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">โชว์ popup ล่วงหน้า (วัน)</label>
                    <input type="number" name="popup_lead_days" value="{{ old('popup_lead_days', $f->popup_lead_days) }}" min="0" max="90" required
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                </div>

                {{-- Headline (popup) --}}
                <div>
                  <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">หัวข้อโชว์ใน popup</label>
                  <input type="text" name="headline" value="{{ old('headline', $f->headline) }}" required maxlength="250"
                         class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>

                {{-- Body markdown --}}
                <div>
                  <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">
                    เนื้อหา <span class="text-slate-400 text-[10px]">(รองรับ Markdown — **bold**, *italic*, list)</span>
                  </label>
                  <textarea name="body_md" rows="4" maxlength="5000"
                            class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition font-mono">{{ old('body_md', $f->body_md) }}</textarea>
                </div>

                {{-- CTA --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">ปุ่ม CTA</label>
                    <input type="text" name="cta_label" value="{{ old('cta_label', $f->cta_label) }}" maxlength="80" placeholder="เช่น ดูช่างภาพ"
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                  <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">ลิงก์ปุ่ม</label>
                    <input type="text" name="cta_url" value="{{ old('cta_url', $f->cta_url) }}" maxlength="500" placeholder="/events?tag=..."
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                  </div>
                </div>

                {{-- Live preview hint card --}}
                <div class="rounded-xl bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-500/10 dark:to-purple-500/10 border border-indigo-200 dark:border-indigo-500/20 px-4 py-3 text-xs text-slate-700 dark:text-slate-300">
                  <i class="bi bi-eye text-indigo-500"></i>
                  <strong>เคล็ดลับ</strong>: ลูกค้าจะเห็น popup ตั้งแต่
                  <strong>{{ $popupStart->format('d M Y') }}</strong>
                  จนถึง <strong>{{ $f->ends_at->format('d M Y') }}</strong>
                  — popup จะแสดง 1 ครั้งต่อ user (กดปิดแล้วไม่กลับมาอีก)
                </div>

                {{-- Form actions --}}
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-white/10">
                  <button type="button" @click="openId = null"
                          class="inline-flex items-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/10 px-4 py-2 text-sm font-medium transition">
                    ยกเลิก
                  </button>
                  <button type="submit"
                          class="inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white px-4 py-2 text-sm font-medium shadow-sm shadow-indigo-500/25 transition">
                    <i class="bi bi-check-lg"></i> บันทึกการเปลี่ยนแปลง
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif

  {{-- Footer help card --}}
  <div class="mt-6 rounded-2xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 p-4 text-xs text-slate-600 dark:text-slate-400">
    <div class="flex items-start gap-3">
      <i class="bi bi-lightbulb text-amber-500 text-base mt-0.5"></i>
      <div class="flex-1 leading-relaxed">
        <p class="font-medium text-slate-700 dark:text-slate-300 mb-1">เกี่ยวกับระบบ Festival Popup</p>
        <ul class="space-y-1 list-disc pl-4">
          <li>Popup จะ pop ขึ้นที่หน้าเว็บลูกค้าหลัง 8 วินาที (announcement popup ขึ้นก่อน 6 วิ)</li>
          <li>กด "ปิด · ไม่แสดงอีก" หรือคลิก CTA = บันทึกใน DB ไม่กลับมาแสดงอีก</li>
          <li>เทศกาลจะหายจาก popup queue เมื่อเลย <code class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-white/10 text-[11px]">ends_at</code></li>
          <li>ตั้ง <strong>Priority</strong> สูงเพื่อให้ขึ้นก่อนเทศกาลอื่น (ถ้ามีเทศกาล active หลายตัวพร้อมกัน)</li>
          <li>เพิ่มเทศกาลใหม่ผ่าน seeder — admin แก้ content ได้แต่ไม่ลบ slug เดิมเพื่อ idempotency</li>
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection

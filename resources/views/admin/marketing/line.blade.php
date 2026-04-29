@extends('layouts.admin')

@section('title', 'LINE Marketing')

@push('styles')
<style>
  .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(16,185,129,.14) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(20,184,166,.10) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(34,197,94,.12) 0px, transparent 50%);
  }
  .dark .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(16,185,129,.20) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(20,184,166,.16) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(34,197,94,.18) 0px, transparent 50%);
  }
  @keyframes pending-glow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(16,185,129,.4); }
    50%      { box-shadow: 0 0 0 6px transparent; }
  }
  .has-changes-pulse { animation: pending-glow 1.8s ease-in-out infinite; }
</style>
@endpush

@section('content')
@php
    $marketingSvc = app(\App\Services\Marketing\MarketingService::class);
    $lineMessagingEnabled = $marketingSvc->lineMessagingEnabled();
    $lineNotifyEnabled = $marketingSvc->lineNotifyEnabled();
@endphp

<div x-data="{ hasChanges: false }" class="max-w-[1100px] mx-auto pb-24 space-y-5">

  {{-- ── HERO HEADER ────────────────────────────────────────────── --}}
  <div class="relative overflow-hidden rounded-2xl border border-emerald-100 dark:border-emerald-500/20 gradient-mesh">
    <div class="relative p-6 md:p-7">
      <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-3">
        <a href="{{ route('admin.marketing.index') }}" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition">Marketing Hub</a>
        <i class="bi bi-chevron-right text-[0.6rem] opacity-50"></i>
        <span class="text-slate-700 dark:text-slate-200 font-medium">LINE Marketing</span>
      </nav>

      <div class="flex items-start justify-between flex-wrap gap-4">
        <div class="flex items-start gap-4 min-w-0 flex-1">
          <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 via-green-500 to-teal-500 text-white flex items-center justify-center shadow-lg shrink-0">
            <i class="bi bi-chat-dots-fill text-2xl"></i>
          </div>
          <div class="min-w-0 flex-1">
            <h1 class="text-2xl md:text-[1.65rem] font-bold text-slate-800 dark:text-slate-100 tracking-tight leading-tight">LINE Marketing</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
              ส่ง broadcast หา friends ของ OA, push notifications ผ่าน LINE Notify
            </p>
            <div class="flex items-center gap-2 mt-3 flex-wrap">
              @if($lineMessagingEnabled)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  Messaging API ON
                </span>
              @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 dark:bg-slate-700/40 dark:text-slate-400">
                  <i class="bi bi-toggle-off"></i> Messaging API OFF
                </span>
              @endif
              @if($lineNotifyEnabled)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300">
                  <i class="bi bi-bell-fill"></i> Notify ON
                </span>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  @if(session('success'))
    <div class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-sm flex items-center gap-2"
         x-data="{ show: true }" x-show="show">
      <i class="bi bi-check-circle-fill text-emerald-500"></i>
      <span class="flex-1">{{ session('success') }}</span>
      <button type="button" @click="show = false" class="text-emerald-600/60 hover:text-emerald-700 dark:text-emerald-400/60 dark:hover:text-emerald-300">
        <i class="bi bi-x-lg text-sm"></i>
      </button>
    </div>
  @endif
  @if(session('error'))
    <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-700 dark:text-rose-300 text-sm flex items-center gap-2">
      <i class="bi bi-exclamation-triangle-fill text-rose-500"></i>
      <span>{{ session('error') }}</span>
    </div>
  @endif

  {{-- ═══ Quota cards ═══ --}}
  @if(!empty($quota) && ($quota['ok'] ?? false))
  @php
      $remaining = max(0, ($quota['data']['value'] ?? 0) - ($usage['data']['totalUsage'] ?? 0));
  @endphp
  <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
          <i class="bi bi-graph-up-arrow"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Monthly Quota</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($quota['data']['value'] ?? 0) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $quota['data']['type'] ?? '—' }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-sky-100 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center">
          <i class="bi bi-send-fill"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sent This Month</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($usage['data']['totalUsage'] ?? 0) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">messages</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-teal-100 dark:bg-teal-500/15 text-teal-600 dark:text-teal-400 flex items-center justify-center">
          <i class="bi bi-bag-check"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Remaining</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($remaining) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">credits left</div>
    </div>
  </div>
  @endif

  {{-- ═══ LINE Settings form ═══ --}}
  <form method="POST" action="{{ route('admin.marketing.line.update') }}" @submit="hasChanges = false" @input="hasChanges = true" class="space-y-4">
    @csrf

    {{-- LINE Messaging API --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
      <div class="bg-gradient-to-r from-emerald-50/60 to-transparent dark:from-emerald-500/5 flex items-center gap-3 p-5 border-b border-slate-200/60 dark:border-white/[0.06]">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center shadow-md shrink-0">
          <i class="bi bi-broadcast text-lg"></i>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="font-bold text-slate-800 dark:text-slate-100">LINE Messaging API (Broadcast)</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">ส่งข้อความหาทุก friend ของ OA — 200 msgs/month ฟรี จากนั้น ฿0.10-0.15/msg</p>
        </div>
        <label class="flex items-center gap-2 cursor-pointer shrink-0">
          <input type="hidden" name="line_messaging_enabled" value="0">
          <input type="checkbox" name="line_messaging_enabled" value="1"
                 class="peer sr-only" {{ $lineMessagingEnabled ? 'checked' : '' }}>
          <span class="relative w-11 h-6 rounded-full bg-slate-300 dark:bg-slate-700 peer-checked:bg-emerald-500 transition-all">
            <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow-md transition-all peer-checked:translate-x-5"></span>
          </span>
        </label>
      </div>
      <div class="p-5 space-y-3">
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Channel Access Token (long-lived)</label>
          <input type="password" name="line_channel_access_token" value="{{ $settings['line_channel_access_token'] }}"
                 autocomplete="off" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm font-mono focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500 outline-none transition">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Channel Secret</label>
          <input type="password" name="line_channel_secret" value="{{ $settings['line_channel_secret'] }}"
                 autocomplete="off" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm font-mono focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500 outline-none transition">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">LINE OA ID (e.g. @yourbrand)</label>
          <input type="text" name="line_oa_id" value="{{ $settings['line_oa_id'] }}"
                 class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm font-mono focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500 outline-none transition">
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 pt-1">
          <i class="bi bi-info-circle"></i> หาได้จาก
          <a href="https://developers.line.biz/console/" target="_blank" class="text-emerald-600 dark:text-emerald-400 hover:underline">LINE Developers Console</a>
          → Messaging API channel
        </p>
      </div>
    </div>

    {{-- LINE Notify --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
      <div class="bg-gradient-to-r from-green-50/60 to-transparent dark:from-green-500/5 flex items-center gap-3 p-5 border-b border-slate-200/60 dark:border-white/[0.06]">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-500 to-emerald-500 text-white flex items-center justify-center shadow-md shrink-0">
          <i class="bi bi-bell-fill text-lg"></i>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="font-bold text-slate-800 dark:text-slate-100">LINE Notify</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">แจ้งเตือน realtime ผ่าน LINE — free, 1000 req/hr</p>
        </div>
        <label class="flex items-center gap-2 cursor-pointer shrink-0">
          <input type="hidden" name="line_notify_enabled" value="0">
          <input type="checkbox" name="line_notify_enabled" value="1"
                 class="peer sr-only" {{ $lineNotifyEnabled ? 'checked' : '' }}>
          <span class="relative w-11 h-6 rounded-full bg-slate-300 dark:bg-slate-700 peer-checked:bg-green-500 transition-all">
            <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow-md transition-all peer-checked:translate-x-5"></span>
          </span>
        </label>
      </div>
      <div class="p-5 space-y-3">
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">LINE Notify Access Token</label>
          <input type="password" name="line_notify_token" value="{{ $settings['line_notify_token'] }}"
                 autocomplete="off" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm font-mono focus:ring-2 focus:ring-green-500/40 focus:border-green-500 outline-none transition">
        </div>
        <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-xs text-amber-700 dark:text-amber-300 flex items-start gap-2">
          <i class="bi bi-exclamation-triangle-fill text-amber-500 mt-0.5"></i>
          <span>LINE Notify จะถูกปิดบริการ 2025-03-31 — ตรวจสอบ migration plan ไปใช้ Messaging API push</span>
        </div>
        <a href="https://notify-bot.line.me/my/" target="_blank" class="text-xs text-green-600 dark:text-green-400 hover:underline inline-flex items-center gap-1">
          <i class="bi bi-box-arrow-up-right"></i> Generate token ที่ LINE Notify console
        </a>
      </div>
    </div>

    {{-- ── STICKY SAVE BAR ────────────────────────────────────────── --}}
    <div class="fixed bottom-0 left-0 right-0 lg:left-[260px] lg:[.lg\:ml-\[72px\]_&]:left-[72px] z-30 transition-all"
         :class="hasChanges ? 'translate-y-0' : 'translate-y-full lg:translate-y-0'">
      <div class="bg-white/95 dark:bg-slate-800/95 backdrop-blur-xl border-t border-slate-200/60 dark:border-white/[0.06] shadow-[0_-8px_24px_-12px_rgba(0,0,0,0.15)]">
        <div class="max-w-full px-4 lg:px-6 py-3 flex items-center justify-between gap-3 flex-wrap">
          <div class="text-xs">
            <span x-show="hasChanges" class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 font-semibold has-changes-pulse">
              <i class="bi bi-exclamation-circle-fill"></i> มีการเปลี่ยนแปลง
            </span>
            <span x-show="!hasChanges" x-cloak class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400">
              <i class="bi bi-check-circle"></i> ไม่มีการเปลี่ยนแปลง
            </span>
          </div>
          <div class="flex items-center gap-2">
            <a href="{{ route('admin.marketing.index') }}"
               class="px-4 py-2 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition">ยกเลิก</a>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold bg-gradient-to-br from-emerald-500 via-green-500 to-teal-500 text-white shadow-lg shadow-emerald-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200">
              <i class="bi bi-check2"></i> บันทึก
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>

  {{-- ═══ Broadcast composer ═══ --}}
  @if($lineMessagingEnabled)
  <div class="bg-white dark:bg-slate-800 border border-emerald-200 dark:border-emerald-500/30 rounded-2xl shadow-sm overflow-hidden">
    <div class="bg-gradient-to-r from-emerald-50/80 to-transparent dark:from-emerald-500/10 p-5 border-b border-emerald-200/60 dark:border-emerald-500/20">
      <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
        <i class="bi bi-megaphone-fill text-emerald-500"></i>
        Broadcast Composer
      </h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">ส่งข้อความไปทุก friend ของ OA — ใช้อย่างประหยัดเพราะหัก quota</p>
    </div>
    <form method="POST" action="{{ route('admin.marketing.line.broadcast') }}" class="p-5 space-y-3"
          onsubmit="return confirm('ยืนยันส่ง broadcast ไปยังทุก friend? การกระทำนี้ย้อนกลับไม่ได้')">
      @csrf
      <textarea name="message" rows="4" maxlength="5000" required
                placeholder="ข้อความที่จะส่ง (max 5000 chars)"
                class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500 outline-none transition"></textarea>
      <div class="flex justify-between items-center flex-wrap gap-2">
        <p class="text-xs text-slate-500 dark:text-slate-400">
          <i class="bi bi-info-circle"></i> จะส่งไปยังทุกคนที่ add friend OA นี้แล้ว
        </p>
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold bg-gradient-to-br from-emerald-500 via-green-500 to-teal-500 text-white shadow-lg shadow-emerald-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all">
          <i class="bi bi-send-fill"></i> ส่ง Broadcast
        </button>
      </div>
    </form>
  </div>
  @endif

  {{-- ═══ Test LINE Notify ═══ --}}
  @if($lineNotifyEnabled)
  <form method="POST" action="{{ route('admin.marketing.line.notify-test') }}">
    @csrf
    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-green-200 dark:border-green-500/30 text-green-700 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-500/10 text-sm font-semibold transition">
      <i class="bi bi-bell-fill"></i> ทดสอบส่ง LINE Notify
    </button>
  </form>
  @endif
</div>
@endsection

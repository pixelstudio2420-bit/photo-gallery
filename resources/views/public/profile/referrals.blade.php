@extends('layouts.app')

@section('title', 'แนะนำเพื่อน')

{{-- =======================================================================
     PROFILE · MY REFERRALS
     -------------------------------------------------------------------
     Personal referral code, share URL with copy/QR, redemption stats,
     and a list of recent invites and rewards earned.

     Empty / disabled states:
       - Marketing referral feature off in admin → "feature unavailable"
       - User has no redemptions yet → friendly call-to-action
     ====================================================================== --}}
@section('content')
<div class="max-w-6xl mx-auto px-4 md:px-6 py-6">

  {{-- ───────────── Header ───────────── --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-pink-500 to-rose-500 text-white shadow-md">
          <i class="bi bi-people-fill"></i>
        </span>
        แนะนำเพื่อน
      </h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 ml-1">
        ชวนเพื่อนมาใช้บริการ — เพื่อนได้ส่วนลด คุณได้รางวัล
      </p>
    </div>
  </div>

  {{-- ───────────── Tab Navigation ───────────── --}}
  <div class="mb-6 flex items-center gap-1 overflow-x-auto pb-1 border-b border-slate-200 dark:border-white/10">
    @foreach([
      ['route' => route('profile'),           'label' => 'ภาพรวม',      'icon' => 'bi-grid',       'active' => false],
      ['route' => route('profile.orders'),    'label' => 'คำสั่งซื้อ',   'icon' => 'bi-receipt',    'active' => false],
      ['route' => route('profile.downloads'), 'label' => 'ดาวน์โหลด', 'icon' => 'bi-download',   'active' => false],
      ['route' => route('profile.reviews'),   'label' => 'รีวิว',        'icon' => 'bi-star',       'active' => false],
      ['route' => route('wishlist.index'),    'label' => 'รายการโปรด','icon' => 'bi-heart',      'active' => false],
      ['route' => route('profile.referrals'), 'label' => 'แนะนำเพื่อน', 'icon' => 'bi-people-fill','active' => true],
    ] as $tab)
      <a href="{{ $tab['route'] }}"
         class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 whitespace-nowrap transition
            {{ $tab['active']
                ? 'border-pink-500 text-pink-600 dark:text-pink-400'
                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300' }}">
        <i class="bi {{ $tab['icon'] }}"></i> {{ $tab['label'] }}
      </a>
    @endforeach
  </div>

  @if(!$enabled)
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 p-10 text-center shadow-sm">
      <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-gradient-to-br from-slate-100 to-slate-50 dark:from-slate-700 dark:to-slate-800 text-slate-400 mb-4">
        <i class="bi bi-pause-circle text-3xl"></i>
      </div>
      <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">ระบบแนะนำเพื่อนยังไม่เปิดให้บริการ</h3>
      <p class="text-sm text-slate-500 dark:text-slate-400">โปรดกลับมาตรวจสอบอีกครั้งภายหลัง</p>
    </div>
  @else
    {{-- ───────────── Stats grid ───────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 p-4 shadow-sm">
        <div class="text-xs text-slate-500 dark:text-slate-400 mb-1">เพื่อนที่ใช้รหัส</div>
        <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($stats['uses']) }}</div>
      </div>
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 p-4 shadow-sm">
        <div class="text-xs text-slate-500 dark:text-slate-400 mb-1">รับรางวัลแล้ว</div>
        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($stats['rewarded']) }}</div>
      </div>
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 p-4 shadow-sm">
        <div class="text-xs text-slate-500 dark:text-slate-400 mb-1">รางวัลรวม</div>
        <div class="text-2xl font-bold text-pink-600 dark:text-pink-400">{{ number_format($stats['total_reward'], 0) }} ฿</div>
      </div>
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 p-4 shadow-sm">
        <div class="text-xs text-slate-500 dark:text-slate-400 mb-1">สถานะรหัส</div>
        <div class="text-sm font-bold {{ $code && $code->is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500' }}">
          @if($code && $code->is_active)
            <i class="bi bi-check-circle-fill"></i> ใช้งานได้
          @else
            <i class="bi bi-x-circle-fill"></i> ปิดอยู่
          @endif
        </div>
      </div>
    </div>

    {{-- ───────────── How It Works (collapsible) ───────────── --}}
    <div class="mb-6 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden"
         x-data="{ open: false }">
      <button type="button" @click="open = !open"
              class="w-full p-4 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-white/5 transition">
        <div class="flex items-center gap-3 text-left">
          <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white">
            <i class="bi bi-lightbulb-fill"></i>
          </span>
          <div>
            <div class="font-semibold text-slate-900 dark:text-white text-sm">ระบบแนะนำเพื่อนทำงานยังไง?</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">3 ขั้นตอนง่ายๆ — ทั้งคุณและเพื่อนได้รับประโยชน์</div>
          </div>
        </div>
        <i class="bi bi-chevron-down text-slate-400 transition-transform" :class="{ 'rotate-180': open }"></i>
      </button>
      <div x-show="open" x-collapse x-cloak class="border-t border-slate-100 dark:border-white/5">
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          {{-- Step 1 --}}
          <div class="rounded-xl bg-gradient-to-br from-pink-50 to-rose-50 dark:from-pink-500/5 dark:to-rose-500/5 border border-pink-100 dark:border-pink-500/20 p-4">
            <div class="flex items-center gap-2 mb-2">
              <span class="w-7 h-7 rounded-lg bg-pink-500 text-white text-xs font-bold flex items-center justify-center">1</span>
              <h4 class="font-semibold text-slate-900 dark:text-white text-sm">แชร์รหัส</h4>
            </div>
            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
              คัดลอกลิงก์หรือกด LINE/Facebook/Twitter ส่งให้เพื่อน — ระบบจะ
              <strong class="text-pink-600 dark:text-pink-400">จดจำรหัส 30 วัน</strong>
              ให้เพื่อนของคุณ
            </p>
          </div>
          {{-- Step 2 --}}
          <div class="rounded-xl bg-gradient-to-br from-sky-50 to-cyan-50 dark:from-sky-500/5 dark:to-cyan-500/5 border border-sky-100 dark:border-sky-500/20 p-4">
            <div class="flex items-center gap-2 mb-2">
              <span class="w-7 h-7 rounded-lg bg-sky-500 text-white text-xs font-bold flex items-center justify-center">2</span>
              <h4 class="font-semibold text-slate-900 dark:text-white text-sm">เพื่อนซื้อ + ชำระเงิน</h4>
            </div>
            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
              เพื่อนได้
              <strong class="text-sky-600 dark:text-sky-400">ส่วนลดทันที</strong>
              ตอน checkout — คุณจะเห็นในตาราง "ประวัติการใช้รหัส" ด้านล่าง
              (สถานะ "รอ" จนกว่าเพื่อนจะชำระเงินเสร็จ)
            </p>
          </div>
          {{-- Step 3 --}}
          <div class="rounded-xl bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-500/5 dark:to-teal-500/5 border border-emerald-100 dark:border-emerald-500/20 p-4">
            <div class="flex items-center gap-2 mb-2">
              <span class="w-7 h-7 rounded-lg bg-emerald-500 text-white text-xs font-bold flex items-center justify-center">3</span>
              <h4 class="font-semibold text-slate-900 dark:text-white text-sm">คุณได้คะแนน</h4>
            </div>
            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
              เมื่อเพื่อนชำระเงินเสร็จ คุณจะได้รับ
              <strong class="text-emerald-600 dark:text-emerald-400">คะแนน Loyalty</strong>
              เข้าบัญชี — เอาไปแลกส่วนลดในการสั่งซื้อครั้งถัดไปได้
            </p>
          </div>
        </div>
        <div class="px-5 pb-5">
          <div class="rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 p-3 flex items-start gap-2">
            <i class="bi bi-info-circle-fill text-amber-500 mt-0.5"></i>
            <div class="text-xs text-amber-800 dark:text-amber-200 leading-relaxed">
              <strong>ข้อจำกัด:</strong> ใช้รหัสของตัวเองไม่ได้ · เพื่อนต้องมีคำสั่งซื้อที่ "ชำระเงินแล้ว" ถึงจะให้คะแนน · ถ้าเพื่อนคืนเงิน คะแนนจะถูกหักคืนอัตโนมัติ
            </div>
          </div>
        </div>
      </div>
    </div>

    @if($code)
      {{-- ───────────── Share card ───────────── --}}
      <div class="rounded-2xl overflow-hidden bg-gradient-to-br from-pink-500 via-rose-500 to-purple-600 text-white shadow-xl mb-6"
           x-data="{
             copied: false,
             copy(text) {
               navigator.clipboard.writeText(text).then(() => {
                 this.copied = true;
                 setTimeout(() => this.copied = false, 1500);
               });
             }
           }">
        <div class="p-6 md:p-8">
          <div class="flex items-center gap-2 mb-3">
            <i class="bi bi-gift-fill text-xl"></i>
            <span class="text-sm font-semibold uppercase tracking-wider opacity-90">รหัสของคุณ</span>
          </div>
          <div class="text-4xl md:text-5xl font-black tracking-wider mb-4">{{ $code->code }}</div>

          <div class="flex flex-wrap items-center gap-2 text-sm opacity-90 mb-5">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/15 backdrop-blur-sm">
              <i class="bi bi-tag-fill"></i>
              เพื่อนได้
              {{ $code->discount_type === 'percent' ? rtrim(rtrim(number_format($code->discount_value, 2), '0'), '.') . '%' : number_format($code->discount_value, 0) . ' ฿' }}
            </span>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/15 backdrop-blur-sm">
              <i class="bi bi-coin"></i>
              คุณได้ {{ number_format($code->reward_value, 0) }} ฿
            </span>
            @if($code->max_uses > 0)
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/15 backdrop-blur-sm">
                <i class="bi bi-bar-chart"></i> {{ $code->uses_count }}/{{ $code->max_uses }} ครั้ง
              </span>
            @endif
          </div>

          {{-- Share URL --}}
          <div class="rounded-xl bg-white/15 backdrop-blur-md p-3 flex items-center gap-2">
            <input type="text" value="{{ $shareUrl }}" readonly
                   class="flex-1 bg-transparent text-white text-sm font-mono outline-none truncate"
                   onclick="this.select()">
            <button type="button"
                    @click="copy('{{ $shareUrl }}')"
                    class="px-3 py-1.5 rounded-lg bg-white/20 hover:bg-white/30 transition text-sm font-semibold whitespace-nowrap">
              <span x-show="!copied"><i class="bi bi-clipboard"></i> คัดลอก</span>
              <span x-show="copied" x-cloak><i class="bi bi-check-lg"></i> คัดลอกแล้ว</span>
            </button>
          </div>

          {{-- Quick share row --}}
          <div class="flex flex-wrap gap-2 mt-3">
            <a href="https://line.me/R/msg/text/?{{ urlencode('ใช้รหัส ' . $code->code . ' ลดทันที! ' . $shareUrl) }}"
               target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-white/15 hover:bg-white/25 transition text-sm font-semibold">
              <i class="bi bi-line"></i> แชร์ LINE
            </a>
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($shareUrl) }}"
               target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-white/15 hover:bg-white/25 transition text-sm font-semibold">
              <i class="bi bi-facebook"></i> Facebook
            </a>
            <a href="https://twitter.com/intent/tweet?text={{ urlencode('ใช้รหัส ' . $code->code . ' ลดทันที! ' . $shareUrl) }}"
               target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-white/15 hover:bg-white/25 transition text-sm font-semibold">
              <i class="bi bi-twitter-x"></i> Twitter
            </a>
          </div>
        </div>
      </div>
    @endif

    {{-- ───────────── Recent redemptions ───────────── --}}
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5">
        <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
          <i class="bi bi-clock-history text-indigo-500"></i> ประวัติการใช้รหัส
        </h3>
      </div>

      @if($redemptions->isEmpty())
        <div class="text-center py-12 px-6">
          <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-pink-100 to-rose-100 dark:from-pink-500/10 dark:to-rose-500/10 text-pink-500 mb-3">
            <i class="bi bi-emoji-smile text-2xl"></i>
          </div>
          <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-1">ยังไม่มีเพื่อนใช้รหัสของคุณ</p>
          <p class="text-xs text-slate-500 dark:text-slate-400">ลองแชร์รหัสด้านบนกับเพื่อนๆ ของคุณดูสิ</p>
        </div>
      @else
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900/50 text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              <tr>
                <th class="px-4 py-3 text-left">วันที่</th>
                <th class="px-4 py-3 text-left">เพื่อน</th>
                <th class="px-4 py-3 text-left">คำสั่งซื้อ</th>
                <th class="px-4 py-3 text-right">ส่วนลดที่ใช้</th>
                <th class="px-4 py-3 text-right">รางวัลของคุณ</th>
                <th class="px-4 py-3 text-center">สถานะ</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
              @foreach($redemptions as $r)
                <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                  <td class="px-4 py-3 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                    {{ $r->created_at?->format('d/m/Y') }}
                  </td>
                  <td class="px-4 py-3 text-slate-700 dark:text-slate-200">
                    @if($r->redeemer)
                      {{ $r->redeemer->first_name }} {{ Str::limit($r->redeemer->last_name ?? '', 1, '.') }}
                    @else
                      <span class="italic text-slate-400">guest</span>
                    @endif
                  </td>
                  <td class="px-4 py-3">
                    @if($r->order)
                      <a href="{{ route('orders.show', $r->order->id) }}"
                         class="text-indigo-600 dark:text-indigo-400 hover:underline font-mono text-xs">
                        {{ $r->order->order_number }}
                      </a>
                    @else
                      <span class="text-slate-400 italic">—</span>
                    @endif
                  </td>
                  <td class="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">
                    {{ number_format($r->discount_applied, 0) }} ฿
                  </td>
                  <td class="px-4 py-3 text-right font-bold text-pink-600 dark:text-pink-400">
                    @if($r->reward_granted > 0)
                      +{{ number_format($r->reward_granted, 0) }} ฿
                    @else
                      <span class="text-slate-400 italic">—</span>
                    @endif
                  </td>
                  <td class="px-4 py-3 text-center">
                    @php
                      $badge = match ($r->status) {
                        'rewarded' => ['bg' => 'bg-emerald-100 dark:bg-emerald-500/20', 'fg' => 'text-emerald-700 dark:text-emerald-300', 'label' => 'รับแล้ว'],
                        'pending'  => ['bg' => 'bg-amber-100 dark:bg-amber-500/20',     'fg' => 'text-amber-700 dark:text-amber-300', 'label' => 'รออนุมัติ'],
                        'reversed' => ['bg' => 'bg-rose-100 dark:bg-rose-500/20',       'fg' => 'text-rose-700 dark:text-rose-300', 'label' => 'ถูกยกเลิก'],
                        default    => ['bg' => 'bg-slate-100 dark:bg-slate-700',         'fg' => 'text-slate-600 dark:text-slate-300', 'label' => $r->status],
                      };
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold {{ $badge['bg'] }} {{ $badge['fg'] }}">
                      {{ $badge['label'] }}
                    </span>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  @endif

</div>
@endsection

@push('styles')
<style>[x-cloak]{display:none !important;}</style>
@endpush

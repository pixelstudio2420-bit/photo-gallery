@extends('layouts.app')

@section('title', 'ราคา & ค่าธรรมเนียม')

{{--
    PRICING PAGE — 3-tier (Free / Pro / Studio)
    ─────────────────────────────────────────────────
    Plan data read from `subscription_plans` table via HomeController::pricing()
    which queries WHERE is_public = true ORDER BY sort_order — currently
    returns 3 rows after migration 2026_05_19_000007. Edit Admin →
    Subscription Plans to change copy, prices, or features.

    The 3-tier structure was chosen for first-time conversion (Hick's
    Law) — fewer choices = faster decisions = higher conversion. Starter
    + Business plans were deactivated (is_public=false) on 2026-05-04
    rather than deleted, so existing logic that references them keeps
    working and we can re-enable later if data shows they're missed.
--}}
@php
    // Format ฿ — accepts numeric, returns "฿790" or "฿3,990"
    $thb = fn ($n) => '฿' . number_format((float) $n, 0);

    // Compute annual savings % (typically ~17% for 12-month prepay)
    $annualSavingsPct = function ($monthly, $annual): ?int {
        $monthly = (float) $monthly;
        $annual  = (float) $annual;
        if ($monthly <= 0 || $annual <= 0) return null;
        $expectedAnnual = $monthly * 12;
        if ($annual >= $expectedAnnual) return null;
        return (int) round((1 - $annual / $expectedAnnual) * 100);
    };
@endphp

@section('hero')
{{-- ──────────── PRICING HERO ──────────── --}}
<section class="relative overflow-hidden bg-gradient-to-br from-indigo-50 via-violet-50 to-pink-50 dark:from-slate-900 dark:via-indigo-950 dark:to-purple-950">
  <div class="absolute inset-0 pointer-events-none opacity-50 dark:opacity-100"
       style="background-image:url('data:image/svg+xml,%3Csvg width=%2240%22 height=%2240%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cpath d=%22M0 40L40 0M-10 10L10-10M30 50L50 30%22 stroke=%22rgba(100,116,139,0.08)%22 stroke-width=%221%22/%3E%3C/svg%3E');"></div>
  <div class="absolute dark:hidden" style="width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(139,92,246,0.20) 0%,transparent 70%);top:-200px;right:-100px;"></div>

  <div class="max-w-5xl mx-auto px-4 py-14 md:py-20 relative">
    <div class="text-center">
      <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold backdrop-blur-md border bg-white/70 dark:bg-white/10 border-indigo-200/60 dark:border-white/10 text-indigo-700 dark:text-indigo-200 shadow-sm mb-5">
        <i class="bi bi-tag-fill"></i> โปร่งใส 100% — ไม่มีค่าซ่อน
      </span>
      <h1 class="font-extrabold text-4xl sm:text-5xl lg:text-6xl leading-tight tracking-tight mb-5">
        <span class="bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 dark:from-indigo-300 dark:via-violet-300 dark:to-fuchsia-300 bg-clip-text text-transparent">
          ราคา & ค่าธรรมเนียม
        </span>
      </h1>
      <p class="text-base sm:text-lg text-slate-600 dark:text-slate-300/80 max-w-2xl mx-auto leading-relaxed">
        เริ่มต้นใช้ <strong class="text-slate-800 dark:text-white">ฟรีตลอดชีพ</strong> · ยกเลิกเมื่อใดก็ได้ · ดูค่าใช้จ่ายทั้งหมดก่อนสมัคร
      </p>
    </div>
  </div>
</section>
@endsection

@section('content')
<div class="py-10 md:py-14"
     x-data="{ tab: 'photographer', cycle: 'monthly' }">

  {{-- ─────────────── PERSONA TABS ─────────────── --}}
  <div class="max-w-6xl mx-auto px-2">
    <div class="flex items-center justify-center gap-2 mb-8">
      <div class="inline-flex items-center p-1 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm">
        <button type="button" @click="tab = 'photographer'"
                :class="tab === 'photographer' ? 'bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'"
                class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all">
          <i class="bi bi-camera-fill mr-1.5"></i>สำหรับช่างภาพ
        </button>
        <button type="button" @click="tab = 'customer'"
                :class="tab === 'customer' ? 'bg-gradient-to-br from-pink-500 to-rose-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'"
                class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all">
          <i class="bi bi-person-heart mr-1.5"></i>สำหรับลูกค้า
        </button>
      </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         PHOTOGRAPHER PRICING — DB-driven from subscription_plans
         ═══════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'photographer'" x-transition>

      {{-- Billing cycle toggle (monthly / annual) --}}
      <div class="flex items-center justify-center gap-3 mb-8">
        <span class="text-sm font-semibold" :class="cycle === 'monthly' ? 'text-slate-800 dark:text-white' : 'text-slate-400'">รายเดือน</span>
        <button type="button" role="switch" @click="cycle = cycle === 'monthly' ? 'annual' : 'monthly'"
                class="relative w-14 h-7 rounded-full transition-colors"
                :class="cycle === 'annual' ? 'bg-gradient-to-r from-indigo-500 to-violet-600' : 'bg-slate-300 dark:bg-slate-700'">
          <span class="absolute top-0.5 left-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-transform"
                :class="cycle === 'annual' ? 'translate-x-7' : 'translate-x-0'"></span>
        </button>
        <span class="text-sm font-semibold" :class="cycle === 'annual' ? 'text-slate-800 dark:text-white' : 'text-slate-400'">
          รายปี <span class="text-emerald-600 dark:text-emerald-400 text-xs font-bold ml-1">ประหยัด ~17%</span>
        </span>
      </div>

      @if($plans->isEmpty())
        <div class="text-center py-12 text-slate-500">ยังไม่มี plan ใน DB — Admin ต้อง configure ที่ /admin/subscriptions/plans</div>
      @else

      {{-- Plan cards grid (3 plans → 1 col mobile, 3 cols desktop) --}}
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-12 max-w-5xl mx-auto">
        @foreach($plans as $plan)
          @php
            $features = json_decode($plan->features_json ?? '[]', true) ?: [];
            $isHighlighted = (bool) $plan->badge;
            $monthly = $thb($plan->price_thb);
            $annual  = $thb($plan->price_annual_thb);
            $savings = $annualSavingsPct($plan->price_thb, $plan->price_annual_thb);
            $accent  = $plan->color_hex ?: '#6366f1';
            $isFree  = ((float) $plan->price_thb) <= 0;
          @endphp
          <div class="relative rounded-3xl bg-white dark:bg-slate-900 p-6 sm:p-7 transition-all duration-200 hover:-translate-y-1 hover:shadow-2xl
                      {{ $isHighlighted
                          ? 'border-2 border-indigo-500 dark:border-indigo-400 shadow-xl shadow-indigo-500/20 scale-[1.02]'
                          : 'border border-slate-200 dark:border-white/10' }}">

            @if($plan->badge)
              <span class="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1.5 rounded-full text-xs font-bold text-white shadow-lg whitespace-nowrap"
                    style="background:linear-gradient(135deg, {{ $accent }}, #8b5cf6);">
                <i class="bi bi-stars mr-1"></i>{{ $plan->badge }}
              </span>
            @endif

            {{-- Plan name + tagline --}}
            <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-1 mt-{{ $plan->badge ? '2' : '0' }}">
              {{ $plan->name }}
            </h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-6 min-h-[40px] leading-snug">
              {{ $plan->tagline ?: '—' }}
            </p>

            {{-- Price (cycles between monthly / annual) --}}
            <div class="mb-1 min-h-[60px]">
              @if($isFree)
                <div class="flex items-baseline gap-1.5">
                  <span class="text-5xl font-extrabold text-slate-900 dark:text-white">฿0</span>
                  <span class="text-sm text-slate-500 dark:text-slate-400">ตลอดชีพ</span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">ไม่ต้องใช้บัตรเครดิต</p>
              @else
                <div x-show="cycle === 'monthly'">
                  <div class="flex items-baseline gap-1.5">
                    <span class="text-5xl font-extrabold text-slate-900 dark:text-white">{{ $monthly }}</span>
                    <span class="text-sm text-slate-500 dark:text-slate-400">/เดือน</span>
                  </div>
                </div>
                <div x-show="cycle === 'annual'" x-cloak>
                  <div class="flex items-baseline gap-1.5">
                    <span class="text-5xl font-extrabold text-slate-900 dark:text-white">{{ $thb($plan->price_annual_thb / 12) }}</span>
                    <span class="text-sm text-slate-500 dark:text-slate-400">/เดือน</span>
                  </div>
                  @if($savings)
                    <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 font-semibold">
                      💰 ประหยัด {{ $savings }}% — จ่าย {{ $annual }}/ปี
                    </p>
                  @endif
                </div>
              @endif
            </div>

            {{-- Commission (vital — display front + center) --}}
            @if((float) $plan->commission_pct > 0)
              <div class="my-5 p-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200/70 dark:border-amber-400/20">
                <p class="text-sm text-amber-800 dark:text-amber-300">
                  <i class="bi bi-percent"></i> commission <strong>{{ rtrim(rtrim(number_format((float) $plan->commission_pct, 2), '0'), '.') }}%</strong> ต่อยอดขาย
                </p>
              </div>
            @else
              <div class="my-5 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200/70 dark:border-emerald-400/20">
                <p class="text-sm text-emerald-800 dark:text-emerald-300 font-semibold">
                  <i class="bi bi-check-circle-fill"></i> commission <strong>0%</strong> — ได้เต็มทุกบาท
                </p>
              </div>
            @endif

            {{-- Features list --}}
            <ul class="space-y-2.5 text-sm text-slate-700 dark:text-slate-300 mb-7">
              @foreach($features as $feature)
                <li class="flex items-start gap-2">
                  <i class="bi bi-check-circle-fill text-emerald-500 mt-1 shrink-0 text-xs"></i>
                  <span class="leading-snug">{{ $feature }}</span>
                </li>
              @endforeach
            </ul>

            {{-- CTA --}}
            <a href="{{ route('photographer-onboarding.quick') }}"
               class="block w-full text-center px-5 py-3 rounded-xl text-sm font-bold transition-all
                      {{ $isHighlighted
                          ? 'text-white bg-gradient-to-br from-indigo-500 to-violet-600 shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:-translate-y-0.5'
                          : ($isFree
                              ? 'border-2 border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-200 hover:border-indigo-400 dark:hover:border-indigo-400/50'
                              : 'border-2 border-indigo-200 dark:border-indigo-400/30 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-500/10') }}">
              {{ $isFree ? 'เริ่มฟรีเลย' : 'เลือก ' . $plan->name }}
              <i class="bi bi-arrow-right ml-1"></i>
            </a>
          </div>
        @endforeach
      </div>

      {{-- Money-back guarantee strip --}}
      <div class="max-w-3xl mx-auto rounded-2xl bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-500/10 dark:to-teal-500/10 border border-emerald-200/70 dark:border-emerald-400/20 p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shrink-0 shadow-md shadow-emerald-500/30">
          <i class="bi bi-shield-check text-xl"></i>
        </div>
        <div class="flex-1">
          <p class="font-bold text-emerald-900 dark:text-emerald-200 text-sm mb-0.5">
            <i class="bi bi-cash-coin"></i> Money-back 30 วัน
          </p>
          <p class="text-xs text-emerald-800/80 dark:text-emerald-300/80 leading-relaxed">
            ทุก plan ที่จ่ายเงิน หากใช้แล้วไม่พอใจ ภายใน 30 วันแรก ขอเงินคืนเต็มจำนวนได้ ไม่ถามคำถาม
          </p>
        </div>
      </div>
      @endif

    </div>

    {{-- ═══════════════════════════════════════════════════════════
         CUSTOMER PRICING
         ═══════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'customer'" x-cloak x-transition>
      <div class="max-w-3xl mx-auto">
        <div class="rounded-2xl bg-white dark:bg-slate-900 border-2 border-slate-200 dark:border-white/10 p-6 sm:p-8 shadow-sm">
          <div class="flex items-center gap-3 mb-5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-pink-500 to-rose-600 text-white flex items-center justify-center shadow-md shadow-pink-500/30">
              <i class="bi bi-person-heart text-xl"></i>
            </div>
            <div>
              <h3 class="text-xl font-bold text-slate-800 dark:text-white">ราคาสำหรับลูกค้า</h3>
              <p class="text-sm text-slate-500 dark:text-slate-400">ค้นหา + ซื้อรูปจากงาน · จองช่างภาพ</p>
            </div>
          </div>

          <div class="space-y-4">

            <div class="flex items-start gap-3 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30">
              <i class="bi bi-check-circle-fill text-emerald-600 dark:text-emerald-400 text-lg mt-0.5 shrink-0"></i>
              <div>
                <p class="font-bold text-emerald-900 dark:text-emerald-200">ค้นหารูป + Browse — <span class="uppercase">ฟรีเสมอ</span></p>
                <p class="text-sm text-emerald-800/80 dark:text-emerald-300/80">ค้นหารูปด้วยใบหน้า, ดู portfolio ช่างภาพ, browse อีเวนต์ — ไม่มีค่าใช้จ่าย</p>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-white/10">
                <div class="flex items-center gap-2 mb-2">
                  <i class="bi bi-image-fill text-indigo-500"></i>
                  <p class="font-bold text-slate-800 dark:text-white text-sm">ซื้อรูปดิจิทัล</p>
                </div>
                <p class="text-2xl font-extrabold text-slate-900 dark:text-white mb-1">ขึ้นกับงาน</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">ช่างภาพแต่ละคนตั้งราคาเอง — ดูได้ก่อนซื้อ</p>
              </div>
              <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-white/10">
                <div class="flex items-center gap-2 mb-2">
                  <i class="bi bi-calendar-event-fill text-violet-500"></i>
                  <p class="font-bold text-slate-800 dark:text-white text-sm">จองช่างภาพ</p>
                </div>
                <p class="text-2xl font-extrabold text-slate-900 dark:text-white mb-1">ขึ้นกับช่างภาพ</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">ดู portfolio + ราคา ก่อนตัดสินใจ</p>
              </div>
            </div>

            <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30">
              <p class="font-bold text-blue-900 dark:text-blue-200 mb-2 flex items-center gap-2">
                <i class="bi bi-shield-check"></i>การรับประกันลูกค้า
              </p>
              <ul class="text-sm text-blue-800/80 dark:text-blue-300/80 space-y-1 list-disc list-inside">
                <li>ตรวจสลิปอัตโนมัติผ่าน SlipOK — ระบบไม่รับสลิปปลอม</li>
                <li>ส่งรูปเข้า LINE อัตโนมัติหลังจ่าย — ไม่ต้องรอช่างภาพ</li>
                <li>แชทกับช่างภาพได้ตรงในระบบก่อนตัดสินใจ</li>
                <li>Money-back นโยบายตามแต่ละช่างภาพกำหนด — ดูในหน้าจอง</li>
              </ul>
            </div>

            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-white/10">
              <p class="font-bold text-slate-800 dark:text-white mb-2 flex items-center gap-2">
                <i class="bi bi-credit-card-2-front-fill text-indigo-500"></i>ช่องทางการชำระเงิน
              </p>
              <div class="flex flex-wrap gap-2 text-xs">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white dark:bg-slate-700 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-200">
                  <i class="bi bi-qr-code text-emerald-500"></i> PromptPay
                </span>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white dark:bg-slate-700 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-200">
                  <i class="bi bi-bank2 text-blue-500"></i> โอนธนาคาร
                </span>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white dark:bg-slate-700 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-200">
                  <i class="bi bi-receipt text-amber-500"></i> ตรวจสลิปอัตโนมัติ
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>

  {{-- ═══════════════ COMPARISON TABLE ═══════════════ --}}
  <div class="max-w-5xl mx-auto px-4 mt-14">
    <h2 class="text-2xl sm:text-3xl font-extrabold text-center text-slate-800 dark:text-white mb-3">เทียบกับช่องทางอื่น</h2>
    <p class="text-center text-slate-500 dark:text-slate-400 mb-8">ทำไมช่างภาพไทยเลือก loadroop.com</p>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gradient-to-r from-indigo-500/10 to-violet-500/10 border-b border-slate-200 dark:border-white/10">
              <th class="px-4 py-4 text-left font-bold text-slate-700 dark:text-slate-200">Feature</th>
              <th class="px-4 py-4 text-center font-bold">
                <span class="inline-flex items-center gap-1.5 text-indigo-700 dark:text-indigo-300">
                  <i class="bi bi-camera-fill"></i> loadroop.com
                </span>
              </th>
              <th class="px-4 py-4 text-center font-semibold text-slate-500 dark:text-slate-400">Facebook Group</th>
              <th class="px-4 py-4 text-center font-semibold text-slate-500 dark:text-slate-400">Instagram</th>
            </tr>
          </thead>
          <tbody>
            @php
              $rows = [
                ['AI Face Search', true, false, false],
                ['ระบบจองอัตโนมัติ', true, false, false],
                ['LINE reminder กันลูกค้าลืม', true, false, false],
                ['ตรวจสลิปอัตโนมัติ (SlipOK)', true, false, false],
                ['ส่งรูปเข้า LINE หลังจ่าย', true, false, false],
                ['Watermark + ป้องกันรูปขโมย', true, false, false],
                ['ใบเสร็จ/e-Tax อัตโนมัติ', true, false, false],
                ['SEO Profile (ติด Google)', true, false, true],
                ['Boost / โปรโมต', true, true, true],
                ['ค่าธรรมเนียม', '0% (Pro/Studio)', 'ฟรี', 'ฟรี'],
                ['ลูกค้าใหม่จากระบบ', '✅ จัดให้', '❌ หาเอง', '❌ หาเอง'],
              ];
            @endphp
            @foreach($rows as $i => $row)
            <tr class="border-b border-slate-100 dark:border-white/5 {{ $i % 2 === 1 ? 'bg-slate-50/50 dark:bg-white/[0.02]' : '' }}">
              <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">{{ $row[0] }}</td>
              @foreach([1,2,3] as $col)
                <td class="px-4 py-3 text-center">
                  @if(is_bool($row[$col]))
                    @if($row[$col])
                      <i class="bi bi-check-circle-fill text-emerald-500 text-lg"></i>
                    @else
                      <i class="bi bi-x-circle text-slate-300 dark:text-slate-600"></i>
                    @endif
                  @else
                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $row[$col] }}</span>
                  @endif
                </td>
              @endforeach
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- ═══════════════ FAQ ═══════════════ --}}
  <div class="max-w-3xl mx-auto px-4 mt-14" x-data="{ open: 0 }">
    <h2 class="text-2xl sm:text-3xl font-extrabold text-center text-slate-800 dark:text-white mb-8">คำถามที่พบบ่อย</h2>

    <div class="space-y-3">
      @php
        $faqs = [
          ['q' => 'มีค่าซ่อนหรือเปล่า?', 'a' => 'ไม่มี — ค่าใช้จ่ายทั้งหมดที่เห็นบนหน้านี้คือทั้งหมด ไม่มีค่าธรรมเนียมแฝง ไม่มีค่า set up ไม่มีค่า cancellation Plan ฟรีไม่ตัดบัตรเครดิต'],
          ['q' => 'commission คิดยังไง?', 'a' => 'คิดจากยอดขายเท่านั้น — ขายไม่ได้ = ไม่จ่าย ตัวอย่าง Free plan commission 20% ขายรูป ฿100 = ได้ ฿80 / Pro และ Studio commission 0% = ได้ ฿100 เต็มจำนวน'],
          ['q' => 'breakeven Free → Pro คือเท่าไหร่?', 'a' => 'ขายเดือนละ ≥ ฿3,950 ขึ้นไป Pro คุ้มกว่า Free — เพราะ Pro ฿790 sub แต่ commission 0% / Free ฿0 sub แต่ commission 20% ดังนั้น (790 / 0.20) = ฿3,950 / เดือน'],
          ['q' => 'อัพเกรด/ดาวน์เกรด plan ได้ไหม?', 'a' => 'ได้ทุกเมื่อในหน้า /photographer/subscription — อัพเกรดเริ่มใช้ทันที + คิดเงินตามสัดส่วนวันที่เหลือ / ดาวน์เกรดมีผลในรอบบิลถัดไป ข้อมูลทั้งหมด (รูป, อีเวนต์, ลูกค้า) ไม่หาย'],
          ['q' => 'ยกเลิกได้เมื่อไหร่?', 'a' => 'ทุกเมื่อ ในหน้า Subscription กดยกเลิกแล้วใช้งานได้ครบวันสุดท้ายของรอบบิลปัจจุบัน — ไม่ตัดเงินรอบถัดไป Account จะกลับเป็น Free plan อัตโนมัติ ไฟล์ + ลูกค้าอยู่ครบ'],
          ['q' => 'จ่ายรายปีคุ้มกว่ารายเดือนเท่าไหร่?', 'a' => 'ประหยัดประมาณ 17% (จ่าย 10 เดือนได้ 12 เดือน) — Pro รายปี ฿7,900 vs ฿9,480 รายเดือน × 12 = ประหยัด ฿1,580/ปี / Studio รายปี ฿39,900 vs ฿47,880 = ประหยัด ฿7,980/ปี'],
          ['q' => 'ลูกค้าจ่ายแล้วช่างภาพไม่ส่งรูป รับเงินคืนยังไง?', 'a' => 'ระบบส่งรูปเข้า LINE อัตโนมัติทันทีหลังตรวจสลิปผ่าน — ช่างภาพไม่ต้องส่งเอง หากเกิดปัญหาทางเทคนิค ติดต่อ support ผ่าน LINE หรืออีเมลเพื่อขอคืนเงิน'],
          ['q' => 'Money-back 30 วัน คืออะไร?', 'a' => 'Pro และ Studio plan มี policy ใช้แล้วไม่พอใจภายใน 30 วันแรกของการสมัครครั้งแรก ขอคืนเงินเต็มจำนวนได้ ไม่ถามคำถาม — ติดต่อทาง LINE OA หรือ email'],
          ['q' => 'ใช้กับงานประเภทไหนได้?', 'a' => 'ทุกประเภทงาน — งานวิ่ง, รับปริญญา, แต่งงาน, อีเวนต์บริษัท, คอนเสิร์ต, งานเทศกาล (สงกรานต์/ลอยกระทง) ตั้ง pricing ต่อรูป + เปิด AI Face Search ได้ทันทีหลังอัพโหลด'],
        ];
      @endphp
      @foreach($faqs as $i => $faq)
      <div class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 overflow-hidden">
        <button type="button" @click="open = open === {{ $i }} ? -1 : {{ $i }}"
                class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
          <span class="font-semibold text-slate-800 dark:text-white text-sm">{{ $faq['q'] }}</span>
          <i class="bi text-slate-400 transition-transform" :class="open === {{ $i }} ? 'bi-dash-circle text-indigo-500' : 'bi-plus-circle'"></i>
        </button>
        <div x-show="open === {{ $i }}" x-collapse>
          <p class="px-5 pb-4 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">{{ $faq['a'] }}</p>
        </div>
      </div>
      @endforeach
    </div>
  </div>

  {{-- ═══════════════ FINAL CTA ═══════════════ --}}
  <div class="max-w-4xl mx-auto px-4 mt-14">
    <div class="rounded-2xl bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 dark:from-indigo-700 dark:via-violet-700 dark:to-fuchsia-700 p-8 sm:p-10 text-center shadow-2xl shadow-indigo-500/30">
      <h2 class="text-2xl sm:text-3xl font-extrabold text-white mb-3">พร้อมเริ่มแล้วใช่ไหม?</h2>
      <p class="text-white/85 mb-6 max-w-xl mx-auto">
        ลงทะเบียนช่างภาพในไม่ถึง 5 นาที — เริ่มฟรีตลอดชีพ ไม่ใช้บัตรเครดิต
      </p>
      <div class="flex flex-wrap items-center justify-center gap-3">
        <a href="{{ route('photographer-onboarding.quick') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-bold text-indigo-700 bg-white shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all">
          <i class="bi bi-camera-fill"></i>ลงทะเบียนช่างภาพ
        </a>
        <a href="{{ route('events.index') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-bold text-white border-2 border-white/30 hover:border-white/60 hover:bg-white/10 transition-all">
          <i class="bi bi-search"></i>ค้นหารูปฉัน
        </a>
      </div>
    </div>
  </div>

</div>
@endsection

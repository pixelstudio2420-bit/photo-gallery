@extends('layouts.app')

@section('title', 'เริ่มขายรูปออนไลน์ — 0% commission · ส่งเข้า LINE อัตโนมัติ')

@push('styles')
<style>
  /* Scoped utility helpers — keep page self-contained without polluting global tailwind */
  .fp-grad-text {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 45%, #ec4899 100%);
    -webkit-background-clip: text; background-clip: text;
    -webkit-text-fill-color: transparent; color: transparent;
  }
  .fp-card { transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease; }
  .fp-card:hover { transform: translateY(-4px); }
  .fp-pulse-dot { animation: fp-pulse 2s ease-in-out infinite; }
  @keyframes fp-pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.6; transform: scale(1.2); } }
</style>
@endpush

@section('content-full')

{{-- ════════════════════════════════════════════════════════════════════
     HERO — single, focused message + dual CTA
     The headline targets the only metric photographers actually care
     about: "what's left in my pocket?" — 0% commission is the hook.
     ════════════════════════════════════════════════════════════════════ --}}
<section class="relative overflow-hidden bg-gradient-to-br from-indigo-50 via-violet-50 to-pink-50 dark:from-slate-950 dark:via-indigo-950 dark:to-purple-950">
  {{-- Decorative blobs --}}
  <div class="absolute pointer-events-none -top-32 -right-24 w-[500px] h-[500px] rounded-full opacity-40 dark:opacity-25"
       style="background: radial-gradient(circle, rgba(236,72,153,0.35) 0%, transparent 70%);"></div>
  <div class="absolute pointer-events-none -bottom-32 -left-24 w-[420px] h-[420px] rounded-full opacity-40 dark:opacity-25"
       style="background: radial-gradient(circle, rgba(99,102,241,0.35) 0%, transparent 70%);"></div>

  <div class="relative max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20 lg:py-28">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-center">

      {{-- Copy column --}}
      <div class="lg:col-span-7 relative z-[1]">
        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-white/80 dark:bg-white/10 backdrop-blur-md border border-emerald-200 dark:border-emerald-400/30 text-emerald-700 dark:text-emerald-300 mb-5 shadow-sm">
          <span class="w-2 h-2 rounded-full bg-emerald-500 fp-pulse-dot"></span>
          เปิดรับสมัครช่างภาพ — ฟรี ไม่มีค่าแรกเข้า
        </span>

        <h1 class="font-extrabold leading-[1.1] tracking-tight text-4xl sm:text-5xl lg:text-6xl mb-6">
          <span class="block fp-grad-text">ขายรูปอีเวนต์</span>
          <span class="block text-slate-800 dark:text-white">เก็บได้ <span class="fp-grad-text">100%</span> เต็ม</span>
        </h1>

        <p class="text-base sm:text-lg lg:text-xl text-slate-600 dark:text-slate-300 leading-relaxed mb-7 max-w-xl">
          แพลตฟอร์มสำหรับช่างภาพอีเวนต์ในไทย —
          <strong class="text-slate-800 dark:text-white">0% คอมมิชชั่น</strong> บนแผน Pro/Studio,
          ส่งรูปเข้า <strong class="text-emerald-600 dark:text-emerald-400">LINE</strong> ลูกค้าอัตโนมัติหลังจ่ายเงิน,
          AI ค้นหาใบหน้าผ่าน AWS Rekognition และโอนเงินเข้าบัญชีไทยตามรอบเมื่อยอดถึงขั้นต่ำ
        </p>

        {{-- Dual CTA — primary "start free" / secondary "see pricing" --}}
        <div class="flex flex-wrap gap-3 mb-8">
          <a href="{{ route('photographer-onboarding.quick') }}"
             class="inline-flex items-center gap-2 px-6 py-3.5 rounded-2xl font-bold text-white text-base bg-gradient-to-br from-indigo-600 to-violet-600 shadow-xl shadow-indigo-500/30 hover:shadow-indigo-500/50 hover:-translate-y-0.5 transition-all">
            <i class="bi bi-rocket-takeoff"></i>
            เริ่มฟรี · ไม่ต้องผูกบัตร
          </a>
          <a href="#pricing"
             class="inline-flex items-center gap-2 px-6 py-3.5 rounded-2xl font-bold text-base border-2 border-slate-300 dark:border-white/20 text-slate-700 dark:text-white bg-white/60 dark:bg-white/5 backdrop-blur-sm hover:bg-white dark:hover:bg-white/10 hover:-translate-y-0.5 transition-all">
            <i class="bi bi-tags"></i>
            ดูราคาแพ็กเกจ
          </a>
        </div>

        {{-- Trust strip — concrete numbers, not vague claims --}}
        <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-600 dark:text-slate-400">
          <span class="inline-flex items-center gap-1.5"><i class="bi bi-check-circle-fill text-emerald-500"></i> ใช้งานฟรี · ไม่มีบัตรเครดิต</span>
          <span class="inline-flex items-center gap-1.5"><i class="bi bi-check-circle-fill text-emerald-500"></i> อัปโหลดได้ทันที</span>
          <span class="inline-flex items-center gap-1.5"><i class="bi bi-check-circle-fill text-emerald-500"></i> Login ด้วย LINE 1 คลิก</span>
        </div>
      </div>

      {{-- Visual column — earnings calculator (the "see your income" moment).
           Pulls live numbers off the migrated subscription_plans rows so
           the calculator stays in lock-step with whatever the operator
           sets in /admin/subscriptions/plans. The hardcoded 890 + 0.80
           that used to live here would silently lie if Pro's price ever
           moved (which it just did, when the plan-redesign migration ran). --}}
      @php
        // Pull the prices we render below ONCE so the rest of the
        // partial stays terse. fallbacks keep the calculator alive
        // even if /promo loaded before the seed ran.
        $proPlan      = $plans?->firstWhere('code', 'pro');
        $freePlan     = $plans?->firstWhere('code', 'free');
        $proPrice     = (int) ($proPlan?->price_thb ?? 890);
        $freeKeepPct  = max(0, 100 - (int) ($freePlan?->commission_pct ?? 30));   // % the photographer keeps
        $freeKeepFrac = number_format($freeKeepPct / 100, 2, '.', '');             // for the JS expression
        $starterPrice = (int) ($plans?->firstWhere('code', 'starter')?->price_thb ?? 299);
      @endphp
      <div class="lg:col-span-5 relative">
        <div class="relative rounded-3xl bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl border border-white/60 dark:border-white/10 shadow-2xl shadow-indigo-500/15 p-6 sm:p-7"
             x-data="{
               pricePerPhoto: 50,
               photosPerEvent: 200,
               eventsPerMonth: 4,
               proPrice: {{ $proPrice }},
               freeKeepFrac: {{ $freeKeepFrac }},
               get monthlyRevenue() { return this.pricePerPhoto * this.photosPerEvent * this.eventsPerMonth; },
               get yourCutFree() { return Math.round(this.monthlyRevenue * this.freeKeepFrac); },
               get yourCutPro() { return Math.max(0, this.monthlyRevenue - this.proPrice); },
               // Pro break-even = subscription / commission delta. Falls
               // back gracefully if Free commission ever drops to 0%.
               get proBreakEven() {
                 const delta = 1 - this.freeKeepFrac;
                 return delta > 0 ? Math.round(this.proPrice / delta) : 0;
               }
             }">
          <div class="flex items-center gap-2 mb-4">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white text-base shadow-md">
              <i class="bi bi-calculator"></i>
            </span>
            <h3 class="font-bold text-slate-800 dark:text-white">ลองคำนวณรายได้</h3>
          </div>

          <div class="space-y-4 text-sm">
            <div>
              <div class="flex justify-between mb-1">
                <label class="text-slate-600 dark:text-slate-400 font-medium">ราคารูป (บาท/ภาพ)</label>
                <span class="font-bold text-slate-800 dark:text-white" x-text="pricePerPhoto"></span>
              </div>
              <input type="range" x-model.number="pricePerPhoto" min="20" max="200" step="5"
                     class="w-full accent-indigo-600">
            </div>
            <div>
              <div class="flex justify-between mb-1">
                <label class="text-slate-600 dark:text-slate-400 font-medium">รูปขายได้ต่อ event</label>
                <span class="font-bold text-slate-800 dark:text-white" x-text="photosPerEvent"></span>
              </div>
              <input type="range" x-model.number="photosPerEvent" min="50" max="2000" step="50"
                     class="w-full accent-indigo-600">
            </div>
            <div>
              <div class="flex justify-between mb-1">
                <label class="text-slate-600 dark:text-slate-400 font-medium">Event ต่อเดือน</label>
                <span class="font-bold text-slate-800 dark:text-white" x-text="eventsPerMonth"></span>
              </div>
              <input type="range" x-model.number="eventsPerMonth" min="1" max="20" step="1"
                     class="w-full accent-indigo-600">
            </div>

            {{-- Result panel --}}
            <div class="mt-5 pt-5 border-t border-slate-200 dark:border-white/10 space-y-2.5">
              <div class="flex justify-between items-baseline text-slate-600 dark:text-slate-400">
                <span>รายได้รวม / เดือน</span>
                <span class="font-bold text-base text-slate-800 dark:text-white">
                  ฿<span x-text="monthlyRevenue.toLocaleString()"></span>
                </span>
              </div>
              <div class="flex justify-between items-baseline">
                <span class="text-slate-500 dark:text-slate-500 text-xs">
                  Free ({{ 100 - $freeKeepPct }}% commission) → ได้
                </span>
                <span class="font-semibold text-slate-600 dark:text-slate-400 text-sm">
                  ฿<span x-text="yourCutFree.toLocaleString()"></span>
                </span>
              </div>
              <div class="flex justify-between items-baseline rounded-xl bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-500/10 dark:to-teal-500/10 px-3 py-2.5 border border-emerald-200 dark:border-emerald-400/30">
                <span class="text-emerald-700 dark:text-emerald-300 font-semibold text-sm">
                  Pro (฿{{ number_format($proPrice) }} / เดือน) → ได้
                </span>
                <span class="font-extrabold text-lg text-emerald-700 dark:text-emerald-300">
                  ฿<span x-text="yourCutPro.toLocaleString()"></span>
                </span>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-500 mt-1">
                <i class="bi bi-info-circle"></i>
                Pro คุ้มค่ากว่าเมื่อรายได้ &gt; ฿<span x-text="proBreakEven.toLocaleString()"></span> / เดือน
              </p>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

{{-- ════════════════════════════════════════════════════════════════════
     USP — 3 differentiators, vs the alternatives
     Each card answers ONE question a photographer would have when
     comparing us to Pixieset/Drive/Manual.
     ════════════════════════════════════════════════════════════════════ --}}
<section class="py-16 sm:py-20 bg-white dark:bg-slate-950">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-12">
      <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 mb-3">
        ทำไมต้องเรา
      </span>
      <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 dark:text-white mb-4">
        ๓ สิ่งที่<span class="fp-grad-text">คนไทยใช้</span>ทำได้แค่ที่นี่
      </h2>
      <p class="text-base text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
        Pixieset / SmugMug / Drive ทำได้แค่บางส่วน — เราออกแบบสำหรับตลาดไทยตั้งแต่ต้น
      </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 lg:gap-6">

      {{-- USP 1 — LINE-native --}}
      <div class="fp-card relative rounded-3xl p-7 bg-gradient-to-br from-emerald-50 via-white to-teal-50 dark:from-emerald-500/10 dark:via-slate-900 dark:to-teal-500/10 border border-emerald-200 dark:border-emerald-400/30 shadow-xl shadow-emerald-500/5 hover:shadow-emerald-500/20">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-green-600 text-white text-2xl shadow-lg shadow-emerald-500/30 mb-5">
          <i class="bi bi-line"></i>
        </div>
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-3">ส่งเข้า LINE อัตโนมัติ</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed mb-4">
          ลูกค้าจ่ายเงิน → ได้รับรูปทาง LINE ทันที พร้อม Rich Menu สั่งซื้อซ้ำ.
          ไม่ต้องส่ง Drive link, ไม่ต้องตอบ chat คน.
        </p>
        <ul class="space-y-1.5 text-xs text-slate-600 dark:text-slate-400">
          <li class="flex items-start gap-2"><i class="bi bi-check-lg text-emerald-500 mt-0.5"></i> LINE Login 1-click — ไม่ต้องสมัครใหม่</li>
          <li class="flex items-start gap-2"><i class="bi bi-check-lg text-emerald-500 mt-0.5"></i> Push notification หลังขาย</li>
          <li class="flex items-start gap-2"><i class="bi bi-check-lg text-emerald-500 mt-0.5"></i> Rich Menu สั่งซื้อรูปเพิ่ม</li>
        </ul>
      </div>

      {{-- USP 2 — 0% commission on Pro/Studio (Free + Starter still take a cut) --}}
      <div class="fp-card relative rounded-3xl p-7 bg-gradient-to-br from-indigo-50 via-white to-violet-50 dark:from-indigo-500/10 dark:via-slate-900 dark:to-violet-500/10 border border-indigo-200 dark:border-indigo-400/30 shadow-xl shadow-indigo-500/5 hover:shadow-indigo-500/20">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white text-2xl shadow-lg shadow-indigo-500/30 mb-5">
          <i class="bi bi-cash-coin"></i>
        </div>
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-3">0% คอม · เก็บเต็มบนแผนเสียเงิน</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed mb-4">
          จ่ายค่าสมาชิกรายเดือน → เก็บรายได้ 100% (เฉพาะแผน Pro/Studio).
          ขาย ฿10,000 → ได้ ฿10,000 ไม่หักคอมแอบแฝง
        </p>
        <ul class="space-y-1.5 text-xs text-slate-600 dark:text-slate-400">
          <li class="flex items-start gap-2"><i class="bi bi-check-lg text-indigo-500 mt-0.5"></i> Free tier: {{ 100 - $freeKeepPct }}% commission, ไม่มี monthly fee</li>
          <li class="flex items-start gap-2"><i class="bi bi-check-lg text-indigo-500 mt-0.5"></i> Pro ฿{{ number_format($proPrice) }}: 0% commission · เก็บเต็มทุกบาท</li>
          <li class="flex items-start gap-2"><i class="bi bi-check-lg text-indigo-500 mt-0.5"></i> แจ้งถอนได้เมื่อยอดถึงขั้นต่ำ — ไม่มีค่าธรรมเนียมแอบแฝง</li>
        </ul>
      </div>

      {{-- USP 3 — Thai-friendly payments + transparent ledger.
           Old copy promised "e-Tax invoice อัตโนมัติ" + Peakaccount sync
           — neither is implemented. The honest USP we actually deliver
           is Thai-native payment rails (PromptPay/LINE Pay/TrueMoney via
           Omise) plus a full audit trail per order. --}}
      <div class="fp-card relative rounded-3xl p-7 bg-gradient-to-br from-amber-50 via-white to-orange-50 dark:from-amber-500/10 dark:via-slate-900 dark:to-orange-500/10 border border-amber-200 dark:border-amber-400/30 shadow-xl shadow-amber-500/5 hover:shadow-amber-500/20">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-white text-2xl shadow-lg shadow-amber-500/30 mb-5">
          <i class="bi bi-receipt"></i>
        </div>
        <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-3">จ่ายแบบไทย · ยอดทุกบาทตรวจได้</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed mb-4">
          รองรับ PromptPay / TrueMoney / LINE Pay / บัตรเครดิต.
          ทุกออเดอร์มีเลขใบเสร็จ + audit trail ตรวจย้อนหลังได้
        </p>
        <ul class="space-y-1.5 text-xs text-slate-600 dark:text-slate-400">
          <li class="flex items-start gap-2"><i class="bi bi-check-lg text-amber-500 mt-0.5"></i> PromptPay / LINE Pay / TrueMoney / บัตร</li>
          <li class="flex items-start gap-2"><i class="bi bi-check-lg text-amber-500 mt-0.5"></i> เลขใบเสร็จ + ประวัติทุกออเดอร์ใน dashboard</li>
          <li class="flex items-start gap-2"><i class="bi bi-check-lg text-amber-500 mt-0.5"></i> โอนเข้าบัญชีไทย (PromptPay หรือเลขบัญชี)</li>
        </ul>
      </div>

    </div>
  </div>
</section>

{{-- ════════════════════════════════════════════════════════════════════
     HOW IT WORKS — 4 concrete steps, with what the user does + what happens
     ════════════════════════════════════════════════════════════════════ --}}
<section class="py-16 sm:py-20 bg-gradient-to-br from-slate-50 to-indigo-50 dark:from-slate-900 dark:to-indigo-950/40">
  <div class="max-w-6xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-12">
      <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300 mb-3">
        เริ่มยังไง
      </span>
      <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white mb-3">
        เริ่มขายภายใน <span class="fp-grad-text">วันนี้</span> — 4 ขั้นตอน
      </h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">

      @php
        // Each step's body must describe a feature that actually exists
        // in code. Removed claims:
        //   • "AI คัดรูปเบลอให้" — quality_filter is a planned task, not
        //     yet implemented in AiTaskService.
        //   • "Auto-payout ทุกวันจันทร์" — flow is actually a manual
        //     WithdrawalRequest the photographer submits when the
        //     balance reaches the admin-configured minimum.
        //   • "e-Tax อัตโนมัติ" — no TaxInvoice model exists; the
        //     system tracks orders/withdrawals but doesn't issue
        //     formal Thai e-Tax invoices automatically.
        $steps = [
          ['n' => '1', 'icon' => 'bi-person-plus', 'title' => 'สมัคร 30 วินาที', 'body' => 'Login ด้วย LINE หรือ Google · เริ่มที่ Free tier · ไม่ต้องผูกบัตร'],
          ['n' => '2', 'icon' => 'bi-cloud-upload', 'title' => 'อัปโหลดงาน', 'body' => 'อัปโหลดได้ทีละหลายรูป · resume ได้ถ้าเน็ตหลุด · ระบบสร้างลายน้ำ + thumbnail ให้อัตโนมัติ'],
          ['n' => '3', 'icon' => 'bi-shop', 'title' => 'เปิดขาย', 'body' => 'ตั้งราคารูป · เปิด event ให้ลูกค้าค้นหาด้วยใบหน้า · ส่งเข้า LINE หลังจ่ายเงิน (Pro+)'],
          ['n' => '4', 'icon' => 'bi-bank', 'title' => 'รับเงินเข้าบัญชี', 'body' => 'แจ้งถอนได้เมื่อยอดถึงขั้นต่ำ · โอนเข้าบัญชีไทยตามรอบ · ดูยอดขายได้ใน dashboard เรียลไทม์'],
        ];
      @endphp

      @foreach($steps as $step)
      <div class="relative">
        <div class="rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 p-6 h-full shadow-lg shadow-indigo-500/5">
          <div class="flex items-center gap-3 mb-4">
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white font-bold text-lg shadow-md">
              {{ $step['n'] }}
            </span>
            <i class="bi {{ $step['icon'] }} text-2xl text-indigo-500 dark:text-indigo-300"></i>
          </div>
          <h3 class="font-bold text-slate-900 dark:text-white mb-2 text-base">{{ $step['title'] }}</h3>
          <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">{{ $step['body'] }}</p>
        </div>
        @unless($loop->last)
        <div class="hidden lg:block absolute top-1/2 -right-3 z-10 text-indigo-300 dark:text-indigo-700">
          <i class="bi bi-chevron-right text-2xl"></i>
        </div>
        @endunless
      </div>
      @endforeach

    </div>
  </div>
</section>

{{-- ════════════════════════════════════════════════════════════════════
     PRICING — pulled live from subscription_plans table.
     Shows only active + public plans, sorted by sort_order. Highlights
     the plan with `badge` set (typically Pro).
     ════════════════════════════════════════════════════════════════════ --}}
<section id="pricing" class="py-16 sm:py-20 bg-white dark:bg-slate-950 scroll-mt-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-12">
      <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-pink-100 dark:bg-pink-500/15 text-pink-700 dark:text-pink-300 mb-3">
        ราคาที่โปร่งใส
      </span>
      <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white mb-3">
        เลือกแพ็กเกจ<span class="fp-grad-text">ที่เหมาะกับคุณ</span>
      </h2>
      <p class="text-base text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
        เริ่มฟรี · เลื่อนขึ้น tier ได้ทุกเมื่อ · ยกเลิกได้ตลอด ไม่มีสัญญาระยะยาว
      </p>
    </div>

    @if($plans->count() > 0)
    {{-- Card grid — auto-fits 1/2/3 columns up to 3 plans wide.
         The previous `xl:grid-cols-6` layout was sized for 5 public plans
         + 1 spare slot; after the 2026-05-04 migration to 3 tiers it left
         visible empty space on wide screens. Capping at lg:grid-cols-3
         with a max-w-5xl container keeps the cards big and centred no
         matter how many active plans the DB returns (1, 2, or 3). --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 lg:gap-6 max-w-5xl mx-auto items-stretch"
         :class="{ 'md:grid-cols-1 max-w-md': false }">
      @foreach($plans as $plan)
        @php
          $isFeatured = !empty($plan->badge);
          $features   = is_array($plan->features_json) ? $plan->features_json : (json_decode((string) $plan->features_json, true) ?: []);
          $isFree     = (float) $plan->price_thb === 0.0;
          $monthlyPriceText = $isFree ? 'ฟรี' : '฿' . number_format($plan->price_thb, 0);
          $annualPrice = (float) ($plan->price_annual_thb ?? 0);
          $annualSavings = ($plan->price_thb > 0 && $annualPrice > 0)
              ? max(0, (int) round((1 - $annualPrice / ($plan->price_thb * 12)) * 100))
              : 0;
        @endphp
        <div class="fp-card relative rounded-3xl p-7 sm:p-8 flex flex-col {{ $isFeatured
            ? 'bg-gradient-to-br from-indigo-600 to-violet-600 text-white border-2 border-indigo-400 shadow-2xl shadow-indigo-500/30 lg:scale-[1.04] lg:z-10'
            : 'bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all' }}">

          @if($isFeatured)
          <span class="absolute -top-3 left-1/2 -translate-x-1/2 inline-flex items-center gap-1 px-3.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wide bg-amber-400 text-amber-900 shadow-md whitespace-nowrap">
            <i class="bi bi-star-fill"></i> {{ $plan->badge }}
          </span>
          @endif

          {{-- Plan name + tagline --}}
          <h3 class="font-bold text-xl mt-{{ $isFeatured ? '2' : '0' }} {{ $isFeatured ? 'text-white' : 'text-slate-900 dark:text-white' }}">
            {{ $plan->name }}
          </h3>
          @if($plan->tagline)
          <p class="text-sm mt-1.5 mb-5 min-h-[40px] leading-snug {{ $isFeatured ? 'text-indigo-100' : 'text-slate-500 dark:text-slate-400' }}">
            {{ $plan->tagline }}
          </p>
          @else
          <div class="mb-5 min-h-[40px]"></div>
          @endif

          {{-- Price block --}}
          <div class="mb-1">
            <div class="flex items-baseline gap-1.5">
              <span class="text-5xl font-extrabold {{ $isFeatured ? 'text-white' : 'text-slate-900 dark:text-white' }}">
                {{ $monthlyPriceText }}
              </span>
              @if(!$isFree)
              <span class="text-sm {{ $isFeatured ? 'text-indigo-100' : 'text-slate-500 dark:text-slate-400' }}">/ เดือน</span>
              @endif
            </div>
            @if($isFree)
              <p class="text-xs mt-1 {{ $isFeatured ? 'text-indigo-100' : 'text-slate-500 dark:text-slate-400' }}">ตลอดชีพ · ไม่ใช้บัตรเครดิต</p>
            @elseif($annualSavings > 0)
              <p class="text-xs mt-1 {{ $isFeatured ? 'text-amber-200 font-semibold' : 'text-emerald-600 dark:text-emerald-400 font-semibold' }}">
                💰 รายปี ฿{{ number_format($annualPrice, 0) }} (ประหยัด {{ $annualSavings }}%)
              </p>
            @endif
          </div>

          {{-- Commission badge — front + center, biggest selling point per tier --}}
          @php $commPct = (float) $plan->commission_pct; @endphp
          <div class="my-5 px-3 py-2 rounded-xl text-sm flex items-center gap-2 {{ $isFeatured
              ? 'bg-white/15 border border-white/25 text-white'
              : ($commPct > 0 ? 'bg-amber-50 dark:bg-amber-500/10 border border-amber-200/70 dark:border-amber-400/20 text-amber-800 dark:text-amber-300' : 'bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200/70 dark:border-emerald-400/20 text-emerald-800 dark:text-emerald-300') }}">
            <i class="bi {{ $commPct > 0 ? 'bi-percent' : 'bi-check-circle-fill' }} shrink-0"></i>
            <span>commission <strong>{{ $commPct > 0 ? rtrim(rtrim(number_format($commPct, 2), '0'), '.') . '%' : '0% — ได้เต็มทุกบาท' }}</strong></span>
          </div>

          {{-- Features list — show all 8, more breathing room with 3-card layout --}}
          <ul class="space-y-2 text-sm mb-7 flex-1 {{ $isFeatured ? 'text-indigo-50' : 'text-slate-600 dark:text-slate-300' }}">
            @foreach(array_slice($features, 0, 8) as $feat)
              <li class="flex items-start gap-2">
                <i class="bi bi-check2 {{ $isFeatured ? 'text-emerald-300' : 'text-emerald-500' }} mt-0.5 shrink-0 text-base"></i>
                <span class="leading-snug">{{ $feat }}</span>
              </li>
            @endforeach
            @if(count($features) > 8)
              <li class="text-xs pl-6 {{ $isFeatured ? 'text-indigo-200' : 'text-slate-400 dark:text-slate-500' }}">
                + อีก {{ count($features) - 8 }} ฟีเจอร์
              </li>
            @endif
          </ul>

          {{-- CTA --}}
          <a href="{{ route('photographer-onboarding.quick') }}?plan={{ $plan->code }}"
             class="inline-flex items-center justify-center gap-1.5 w-full px-5 py-3 rounded-xl font-bold text-sm transition-all {{ $isFeatured
                ? 'bg-white text-indigo-700 hover:bg-indigo-50 shadow-lg hover:-translate-y-0.5'
                : 'bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400 hover:-translate-y-0.5 hover:shadow-lg' }}">
            {{ $isFree ? 'เริ่มฟรีเลย' : 'เลือก ' . $plan->name }}
            <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      @endforeach
    </div>
    @else
    <p class="text-center text-slate-500 dark:text-slate-400">กำลังโหลดข้อมูลแพ็กเกจ…</p>
    @endif

    <p class="text-center mt-10 text-sm text-slate-500 dark:text-slate-400">
      ราคาที่แสดงคือราคาที่จ่ายจริง · ใบเสร็จออนไลน์ทุกออเดอร์ · ยกเลิกได้ตลอดไม่มีค่าธรรมเนียม
    </p>
    <div class="text-center mt-3">
      <a href="{{ route('pricing') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 dark:text-indigo-300 hover:text-indigo-700 dark:hover:text-indigo-200 transition">
        ดูเปรียบเทียบทุก feature ละเอียดในหน้าราคา
        <i class="bi bi-arrow-right"></i>
      </a>
    </div>
  </div>
</section>

{{-- ════════════════════════════════════════════════════════════════════
     FAQ — answers the 4 things every photographer asks before signing up
     ════════════════════════════════════════════════════════════════════ --}}
<section class="py-16 sm:py-20 bg-gradient-to-b from-white to-slate-50 dark:from-slate-950 dark:to-slate-900">
  <div class="max-w-3xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-10">
      <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 mb-3">
        คำถามที่พบบ่อย
      </span>
      <h2 class="text-3xl font-extrabold text-slate-900 dark:text-white">ก่อนสมัคร · ตอบทุกข้อสงสัย</h2>
    </div>

    <div class="space-y-3" x-data="{ open: 0 }">
      @php
        // Every answer below is grounded in actual code paths:
        //   • Free / paid pricing → SubscriptionPlan rows (live)
        //   • Withdrawal flow → WithdrawalRequest model (manual,
        //     not auto-payout)
        //   • Face threshold → FaceSearchService::searchByFace
        //     defaults to 80% similarity
        //   • Cancel-keeps-files → SubscriptionService::cancel()
        //   • Daily-payout / "2× faster Pro upload" / "95-99%
        //     accuracy" claims removed: they were not implemented.
        $faqs = [
          ['q' => 'ใช้ฟรีจริงหรือ? มีค่าซ่อนไหม?', 'a' =>
              'ฟรีจริง ไม่ต้องผูกบัตรเครดิต. Free tier มีพื้นที่ '
              . ($freePlan?->storage_gb ?? 5) . ' GB + AI '
              . number_format($freePlan?->monthly_ai_credits ?? 50) . ' credits/เดือน + คอมมิชชั่น '
              . (100 - $freeKeepPct) . '% ต่อรายการขาย. '
              . 'หากต้องการ 0% คอมมิชชั่นและฟีเจอร์เต็ม ต้องสมัคร paid tier (เริ่ม ฿'
              . number_format($starterPrice) . '/เดือน)'],
          ['q' => 'รับเงินอย่างไร? เร็วแค่ไหน?', 'a' => 'เมื่อยอดสะสมถึงขั้นต่ำที่แอดมินกำหนด (เช่น ฿500) คุณกด "แจ้งถอน" จาก dashboard — แอดมินตรวจและโอนเข้าบัญชีไทยตามรอบ (PromptPay หรือเลขบัญชี). ทุกรายการมี audit trail ตรวจสอบย้อนหลังได้.'],
          ['q' => 'ระบบ AI ใช้อะไร? แม่นแค่ไหน?', 'a' => 'ระบบใช้ AWS Rekognition (บริการ enterprise ของ Amazon) เพื่อจับคู่ใบหน้า. ค่าเริ่มต้นใช้เกณฑ์ความเหมือน 80% (ปรับได้). ใส่หมวก / แว่น / มุมเฉียง โดยทั่วไปยังจับคู่ได้. ภาพ selfie ของลูกค้าไม่ถูกบันทึกลงระบบ — ใช้แล้วทิ้ง.'],
          ['q' => 'ถ้าอยากเปลี่ยน/ยกเลิก plan?', 'a' => 'เปลี่ยน tier ได้ตลอดในหน้า Subscription. ยกเลิกได้ทุกเมื่อโดยไม่มีค่าธรรมเนียม — เข้า dashboard กด "ยกเลิก" จะอยู่จนสิ้นรอบบิล แล้ว downgrade เป็น Free tier อัตโนมัติ. รูปและข้อมูลทั้งหมดยังคงอยู่.'],
          ['q' => 'ลูกค้าหารูปยังไง? ต้องสอนเขาไหม?', 'a' => 'ลูกค้าเข้า event ของคุณ → สแกน QR หรือกดลิงก์ → ระบบ AI ค้นหาใบหน้าของเขาในรูปทั้งหมด → จ่ายเงิน → ได้รูปทาง LINE หรือลิงก์ดาวน์โหลด. flow ถูกออกแบบให้ลูกค้าทั่วไปใช้เองได้ ไม่ต้องสอน.'],
          ['q' => 'ขนาดรูปอัปโหลดเท่าไรได้?', 'a' => 'อัปโหลดเป็น batch ได้ Resume ได้ถ้าเน็ตหลุดกลางคัน รองรับ JPEG/PNG ไฟล์เต็มความละเอียด ระบบจะสร้าง thumbnail + ลายน้ำให้อัตโนมัติ. ขนาดไฟล์สูงสุดต่อรูปและจำนวนรูปต่ออีเวนต์ขึ้นกับแผนของคุณ.'],
        ];
      @endphp

      @foreach($faqs as $i => $faq)
      <div class="rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 overflow-hidden shadow-sm">
        <button type="button"
                @click="open = (open === {{ $i }} ? null : {{ $i }})"
                class="w-full text-left flex items-center justify-between gap-3 px-5 py-4 hover:bg-slate-50 dark:hover:bg-white/5 transition">
          <span class="font-semibold text-slate-900 dark:text-white text-base">{{ $faq['q'] }}</span>
          <i class="bi text-indigo-500" :class="open === {{ $i }} ? 'bi-dash-circle' : 'bi-plus-circle'"></i>
        </button>
        <div x-show="open === {{ $i }}" x-collapse class="px-5 pb-4 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
          {{ $faq['a'] }}
        </div>
      </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ════════════════════════════════════════════════════════════════════
     FINAL CTA — last chance before they bounce
     ════════════════════════════════════════════════════════════════════ --}}
<section class="py-16 sm:py-20 bg-gradient-to-br from-indigo-600 via-violet-600 to-pink-600 dark:from-indigo-700 dark:via-violet-700 dark:to-pink-700 relative overflow-hidden">
  {{-- Decorative shapes --}}
  <div class="absolute pointer-events-none -top-20 -right-20 w-[400px] h-[400px] rounded-full opacity-20"
       style="background: radial-gradient(circle, rgba(255,255,255,0.3) 0%, transparent 70%);"></div>
  <div class="absolute pointer-events-none -bottom-20 -left-20 w-[400px] h-[400px] rounded-full opacity-20"
       style="background: radial-gradient(circle, rgba(255,255,255,0.3) 0%, transparent 70%);"></div>

  <div class="relative max-w-4xl mx-auto px-4 sm:px-6 text-center text-white">
    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold mb-5 leading-tight">
      พร้อมเริ่มขายรูปแล้วใช่ไหม?
    </h2>
    <p class="text-base sm:text-lg text-indigo-100 mb-8 max-w-2xl mx-auto">
      ใช้เวลา 30 วินาที — สมัครฟรี อัปโหลดงานแรกได้ทันที.
      ไม่มีบัตรเครดิต ไม่มีสัญญาระยะยาว
    </p>
    <div class="flex flex-wrap items-center justify-center gap-3">
      <a href="{{ route('photographer-onboarding.quick') }}"
         class="inline-flex items-center gap-2 px-7 py-4 rounded-2xl font-bold text-base text-indigo-700 bg-white shadow-2xl hover:shadow-white/30 hover:-translate-y-0.5 transition-all">
        <i class="bi bi-rocket-takeoff"></i>
        เริ่มฟรีเดี๋ยวนี้
      </a>
      <a href="{{ route('contact') }}"
         class="inline-flex items-center gap-2 px-7 py-4 rounded-2xl font-bold text-base text-white border-2 border-white/40 bg-white/5 backdrop-blur-sm hover:bg-white/10 hover:-translate-y-0.5 transition-all">
        <i class="bi bi-chat-dots"></i>
        คุยกับทีม
      </a>
    </div>
  </div>
</section>

@endsection

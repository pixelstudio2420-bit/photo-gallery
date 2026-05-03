@extends('layouts.app')

@section('title', 'ราคา & ค่าธรรมเนียม')

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
        ใช้งานพื้นฐานได้ฟรี <strong class="text-slate-800 dark:text-white">ไม่ต้องใช้บัตรเครดิต</strong> ยกเลิกได้ทุกเมื่อ — ดูค่าใช้จ่ายทั้งหมดได้ก่อนสมัคร
      </p>
    </div>
  </div>
</section>
@endsection

@section('content')
<div class="py-10 md:py-14">

  {{-- ─────────────── PERSONA TABS ─────────────── --}}
  <div class="max-w-5xl mx-auto px-2" x-data="{ tab: 'photographer' }">
    <div class="flex items-center justify-center gap-2 mb-10">
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
         PHOTOGRAPHER PRICING
         ═══════════════════════════════════════════════════════════ --}}
    <div x-show="tab === 'photographer'" x-transition>

      {{-- Founding banner --}}
      <div class="mb-8 mx-auto max-w-3xl rounded-2xl bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-500/10 dark:to-orange-500/10 border-2 border-dashed border-amber-300 dark:border-amber-400/30 p-5 sm:p-6">
        <div class="flex items-start gap-4">
          <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-white flex items-center justify-center shrink-0 shadow-md shadow-amber-500/30">
            <i class="bi bi-rocket-takeoff text-lg"></i>
          </div>
          <div class="flex-1">
            <p class="font-bold text-amber-900 dark:text-amber-200 mb-1">🎉 Founding Photographer Program</p>
            <p class="text-sm text-amber-800/90 dark:text-amber-300/90 leading-relaxed">
              ช่างภาพ <strong>50 คนแรก</strong> ที่ลงทะเบียน + ผ่านการอนุมัติ จะได้ commission rate ลด <strong>50% ตลอดอายุการใช้งาน</strong> — ปกติ 15% เหลือ <strong>7.5%</strong> ตลอดไป
            </p>
          </div>
        </div>
      </div>

      {{-- Plan cards --}}
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-10">

        {{-- FREE plan --}}
        <div class="relative rounded-2xl bg-white dark:bg-slate-900 border-2 border-slate-200 dark:border-white/10 p-6 hover:shadow-xl hover:-translate-y-1 transition-all">
          <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-1">เริ่มต้น (Free)</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mb-5">เหมาะสำหรับช่างภาพที่เริ่มต้น</p>
          <div class="mb-5">
            <span class="text-4xl font-extrabold text-slate-900 dark:text-white">฿0</span>
            <span class="text-sm text-slate-500 dark:text-slate-400 ml-1">/เดือน</span>
          </div>
          <ul class="space-y-2.5 text-sm text-slate-700 dark:text-slate-300 mb-6">
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i><span>ลงงานได้ <strong>3 อีเวนต์</strong>/เดือน</span></li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i><span>Storage <strong>5 GB</strong></span></li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i><span>ระบบ booking + LINE reminder</span></li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i><span>Slip auto-verify (SlipOK)</span></li>
            <li class="flex items-start gap-2"><i class="bi bi-x-circle text-slate-300 dark:text-slate-600 mt-0.5 shrink-0"></i><span class="text-slate-400 line-through">AI Face Search</span></li>
            <li class="flex items-start gap-2"><i class="bi bi-x-circle text-slate-300 dark:text-slate-600 mt-0.5 shrink-0"></i><span class="text-slate-400 line-through">Watermark + Source protection</span></li>
            <li class="flex items-start gap-2"><i class="bi bi-percent text-amber-500 mt-0.5 shrink-0"></i><span>Commission <strong>15%</strong> ต่อยอดขาย</span></li>
          </ul>
          <a href="{{ route('photographer-onboarding.quick') }}" class="block w-full text-center px-5 py-2.5 rounded-xl text-sm font-semibold border-2 border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-200 hover:border-indigo-400 dark:hover:border-indigo-400/50 transition-colors">
            เริ่มฟรี
          </a>
        </div>

        {{-- PRO plan (highlighted) --}}
        <div class="relative rounded-2xl bg-gradient-to-br from-indigo-50 to-violet-50 dark:from-indigo-500/10 dark:to-violet-500/10 border-2 border-indigo-500 dark:border-indigo-400 p-6 hover:shadow-2xl hover:-translate-y-1 transition-all shadow-lg shadow-indigo-500/20">
          <span class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full bg-gradient-to-r from-indigo-500 to-violet-600 text-white text-xs font-bold shadow-md shadow-indigo-500/40">
            <i class="bi bi-stars mr-1"></i>ยอดนิยม
          </span>
          <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-1">Pro</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mb-5">ช่างภาพอาชีพ ทำเงินจริง</p>
          <div class="mb-5">
            <span class="text-4xl font-extrabold text-slate-900 dark:text-white">฿299</span>
            <span class="text-sm text-slate-500 dark:text-slate-400 ml-1">/เดือน</span>
            <p class="text-[11px] text-amber-600 dark:text-amber-300 mt-1 font-semibold">
              ฟรี 60 วัน · ไม่ใช้บัตรเครดิต
            </p>
          </div>
          <ul class="space-y-2.5 text-sm text-slate-700 dark:text-slate-200 mb-6">
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i><span><strong>ไม่จำกัด</strong>อีเวนต์</span></li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i><span>Storage <strong>200 GB</strong></span></li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i><span>ระบบ booking + LINE reminder</span></li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i><span><strong>AI Face Search</strong> ทุกอีเวนต์</span></li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i><span>Watermark + Source protection</span></li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i><span>Priority support (LINE Direct)</span></li>
            <li class="flex items-start gap-2"><i class="bi bi-percent text-emerald-600 dark:text-emerald-400 mt-0.5 shrink-0"></i><span>Commission <strong class="text-emerald-700 dark:text-emerald-300">10%</strong> · Founding 50% off → <strong>5%</strong></span></li>
          </ul>
          <a href="{{ route('photographer-onboarding.quick') }}" class="block w-full text-center px-5 py-3 rounded-xl text-sm font-bold text-white bg-gradient-to-br from-indigo-500 to-violet-600 shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all">
            ทดลองใช้ Pro ฟรี 60 วัน
          </a>
        </div>

        {{-- ENTERPRISE plan --}}
        <div class="relative rounded-2xl bg-white dark:bg-slate-900 border-2 border-slate-200 dark:border-white/10 p-6 hover:shadow-xl hover:-translate-y-1 transition-all">
          <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-1">Enterprise</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mb-5">Studio · Agency · Event organizer</p>
          <div class="mb-5">
            <span class="text-3xl font-extrabold text-slate-900 dark:text-white">ราคาตามตกลง</span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">เริ่ม ฿2,990/เดือน</p>
          </div>
          <ul class="space-y-2.5 text-sm text-slate-700 dark:text-slate-300 mb-6">
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i><span><strong>Multi-photographer</strong> account</span></li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i><span>Storage ตามต้องการ</span></li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i><span>White-label option</span></li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i><span>API access</span></li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i><span>SLA + Dedicated manager</span></li>
            <li class="flex items-start gap-2"><i class="bi bi-percent text-emerald-600 dark:text-emerald-400 mt-0.5 shrink-0"></i><span>Commission ลดเหลือ <strong>5-7%</strong></span></li>
          </ul>
          <a href="mailto:hello@loadroop.com?subject=Enterprise%20Inquiry" class="block w-full text-center px-5 py-2.5 rounded-xl text-sm font-semibold border-2 border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-200 hover:border-indigo-400 dark:hover:border-indigo-400/50 transition-colors">
            ติดต่อทีมขาย
          </a>
        </div>
      </div>
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
                <p class="text-2xl font-extrabold text-slate-900 dark:text-white mb-1">฿49 - ฿299</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">ต่อรูป — ราคาตั้งโดยช่างภาพ ดูได้ก่อนซื้อ</p>
              </div>
              <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-white/10">
                <div class="flex items-center gap-2 mb-2">
                  <i class="bi bi-calendar-event-fill text-violet-500"></i>
                  <p class="font-bold text-slate-800 dark:text-white text-sm">จองช่างภาพ</p>
                </div>
                <p class="text-2xl font-extrabold text-slate-900 dark:text-white mb-1">฿1,500 - ฿15,000+</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">ตามงาน — ดูราคา portfolio + คุยตรง</p>
              </div>
            </div>

            <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30">
              <p class="font-bold text-blue-900 dark:text-blue-200 mb-2 flex items-center gap-2">
                <i class="bi bi-shield-check"></i>รับประกันคืนเงิน 100%
              </p>
              <ul class="text-sm text-blue-800/80 dark:text-blue-300/80 space-y-1 list-disc list-inside">
                <li>จ่ายแล้วช่างไม่มา → คืน 100% ภายใน 24 ชม.</li>
                <li>รูปไม่ตรงตาม preview → คืน 100% ภายใน 7 วัน</li>
                <li>ไฟล์เสียหาย / ดาวน์โหลดไม่ได้ → ส่งใหม่หรือคืนเงิน</li>
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
                ['ตรวจสลิปอัตโนมัติ', true, false, false],
                ['ส่งรูปเข้า LINE หลังจ่าย', true, false, false],
                ['Watermark + ป้องกันรูปขโมย', true, false, false],
                ['ใบเสร็จ/e-Tax', true, false, false],
                ['SEO Profile (ติด Google)', true, false, true],
                ['Boost / โปรโมต', true, true, true],
                ['ค่าธรรมเนียม', '5-15%', 'ฟรี', 'ฟรี'],
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
          ['q' => 'มีค่าซ่อนหรือเปล่า?', 'a' => 'ไม่มี — ค่าใช้จ่ายทั้งหมดที่เห็นบนหน้านี้คือทั้งหมด ไม่มีค่าธรรมเนียมแฝง ไม่มีค่า set up ไม่มีค่า cancellation'],
          ['q' => 'ทดลองใช้ Pro ฟรี 60 วัน — ต้องใส่บัตรเครดิตไหม?', 'a' => 'ไม่ต้อง — ลงทะเบียนด้วย LINE หรือ email ก็เริ่มได้เลย ครบ 60 วันแล้วระบบจะถามว่าจะต่อหรือไม่ ถ้าไม่ตอบก็จะ downgrade เป็น Free อัตโนมัติ ไม่ตัดเงิน'],
          ['q' => 'commission คิดยังไง?', 'a' => 'คิดจากยอดขายเท่านั้น — ขายไม่ได้ = ไม่จ่าย ขายรูปดิจิทัล ฿100 หัก 10% (Pro) = ได้ ฿90 หัก 15% (Free) = ได้ ฿85'],
          ['q' => 'Founding Photographer 50% off ตลอดอายุการใช้งาน — จริงเหรอ?', 'a' => 'จริง — เราต้องการช่างภาพ 50 คนแรกที่ช่วยสร้างชุมชนนี้ขึ้นมา commission rate 50% off จะ lock ให้ตลอดที่บัญชีของคุณยัง active โดยไม่ต้องสมัครใหม่'],
          ['q' => 'ยกเลิกได้เมื่อไหร่?', 'a' => 'ทุกเมื่อ ในหน้า Subscription กดยกเลิกแล้วใช้งานได้ครบวันสุดท้ายของรอบบิลปัจจุบัน — ไม่ปรับ pro-rate, ไม่ตัดเงินรอบถัดไป'],
          ['q' => 'ลูกค้าจ่ายแล้วช่างไม่มา รับเงินคืนยังไง?', 'a' => 'ระบบจะคืนเงิน 100% เข้าบัญชี/PromptPay ที่จ่ายมา ภายใน 24 ชม. โดยอัตโนมัติ — ช่างภาพต้อง confirm ภายใน 4 ชม.หลังเวลานัด ถ้าไม่ confirm ระบบ refund อัตโนมัติ'],
          ['q' => 'ใช้กับงาน 1-day event ได้ไหม?', 'a' => 'ได้ — ระบบรองรับงานวิ่ง, รับปริญญา, แต่งงาน, อีเวนต์บริษัท, คอนเสิร์ต, งานเทศกาล (สงกรานต์/ลอยกระทง) ทุกประเภท ตั้ง pricing ต่อรูป + Face Search ได้ทันที'],
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
        ลงทะเบียนช่างภาพในไม่ถึง 5 นาที — ฟรี 60 วัน ไม่ต้องใช้บัตรเครดิต
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

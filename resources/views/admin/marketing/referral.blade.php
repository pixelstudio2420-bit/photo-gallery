@extends('layouts.admin')

@section('title', 'Referral Program')

@push('styles')
<style>
  .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(245,158,11,.14) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(249,115,22,.10) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(239,68,68,.12) 0px, transparent 50%);
  }
  .dark .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(245,158,11,.20) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(249,115,22,.16) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(239,68,68,.18) 0px, transparent 50%);
  }
  @keyframes pending-glow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(245,158,11,.4); }
    50%      { box-shadow: 0 0 0 6px transparent; }
  }
  .has-changes-pulse { animation: pending-glow 1.8s ease-in-out infinite; }
</style>
@endpush

@section('content')
<div class="max-w-[1300px] mx-auto pb-24 space-y-5" x-data="{
    showHelp: false,
    editing: null,
    hasChanges: false,
    open(code) { this.editing = code; },
    close() { this.editing = null; }
  }">

  {{-- ── HERO HEADER ────────────────────────────────────────────── --}}
  <div class="relative overflow-hidden rounded-2xl border border-amber-100 dark:border-amber-500/20 gradient-mesh">
    <div class="relative p-6 md:p-7">
      <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-3">
        <a href="{{ route('admin.marketing.index') }}" class="hover:text-amber-600 dark:hover:text-amber-400 transition">Marketing Hub</a>
        <i class="bi bi-chevron-right text-[0.6rem] opacity-50"></i>
        <span class="text-slate-700 dark:text-slate-200 font-medium">Referral Program</span>
      </nav>

      <div class="flex items-start justify-between flex-wrap gap-4">
        <div class="flex items-start gap-4 min-w-0 flex-1">
          <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 via-orange-500 to-red-500 text-white flex items-center justify-center shadow-lg shrink-0">
            <i class="bi bi-people-fill text-2xl"></i>
          </div>
          <div class="min-w-0 flex-1">
            <h1 class="text-2xl md:text-[1.65rem] font-bold text-slate-800 dark:text-slate-100 tracking-tight leading-tight">Referral Program</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">ให้ลูกค้าแนะนำเพื่อน → ทั้งสองฝ่ายได้ส่วนลด/แต้ม (growth hack ต้นทุนต่ำ)</p>
            <div class="flex items-center gap-2 mt-3 flex-wrap">
              @if($masterEnabled && $referralEnabled)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  เปิดใช้งาน
                </span>
              @elseif(!$masterEnabled)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                  <i class="bi bi-toggle-off"></i> Master Marketing OFF
                </span>
              @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 dark:bg-slate-700/40 dark:text-slate-400">
                  <i class="bi bi-toggle-off"></i> Referral OFF
                </span>
              @endif
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300">
                <i class="bi bi-tag-fill"></i> {{ number_format($summary['codes']) }} codes
              </span>
            </div>
          </div>
        </div>
        <button type="button" @click="showHelp = !showHelp"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/[0.08] hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-600 dark:text-slate-300 text-xs font-medium transition shrink-0">
            <i class="bi bi-info-circle"></i>
            <span x-text="showHelp ? 'ซ่อนคำอธิบาย' : 'ดูคำอธิบายระบบ'"></span>
        </button>
      </div>
    </div>
  </div>

  {{-- ───────────── Help / Explanation Panel ───────────── --}}
  <div x-show="showHelp" x-collapse x-cloak>
    <div class="bg-white dark:bg-slate-800 border border-amber-200 dark:border-amber-500/30 rounded-2xl shadow-sm overflow-hidden">
      <div class="bg-gradient-to-r from-amber-50/80 to-transparent dark:from-amber-500/10 p-5 border-b border-amber-200/60 dark:border-amber-500/20">
        <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
          <i class="bi bi-lightbulb-fill text-amber-500"></i>
          ระบบ Referral ทำงานอย่างไร — คำอธิบายแบบเข้าใจง่าย
        </h3>
      </div>
      <div class="p-5 grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Customer/Photographer side --}}
        <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200/60 dark:border-white/[0.06] p-4">
          <div class="flex items-center gap-2 mb-2">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-pink-100 dark:bg-pink-500/15 text-pink-600 dark:text-pink-400">
              <i class="bi bi-person-heart"></i>
            </span>
            <h4 class="font-semibold text-slate-800 dark:text-slate-100">สำหรับ "เจ้าของรหัส"</h4>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">(ลูกค้า/ช่างภาพที่เป็นคนชวนเพื่อน)</p>
          <ol class="text-xs text-slate-700 dark:text-slate-300 space-y-1.5 list-decimal list-inside">
            <li>ระบบสร้าง <span class="text-amber-600 dark:text-amber-300 font-mono">รหัส 8 ตัวอักษร</span> ให้อัตโนมัติเมื่อเข้าหน้า "แนะนำเพื่อน"</li>
            <li>แชร์ลิงก์ <code class="text-amber-600 dark:text-amber-300">/r/CODE</code> ให้เพื่อน</li>
            <li>เพื่อนใช้รหัสซื้อ → สถานะ <span class="text-amber-600 dark:text-amber-400">pending</span></li>
            <li>เพื่อนชำระเงินเสร็จ → ได้ <span class="text-emerald-600 dark:text-emerald-400">คะแนน Loyalty</span> เข้าบัญชี</li>
            <li>ถ้าเพื่อนคืนเงิน → คะแนนถูกหักคืน</li>
          </ol>
        </div>

        {{-- Friend (redeemer) side --}}
        <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200/60 dark:border-white/[0.06] p-4">
          <div class="flex items-center gap-2 mb-2">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-sky-100 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400">
              <i class="bi bi-gift"></i>
            </span>
            <h4 class="font-semibold text-slate-800 dark:text-slate-100">สำหรับ "เพื่อนที่ใช้รหัส"</h4>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">(ลูกค้าใหม่/มือใหม่ที่เพิ่งสมัคร)</p>
          <ol class="text-xs text-slate-700 dark:text-slate-300 space-y-1.5 list-decimal list-inside">
            <li>คลิกลิงก์ <code class="text-amber-600 dark:text-amber-300">/r/CODE</code> → ระบบจำรหัสไว้ 30 วัน (cookie)</li>
            <li>ใส่รหัสในตะกร้าตอน checkout (หรือใช้อัตโนมัติ)</li>
            <li>ได้ส่วนลดทันที <span class="text-emerald-600 dark:text-emerald-400">(% หรือบาทคงที่)</span></li>
            <li>ใช้ <strong>รหัสตัวเอง</strong> ไม่ได้ — ระบบบล็อก</li>
            <li>ถ้าตั้ง cooldown → ใช้ซ้ำได้ทุก N วัน</li>
          </ol>
        </div>

        {{-- Admin side --}}
        <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200/60 dark:border-white/[0.06] p-4">
          <div class="flex items-center gap-2 mb-2">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400">
              <i class="bi bi-gear-fill"></i>
            </span>
            <h4 class="font-semibold text-slate-800 dark:text-slate-100">สำหรับ "แอดมิน"</h4>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">(คนที่คุมระบบและดู ROI)</p>
          <ol class="text-xs text-slate-700 dark:text-slate-300 space-y-1.5 list-decimal list-inside">
            <li>ตั้งค่า <strong>default</strong> ที่หน้านี้ — โค้ดใหม่จะใช้ค่านี้</li>
            <li>แก้ค่า <strong>รายโค้ด</strong> ได้ผ่านปุ่ม Edit (ใช้ทำ ambassador)</li>
            <li>เปิด/ปิดรหัสรายตัว → ปุ่ม toggle</li>
            <li>ดูสรุป ROI: rewarded / total_reward</li>
            <li><strong>ต้องเปิด Loyalty ด้วย</strong> รหัสถึงจะให้คะแนนได้จริง</li>
          </ol>
        </div>
      </div>

      {{-- Flow diagram --}}
      <div class="px-5 pb-5">
        <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200/60 dark:border-white/[0.06] p-4">
          <div class="text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-3">Flow การจ่ายรางวัล</div>
          <div class="flex items-center justify-between flex-wrap gap-2 text-xs">
            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/[0.06]">
              <i class="bi bi-1-circle-fill text-slate-400"></i>
              <span class="text-slate-700 dark:text-slate-300">เพื่อนใส่รหัสในตะกร้า</span>
            </div>
            <i class="bi bi-arrow-right text-slate-400"></i>
            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30">
              <i class="bi bi-2-circle-fill text-amber-500"></i>
              <span class="text-amber-700 dark:text-amber-300">redemption: pending</span>
            </div>
            <i class="bi bi-arrow-right text-slate-400"></i>
            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/30">
              <i class="bi bi-3-circle-fill text-sky-500"></i>
              <span class="text-sky-700 dark:text-sky-300">order: paid</span>
            </div>
            <i class="bi bi-arrow-right text-slate-400"></i>
            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30">
              <i class="bi bi-4-circle-fill text-emerald-500"></i>
              <span class="text-emerald-700 dark:text-emerald-300">เจ้าของรหัสได้คะแนน</span>
            </div>
          </div>
          <div class="mt-3 pt-3 border-t border-slate-200/60 dark:border-white/[0.06] text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
            <strong class="text-slate-700 dark:text-slate-300">ตัวอย่าง:</strong> reward_value = 50 บาท, points_per_baht = 1 →
            เจ้าของได้ <span class="text-emerald-600 dark:text-emerald-400 font-semibold">50 คะแนน</span>
            (ถ้า points_per_baht = 2 จะได้ 100 คะแนน) คะแนนเหล่านี้จะใช้แลกส่วนลดได้ตามอัตราที่ตั้งใน Loyalty Settings
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

  {{-- ───────────── Master + Feature toggles ───────────── --}}
  <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
    <div class="bg-gradient-to-r from-amber-50/60 to-transparent dark:from-amber-500/5 p-5 border-b border-slate-200/60 dark:border-white/[0.06] flex items-center justify-between flex-wrap gap-2">
      <div class="flex items-center gap-2">
        <i class="bi bi-power text-amber-500 text-lg"></i>
        <h3 class="font-bold text-slate-800 dark:text-slate-100">สถานะระบบ</h3>
      </div>
      @if($masterEnabled && $referralEnabled)
        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-100 dark:bg-emerald-500/15 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-xs font-semibold">
          <i class="bi bi-check-circle-fill"></i> เปิดใช้งาน
        </span>
      @elseif(!$masterEnabled)
        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-amber-100 dark:bg-amber-500/15 border border-amber-200 dark:border-amber-500/30 text-amber-700 dark:text-amber-300 text-xs font-semibold"
              title="Master Marketing toggle is OFF — flip it to enable any marketing module">
          <i class="bi bi-toggle-off"></i> ปิด Master Marketing
        </span>
      @else
        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-700/50 border border-slate-200 dark:border-white/[0.06] text-slate-600 dark:text-slate-400 text-xs font-semibold"
              title="Referral toggle is OFF — flip it to start tracking">
          <i class="bi bi-toggle-off"></i> ปิดอยู่ (กดเปิดที่ปุ่มด้านล่าง)
        </span>
      @endif
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-200/60 dark:divide-white/[0.06]">
      {{-- Master Switch --}}
      <div class="p-5 flex items-center justify-between gap-3">
        <div class="flex items-start gap-3">
          <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl {{ $masterEnabled ? 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-700/40 text-slate-400 dark:text-slate-500' }}">
            <i class="bi bi-toggle-{{ $masterEnabled ? 'on' : 'off' }} text-lg"></i>
          </span>
          <div>
            <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">1. Master Marketing</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">สวิตช์รวม — ปิดแล้ว feature ทั้งหมดจะปิดตาม</div>
          </div>
        </div>
        <form method="POST" action="{{ route('admin.marketing.toggle') }}">
          @csrf
          <input type="hidden" name="feature" value="master">
          <input type="hidden" name="enabled" value="{{ $masterEnabled ? '0' : '1' }}">
          <button type="submit"
              class="px-3 py-1.5 rounded-xl text-xs font-semibold transition {{ $masterEnabled ? 'bg-rose-100 hover:bg-rose-200 text-rose-700 border border-rose-200 dark:bg-rose-500/15 dark:hover:bg-rose-500/25 dark:text-rose-300 dark:border-rose-500/30' : 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/15 dark:hover:bg-emerald-500/25 dark:text-emerald-300 dark:border-emerald-500/30' }}">
            {{ $masterEnabled ? 'ปิด Master' : 'เปิด Master' }}
          </button>
        </form>
      </div>
      {{-- Referral Feature Switch --}}
      <div class="p-5 flex items-center justify-between gap-3">
        <div class="flex items-start gap-3">
          <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl {{ $referralEnabled ? 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-700/40 text-slate-400 dark:text-slate-500' }}">
            <i class="bi bi-toggle-{{ $referralEnabled ? 'on' : 'off' }} text-lg"></i>
          </span>
          <div>
            <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">2. Referral Feature</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">เปิดเฉพาะระบบแนะนำเพื่อน</div>
          </div>
        </div>
        <form method="POST" action="{{ route('admin.marketing.toggle') }}">
          @csrf
          <input type="hidden" name="feature" value="referral">
          <input type="hidden" name="enabled" value="{{ $referralEnabled ? '0' : '1' }}">
          <button type="submit"
              class="px-3 py-1.5 rounded-xl text-xs font-semibold transition {{ $referralEnabled ? 'bg-rose-100 hover:bg-rose-200 text-rose-700 border border-rose-200 dark:bg-rose-500/15 dark:hover:bg-rose-500/25 dark:text-rose-300 dark:border-rose-500/30' : 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/15 dark:hover:bg-emerald-500/25 dark:text-emerald-300 dark:border-emerald-500/30' }}">
            {{ $referralEnabled ? 'ปิด Referral' : 'เปิด Referral' }}
          </button>
        </form>
      </div>
    </div>
    @if(!$masterEnabled || !$referralEnabled)
      <div class="px-5 py-3 bg-amber-50 dark:bg-amber-500/10 border-t border-amber-200 dark:border-amber-500/20 flex items-start gap-2">
        <i class="bi bi-info-circle-fill text-amber-500 mt-0.5"></i>
        <div class="text-xs text-amber-700 dark:text-amber-300 leading-relaxed">
          <strong>ต้องเปิดทั้ง 2 สวิตช์</strong> ระบบ Referral ถึงจะใช้งานได้จริง
          @if(!$masterEnabled)
            — ตอนนี้ Master ปิด ทำให้ feature ทั้งหมด (รวม Referral) ถูกบล็อกอัตโนมัติ
          @elseif(!$referralEnabled)
            — ตอนนี้ Master เปิดแล้ว แต่ Referral feature ยังปิด
          @endif
        </div>
      </div>
    @endif
  </div>

  {{-- Loyalty status warning --}}
  @if($referralEnabled && !$loyaltyEnabled)
    <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 flex items-start gap-3">
      <i class="bi bi-exclamation-triangle-fill text-amber-500 text-xl mt-0.5"></i>
      <div class="flex-1">
        <div class="font-semibold text-amber-700 dark:text-amber-300 text-sm">ระบบ Loyalty ปิดอยู่</div>
        <div class="text-xs text-amber-700 dark:text-amber-300/80 mt-1">
          Referral เปิดอยู่แต่ Loyalty ปิด → รางวัลจะถูก "บันทึกไว้" แต่ "ไม่ได้ credit จริง" ให้เจ้าของรหัส
          เปิด Loyalty ที่ <a href="{{ route('admin.marketing.loyalty') }}" class="underline font-semibold">Loyalty Program</a>
          หรือเปิดอัตโนมัติด้วย checkbox ในฟอร์มด้านล่าง
        </div>
      </div>
    </div>
  @endif

  {{-- ═══ Summary stats ═══ --}}
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 flex items-center justify-center">
          <i class="bi bi-tag-fill"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Codes</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($summary['codes']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
          <i class="bi bi-lightning-charge-fill"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Active</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($summary['active_codes']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-sky-100 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center">
          <i class="bi bi-arrow-repeat"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Redemptions</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($summary['redemptions']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-indigo-100 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
          <i class="bi bi-gift-fill"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Rewarded</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($summary['rewarded']) }}</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
          <i class="bi bi-coin"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Reward</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">฿{{ number_format($summary['total_reward'], 0) }}</div>
    </div>
  </div>

  {{-- ═══ Settings ═══ --}}
  <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
    <div class="bg-gradient-to-r from-amber-50/60 to-transparent dark:from-amber-500/5 p-5 border-b border-slate-200/60 dark:border-white/[0.06] flex items-center gap-2">
      <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-amber-500 to-orange-500 text-white flex items-center justify-center shadow-md">
        <i class="bi bi-sliders"></i>
      </div>
      <h3 class="font-bold text-slate-800 dark:text-slate-100">Default Settings (ใช้กับโค้ดที่สร้างใหม่)</h3>
    </div>
    <form method="POST" action="{{ route('admin.marketing.referral.settings') }}" @submit="hasChanges = false" @input="hasChanges = true" class="p-5">
      @csrf
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Discount Type</label>
          <select name="discount_type" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
            <option value="percent" {{ $settings['discount_type'] === 'percent' ? 'selected' : '' }}>Percent (%)</option>
            <option value="fixed"   {{ $settings['discount_type'] === 'fixed' ? 'selected' : '' }}>Fixed (THB)</option>
          </select>
          <p class="text-[0.68rem] text-slate-500 dark:text-slate-400 mt-1">% คูณ subtotal หรือ บาทคงที่</p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Discount (เพื่อนได้)</label>
          <input type="number" step="0.01" name="discount_value" value="{{ $settings['discount_value'] }}"
              class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
          <p class="text-[0.68rem] text-slate-500 dark:text-slate-400 mt-1">ส่วนลดที่ "เพื่อน" ได้</p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Reward (เจ้าของได้, THB)</label>
          <input type="number" step="0.01" name="reward_value" value="{{ $settings['reward_value'] }}"
              class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
          <p class="text-[0.68rem] text-slate-500 dark:text-slate-400 mt-1">มูลค่ารางวัล (บาท)</p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Points / 1 บาท</label>
          <input type="number" step="0.01" min="0" max="1000" name="points_per_baht" value="{{ $settings['points_per_baht'] }}"
              class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
          <p class="text-[0.68rem] text-slate-500 dark:text-slate-400 mt-1">{{ $settings['reward_value'] }} ฿ × {{ $settings['points_per_baht'] }} = <span class="text-amber-600 dark:text-amber-400 font-semibold">{{ number_format($settings['reward_value'] * $settings['points_per_baht'], 0) }} คะแนน</span></p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Cooldown (วัน)</label>
          <input type="number" min="0" max="365" name="cooldown_days" value="{{ $settings['cooldown_days'] }}"
              class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
          <p class="text-[0.68rem] text-slate-500 dark:text-slate-400 mt-1">0 = ใช้ได้ทุกออเดอร์</p>
        </div>
      </div>

      <div class="flex items-center justify-between flex-wrap gap-3 pt-4 border-t border-slate-200/60 dark:border-white/[0.06]">
        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
          <input type="checkbox" name="auto_enable_loyalty" value="1"
              class="rounded border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-amber-500 focus:ring-amber-500/30"
              {{ $loyaltyEnabled ? 'disabled checked' : '' }}>
          <span>
            @if($loyaltyEnabled)
              <span class="text-emerald-600 dark:text-emerald-400"><i class="bi bi-check-circle-fill"></i> Loyalty เปิดอยู่แล้ว</span>
            @else
              เปิดระบบ Loyalty อัตโนมัติเมื่อบันทึก
            @endif
          </span>
        </label>
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold bg-gradient-to-br from-amber-500 via-orange-500 to-red-500 text-white shadow-lg shadow-amber-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200">
          <i class="bi bi-check2"></i> บันทึก
        </button>
      </div>
    </form>
  </div>

  {{-- ═══ Codes table ═══ --}}
  <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-200/60 dark:border-white/[0.06] flex items-center justify-between flex-wrap gap-2">
      <h3 class="font-bold text-slate-800 dark:text-slate-100">Referral Codes</h3>
      <span class="text-xs text-slate-500 dark:text-slate-400">คลิก <i class="bi bi-pencil-square"></i> เพื่อแก้รายโค้ด · <i class="bi bi-toggle-on"></i> เปิด/ปิด</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50/80 dark:bg-slate-900/40 text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wider">
          <tr>
            <th class="text-left px-4 py-3 font-semibold">Code</th>
            <th class="text-left px-4 py-3 font-semibold">Owner</th>
            <th class="text-left px-4 py-3 font-semibold">Discount → Reward</th>
            <th class="text-right px-4 py-3 font-semibold">Uses</th>
            <th class="text-left px-4 py-3 font-semibold">Expires</th>
            <th class="text-left px-4 py-3 font-semibold">Status</th>
            <th class="text-right px-4 py-3 font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200/60 dark:divide-white/[0.06]">
          @forelse($codes as $c)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
              <td class="px-4 py-3">
                <code class="px-2 py-1 rounded-lg bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 font-mono font-semibold border border-amber-200/60 dark:border-amber-500/20">{{ $c->code }}</code>
              </td>
              <td class="px-4 py-3">
                <div class="text-slate-800 dark:text-slate-100 text-sm">{{ $c->owner?->name ?? '—' }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $c->owner?->email }}</div>
              </td>
              <td class="px-4 py-3 text-slate-700 dark:text-slate-300 text-sm">
                {{ $c->discount_type === 'percent' ? $c->discount_value . '%' : '฿' . number_format($c->discount_value, 0) }}
                <span class="text-slate-500 dark:text-slate-400 text-xs">→ ฿{{ number_format($c->reward_value, 0) }}</span>
              </td>
              <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-300 tabular-nums">
                {{ $c->uses_count }}@if($c->max_uses > 0) / {{ $c->max_uses }}@endif
              </td>
              <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                {{ $c->expires_at ? $c->expires_at->format('d/m/Y') : '—' }}
              </td>
              <td class="px-4 py-3">
                @if($c->is_active && (!$c->expires_at || $c->expires_at->isFuture()))
                  <span class="inline-flex px-2 py-0.5 rounded-full text-[0.68rem] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30">Active</span>
                @else
                  <span class="inline-flex px-2 py-0.5 rounded-full text-[0.68rem] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-400 border border-slate-200 dark:border-slate-500/30">Inactive</span>
                @endif
              </td>
              <td class="px-4 py-3 text-right">
                @php
                  $codePayload = [
                    'id'             => $c->id,
                    'code'           => $c->code,
                    'discount_type'  => $c->discount_type,
                    'discount_value' => (float) $c->discount_value,
                    'reward_value'   => (float) $c->reward_value,
                    'max_uses'       => (int) $c->max_uses,
                    'expires_at'     => $c->expires_at?->format('Y-m-d\TH:i'),
                    'is_active'      => (bool) $c->is_active,
                  ];
                @endphp
                <div class="inline-flex items-center gap-1">
                  <button type="button" @click="open({{ Js::from($codePayload) }})"
                      class="p-1.5 rounded-lg text-slate-500 hover:text-amber-600 hover:bg-amber-50 dark:text-slate-400 dark:hover:text-amber-300 dark:hover:bg-amber-500/10 transition" title="แก้รายโค้ด">
                      <i class="bi bi-pencil-square"></i>
                  </button>
                  <form method="POST" action="{{ route('admin.marketing.referral.toggle', $c) }}" class="inline">
                    @csrf
                    <button class="p-1.5 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-slate-100 dark:hover:bg-slate-700 transition" title="เปิด/ปิด">
                      <i class="bi bi-toggle-{{ $c->is_active ? 'on' : 'off' }}"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
              <i class="bi bi-inbox text-4xl block mb-2 opacity-50"></i>
              ยังไม่มี referral codes — จะสร้างอัตโนมัติเมื่อ user เข้าหน้า "แนะนำเพื่อน"
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-4 border-t border-slate-200/60 dark:border-white/[0.06]">{{ $codes->links() }}</div>
  </div>

  {{-- ───────────── Edit Modal ───────────── --}}
  <template x-if="editing">
    <div x-show="editing" x-cloak x-transition.opacity
        class="fixed inset-0 z-[1100] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
        @keydown.escape.window="close()" @click.self="close()">
      <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/[0.08] shadow-2xl overflow-hidden"
           x-transition>
        <div class="bg-gradient-to-r from-amber-50/80 to-transparent dark:from-amber-500/10 p-4 border-b border-slate-200/60 dark:border-white/[0.06] flex items-center justify-between">
          <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
            <i class="bi bi-pencil-square text-amber-500"></i>
            แก้รหัส <code class="text-amber-700 dark:text-amber-300 font-mono" x-text="editing.code"></code>
          </h3>
          <button @click="close()" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-100 transition"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" :action="`{{ url('admin/marketing/referral') }}/${editing.id}/update`" class="p-5">
          @csrf
          <div class="grid grid-cols-2 gap-3 mb-3">
            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Discount Type</label>
              <select name="discount_type" x-model="editing.discount_type"
                      class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
                <option value="percent">Percent (%)</option>
                <option value="fixed">Fixed (THB)</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Discount Value</label>
              <input type="number" step="0.01" name="discount_value" x-model="editing.discount_value"
                     class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3 mb-3">
            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Reward (THB)</label>
              <input type="number" step="0.01" name="reward_value" x-model="editing.reward_value"
                     class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Max Uses (0 = ไม่จำกัด)</label>
              <input type="number" min="0" name="max_uses" x-model="editing.max_uses"
                     class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
            </div>
          </div>
          <div class="mb-4">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Expires (เว้นว่าง = ไม่หมดอายุ)</label>
            <input type="datetime-local" name="expires_at" x-model="editing.expires_at"
                   class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500 outline-none transition">
          </div>
          <div class="mb-4">
            <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
              <input type="checkbox" name="is_active" value="1" x-model="editing.is_active"
                     class="rounded border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-amber-500 focus:ring-amber-500/30">
              <span>เปิดใช้งานรหัสนี้</span>
            </label>
          </div>
          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200/60 dark:border-white/[0.06]">
            <button type="button" @click="close()" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-700/60 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm transition">ยกเลิก</button>
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold bg-gradient-to-br from-amber-500 via-orange-500 to-red-500 text-white shadow-lg shadow-amber-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all">
              <i class="bi bi-check2"></i> บันทึก
            </button>
          </div>
        </form>
      </div>
    </div>
  </template>

</div>
@endsection

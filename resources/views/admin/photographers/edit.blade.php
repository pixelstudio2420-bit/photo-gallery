@extends('layouts.admin')

@section('title', 'แก้ไขช่างภาพ — ' . $photographer->display_name)

@section('content')
<div class="mb-6">
  <a href="{{ route('admin.photographers.show', $photographer) }}" class="inline-flex items-center text-sm text-gray-500 hover:text-indigo-600 transition">
    <i class="bi bi-arrow-left mr-1"></i> กลับไปหน้ารายละเอียด
  </a>
</div>

<div class="flex justify-between items-center mb-6 flex-wrap gap-2">
  <div>
    <h4 class="font-bold mb-1 text-xl tracking-tight">
      <i class="bi bi-pencil-square mr-2 text-indigo-500"></i>แก้ไขช่างภาพ
    </h4>
    <p class="text-gray-500 mb-0 text-sm">{{ $photographer->display_name }} — {{ $photographer->photographer_code }}</p>
  </div>
</div>

@if($errors->any())
<div class="mb-4 flex items-start gap-2 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm border border-red-100 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20">
  <i class="bi bi-exclamation-circle-fill mt-0.5"></i>
  <ul class="list-disc list-inside space-y-0.5">
    @foreach($errors->all() as $error)
    <li>{{ $error }}</li>
    @endforeach
  </ul>
</div>
@endif

<form method="POST" action="{{ route('admin.photographers.update', $photographer) }}">
  @csrf
  @method('PUT')

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- ── Left Column: Profile Info ── --}}
    <div class="lg:col-span-2 space-y-6">
      {{-- Profile Section --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-person-badge mr-1 text-indigo-500"></i>ข้อมูลโปรไฟล์</h6>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">ชื่อที่แสดง <span class="text-red-500">*</span></label>
            <input type="text" name="display_name" value="{{ old('display_name', $photographer->display_name) }}" required
                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Bio</label>
            <textarea name="bio" rows="4"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">{{ old('bio', $photographer->bio) }}</textarea>
            <small class="text-gray-400 mt-1">สูงสุด 1,000 ตัวอักษร</small>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Portfolio URL</label>
            <div class="flex">
              <span class="inline-flex items-center px-3 rounded-l-xl border border-r-0 border-gray-200 bg-gray-50 text-gray-500 text-sm dark:bg-slate-700 dark:border-white/10">
                <i class="bi bi-globe"></i>
              </span>
              <input type="url" name="portfolio_url" value="{{ old('portfolio_url', $photographer->portfolio_url) }}"
                     placeholder="https://example.com"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-r-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            </div>
          </div>
        </div>
      </div>

      {{-- Bank Info Section --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-bank mr-1 text-emerald-500"></i>ข้อมูลบัญชีธนาคาร</h6>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">ชื่อธนาคาร</label>
            <select name="bank_name" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
              <option value="">-- เลือกธนาคาร --</option>
              @php
                $banks = ['กสิกรไทย (KBANK)', 'กรุงเทพ (BBL)', 'กรุงไทย (KTB)', 'ไทยพาณิชย์ (SCB)', 'กรุงศรี (BAY)', 'ทหารไทยธนชาต (TTB)', 'ออมสิน (GSB)', 'ธ.ก.ส. (BAAC)', 'เกียรตินาคินภัทร (KKP)', 'ซีไอเอ็มบี (CIMBT)', 'ยูโอบี (UOBT)', 'แลนด์ แอนด์ เฮ้าส์ (LHBANK)'];
              @endphp
              @foreach($banks as $bank)
              <option value="{{ $bank }}" {{ old('bank_name', $photographer->bank_name) === $bank ? 'selected' : '' }}>{{ $bank }}</option>
              @endforeach
            </select>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">เลขบัญชี</label>
              <input type="text" name="bank_account_number" value="{{ old('bank_account_number', $photographer->bank_account_number) }}"
                     placeholder="xxx-x-xxxxx-x"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">ชื่อบัญชี</label>
              <input type="text" name="bank_account_name" value="{{ old('bank_account_name', $photographer->bank_account_name) }}"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">หมายเลข PromptPay</label>
            <div class="flex">
              <span class="inline-flex items-center px-3 rounded-l-xl border border-r-0 border-gray-200 bg-gray-50 text-gray-500 text-sm dark:bg-slate-700 dark:border-white/10">
                <i class="bi bi-phone"></i>
              </span>
              <input type="text" name="promptpay_number" value="{{ old('promptpay_number', $photographer->promptpay_number) }}"
                     placeholder="08x-xxx-xxxx หรือ เลขบัตรประชาชน"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-r-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ── Right Column: Settings ── --}}
    <div class="space-y-6">
      {{-- Status --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-toggle-on mr-1 text-emerald-500"></i>สถานะ</h6>
        </div>
        <div class="p-6">
          <select name="status" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            <option value="pending" {{ old('status', $photographer->status) === 'pending' ? 'selected' : '' }}>รอตรวจสอบ (Pending)</option>
            <option value="approved" {{ old('status', $photographer->status) === 'approved' ? 'selected' : '' }}>อนุมัติ (Approved)</option>
            <option value="suspended" {{ old('status', $photographer->status) === 'suspended' ? 'selected' : '' }}>ระงับ (Suspended)</option>
          </select>
          @php
            $stMap = [
              'pending' => ['color' => 'amber', 'desc' => 'ช่างภาพยังไม่ได้รับการอนุมัติ ไม่สามารถเข้าใช้งานระบบได้'],
              'approved' => ['color' => 'emerald', 'desc' => 'ช่างภาพสามารถเข้าใช้งานระบบได้ตามปกติ'],
              'suspended' => ['color' => 'red', 'desc' => 'ช่างภาพถูกระงับ ไม่สามารถเข้าใช้งานระบบได้'],
            ];
            $curSt = $stMap[$photographer->status] ?? $stMap['pending'];
          @endphp
          <p class="text-xs text-gray-400 mt-2"><i class="bi bi-info-circle mr-0.5"></i>{{ $curSt['desc'] }}</p>
        </div>
      </div>

      {{-- Commission --}}
      @php
        // Mirror the server-side bounds from ResolvesCommissionBounds so the
        // HTML5 validation matches what the backend will accept. Avoids
        // "you can type it but the form rejects it" confusion.
        $cMin = (float) \App\Models\AppSetting::get('min_commission_rate', 0);
        $cMax = (float) \App\Models\AppSetting::get('max_commission_rate', 100);
      @endphp
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-percent mr-1 text-indigo-500"></i>ค่าคอมมิชชั่น</h6>
        </div>
        <div class="p-6">
          <div class="flex items-center gap-3">
            <input type="number" name="commission_rate" value="{{ old('commission_rate', $photographer->commission_rate) }}" min="{{ $cMin }}" max="{{ $cMax }}" step="1" required
                   class="w-24 px-4 py-2.5 border border-gray-200 rounded-xl text-sm text-center font-semibold focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            <span class="text-gray-500 text-sm font-medium">% ช่างภาพได้รับ <small class="text-gray-400">(ต้องอยู่ระหว่าง {{ $cMin }}–{{ $cMax }}%)</small></span>
          </div>
          <div class="mt-3 p-3 rounded-lg bg-gray-50 dark:bg-white/[0.03]">
            <div class="flex justify-between text-sm">
              <span class="text-gray-500">ช่างภาพได้รับ</span>
              <span class="font-semibold text-indigo-600">{{ number_format($photographer->commission_rate, 0) }}%</span>
            </div>
            <div class="flex justify-between text-sm mt-1">
              <span class="text-gray-500">แพลตฟอร์มได้รับ</span>
              <span class="font-semibold text-gray-500">{{ number_format(100 - $photographer->commission_rate, 0) }}%</span>
            </div>
          </div>
        </div>
      </div>

      {{-- Account Info (read-only) --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-info-circle mr-1 text-blue-500"></i>ข้อมูลบัญชี</h6>
        </div>
        <div class="p-6 space-y-3 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-500">User ID</span>
            <span class="font-medium">{{ $photographer->user_id }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">อีเมล</span>
            <span class="font-medium">{{ $photographer->user->email ?? '-' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">ชื่อ-นามสกุล</span>
            <span class="font-medium">{{ ($photographer->user->first_name ?? '') . ' ' . ($photographer->user->last_name ?? '') }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">รหัสช่างภาพ</span>
            <span class="font-mono text-indigo-600">{{ $photographer->photographer_code }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">สมัครเมื่อ</span>
            <span>{{ $photographer->created_at?->format('d/m/Y H:i') }}</span>
          </div>
        </div>
      </div>

      {{-- Save Button --}}
      <button type="submit" class="w-full inline-flex items-center justify-center py-3 bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl font-medium transition hover:from-indigo-600 hover:to-indigo-700">
        <i class="bi bi-check-lg mr-1"></i> บันทึกการเปลี่ยนแปลง
      </button>
    </div>
  </div>
</form>

{{--
  Subscription management — separate from the profile form because the
  3 actions (assign/cancel/extend) each have their own POST endpoint
  with their own validation. Putting them inside the profile @form
  would either trigger them on every save or require splitting the
  CSRF context. Cleaner to render this section AFTER the profile form
  closes and let each sub-form post independently.
--}}
@if(isset($subSummary) && isset($availablePlans))
@php
  $currentPlan = $subSummary['plan'] ?? null;
  $currentSub  = $subSummary['subscription'] ?? null;
  $isFreeNow   = (bool) ($subSummary['is_free'] ?? true);
  $periodEnd   = $subSummary['current_period_end'] ?? null;
  $daysLeft    = $subSummary['days_until_renewal'] ?? null;
  $willCancel  = (bool) ($subSummary['cancel_at_period_end'] ?? false);
@endphp

<div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
  <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06] flex items-center justify-between gap-2">
    <h6 class="font-semibold text-sm">
      <i class="bi bi-stars mr-1 text-amber-500"></i>การจัดการแผนสมาชิก (Admin)
    </h6>
    @if($currentSub)
      <a href="{{ route('admin.subscriptions.show', $currentSub->id) }}"
         class="text-xs text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1">
        ดูรายละเอียด <i class="bi bi-arrow-right"></i>
      </a>
    @endif
  </div>

  <div class="p-6 space-y-5">

    {{-- Current state pill row --}}
    <div class="flex items-center gap-3 flex-wrap p-4 rounded-lg bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-500/10 dark:to-purple-500/10 border border-indigo-100 dark:border-indigo-500/20">
      <div class="w-12 h-12 rounded-xl bg-white dark:bg-slate-900 shadow-sm flex items-center justify-center shrink-0">
        <i class="bi {{ $currentPlan?->iconClass() ?? 'bi-camera' }} text-xl text-indigo-600"></i>
      </div>
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="font-bold text-base text-slate-900 dark:text-white">{{ $currentPlan?->name ?? 'Free' }}</span>
          @if($willCancel)
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">จะหมดสิ้นรอบ</span>
          @elseif(!$isFreeNow)
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">ใช้งานอยู่</span>
          @endif
          @if($currentSub?->meta['admin_assigned'] ?? false)
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300" title="แผนนี้ถูก comp โดยแอดมิน">
              <i class="bi bi-shield-fill-check"></i> Comped
            </span>
          @endif
        </div>
        <div class="text-xs text-slate-600 dark:text-slate-400 mt-1 flex items-center gap-3 flex-wrap">
          <span><i class="bi bi-hdd-stack mr-1"></i>{{ number_format($subSummary['storage_quota_gb'] ?? 0) }} GB</span>
          <span><i class="bi bi-percent mr-1"></i>{{ rtrim(rtrim(number_format((float) ($subSummary['commission_pct'] ?? 0), 1), '0'), '.') }}% commission</span>
          <span><i class="bi bi-cpu mr-1"></i>{{ number_format($subSummary['ai_credits_cap'] ?? 0) }} credits/mo</span>
          @if($periodEnd)
            <span class="text-amber-600 dark:text-amber-400">
              <i class="bi bi-calendar-event mr-1"></i>
              สิ้นสุด {{ \Carbon\Carbon::parse($periodEnd)->format('d M Y') }}
              @if($daysLeft !== null)
                <span class="opacity-75">(เหลือ {{ (int) $daysLeft }} วัน)</span>
              @endif
            </span>
          @endif
        </div>
      </div>
    </div>

    {{-- Action 1: Assign / Change Plan ─────────────────────────── --}}
    <div x-data="{ open: false, planCode: '{{ $currentPlan?->code ?? 'free' }}', days: '', reason: '' }" class="border border-gray-200 dark:border-white/[0.06] rounded-lg overflow-hidden">
      <button type="button" @click="open = !open"
              class="w-full px-5 py-3 flex items-center justify-between gap-3 text-left bg-gray-50 dark:bg-slate-900/40 hover:bg-gray-100 dark:hover:bg-slate-900/60 transition">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-indigo-100 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
            <i class="bi bi-arrow-left-right"></i>
          </div>
          <div>
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">เปลี่ยน / กำหนดแผนใหม่</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">ใช้สำหรับ comp · เปลี่ยน tier · แก้ปัญหา</p>
          </div>
        </div>
        <i class="bi bi-chevron-down text-slate-400 transition" :class="open ? 'rotate-180' : ''"></i>
      </button>

      <form x-show="open" x-cloak x-collapse method="POST"
            action="{{ route('admin.photographers.assign-plan', $photographer) }}"
            onsubmit="return confirm('ยืนยันการเปลี่ยนแผนสำหรับช่างภาพคนนี้?\n— จะยกเลิกแผนเดิม + สร้างแผนใหม่เป็น active ทันที (ไม่มีการเก็บเงิน)');">
        @csrf
        <div class="p-5 space-y-3 border-t border-gray-200 dark:border-white/[0.06]">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">เลือกแผน</label>
            <select name="plan_code" x-model="planCode"
                    class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              @foreach($availablePlans as $p)
                <option value="{{ $p->code }}">
                  {{ $p->name }}
                  @if($p->price_thb > 0)
                    — ฿{{ number_format($p->price_thb, 0) }}/เดือน
                  @else
                    — ฟรี
                  @endif
                </option>
              @endforeach
            </select>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                ระยะเวลา (วัน)
                <span class="font-normal text-slate-400">— เว้นว่าง = 1 รอบบิลปกติ</span>
              </label>
              <input type="number" name="days" x-model="days" min="1" max="3650" placeholder="เช่น 30"
                     class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                เหตุผล <span class="font-normal text-slate-400">(audit log)</span>
              </label>
              <input type="text" name="reason" x-model="reason" maxlength="500"
                     placeholder="เช่น 'partnership comp', 'dispute resolution'"
                     class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500">
            </div>
          </div>

          <button type="submit"
                  class="w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white text-sm font-semibold transition">
            <i class="bi bi-check-lg"></i> เปลี่ยน/กำหนดแผน
          </button>
        </div>
      </form>
    </div>

    {{-- Action 2: Extend Period ──────────────────────────────── --}}
    <div x-data="{ open: false, days: 7, reason: '' }" class="border border-gray-200 dark:border-white/[0.06] rounded-lg overflow-hidden">
      <button type="button" @click="open = !open"
              class="w-full px-5 py-3 flex items-center justify-between gap-3 text-left bg-gray-50 dark:bg-slate-900/40 hover:bg-gray-100 dark:hover:bg-slate-900/60 transition">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
            <i class="bi bi-calendar-plus"></i>
          </div>
          <div>
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">ขยายเวลาแผนปัจจุบัน</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">refund alternative · service make-good</p>
          </div>
        </div>
        <i class="bi bi-chevron-down text-slate-400 transition" :class="open ? 'rotate-180' : ''"></i>
      </button>

      <form x-show="open" x-cloak x-collapse method="POST"
            action="{{ route('admin.photographers.extend-period', $photographer) }}"
            onsubmit="return confirm('ยืนยันการขยายเวลาแผนปัจจุบัน?');">
        @csrf
        <div class="p-5 space-y-3 border-t border-gray-200 dark:border-white/[0.06]">
          @if($isFreeNow)
            <div class="flex items-start gap-2 p-3 rounded-lg bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-300 text-xs border border-amber-200 dark:border-amber-500/20">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <span>ช่างภาพคนนี้อยู่ที่แผน Free — ขยายเวลาทำได้เฉพาะแผน paid (กรุณาเปลี่ยนแผนก่อน)</span>
            </div>
          @endif
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <button type="button" @click="days = 7"
                    class="px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 text-sm hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition"
                    :class="days == 7 ? 'bg-emerald-50 border-emerald-300 text-emerald-700 dark:bg-emerald-500/15 dark:border-emerald-400/40' : 'text-slate-700 dark:text-slate-300'">
              + 7 วัน
            </button>
            <button type="button" @click="days = 14"
                    class="px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 text-sm hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition"
                    :class="days == 14 ? 'bg-emerald-50 border-emerald-300 text-emerald-700 dark:bg-emerald-500/15 dark:border-emerald-400/40' : 'text-slate-700 dark:text-slate-300'">
              + 14 วัน
            </button>
            <button type="button" @click="days = 30"
                    class="px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 text-sm hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition"
                    :class="days == 30 ? 'bg-emerald-50 border-emerald-300 text-emerald-700 dark:bg-emerald-500/15 dark:border-emerald-400/40' : 'text-slate-700 dark:text-slate-300'">
              + 30 วัน
            </button>
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">หรือกรอกจำนวนวันเอง</label>
            <input type="number" name="days" x-model="days" min="1" max="365" required
                   class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">เหตุผล</label>
            <input type="text" name="reason" x-model="reason" maxlength="500"
                   placeholder="เช่น 'service outage make-good', 'refund credit'"
                   class="w-full px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500">
          </div>
          <button type="submit" :disabled="{{ $isFreeNow ? 'true' : 'false' }}"
                  class="w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white text-sm font-semibold transition disabled:opacity-50 disabled:cursor-not-allowed">
            <i class="bi bi-calendar-plus"></i> <span x-text="`ขยาย ${days} วัน`"></span>
          </button>
        </div>
      </form>
    </div>

    {{-- Action 3: Cancel Subscription ────────────────────────── --}}
    @if(!$isFreeNow)
      <div x-data="{ open: false, reason: '' }" class="border border-rose-200 dark:border-rose-500/20 rounded-lg overflow-hidden">
        <button type="button" @click="open = !open"
                class="w-full px-5 py-3 flex items-center justify-between gap-3 text-left bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-100 dark:hover:bg-rose-500/15 transition">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 flex items-center justify-center">
              <i class="bi bi-x-octagon"></i>
            </div>
            <div>
              <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">ยกเลิกแผนทันที (downgrade เป็น Free)</p>
              <p class="text-xs text-rose-700 dark:text-rose-300">⚠ ใช้สำหรับ TOS violation · refund issued</p>
            </div>
          </div>
          <i class="bi bi-chevron-down text-rose-400 transition" :class="open ? 'rotate-180' : ''"></i>
        </button>

        <form x-show="open" x-cloak x-collapse method="POST"
              action="{{ route('admin.photographers.cancel-subscription', $photographer) }}"
              onsubmit="return confirm('ยืนยันการยกเลิกแผนทันที?\n— ระบบจะดาวน์เกรดเป็น Free ทันที (ไม่รอสิ้นรอบบิล)\n— ฟีเจอร์ paid ทั้งหมดจะถูกตัดทันที');">
          @csrf
          <div class="p-5 space-y-3 border-t border-rose-200 dark:border-rose-500/20">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">เหตุผล (จำเป็น)</label>
              <input type="text" name="reason" x-model="reason" maxlength="500" required
                     placeholder="เช่น 'TOS violation', 'fraud', 'admin error correction'"
                     class="w-full px-3 py-2 border border-rose-200 dark:border-rose-500/30 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-slate-100 focus:ring-2 focus:ring-rose-500">
            </div>
            <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-lg bg-gradient-to-br from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white text-sm font-semibold transition">
              <i class="bi bi-x-octagon"></i> ยกเลิกแผน + ดาวน์เกรด
            </button>
          </div>
        </form>
      </div>
    @endif

    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed border-t border-gray-100 dark:border-white/[0.06] pt-3">
      <i class="bi bi-info-circle"></i>
      ทุก action จะบันทึกใน activity log พร้อม admin id + เหตุผล —
      ดูประวัติได้ที่ <a href="{{ route('admin.activity-log') }}?target_type=PhotographerProfile&target_id={{ $photographer->id }}" class="text-indigo-600 hover:underline">Activity Log</a>
    </p>
  </div>
</div>
@endif
@endsection

@extends('layouts.photographer')

@section('title', 'แผนสมัครสมาชิกของฉัน')

@php
  use App\Models\PhotographerSubscription;
  use App\Models\SubscriptionInvoice;

  $plan     = $summary['plan'] ?? null;
  $sub      = $summary['subscription'] ?? null;
  $isFree   = (bool) ($summary['is_free'] ?? true);
  $inGrace  = (bool) ($summary['in_grace'] ?? false);
  $willCancel = (bool) ($summary['cancel_at_period_end'] ?? false);
  // Pull labels from FeatureFlagController — single source of truth so
  // a label change here also updates the plan-picker, admin features
  // page, and plan-edit screen. Filter by the global flag so admin's
  // kill-switched features don't render as "missing" rows.
  $subs = app(\App\Services\SubscriptionService::class);
  $featureLabels = collect(\App\Http\Controllers\Admin\FeatureFlagController::featureLabels())
    ->filter(fn($_, $code) => $subs->featureGloballyEnabled($code))
    ->map(fn($v) => $v[0])  // dashboard view only needs the label string
    ->all();
@endphp

@section('content')
@php
  // Hero CTA copy adapts to plan state — Free sees a punchy "เริ่มขายเลย",
  // existing paying users see neutral "เปลี่ยน/อัปเกรดแผน". The CTA always
  // routes to the plan picker; the label difference is purely psychological
  // (action verb for cold visitors, neutral for warm ones).
  $heroCta = $isFree
    ? '<a href="'.route('photographer.subscription.plans').'" class="pg-btn-primary"><i class="bi bi-rocket-takeoff"></i> เลือกแผนเริ่มขายวันนี้</a>'
    : '<a href="'.route('photographer.subscription.plans').'" class="pg-btn-primary"><i class="bi bi-arrow-up-circle"></i> เปลี่ยน / อัปเกรดแผน</a>';
  $heroSubtitle = $isFree
    ? 'อยู่ในแผน Free — อัปโหลดได้ '.number_format($summary['storage_quota_gb'], 0).' GB · หัก '.rtrim(rtrim(number_format((float)($summary['commission_pct'] ?? 0), 1), '0'), '.').'% ทุกออเดอร์ · อัปเกรดเพื่อปลดล็อกค่าคอมต่ำลง + พื้นที่เพิ่ม'
    : 'พื้นที่จัดเก็บ · ค่าคอมมิชชั่น · ฟีเจอร์ AI ที่ปลดล็อก';
@endphp
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-stars',
  'eyebrow'  => 'บริการเสริม',
  'title'    => 'แผนสมัครสมาชิกของฉัน',
  'subtitle' => $heroSubtitle,
  'actions'  => $heroCta,
])

@if(session('success'))
  <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-4 py-3">
    <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
  </div>
@endif
@if(session('error'))
  <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-900 text-sm px-4 py-3">
    <i class="bi bi-exclamation-triangle-fill mr-1.5"></i>{{ session('error') }}
  </div>
@endif
@if(session('info'))
  <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 text-sky-900 text-sm px-4 py-3">
    <i class="bi bi-info-circle-fill mr-1.5"></i>{{ session('info') }}
  </div>
@endif

@if($inGrace)
  <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-5">
    <div class="flex items-start gap-3">
      <i class="bi bi-exclamation-triangle-fill text-rose-600 text-xl mt-0.5"></i>
      <div class="flex-1">
        <p class="font-semibold text-rose-900 mb-1">การชำระเงินล่าสุดไม่สำเร็จ</p>
        <p class="text-sm text-rose-800">
          บัญชีของคุณเข้าสู่ช่วงผ่อนผัน — กรุณาอัพเดทวิธีชำระเงินภายใน
          {{ $summary['grace_ends_at']?->format('d M Y') ?? '—' }}
          เพื่อคงสิทธิ์การใช้งาน มิฉะนั้นจะถูกดาวน์เกรดเป็นแผนฟรีอัตโนมัติ
        </p>
      </div>
    </div>
  </div>
@endif

{{-- ─────────────────── Current Plan Card + Storage Usage ─────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

  {{-- Current plan (primary) --}}
  <div class="lg:col-span-2 rounded-xl shadow-sm p-6 text-white"
       style="background:linear-gradient(135deg, {{ $plan?->color_hex ?: '#6366f1' }} 0%, #4f46e5 100%);">
    <div class="flex items-start justify-between mb-4">
      <div>
        <p class="text-white/70 text-xs uppercase tracking-wider font-medium mb-1">แผนปัจจุบัน</p>
        <h2 class="font-bold text-3xl tracking-tight">{{ $plan?->name ?? 'Free' }}</h2>
        @if($plan?->badge)
          <span class="inline-block mt-1 px-2 py-0.5 rounded bg-white/25 text-[11px] font-medium">{{ $plan->badge }}</span>
        @endif
      </div>
      {{-- Plan-aware icon — matches the icon on the plans page card so
           the photographer always sees the same visual token for "your
           current tier". --}}
      <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm border border-white/25 shadow-sm">
        <i class="bi {{ $plan?->iconClass() ?? 'bi-camera' }} text-xl"></i>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mt-4">
      <div>
        <p class="text-white/70 text-[11px] uppercase tracking-wider">พื้นที่จัดเก็บ</p>
        <p class="font-semibold text-lg">{{ number_format($summary['storage_quota_gb'], 0) }} GB</p>
      </div>
      <div>
        <p class="text-white/70 text-[11px] uppercase tracking-wider">ค่าคอมมิชชั่น</p>
        <p class="font-semibold text-lg">
          {{ rtrim(rtrim(number_format((float) ($summary['commission_pct'] ?? 0), 2), '0'), '.') }}%
          <span class="text-[11px] text-white/70">(คุณรับ {{ rtrim(rtrim(number_format((float) ($summary['photographer_share_pct'] ?? 100), 2), '0'), '.') }}%)</span>
        </p>
      </div>
      <div>
        <p class="text-white/70 text-[11px] uppercase tracking-wider">ราคา</p>
        <p class="font-semibold text-lg">
          @if($isFree)
            ฟรี
          @else
            {{ number_format((float) ($plan->price_thb ?? 0), 0) }} บาท/เดือน
          @endif
        </p>
      </div>
      <div>
        <p class="text-white/70 text-[11px] uppercase tracking-wider">สถานะ</p>
        <p class="font-semibold text-lg">
          @if($willCancel)
            <span class="text-amber-200"><i class="bi bi-clock-history"></i> จะหมดสิทธิ์สิ้นรอบ</span>
          @elseif($inGrace)
            <span class="text-rose-200"><i class="bi bi-exclamation-triangle-fill"></i> ช่วงผ่อนผัน</span>
          @elseif($isFree)
            <span>ฟรี</span>
          @else
            <span class="text-emerald-100"><i class="bi bi-check-circle-fill"></i> ใช้งานอยู่</span>
          @endif
        </p>
      </div>
    </div>

    @if(!$isFree && $summary['current_period_end'])
      <p class="text-white/80 text-xs mt-4">
        <i class="bi bi-calendar-event mr-1"></i>
        @if($willCancel)
          สิ้นสุดการใช้งาน: {{ \Carbon\Carbon::parse($summary['current_period_end'])->format('d M Y') }}
        @else
          ต่ออายุถัดไป: {{ \Carbon\Carbon::parse($summary['current_period_end'])->format('d M Y') }}
          @if($summary['days_until_renewal'])
            <span class="ml-1 text-white/70">(อีก {{ (int) $summary['days_until_renewal'] }} วัน)</span>
          @endif
        @endif
      </p>
    @endif
  </div>

  {{-- Storage usage --}}
  <div class="pg-card p-5">
    <p class="text-gray-500 text-xs uppercase tracking-wider font-medium mb-3">การใช้พื้นที่</p>
    <p class="font-bold text-2xl tracking-tight text-gray-900">
      {{ number_format($summary['storage_used_gb'], 2) }}
      <span class="text-sm font-medium text-gray-500">/ {{ number_format($summary['storage_quota_gb'], 0) }} GB</span>
    </p>
    <div class="mt-3 w-full bg-gray-100 rounded-full h-2.5">
      @php
        $pct = (float) $summary['storage_used_pct'];
        $barClass = $summary['storage_critical'] ? 'bg-rose-500'
                     : ($summary['storage_warn'] ? 'bg-amber-500' : 'bg-indigo-500');
      @endphp
      <div class="{{ $barClass }} h-2.5 rounded-full transition-all" style="width: {{ $pct }}%"></div>
    </div>
    <p class="text-[11px] text-gray-500 mt-2">
      ใช้แล้ว {{ number_format($pct, 1) }}%
      @if($summary['storage_critical'])
        — <span class="text-rose-600 font-medium">เต็มใกล้แล้ว! ลบอีเว้นเก่าเพื่อคืนพื้นที่</span>
      @elseif($summary['storage_warn'])
        — <span class="text-amber-600 font-medium">ใกล้เต็มแล้ว</span>
      @endif
    </p>
  </div>
</div>

{{-- ─────────────────── Limits row: Events + AI Credits ─────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

  {{-- Concurrent events usage --}}
  <div class="pg-card p-5">
    <div class="flex items-center justify-between mb-2">
      <p class="text-gray-500 text-xs uppercase tracking-wider font-medium">
        <i class="bi bi-calendar-event mr-1"></i>อีเวนต์ที่เปิดอยู่
      </p>
      @if($summary['events_unlimited'])
        <span class="text-[11px] px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-medium">ไม่จำกัด</span>
      @endif
    </div>
    <p class="font-bold text-2xl tracking-tight text-gray-900">
      {{ (int) $summary['events_used'] }}
      <span class="text-sm font-medium text-gray-500">
        @if($summary['events_unlimited'])
          / ∞
        @else
          / {{ (int) $summary['events_cap'] }} งาน
        @endif
      </span>
    </p>
    @if(!$summary['events_unlimited'])
      <div class="mt-3 w-full bg-gray-100 rounded-full h-2.5">
        @php
          $epct = (float) $summary['events_used_pct'];
          $ebar = $epct >= 100 ? 'bg-rose-500' : ($epct >= 80 ? 'bg-amber-500' : 'bg-indigo-500');
        @endphp
        <div class="{{ $ebar }} h-2.5 rounded-full transition-all" style="width: {{ min(100, $epct) }}%"></div>
      </div>
      @if((int) $summary['events_cap'] === 0)
        <p class="text-[11px] text-gray-500 mt-2">
          แผนปัจจุบันไม่อนุญาตให้เปิดขายอีเวนต์ —
          <a href="{{ route('photographer.subscription.plans') }}" class="text-indigo-600 font-medium hover:underline">อัปเกรดเพื่อเริ่มขาย</a>
        </p>
      @elseif($epct >= 100)
        <p class="text-[11px] text-rose-600 font-medium mt-2">เต็มโควต้าแล้ว — ปิดอีเวนต์เก่าหรืออัปเกรดแผน</p>
      @elseif($epct >= 80)
        <p class="text-[11px] text-amber-600 font-medium mt-2">ใกล้เต็มโควต้าแล้ว</p>
      @endif
    @endif
  </div>

  {{-- Monthly AI credits --}}
  <div class="pg-card p-5">
    <div class="flex items-center justify-between mb-2">
      <p class="text-gray-500 text-xs uppercase tracking-wider font-medium">
        <i class="bi bi-cpu mr-1"></i>เครดิต AI ในรอบนี้
      </p>
      @if($summary['ai_credits_period_end'])
        <span class="text-[11px] text-gray-500">
          รีเซ็ต {{ \Carbon\Carbon::parse($summary['ai_credits_period_end'])->format('d M') }}
        </span>
      @endif
    </div>
    <p class="font-bold text-2xl tracking-tight text-gray-900">
      {{ number_format((int) $summary['ai_credits_used']) }}
      <span class="text-sm font-medium text-gray-500">
        / {{ number_format((int) $summary['ai_credits_cap']) }}
      </span>
    </p>
    @if((int) $summary['ai_credits_cap'] > 0)
      <div class="mt-3 w-full bg-gray-100 rounded-full h-2.5">
        @php
          $apct = (float) $summary['ai_credits_used_pct'];
          $abar = $apct >= 100 ? 'bg-rose-500' : ($apct >= 80 ? 'bg-amber-500' : 'bg-violet-500');
        @endphp
        <div class="{{ $abar }} h-2.5 rounded-full transition-all" style="width: {{ min(100, $apct) }}%"></div>
      </div>
      <p class="text-[11px] text-gray-500 mt-2">
        เหลือ {{ number_format((int) $summary['ai_credits_remaining']) }} เครดิต
        @if($apct >= 100)
          — <span class="text-rose-600 font-medium">หมดแล้ว — รอรอบใหม่หรืออัปเกรดแผน</span>
        @elseif($apct >= 80)
          — <span class="text-amber-600 font-medium">ใกล้หมด</span>
        @endif
      </p>
    @else
      <p class="text-[11px] text-gray-500 mt-3">
        แผนนี้ไม่มีเครดิต AI —
        <a href="{{ route('photographer.subscription.plans') }}" class="text-indigo-600 font-medium hover:underline">ดูแผนที่รองรับ AI</a>
      </p>
    @endif
  </div>

</div>

{{-- ─────────────────── AI Features Card ─────────────────── --}}
<div class="pg-card p-5 mb-6">
  <div class="flex items-center justify-between mb-4">
    <h5 class="font-semibold text-gray-900">
      <i class="bi bi-cpu mr-1.5 text-indigo-500"></i>ฟีเจอร์ AI ที่เปิดใช้งาน
    </h5>
    @if($isFree)
      <a href="{{ route('photographer.subscription.plans') }}"
         class="text-xs font-medium text-indigo-600 hover:text-indigo-700">
        อัพเกรดเพื่อปลดล็อก AI <i class="bi bi-arrow-right"></i>
      </a>
    @endif
  </div>
  @php $activeFeatures = $summary['ai_features'] ?? []; @endphp
  <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
    @foreach($featureLabels as $code => $label)
      @php $enabled = in_array($code, $activeFeatures, true); @endphp
      <div class="flex items-center gap-2.5 rounded-lg border p-3
                  {{ $enabled ? 'border-emerald-200 bg-emerald-50' : 'border-gray-200 bg-gray-50 opacity-60' }}">
        <i class="bi {{ $enabled ? 'bi-check-circle-fill text-emerald-600' : 'bi-lock-fill text-gray-400' }}"></i>
        <span class="text-sm {{ $enabled ? 'text-emerald-900 font-medium' : 'text-gray-500' }}">{{ $label }}</span>
      </div>
    @endforeach
  </div>
</div>

{{-- ─────────────────── Free-plan upgrade nudge ─────────────────────────
     Renders only for Free users. Numbers below pull straight from the
     SubscriptionPlan table — no marketing claims, every value is the
     actual delta the photographer would unlock. The upgrade target is
     the next public paid plan ordered by price (typically Pro/Lite),
     so we always recommend the lowest-cost upgrade path.
     ─────────────────────────────────────────────────────────────────── --}}
@if($isFree)
  @php
    $upgradeTarget = \App\Models\SubscriptionPlan::active()->public()
      ->where('price_thb', '>', 0)
      ->orderBy('price_thb', 'asc')
      ->first();
  @endphp
  @if($upgradeTarget)
    @php
      $deltaStorage = max(0, (int) $upgradeTarget->storage_gb - (int) ($summary['storage_quota_gb'] ?? 0));
      $deltaAi      = max(0, (int) $upgradeTarget->monthly_ai_credits - (int) ($summary['ai_credits_cap'] ?? 0));
      $deltaCommPp  = (float) ($summary['commission_pct'] ?? 0) - (float) $upgradeTarget->commission_pct;
    @endphp
    <div class="mb-6 rounded-2xl overflow-hidden relative shadow-lg shadow-indigo-900/10"
         style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#ec4899 100%);">
      <div class="absolute inset-0 opacity-30 pointer-events-none"
           style="background:radial-gradient(800px 400px at 90% 0%, rgba(255,255,255,.18), transparent 60%);"></div>
      <div class="relative p-5 sm:p-6 text-white">
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div class="flex-1 min-w-[240px]">
            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/15 backdrop-blur-sm text-[10px] font-bold uppercase tracking-[0.14em] mb-3">
              <i class="bi bi-stars"></i> อัปเกรดได้ทันที
            </div>
            <h3 class="text-xl sm:text-2xl font-extrabold tracking-tight leading-tight">
              ขยับมา <span class="text-amber-200">{{ $upgradeTarget->name }}</span> วันนี้ —
              <span class="block sm:inline">ปลดล็อกแบบนี้</span>
            </h3>
            <p class="text-white/80 text-sm mt-1.5">
              เริ่มต้น ฿{{ number_format((float) $upgradeTarget->price_thb, 0) }}/เดือน
              · ยกเลิกได้ทุกเมื่อ · ดาวน์เกรดไฟล์ยังอยู่ครบ
            </p>
          </div>
          <a href="{{ route('photographer.subscription.plans') }}"
             class="inline-flex items-center gap-2 bg-white text-indigo-700 hover:bg-indigo-50 font-bold text-sm px-5 py-2.5 rounded-xl shadow-lg transition-transform hover:-translate-y-0.5">
            ดูแผนทั้งหมด <i class="bi bi-arrow-right"></i>
          </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-5">
          @if($deltaStorage > 0)
          <div class="rounded-xl bg-white/10 backdrop-blur-sm p-3 border border-white/10">
            <p class="text-[10px] uppercase tracking-wider text-white/70 font-bold">+ พื้นที่เก็บภาพ</p>
            <p class="text-2xl font-extrabold mt-0.5">+{{ number_format($deltaStorage) }} <span class="text-sm font-semibold">GB</span></p>
            <p class="text-[11px] text-white/70 mt-0.5">รวม {{ number_format($upgradeTarget->storage_gb) }} GB</p>
          </div>
          @endif
          @if($deltaCommPp > 0)
          <div class="rounded-xl bg-white/10 backdrop-blur-sm p-3 border border-white/10">
            <p class="text-[10px] uppercase tracking-wider text-white/70 font-bold">– ค่าคอมมิชชั่น</p>
            <p class="text-2xl font-extrabold mt-0.5">−{{ rtrim(rtrim(number_format($deltaCommPp, 1), '0'), '.') }}<span class="text-sm font-semibold">pp</span></p>
            <p class="text-[11px] text-white/70 mt-0.5">เหลือ {{ rtrim(rtrim(number_format((float) $upgradeTarget->commission_pct, 1), '0'), '.') }}% ทุกออเดอร์</p>
          </div>
          @endif
          @if($deltaAi > 0)
          <div class="rounded-xl bg-white/10 backdrop-blur-sm p-3 border border-white/10">
            <p class="text-[10px] uppercase tracking-wider text-white/70 font-bold">+ AI Credits / เดือน</p>
            <p class="text-2xl font-extrabold mt-0.5">+{{ number_format($deltaAi) }}</p>
            <p class="text-[11px] text-white/70 mt-0.5">ค้นหาหน้า / ลายน้ำ / OCR</p>
          </div>
          @endif
        </div>

        <p class="text-[11px] text-white/65 mt-3 flex items-center gap-1.5">
          <i class="bi bi-info-circle"></i>
          ตัวเลขทั้งหมดอ่านจากตาราง subscription_plans จริง — ไม่ได้สร้างขึ้นเพื่อโฆษณา
        </p>
      </div>
    </div>
  @endif
@endif

{{-- ─────────────────── Action Buttons ─────────────────── --}}
@if(!$isFree)
<div class="flex flex-wrap gap-2 mb-6">
  @if($willCancel)
    <form method="POST" action="{{ route('photographer.subscription.resume') }}">
      @csrf
      <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
        <i class="bi bi-arrow-clockwise"></i> กู้คืนการต่ออายุ
      </button>
    </form>
  @else
    <form method="POST" action="{{ route('photographer.subscription.cancel') }}"
          onsubmit="return confirm('ยืนยันการยกเลิกต่ออายุ? คุณจะยังใช้งานได้จนถึงสิ้นรอบบิลปัจจุบัน');">
      @csrf
      <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
        <i class="bi bi-x-circle"></i> ยกเลิกการต่ออายุ
      </button>
    </form>
  @endif
  <a href="{{ route('photographer.subscription.invoices') }}"
     class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
    <i class="bi bi-receipt"></i> ใบเสร็จย้อนหลัง
  </a>
</div>
@endif

{{-- ─────────────────── Recent Invoices ─────────────────── --}}
<div class="pg-card overflow-hidden pg-anim d3">
  <div class="pg-card-header">
    <h5 class="pg-section-title m-0"><i class="bi bi-receipt"></i> ใบเสร็จล่าสุด</h5>
    <a href="{{ route('photographer.subscription.invoices') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1 no-underline">
      ดูทั้งหมด <i class="bi bi-arrow-right"></i>
    </a>
  </div>
  @if($invoices->isEmpty())
    <div class="pg-empty">
      <div class="pg-empty-icon"><i class="bi bi-receipt"></i></div>
      <p class="font-medium">ยังไม่มีใบเสร็จ</p>
    </div>
  @else
    <div class="pg-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
      <table class="pg-table">
        <thead>
          <tr>
            <th>เลขที่</th>
            <th>วันที่</th>
            <th class="text-end">ยอด</th>
            <th>สถานะ</th>
          </tr>
        </thead>
        <tbody>
          @foreach($invoices as $inv)
            <tr>
              <td class="is-mono text-gray-700">{{ $inv->invoice_number }}</td>
              <td class="text-gray-600">{{ $inv->created_at?->format('d M Y') }}</td>
              <td class="text-end is-mono font-bold">{{ number_format((float) $inv->amount_thb, 2) }}</td>
              <td>
                @php
                  $iPill = match($inv->status) {
                    SubscriptionInvoice::STATUS_PAID     => ['pg-pill--green', 'ชำระแล้ว'],
                    SubscriptionInvoice::STATUS_PENDING  => ['pg-pill--amber', 'รอชำระ'],
                    SubscriptionInvoice::STATUS_FAILED   => ['pg-pill--rose',  'ล้มเหลว'],
                    SubscriptionInvoice::STATUS_REFUNDED => ['pg-pill--blue',  'คืนเงิน'],
                    default                              => ['pg-pill--gray',  $inv->status],
                  };
                @endphp
                <span class="pg-pill {{ $iPill[0] }}">{{ $iPill[1] }}</span>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection

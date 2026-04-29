@extends('layouts.admin')

@section('title', 'โควต้าพื้นที่ช่างภาพ')

@section('content')
@php
  $quotaSvc = app(\App\Services\StorageQuotaService::class);
  $GB = 1073741824;
@endphp

<div class="flex items-center justify-between mb-4">
  <h4 class="font-bold tracking-tight">
    <i class="bi bi-person-bounding-box mr-2 text-teal-500"></i>โควต้าพื้นที่ช่างภาพ (ตาม Tier)
  </h4>
  <div class="flex items-center gap-2">
    <form method="POST" action="{{ route('admin.settings.photographer-storage.recalc') }}" class="inline">
      @csrf
      <button type="submit" class="inline-flex items-center px-4 py-1.5 bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-300 text-sm font-medium rounded-lg hover:bg-amber-100 transition">
        <i class="bi bi-arrow-clockwise mr-1"></i> คำนวณใหม่ทั้งหมด
      </button>
    </form>
    <a href="{{ route('admin.settings.index') }}" class="inline-flex items-center px-4 py-1.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-300 text-sm font-medium rounded-lg hover:bg-indigo-100 transition">
      <i class="bi bi-arrow-left mr-1"></i> กลับ
    </a>
  </div>
</div>

@if(session('success'))
<div class="bg-emerald-50 text-emerald-700 rounded-lg p-4 text-sm mb-4">
  <i class="bi bi-check-circle mr-1"></i> {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="bg-red-50 text-red-700 rounded-lg p-4 text-sm mb-4 space-y-1">
  @foreach($errors->all() as $err)
  <div><i class="bi bi-exclamation-circle mr-1"></i> {{ $err }}</div>
  @endforeach
</div>
@endif

{{-- ── Snapshot KPIs ──────────────────────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-5">
  <div class="rounded-2xl p-4 border border-teal-200 bg-teal-50 dark:bg-teal-500/10 dark:border-teal-500/30">
    <div class="text-xs text-teal-700 dark:text-teal-200 uppercase">ช่างภาพทั้งหมด</div>
    <div class="text-3xl font-bold text-teal-900 dark:text-teal-50 mt-1">{{ number_format($snapshot['photographers_total'] ?? 0) }}</div>
  </div>
  <div class="rounded-2xl p-4 border border-sky-200 bg-sky-50 dark:bg-sky-500/10 dark:border-sky-500/30">
    <div class="text-xs text-sky-700 dark:text-sky-200 uppercase">ใช้งานรวม</div>
    <div class="text-3xl font-bold text-sky-900 dark:text-sky-50 mt-1">{{ $quotaSvc->humanBytes($snapshot['bytes_used_total'] ?? 0) }}</div>
    <div class="text-[11px] text-sky-700 dark:text-sky-300 mt-1">รวม original + thumb + watermark</div>
  </div>
  <div class="rounded-2xl p-4 border border-amber-200 bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/30">
    <div class="text-xs text-amber-700 dark:text-amber-200 uppercase">ค่าใช้จ่าย R2 ประมาณ</div>
    <div class="text-3xl font-bold text-amber-900 dark:text-amber-50 mt-1">${{ number_format($snapshot['est_cost_usd_month'] ?? 0, 2) }}</div>
    <div class="text-[11px] text-amber-700 dark:text-amber-300 mt-1">/เดือน ($0.015/GB storage)</div>
  </div>
  <div class="rounded-2xl p-4 border border-fuchsia-200 bg-fuchsia-50 dark:bg-fuchsia-500/10 dark:border-fuchsia-500/30">
    <div class="text-xs text-fuchsia-700 dark:text-fuchsia-200 uppercase">เตือนเมื่อใช้เกิน</div>
    <div class="text-3xl font-bold text-fuchsia-900 dark:text-fuchsia-50 mt-1">{{ $settings['photographer_quota_warn_threshold_pct'] }}%</div>
    <div class="text-[11px] text-fuchsia-700 dark:text-fuchsia-300 mt-1">ของโควต้าที่ใช้</div>
  </div>
</div>

{{-- ── Tier breakdown ─────────────────────────────────────────── --}}
<div class="bg-white dark:bg-slate-800 rounded-2xl p-5 mb-5 border border-gray-100 dark:border-white/5">
  <h3 class="font-semibold text-sm text-slate-800 dark:text-gray-100 mb-3">
    <i class="bi bi-diagram-3 text-indigo-500 mr-1"></i> แยกตาม Tier
  </h3>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    @foreach (['creator' => 'Creator (ฟรี)', 'seller' => 'Seller', 'pro' => 'Pro'] as $tierKey => $tierLabel)
      @php
        $row = $snapshot['by_tier'][$tierKey] ?? ['count' => 0, 'bytes' => 0];
        $quotaBytes = $quotaSvc->tierQuotaBytes($tierKey);
        $aggregateQuota = $quotaBytes * $row['count'];
        $pct = $aggregateQuota > 0 ? min(100, ($row['bytes'] / $aggregateQuota) * 100) : 0;
      @endphp
      <div class="rounded-xl p-4 border border-gray-100 dark:border-white/5 bg-gray-50 dark:bg-slate-900/50">
        <div class="flex items-center justify-between mb-2">
          <span class="font-semibold text-sm text-slate-800 dark:text-gray-100">{{ $tierLabel }}</span>
          <span class="text-xs text-gray-500">{{ number_format($row['count']) }} คน</span>
        </div>
        <div class="text-2xl font-bold text-slate-700 dark:text-gray-200">{{ $quotaSvc->humanBytes($row['bytes']) }}</div>
        <div class="text-xs text-gray-500 mt-1">
          โควต้ารวม {{ $quotaSvc->humanBytes($aggregateQuota) }} ({{ number_format($pct, 0) }}%)
        </div>
        <div class="mt-2 h-1.5 bg-gray-200 dark:bg-slate-700 rounded-full overflow-hidden">
          <div class="h-full bg-gradient-to-r from-teal-400 to-sky-500" style="width: {{ $pct }}%"></div>
        </div>
      </div>
    @endforeach
  </div>
</div>

<form method="POST" action="{{ route('admin.settings.photographer-storage.update') }}" class="space-y-5">
  @csrf

  {{-- Master enforcement + warn threshold --}}
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div>
        <label class="flex items-start gap-3 cursor-pointer">
          <input type="checkbox" name="photographer_quota_enforcement_enabled" value="1"
                 {{ ($settings['photographer_quota_enforcement_enabled'] ?? '1') === '1' ? 'checked' : '' }}
                 class="mt-1 w-5 h-5 accent-teal-500">
          <div>
            <div class="font-semibold text-slate-800 dark:text-gray-100">บังคับใช้โควต้า (Master Switch)</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
              ปิดสวิตช์นี้เพื่อให้ทุกคนอัปโหลดได้ไม่จำกัดชั่วคราว (เช่นตอน migrate ข้อมูล)
            </div>
          </div>
        </label>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">เตือนเมื่อใช้เกิน (%)</label>
        <input type="number" name="photographer_quota_warn_threshold_pct" min="0" max="100"
               value="{{ old('photographer_quota_warn_threshold_pct', $settings['photographer_quota_warn_threshold_pct']) }}"
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-700 text-sm">
        <div class="text-[11px] text-gray-500 mt-1">ช่างภาพจะเห็น banner เชิญชวนอัปเกรดเมื่อใช้เกิน % นี้</div>
      </div>
    </div>
  </div>

  {{-- Tier quotas --}}
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
    <h3 class="font-semibold text-sm text-slate-800 dark:text-gray-100 mb-3">
      <i class="bi bi-hdd text-teal-500 mr-1"></i> โควต้าต่อ Tier (GB)
    </h3>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
      แต่ละรูปเก็บ 3 ไฟล์ (original + thumbnail + watermark) — ระบบคูณ 3 ให้อัตโนมัติเพื่อกันบิลบาน
    </p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Creator (ฟรี)</label>
        <input type="number" name="photographer_quota_creator_gb" min="0" max="100000"
               value="{{ old('photographer_quota_creator_gb', $settings['photographer_quota_creator_gb']) }}"
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-700 text-sm">
        <div class="text-[11px] text-gray-500 mt-1">แนะนำ 5 GB (ลอง 1 event ได้)</div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Seller (฿{{ $settings['subscription_price_seller'] }}/เดือน)</label>
        <input type="number" name="photographer_quota_seller_gb" min="0" max="1000000"
               value="{{ old('photographer_quota_seller_gb', $settings['photographer_quota_seller_gb']) }}"
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-700 text-sm">
        <div class="text-[11px] text-gray-500 mt-1">แนะนำ 50 GB</div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pro (฿{{ $settings['subscription_price_pro'] }}/เดือน)</label>
        <input type="number" name="photographer_quota_pro_gb" min="0" max="10000000"
               value="{{ old('photographer_quota_pro_gb', $settings['photographer_quota_pro_gb']) }}"
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-700 text-sm">
        <div class="text-[11px] text-gray-500 mt-1">แนะนำ 500 GB</div>
      </div>
    </div>
  </div>

  {{-- Commission + pricing --}}
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-5">
    <h3 class="font-semibold text-sm text-slate-800 dark:text-gray-100 mb-3">
      <i class="bi bi-cash-stack text-emerald-500 mr-1"></i> Commission & Platform Fee
    </h3>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
      ใช้คำนวณ "ค่าประหยัดถ้าอัปเกรด" ที่โชว์ให้ช่างภาพเห็นใน dashboard ของเขา
      <strong>หมายเหตุ:</strong> ค่าพวกนี้ต้อง sync กับระบบ order/payout ด้วย (ในเวอร์ชันนี้ยังไม่บังคับใช้ในการคำนวณจ่ายเงินจริง)
    </p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      @foreach(['creator' => 'Creator', 'seller' => 'Seller', 'pro' => 'Pro'] as $t => $label)
      <div class="rounded-xl p-3 border border-gray-100 dark:border-white/5 bg-gray-50 dark:bg-slate-900/30">
        <div class="text-sm font-semibold mb-2">{{ $label }}</div>
        <label class="block text-xs text-gray-600 dark:text-gray-400 mt-2">Commission (%)</label>
        <input type="number" name="commission_pct_{{ $t }}" min="0" max="100" step="0.01"
               value="{{ old('commission_pct_'.$t, $settings['commission_pct_'.$t]) }}"
               class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-700 text-sm mt-1">
        <label class="block text-xs text-gray-600 dark:text-gray-400 mt-2">Platform Fee (฿/รูป)</label>
        <input type="number" name="platform_fee_per_photo_{{ $t }}" min="0" max="1000"
               value="{{ old('platform_fee_per_photo_'.$t, $settings['platform_fee_per_photo_'.$t]) }}"
               class="w-full px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-700 text-sm mt-1">
      </div>
      @endforeach
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Subscription Seller (฿/เดือน)</label>
        <input type="number" name="subscription_price_seller" min="0" max="1000000"
               value="{{ old('subscription_price_seller', $settings['subscription_price_seller']) }}"
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-700 text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Subscription Pro (฿/เดือน)</label>
        <input type="number" name="subscription_price_pro" min="0" max="1000000"
               value="{{ old('subscription_price_pro', $settings['subscription_price_pro']) }}"
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-700 text-sm">
      </div>
    </div>
  </div>

  <div class="flex justify-end">
    <button type="submit" class="inline-flex items-center px-6 py-2.5 bg-gradient-to-r from-teal-500 to-sky-500 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition">
      <i class="bi bi-check-lg mr-2"></i> บันทึก
    </button>
  </div>
</form>

{{-- ── Top usage table ────────────────────────────────────────── --}}
<div class="bg-white dark:bg-slate-800 rounded-2xl p-5 mt-5 border border-gray-100 dark:border-white/5">
  <h3 class="font-semibold text-sm text-slate-800 dark:text-gray-100 mb-3">
    <i class="bi bi-bar-chart text-violet-500 mr-1"></i> 20 ช่างภาพที่ใช้พื้นที่สูงสุด
  </h3>
  @if(count($snapshot['top_users'] ?? []) === 0)
    <div class="text-sm text-gray-500 py-4 text-center">ยังไม่มีข้อมูลการใช้งาน</div>
  @else
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-xs text-gray-500 uppercase border-b border-gray-100 dark:border-white/5">
          <th class="text-left py-2">Photographer</th>
          <th class="text-left py-2">Tier</th>
          <th class="text-right py-2">ใช้ไป</th>
          <th class="text-right py-2">โควต้า</th>
          <th class="text-right py-2">%</th>
        </tr>
      </thead>
      <tbody>
        @foreach($snapshot['top_users'] as $p)
          @php
            $effQuota = (int) ($p->storage_quota_bytes ?: $quotaSvc->tierQuotaBytes((string) $p->tier));
            $pct = $effQuota > 0 ? min(100, ((int) $p->storage_used_bytes / $effQuota) * 100) : 0;
            $barColor = $pct >= 90 ? 'bg-red-500' : ($pct >= 70 ? 'bg-amber-500' : 'bg-teal-500');
          @endphp
          <tr class="border-b border-gray-50 dark:border-white/5 hover:bg-gray-50 dark:hover:bg-slate-900/30">
            <td class="py-2.5">
              <div class="font-medium text-slate-700 dark:text-gray-200">
                {{ $p->display_name ?: optional($p->user)->email ?: '—' }}
              </div>
              <div class="text-[11px] text-gray-500">{{ optional($p->user)->email }}</div>
            </td>
            <td class="py-2.5">
              <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold
                @if($p->tier === 'pro') bg-fuchsia-100 text-fuchsia-700
                @elseif($p->tier === 'seller') bg-sky-100 text-sky-700
                @else bg-gray-100 text-gray-600
                @endif">{{ $p->tier ?: 'creator' }}</span>
            </td>
            <td class="py-2.5 text-right tabular-nums">{{ $quotaSvc->humanBytes((int) $p->storage_used_bytes) }}</td>
            <td class="py-2.5 text-right tabular-nums text-gray-500">{{ $quotaSvc->humanBytes($effQuota) }}</td>
            <td class="py-2.5 text-right tabular-nums">
              <div class="inline-flex items-center gap-2">
                <span class="font-semibold">{{ number_format($pct, 0) }}%</span>
                <div class="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                  <div class="h-full {{ $barColor }}" style="width: {{ $pct }}%"></div>
                </div>
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
  <div class="text-[11px] text-gray-500 mt-3">
    Snapshot cached until: {{ $snapshot['computed_at'] ?? '-' }}
  </div>
</div>
@endsection

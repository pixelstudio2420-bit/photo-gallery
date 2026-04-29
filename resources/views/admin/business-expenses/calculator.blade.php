@extends('layouts.admin')

@section('title', 'คำนวณต้นทุน / Calculator')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
  <h4 class="font-bold mb-0 tracking-tight">
    <i class="bi bi-calculator mr-2 text-indigo-500"></i>คำนวณต้นทุน / Per-Service Calculator
  </h4>
  <a href="{{ route('admin.business-expenses.index') }}"
     class="px-4 py-2 border border-gray-200 dark:border-white/5 dark:text-gray-200 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-slate-700">
    <i class="bi bi-arrow-left mr-1"></i>กลับรายการค่าใช้จ่าย
  </a>
</div>

{{-- ─── What-if Revenue Input ─────────────────────────────────── --}}
<form method="GET" class="bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-500/10 dark:to-purple-500/10 border border-indigo-100 dark:border-indigo-500/30 rounded-2xl p-5 mb-4">
  <div class="flex flex-wrap items-end gap-3">
    <div class="flex-1 min-w-[200px]">
      <label class="block text-xs font-medium text-indigo-700 dark:text-indigo-300 mb-1">
        <i class="bi bi-cash-coin mr-1"></i>รายรับต่อเดือนที่คาดการณ์ (what-if)
      </label>
      <input type="number" name="revenue" step="0.01" min="0" value="{{ $projectedRevenue }}"
             class="w-full border border-indigo-200 dark:border-indigo-500/30 dark:bg-slate-800 dark:text-gray-100 rounded-lg px-3 py-2 text-sm font-mono">
      <p class="text-xs text-indigo-700/70 dark:text-indigo-300/70 mt-1">
        ค่าเริ่มต้น: เฉลี่ย 3 เดือนย้อนหลัง ({{ number_format($avgMonthlyRevenue, 2) }} บาท)
      </p>
    </div>
    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium">
      <i class="bi bi-arrow-repeat mr-1"></i>คำนวณใหม่
    </button>
  </div>
</form>

{{-- ─── Top-level KPI ─────────────────────────────────────────── --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-4">
    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">รายจ่ายรวม/เดือน</div>
    <div class="text-2xl font-bold text-rose-500 dark:text-rose-400 font-mono">
      -{{ number_format($totalMonthly, 2) }}
    </div>
    <div class="text-xs text-gray-400 mt-1">/ {{ number_format($totalYearly, 0) }} ต่อปี</div>
  </div>
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-4">
    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">รายรับ (คาดการณ์)</div>
    <div class="text-2xl font-bold text-emerald-500 dark:text-emerald-400 font-mono">
      +{{ number_format($projectedRevenue, 2) }}
    </div>
  </div>
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-4">
    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">กำไรขั้นต้น / เดือน</div>
    <div class="text-2xl font-bold font-mono {{ $grossMargin >= 0 ? 'text-indigo-600 dark:text-indigo-300' : 'text-rose-500' }}">
      {{ $grossMargin >= 0 ? '+' : '' }}{{ number_format($grossMargin, 2) }}
    </div>
    @if($marginPct !== null)
      <div class="text-xs text-gray-400 mt-1">Margin: {{ $marginPct }}%</div>
    @endif
  </div>
  <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-4">
    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Break-even ratio</div>
    @if($breakEvenMult !== null)
      <div class="text-2xl font-bold text-slate-700 dark:text-gray-100 font-mono">×{{ $breakEvenMult }}</div>
      <div class="text-xs text-gray-400 mt-1">ของรายรับเดือนนี้</div>
    @else
      <div class="text-2xl font-bold text-slate-400">n/a</div>
    @endif
  </div>
</div>

{{-- ─── Per-Service Breakdown Table ──────────────────────────── --}}
<div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-5 mb-4">
  <h5 class="font-semibold text-slate-800 dark:text-gray-100 mb-3">
    <i class="bi bi-diagram-3 mr-1 text-emerald-500"></i>ต้นทุนต่อบริการ / Per-Service Cost Allocation
  </h5>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-xs uppercase text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-white/5">
        <tr>
          <th class="py-2 text-left">บริการ</th>
          <th class="py-2 text-right">ค่าใช้จ่าย/เดือน (THB)</th>
          <th class="py-2 text-right">% ของรายจ่าย</th>
          <th class="py-2">สัดส่วน</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/5">
        @forelse($byService as $svc => $amt)
          @php $pct = $totalMonthly > 0 ? round(($amt / $totalMonthly) * 100, 1) : 0; @endphp
          <tr>
            <td class="py-2 font-medium text-slate-700 dark:text-gray-200">{{ $services[$svc] ?? $svc }}</td>
            <td class="py-2 text-right font-mono">{{ number_format($amt, 2) }}</td>
            <td class="py-2 text-right text-gray-500">{{ $pct }}%</td>
            <td class="py-2 pl-4" style="min-width:180px;">
              <div class="h-2 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-emerald-400 to-emerald-600 rounded-full" style="width: {{ max(2, $pct) }}%"></div>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="py-6 text-center text-gray-400">ไม่มีข้อมูล</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- ─── Per-Category Breakdown ───────────────────────────────── --}}
<div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-5 mb-4">
  <h5 class="font-semibold text-slate-800 dark:text-gray-100 mb-3">
    <i class="bi bi-pie-chart mr-1 text-indigo-500"></i>ต้นทุนตามหมวดหมู่
  </h5>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-xs uppercase text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-white/5">
        <tr>
          <th class="py-2 text-left">หมวดหมู่</th>
          <th class="py-2 text-right">ค่าใช้จ่าย/เดือน</th>
          <th class="py-2 text-right">%</th>
          <th class="py-2">สัดส่วน</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/5">
        @foreach($byCategory as $cat => $amt)
          @php $pct = $totalMonthly > 0 ? round(($amt / $totalMonthly) * 100, 1) : 0; @endphp
          <tr>
            <td class="py-2 font-medium text-slate-700 dark:text-gray-200">{{ $categories[$cat] ?? $cat }}</td>
            <td class="py-2 text-right font-mono">{{ number_format($amt, 2) }}</td>
            <td class="py-2 text-right text-gray-500">{{ $pct }}%</td>
            <td class="py-2 pl-4" style="min-width:180px;">
              <div class="h-2 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-indigo-400 to-indigo-600 rounded-full" style="width: {{ max(2, $pct) }}%"></div>
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

{{-- ─── Unit Costs ────────────────────────────────────────────── --}}
<div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-5">
  <h5 class="font-semibold text-slate-800 dark:text-gray-100 mb-3">
    <i class="bi bi-boxes mr-1 text-amber-500"></i>ต้นทุนต่อหน่วย (เฉลี่ย 30 วันย้อนหลัง)
  </h5>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <div class="border border-gray-100 dark:border-white/5 rounded-xl p-4 text-center">
      <div class="text-xs text-gray-500 dark:text-gray-400">ต้นทุน / อีเวนต์</div>
      <div class="text-2xl font-bold mt-1 font-mono {{ $unitCosts['per_event'] ? 'text-amber-500' : 'text-slate-400' }}">
        {{ $unitCosts['per_event'] !== null ? number_format($unitCosts['per_event'], 2) : 'n/a' }}
      </div>
      <div class="text-xs text-gray-400">THB / event</div>
    </div>
    <div class="border border-gray-100 dark:border-white/5 rounded-xl p-4 text-center">
      <div class="text-xs text-gray-500 dark:text-gray-400">ต้นทุน / รูป</div>
      <div class="text-2xl font-bold mt-1 font-mono {{ $unitCosts['per_photo'] ? 'text-amber-500' : 'text-slate-400' }}">
        {{ $unitCosts['per_photo'] !== null ? number_format($unitCosts['per_photo'], 4) : 'n/a' }}
      </div>
      <div class="text-xs text-gray-400">THB / photo</div>
    </div>
    <div class="border border-gray-100 dark:border-white/5 rounded-xl p-4 text-center">
      <div class="text-xs text-gray-500 dark:text-gray-400">ต้นทุน / ออเดอร์</div>
      <div class="text-2xl font-bold mt-1 font-mono {{ $unitCosts['per_order'] ? 'text-amber-500' : 'text-slate-400' }}">
        {{ $unitCosts['per_order'] !== null ? number_format($unitCosts['per_order'], 2) : 'n/a' }}
      </div>
      <div class="text-xs text-gray-400">THB / order</div>
    </div>
  </div>
  <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">
    <i class="bi bi-info-circle"></i>
    ตัวเลขคำนวณจาก: รายจ่ายรวม/เดือน ÷ จำนวนอีเวนต์ใหม่ในช่วง 30 วันที่ผ่านมา (หรือจำนวนรูป/ออเดอร์ตามลำดับ)
  </p>
</div>
@endsection

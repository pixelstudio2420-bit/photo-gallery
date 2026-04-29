@extends('layouts.admin')

@section('title', 'วิเคราะห์ต้นทุน')

@section('content')
<div class="flex justify-between items-center mb-6 flex-wrap gap-2">
  <div>
    <h4 class="font-bold mb-1 text-xl tracking-tight">
      <i class="bi bi-bar-chart-line mr-2 text-indigo-500"></i>วิเคราะห์ต้นทุน
    </h4>
    <p class="text-gray-500 dark:text-gray-400 mb-0 text-sm">วิเคราะห์ต้นทุนช่างภาพ รายได้แพลตฟอร์ม และอัตรากำไรต่ออีเวนต์</p>
  </div>
  <a href="{{ route('admin.tax.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition dark:bg-white/[0.06] dark:text-gray-300 no-underline">
    <i class="bi bi-arrow-left mr-1.5"></i> แดชบอร์ดภาษี
  </a>
</div>

{{-- Stats Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
  @php
    $cards = [
      ['icon' => 'bi-cash-stack', 'color' => 'indigo', 'hex' => '#6366f1', 'value' => $stats['total_revenue'], 'label' => 'รายได้รวม', 'prefix' => '฿'],
      ['icon' => 'bi-camera', 'color' => 'red', 'hex' => '#ef4444', 'value' => $stats['photographer_costs'], 'label' => 'ต้นทุนช่างภาพ', 'prefix' => '฿'],
      ['icon' => 'bi-building', 'color' => 'emerald', 'hex' => '#10b981', 'value' => $stats['platform_revenue'], 'label' => 'รายได้แพลตฟอร์ม', 'prefix' => '฿'],
      ['icon' => 'bi-pie-chart', 'color' => 'amber', 'hex' => '#f59e0b', 'value' => $stats['cost_ratio'], 'label' => 'สัดส่วนต้นทุน', 'suffix' => '%', 'decimal' => 1],
    ];
  @endphp
  @foreach($cards as $c)
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-4 px-4">
      <div class="flex items-center gap-3">
        <div class="flex items-center justify-center w-11 h-11 rounded-xl shrink-0" style="background:{{ $c['hex'] }}12;">
          <i class="bi {{ $c['icon'] }} text-lg" style="color:{{ $c['hex'] }};"></i>
        </div>
        <div class="min-w-0">
          <div class="font-bold text-lg text-gray-900 dark:text-white truncate">
            {{ $c['prefix'] ?? '' }}{{ number_format($c['value'], $c['decimal'] ?? 0) }}{{ $c['suffix'] ?? '' }}
          </div>
          <small class="text-gray-500 dark:text-gray-400">{{ $c['label'] }}</small>
        </div>
      </div>
    </div>
  </div>
  @endforeach
</div>

{{-- Cost Ratio Visual --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06] mb-6">
  <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
    <h6 class="font-semibold text-sm mb-0">
      <i class="bi bi-pie-chart mr-1.5 text-indigo-500"></i>สัดส่วนต้นทุนและกำไร
    </h6>
  </div>
  <div class="p-6">
    @php
      $costPct = $stats['cost_ratio'];
      $profitPct = 100 - $costPct;
    @endphp
    <div class="flex items-center gap-4 mb-3">
      <div class="flex-1">
        <div class="h-4 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden flex">
          <div class="h-full bg-red-400 rounded-l-full transition-all" style="width:{{ min($costPct, 100) }}%"></div>
          <div class="h-full bg-emerald-400 rounded-r-full transition-all" style="width:{{ max($profitPct, 0) }}%"></div>
        </div>
      </div>
    </div>
    <div class="flex gap-6 text-sm">
      <div class="flex items-center gap-2">
        <span class="w-3 h-3 rounded-full bg-red-400 shrink-0"></span>
        <span class="text-gray-600 dark:text-gray-400">ต้นทุนช่างภาพ {{ number_format($costPct, 1) }}%</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="w-3 h-3 rounded-full bg-emerald-400 shrink-0"></span>
        <span class="text-gray-600 dark:text-gray-400">รายได้แพลตฟอร์ม {{ number_format($profitPct, 1) }}%</span>
      </div>
    </div>
  </div>
</div>

{{-- Event Cost Table --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
  <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
    <h6 class="font-semibold text-sm mb-0">
      <i class="bi bi-calendar-event mr-1.5 text-indigo-500"></i>ต้นทุนรายอีเวนต์ (Top 20)
    </h6>
  </div>

  @if($eventCosts->isEmpty())
  <div class="p-12 text-center">
    <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-gray-100 dark:bg-white/[0.06] mx-auto mb-4">
      <i class="bi bi-inbox text-2xl text-gray-400"></i>
    </div>
    <h6 class="font-semibold text-gray-500 dark:text-gray-400 mb-1">ยังไม่มีข้อมูล</h6>
    <p class="text-gray-400 text-sm">จะแสดงข้อมูลเมื่อมีออเดอร์ที่สำเร็จแล้ว</p>
  </div>
  @else
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="bg-gray-50 dark:bg-white/[0.03]">
          <th class="px-6 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">อีเวนต์</th>
          <th class="px-6 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">คำสั่งซื้อ</th>
          <th class="px-6 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">รายได้รวม (฿)</th>
          <th class="px-6 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">ต้นทุนช่างภาพ (฿)</th>
          <th class="px-6 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">รายได้แพลตฟอร์ม (฿)</th>
          <th class="px-6 py-3 text-center font-semibold text-gray-600 dark:text-gray-300" style="min-width:180px;">อัตรากำไร</th>
        </tr>
      </thead>
      <tbody>
        @foreach($eventCosts as $event)
        <tr class="border-b border-gray-50 dark:border-white/[0.04] hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
          <td class="px-6 py-3">
            <div class="font-medium text-gray-800 dark:text-gray-200 truncate max-w-[250px]" title="{{ $event->name }}">
              {{ $event->name }}
            </div>
          </td>
          <td class="px-6 py-3 text-center">
            <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
              {{ $event->orders_count }}
            </span>
          </td>
          <td class="px-6 py-3 text-right font-medium text-gray-700 dark:text-gray-300">
            {{ number_format($event->orders_sum_total ?? 0, 2) }}
          </td>
          <td class="px-6 py-3 text-right text-red-600 dark:text-red-400">
            {{ number_format($event->photographer_cost, 2) }}
          </td>
          <td class="px-6 py-3 text-right font-semibold {{ $event->platform_revenue >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
            {{ number_format($event->platform_revenue, 2) }}
          </td>
          <td class="px-6 py-3">
            <div class="flex items-center gap-2">
              <div class="flex-1 h-2 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                @php $barColor = $event->margin_pct >= 30 ? 'bg-emerald-400' : ($event->margin_pct >= 15 ? 'bg-amber-400' : 'bg-red-400'); @endphp
                <div class="{{ $barColor }} h-full rounded-full transition-all" style="width:{{ min(max($event->margin_pct, 0), 100) }}%"></div>
              </div>
              <span class="text-xs font-semibold w-12 text-right {{ $event->margin_pct >= 30 ? 'text-emerald-600 dark:text-emerald-400' : ($event->margin_pct >= 15 ? 'text-amber-600 dark:text-amber-400' : 'text-red-500 dark:text-red-400') }}">
                {{ number_format($event->margin_pct, 1) }}%
              </span>
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
</div>
@endsection

@extends('layouts.admin')

@section('title', 'Coupon Analytics')

@section('content')
<div class="space-y-5">

  {{-- Header --}}
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100">
        <i class="bi bi-graph-up-arrow text-indigo-500 mr-2"></i>Coupon Analytics
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">วิเคราะห์ผลการใช้งานคูปอง และ ROI</p>
    </div>
    <div class="flex gap-2">
      <a href="{{ route('admin.coupons.index') }}" class="px-4 py-2 border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-50">
        <i class="bi bi-list"></i> ทั้งหมด
      </a>
      <a href="{{ route('admin.coupons.bulk-create') }}" class="px-4 py-2 bg-gradient-to-br from-emerald-500 to-emerald-600 text-white rounded-xl text-sm font-medium hover:shadow-lg">
        <i class="bi bi-plus-square-dotted"></i> สร้างหลายรายการ
      </a>
      <a href="{{ route('admin.coupons.create') }}" class="px-4 py-2 bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl text-sm font-medium hover:shadow-lg">
        <i class="bi bi-plus-lg"></i> สร้างคูปอง
      </a>
    </div>
  </div>

  {{-- Period selector --}}
  <div class="flex gap-2">
    @foreach(['7d' => '7 วัน', '30d' => '30 วัน', '90d' => '90 วัน', '365d' => '1 ปี'] as $key => $label)
    <a href="?period={{ $key }}" class="px-4 py-1.5 rounded-lg text-sm font-medium transition
             {{ $period === $key ? 'bg-indigo-500 text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' }}">
      {{ $label }}
    </a>
    @endforeach
  </div>

  {{-- Stats Cards --}}
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-2xl p-5">
      <div class="flex items-start justify-between">
        <div>
          <div class="text-xs text-indigo-100">Total Redemptions</div>
          <div class="text-3xl font-bold mt-1">{{ number_format($stats['total_redemptions']) }}</div>
          <div class="text-xs text-indigo-100 mt-2">
            <i class="bi bi-arrow-up-right"></i> +{{ number_format($stats['period_redemptions']) }} ในช่วง {{ $period }}
          </div>
        </div>
        <i class="bi bi-ticket-detailed text-3xl text-indigo-200 opacity-70"></i>
      </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-5">
      <div class="flex items-start justify-between mb-2">
        <div>
          <div class="text-xs text-gray-500">Total Discount</div>
          <div class="text-3xl font-bold mt-1 text-red-600">-฿{{ number_format($stats['total_discount'], 0) }}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-red-100 text-red-600 flex items-center justify-center"><i class="bi bi-tag"></i></div>
      </div>
      <div class="text-xs text-gray-500">ใน {{ $period }}: -฿{{ number_format($stats['period_discount'], 0) }}</div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-5">
      <div class="flex items-start justify-between mb-2">
        <div>
          <div class="text-xs text-gray-500">Revenue Generated</div>
          <div class="text-3xl font-bold mt-1 text-emerald-600">฿{{ number_format($stats['revenue_generated'], 0) }}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center"><i class="bi bi-graph-up"></i></div>
      </div>
      <div class="text-xs text-gray-500">ROI: {{ $stats['total_discount'] > 0 ? number_format(($stats['revenue_generated'] / $stats['total_discount']) * 100, 1) : 'N/A' }}%</div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl p-5">
      <div class="flex items-start justify-between mb-2">
        <div>
          <div class="text-xs text-gray-500">Unique Customers</div>
          <div class="text-3xl font-bold mt-1 text-purple-600">{{ number_format($stats['unique_customers']) }}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center"><i class="bi bi-people"></i></div>
      </div>
      <div class="text-xs text-gray-500">Avg Discount: ฿{{ number_format($stats['avg_discount'], 0) }}</div>
    </div>
  </div>

  {{-- Coupon Status Breakdown --}}
  <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
    @php
      $statusCards = [
        ['key' => 'active',    'label' => 'เปิดใช้งาน',  'value' => $statusBreakdown['active'] ?? 0, 'color' => 'emerald', 'icon' => 'check-circle'],
        ['key' => 'scheduled', 'label' => 'รอเปิด',      'value' => $statusBreakdown['scheduled'] ?? 0, 'color' => 'blue',   'icon' => 'clock'],
        ['key' => 'expired',   'label' => 'หมดอายุ',     'value' => $statusBreakdown['expired'] ?? 0, 'color' => 'red',     'icon' => 'x-circle'],
        ['key' => 'exhausted', 'label' => 'ใช้ครบแล้ว',  'value' => $statusBreakdown['exhausted'] ?? 0, 'color' => 'amber',  'icon' => 'slash-circle'],
        ['key' => 'disabled',  'label' => 'ปิดใช้งาน',   'value' => $statusBreakdown['disabled'] ?? 0, 'color' => 'gray',    'icon' => 'dash-circle'],
      ];
    @endphp
    @foreach($statusCards as $s)
    <div class="bg-white border border-gray-100 rounded-xl p-3">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-{{ $s['color'] }}-100 text-{{ $s['color'] }}-600 flex items-center justify-center">
          <i class="bi bi-{{ $s['icon'] }} text-sm"></i>
        </div>
        <div>
          <div class="text-xs text-gray-500">{{ $s['label'] }}</div>
          <div class="text-lg font-bold text-{{ $s['color'] }}-600">{{ number_format($s['value']) }}</div>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  {{-- Redemption Trend Chart --}}
  <div class="bg-white border border-gray-100 rounded-2xl p-5">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-bold text-slate-800">
        <i class="bi bi-bar-chart text-indigo-500 mr-1"></i>การใช้งาน 30 วันล่าสุด
      </h3>
    </div>
    <div class="relative h-64">
      <canvas id="trendChart"></canvas>
    </div>
  </div>

  {{-- Two-column section --}}
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

    {{-- Top Performers --}}
    <div class="bg-white border border-gray-100 rounded-2xl p-5">
      <h3 class="font-bold text-slate-800 mb-4">
        <i class="bi bi-trophy-fill text-amber-500 mr-1"></i>Top 10 คูปองสร้างรายได้สูงสุด
      </h3>
      <div class="space-y-2">
        @forelse($topPerformers as $i => $p)
        <div class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg transition">
          <div class="w-8 h-8 rounded-full font-bold flex items-center justify-center shrink-0
                      {{ $i === 0 ? 'bg-amber-100 text-amber-600' : ($i < 3 ? 'bg-gray-100 text-gray-600' : 'bg-slate-50 text-slate-500') }}">
            {{ $i + 1 }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-mono text-sm font-bold text-indigo-600">{{ $p->code }}</div>
            <div class="text-xs text-gray-500 truncate">{{ $p->name }}</div>
          </div>
          <div class="text-right">
            <div class="text-sm font-bold text-emerald-600">฿{{ number_format($p->revenue_generated, 0) }}</div>
            <div class="text-xs text-gray-500">{{ $p->usage_count }} uses</div>
          </div>
        </div>
        @empty
        <p class="text-gray-400 text-center py-4">ยังไม่มีข้อมูล</p>
        @endforelse
      </div>
    </div>

    {{-- Top Customers --}}
    <div class="bg-white border border-gray-100 rounded-2xl p-5">
      <h3 class="font-bold text-slate-800 mb-4">
        <i class="bi bi-person-hearts text-pink-500 mr-1"></i>Top 10 ลูกค้าที่ใช้คูปองบ่อยสุด
      </h3>
      <div class="space-y-2">
        @forelse($topCustomers as $i => $c)
        <div class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg transition">
          <div class="w-8 h-8 rounded-full bg-gradient-to-br from-pink-400 to-purple-500 text-white flex items-center justify-center font-semibold text-sm shrink-0">
            {{ mb_strtoupper(mb_substr($c->first_name ?? 'U', 0, 1, 'UTF-8'), 'UTF-8') }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-medium text-sm text-slate-800 truncate">{{ $c->first_name }} {{ $c->last_name }}</div>
            <div class="text-xs text-gray-500 truncate">{{ $c->email }}</div>
          </div>
          <div class="text-right">
            <div class="text-sm font-bold text-pink-600">{{ $c->redemption_count }}</div>
            <div class="text-xs text-gray-500">ประหยัด ฿{{ number_format($c->total_saved, 0) }}</div>
          </div>
        </div>
        @empty
        <p class="text-gray-400 text-center py-4">ยังไม่มีข้อมูล</p>
        @endforelse
      </div>
    </div>
  </div>

  {{-- Conversion Impact --}}
  <div class="bg-white border border-gray-100 rounded-2xl p-5">
    <h3 class="font-bold text-slate-800 mb-4">
      <i class="bi bi-arrow-up-right-circle text-emerald-500 mr-1"></i>Conversion Impact ({{ $period }})
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-indigo-50 rounded-xl p-4">
        <div class="text-xs text-indigo-600 font-semibold uppercase mb-1">With Coupon</div>
        <div class="text-2xl font-bold text-indigo-700">{{ number_format($conversion['with_coupon']['count']) }} orders</div>
        <div class="text-sm text-indigo-600 mt-2">Avg: ฿{{ number_format($conversion['with_coupon']['avg_total'], 0) }}</div>
        <div class="text-sm text-indigo-600">Total: ฿{{ number_format($conversion['with_coupon']['total'], 0) }}</div>
      </div>
      <div class="bg-gray-50 rounded-xl p-4">
        <div class="text-xs text-gray-600 font-semibold uppercase mb-1">Without Coupon</div>
        <div class="text-2xl font-bold text-gray-700">{{ number_format($conversion['without_coupon']['count']) }} orders</div>
        <div class="text-sm text-gray-600 mt-2">Avg: ฿{{ number_format($conversion['without_coupon']['avg_total'], 0) }}</div>
        <div class="text-sm text-gray-600">Total: ฿{{ number_format($conversion['without_coupon']['total'], 0) }}</div>
      </div>
      <div class="bg-gradient-to-br from-emerald-50 to-green-50 rounded-xl p-4">
        <div class="text-xs text-emerald-600 font-semibold uppercase mb-1">Lift</div>
        @php
          $woAvg = $conversion['without_coupon']['avg_total'] ?: 1;
          $lift = (($conversion['with_coupon']['avg_total'] - $woAvg) / $woAvg) * 100;
        @endphp
        <div class="text-2xl font-bold text-emerald-700">
          {{ $lift >= 0 ? '+' : '' }}{{ number_format($lift, 1) }}%
        </div>
        <div class="text-sm text-emerald-600 mt-2">Average order value</div>
        <div class="text-xs text-gray-500 mt-1">
          เทียบ with vs without coupon
        </div>
      </div>
    </div>
  </div>

  {{-- Expiring Soon --}}
  @if($expiringSoon->count() > 0)
  <div class="bg-amber-50 border-l-4 border-amber-400 rounded-r-2xl p-5">
    <h3 class="font-bold text-amber-800 mb-3">
      <i class="bi bi-clock-history mr-1"></i>คูปองใกล้หมดอายุ (7 วัน)
    </h3>
    <div class="space-y-2">
      @foreach($expiringSoon as $c)
      <div class="flex items-center justify-between bg-white rounded-lg p-3">
        <div>
          <div class="font-mono text-sm font-bold text-indigo-600">{{ $c->code }}</div>
          <div class="text-xs text-gray-500">{{ $c->name }}</div>
        </div>
        <div class="text-right">
          <div class="text-sm font-semibold text-amber-700">
            {{ $c->end_date->diffForHumans() }}
          </div>
          <div class="text-xs text-gray-500">{{ $c->end_date->format('d/m/Y H:i') }}</div>
        </div>
        <a href="{{ route('admin.coupons.edit', $c) }}" class="ml-3 text-indigo-600 hover:text-indigo-700">
          <i class="bi bi-pencil"></i>
        </a>
      </div>
      @endforeach
    </div>
  </div>
  @endif

  {{-- Type Distribution --}}
  <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div class="bg-white border border-gray-100 rounded-2xl p-5">
      <h3 class="font-bold text-slate-800 mb-3">
        <i class="bi bi-pie-chart text-violet-500 mr-1"></i>ประเภทคูปอง
      </h3>
      <div class="space-y-3">
        <div>
          <div class="flex justify-between text-sm mb-1">
            <span class="font-medium">ส่วนลด % (Percent)</span>
            <span class="text-indigo-600 font-bold">{{ $typeDist['percent']['count'] }} uses</span>
          </div>
          @php $totalUses = ($typeDist['percent']['count'] + $typeDist['fixed']['count']) ?: 1; @endphp
          <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-gradient-to-r from-indigo-500 to-indigo-600"
                 style="width: {{ round(($typeDist['percent']['count'] / $totalUses) * 100, 1) }}%"></div>
          </div>
          <div class="text-xs text-gray-500 mt-1">Discount: ฿{{ number_format($typeDist['percent']['discount'], 0) }}</div>
        </div>
        <div>
          <div class="flex justify-between text-sm mb-1">
            <span class="font-medium">จำนวนคงที่ (Fixed)</span>
            <span class="text-emerald-600 font-bold">{{ $typeDist['fixed']['count'] }} uses</span>
          </div>
          <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-600"
                 style="width: {{ round(($typeDist['fixed']['count'] / $totalUses) * 100, 1) }}%"></div>
          </div>
          <div class="text-xs text-gray-500 mt-1">Discount: ฿{{ number_format($typeDist['fixed']['discount'], 0) }}</div>
        </div>
      </div>
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white border border-gray-100 rounded-2xl p-5">
      <h3 class="font-bold text-slate-800 mb-3">
        <i class="bi bi-lightning-charge text-amber-500 mr-1"></i>Quick Actions
      </h3>
      <div class="space-y-2">
        <a href="{{ route('admin.coupons.bulk-create') }}" class="flex items-center gap-3 p-3 bg-gradient-to-r from-emerald-50 to-green-50 border border-emerald-100 rounded-xl hover:shadow-sm transition">
          <i class="bi bi-plus-square-dotted text-2xl text-emerald-600"></i>
          <div>
            <div class="font-semibold text-emerald-700 text-sm">สร้างคูปองหลายรายการ</div>
            <div class="text-xs text-emerald-600">สำหรับ campaign หรือ email marketing</div>
          </div>
        </a>
        <a href="{{ route('admin.coupons.export') }}" class="flex items-center gap-3 p-3 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-xl hover:shadow-sm transition">
          <i class="bi bi-download text-2xl text-blue-600"></i>
          <div>
            <div class="font-semibold text-blue-700 text-sm">ส่งออก CSV</div>
            <div class="text-xs text-blue-600">ดาวน์โหลดรายการคูปองทั้งหมด</div>
          </div>
        </a>
        <a href="{{ route('admin.coupons.index', ['status' => 'expiring']) }}" class="flex items-center gap-3 p-3 bg-gradient-to-r from-amber-50 to-yellow-50 border border-amber-100 rounded-xl hover:shadow-sm transition">
          <i class="bi bi-clock-history text-2xl text-amber-600"></i>
          <div>
            <div class="font-semibold text-amber-700 text-sm">คูปองใกล้หมดอายุ</div>
            <div class="text-xs text-amber-600">{{ $stats['expiring_soon'] }} รายการ ใน 7 วัน</div>
          </div>
        </a>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const trendData = @json($trend);

new Chart(document.getElementById('trendChart'), {
  type: 'bar',
  data: {
    labels: trendData.map(d => d.label),
    datasets: [
      {
        label: 'Redemptions',
        data: trendData.map(d => d.count),
        backgroundColor: 'rgba(99,102,241,0.7)',
        borderColor: 'rgb(99,102,241)',
        borderWidth: 1,
        yAxisID: 'y',
      },
      {
        label: 'Discount (฿)',
        data: trendData.map(d => d.discount),
        type: 'line',
        borderColor: 'rgb(239,68,68)',
        backgroundColor: 'rgba(239,68,68,0.1)',
        tension: 0.4,
        yAxisID: 'y1',
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { position: 'top' } },
    scales: {
      y: {
        type: 'linear',
        position: 'left',
        title: { display: true, text: 'Redemptions' },
        beginAtZero: true,
      },
      y1: {
        type: 'linear',
        position: 'right',
        title: { display: true, text: 'Discount (฿)' },
        grid: { drawOnChartArea: false },
        beginAtZero: true,
      },
    },
  }
});
</script>
@endpush

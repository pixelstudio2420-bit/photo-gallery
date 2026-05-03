@extends('layouts.photographer')

@section('title', 'Analytics')

@push('styles')
<style>
.stat-card {
  transition: transform 0.2s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.top-event-row {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 0; border-bottom: 1px solid #f1f5f9;
}
.top-event-row:last-child { border-bottom: none; }
.rank-badge {
  width: 28px; height: 28px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.75rem; font-weight: 700; flex-shrink: 0;
}
</style>
@endpush

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-graph-up-arrow',
  'eyebrow'  => 'การทำงาน',
  'title'    => 'Analytics',
  'subtitle' => 'ภาพรวมรายได้ · อีเวนต์ขายดี · พฤติกรรมลูกค้า',
  'actions'  => '<a href="'.route('photographer.dashboard').'" class="pg-btn-ghost"><i class="bi bi-arrow-left"></i> Dashboard</a>',
])

{{-- Summary Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="stat-card pg-card">
    <div class="p-4 flex items-center gap-3">
      <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0" style="background:rgba(37,99,235,0.08);color:#2563eb;"><i class="bi bi-wallet2"></i></div>
      <div>
        <div class="text-gray-500 text-xs">รายได้สุทธิ</div>
        <div class="font-bold text-lg text-indigo-600">{{ number_format($totalEarnings, 0) }}</div>
      </div>
    </div>
  </div>
  <div class="stat-card pg-card">
    <div class="p-4 flex items-center gap-3">
      <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0" style="background:rgba(16,185,129,0.08);color:#10b981;"><i class="bi bi-cash-stack"></i></div>
      <div>
        <div class="text-gray-500 text-xs">รายได้เดือนนี้</div>
        <div class="font-bold text-lg text-emerald-500">{{ number_format($thisMonthEarnings, 0) }}</div>
      </div>
    </div>
  </div>
  <div class="stat-card pg-card">
    <div class="p-4 flex items-center gap-3">
      <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0" style="background:rgba(245,158,11,0.08);color:#f59e0b;"><i class="bi bi-bag-check"></i></div>
      <div>
        <div class="text-gray-500 text-xs">ออเดอร์ทั้งหมด</div>
        <div class="font-bold text-lg text-amber-500">{{ number_format($totalOrders) }}</div>
      </div>
    </div>
  </div>
  <div class="stat-card pg-card">
    <div class="p-4 flex items-center gap-3">
      <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0" style="background:rgba(139,92,246,0.08);color:#8b5cf6;"><i class="bi bi-image"></i></div>
      <div>
        <div class="text-gray-500 text-xs">รูปภาพ / วิว</div>
        <div class="font-bold text-lg text-violet-500">{{ number_format($totalPhotos) }} <span class="text-gray-500 font-normal text-xs">/ {{ number_format($totalViews) }} views</span></div>
      </div>
    </div>
  </div>
</div>

{{-- Revenue Charts --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
  <div class="lg:col-span-2 pg-card">
    <div class="p-5">
      <div class="flex justify-between items-center mb-4">
        <h6 class="font-semibold"><i class="bi bi-bar-chart-line mr-1 text-indigo-600"></i> รายได้ 30 วัน</h6>
        <span class="inline-block text-xs font-medium px-2.5 py-0.5 rounded-lg" style="background:rgba(37,99,235,0.1);color:#2563eb;">THB</span>
      </div>
      <canvas id="dailyRevenueChart" height="180"></canvas>
    </div>
  </div>
  <div class="pg-card">
    <div class="p-5">
      <h6 class="font-semibold mb-4"><i class="bi bi-pie-chart mr-1 text-amber-500"></i> สถานะออเดอร์</h6>
      <canvas id="orderStatusChart" height="180"></canvas>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-6">
  <div class="lg:col-span-7 pg-card">
    <div class="p-5">
      <h6 class="font-semibold mb-4"><i class="bi bi-calendar3 mr-1 text-emerald-500"></i> รายได้รายเดือน (12 เดือน)</h6>
      <canvas id="monthlyRevenueChart" height="200"></canvas>
    </div>
  </div>
  <div class="lg:col-span-5 pg-card">
    <div class="p-5">
      <h6 class="font-semibold mb-4"><i class="bi bi-trophy mr-1 text-amber-500"></i> อีเวนต์ขายดี</h6>
      @forelse($topEvents as $i => $ev)
      <div class="top-event-row">
        <div class="rank-badge" style="background:{{ $i < 3 ? 'rgba(245,158,11,0.1)' : '#f1f5f9' }};color:{{ $i < 3 ? '#f59e0b' : '#64748b' }};">{{ $i + 1 }}</div>
        <div class="flex-1 min-w-0">
          <div class="font-semibold text-sm truncate">{{ $ev->name }}</div>
          <div class="text-gray-500 text-xs">{{ $ev->order_count }} orders</div>
        </div>
        <div class="font-bold text-sm text-indigo-600">{{ number_format($ev->revenue, 0) }}</div>
      </div>
      @empty
      <div class="text-center text-gray-500 py-8">
        <i class="bi bi-inbox text-3xl opacity-30"></i>
        <p class="mt-2 text-sm">ยังไม่มีข้อมูล</p>
      </div>
      @endforelse
    </div>
  </div>
</div>

{{-- ────────── Customer Analytics (Business+) ────────── --}}
@if($hasCustomerAnalytics ?? false)
<div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mt-4">
  <div class="lg:col-span-7 pg-card pg-card-padded pg-anim d3">
    <h6 class="pg-section-title mb-4"><i class="bi bi-people"></i> ลูกค้าที่จ่ายมากที่สุด</h6>
    @if($topCustomers->isEmpty())
      <div class="pg-empty">
        <div class="pg-empty-icon"><i class="bi bi-people"></i></div>
        <p class="font-medium">ยังไม่มีข้อมูล</p>
      </div>
    @else
      <div class="pg-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
        <table class="pg-table pg-table--compact">
          <thead>
            <tr>
              <th>ลูกค้า</th>
              <th class="text-end">ออเดอร์</th>
              <th class="text-end">ใช้จ่ายรวม</th>
            </tr>
          </thead>
          <tbody>
            @foreach($topCustomers as $c)
              <tr>
                <td>
                  <p class="font-semibold m-0">{{ $c->name ?? '—' }}</p>
                  <p class="text-xs text-gray-500 m-0">{{ $c->email ?? '' }}</p>
                </td>
                <td class="text-end is-mono">{{ $c->orders }}</td>
                <td class="text-end is-mono font-bold text-violet-600">{{ number_format($c->spend, 0) }} ฿</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  <div class="lg:col-span-5 pg-card pg-card-padded pg-anim d3">
    <h6 class="font-semibold mb-3"><i class="bi bi-graph-up mr-1 text-emerald-500"></i> Conversion Rate</h6>
    <div class="text-center py-3">
      <p class="text-3xl font-bold text-emerald-600">{{ number_format($conversionRate, 2) }}%</p>
      <p class="text-xs text-gray-500 mt-1">{{ number_format($totalOrders) }} orders / {{ number_format($totalViews) }} views</p>
    </div>
    <hr class="my-4 border-gray-100">
    <p class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-3">ช่วงเวลาที่ขายดี (60 วันล่าสุด)</p>
    @if(!empty($hourlyHeatmap) && count($hourlyHeatmap) > 0)
      <div class="grid grid-cols-12 gap-0.5">
        @php $maxHour = $hourlyHeatmap->max('orders') ?: 1; @endphp
        @for($h = 0; $h < 24; $h++)
          @php
            $cnt = (int) ($hourlyHeatmap[$h]->orders ?? 0);
            $opacity = $maxHour > 0 ? round(($cnt / $maxHour) * 100) : 0;
          @endphp
          <div class="aspect-square rounded text-[9px] flex items-center justify-center font-medium
                      @if($cnt > 0) text-white @else text-gray-400 @endif"
               style="background-color: rgba(139, 92, 246, {{ $opacity / 100 }}); @if($opacity < 30 && $cnt > 0) color: #6b21a8; @endif"
               title="{{ $h }}:00 — {{ $cnt }} orders">
            {{ $h }}
          </div>
        @endfor
      </div>
    @else
      <p class="text-xs text-gray-400 text-center py-4">ยังไม่มีข้อมูลออเดอร์ใน 60 วันที่ผ่านมา</p>
    @endif
  </div>
</div>
@else
<div class="mt-4 rounded-xl border border-violet-200 bg-violet-50 p-5">
  <p class="font-semibold text-violet-900">
    <i class="bi bi-stars mr-1.5"></i> Customer Analytics (Business+)
  </p>
  <p class="text-sm text-violet-800 mt-2">
    ดูรายชื่อลูกค้าที่ใช้จ่ายมากที่สุด, conversion rate, ช่วงเวลาที่ขายดี และ insight อื่นๆ —
    <a href="{{ route('photographer.subscription.plans') }}" class="font-medium underline">อัปเกรดเป็น Business เพื่อปลดล็อก</a>
  </p>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     GA4 + Search Console insights — only render when admin configured.
     ═══════════════════════════════════════════════════════════════ --}}
@if($gaConfigured || $scConfigured)
<div class="mt-6">
  <div class="flex items-center gap-2 mb-4">
    <i class="bi bi-google text-blue-500 text-lg"></i>
    <h3 class="font-semibold text-slate-900 dark:text-slate-100">Insights จาก Google Analytics</h3>
    <span class="text-[10px] uppercase tracking-wider rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300 px-2 py-0.5 font-bold">Live</span>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    {{-- Traffic sources --}}
    @if($gaConfigured)
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <i class="bi bi-funnel text-purple-500"></i>
          <h4 class="font-semibold text-slate-900 dark:text-slate-100 text-sm">ลูกค้ามาจากไหน <span class="text-[10px] text-slate-500 font-normal">(30 วันล่าสุด)</span></h4>
        </div>
      </div>
      <div class="p-5">
        @if(empty($trafficSources))
          <p class="text-xs text-slate-500 dark:text-slate-400 text-center py-6">
            ยังไม่มีข้อมูล — Google ใช้เวลา ~24 ชม. ในการเก็บข้อมูลครั้งแรก
          </p>
        @else
          @php $maxSessions = max(array_column($trafficSources, 'sessions')) ?: 1; @endphp
          <div class="space-y-2">
            @foreach($trafficSources as $src)
              <div>
                <div class="flex items-center justify-between text-xs mb-1">
                  <span class="font-medium text-slate-700 dark:text-slate-300 truncate flex-1">
                    <i class="bi bi-arrow-up-right text-slate-400 text-[10px]"></i>
                    {{ $src['source'] }} <span class="text-slate-400">·</span> <span class="text-slate-500">{{ $src['medium'] }}</span>
                  </span>
                  <span class="ml-2 font-mono font-bold text-purple-600 dark:text-purple-400">{{ number_format($src['sessions']) }}</span>
                </div>
                <div class="h-1.5 rounded-full bg-slate-100 dark:bg-white/5 overflow-hidden">
                  <div class="h-full bg-gradient-to-r from-purple-500 to-pink-500 rounded-full" style="width: {{ ($src['sessions'] / $maxSessions) * 100 }}%"></div>
                </div>
              </div>
            @endforeach
          </div>
        @endif
      </div>
    </div>
    @endif

    {{-- Geographic breakdown --}}
    @if($gaConfigured)
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-2">
        <i class="bi bi-geo-alt text-emerald-500"></i>
        <h4 class="font-semibold text-slate-900 dark:text-slate-100 text-sm">ลูกค้าอยู่ที่ไหน <span class="text-[10px] text-slate-500 font-normal">(30 วันล่าสุด)</span></h4>
      </div>
      <div class="p-5">
        @if(empty($geoBreakdown))
          <p class="text-xs text-slate-500 dark:text-slate-400 text-center py-6">ยังไม่มีข้อมูล</p>
        @else
          @php $maxUsers = max(array_column($geoBreakdown, 'users')) ?: 1; @endphp
          <div class="space-y-1.5 max-h-72 overflow-y-auto">
            @foreach($geoBreakdown as $loc)
              <div class="flex items-center gap-2 text-xs">
                <span class="font-medium text-slate-700 dark:text-slate-300 flex-1 truncate">
                  <span class="text-slate-400 text-[10px]">{{ $loc['country'] }}</span>
                  →
                  <strong>{{ $loc['city'] ?: '(ไม่ระบุเมือง)' }}</strong>
                </span>
                <div class="w-24 h-1.5 rounded-full bg-slate-100 dark:bg-white/5 overflow-hidden">
                  <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500" style="width: {{ ($loc['users'] / $maxUsers) * 100 }}%"></div>
                </div>
                <span class="font-mono text-emerald-600 dark:text-emerald-400 w-12 text-right">{{ number_format($loc['users']) }}</span>
              </div>
            @endforeach
          </div>
        @endif
      </div>
    </div>
    @endif

    {{-- Search keywords (lg:col-span-2 to span both columns) --}}
    @if($scConfigured)
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden lg:col-span-2">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-2">
        <i class="bi bi-search text-amber-500"></i>
        <h4 class="font-semibold text-slate-900 dark:text-slate-100 text-sm">คำค้นหาที่ทำให้คนเจอแกลเลอรี่ <span class="text-[10px] text-slate-500 font-normal">(28 วันล่าสุด · จาก Google Search)</span></h4>
      </div>
      <div class="p-5">
        @if(empty($topKeywords))
          <p class="text-xs text-slate-500 dark:text-slate-400 text-center py-6">
            ยังไม่มี search query — Search Console ใช้เวลา ~3 วันก่อนเริ่มมีข้อมูล
          </p>
        @else
          <div class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead class="text-left text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px]">
                <tr class="border-b border-slate-200 dark:border-white/10">
                  <th class="py-2 pr-3">คำค้น</th>
                  <th class="py-2 px-2 text-right">คลิก</th>
                  <th class="py-2 px-2 text-right">Impressions</th>
                  <th class="py-2 px-2 text-right">CTR</th>
                  <th class="py-2 pl-2 text-right">อันดับ</th>
                </tr>
              </thead>
              <tbody>
                @foreach($topKeywords as $kw)
                <tr class="border-b border-slate-100 dark:border-white/5 hover:bg-slate-50 dark:hover:bg-white/5 transition">
                  <td class="py-2 pr-3 font-medium text-slate-900 dark:text-slate-100 truncate max-w-xs">{{ $kw['query'] }}</td>
                  <td class="py-2 px-2 text-right font-mono font-bold text-amber-600 dark:text-amber-400">{{ number_format($kw['clicks']) }}</td>
                  <td class="py-2 px-2 text-right font-mono text-slate-700 dark:text-slate-300">{{ number_format($kw['impressions']) }}</td>
                  <td class="py-2 px-2 text-right font-mono text-slate-600 dark:text-slate-400">{{ $kw['ctr'] }}%</td>
                  <td class="py-2 pl-2 text-right font-mono">
                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[10px] font-bold
                                 {{ $kw['position'] <= 3 ? 'bg-emerald-100 text-emerald-700' : ($kw['position'] <= 10 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                      #{{ $kw['position'] }}
                    </span>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
    @endif

  </div>
</div>
@endif

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Daily Revenue Chart
  const dailyCtx = document.getElementById('dailyRevenueChart');
  if (dailyCtx) {
    new Chart(dailyCtx, {
      type: 'bar',
      data: {
        labels: {!! json_encode($revenueDays->pluck('label')) !!},
        datasets: [{
          label: 'รายได้ (THB)',
          data: {!! json_encode($revenueDays->pluck('total')) !!},
          backgroundColor: 'rgba(37, 99, 235, 0.15)',
          borderColor: '#2563eb',
          borderWidth: 2,
          borderRadius: 4,
          borderSkipped: false,
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 9 }, maxRotation: 45 } },
          y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 10 } } }
        }
      }
    });
  }

  // Monthly Revenue Chart
  const monthlyCtx = document.getElementById('monthlyRevenueChart');
  if (monthlyCtx) {
    new Chart(monthlyCtx, {
      type: 'line',
      data: {
        labels: {!! json_encode($revenueMonths->pluck('label')) !!},
        datasets: [
          {
            label: 'รายได้สุทธิ',
            data: {!! json_encode($revenueMonths->pluck('total')) !!},
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37,99,235,0.08)',
            fill: true, tension: 0.4, borderWidth: 2, pointRadius: 3,
          },
          {
            label: 'ยอดขายรวม',
            data: {!! json_encode($revenueMonths->pluck('gross')) !!},
            borderColor: '#10b981',
            backgroundColor: 'rgba(16,185,129,0.05)',
            fill: true, tension: 0.4, borderWidth: 2, pointRadius: 3, borderDash: [5, 5],
          }
        ]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 10 } } },
          y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } }
        }
      }
    });
  }

  // Order Status Doughnut
  const statusCtx = document.getElementById('orderStatusChart');
  if (statusCtx) {
    const statusData = {!! json_encode($orderStatuses) !!};
    const labels = []; const data = []; const colors = [];
    const colorMap = { paid: '#10b981', pending: '#f59e0b', cancelled: '#ef4444', failed: '#6b7280' };
    const labelMap = { paid: 'ชำระแล้ว', pending: 'รอชำระ', cancelled: 'ยกเลิก', failed: 'ล้มเหลว' };
    for (const [key, val] of Object.entries(statusData)) {
      labels.push(labelMap[key] || key);
      data.push(val.count);
      colors.push(colorMap[key] || '#94a3b8');
    }
    if (data.length === 0) { labels.push('ไม่มีข้อมูล'); data.push(1); colors.push('#e2e8f0'); }
    new Chart(statusCtx, {
      type: 'doughnut',
      data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 0 }] },
      options: {
        responsive: true, cutout: '65%',
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 }, padding: 12 } } }
      }
    });
  }
});
</script>
@endpush

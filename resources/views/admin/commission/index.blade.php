@extends('layouts.admin')

@section('title', 'คอมมิชชั่น')

@section('content')
<div class="flex justify-between items-center mb-6 flex-wrap gap-2">
  <div>
    <h4 class="font-bold mb-1 text-xl tracking-tight">
      <i class="bi bi-percent mr-2 text-indigo-500"></i>แดชบอร์ดคอมมิชชั่น
    </h4>
    <p class="text-gray-500 mb-0 text-sm">ภาพรวมรายได้แพลตฟอร์มและค่าคอมมิชชั่นช่างภาพ</p>
  </div>
  <div class="flex gap-2">
    <a href="{{ route('admin.commission.settings') }}" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition dark:bg-white/[0.06] dark:text-gray-300">
      <i class="bi bi-gear mr-1"></i> ตั้งค่า
    </a>
    <a href="{{ route('admin.commission.bulk') }}" class="inline-flex items-center bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-4 py-2 transition hover:from-indigo-600 hover:to-indigo-700">
      <i class="bi bi-sliders mr-1"></i> ปรับแบบกลุ่ม
    </a>
  </div>
</div>

{{-- Plan-based Commission Banner ───────────────────────────────────
     Since 2026-04-30 commission is determined by the photographer's
     subscription plan, not a single global number. Show every plan +
     its rate so admins immediately see the truth. --}}
<div class="bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-400 rounded-2xl p-6 mb-6 text-white">
  <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-4">
    <div>
      <h5 class="font-bold text-lg mb-1">อัตราค่าคอมมิชชั่นตามแผน</h5>
      <p class="text-white/70 text-sm mb-0">
        ค่าคอมมิชชั่นถูกกำหนดโดยแผนสมาชิกของช่างภาพแต่ละคน — ช่างภาพใหม่จะถูก assign อัตโนมัติเข้าแผน <span class="font-semibold">Free</span>
      </p>
    </div>
    <a href="{{ route('admin.subscriptions.plans') }}" class="inline-flex items-center px-3 py-1.5 bg-white/15 hover:bg-white/25 rounded-lg text-xs font-medium transition shrink-0">
      <i class="bi bi-grid-3x3-gap mr-1"></i> จัดการแผน
    </a>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
    @foreach($plans as $plan)
      @php
        $keepPct  = 100 - (float) $plan->commission_pct;
        $count    = $planCounts[$plan->code] ?? 0;
        $isPublic = (int) $plan->is_public === 1;
      @endphp
      <div class="bg-white/15 rounded-xl p-3 text-center backdrop-blur-sm {{ $isPublic ? '' : 'opacity-60' }}">
        <div class="text-xs text-white/70 mb-0.5">{{ $plan->name }}</div>
        <div class="text-2xl font-bold leading-tight">{{ number_format($keepPct, 0) }}%</div>
        <div class="text-[10px] text-white/60 mt-0.5">
          ช่างภาพได้
          @if(!$isPublic) <span class="ml-1 px-1 py-0.5 bg-white/20 rounded text-[8px]">ซ่อน</span> @endif
        </div>
        <div class="mt-1.5 pt-1.5 border-t border-white/20 text-[10px] text-white/70">
          <i class="bi bi-people mr-0.5"></i>{{ $count }} คน
        </div>
      </div>
    @endforeach
  </div>

  {{-- Fallback rate (used when a profile has no subscription_plan_code at all) --}}
  <div class="mt-4 pt-4 border-t border-white/20 flex flex-wrap items-center justify-between gap-2 text-xs text-white/70">
    <div>
      <i class="bi bi-info-circle mr-1"></i>
      ค่าเริ่มต้น (เมื่อช่างภาพยังไม่มีแผน):
      <span class="font-semibold text-white">{{ number_format(100 - $stats['default_platform_rate'], 0) }}% / {{ number_format($stats['default_platform_rate'], 0) }}%</span>
      (ช่างภาพ / แพลตฟอร์ม)
    </div>
    <a href="{{ route('admin.commission.settings') }}" class="hover:text-white transition">
      ตั้งค่าขั้นสูง <i class="bi bi-arrow-right ml-0.5"></i>
    </a>
  </div>
</div>

{{-- Stats Cards --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
  @php
    $cards = [
      ['icon' => 'bi-cash-stack', 'color' => 'emerald', 'value' => '฿'.number_format($stats['total_revenue'],0), 'label' => 'รายได้ทั้งหมด'],
      ['icon' => 'bi-building', 'color' => 'indigo', 'value' => '฿'.number_format($stats['total_platform_fee'],0), 'label' => 'ค่าแพลตฟอร์ม'],
      ['icon' => 'bi-wallet2', 'color' => 'blue', 'value' => '฿'.number_format($stats['total_photographer_payout'],0), 'label' => 'จ่ายช่างภาพ'],
      ['icon' => 'bi-clock-history', 'color' => 'amber', 'value' => '฿'.number_format($stats['pending_payout'],0), 'label' => 'รอจ่าย'],
    ];
  @endphp
  @foreach($cards as $c)
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="py-3 px-4 flex items-center gap-3">
      <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-{{ $c['color'] }}-500/10">
        <i class="bi {{ $c['icon'] }} text-{{ $c['color'] }}-500 text-lg"></i>
      </div>
      <div>
        <div class="font-bold text-lg">{{ $c['value'] }}</div>
        <small class="text-gray-500">{{ $c['label'] }}</small>
      </div>
    </div>
  </div>
  @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
  {{-- Revenue Split --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
      <h6 class="font-semibold text-sm"><i class="bi bi-pie-chart mr-1 text-indigo-500"></i>สัดส่วนรายได้</h6>
    </div>
    <div class="p-6">
      @php
        $total = $stats['total_revenue'] ?: 1;
        $platformPct = round($stats['total_platform_fee'] / $total * 100, 1);
        $photographerPct = round($stats['total_photographer_payout'] / $total * 100, 1);
      @endphp
      <div class="flex items-center gap-4 mb-4">
        <div class="flex-1">
          <div class="h-4 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden flex">
            <div class="h-full bg-indigo-500 rounded-l-full" style="width:{{ $platformPct }}%"></div>
            <div class="h-full bg-emerald-500 rounded-r-full" style="width:{{ $photographerPct }}%"></div>
          </div>
        </div>
      </div>
      <div class="flex justify-between text-sm">
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-full bg-indigo-500"></span>
          <span class="text-gray-600 dark:text-gray-300">แพลตฟอร์ม</span>
          <span class="font-semibold text-indigo-600">{{ $platformPct }}%</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
          <span class="text-gray-600 dark:text-gray-300">ช่างภาพ</span>
          <span class="font-semibold text-emerald-600">{{ $photographerPct }}%</span>
        </div>
      </div>
      <div class="mt-4 space-y-2 text-sm">
        <div class="flex justify-between py-2 border-b border-gray-100 dark:border-white/[0.04]">
          <span class="text-gray-500">ช่างภาพที่อนุมัติ</span>
          <span class="font-semibold">{{ $stats['photographers_count'] }} คน</span>
        </div>
        <div class="flex justify-between py-2 border-b border-gray-100 dark:border-white/[0.04]">
          <span class="text-gray-500">อัตราเฉลี่ย (ช่างภาพ)</span>
          <span class="font-semibold text-indigo-600">{{ number_format($stats['avg_commission_rate'], 1) }}%</span>
        </div>
        <div class="flex justify-between py-2">
          <span class="text-gray-500">จ่ายแล้ว / รอจ่าย</span>
          <span class="font-semibold">฿{{ number_format($stats['paid_payout'],0) }} / ฿{{ number_format($stats['pending_payout'],0) }}</span>
        </div>
      </div>
    </div>
  </div>

  {{-- Active Tiers --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06] flex items-center justify-between">
      <h6 class="font-semibold text-sm"><i class="bi bi-award mr-1 text-amber-500"></i>ระดับคอมมิชชั่น</h6>
      <a href="{{ route('admin.commission.tiers') }}" class="text-xs text-indigo-500 hover:text-indigo-700">จัดการ &rarr;</a>
    </div>
    <div class="p-6">
      @if($tiers->count())
      <div class="space-y-3">
        @foreach($tiers as $tier)
        <div class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 dark:border-white/[0.06]">
          <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:{{ $tier->color }}15;color:{{ $tier->color }};">
            <i class="bi {{ $tier->icon }}"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-semibold text-sm">{{ $tier->name }}</div>
            <div class="text-xs text-gray-400">รายได้ &ge; ฿{{ number_format($tier->min_revenue, 0) }}</div>
          </div>
          <span class="font-bold text-sm" style="color:{{ $tier->color }};">{{ number_format($tier->commission_rate, 0) }}%</span>
        </div>
        @endforeach
      </div>
      @else
      <div class="text-center py-6">
        <i class="bi bi-award text-2xl text-gray-300"></i>
        <p class="text-gray-400 text-sm mt-1">ยังไม่มีระดับคอมมิชชั่น</p>
        <a href="{{ route('admin.commission.tiers') }}" class="text-sm text-indigo-500 hover:text-indigo-700">สร้างระดับ &rarr;</a>
      </div>
      @endif
    </div>
  </div>

  {{-- Commission Distribution --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
      <h6 class="font-semibold text-sm"><i class="bi bi-bar-chart mr-1 text-blue-500"></i>การกระจายอัตราคอมมิชชั่น</h6>
    </div>
    <div class="p-6">
      @if($distribution->count())
      <div class="space-y-2">
        @php $maxCount = $distribution->max('count') ?: 1; @endphp
        @foreach($distribution as $d)
        <div class="flex items-center gap-3">
          <span class="text-sm font-mono w-12 text-right text-gray-600">{{ number_format($d->commission_rate, 0) }}%</span>
          <div class="flex-1 h-6 bg-gray-100 dark:bg-white/[0.06] rounded-full overflow-hidden">
            <div class="h-full bg-indigo-500 rounded-full flex items-center justify-end pr-2" style="width:{{ max(($d->count / $maxCount * 100), 8) }}%">
              <span class="text-[10px] text-white font-semibold">{{ $d->count }}</span>
            </div>
          </div>
        </div>
        @endforeach
      </div>
      @else
      <div class="text-center py-6 text-gray-400 text-sm">ไม่มีข้อมูล</div>
      @endif
    </div>
  </div>
</div>

{{-- Top Earners + Recent Logs --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  {{-- Top Earners --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
      <h6 class="font-semibold text-sm"><i class="bi bi-trophy mr-1 text-amber-500"></i>ช่างภาพรายได้สูงสุด</h6>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-white/[0.02]">
          <tr>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">ช่างภาพ</th>
            <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">รายได้รวม</th>
            <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase">อัตรา</th>
            <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">ค่าแพลตฟอร์ม</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
          @foreach($topEarners as $i => $pg)
          <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
            <td class="px-4 py-3 font-semibold text-gray-400">{{ $i + 1 }}</td>
            <td class="px-4 py-3">
              <a href="{{ route('admin.photographers.show', $pg) }}" class="font-medium hover:text-indigo-600 transition">{{ $pg->display_name }}</a>
            </td>
            <td class="px-4 py-3 text-right font-semibold">฿{{ number_format($pg->total_gross, 0) }}</td>
            <td class="px-4 py-3 text-center text-indigo-600 font-medium">{{ number_format($pg->commission_rate, 0) }}%</td>
            <td class="px-4 py-3 text-right text-gray-500">฿{{ number_format($pg->total_platform, 0) }}</td>
          </tr>
          @endforeach
          @if($topEarners->isEmpty())
          <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">ยังไม่มีข้อมูล</td></tr>
          @endif
        </tbody>
      </table>
    </div>
  </div>

  {{-- Recent Changes --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
    <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06] flex items-center justify-between">
      <h6 class="font-semibold text-sm"><i class="bi bi-clock-history mr-1 text-purple-500"></i>ประวัติล่าสุด</h6>
      <a href="{{ route('admin.commission.history') }}" class="text-xs text-indigo-500 hover:text-indigo-700">ดูทั้งหมด &rarr;</a>
    </div>
    <div class="divide-y divide-gray-100 dark:divide-white/[0.04]">
      @forelse($recentLogs as $log)
      <div class="px-6 py-3 flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center shrink-0">
          <i class="bi bi-arrow-left-right text-purple-500 text-xs"></i>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm">
            <span class="font-medium">{{ $log->photographer->display_name ?? 'Unknown' }}</span>
            <span class="text-red-500 font-mono">{{ number_format($log->old_rate, 0) }}%</span>
            <i class="bi bi-arrow-right text-gray-400 text-xs mx-0.5"></i>
            <span class="text-emerald-600 font-mono">{{ number_format($log->new_rate, 0) }}%</span>
          </div>
          <div class="text-xs text-gray-400">{{ $log->reason ?? $log->source }} &bull; {{ $log->created_at?->diffForHumans() }}</div>
        </div>
      </div>
      @empty
      <div class="px-6 py-8 text-center text-gray-400 text-sm">ยังไม่มีประวัติการเปลี่ยนแปลง</div>
      @endforelse
    </div>
  </div>
</div>
@endsection

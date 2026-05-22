{{-- =======================================================================
     R2 STORAGE COST ESTIMATOR WIDGET
     -------------------------------------------------------------------
     Consumes $r2Cost = [
       'org'       => [...],   // org-wide totals from R2CostEstimatorService::orgSummary()
       'top'       => Collection,  // top photographers by cost (perPhotographer)
       'projected' => [...],   // projectedSavings()
     ]
     • Hidden silently if the data isn't loaded — won't break the dashboard
       on an old release where DashboardController doesn't pass $r2Cost.
     • Cached server-side for 5 minutes (see R2CostEstimatorService) so this
       partial is cheap to render on every dashboard hit.
     ====================================================================== --}}
@if(!empty($r2Cost) && is_array($r2Cost))
@php
  $r2Org       = $r2Cost['org']       ?? [];
  $r2Top       = $r2Cost['top']       ?? collect();
  $r2Projected = $r2Cost['projected'] ?? [];

  $orgGap      = (float) ($r2Org['gap_usd'] ?? 0);
  $isLossy     = $orgGap > 0;

  $tierBadge = function (string $tier): array {
      return match ($tier) {
          'pro'     => ['label' => 'PRO',     'bg' => 'rgba(245,158,11,0.12)', 'fg' => '#d97706'],
          'seller'  => ['label' => 'SELLER',  'bg' => 'rgba(16,185,129,0.12)', 'fg' => '#059669'],
          'creator' => ['label' => 'FREE',    'bg' => 'rgba(99,102,241,0.12)', 'fg' => '#4f46e5'],
          default   => ['label' => strtoupper($tier ?: '-'), 'bg' => 'rgba(100,116,139,0.12)', 'fg' => '#475569'],
      };
  };
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

  {{-- ──────────────────────── ORG SUMMARY CARD ──────────────────────── --}}
  <div class="adm-card p-5">
    <div class="flex items-center justify-between mb-3">
      <div class="flex items-center gap-2">
        <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl
                     bg-gradient-to-br from-cyan-500 to-sky-600 text-white">
          <i class="bi bi-cloud-fill text-sm"></i>
        </span>
        <div>
          <div class="font-semibold text-sm text-slate-900 dark:text-white leading-tight">R2 Storage Cost</div>
          <div class="text-[10px] text-slate-500 dark:text-slate-400">Org-wide ทั้งระบบ (cached 5 นาที)</div>
        </div>
      </div>
      <a href="{{ route('admin.settings.retention') }}"
         class="text-[11px] font-medium px-2.5 py-1 rounded-full transition
                bg-slate-100 dark:bg-slate-800
                border border-slate-200 dark:border-white/10
                text-slate-600 dark:text-slate-300
                hover:bg-slate-200 dark:hover:bg-slate-700">
        Retention
      </a>
    </div>

    <div class="grid grid-cols-2 gap-3 mt-4">
      <div class="p-3 rounded-xl" style="background:rgba(6,182,212,0.06);border:1px solid rgba(6,182,212,0.18);">
        <div class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold mb-1">พื้นที่รวม</div>
        <div class="text-xl font-bold text-cyan-700 dark:text-cyan-300 tabular-nums">
          {{ number_format($r2Org['total_gb'] ?? 0, 2) }}
          <span class="text-xs font-normal text-slate-500">GB</span>
        </div>
        <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
          {{ number_format($r2Org['photographer_count'] ?? 0) }} ช่างภาพ
        </div>
      </div>

      <div class="p-3 rounded-xl" style="background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.18);">
        <div class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold mb-1">ต้นทุน/เดือน</div>
        <div class="text-xl font-bold text-rose-600 dark:text-rose-300 tabular-nums">
          ${{ number_format($r2Org['total_cost_usd'] ?? 0, 2) }}
        </div>
        <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
          @ ${{ number_format($r2Org['cost_per_gb_usd'] ?? 0.015, 4) }}/GB
        </div>
      </div>

      <div class="p-3 rounded-xl" style="background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.18);">
        <div class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold mb-1">รายรับ/เดือน</div>
        <div class="text-xl font-bold text-emerald-700 dark:text-emerald-300 tabular-nums">
          ฿{{ number_format($r2Org['total_revenue_thb'] ?? 0, 0) }}
        </div>
        <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
          ≈ ${{ number_format($r2Org['total_revenue_usd'] ?? 0, 2) }}
        </div>
      </div>

      <div class="p-3 rounded-xl" style="background:{{ $isLossy ? 'rgba(239,68,68,0.06)' : 'rgba(16,185,129,0.06)' }};border:1px solid {{ $isLossy ? 'rgba(239,68,68,0.18)' : 'rgba(16,185,129,0.18)' }};">
        <div class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold mb-1">
          {{ $isLossy ? 'ขาดทุน/เดือน' : 'กำไรขั้นต้น/เดือน' }}
        </div>
        <div class="text-xl font-bold tabular-nums" style="color:{{ $isLossy ? '#dc2626' : '#059669' }};">
          {{ $isLossy ? '-' : '+' }}${{ number_format(abs($orgGap), 2) }}
        </div>
        <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
          rate ฿{{ number_format($r2Org['usd_thb_rate'] ?? 34.5, 2) }}/$
        </div>
      </div>
    </div>
  </div>

  {{-- ──────────────────────── PROJECTED SAVINGS ──────────────────────── --}}
  <div class="adm-card p-5">
    <div class="flex items-center justify-between mb-3">
      <div class="flex items-center gap-2">
        <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl
                     bg-gradient-to-br from-violet-500 to-purple-600 text-white">
          <i class="bi bi-recycle text-sm"></i>
        </span>
        <div>
          <div class="font-semibold text-sm text-slate-900 dark:text-white leading-tight">ประหยัดได้ถ้าลบตอนนี้</div>
          <div class="text-[10px] text-slate-500 dark:text-slate-400">
            จากอีเวนต์ที่เก่ากว่า {{ (int) ($r2Projected['retention_days'] ?? 90) }} วัน
          </div>
        </div>
      </div>
    </div>

    <div class="space-y-3 mt-4">
      <div class="p-3 rounded-xl" style="background:rgba(139,92,246,0.06);border:1px solid rgba(139,92,246,0.18);">
        <div class="flex items-center justify-between">
          <div class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold">พื้นที่เก่าทั้งหมด</div>
          <div class="text-[11px] text-slate-500 tabular-nums">
            {{ number_format($r2Projected['old_bytes'] ?? 0) }} B
          </div>
        </div>
        <div class="text-2xl font-bold text-violet-700 dark:text-violet-300 tabular-nums mt-1">
          {{ number_format($r2Projected['old_gb'] ?? 0, 2) }}
          <span class="text-xs font-normal text-slate-500">GB</span>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="p-3 rounded-xl" style="background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.18);">
          <div class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold mb-1">Portfolio</div>
          <div class="text-lg font-bold text-emerald-700 dark:text-emerald-300 tabular-nums">
            ${{ number_format($r2Projected['savings_usd_portfolio'] ?? 0, 2) }}
          </div>
          <div class="text-[10px] text-slate-500 mt-0.5">~96% recover</div>
        </div>

        <div class="p-3 rounded-xl" style="background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.18);">
          <div class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold mb-1">Full</div>
          <div class="text-lg font-bold text-rose-700 dark:text-rose-300 tabular-nums">
            ${{ number_format($r2Projected['savings_usd_full'] ?? 0, 2) }}
          </div>
          <div class="text-[10px] text-slate-500 mt-0.5">100% recover</div>
        </div>
      </div>

      <div class="text-[10px] text-slate-500 dark:text-slate-400 px-1">
        <i class="bi bi-info-circle mr-1"></i>
        Portfolio = ลบต้นฉบับ เก็บปก+พรีวิว+ลายน้ำ (~4% ของไฟล์ทั้งหมด)
      </div>
    </div>
  </div>

  {{-- ──────────────────────── TOP COST PHOTOGRAPHERS ──────────────────────── --}}
  <div class="adm-card overflow-hidden">
    <div class="flex items-center justify-between px-5 pt-4 pb-3">
      <div class="flex items-center gap-2">
        <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl
                     bg-gradient-to-br from-orange-500 to-rose-600 text-white">
          <i class="bi bi-hdd-rack-fill text-sm"></i>
        </span>
        <div>
          <div class="font-semibold text-sm text-slate-900 dark:text-white leading-tight">Top R2 ผู้บริโภค</div>
          <div class="text-[10px] text-slate-500 dark:text-slate-400">เรียงตามพื้นที่ใช้งาน (มากสุด→น้อยสุด)</div>
        </div>
      </div>
      <a href="{{ route('admin.settings.photographer-storage') }}"
         class="text-[11px] font-medium px-2.5 py-1 rounded-full transition
                bg-slate-100 dark:bg-slate-800
                border border-slate-200 dark:border-white/10
                text-slate-600 dark:text-slate-300
                hover:bg-slate-200 dark:hover:bg-slate-700">
        จัดการ
      </a>
    </div>
    <div class="overflow-x-auto" style="max-height:340px;overflow-y:auto;">
      <table class="w-full text-sm">
        <thead class="sticky top-0 z-10">
          <tr class="bg-slate-50 dark:bg-slate-800/90 border-y border-slate-200 dark:border-white/10">
            <th class="pl-4 pr-2 py-2 text-left text-[10px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">ช่างภาพ</th>
            <th class="px-2 py-2 text-center text-[10px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">Plan</th>
            <th class="px-2 py-2 text-right text-[10px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">GB</th>
            <th class="px-2 py-2 text-right text-[10px] font-bold text-rose-600 dark:text-rose-300 uppercase tracking-wider">Cost</th>
            <th class="pr-4 pl-2 py-2 text-right text-[10px] font-bold text-amber-600 dark:text-amber-300 uppercase tracking-wider">Gap</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
          @forelse($r2Top->take(10) as $row)
            @php
              $tier = $tierBadge((string) ($row['tier'] ?? '-'));
              $gap  = (float) ($row['gap_usd'] ?? 0);
              $over = (bool) ($row['over_quota'] ?? false);
            @endphp
            <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/40">
              <td class="pl-4 pr-2 py-2">
                <div class="flex items-center gap-2 min-w-0">
                  <a href="{{ route('admin.photographers.show', $row['user_id']) }}"
                     class="text-[12px] font-semibold text-slate-900 dark:text-white truncate max-w-[140px]"
                     title="{{ $row['display_name'] }}">
                    {{ $row['display_name'] }}
                  </a>
                  @if($over)
                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded"
                          style="background:rgba(239,68,68,0.15);color:#dc2626;"
                          title="{{ number_format($row['storage_used_gb'] ?? 0, 1) }} / {{ number_format($row['storage_quota_gb'] ?? 0, 1) }} GB">
                      OVER
                    </span>
                  @endif
                </div>
              </td>
              <td class="px-2 py-2 text-center">
                <span class="inline-block text-[10px] font-semibold px-2 py-0.5 rounded-full"
                      style="background:{{ $tier['bg'] }};color:{{ $tier['fg'] }};">
                  {{ $tier['label'] }}
                </span>
                <div class="text-[9px] text-slate-500 mt-0.5">{{ $row['plan_code'] }}</div>
              </td>
              <td class="px-2 py-2 text-right tabular-nums text-[12px] text-slate-700 dark:text-slate-300">
                {{ number_format($row['storage_used_gb'] ?? 0, 2) }}
              </td>
              <td class="px-2 py-2 text-right tabular-nums text-[12px] font-bold text-rose-600 dark:text-rose-300">
                ${{ number_format($row['monthly_cost_usd'] ?? 0, 2) }}
              </td>
              <td class="pr-4 pl-2 py-2 text-right tabular-nums text-[12px] font-bold"
                  style="color:{{ $gap > 0 ? '#dc2626' : '#059669' }};">
                {{ $gap > 0 ? '-' : '+' }}${{ number_format(abs($gap), 2) }}
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center py-6 text-slate-500 text-xs">ยังไม่มีข้อมูล</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endif

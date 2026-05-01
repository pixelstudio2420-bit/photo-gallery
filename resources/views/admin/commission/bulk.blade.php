@extends('layouts.admin')

@section('title', 'ปรับคอมมิชชั่นแบบกลุ่ม')

@section('content')
<div class="flex justify-between items-center mb-6 flex-wrap gap-2">
  <div>
    <h4 class="font-bold mb-1 text-xl tracking-tight">
      <i class="bi bi-sliders mr-2 text-indigo-500"></i>ปรับคอมมิชชั่นแบบกลุ่ม
    </h4>
    <p class="text-gray-500 mb-0 text-sm">เลือกช่างภาพและปรับอัตราค่าคอมมิชชั่นพร้อมกัน</p>
  </div>
  <a href="{{ route('admin.commission.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200 transition dark:bg-white/[0.06] dark:text-gray-300">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

@php
  // Bounds still come from AppSetting so admins can clamp manually-set
  // rates within a sane range (e.g. min 50%, max 99%).
  $cMin = (float) \App\Models\AppSetting::get('min_commission_rate', 0);
  $cMax = (float) \App\Models\AppSetting::get('max_commission_rate', 100);

  // Pre-filled rate: prefer the Free plan's keep% (since 2026-04-30 the
  // plan IS the source of truth — see CommissionResolver). Fall back to
  // the legacy AppSetting only if the Free plan row is missing.
  $freePlanCommission = \Illuminate\Support\Facades\DB::table('subscription_plans')
      ->where('code', 'free')
      ->where('is_active', 1)
      ->value('commission_pct');

  $cDefault = $freePlanCommission !== null
      ? 100 - (float) $freePlanCommission
      : 100 - (float) \App\Models\AppSetting::get('platform_commission', 30);

  // Clamp so the pre-filled number is never rejected by the server.
  $cDefault = max($cMin, min($cMax, $cDefault));
@endphp

<div x-data="{
  selectedIds: [],
  selectAll: false,
  newRate: {{ $cDefault }},
  reason: ''
}" x-init="$watch('selectAll', val => {
  if (val) {
    selectedIds = {{ $photographers->pluck('id')->toJson() }};
  } else {
    selectedIds = [];
  }
})">

  <form method="POST" action="{{ route('admin.commission.bulk.update') }}" @submit.prevent="if (selectedIds.length === 0) { alert('กรุณาเลือกช่างภาพอย่างน้อย 1 คน'); return; } if (confirm('ยืนยันปรับค่าคอมมิชชั่น ' + selectedIds.length + ' ช่างภาพ เป็น ' + newRate + '% ?')) { $el.submit(); }">
    @csrf

    <input type="hidden" name="commission_rate" :value="newRate">
    <input type="hidden" name="reason" :value="reason">
    <template x-for="id in selectedIds" :key="id">
      <input type="hidden" name="photographer_ids[]" :value="id">
    </template>

    {{-- Sticky Top Bar --}}
    <div class="sticky top-0 z-10 bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4 dark:bg-slate-800 dark:border-white/[0.06]">
      <div class="flex flex-col md:flex-row md:items-center gap-3">
        <div class="flex items-center gap-2 text-sm">
          <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-500/10">
            <i class="bi bi-people-fill text-indigo-500"></i>
          </span>
          <span class="text-gray-600 dark:text-gray-300">เลือก <strong class="text-indigo-600 dark:text-indigo-400" x-text="selectedIds.length">0</strong> ช่างภาพ</span>
        </div>

        <div class="flex flex-1 flex-col sm:flex-row items-stretch sm:items-center gap-3">
          <div class="flex items-center gap-2">
            <label class="text-sm text-gray-500 whitespace-nowrap">อัตราใหม่ <small class="text-gray-400">({{ $cMin }}–{{ $cMax }})</small></label>
            <div class="relative">
              <input type="number" x-model.number="newRate" min="{{ $cMin }}" max="{{ $cMax }}" step="1"
                     class="w-24 pl-3 pr-8 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/[0.1] dark:text-gray-200">
              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">%</span>
            </div>
          </div>

          <div class="flex-1">
            <input type="text" x-model="reason" placeholder="เหตุผล (ไม่จำเป็น)"
                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/[0.1] dark:text-gray-200">
          </div>

          <button type="submit"
                  class="inline-flex items-center justify-center gap-1.5 bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-lg font-medium px-5 py-2 text-sm transition hover:from-indigo-600 hover:to-indigo-700 disabled:opacity-50 whitespace-nowrap"
                  :disabled="selectedIds.length === 0">
            <i class="bi bi-check2-all"></i> ปรับค่าคอมมิชชั่น
          </button>
        </div>
      </div>
    </div>

    {{-- Photographers Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06] overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-white/[0.02]">
            <tr>
              <th class="px-4 py-3 text-left w-10">
                <input type="checkbox" x-model="selectAll"
                       class="rounded border-gray-300 text-indigo-500 focus:ring-indigo-500 dark:border-white/[0.2] dark:bg-slate-700">
              </th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ช่างภาพ</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">อัตราปัจจุบัน</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">รายได้รวม</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">ระดับ</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
            @forelse($photographers as $pg)
            @php
              $matchedTier = $tiers->filter(fn($t) => $t->min_revenue <= $pg->total_revenue)->sortByDesc('min_revenue')->first();
            @endphp
            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
              <td class="px-4 py-3">
                <input type="checkbox" name="photographer_ids[]" value="{{ $pg->id }}" x-model="selectedIds"
                       class="rounded border-gray-300 text-indigo-500 focus:ring-indigo-500 dark:border-white/[0.2] dark:bg-slate-700">
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <x-avatar :src="$pg->avatar" :name="$pg->display_name" :user-id="$pg->user_id" size="sm" rounded="rounded" />
                  <div class="min-w-0">
                    <div class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $pg->display_name }}</div>
                    <div class="text-xs text-gray-400 truncate">{{ $pg->email }}</div>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                  {{ number_format($pg->commission_rate, 0) }}%
                </span>
              </td>
              <td class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">
                ฿{{ number_format($pg->total_revenue, 0) }}
              </td>
              <td class="px-4 py-3 text-center">
                @if($matchedTier)
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold" style="background:{{ $matchedTier->color }}15;color:{{ $matchedTier->color }};">
                    {{ $matchedTier->name }}
                  </span>
                @else
                  <span class="text-gray-300 dark:text-gray-600">-</span>
                @endif
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="5" class="px-4 py-12 text-center text-gray-400">
                <i class="bi bi-camera text-2xl"></i>
                <p class="mt-1 text-sm">ยังไม่มีช่างภาพในระบบ</p>
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  </form>
</div>
@endsection

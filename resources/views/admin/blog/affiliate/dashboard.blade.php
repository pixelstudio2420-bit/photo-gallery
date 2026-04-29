@extends('layouts.admin')

@section('title', 'แดชบอร์ด Affiliate')

@section('content')
<div x-data="affiliateDashboard()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white">
                <i class="bi bi-graph-up text-purple-500 mr-2"></i>แดชบอร์ด Affiliate
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">วิเคราะห์ผลการดำเนินงาน Affiliate</p>
        </div>
        <a href="{{ route('admin.blog.affiliate.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-xl font-medium text-sm hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
            <i class="bi bi-arrow-left"></i>กลับไปรายการ
        </a>
    </div>

    {{-- Overview Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-5 text-white">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                    <i class="bi bi-hand-index text-lg"></i>
                </div>
                <span class="text-xs bg-white/20 px-2 py-0.5 rounded-full">วันนี้</span>
            </div>
            <p class="text-2xl font-bold">{{ number_format($overview['clicks_today'] ?? 0) }}</p>
            <p class="text-sm text-blue-100 mt-1">คลิกวันนี้</p>
        </div>

        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl p-5 text-white">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                    <i class="bi bi-calendar-week text-lg"></i>
                </div>
                <span class="text-xs bg-white/20 px-2 py-0.5 rounded-full">สัปดาห์นี้</span>
            </div>
            <p class="text-2xl font-bold">{{ number_format($overview['clicks_week'] ?? 0) }}</p>
            <p class="text-sm text-indigo-100 mt-1">คลิกสัปดาห์นี้</p>
        </div>

        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-5 text-white">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                    <i class="bi bi-calendar-month text-lg"></i>
                </div>
                <span class="text-xs bg-white/20 px-2 py-0.5 rounded-full">เดือนนี้</span>
            </div>
            <p class="text-2xl font-bold">{{ number_format($overview['clicks_month'] ?? 0) }}</p>
            <p class="text-sm text-purple-100 mt-1">คลิกเดือนนี้</p>
        </div>

        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl p-5 text-white">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                    <i class="bi bi-currency-dollar text-lg"></i>
                </div>
                <span class="text-xs bg-white/20 px-2 py-0.5 rounded-full">รวม</span>
            </div>
            <p class="text-2xl font-bold">{{ number_format($overview['total_revenue'] ?? 0, 2) }}</p>
            <p class="text-sm text-emerald-100 mt-1">รายได้รวม (บาท)</p>
        </div>
    </div>

    {{-- Chart + Device Breakdown --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- Clicks Chart --}}
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-6">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                <i class="bi bi-bar-chart text-indigo-500"></i>คลิกต่อวัน (30 วันล่าสุด)
            </h3>
            <div class="relative" style="min-height: 250px;">
                <div class="flex items-end gap-1 h-[250px]">
                    @foreach($chartData ?? [] as $day)
                        @php
                            $maxClicks = collect($chartData)->max('clicks') ?: 1;
                            $height = ($day['clicks'] / $maxClicks) * 100;
                        @endphp
                        <div class="flex-1 flex flex-col items-center justify-end group relative">
                            <div class="absolute -top-8 bg-slate-800 dark:bg-white text-white dark:text-slate-800 text-[10px] px-2 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10">
                                {{ $day['date'] }}: {{ $day['clicks'] }} คลิก
                            </div>
                            <div class="w-full bg-indigo-500 dark:bg-indigo-400 rounded-t-sm hover:bg-indigo-600 dark:hover:bg-indigo-300 transition-colors cursor-pointer"
                                 style="height: {{ max($height, 2) }}%; min-height: 2px;"
                                 title="{{ $day['date'] }}: {{ $day['clicks'] }} คลิก"></div>
                        </div>
                    @endforeach
                </div>
                @if(empty($chartData))
                    <div class="absolute inset-0 flex items-center justify-center text-gray-400 dark:text-gray-500">
                        <div class="text-center">
                            <i class="bi bi-bar-chart text-3xl mb-2"></i>
                            <p class="text-sm">ยังไม่มีข้อมูลคลิก</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Device Breakdown --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-6">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                <i class="bi bi-phone text-teal-500"></i>อุปกรณ์
            </h3>
            <div class="space-y-4">
                @php
                    $devices = $deviceBreakdown ?? ['mobile' => 60, 'desktop' => 30, 'tablet' => 10];
                @endphp
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-2">
                            <i class="bi bi-phone text-blue-500"></i>มือถือ
                        </span>
                        <span class="text-sm font-bold text-slate-700 dark:text-gray-200">{{ $devices['mobile'] }}%</span>
                    </div>
                    <div class="w-full bg-gray-100 dark:bg-slate-700 rounded-full h-2.5">
                        <div class="bg-blue-500 h-2.5 rounded-full transition-all" style="width: {{ $devices['mobile'] }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-2">
                            <i class="bi bi-laptop text-indigo-500"></i>เดสก์ท็อป
                        </span>
                        <span class="text-sm font-bold text-slate-700 dark:text-gray-200">{{ $devices['desktop'] }}%</span>
                    </div>
                    <div class="w-full bg-gray-100 dark:bg-slate-700 rounded-full h-2.5">
                        <div class="bg-indigo-500 h-2.5 rounded-full transition-all" style="width: {{ $devices['desktop'] }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm text-gray-600 dark:text-gray-300 flex items-center gap-2">
                            <i class="bi bi-tablet text-purple-500"></i>แท็บเล็ต
                        </span>
                        <span class="text-sm font-bold text-slate-700 dark:text-gray-200">{{ $devices['tablet'] }}%</span>
                    </div>
                    <div class="w-full bg-gray-100 dark:bg-slate-700 rounded-full h-2.5">
                        <div class="bg-purple-500 h-2.5 rounded-full transition-all" style="width: {{ $devices['tablet'] }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Top Links + Top Posts --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Top Performing Links --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="bi bi-trophy text-amber-500"></i>ลิงก์ยอดนิยม
                </h3>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                @forelse($topLinks ?? [] as $index => $topLink)
                <div class="px-5 py-3 flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xs font-bold flex-shrink-0">
                        {{ $index + 1 }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-700 dark:text-gray-200 truncate">{{ $topLink->name }}</p>
                        <p class="text-xs text-gray-400">/go/{{ $topLink->slug }}</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-sm font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($topLink->total_clicks ?? 0) }}</p>
                        <p class="text-[10px] text-gray-400">คลิก</p>
                    </div>
                </div>
                @empty
                <div class="px-5 py-8 text-center text-gray-400 text-sm">ยังไม่มีข้อมูล</div>
                @endforelse
            </div>
        </div>

        {{-- Top Performing Posts --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="bi bi-file-earmark-richtext text-emerald-500"></i>บทความ Affiliate ยอดนิยม
                </h3>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                @forelse($topPosts ?? [] as $index => $topPost)
                <div class="px-5 py-3 flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs font-bold flex-shrink-0">
                        {{ $index + 1 }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-700 dark:text-gray-200 truncate">{{ $topPost->title }}</p>
                        <p class="text-xs text-gray-400">{{ $topPost->views_count ?? 0 }} ยอดดู</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($topPost->affiliate_clicks ?? 0) }}</p>
                        <p class="text-[10px] text-gray-400">คลิก Affiliate</p>
                    </div>
                </div>
                @empty
                <div class="px-5 py-8 text-center text-gray-400 text-sm">ยังไม่มีข้อมูล</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- CTA Buttons Performance --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="bi bi-megaphone text-pink-500"></i>ประสิทธิภาพปุ่ม CTA
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/80 dark:bg-slate-700/50">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">CTA</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Impressions</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">คลิก</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">CTR</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                    @forelse($ctaPerformance ?? [] as $cta)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-slate-700/30">
                        <td class="px-4 py-3">
                            <span class="font-medium text-slate-700 dark:text-gray-200">{{ $cta->name ?? $cta->label ?? '-' }}</span>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-300">{{ number_format($cta->impressions ?? 0) }}</td>
                        <td class="px-4 py-3 text-center font-semibold text-indigo-600 dark:text-indigo-400">{{ number_format($cta->clicks ?? 0) }}</td>
                        <td class="px-4 py-3 text-center">
                            @php $ctr = ($cta->impressions ?? 0) > 0 ? round(($cta->clicks / $cta->impressions) * 100, 2) : 0; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ $ctr > 5 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' : ($ctr > 2 ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-500/20 dark:text-gray-400') }}">
                                {{ $ctr }}%
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-400 text-sm">ยังไม่มีข้อมูล CTA</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function affiliateDashboard() {
    return {};
}
</script>
@endpush

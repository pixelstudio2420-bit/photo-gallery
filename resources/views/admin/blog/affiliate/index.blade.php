@extends('layouts.admin')

@section('title', 'จัดการลิงก์ Affiliate')

@section('content')
<div x-data="affiliateManager()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white">
                <i class="bi bi-link-45deg text-purple-500 mr-2"></i>จัดการลิงก์ Affiliate
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">จัดการลิงก์ Affiliate และติดตามผลการคลิก</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.blog.affiliate.dashboard') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-purple-100 dark:bg-purple-500/20 text-purple-700 dark:text-purple-400 rounded-xl font-medium text-sm hover:bg-purple-200 dark:hover:bg-purple-500/30 transition-colors">
                <i class="bi bi-graph-up"></i>แดชบอร์ด
            </a>
            <a href="{{ route('admin.blog.affiliate.create') }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-medium text-sm transition-colors shadow-lg shadow-indigo-500/25">
                <i class="bi bi-plus-lg"></i>เพิ่มลิงก์
            </a>
        </div>
    </div>

    {{-- Stats Bar --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-gray-100 dark:border-white/[0.06]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-500/10 flex items-center justify-center">
                    <i class="bi bi-link-45deg text-purple-500 text-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">ลิงก์ทั้งหมด</p>
                    <p class="text-xl font-bold text-slate-800 dark:text-white">{{ $stats['total'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-gray-100 dark:border-white/[0.06]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center">
                    <i class="bi bi-check-circle text-emerald-500 text-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">ใช้งานอยู่</p>
                    <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ $stats['active'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-gray-100 dark:border-white/[0.06]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center">
                    <i class="bi bi-hand-index text-blue-500 text-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">คลิกวันนี้</p>
                    <p class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($stats['clicks_today'] ?? 0) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-gray-100 dark:border-white/[0.06]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center">
                    <i class="bi bi-currency-dollar text-amber-500 text-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">รายได้รวม</p>
                    <p class="text-xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($stats['total_revenue'] ?? 0, 2) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Links Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/80 dark:bg-slate-700/50">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">ชื่อ</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">URL (Cloaked)</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">ปลายทาง</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">ผู้ให้บริการ</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">ค่าคอม %</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">คลิก</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Conversions</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">สถานะ</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                    @forelse($links ?? [] as $link)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition-colors">
                        {{-- Name --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if($link->image)
                                    <img src="{{ asset('storage/' . $link->image) }}" alt="" class="w-10 h-10 rounded-lg object-cover">
                                @else
                                    <div class="w-10 h-10 rounded-lg bg-purple-50 dark:bg-purple-500/10 flex items-center justify-center">
                                        <i class="bi bi-link-45deg text-purple-500"></i>
                                    </div>
                                @endif
                                <span class="font-semibold text-slate-800 dark:text-white">{{ $link->name }}</span>
                            </div>
                        </td>

                        {{-- Cloaked URL --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <code class="text-xs text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 px-2 py-1 rounded">/go/{{ $link->slug }}</code>
                                <button @click="copyUrl('{{ url('/go/' . $link->slug) }}')" title="คัดลอก URL"
                                        class="text-gray-400 hover:text-indigo-500 transition-colors">
                                    <i class="bi bi-clipboard text-xs"></i>
                                </button>
                            </div>
                        </td>

                        {{-- Destination --}}
                        <td class="px-4 py-3">
                            <a href="{{ $link->destination_url }}" target="_blank" title="{{ $link->destination_url }}"
                               class="text-xs text-gray-500 dark:text-gray-400 hover:text-indigo-500 truncate block max-w-[200px]">
                                {{ Str::limit($link->destination_url, 40) }}
                                <i class="bi bi-box-arrow-up-right ml-1 text-[10px]"></i>
                            </a>
                        </td>

                        {{-- Provider --}}
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs text-gray-600 dark:text-gray-300">{{ $link->provider ?? '-' }}</span>
                        </td>

                        {{-- Commission --}}
                        <td class="px-4 py-3 text-center">
                            @if($link->commission_rate)
                                <span class="inline-flex items-center px-2 py-0.5 bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 rounded text-xs font-bold">
                                    {{ $link->commission_rate }}%
                                </span>
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>

                        {{-- Clicks --}}
                        <td class="px-4 py-3 text-center">
                            <span class="text-sm font-semibold text-slate-700 dark:text-gray-200">{{ number_format($link->total_clicks ?? 0) }}</span>
                        </td>

                        {{-- Conversions --}}
                        <td class="px-4 py-3 text-center">
                            <span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format($link->conversions ?? 0) }}</span>
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3 text-center">
                            @if($link->is_active)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400">
                                    ใช้งาน
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-500/20 dark:text-gray-400">
                                    ปิดใช้งาน
                                </span>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <button @click="copyUrl('{{ url('/go/' . $link->slug) }}')" title="คัดลอก URL"
                                        class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-gray-400 flex items-center justify-center hover:bg-indigo-100 hover:text-indigo-600 dark:hover:bg-indigo-500/20 dark:hover:text-indigo-400 transition-colors">
                                    <i class="bi bi-clipboard text-xs"></i>
                                </button>
                                <a href="{{ route('admin.blog.affiliate.edit', $link) }}" title="แก้ไข"
                                   class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-gray-400 flex items-center justify-center hover:bg-amber-100 hover:text-amber-600 dark:hover:bg-amber-500/20 dark:hover:text-amber-400 transition-colors">
                                    <i class="bi bi-pencil text-xs"></i>
                                </a>
                                <button @click="deleteLink({{ $link->id }}, '{{ $link->name }}')" title="ลบ"
                                        class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-gray-400 flex items-center justify-center hover:bg-red-100 hover:text-red-600 dark:hover:bg-red-500/20 dark:hover:text-red-400 transition-colors">
                                    <i class="bi bi-trash text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-3">
                                    <i class="bi bi-link-45deg text-2xl text-gray-400"></i>
                                </div>
                                <p class="text-gray-500 dark:text-gray-400 font-medium">ยังไม่มีลิงก์ Affiliate</p>
                                <a href="{{ route('admin.blog.affiliate.create') }}"
                                   class="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-medium transition-colors">
                                    <i class="bi bi-plus-lg"></i>เพิ่มลิงก์แรก
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($links) && $links->hasPages())
        <div class="px-4 py-3 border-t border-gray-100 dark:border-white/[0.06]">
            {{ $links->appends(request()->query())->links() }}
        </div>
        @endif
    </div>

    <form id="deleteLinkForm" method="POST" class="hidden">@csrf @method('DELETE')</form>
</div>
@endsection

@push('scripts')
<script>
function affiliateManager() {
    return {
        copyUrl(url) {
            navigator.clipboard.writeText(url).then(() => {
                Swal.fire({ icon: 'success', title: 'คัดลอก URL แล้ว', text: url, timer: 1500, showConfirmButton: false });
            });
        },

        deleteLink(id, name) {
            Swal.fire({
                title: 'ลบลิงก์ Affiliate?',
                html: `คุณต้องการลบ <strong>${name}</strong>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ลบเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('deleteLinkForm');
                    form.action = `{{ url('admin/blog/affiliate') }}/${id}`;
                    form.submit();
                }
            });
        }
    };
}
</script>
@endpush

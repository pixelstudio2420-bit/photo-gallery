@extends('layouts.admin')

@section('title', 'จัดการแท็ก')

@section('content')
<div x-data="tagManager()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white">
                <i class="bi bi-tags text-teal-500 mr-2"></i>จัดการแท็ก
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">จัดการแท็กสำหรับจัดกลุ่มบทความ</p>
        </div>
    </div>

    {{-- Quick Create Form --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-5 mb-6">
        <form method="POST" action="{{ route('admin.blog.tags.store') }}" class="flex flex-col sm:flex-row items-end gap-3">
            @csrf
            <div class="flex-1 w-full">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">ชื่อแท็ก <span class="text-red-500">*</span></label>
                <input type="text" name="name" required placeholder="พิมพ์ชื่อแท็กใหม่..."
                       class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="w-full sm:w-48">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Slug (ไม่บังคับ)</label>
                <input type="text" name="slug" placeholder="auto-generated"
                       class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500 font-mono">
            </div>
            <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-medium transition-colors shadow-lg shadow-indigo-500/25 whitespace-nowrap">
                <i class="bi bi-plus-lg mr-1"></i>สร้างแท็ก
            </button>
        </form>
    </div>

    {{-- Bulk Actions --}}
    <div class="flex items-center gap-3 mb-4" x-show="selectedTags.length > 0" x-cloak x-transition>
        <span class="text-sm text-gray-500 dark:text-gray-400">
            เลือก <strong class="text-indigo-600 dark:text-indigo-400" x-text="selectedTags.length"></strong> รายการ
        </span>
        <button @click="bulkDelete()"
                class="px-3 py-1.5 text-xs font-medium bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400 rounded-lg hover:bg-red-200 transition-colors">
            <i class="bi bi-trash mr-1"></i>ลบที่เลือก
        </button>
    </div>

    {{-- Tags Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/80 dark:bg-slate-700/50">
                        <th class="w-10 px-4 py-3">
                            <input type="checkbox" @change="toggleAll($event)"
                                   class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">ชื่อ</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Slug</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">จำนวนบทความ</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                    @forelse($tags ?? [] as $tag)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition-colors"
                        x-data="{ editing: false, editName: '{{ $tag->name }}', editSlug: '{{ $tag->slug }}' }">
                        {{-- Checkbox --}}
                        <td class="px-4 py-3">
                            <input type="checkbox" value="{{ $tag->id }}" x-model="selectedTags"
                                   class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </td>

                        {{-- Name --}}
                        <td class="px-4 py-3">
                            <template x-if="!editing">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2.5 py-1 bg-teal-50 dark:bg-teal-500/10 text-teal-700 dark:text-teal-400 rounded-lg text-sm font-medium">
                                        <i class="bi bi-hash mr-0.5"></i>{{ $tag->name }}
                                    </span>
                                </div>
                            </template>
                            <template x-if="editing">
                                <input type="text" x-model="editName"
                                       class="text-sm px-2 py-1 border border-indigo-300 rounded-lg bg-white dark:bg-slate-700 dark:text-white focus:ring-2 focus:ring-indigo-500 w-40">
                            </template>
                        </td>

                        {{-- Slug --}}
                        <td class="px-4 py-3">
                            <template x-if="!editing">
                                <code class="text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-slate-700 px-2 py-1 rounded">{{ $tag->slug }}</code>
                            </template>
                            <template x-if="editing">
                                <input type="text" x-model="editSlug"
                                       class="text-sm px-2 py-1 border border-indigo-300 rounded-lg bg-white dark:bg-slate-700 dark:text-white focus:ring-2 focus:ring-indigo-500 w-40 font-mono">
                            </template>
                        </td>

                        {{-- Posts Count --}}
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-sm font-bold">
                                {{ $tag->posts_count ?? 0 }}
                            </span>
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <template x-if="!editing">
                                    <div class="flex items-center gap-2">
                                        <button @click="editing = true" title="แก้ไข"
                                                class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-500 flex items-center justify-center hover:bg-indigo-100 dark:hover:bg-indigo-500/20 transition-colors">
                                            <i class="bi bi-pencil text-sm"></i>
                                        </button>
                                        <button @click="deleteTag({{ $tag->id }}, '{{ $tag->name }}')" title="ลบ"
                                                class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-500/10 text-red-500 flex items-center justify-center hover:bg-red-100 dark:hover:bg-red-500/20 transition-colors">
                                            <i class="bi bi-trash text-sm"></i>
                                        </button>
                                    </div>
                                </template>
                                <template x-if="editing">
                                    <div class="flex items-center gap-2">
                                        <button @click="saveTag({{ $tag->id }}, editName, editSlug)" title="บันทึก"
                                                class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-500 flex items-center justify-center hover:bg-emerald-100 dark:hover:bg-emerald-500/20 transition-colors">
                                            <i class="bi bi-check-lg text-sm"></i>
                                        </button>
                                        <button @click="editing = false" title="ยกเลิก"
                                                class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 text-gray-500 flex items-center justify-center hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
                                            <i class="bi bi-x-lg text-sm"></i>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-3">
                                    <i class="bi bi-tags text-2xl text-gray-400"></i>
                                </div>
                                <p class="text-gray-500 dark:text-gray-400 font-medium">ยังไม่มีแท็ก</p>
                                <p class="text-sm text-gray-400 mt-1">สร้างแท็กแรกได้จากฟอร์มด้านบน</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($tags) && $tags->hasPages())
        <div class="px-4 py-3 border-t border-gray-100 dark:border-white/[0.06]">
            {{ $tags->links() }}
        </div>
        @endif
    </div>

    <form id="deleteTagForm" method="POST" class="hidden">@csrf @method('DELETE')</form>
    <form id="bulkDeleteForm" method="POST" action="{{ route('admin.blog.tags.bulk-delete') }}" class="hidden">
        @csrf
        <input type="hidden" name="ids" id="bulkTagIds">
    </form>
</div>
@endsection

@push('scripts')
<script>
function tagManager() {
    return {
        selectedTags: [],

        toggleAll(event) {
            if (event.target.checked) {
                this.selectedTags = Array.from(document.querySelectorAll('tbody input[type="checkbox"]')).map(cb => cb.value);
            } else {
                this.selectedTags = [];
            }
        },

        saveTag(id, name, slug) {
            fetch(`{{ url('admin/blog/tags') }}/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ name, slug })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'บันทึกแล้ว', timer: 1200, showConfirmButton: false });
                    setTimeout(() => location.reload(), 1200);
                } else {
                    Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: data.message || 'ไม่สามารถบันทึกได้' });
                }
            }).catch(() => {
                Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: 'เกิดข้อผิดพลาดในการเชื่อมต่อ' });
            });
        },

        deleteTag(id, name) {
            Swal.fire({
                title: 'ลบแท็ก?',
                html: `คุณต้องการลบแท็ก <strong>#${name}</strong>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ลบเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('deleteTagForm');
                    form.action = `{{ url('admin/blog/tags') }}/${id}`;
                    form.submit();
                }
            });
        },

        bulkDelete() {
            if (this.selectedTags.length === 0) return;
            Swal.fire({
                title: 'ลบแท็กที่เลือก?',
                text: `คุณต้องการลบ ${this.selectedTags.length} แท็กที่เลือก?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ลบทั้งหมด',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('bulkTagIds').value = JSON.stringify(this.selectedTags);
                    document.getElementById('bulkDeleteForm').submit();
                }
            });
        }
    };
}
</script>
@endpush

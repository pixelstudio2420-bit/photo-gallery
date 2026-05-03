@extends('layouts.admin')

@section('title', 'จัดการบทความ')

@push('styles')
<style>
    .seo-bar { height: 6px; border-radius: 3px; transition: width 0.3s ease; }
    .line-clamp-1 { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; }
    .line-clamp-2 { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
</style>
@endpush

@section('content')
<div x-data="blogPostsManager()" x-init="init()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white">
                <i class="bi bi-file-earmark-richtext text-indigo-500 mr-2"></i>จัดการบทความ
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">จัดการบทความทั้งหมดในระบบบล็อก</p>
        </div>
        <a href="{{ route('admin.blog.posts.create') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-medium text-sm transition-colors shadow-lg shadow-indigo-500/25">
            <i class="bi bi-plus-lg"></i>
            สร้างบทความ
        </a>
    </div>

    {{-- Stats Bar --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-gray-100 dark:border-white/[0.06]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center">
                    <i class="bi bi-file-earmark-text text-indigo-500 text-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">บทความทั้งหมด</p>
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
                    <p class="text-xs text-gray-500 dark:text-gray-400">เผยแพร่แล้ว</p>
                    <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ $stats['published'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-gray-100 dark:border-white/[0.06]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center">
                    <i class="bi bi-pencil-square text-amber-500 text-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">แบบร่าง</p>
                    <p class="text-xl font-bold text-amber-600 dark:text-amber-400">{{ $stats['drafts'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-gray-100 dark:border-white/[0.06]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center">
                    <i class="bi bi-eye text-blue-500 text-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">ยอดดูทั้งหมด</p>
                    <p class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($stats['total_views'] ?? 0) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-4 mb-6">
        <form method="GET" action="{{ route('admin.blog.posts.index') }}" id="filterForm">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                {{-- Search --}}
                <div class="xl:col-span-2">
                    <div class="relative">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="ค้นหาบทความ..."
                               class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white">
                    </div>
                </div>

                {{-- Status --}}
                <select name="status" onchange="document.getElementById('filterForm').submit()"
                        class="text-sm border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 py-2.5 px-3 focus:ring-2 focus:ring-indigo-500 dark:text-white">
                    <option value="">สถานะทั้งหมด</option>
                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>แบบร่าง</option>
                    <option value="published" {{ request('status') == 'published' ? 'selected' : '' }}>เผยแพร่แล้ว</option>
                    <option value="scheduled" {{ request('status') == 'scheduled' ? 'selected' : '' }}>กำหนดเวลา</option>
                    <option value="archived" {{ request('status') == 'archived' ? 'selected' : '' }}>เก็บถาวร</option>
                </select>

                {{-- Category --}}
                <select name="category" onchange="document.getElementById('filterForm').submit()"
                        class="text-sm border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 py-2.5 px-3 focus:ring-2 focus:ring-indigo-500 dark:text-white">
                    <option value="">หมวดหมู่ทั้งหมด</option>
                    @foreach($categories ?? [] as $category)
                        <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>

                {{-- Toggles --}}
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="affiliate" value="1" {{ request('affiliate') ? 'checked' : '' }}
                               onchange="document.getElementById('filterForm').submit()"
                               class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-600 dark:text-gray-300">Affiliate</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="featured" value="1" {{ request('featured') ? 'checked' : '' }}
                               onchange="document.getElementById('filterForm').submit()"
                               class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-600 dark:text-gray-300">แนะนำ</span>
                    </label>
                </div>

                {{-- Sort --}}
                <select name="sort" onchange="document.getElementById('filterForm').submit()"
                        class="text-sm border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 py-2.5 px-3 focus:ring-2 focus:ring-indigo-500 dark:text-white">
                    <option value="latest" {{ request('sort') == 'latest' ? 'selected' : '' }}>ล่าสุด</option>
                    <option value="oldest" {{ request('sort') == 'oldest' ? 'selected' : '' }}>เก่าสุด</option>
                    <option value="most_views" {{ request('sort') == 'most_views' ? 'selected' : '' }}>ยอดดูมากสุด</option>
                    <option value="title" {{ request('sort') == 'title' ? 'selected' : '' }}>ชื่อ A-Z</option>
                </select>
            </div>
        </form>
    </div>

    {{-- Bulk Actions --}}
    <div class="flex items-center gap-3 mb-4" x-show="selectedPosts.length > 0" x-cloak x-transition>
        <span class="text-sm text-gray-500 dark:text-gray-400">
            เลือก <strong class="text-indigo-600 dark:text-indigo-400" x-text="selectedPosts.length"></strong> รายการ
        </span>
        <button @click="bulkAction('publish')"
                class="px-3 py-1.5 text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400 rounded-lg hover:bg-emerald-200 transition-colors">
            <i class="bi bi-check-circle mr-1"></i>เผยแพร่
        </button>
        <button @click="bulkAction('draft')"
                class="px-3 py-1.5 text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400 rounded-lg hover:bg-amber-200 transition-colors">
            <i class="bi bi-pencil mr-1"></i>แบบร่าง
        </button>
        <button @click="bulkAction('delete')"
                class="px-3 py-1.5 text-xs font-medium bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400 rounded-lg hover:bg-red-200 transition-colors">
            <i class="bi bi-trash mr-1"></i>ลบ
        </button>
    </div>

    {{-- Posts Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/80 dark:bg-slate-700/50">
                        <th class="w-10 px-4 py-3">
                            <input type="checkbox" @change="toggleAll($event)"
                                   class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">บทความ</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">หมวดหมู่</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">สถานะ</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">SEO</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">ยอดดู</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">วันที่เผยแพร่</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                    @forelse($posts ?? [] as $post)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-slate-700/30 transition-colors">
                        {{-- Checkbox --}}
                        <td class="px-4 py-3">
                            <input type="checkbox" value="{{ $post->id }}" x-model="selectedPosts"
                                   class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </td>

                        {{-- Post Info --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if($post->featured_image)
                                    <img src="{{ asset('storage/' . $post->featured_image) }}" alt=""
                                         class="w-12 h-12 rounded-lg object-cover flex-shrink-0 border border-gray-200 dark:border-white/10">
                                @else
                                    <div class="w-12 h-12 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                                        <i class="bi bi-image text-gray-400 text-lg"></i>
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <a href="{{ route('admin.blog.posts.edit', $post) }}"
                                       class="text-sm font-semibold text-slate-800 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors line-clamp-1">
                                        {{ $post->title }}
                                    </a>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 line-clamp-1">/{{ $post->slug }}</p>
                                    <div class="flex items-center gap-1.5 mt-1">
                                        @if($post->is_featured)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400">
                                                <i class="bi bi-star-fill mr-0.5"></i>แนะนำ
                                            </span>
                                        @endif
                                        @if($post->is_affiliate_post)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-400">
                                                <i class="bi bi-link-45deg mr-0.5"></i>Affiliate
                                            </span>
                                        @endif
                                        @if($post->is_ai_generated)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-cyan-100 text-cyan-700 dark:bg-cyan-500/20 dark:text-cyan-400">
                                                <i class="bi bi-robot mr-0.5"></i>AI
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>

                        {{-- Category --}}
                        <td class="px-4 py-3">
                            @if($post->category)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium"
                                      style="background-color: {{ $post->category->color ?? '#6366f1' }}20; color: {{ $post->category->color ?? '#6366f1' }};">
                                    @if($post->category->icon)
                                        <i class="bi bi-{{ $post->category->icon }} mr-1"></i>
                                    @endif
                                    {{ $post->category->name }}
                                </span>
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3 text-center">
                            @php
                                $statusStyles = [
                                    'draft' => 'bg-gray-100 text-gray-600 dark:bg-gray-500/20 dark:text-gray-400',
                                    'published' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400',
                                    'scheduled' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400',
                                    'archived' => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400',
                                ];
                                $statusLabels = [
                                    'draft' => 'แบบร่าง',
                                    'published' => 'เผยแพร่แล้ว',
                                    'scheduled' => 'กำหนดเวลา',
                                    'archived' => 'เก็บถาวร',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium {{ $statusStyles[$post->status] ?? $statusStyles['draft'] }}">
                                {{ $statusLabels[$post->status] ?? $post->status }}
                            </span>
                        </td>

                        {{-- SEO Score --}}
                        <td class="px-4 py-3">
                            @php
                                $seoScore = $post->seo_score ?? 0;
                                $seoColor = $seoScore > 70 ? 'bg-emerald-500' : ($seoScore > 40 ? 'bg-amber-500' : 'bg-red-500');
                                $seoBg = $seoScore > 70 ? 'bg-emerald-100 dark:bg-emerald-500/20' : ($seoScore > 40 ? 'bg-amber-100 dark:bg-amber-500/20' : 'bg-red-100 dark:bg-red-500/20');
                            @endphp
                            <div class="flex flex-col items-center gap-1">
                                <span class="text-xs font-semibold {{ $seoScore > 70 ? 'text-emerald-600' : ($seoScore > 40 ? 'text-amber-600' : 'text-red-600') }}">
                                    {{ $seoScore }}%
                                </span>
                                <div class="w-16 {{ $seoBg }} rounded-full overflow-hidden">
                                    <div class="seo-bar {{ $seoColor }}" style="width: {{ $seoScore }}%"></div>
                                </div>
                            </div>
                        </td>

                        {{-- Views --}}
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-300">
                                <i class="bi bi-eye text-xs text-gray-400"></i>
                                {{ number_format($post->views_count ?? 0) }}
                            </span>
                        </td>

                        {{-- Published Date --}}
                        <td class="px-4 py-3 text-center">
                            @if($post->status === 'scheduled' && $post->published_at)
                                <div class="text-xs">
                                    <span class="text-blue-600 dark:text-blue-400 font-medium">กำลังจะเผยแพร่</span>
                                    <br>
                                    <span class="text-gray-400">{{ $post->published_at->format('d/m/Y H:i') }}</span>
                                </div>
                            @elseif($post->published_at)
                                <span class="text-xs text-gray-600 dark:text-gray-300">
                                    {{ $post->published_at->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3 text-center">
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" @click.outside="open = false"
                                        class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors mx-auto">
                                    <i class="bi bi-three-dots-vertical text-gray-500 dark:text-gray-400 text-sm"></i>
                                </button>
                                <div x-show="open" x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                                     class="absolute right-0 mt-1 w-48 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-100 dark:border-white/10 py-1 z-50"
                                     x-cloak>
                                    <a href="{{ route('admin.blog.posts.edit', $post) }}"
                                       class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700/50">
                                        <i class="bi bi-pencil-square text-indigo-500"></i>แก้ไข
                                    </a>
                                    @if($post->status === 'published')
                                    <a href="{{ route('admin.blog.posts.show', $post) }}" target="_blank"
                                       class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700/50">
                                        <i class="bi bi-eye text-blue-500"></i>ดูหน้าเว็บ
                                    </a>
                                    @endif
                                    <button @click="duplicatePost({{ $post->id }}); open = false"
                                            class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700/50 w-full text-left">
                                        <i class="bi bi-copy text-teal-500"></i>ทำสำเนา
                                    </button>
                                    <button @click="toggleFeatured({{ $post->id }}); open = false"
                                            class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700/50 w-full text-left">
                                        <i class="bi bi-star{{ $post->is_featured ? '-fill text-amber-500' : ' text-gray-400' }}"></i>
                                        {{ $post->is_featured ? 'ยกเลิกแนะนำ' : 'ตั้งเป็นแนะนำ' }}
                                    </button>
                                    <button @click="toggleStatus({{ $post->id }}); open = false"
                                            class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700/50 w-full text-left">
                                        <i class="bi bi-toggle-{{ $post->status === 'published' ? 'on text-emerald-500' : 'off text-gray-400' }}"></i>
                                        {{ $post->status === 'published' ? 'เปลี่ยนเป็นแบบร่าง' : 'เผยแพร่' }}
                                    </button>
                                    <div class="border-t border-gray-100 dark:border-white/[0.06] my-1"></div>
                                    <button @click="deletePost({{ $post->id }}); open = false"
                                            class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 w-full text-left">
                                        <i class="bi bi-trash"></i>ลบ
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-4">
                                    <i class="bi bi-file-earmark-text text-3xl text-gray-400"></i>
                                </div>
                                <p class="text-gray-500 dark:text-gray-400 font-medium">ยังไม่มีบทความ</p>
                                <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">เริ่มต้นสร้างบทความแรกของคุณ</p>
                                <a href="{{ route('admin.blog.posts.create') }}"
                                   class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-medium transition-colors">
                                    <i class="bi bi-plus-lg"></i>สร้างบทความ
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if(isset($posts) && $posts->hasPages())
        <div class="px-4 py-3 border-t border-gray-100 dark:border-white/[0.06]">
            {{ $posts->appends(request()->query())->links() }}
        </div>
        @endif
    </div>

    {{-- Delete Form (hidden) --}}
    <form id="deleteForm" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>
    {{-- Bulk-action submission is now handled via fetch() in
         bulkAction() above — sending ids[] as a real PHP array.
         The previous hidden <form> with name="ids" only sent a JSON
         string, which silently failed Laravel's `array` validation. --}}
</div>
@endsection

@push('scripts')
<script>
function blogPostsManager() {
    return {
        selectedPosts: [],

        init() {},

        toggleAll(event) {
            if (event.target.checked) {
                this.selectedPosts = Array.from(document.querySelectorAll('tbody input[type="checkbox"]')).map(cb => cb.value);
            } else {
                this.selectedPosts = [];
            }
        },

        bulkAction(action) {
            if (this.selectedPosts.length === 0) return;

            const labels = { publish: 'เผยแพร่', draft: 'เปลี่ยนเป็นแบบร่าง', delete: 'ลบ' };

            Swal.fire({
                title: 'ยืนยันการดำเนินการ',
                text: `คุณต้องการ${labels[action]} ${this.selectedPosts.length} บทความที่เลือก?`,
                icon: action === 'delete' ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonColor: action === 'delete' ? '#ef4444' : '#6366f1',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (!result.isConfirmed) return;

                // The controller returns a JSON response, so we submit
                // via fetch + FormData (sending ids[] as a real PHP
                // array — JSON.stringify would arrive as a single
                // string and fail the `array` validation rule, which
                // is why the publish-all button was silently failing).
                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('action', action);
                this.selectedPosts.forEach(id => formData.append('ids[]', id));

                fetch('{{ route('admin.blog.posts.bulk-action') }}', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                .then(async r => ({ status: r.status, body: await r.json().catch(() => ({})) }))
                .then(({ status, body }) => {
                    if (status === 200 && body.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ',
                            text: body.message || 'ดำเนินการเรียบร้อย',
                            timer: 1500,
                            showConfirmButton: false,
                        }).then(() => window.location.reload());
                    } else {
                        // Validation errors come back as 422 + body.errors map
                        const detail = body.message
                            || (body.errors ? Object.values(body.errors).flat().join(', ') : '')
                            || 'ดำเนินการไม่สำเร็จ';
                        Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: detail });
                    }
                })
                .catch(e => {
                    Swal.fire({ icon: 'error', title: 'เครือข่ายมีปัญหา', text: e.message });
                });
            });
        },

        duplicatePost(id) {
            Swal.fire({
                title: 'ทำสำเนาบทความ?',
                text: 'ระบบจะสร้างบทความใหม่จากบทความนี้',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#6366f1',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ทำสำเนา',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `{{ url('admin/blog/posts') }}/${id}/duplicate`;
                }
            });
        },

        toggleFeatured(id) {
            fetch(`{{ url('admin/blog/posts') }}/${id}/toggle-featured`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'สำเร็จ', text: data.message, timer: 1500, showConfirmButton: false });
                    setTimeout(() => location.reload(), 1500);
                }
            });
        },

        toggleStatus(id) {
            fetch(`{{ url('admin/blog/posts') }}/${id}/toggle-status`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'สำเร็จ', text: data.message, timer: 1500, showConfirmButton: false });
                    setTimeout(() => location.reload(), 1500);
                }
            });
        },

        deletePost(id) {
            Swal.fire({
                title: 'ลบบทความ?',
                text: 'เมื่อลบแล้วจะไม่สามารถกู้คืนได้',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ลบเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('deleteForm');
                    form.action = `{{ url('admin/blog/posts') }}/${id}`;
                    form.submit();
                }
            });
        }
    };
}
</script>
@endpush

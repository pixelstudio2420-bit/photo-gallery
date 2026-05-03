{{-- Shared form partial for blog post create/edit --}}
@php
    $isEdit = isset($post) && $post->exists;
@endphp

@push('styles')
<style>
    .editor-toolbar button { @apply w-8 h-8 rounded-lg flex items-center justify-center text-gray-500 hover:bg-gray-100 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-slate-700 dark:hover:text-indigo-400 transition-colors text-sm; }
    .editor-toolbar button.active { @apply bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400; }
    .char-counter { @apply text-xs text-gray-400; }
    .char-counter.warning { @apply text-amber-500; }
    .char-counter.danger { @apply text-red-500; }
    .tag-chip { @apply inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-50 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-400 rounded-lg text-xs font-medium; }
    .drop-zone { @apply border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-6 text-center cursor-pointer transition-colors; }
    .drop-zone.dragover { @apply border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10; }
    .ai-tool-tab { @apply px-3 py-2 text-xs font-medium rounded-lg transition-colors cursor-pointer; }
    .ai-tool-tab.active { @apply bg-indigo-600 text-white; }
    .ai-tool-tab:not(.active) { @apply text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-700; }

    /* ═══ AI Tools Panel — Premium Redesign ═══ */
    .ai-tab {
        @apply inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl transition-all duration-200 cursor-pointer whitespace-nowrap;
    }
    .ai-tab i { @apply text-sm; }
    .ai-tab-inactive {
        @apply text-gray-600 dark:text-gray-300 bg-white dark:bg-slate-800 border border-gray-200/70 dark:border-white/[0.07]
               hover:border-violet-300 dark:hover:border-violet-400/40 hover:text-violet-700 dark:hover:text-violet-300 hover:-translate-y-0.5;
    }
    .ai-tab-active-violet   { @apply text-white bg-gradient-to-br from-violet-500 to-indigo-600 shadow-lg shadow-violet-500/35 -translate-y-0.5; }
    .ai-tab-active-blue     { @apply text-white bg-gradient-to-br from-blue-500 to-cyan-600 shadow-lg shadow-blue-500/35 -translate-y-0.5; }
    .ai-tab-active-emerald  { @apply text-white bg-gradient-to-br from-emerald-500 to-teal-600 shadow-lg shadow-emerald-500/35 -translate-y-0.5; }
    .ai-tab-active-amber    { @apply text-white bg-gradient-to-br from-amber-500 to-orange-600 shadow-lg shadow-amber-500/35 -translate-y-0.5; }
    .ai-tab-active-rose     { @apply text-white bg-gradient-to-br from-rose-500 to-pink-600 shadow-lg shadow-rose-500/35 -translate-y-0.5; }
    .ai-tab-active-fuchsia  { @apply text-white bg-gradient-to-br from-fuchsia-500 to-purple-600 shadow-lg shadow-fuchsia-500/35 -translate-y-0.5; }

    /* AI provider cards */
    .ai-provider-card {
        @apply relative flex items-center gap-2.5 p-3 rounded-xl border-2 cursor-pointer transition-all duration-200 text-left;
    }
    .ai-provider {
        @apply border-gray-200/70 dark:border-white/[0.07] bg-white dark:bg-slate-800/60 hover:border-violet-300 dark:hover:border-violet-400/40 hover:-translate-y-0.5;
    }
    .ai-provider-active {
        @apply border-violet-500 dark:border-violet-400 bg-gradient-to-br from-violet-50 to-indigo-50/60 dark:from-violet-500/15 dark:to-indigo-500/10 shadow-md shadow-violet-500/15;
    }

    /* Tone / language chip groups */
    .ai-chip {
        @apply inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-lg cursor-pointer transition-all duration-150 border;
    }
    .ai-chip-inactive {
        @apply text-gray-600 dark:text-gray-300 bg-white dark:bg-slate-800/60 border-gray-200/70 dark:border-white/[0.07] hover:border-violet-300 dark:hover:border-violet-400/40 hover:text-violet-700 dark:hover:text-violet-300;
    }
    .ai-chip-active {
        @apply text-white bg-gradient-to-br from-violet-500 to-indigo-600 border-transparent shadow-md shadow-violet-500/30;
    }

    /* AI submit buttons (per-tool gradient) */
    .ai-submit-btn {
        @apply relative inline-flex items-center gap-2 px-5 py-2.5 text-sm font-bold text-white rounded-xl transition-all duration-200
               disabled:opacity-50 disabled:cursor-not-allowed disabled:translate-y-0 hover:-translate-y-0.5;
    }
    .ai-submit-violet  { @apply bg-gradient-to-br from-violet-500 to-indigo-600 shadow-lg shadow-violet-500/40 hover:shadow-xl hover:shadow-violet-500/50; }
    .ai-submit-blue    { @apply bg-gradient-to-br from-blue-500 to-cyan-600 shadow-lg shadow-blue-500/40 hover:shadow-xl hover:shadow-blue-500/50; }
    .ai-submit-emerald { @apply bg-gradient-to-br from-emerald-500 to-teal-600 shadow-lg shadow-emerald-500/40 hover:shadow-xl hover:shadow-emerald-500/50; }
    .ai-submit-amber   { @apply bg-gradient-to-br from-amber-500 to-orange-600 shadow-lg shadow-amber-500/40 hover:shadow-xl hover:shadow-amber-500/50; }
    .ai-submit-rose    { @apply bg-gradient-to-br from-rose-500 to-pink-600 shadow-lg shadow-rose-500/40 hover:shadow-xl hover:shadow-rose-500/50; }
    .ai-submit-fuchsia { @apply bg-gradient-to-br from-fuchsia-500 to-purple-600 shadow-lg shadow-fuchsia-500/40 hover:shadow-xl hover:shadow-fuchsia-500/50; }

    /* Animated loading dots */
    @keyframes aiPulse {
        0%, 80%, 100% { opacity: 0.3; transform: scale(0.7); }
        40%           { opacity: 1;   transform: scale(1); }
    }
    .ai-dot { animation: aiPulse 1.4s ease-in-out infinite both; }
    .ai-dot:nth-child(2) { animation-delay: 0.16s; }
    .ai-dot:nth-child(3) { animation-delay: 0.32s; }

    /* Premium range slider for word count */
    .ai-range {
        @apply w-full h-2 rounded-full appearance-none cursor-pointer outline-none;
        background: linear-gradient(90deg, #8b5cf6 0%, #6366f1 var(--range-fill, 50%), rgba(148, 163, 184, 0.25) var(--range-fill, 50%), rgba(148, 163, 184, 0.25) 100%);
    }
    .ai-range::-webkit-slider-thumb {
        @apply appearance-none w-5 h-5 rounded-full bg-white border-2 border-violet-500 shadow-lg shadow-violet-500/40 cursor-pointer transition-transform;
    }
    .ai-range::-webkit-slider-thumb:hover { @apply scale-110; }
    .ai-range::-moz-range-thumb {
        @apply w-5 h-5 rounded-full bg-white border-2 border-violet-500 shadow-lg cursor-pointer;
    }

    /* Soft animated background blob */
    @keyframes aiBlob {
        0%, 100% { transform: translate(0, 0) scale(1); }
        50%      { transform: translate(20px, -10px) scale(1.05); }
    }
    .ai-blob { animation: aiBlob 12s ease-in-out infinite; }
</style>
@endpush

<div x-data="blogPostForm()" x-init="init()">
    <form method="POST"
          action="{{ $isEdit ? route('admin.blog.posts.update', $post) : route('admin.blog.posts.store') }}"
          enctype="multipart/form-data"
          id="postForm"
          @submit="handleSubmit($event)">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            {{-- ═══════════════════════════════════
                 Main Content Area (8/12)
                 ═══════════════════════════════════ --}}
            <div class="lg:col-span-8 space-y-6">

                {{-- Title --}}
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-gray-200 mb-2">
                                หัวข้อบทความ <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="title" x-model="form.title"
                                   @input="generateSlug()"
                                   value="{{ old('title', $post->title ?? '') }}"
                                   placeholder="ใส่หัวข้อบทความ..."
                                   class="w-full text-xl font-semibold px-4 py-3 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white placeholder-gray-400"
                                   required>
                            @error('title')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Slug</label>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-400 dark:text-gray-500 flex-shrink-0">{{ url('/blog') }}/</span>
                                <input type="text" name="slug" x-model="form.slug"
                                       value="{{ old('slug', $post->slug ?? '') }}"
                                       placeholder="url-slug"
                                       class="flex-1 text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 focus:ring-2 focus:ring-indigo-500 dark:text-white font-mono">
                                <button type="button" @click="generateSlug(true)"
                                        class="px-3 py-2 text-xs bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors"
                                        title="สร้าง slug จากหัวข้อ">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </div>
                            @error('slug')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Content Editor --}}
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
                    {{-- Editor Tabs --}}
                    <div class="flex items-center justify-between border-b border-gray-100 dark:border-white/[0.06] px-4 py-2">
                        <div class="flex items-center gap-1">
                            <button type="button" @click="editorTab = 'write'"
                                    :class="editorTab === 'write' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-700'"
                                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
                                <i class="bi bi-pencil mr-1"></i>เขียน
                            </button>
                            <button type="button" @click="editorTab = 'preview'"
                                    :class="editorTab === 'preview' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-700'"
                                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
                                <i class="bi bi-eye mr-1"></i>พรีวิว
                            </button>
                        </div>
                    </div>

                    {{-- Toolbar --}}
                    <div class="editor-toolbar flex items-center gap-1 px-4 py-2 border-b border-gray-100 dark:border-white/[0.06] bg-gray-50/50 dark:bg-slate-700/30 flex-wrap"
                         x-show="editorTab === 'write'">
                        <button type="button" @click="insertFormat('bold')" title="ตัวหนา"><i class="bi bi-type-bold"></i></button>
                        <button type="button" @click="insertFormat('italic')" title="ตัวเอียง"><i class="bi bi-type-italic"></i></button>
                        <div class="w-px h-5 bg-gray-200 dark:bg-gray-600 mx-1"></div>
                        <button type="button" @click="insertFormat('h2')" title="หัวข้อ H2"><b class="text-xs">H2</b></button>
                        <button type="button" @click="insertFormat('h3')" title="หัวข้อ H3"><b class="text-xs">H3</b></button>
                        <div class="w-px h-5 bg-gray-200 dark:bg-gray-600 mx-1"></div>
                        <button type="button" @click="insertFormat('ul')" title="รายการ"><i class="bi bi-list-ul"></i></button>
                        <button type="button" @click="insertFormat('link')" title="ลิงก์"><i class="bi bi-link-45deg"></i></button>
                        <button type="button" @click="insertFormat('image')" title="รูปภาพ"><i class="bi bi-image"></i></button>
                        <div class="w-px h-5 bg-gray-200 dark:bg-gray-600 mx-1"></div>
                        <button type="button" @click="insertFormat('affiliate_cta')" title="Affiliate CTA Block"
                                class="!w-auto px-2 gap-1">
                            <i class="bi bi-megaphone"></i>
                            <span class="text-xs">CTA</span>
                        </button>
                    </div>

                    {{-- Write Tab --}}
                    <div x-show="editorTab === 'write'">
                        <textarea name="content" id="contentEditor" x-model="form.content"
                                  placeholder="เริ่มเขียนเนื้อหาบทความ..."
                                  class="w-full px-6 py-4 text-sm border-0 focus:ring-0 bg-transparent dark:text-white resize-none font-mono leading-relaxed"
                                  style="min-height: 500px;"
                                  >{{ old('content', $post->content ?? '') }}</textarea>
                    </div>

                    {{-- Preview Tab --}}
                    <div x-show="editorTab === 'preview'" x-cloak
                         class="px-6 py-4 prose dark:prose-invert max-w-none min-h-[500px]"
                         x-html="renderPreview()">
                    </div>
                </div>

                {{-- Excerpt --}}
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-6">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-semibold text-slate-700 dark:text-gray-200">สรุปย่อ (Excerpt)</label>
                        <span class="char-counter" :class="{ 'warning': form.excerpt.length > 250, 'danger': form.excerpt.length > 300 }"
                              x-text="form.excerpt.length + '/300'"></span>
                    </div>
                    <textarea name="excerpt" x-model="form.excerpt" rows="3" maxlength="300"
                              placeholder="สรุปย่อบทความสำหรับแสดงในรายการ..."
                              class="w-full px-4 py-3 text-sm border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 focus:ring-2 focus:ring-indigo-500 dark:text-white resize-none"
                              >{{ old('excerpt', $post->excerpt ?? '') }}</textarea>
                </div>

                {{-- ═══════════════════════════════════════════════════
                     AI Tools Panel — Premium Redesign
                     ═══════════════════════════════════════════════════ --}}
                <div class="relative bg-gradient-to-br from-violet-50/80 via-indigo-50/40 to-purple-50/60 dark:from-violet-500/[0.08] dark:via-indigo-500/[0.06] dark:to-purple-500/[0.08] rounded-2xl border border-violet-200/60 dark:border-violet-400/15 overflow-hidden shadow-lg shadow-violet-500/[0.08]"
                     x-data="{ aiOpen: false }">

                    {{-- Decorative gradient blobs --}}
                    <div class="ai-blob absolute -top-24 -right-24 w-64 h-64 bg-gradient-to-br from-violet-400/30 to-indigo-500/15 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
                    <div class="ai-blob absolute -bottom-32 -left-20 w-72 h-72 bg-gradient-to-br from-fuchsia-400/15 to-purple-500/10 rounded-full blur-3xl pointer-events-none" style="animation-delay:-6s;" aria-hidden="true"></div>

                    {{-- ─── Header (collapse trigger) ─── --}}
                    <button type="button" @click="aiOpen = !aiOpen"
                            class="relative w-full flex items-center justify-between gap-4 px-5 sm:px-6 py-5 text-left group">
                        <div class="flex items-center gap-4 min-w-0">
                            {{-- Animated icon --}}
                            <div class="relative shrink-0">
                                <div class="absolute inset-0 bg-gradient-to-br from-violet-500 to-indigo-600 rounded-2xl blur-md opacity-50 group-hover:opacity-80 transition-opacity"></div>
                                <div class="relative w-12 h-12 rounded-2xl bg-gradient-to-br from-violet-500 via-indigo-600 to-purple-600 flex items-center justify-center shadow-xl shadow-violet-500/40">
                                    <i class="bi bi-robot text-white text-xl"></i>
                                </div>
                                {{-- Pulsing status dot --}}
                                <span class="absolute -top-0.5 -right-0.5 flex h-3 w-3">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500 ring-2 ring-white dark:ring-slate-900"></span>
                                </span>
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                                    <h3 class="text-base font-bold text-slate-800 dark:text-white">เครื่องมือ AI</h3>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold bg-gradient-to-r from-violet-500 to-indigo-600 text-white rounded-full uppercase tracking-wider shadow-md shadow-violet-500/30">
                                        <i class="bi bi-stars text-[8px]"></i>Pro
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    สร้างบทความ • เขียนใหม่ • SEO • Meta Tags อัตโนมัติ
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-white/70 dark:bg-slate-800/60 text-violet-700 dark:text-violet-300 border border-violet-200/60 dark:border-violet-400/20"
                                  x-text="aiOpen ? 'ปิด' : 'เปิดใช้งาน'"></span>
                            <div class="w-9 h-9 rounded-xl bg-white/70 dark:bg-slate-800/60 flex items-center justify-center border border-violet-200/60 dark:border-violet-400/15 group-hover:bg-violet-100 dark:group-hover:bg-violet-500/20 transition-colors">
                                <i class="bi text-violet-600 dark:text-violet-300 transition-transform duration-300"
                                   :class="aiOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                            </div>
                        </div>
                    </button>

                    {{-- ─── Collapsible Body ─── --}}
                    <div x-show="aiOpen" x-collapse x-cloak>
                        <div class="relative border-t border-violet-200/40 dark:border-violet-400/10">

                            {{-- Tool Tab Bar --}}
                            <div class="overflow-x-auto px-4 sm:px-6 py-3 bg-white/60 dark:bg-slate-900/40 backdrop-blur-sm border-b border-violet-200/40 dark:border-violet-400/10">
                                <div class="flex items-center gap-2 min-w-max">
                                    <button type="button" @click="aiTool = 'generate'"
                                            class="ai-tab" :class="aiTool === 'generate' ? 'ai-tab-active-violet' : 'ai-tab-inactive'">
                                        <i class="bi bi-magic"></i><span>สร้างบทความ</span>
                                    </button>
                                    <button type="button" @click="aiTool = 'rewrite'"
                                            class="ai-tab" :class="aiTool === 'rewrite' ? 'ai-tab-active-blue' : 'ai-tab-inactive'">
                                        <i class="bi bi-pencil-square"></i><span>เขียนใหม่</span>
                                    </button>
                                    <button type="button" @click="aiTool = 'summarize'"
                                            class="ai-tab" :class="aiTool === 'summarize' ? 'ai-tab-active-emerald' : 'ai-tab-inactive'">
                                        <i class="bi bi-card-text"></i><span>สรุปเนื้อหา</span>
                                    </button>
                                    <button type="button" @click="aiTool = 'keywords'"
                                            class="ai-tab" :class="aiTool === 'keywords' ? 'ai-tab-active-amber' : 'ai-tab-inactive'">
                                        <i class="bi bi-tags"></i><span>คีย์เวิร์ด</span>
                                    </button>
                                    <button type="button" @click="aiTool = 'seo'"
                                            class="ai-tab" :class="aiTool === 'seo' ? 'ai-tab-active-rose' : 'ai-tab-inactive'">
                                        <i class="bi bi-graph-up-arrow"></i><span>วิเคราะห์ SEO</span>
                                    </button>
                                    <button type="button" @click="aiTool = 'meta'"
                                            class="ai-tab" :class="aiTool === 'meta' ? 'ai-tab-active-fuchsia' : 'ai-tab-inactive'">
                                        <i class="bi bi-code-slash"></i><span>Meta Tags</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Tool Panels --}}
                            <div class="relative p-5 sm:p-6">

                                {{-- ╔═ Generate Article ═╗ --}}
                                <div x-show="aiTool === 'generate'"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0">

                                    <div class="flex items-center gap-2 mb-5">
                                        <span class="w-1 h-6 rounded-full bg-gradient-to-b from-violet-500 to-indigo-600"></span>
                                        <h4 class="text-sm font-bold text-slate-800 dark:text-white">สร้างบทความ AI</h4>
                                        <span class="text-xs text-gray-400 hidden sm:inline">— กรอกข้อมูลแล้วให้ AI ร่างให้</span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                                        {{-- Keyword --}}
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-1.5 uppercase tracking-wider">
                                                <i class="bi bi-key-fill text-violet-500 mr-1"></i>คีย์เวิร์ดหลัก
                                            </label>
                                            <input type="text" x-model="ai.keyword" placeholder="เช่น กล้อง mirrorless 2025"
                                                   class="w-full text-sm px-4 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-white dark:bg-slate-800/60 focus:ring-2 focus:ring-violet-500 focus:border-violet-500 dark:text-white transition-all">
                                        </div>

                                        {{-- Word count slider --}}
                                        <div class="sm:col-span-2">
                                            <div class="flex items-center justify-between mb-2">
                                                <label class="text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                                    <i class="bi bi-text-paragraph text-violet-500 mr-1"></i>จำนวนคำ
                                                </label>
                                                <span class="px-3 py-1 text-xs font-bold bg-gradient-to-r from-violet-500 to-indigo-600 text-white rounded-full shadow-md shadow-violet-500/25"
                                                      x-text="ai.wordCount + ' คำ'"></span>
                                            </div>
                                            <input type="range" x-model="ai.wordCount" min="300" max="5000" step="100"
                                                   class="ai-range"
                                                   :style="`--range-fill: ${(ai.wordCount - 300) / 47}%`">
                                            <div class="flex justify-between text-[10px] text-gray-400 mt-1 font-medium">
                                                <span>300</span><span>1,500</span><span>3,000</span><span>5,000</span>
                                            </div>
                                        </div>

                                        {{-- Tone chips --}}
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-2 uppercase tracking-wider">
                                                <i class="bi bi-mic-fill text-violet-500 mr-1"></i>โทนเสียง
                                            </label>
                                            <div class="flex flex-wrap gap-1.5">
                                                <template x-for="t in [
                                                    {v:'professional', l:'มืออาชีพ', i:'briefcase'},
                                                    {v:'casual',       l:'เป็นกันเอง', i:'chat-heart'},
                                                    {v:'academic',     l:'วิชาการ', i:'mortarboard'},
                                                    {v:'creative',     l:'สร้างสรรค์', i:'palette'},
                                                    {v:'seo',          l:'เน้น SEO', i:'graph-up'}
                                                ]" :key="t.v">
                                                    <button type="button" @click="ai.tone = t.v"
                                                            class="ai-chip" :class="ai.tone === t.v ? 'ai-chip-active' : 'ai-chip-inactive'">
                                                        <i :class="`bi bi-${t.i}`"></i><span x-text="t.l"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>

                                        {{-- Language chips --}}
                                        <div>
                                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-2 uppercase tracking-wider">
                                                <i class="bi bi-translate text-violet-500 mr-1"></i>ภาษา
                                            </label>
                                            <div class="flex gap-1.5">
                                                <template x-for="lang in [
                                                    {v:'th',    l:'ไทย',    f:'🇹🇭'},
                                                    {v:'en',    l:'อังกฤษ', f:'🇬🇧'},
                                                    {v:'th-en', l:'ไทย-อังกฤษ', f:'🌐'}
                                                ]" :key="lang.v">
                                                    <button type="button" @click="ai.language = lang.v"
                                                            class="ai-chip flex-1 justify-center" :class="ai.language === lang.v ? 'ai-chip-active' : 'ai-chip-inactive'">
                                                        <span x-text="lang.f"></span><span x-text="lang.l"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>

                                        {{-- AI Provider visual cards --}}
                                        <div>
                                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-2 uppercase tracking-wider">
                                                <i class="bi bi-cpu-fill text-violet-500 mr-1"></i>AI Provider
                                            </label>
                                            <div class="grid grid-cols-3 gap-1.5">
                                                {{-- OpenAI --}}
                                                <button type="button" @click="ai.provider = 'openai'"
                                                        class="ai-provider-card" :class="ai.provider === 'openai' ? 'ai-provider-active' : 'ai-provider'">
                                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white shrink-0 shadow-sm">
                                                        <i class="bi bi-stars text-sm"></i>
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-[11px] font-bold text-slate-800 dark:text-white truncate">OpenAI</p>
                                                        <p class="text-[9px] text-gray-400 truncate">GPT-4</p>
                                                    </div>
                                                    <i x-show="ai.provider === 'openai'" class="bi bi-check-circle-fill text-violet-500 text-sm"></i>
                                                </button>
                                                {{-- Claude --}}
                                                <button type="button" @click="ai.provider = 'claude'"
                                                        class="ai-provider-card" :class="ai.provider === 'claude' ? 'ai-provider-active' : 'ai-provider'">
                                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white shrink-0 shadow-sm">
                                                        <i class="bi bi-gem text-sm"></i>
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-[11px] font-bold text-slate-800 dark:text-white truncate">Claude</p>
                                                        <p class="text-[9px] text-gray-400 truncate">Anthropic</p>
                                                    </div>
                                                    <i x-show="ai.provider === 'claude'" class="bi bi-check-circle-fill text-violet-500 text-sm"></i>
                                                </button>
                                                {{-- Gemini --}}
                                                <button type="button" @click="ai.provider = 'gemini'"
                                                        class="ai-provider-card" :class="ai.provider === 'gemini' ? 'ai-provider-active' : 'ai-provider'">
                                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-sky-400 to-sky-600 flex items-center justify-center text-white shrink-0 shadow-sm">
                                                        <i class="bi bi-star-fill text-sm"></i>
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-[11px] font-bold text-slate-800 dark:text-white truncate">Gemini</p>
                                                        <p class="text-[9px] text-gray-400 truncate">Google</p>
                                                    </div>
                                                    <i x-show="ai.provider === 'gemini'" class="bi bi-check-circle-fill text-violet-500 text-sm"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button" @click="runAiTool('generate')" :disabled="ai.loading"
                                            class="ai-submit-btn ai-submit-violet">
                                        <template x-if="!ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <i class="bi bi-magic text-base"></i>สร้างบทความ
                                                <i class="bi bi-arrow-right ml-1"></i>
                                            </span>
                                        </template>
                                        <template x-if="ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="inline-flex items-center gap-1">
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                </span>
                                                AI กำลังคิด...
                                            </span>
                                        </template>
                                    </button>
                                </div>

                                {{-- ╔═ Rewrite ═╗ --}}
                                <div x-show="aiTool === 'rewrite'" x-cloak
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0">

                                    <div class="flex items-center gap-2 mb-5">
                                        <span class="w-1 h-6 rounded-full bg-gradient-to-b from-blue-500 to-cyan-600"></span>
                                        <h4 class="text-sm font-bold text-slate-800 dark:text-white">เขียนเนื้อหาใหม่</h4>
                                        <span class="text-xs text-gray-400 hidden sm:inline">— ปรับสไตล์ให้เหมาะกับกลุ่มเป้าหมาย</span>
                                    </div>

                                    <div class="mb-4">
                                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-1.5 uppercase tracking-wider">
                                            <i class="bi bi-textarea-t text-blue-500 mr-1"></i>เนื้อหาที่ต้องการเขียนใหม่
                                        </label>
                                        <textarea x-model="ai.rewriteText" rows="5"
                                                  placeholder="วางเนื้อหาที่นี่ หรือเลือกจากบทความ..."
                                                  class="w-full text-sm px-4 py-3 border border-gray-200 dark:border-white/10 rounded-xl bg-white dark:bg-slate-800/60 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:text-white resize-none transition-all"></textarea>
                                    </div>

                                    <div class="mb-5">
                                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-2 uppercase tracking-wider">
                                            <i class="bi bi-palette2 text-blue-500 mr-1"></i>สไตล์การเขียนใหม่
                                        </label>
                                        <div class="flex flex-wrap gap-1.5">
                                            <template x-for="s in [
                                                {l:'ปรับปรุง', i:'arrow-up-right'},
                                                {l:'ง่ายขึ้น', i:'emoji-smile'},
                                                {l:'เป็นทางการ', i:'briefcase'},
                                                {l:'สร้างสรรค์', i:'lightbulb'},
                                                {l:'สั้นลง', i:'arrows-collapse'},
                                                {l:'ยาวขึ้น', i:'arrows-expand'}
                                            ]" :key="s.l">
                                                <button type="button" @click="ai.rewriteStyle = s.l"
                                                        class="ai-chip" :class="ai.rewriteStyle === s.l ? 'ai-chip-active' : 'ai-chip-inactive'">
                                                    <i :class="`bi bi-${s.i}`"></i><span x-text="s.l"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>

                                    <button type="button" @click="runAiTool('rewrite')" :disabled="ai.loading"
                                            class="ai-submit-btn ai-submit-blue">
                                        <template x-if="!ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <i class="bi bi-pencil-square"></i>เขียนใหม่<i class="bi bi-arrow-right ml-1"></i>
                                            </span>
                                        </template>
                                        <template x-if="ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="inline-flex items-center gap-1">
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                </span>
                                                กำลังเขียนใหม่...
                                            </span>
                                        </template>
                                    </button>
                                </div>

                                {{-- ╔═ Summarize ═╗ --}}
                                <div x-show="aiTool === 'summarize'" x-cloak
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0">

                                    <div class="flex items-center gap-2 mb-5">
                                        <span class="w-1 h-6 rounded-full bg-gradient-to-b from-emerald-500 to-teal-600"></span>
                                        <h4 class="text-sm font-bold text-slate-800 dark:text-white">สรุปเนื้อหา</h4>
                                        <span class="text-xs text-gray-400 hidden sm:inline">— สรุปบทความยาวให้กระชับ</span>
                                    </div>

                                    <div class="mb-5">
                                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-1.5 uppercase tracking-wider">
                                            <i class="bi bi-link-45deg text-emerald-500 mr-1"></i>เนื้อหาหรือ URL
                                        </label>
                                        <textarea x-model="ai.summarizeText" rows="5"
                                                  placeholder="วางเนื้อหาหรือ URL ที่ต้องการสรุป..."
                                                  class="w-full text-sm px-4 py-3 border border-gray-200 dark:border-white/10 rounded-xl bg-white dark:bg-slate-800/60 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 dark:text-white resize-none transition-all"></textarea>
                                    </div>

                                    <button type="button" @click="runAiTool('summarize')" :disabled="ai.loading"
                                            class="ai-submit-btn ai-submit-emerald">
                                        <template x-if="!ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <i class="bi bi-card-text"></i>สรุปเนื้อหา<i class="bi bi-arrow-right ml-1"></i>
                                            </span>
                                        </template>
                                        <template x-if="ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="inline-flex items-center gap-1">
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                </span>
                                                กำลังสรุป...
                                            </span>
                                        </template>
                                    </button>
                                </div>

                                {{-- ╔═ Keywords ═╗ --}}
                                <div x-show="aiTool === 'keywords'" x-cloak
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0">

                                    <div class="flex items-center gap-2 mb-5">
                                        <span class="w-1 h-6 rounded-full bg-gradient-to-b from-amber-500 to-orange-600"></span>
                                        <h4 class="text-sm font-bold text-slate-800 dark:text-white">แนะนำคีย์เวิร์ด SEO</h4>
                                        <span class="text-xs text-gray-400 hidden sm:inline">— หาคีย์เวิร์ดทอง + long-tail</span>
                                    </div>

                                    <div class="mb-5">
                                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 mb-1.5 uppercase tracking-wider">
                                            <i class="bi bi-bullseye text-amber-500 mr-1"></i>หัวข้อหรือเนื้อหา
                                        </label>
                                        <input type="text" x-model="ai.keywordTopic"
                                               placeholder="ใส่หัวข้อที่ต้องการค้นหาคีย์เวิร์ด..."
                                               class="w-full text-sm px-4 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-white dark:bg-slate-800/60 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 dark:text-white transition-all">
                                    </div>

                                    <button type="button" @click="runAiTool('keywords')" :disabled="ai.loading"
                                            class="ai-submit-btn ai-submit-amber">
                                        <template x-if="!ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <i class="bi bi-tags-fill"></i>แนะนำคีย์เวิร์ด<i class="bi bi-arrow-right ml-1"></i>
                                            </span>
                                        </template>
                                        <template x-if="ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="inline-flex items-center gap-1">
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                </span>
                                                กำลังค้นหา...
                                            </span>
                                        </template>
                                    </button>
                                </div>

                                {{-- ╔═ SEO Analysis ═╗ --}}
                                <div x-show="aiTool === 'seo'" x-cloak
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0">

                                    <div class="flex items-center gap-2 mb-5">
                                        <span class="w-1 h-6 rounded-full bg-gradient-to-b from-rose-500 to-pink-600"></span>
                                        <h4 class="text-sm font-bold text-slate-800 dark:text-white">วิเคราะห์ SEO</h4>
                                        <span class="text-xs text-gray-400 hidden sm:inline">— ตรวจคุณภาพ SEO + แนะนำแก้ไข</span>
                                    </div>

                                    <div class="mb-5 p-4 rounded-xl bg-rose-50/50 dark:bg-rose-500/5 border border-rose-200/50 dark:border-rose-400/15">
                                        <div class="flex gap-3">
                                            <i class="bi bi-info-circle-fill text-rose-500 dark:text-rose-300 text-lg shrink-0 mt-0.5"></i>
                                            <div class="text-xs text-rose-900 dark:text-rose-200">
                                                <p class="font-semibold mb-1">AI จะวิเคราะห์เนื้อหาบทความปัจจุบันและให้คะแนน SEO พร้อมข้อเสนอแนะ:</p>
                                                <ul class="list-disc list-inside space-y-0.5 text-rose-700/80 dark:text-rose-200/80">
                                                    <li>ความหนาแน่นคีย์เวิร์ด</li>
                                                    <li>โครงสร้าง heading (H1-H6)</li>
                                                    <li>คุณภาพ meta tags</li>
                                                    <li>การใช้ internal/external links</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button" @click="runAiTool('seo')" :disabled="ai.loading"
                                            class="ai-submit-btn ai-submit-rose">
                                        <template x-if="!ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <i class="bi bi-graph-up-arrow"></i>วิเคราะห์ SEO<i class="bi bi-arrow-right ml-1"></i>
                                            </span>
                                        </template>
                                        <template x-if="ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="inline-flex items-center gap-1">
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                </span>
                                                กำลังวิเคราะห์...
                                            </span>
                                        </template>
                                    </button>
                                </div>

                                {{-- ╔═ Meta Tags ═╗ --}}
                                <div x-show="aiTool === 'meta'" x-cloak
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 translate-y-2"
                                     x-transition:enter-end="opacity-100 translate-y-0">

                                    <div class="flex items-center gap-2 mb-5">
                                        <span class="w-1 h-6 rounded-full bg-gradient-to-b from-fuchsia-500 to-purple-600"></span>
                                        <h4 class="text-sm font-bold text-slate-800 dark:text-white">สร้าง Meta Tags</h4>
                                        <span class="text-xs text-gray-400 hidden sm:inline">— Title, Description, OG Tags</span>
                                    </div>

                                    <div class="mb-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div class="p-3 rounded-xl bg-fuchsia-50/50 dark:bg-fuchsia-500/5 border border-fuchsia-200/50 dark:border-fuchsia-400/15 flex items-start gap-2">
                                            <i class="bi bi-card-heading text-fuchsia-500 dark:text-fuchsia-300 mt-0.5"></i>
                                            <div>
                                                <p class="text-xs font-bold text-fuchsia-900 dark:text-fuchsia-200">Meta Title</p>
                                                <p class="text-[11px] text-fuchsia-700/80 dark:text-fuchsia-200/70">50-60 ตัวอักษร</p>
                                            </div>
                                        </div>
                                        <div class="p-3 rounded-xl bg-purple-50/50 dark:bg-purple-500/5 border border-purple-200/50 dark:border-purple-400/15 flex items-start gap-2">
                                            <i class="bi bi-text-paragraph text-purple-500 dark:text-purple-300 mt-0.5"></i>
                                            <div>
                                                <p class="text-xs font-bold text-purple-900 dark:text-purple-200">Meta Description</p>
                                                <p class="text-[11px] text-purple-700/80 dark:text-purple-200/70">140-160 ตัวอักษร</p>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button" @click="runAiTool('meta')" :disabled="ai.loading"
                                            class="ai-submit-btn ai-submit-fuchsia">
                                        <template x-if="!ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <i class="bi bi-code-slash"></i>สร้าง Meta Tags<i class="bi bi-arrow-right ml-1"></i>
                                            </span>
                                        </template>
                                        <template x-if="ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="inline-flex items-center gap-1">
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                </span>
                                                กำลังสร้าง...
                                            </span>
                                        </template>
                                    </button>
                                </div>

                                {{-- ─── AI Result Display ─── --}}
                                <div x-show="ai.result" x-cloak
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     class="mt-6">
                                    <div class="relative bg-gradient-to-br from-white via-violet-50/30 to-indigo-50/40 dark:from-slate-800/80 dark:via-violet-500/[0.04] dark:to-indigo-500/[0.06] rounded-2xl border border-violet-200/60 dark:border-violet-400/20 overflow-hidden shadow-md shadow-violet-500/[0.08]">

                                        {{-- Result header bar --}}
                                        <div class="flex items-center justify-between px-5 py-3 bg-gradient-to-r from-violet-500/[0.08] via-indigo-500/[0.06] to-transparent dark:from-violet-500/15 dark:via-indigo-500/10 border-b border-violet-200/40 dark:border-violet-400/15">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center shadow-md shadow-violet-500/30">
                                                    <i class="bi bi-check2-circle text-white text-sm"></i>
                                                </div>
                                                <div>
                                                    <h4 class="text-sm font-bold text-slate-800 dark:text-white">ผลลัพธ์ AI</h4>
                                                    <p class="text-[10px] text-gray-500 dark:text-gray-400" x-text="(ai.result?.length || 0) + ' ตัวอักษร'"></p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <button type="button" @click="copyAiResult()"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold bg-white dark:bg-slate-700 border border-gray-200 dark:border-white/10 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-600 hover:border-violet-300 dark:hover:border-violet-400/40 transition-all"
                                                        title="คัดลอกไปยัง clipboard">
                                                    <i class="bi bi-clipboard"></i><span class="hidden sm:inline">คัดลอก</span>
                                                </button>
                                                <button type="button" @click="insertAiResult()"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-white bg-gradient-to-br from-violet-500 to-indigo-600 rounded-lg shadow-md shadow-violet-500/30 hover:shadow-lg hover:shadow-violet-500/40 hover:-translate-y-0.5 transition-all">
                                                    <i class="bi bi-plus-circle-fill"></i><span>ใช้เนื้อหานี้</span>
                                                </button>
                                            </div>
                                        </div>

                                        {{-- Result body --}}
                                        <div class="p-5 max-h-96 overflow-y-auto text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap leading-relaxed"
                                             style="font-family: 'Noto Sans Thai', sans-serif;"
                                             x-text="ai.result"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════
                 Sidebar (4/12)
                 ═══════════════════════════════════ --}}
            <div class="lg:col-span-4 space-y-6">

                {{-- Publish Card --}}
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <i class="bi bi-send text-indigo-500"></i>เผยแพร่
                        </h3>
                    </div>
                    <div class="p-5 space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">สถานะ</label>
                            <select name="status" x-model="form.status"
                                    class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white">
                                <option value="draft" {{ old('status', $post->status ?? 'draft') == 'draft' ? 'selected' : '' }}>แบบร่าง</option>
                                <option value="published" {{ old('status', $post->status ?? '') == 'published' ? 'selected' : '' }}>เผยแพร่</option>
                                <option value="scheduled" {{ old('status', $post->status ?? '') == 'scheduled' ? 'selected' : '' }}>กำหนดเวลา</option>
                            </select>
                        </div>

                        {{-- Scheduled datetime --}}
                        <div x-show="form.status === 'scheduled'" x-cloak x-transition>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">วันเวลาเผยแพร่</label>
                            <input type="datetime-local" name="published_at" x-model="form.published_at"
                                   value="{{ old('published_at', isset($post) && $post->published_at ? $post->published_at->format('Y-m-d\TH:i') : '') }}"
                                   class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">การแสดงผล</label>
                            <select name="visibility" x-model="form.visibility"
                                    class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white">
                                <option value="public" {{ old('visibility', $post->visibility ?? 'public') == 'public' ? 'selected' : '' }}>สาธารณะ</option>
                                <option value="private" {{ old('visibility', $post->visibility ?? '') == 'private' ? 'selected' : '' }}>ส่วนตัว</option>
                                <option value="password" {{ old('visibility', $post->visibility ?? '') == 'password' ? 'selected' : '' }}>ใส่รหัสผ่าน</option>
                            </select>
                        </div>

                        {{-- Password field --}}
                        <div x-show="form.visibility === 'password'" x-cloak x-transition>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">รหัสผ่าน</label>
                            <input type="text" name="password" x-model="form.password"
                                   value="{{ old('password', $post->password ?? '') }}"
                                   class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white">
                        </div>

                        <div class="flex items-center gap-2 pt-2">
                            <button type="submit" name="action" value="draft"
                                    class="flex-1 px-4 py-2.5 text-sm font-medium bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
                                <i class="bi bi-file-earmark mr-1"></i>บันทึกแบบร่าง
                            </button>
                            <button type="submit" name="action" value="publish"
                                    class="flex-1 px-4 py-2.5 text-sm font-medium bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-500/25">
                                <i class="bi bi-send mr-1"></i>{{ $isEdit ? 'อัปเดต' : 'เผยแพร่' }}
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Category Card --}}
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                                <i class="bi bi-folder text-amber-500"></i>หมวดหมู่
                            </h3>
                            <a href="{{ route('admin.blog.categories.create') }}" target="_blank"
                               class="text-xs text-indigo-500 hover:text-indigo-700 font-medium">
                                <i class="bi bi-plus-lg mr-0.5"></i>สร้างใหม่
                            </a>
                        </div>
                    </div>
                    <div class="p-5">
                        <select name="blog_category_id"
                                class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white">
                            <option value="">-- เลือกหมวดหมู่ --</option>
                            @foreach($categories ?? [] as $category)
                                <option value="{{ $category->id }}"
                                        {{ old('blog_category_id', $post->blog_category_id ?? '') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Tags Card --}}
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <i class="bi bi-tags text-teal-500"></i>แท็ก
                        </h3>
                    </div>
                    <div class="p-5">
                        <div class="relative mb-3">
                            <input type="text" x-model="tagInput" @keydown.enter.prevent="addTag(tagInput)"
                                   @input="searchTags()"
                                   placeholder="พิมพ์ชื่อแท็กแล้วกด Enter..."
                                   class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white">
                            {{-- Tag suggestions --}}
                            <div x-show="tagSuggestions.length > 0" x-cloak
                                 class="absolute left-0 right-0 mt-1 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-100 dark:border-white/10 py-1 z-50 max-h-40 overflow-y-auto">
                                <template x-for="suggestion in tagSuggestions" :key="suggestion.id">
                                    <button type="button" @click="addTag(suggestion.name); tagSuggestions = []"
                                            class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700/50"
                                            x-text="suggestion.name"></button>
                                </template>
                            </div>
                        </div>
                        <input type="hidden" name="tags" :value="JSON.stringify(selectedTags)">
                        <div class="flex flex-wrap gap-2">
                            <template x-for="(tag, index) in selectedTags" :key="index">
                                <span class="tag-chip">
                                    <span x-text="tag"></span>
                                    <button type="button" @click="removeTag(index)" class="text-indigo-400 hover:text-red-500 ml-0.5">
                                        <i class="bi bi-x text-xs"></i>
                                    </button>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Featured Image Card --}}
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <i class="bi bi-image text-pink-500"></i>ภาพเด่น
                        </h3>
                    </div>
                    <div class="p-5">
                        <div class="drop-zone" :class="{ 'dragover': isDragging }"
                             @dragover.prevent="isDragging = true" @dragleave.prevent="isDragging = false"
                             @drop.prevent="handleDrop($event)" @click="$refs.featuredImage.click()">
                            <template x-if="!imagePreview">
                                <div>
                                    <i class="bi bi-cloud-arrow-up text-3xl text-gray-400 mb-2"></i>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">ลากไฟล์มาวาง หรือคลิกเพื่อเลือก</p>
                                    <p class="text-xs text-gray-400 mt-1">PNG, JPG, WebP (สูงสุด 2MB)</p>
                                </div>
                            </template>
                            <template x-if="imagePreview">
                                <div class="relative">
                                    <img :src="imagePreview" class="w-full h-48 object-cover rounded-lg">
                                    <button type="button" @click.stop="removeImage()"
                                            class="absolute top-2 right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </template>
                        </div>
                        <input type="file" name="featured_image" x-ref="featuredImage" @change="previewImage($event)"
                               accept="image/*" class="hidden">
                        @if($isEdit && $post->featured_image)
                            <input type="hidden" name="existing_featured_image" value="{{ $post->featured_image }}">
                        @endif
                    </div>
                </div>

                {{-- SEO Card --}}
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden"
                     x-data="{ seoOpen: {{ $isEdit ? 'true' : 'false' }} }">
                    <button type="button" @click="seoOpen = !seoOpen"
                            class="w-full px-5 py-4 flex items-center justify-between text-left border-b border-gray-100 dark:border-white/[0.06]">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <i class="bi bi-search text-emerald-500"></i>SEO
                        </h3>
                        <i class="bi text-gray-400" :class="seoOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    </button>
                    <div x-show="seoOpen" x-collapse x-cloak>
                        <div class="p-5 space-y-4">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Meta Title</label>
                                    <span class="char-counter"
                                          :class="{ 'warning': (form.meta_title || '').length > 50, 'danger': (form.meta_title || '').length > 60 }"
                                          x-text="(form.meta_title || '').length + '/60'"></span>
                                </div>
                                <input type="text" name="meta_title" x-model="form.meta_title"
                                       value="{{ old('meta_title', $post->meta_title ?? '') }}"
                                       maxlength="60" placeholder="หัวข้อสำหรับ SEO..."
                                       class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white">
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Meta Description</label>
                                    <span class="char-counter"
                                          :class="{ 'warning': (form.meta_description || '').length > 140, 'danger': (form.meta_description || '').length > 160 }"
                                          x-text="(form.meta_description || '').length + '/160'"></span>
                                </div>
                                <textarea name="meta_description" x-model="form.meta_description" rows="3" maxlength="160"
                                          placeholder="คำอธิบายสำหรับ SEO..."
                                          class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white resize-none"
                                          >{{ old('meta_description', $post->meta_description ?? '') }}</textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Focus Keyword</label>
                                <input type="text" name="focus_keyword" x-model="form.focus_keyword"
                                       value="{{ old('focus_keyword', $post->focus_keyword ?? '') }}"
                                       placeholder="คีย์เวิร์ดหลัก..."
                                       class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">OG Image</label>
                                <input type="file" name="og_image" accept="image/*"
                                       class="w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-indigo-50 file:text-indigo-600 dark:file:bg-indigo-500/20 dark:file:text-indigo-400 hover:file:bg-indigo-100">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Canonical URL</label>
                                <input type="url" name="canonical_url" x-model="form.canonical_url"
                                       value="{{ old('canonical_url', $post->canonical_url ?? '') }}"
                                       placeholder="https://..."
                                       class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white">
                            </div>

                            {{-- SEO Score display --}}
                            @if($isEdit && isset($post->seo_score))
                            <div class="bg-gray-50 dark:bg-slate-700/50 rounded-xl p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">คะแนน SEO</span>
                                    <span class="text-sm font-bold {{ ($post->seo_score ?? 0) > 70 ? 'text-emerald-600' : (($post->seo_score ?? 0) > 40 ? 'text-amber-600' : 'text-red-600') }}">
                                        {{ $post->seo_score ?? 0 }}%
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-slate-600 rounded-full h-2">
                                    <div class="h-2 rounded-full {{ ($post->seo_score ?? 0) > 70 ? 'bg-emerald-500' : (($post->seo_score ?? 0) > 40 ? 'bg-amber-500' : 'bg-red-500') }}"
                                         style="width: {{ $post->seo_score ?? 0 }}%"></div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Options Card --}}
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <i class="bi bi-gear text-gray-500"></i>ตัวเลือกเพิ่มเติม
                        </h3>
                    </div>
                    <div class="p-5 space-y-4">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_featured" value="1" x-model="form.is_featured"
                                   {{ old('is_featured', $post->is_featured ?? false) ? 'checked' : '' }}
                                   class="w-4 h-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                            <div>
                                <span class="text-sm font-medium text-slate-700 dark:text-gray-200">บทความแนะนำ</span>
                                <p class="text-xs text-gray-400">แสดงในส่วนบทความแนะนำบนหน้าแรก</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_affiliate_post" value="1" x-model="form.is_affiliate_post"
                                   {{ old('is_affiliate_post', $post->is_affiliate_post ?? false) ? 'checked' : '' }}
                                   class="w-4 h-4 rounded border-gray-300 text-purple-500 focus:ring-purple-500">
                            <div>
                                <span class="text-sm font-medium text-slate-700 dark:text-gray-200">บทความ Affiliate</span>
                                <p class="text-xs text-gray-400">มีลิงก์ Affiliate ในบทความ</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="allow_comments" value="1" x-model="form.allow_comments"
                                   {{ old('allow_comments', $post->allow_comments ?? true) ? 'checked' : '' }}
                                   class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <div>
                                <span class="text-sm font-medium text-slate-700 dark:text-gray-200">อนุญาตความคิดเห็น</span>
                                <p class="text-xs text-gray-400">เปิดให้ผู้อ่านแสดงความคิดเห็นได้</p>
                            </div>
                        </label>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">ประเภท Schema</label>
                            <select name="schema_type"
                                    class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white">
                                <option value="Article" {{ old('schema_type', $post->schema_type ?? 'Article') == 'Article' ? 'selected' : '' }}>Article</option>
                                <option value="BlogPosting" {{ old('schema_type', $post->schema_type ?? '') == 'BlogPosting' ? 'selected' : '' }}>BlogPosting</option>
                                <option value="NewsArticle" {{ old('schema_type', $post->schema_type ?? '') == 'NewsArticle' ? 'selected' : '' }}>NewsArticle</option>
                            </select>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function blogPostForm() {
    return {
        editorTab: 'write',
        aiTool: 'generate',
        tagInput: '',
        tagSuggestions: [],
        selectedTags: @json(old('tags') ? json_decode(old('tags')) : (isset($post) && $post->tags ? $post->tags->pluck('name')->toArray() : [])),
        imagePreview: @json(isset($post) && $post->featured_image ? asset('storage/' . $post->featured_image) : null),
        isDragging: false,

        form: {
            title: @json(old('title', $post->title ?? '')),
            slug: @json(old('slug', $post->slug ?? '')),
            content: @json(old('content', $post->content ?? '')),
            excerpt: @json(old('excerpt', $post->excerpt ?? '')),
            status: @json(old('status', $post->status ?? 'draft')),
            published_at: @json(old('published_at', isset($post) && $post->published_at ? $post->published_at->format('Y-m-d\TH:i') : '')),
            visibility: @json(old('visibility', $post->visibility ?? 'public')),
            password: @json(old('password', $post->password ?? '')),
            meta_title: @json(old('meta_title', $post->meta_title ?? '')),
            meta_description: @json(old('meta_description', $post->meta_description ?? '')),
            focus_keyword: @json(old('focus_keyword', $post->focus_keyword ?? '')),
            canonical_url: @json(old('canonical_url', $post->canonical_url ?? '')),
            is_featured: @json(old('is_featured', $post->is_featured ?? false) ? true : false),
            is_affiliate_post: @json(old('is_affiliate_post', $post->is_affiliate_post ?? false) ? true : false),
            allow_comments: @json(old('allow_comments', $post->allow_comments ?? true) ? true : false),
        },

        ai: {
            loading: false,
            result: null,
            keyword: '',
            wordCount: 1500,
            tone: 'professional',
            language: 'th',
            provider: 'openai',
            rewriteText: '',
            rewriteStyle: 'ปรับปรุง',
            summarizeText: '',
            keywordTopic: '',
        },

        init() {
            @if(isset($post) && $post->featured_image)
                this.imagePreview = "{{ asset('storage/' . $post->featured_image) }}";
            @endif
        },

        generateSlug(force = false) {
            if (!force && this.form.slug && this.form.slug !== '') return;
            let slug = this.form.title
                .toLowerCase()
                .replace(/[^\u0E00-\u0E7Fa-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');
            this.form.slug = slug;
        },

        insertFormat(type) {
            const textarea = document.getElementById('contentEditor');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selected = this.form.content.substring(start, end);
            let insert = '';

            switch(type) {
                case 'bold': insert = `**${selected || 'ข้อความตัวหนา'}**`; break;
                case 'italic': insert = `*${selected || 'ข้อความตัวเอียง'}*`; break;
                case 'h2': insert = `\n## ${selected || 'หัวข้อ H2'}\n`; break;
                case 'h3': insert = `\n### ${selected || 'หัวข้อ H3'}\n`; break;
                case 'ul': insert = `\n- ${selected || 'รายการ'}\n`; break;
                case 'link': insert = `[${selected || 'ข้อความลิงก์'}](url)`; break;
                case 'image': insert = `![${selected || 'คำอธิบายรูป'}](url)`; break;
                case 'affiliate_cta':
                    insert = `\n<div class="affiliate-cta">\n  <h3>ชื่อสินค้า</h3>\n  <p>คำอธิบายสั้นๆ</p>\n  <a href="/go/slug" class="cta-button">ดูราคาล่าสุด</a>\n</div>\n`;
                    break;
            }

            this.form.content = this.form.content.substring(0, start) + insert + this.form.content.substring(end);
            this.$nextTick(() => {
                textarea.focus();
                textarea.setSelectionRange(start + insert.length, start + insert.length);
            });
        },

        renderPreview() {
            let html = this.form.content || '<p class="text-gray-400">ยังไม่มีเนื้อหา</p>';
            html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
            html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>');
            html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>');
            html = html.replace(/^\- (.*$)/gim, '<li>$1</li>');
            html = html.replace(/\!\[(.*?)\]\((.*?)\)/g, '<img src="$2" alt="$1" class="rounded-lg max-w-full">');
            html = html.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" class="text-indigo-600 underline">$1</a>');
            html = html.replace(/\n/g, '<br>');
            return html;
        },

        searchTags() {
            if (this.tagInput.length < 2) { this.tagSuggestions = []; return; }
            fetch(`{{ route('admin.blog.tags.search') }}?q=${encodeURIComponent(this.tagInput)}`, {
                headers: { 'Accept': 'application/json' }
            }).then(r => r.json()).then(data => {
                this.tagSuggestions = data.filter(t => !this.selectedTags.includes(t.name));
            }).catch(() => { this.tagSuggestions = []; });
        },

        addTag(name) {
            name = name.trim();
            if (name && !this.selectedTags.includes(name)) {
                this.selectedTags.push(name);
            }
            this.tagInput = '';
            this.tagSuggestions = [];
        },

        removeTag(index) {
            this.selectedTags.splice(index, 1);
        },

        previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => { this.imagePreview = e.target.result; };
                reader.readAsDataURL(file);
            }
        },

        handleDrop(event) {
            this.isDragging = false;
            const file = event.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                this.$refs.featuredImage.files = dataTransfer.files;
                const reader = new FileReader();
                reader.onload = (e) => { this.imagePreview = e.target.result; };
                reader.readAsDataURL(file);
            }
        },

        removeImage() {
            this.imagePreview = null;
            this.$refs.featuredImage.value = '';
        },

        runAiTool(tool) {
            this.ai.loading = true;
            this.ai.result = null;

            let payload = { tool: tool };
            switch(tool) {
                case 'generate':
                    payload = { ...payload, keyword: this.ai.keyword, word_count: this.ai.wordCount, tone: this.ai.tone, language: this.ai.language, provider: this.ai.provider };
                    break;
                case 'rewrite':
                    payload = { ...payload, text: this.ai.rewriteText, style: this.ai.rewriteStyle, provider: this.ai.provider };
                    break;
                case 'summarize':
                    payload = { ...payload, text: this.ai.summarizeText, provider: this.ai.provider };
                    break;
                case 'keywords':
                    payload = { ...payload, topic: this.ai.keywordTopic, provider: this.ai.provider };
                    break;
                case 'seo':
                case 'meta':
                    payload = { ...payload, title: this.form.title, content: this.form.content, provider: this.ai.provider };
                    break;
            }

            fetch("{{ route('admin.blog.ai.process') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(data => {
                this.ai.result = data.result || data.error || 'ไม่มีผลลัพธ์';
                if (tool === 'meta' && data.meta_title) {
                    this.form.meta_title = data.meta_title;
                    this.form.meta_description = data.meta_description;
                }
            })
            .catch(err => { this.ai.result = 'เกิดข้อผิดพลาด: ' + err.message; })
            .finally(() => { this.ai.loading = false; });
        },

        copyAiResult() {
            navigator.clipboard.writeText(this.ai.result).then(() => {
                Swal.fire({ icon: 'success', title: 'คัดลอกแล้ว', timer: 1000, showConfirmButton: false });
            });
        },

        insertAiResult() {
            this.form.content += '\n\n' + this.ai.result;
            this.ai.result = null;
            this.editorTab = 'write';
            Swal.fire({ icon: 'success', title: 'เพิ่มเนื้อหาแล้ว', timer: 1000, showConfirmButton: false });
        },

        handleSubmit(event) {
            // Allow normal form submission
        }
    };
}
</script>
@endpush

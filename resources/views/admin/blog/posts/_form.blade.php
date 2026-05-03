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

    /* ═══ AI Tools Panel — Clean Minimal Redesign ═══ */
    .ai-tab {
        @apply inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors cursor-pointer whitespace-nowrap;
    }
    .ai-tab.active   { @apply bg-indigo-600 text-white; }
    .ai-tab:not(.active) { @apply text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700; }

    /* Provider cards (uniform — no rainbow) */
    .ai-provider-card {
        @apply relative flex items-center gap-2.5 px-3 py-2.5 rounded-xl border cursor-pointer transition-colors text-left;
    }
    .ai-provider     { @apply bg-gray-50 dark:bg-slate-700/30 border-gray-200 dark:border-white/[0.07] hover:border-indigo-300 dark:hover:border-indigo-400/40; }
    .ai-provider-active { @apply bg-indigo-50 dark:bg-indigo-500/10 border-indigo-500 dark:border-indigo-400; }

    /* Chip group (tone, language, rewrite-style) */
    .ai-chip {
        @apply inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg cursor-pointer transition-colors border;
    }
    .ai-chip-inactive { @apply text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-slate-700/30 border-gray-200 dark:border-white/[0.07] hover:border-indigo-300 dark:hover:border-indigo-400/40; }
    .ai-chip-active   { @apply text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-500/15 border-indigo-300 dark:border-indigo-400/40; }

    /* AI section field label */
    .ai-label { @apply block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5; }

    /* Loading dots */
    @keyframes aiPulse {
        0%, 80%, 100% { opacity: 0.3; transform: scale(0.7); }
        40%           { opacity: 1;   transform: scale(1); }
    }
    .ai-dot { animation: aiPulse 1.4s ease-in-out infinite both; }
    .ai-dot:nth-child(2) { animation-delay: 0.16s; }
    .ai-dot:nth-child(3) { animation-delay: 0.32s; }

    /* Range slider */
    .ai-range {
        @apply w-full h-2 rounded-full appearance-none cursor-pointer outline-none;
        background: linear-gradient(90deg, #6366f1 0%, #6366f1 var(--range-fill, 50%), rgba(148, 163, 184, 0.25) var(--range-fill, 50%), rgba(148, 163, 184, 0.25) 100%);
    }
    .ai-range::-webkit-slider-thumb {
        @apply appearance-none w-4 h-4 rounded-full bg-white border-2 border-indigo-600 cursor-pointer;
    }
    .ai-range::-moz-range-thumb {
        @apply w-4 h-4 rounded-full bg-white border-2 border-indigo-600 cursor-pointer;
    }
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

                {{-- ═══ AI Tools Panel ═══ --}}
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden"
                     x-data="{ aiOpen: false }">
                    {{-- Collapsible Header --}}
                    <button type="button" @click="aiOpen = !aiOpen"
                            class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center shadow-sm">
                                <i class="bi bi-robot text-white text-base"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                                    เครื่องมือ AI
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300 font-semibold">PRO</span>
                                </h3>
                                <p class="text-xs text-gray-400 mt-0.5">สร้างเนื้อหา, เขียนใหม่, SEO และอื่นๆ</p>
                            </div>
                        </div>
                        <i class="bi text-gray-400 transition-transform duration-200"
                           :class="aiOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    </button>

                    <div x-show="aiOpen" x-collapse x-cloak>
                        <div class="border-t border-gray-100 dark:border-white/[0.06]">

                            {{-- Tab Bar --}}
                            <div class="overflow-x-auto px-5 py-3 bg-gray-50/50 dark:bg-slate-700/20 border-b border-gray-100 dark:border-white/[0.06]">
                                <div class="flex items-center gap-1.5 min-w-max">
                                    <button type="button" @click="aiTool = 'generate'"
                                            class="ai-tab" :class="{ 'active': aiTool === 'generate' }">
                                        <i class="bi bi-magic"></i><span>สร้างบทความ</span>
                                    </button>
                                    <button type="button" @click="aiTool = 'rewrite'"
                                            class="ai-tab" :class="{ 'active': aiTool === 'rewrite' }">
                                        <i class="bi bi-pencil-square"></i><span>เขียนใหม่</span>
                                    </button>
                                    <button type="button" @click="aiTool = 'summarize'"
                                            class="ai-tab" :class="{ 'active': aiTool === 'summarize' }">
                                        <i class="bi bi-card-text"></i><span>สรุปเนื้อหา</span>
                                    </button>
                                    <button type="button" @click="aiTool = 'keywords'"
                                            class="ai-tab" :class="{ 'active': aiTool === 'keywords' }">
                                        <i class="bi bi-tags"></i><span>คีย์เวิร์ด</span>
                                    </button>
                                    <button type="button" @click="aiTool = 'seo'"
                                            class="ai-tab" :class="{ 'active': aiTool === 'seo' }">
                                        <i class="bi bi-graph-up"></i><span>SEO</span>
                                    </button>
                                    <button type="button" @click="aiTool = 'meta'"
                                            class="ai-tab" :class="{ 'active': aiTool === 'meta' }">
                                        <i class="bi bi-code-slash"></i><span>Meta Tags</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Panels --}}
                            <div class="p-5 space-y-4">

                                {{-- ─── Generate ─── --}}
                                <div x-show="aiTool === 'generate'" class="space-y-4">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="sm:col-span-2">
                                            <label class="ai-label">คีย์เวิร์ดหลัก</label>
                                            <input type="text" x-model="ai.keyword" placeholder="เช่น กล้อง mirrorless 2025"
                                                   class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 focus:ring-2 focus:ring-indigo-500 dark:text-white">
                                        </div>

                                        <div class="sm:col-span-2">
                                            <div class="flex items-center justify-between mb-1.5">
                                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">จำนวนคำ</label>
                                                <span class="text-xs font-mono font-semibold text-indigo-600 dark:text-indigo-300" x-text="ai.wordCount + ' คำ'"></span>
                                            </div>
                                            <input type="range" x-model="ai.wordCount" min="300" max="5000" step="100"
                                                   class="ai-range"
                                                   :style="`--range-fill: ${(ai.wordCount - 300) / 47}%`">
                                            <div class="flex justify-between text-[10px] text-gray-400 mt-1">
                                                <span>300</span><span>1,500</span><span>3,000</span><span>5,000</span>
                                            </div>
                                        </div>

                                        <div class="sm:col-span-2">
                                            <label class="ai-label">โทนเสียง</label>
                                            <div class="flex flex-wrap gap-1.5">
                                                <template x-for="t in [
                                                    {v:'professional', l:'มืออาชีพ'},
                                                    {v:'casual',       l:'เป็นกันเอง'},
                                                    {v:'academic',     l:'วิชาการ'},
                                                    {v:'creative',     l:'สร้างสรรค์'},
                                                    {v:'seo',          l:'เน้น SEO'}
                                                ]" :key="t.v">
                                                    <button type="button" @click="ai.tone = t.v"
                                                            class="ai-chip" :class="ai.tone === t.v ? 'ai-chip-active' : 'ai-chip-inactive'"
                                                            x-text="t.l"></button>
                                                </template>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="ai-label">ภาษา</label>
                                            <div class="flex gap-1.5">
                                                <template x-for="lang in [
                                                    {v:'th',    l:'ไทย'},
                                                    {v:'en',    l:'อังกฤษ'},
                                                    {v:'th-en', l:'ไทย-อังกฤษ'}
                                                ]" :key="lang.v">
                                                    <button type="button" @click="ai.language = lang.v"
                                                            class="ai-chip flex-1 justify-center"
                                                            :class="ai.language === lang.v ? 'ai-chip-active' : 'ai-chip-inactive'"
                                                            x-text="lang.l"></button>
                                                </template>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="ai-label">AI Provider</label>
                                            <div class="grid grid-cols-3 gap-1.5">
                                                <button type="button" @click="ai.provider = 'openai'"
                                                        class="ai-provider-card" :class="ai.provider === 'openai' ? 'ai-provider-active' : 'ai-provider'">
                                                    <span class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-slate-600 flex items-center justify-center text-[11px] font-bold text-slate-700 dark:text-gray-200 shrink-0">OAI</span>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-[11px] font-semibold text-slate-700 dark:text-gray-200 truncate">OpenAI</p>
                                                        <p class="text-[9px] text-gray-400 truncate">GPT-4</p>
                                                    </div>
                                                </button>
                                                <button type="button" @click="ai.provider = 'claude'"
                                                        class="ai-provider-card" :class="ai.provider === 'claude' ? 'ai-provider-active' : 'ai-provider'">
                                                    <span class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-slate-600 flex items-center justify-center text-[11px] font-bold text-slate-700 dark:text-gray-200 shrink-0">CL</span>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-[11px] font-semibold text-slate-700 dark:text-gray-200 truncate">Claude</p>
                                                        <p class="text-[9px] text-gray-400 truncate">Anthropic</p>
                                                    </div>
                                                </button>
                                                <button type="button" @click="ai.provider = 'gemini'"
                                                        class="ai-provider-card" :class="ai.provider === 'gemini' ? 'ai-provider-active' : 'ai-provider'">
                                                    <span class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-slate-600 flex items-center justify-center text-[11px] font-bold text-slate-700 dark:text-gray-200 shrink-0">GM</span>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-[11px] font-semibold text-slate-700 dark:text-gray-200 truncate">Gemini</p>
                                                        <p class="text-[9px] text-gray-400 truncate">Google</p>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button" @click="runAiTool('generate')" :disabled="ai.loading"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium disabled:opacity-50 transition-colors">
                                        <template x-if="!ai.loading">
                                            <span class="inline-flex items-center gap-2"><i class="bi bi-magic"></i>สร้างบทความ</span>
                                        </template>
                                        <template x-if="ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="inline-flex items-center gap-0.5">
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                </span>
                                                กำลังสร้าง...
                                            </span>
                                        </template>
                                    </button>
                                </div>

                                {{-- ─── Rewrite ─── --}}
                                <div x-show="aiTool === 'rewrite'" x-cloak class="space-y-4">
                                    <div>
                                        <label class="ai-label">เนื้อหาที่ต้องการเขียนใหม่</label>
                                        <textarea x-model="ai.rewriteText" rows="5" placeholder="วางเนื้อหาที่นี่ หรือเลือกจากบทความ..."
                                                  class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 focus:ring-2 focus:ring-indigo-500 dark:text-white resize-none"></textarea>
                                    </div>
                                    <div>
                                        <label class="ai-label">สไตล์</label>
                                        <div class="flex flex-wrap gap-1.5">
                                            <template x-for="s in ['ปรับปรุง', 'ง่ายขึ้น', 'เป็นทางการ', 'สร้างสรรค์', 'สั้นลง', 'ยาวขึ้น']" :key="s">
                                                <button type="button" @click="ai.rewriteStyle = s"
                                                        class="ai-chip" :class="ai.rewriteStyle === s ? 'ai-chip-active' : 'ai-chip-inactive'"
                                                        x-text="s"></button>
                                            </template>
                                        </div>
                                    </div>
                                    <button type="button" @click="runAiTool('rewrite')" :disabled="ai.loading"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium disabled:opacity-50 transition-colors">
                                        <template x-if="!ai.loading">
                                            <span class="inline-flex items-center gap-2"><i class="bi bi-pencil-square"></i>เขียนใหม่</span>
                                        </template>
                                        <template x-if="ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="inline-flex items-center gap-0.5">
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                </span>
                                                กำลังเขียนใหม่...
                                            </span>
                                        </template>
                                    </button>
                                </div>

                                {{-- ─── Summarize ─── --}}
                                <div x-show="aiTool === 'summarize'" x-cloak class="space-y-4">
                                    <div>
                                        <label class="ai-label">เนื้อหาหรือ URL ที่ต้องการสรุป</label>
                                        <textarea x-model="ai.summarizeText" rows="5" placeholder="วางเนื้อหาหรือ URL ที่ต้องการสรุป..."
                                                  class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 focus:ring-2 focus:ring-indigo-500 dark:text-white resize-none"></textarea>
                                    </div>
                                    <button type="button" @click="runAiTool('summarize')" :disabled="ai.loading"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium disabled:opacity-50 transition-colors">
                                        <template x-if="!ai.loading">
                                            <span class="inline-flex items-center gap-2"><i class="bi bi-card-text"></i>สรุปเนื้อหา</span>
                                        </template>
                                        <template x-if="ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="inline-flex items-center gap-0.5">
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                </span>
                                                กำลังสรุป...
                                            </span>
                                        </template>
                                    </button>
                                </div>

                                {{-- ─── Keywords ─── --}}
                                <div x-show="aiTool === 'keywords'" x-cloak class="space-y-4">
                                    <div>
                                        <label class="ai-label">หัวข้อหรือคีย์เวิร์ดเริ่มต้น</label>
                                        <input type="text" x-model="ai.keywordTopic" placeholder="ใส่หัวข้อที่ต้องการค้นหาคีย์เวิร์ด..."
                                               class="w-full text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 focus:ring-2 focus:ring-indigo-500 dark:text-white">
                                    </div>
                                    <button type="button" @click="runAiTool('keywords')" :disabled="ai.loading"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium disabled:opacity-50 transition-colors">
                                        <template x-if="!ai.loading">
                                            <span class="inline-flex items-center gap-2"><i class="bi bi-tags"></i>แนะนำคีย์เวิร์ด</span>
                                        </template>
                                        <template x-if="ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="inline-flex items-center gap-0.5">
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                </span>
                                                กำลังค้นหา...
                                            </span>
                                        </template>
                                    </button>
                                </div>

                                {{-- ─── SEO ─── --}}
                                <div x-show="aiTool === 'seo'" x-cloak class="space-y-4">
                                    <div class="bg-gray-50 dark:bg-slate-700/30 border border-gray-200 dark:border-white/10 rounded-xl p-4">
                                        <p class="text-sm font-medium text-slate-700 dark:text-gray-200 mb-2">AI จะวิเคราะห์เนื้อหาบทความปัจจุบันและให้คะแนน SEO</p>
                                        <ul class="text-xs text-gray-500 dark:text-gray-400 space-y-1 list-disc list-inside">
                                            <li>ความหนาแน่นคีย์เวิร์ด</li>
                                            <li>โครงสร้าง heading (H1-H6)</li>
                                            <li>คุณภาพ meta tags</li>
                                            <li>การใช้ internal/external links</li>
                                        </ul>
                                    </div>
                                    <button type="button" @click="runAiTool('seo')" :disabled="ai.loading"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium disabled:opacity-50 transition-colors">
                                        <template x-if="!ai.loading">
                                            <span class="inline-flex items-center gap-2"><i class="bi bi-graph-up"></i>วิเคราะห์ SEO</span>
                                        </template>
                                        <template x-if="ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="inline-flex items-center gap-0.5">
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                </span>
                                                กำลังวิเคราะห์...
                                            </span>
                                        </template>
                                    </button>
                                </div>

                                {{-- ─── Meta Tags ─── --}}
                                <div x-show="aiTool === 'meta'" x-cloak class="space-y-4">
                                    <div class="bg-gray-50 dark:bg-slate-700/30 border border-gray-200 dark:border-white/10 rounded-xl p-4">
                                        <p class="text-sm font-medium text-slate-700 dark:text-gray-200 mb-2">สร้าง Meta Title และ Description อัตโนมัติจากเนื้อหาบทความ</p>
                                        <ul class="text-xs text-gray-500 dark:text-gray-400 space-y-1 list-disc list-inside">
                                            <li>Meta Title (50-60 ตัวอักษร)</li>
                                            <li>Meta Description (140-160 ตัวอักษร)</li>
                                        </ul>
                                    </div>
                                    <button type="button" @click="runAiTool('meta')" :disabled="ai.loading"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium disabled:opacity-50 transition-colors">
                                        <template x-if="!ai.loading">
                                            <span class="inline-flex items-center gap-2"><i class="bi bi-code-slash"></i>สร้าง Meta Tags</span>
                                        </template>
                                        <template x-if="ai.loading">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="inline-flex items-center gap-0.5">
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                    <span class="ai-dot w-1.5 h-1.5 rounded-full bg-white"></span>
                                                </span>
                                                กำลังสร้าง...
                                            </span>
                                        </template>
                                    </button>
                                </div>

                                {{-- ─── Result ─── --}}
                                <div x-show="ai.result" x-cloak class="bg-gray-50 dark:bg-slate-700/30 border border-gray-200 dark:border-white/10 rounded-xl overflow-hidden">
                                    <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 dark:border-white/10">
                                        <div class="flex items-center gap-2">
                                            <i class="bi bi-check2-circle text-emerald-500"></i>
                                            <h4 class="text-sm font-semibold text-slate-700 dark:text-gray-200">ผลลัพธ์ AI</h4>
                                            <span class="text-[10px] text-gray-400" x-text="(ai.result?.length || 0) + ' ตัวอักษร'"></span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" @click="copyAiResult()"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium bg-white dark:bg-slate-700 border border-gray-200 dark:border-white/10 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-600 transition-colors">
                                                <i class="bi bi-clipboard"></i><span class="hidden sm:inline">คัดลอก</span>
                                            </button>
                                            <button type="button" @click="insertAiResult()"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors">
                                                <i class="bi bi-plus-circle"></i><span>ใช้เนื้อหานี้</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="p-4 max-h-96 overflow-y-auto text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap leading-relaxed" x-text="ai.result"></div>
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

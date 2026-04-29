@extends('layouts.admin')

@section('title', 'เครื่องมือ AI')

@push('styles')
<style>
    .ai-card { @apply bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-6 cursor-pointer transition-all hover:shadow-lg hover:-translate-y-0.5; }
    .ai-card:hover .ai-icon { @apply scale-110; }
    .ai-icon { @apply transition-transform duration-200; }
    @keyframes aiPulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    .ai-loading { animation: aiPulse 1.5s ease-in-out infinite; }
</style>
@endpush

@section('content')
<div x-data="aiDashboard()" x-init="init()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white">
                <i class="bi bi-robot text-violet-500 mr-2"></i>เครื่องมือ AI
                @if(isset($aiStatus) && $aiStatus['master'])
                    <span class="inline-flex items-center ml-2 px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-xs font-medium">
                        <i class="bi bi-circle-fill text-[8px] mr-1 animate-pulse"></i>ระบบเปิดใช้งาน
                    </span>
                @elseif(isset($aiStatus))
                    <span class="inline-flex items-center ml-2 px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs font-medium">
                        <i class="bi bi-circle-fill text-[8px] mr-1"></i>ระบบถูกปิด
                    </span>
                @endif
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">ใช้ AI ช่วยสร้างและจัดการเนื้อหา</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.blog.ai.cost') }}" class="px-4 py-2 border border-gray-200 text-gray-700 rounded-xl text-sm hover:bg-gray-50">
                <i class="bi bi-graph-up"></i> Cost Report
            </a>
            <a href="{{ route('admin.blog.ai.history') }}" class="px-4 py-2 border border-gray-200 text-gray-700 rounded-xl text-sm hover:bg-gray-50">
                <i class="bi bi-clock-history"></i> History
            </a>
            <a href="{{ route('admin.blog.ai.toggles') }}" class="px-4 py-2 bg-gradient-to-br from-violet-500 to-violet-600 text-white rounded-xl text-sm font-medium hover:shadow-lg">
                <i class="bi bi-toggles"></i> เปิด-ปิด AI
            </a>
        </div>
    </div>

    {{-- Master Switch Warning --}}
    @if(isset($aiStatus) && !$aiStatus['master'])
    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl mb-5">
        <div class="flex items-center gap-3">
            <i class="bi bi-exclamation-triangle-fill text-red-500 text-2xl"></i>
            <div class="flex-1">
                <strong class="text-red-800">ระบบ AI ถูกปิดใช้งาน</strong>
                <p class="text-sm text-red-700 mt-1">เครื่องมือ AI ทั้งหมดจะไม่สามารถใช้งานได้ — กรุณาเปิด Master Switch ที่ <a href="{{ route('admin.blog.ai.toggles') }}" class="underline font-semibold">หน้าตั้งค่า</a></p>
            </div>
        </div>
    </div>
    @endif

    {{-- Provider Status Summary --}}
    @if(isset($aiStatus) && $aiStatus['master'])
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-2xl p-4 mb-5">
        <div class="flex items-center gap-4 flex-wrap">
            <span class="text-sm font-semibold text-gray-700">Providers:</span>
            @foreach($aiStatus['providers'] as $p)
            <div class="flex items-center gap-1.5 text-sm">
                <span class="text-lg">{{ $p['meta']['icon'] }}</span>
                <span class="font-medium">{{ $p['meta']['label'] }}</span>
                @if($p['usable'])
                    <span class="inline-flex items-center px-1.5 py-0.5 bg-emerald-100 text-emerald-700 rounded text-xs">
                        <i class="bi bi-check-circle-fill"></i>
                    </span>
                @elseif(!$p['has_api_key'])
                    <span class="inline-flex items-center px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded text-xs" title="ไม่มี API Key">
                        <i class="bi bi-key"></i>
                    </span>
                @else
                    <span class="inline-flex items-center px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded text-xs">
                        <i class="bi bi-pause-circle"></i>
                    </span>
                @endif
            </div>
            @endforeach
            <span class="ml-auto text-xs text-gray-500">
                Tools เปิด: {{ collect($aiStatus['tools'])->where('enabled', true)->count() }}/{{ count($aiStatus['tools']) }}
            </span>
        </div>
    </div>
    @endif

    {{-- Quick Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-gray-100 dark:border-white/[0.06]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-500/10 flex items-center justify-center">
                    <i class="bi bi-robot text-violet-500 text-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">งาน AI ทั้งหมด</p>
                    <p class="text-xl font-bold text-slate-800 dark:text-white">{{ number_format($stats['total_tasks'] ?? 0) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-gray-100 dark:border-white/[0.06]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center">
                    <i class="bi bi-hash text-blue-500 text-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Tokens ใช้ไป</p>
                    <p class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($stats['tokens_used'] ?? 0) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-gray-100 dark:border-white/[0.06]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center">
                    <i class="bi bi-currency-dollar text-amber-500 text-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">ค่าใช้จ่ายรวม</p>
                    <p class="text-xl font-bold text-amber-600 dark:text-amber-400">${{ number_format($stats['total_cost'] ?? 0, 4) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-gray-100 dark:border-white/[0.06]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center">
                    <i class="bi bi-clock text-emerald-500 text-lg"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">เวลาเฉลี่ย</p>
                    <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($stats['avg_time'] ?? 0, 1) }}s</p>
                </div>
            </div>
        </div>
    </div>

    {{-- AI Tool Cards Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        {{-- Generate Article --}}
        <div class="ai-card" @click="openTool('generate')">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center ai-icon">
                    <i class="bi bi-robot text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white">สร้างบทความ</h3>
                    <p class="text-xs text-gray-400 mt-1">สร้างบทความจากคีย์เวิร์ดด้วย AI</p>
                </div>
            </div>
        </div>

        {{-- Rewrite --}}
        <div class="ai-card" @click="openTool('rewrite')">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-pink-500 to-rose-600 flex items-center justify-center ai-icon">
                    <i class="bi bi-pencil-square text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white">เขียนใหม่</h3>
                    <p class="text-xs text-gray-400 mt-1">ปรับเนื้อหาหรือเขียนใหม่ด้วย AI</p>
                </div>
            </div>
        </div>

        {{-- Summarize --}}
        <div class="ai-card" @click="openTool('summarize')">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-teal-500 to-emerald-600 flex items-center justify-center ai-icon">
                    <i class="bi bi-card-text text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white">สรุปเนื้อหา</h3>
                    <p class="text-xs text-gray-400 mt-1">สรุปเนื้อหายาวให้สั้นลง</p>
                </div>
            </div>
        </div>

        {{-- Research --}}
        <div class="ai-card" @click="openTool('research')">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center ai-icon">
                    <i class="bi bi-search text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white">ค้นหาข้อมูล</h3>
                    <p class="text-xs text-gray-400 mt-1">ค้นคว้าข้อมูลจากเว็บด้วย AI</p>
                </div>
            </div>
        </div>

        {{-- Keywords --}}
        <div class="ai-card" @click="openTool('keywords')">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center ai-icon">
                    <i class="bi bi-tags text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white">แนะนำคีย์เวิร์ด</h3>
                    <p class="text-xs text-gray-400 mt-1">AI แนะนำคีย์เวิร์ดที่เหมาะสม</p>
                </div>
            </div>
        </div>

        {{-- SEO Analysis --}}
        <div class="ai-card" @click="openTool('seo')">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-green-600 flex items-center justify-center ai-icon">
                    <i class="bi bi-graph-up text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white">วิเคราะห์ SEO</h3>
                    <p class="text-xs text-gray-400 mt-1">วิเคราะห์และแนะนำ SEO</p>
                </div>
            </div>
        </div>
    </div>

    {{-- AI Tool Modal --}}
    <div x-show="activeTool" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click.self="activeTool = null">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0">

            {{-- Modal Header --}}
            <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06] flex items-center justify-between flex-shrink-0">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="bi" :class="toolConfig[activeTool]?.icon || 'bi-robot'" :style="'color:' + (toolConfig[activeTool]?.color || '#7c3aed')"></i>
                    <span x-text="toolConfig[activeTool]?.title || ''"></span>
                </h3>
                <button @click="activeTool = null" class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-500 hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>

            {{-- Modal Body --}}
            <div class="flex-1 overflow-y-auto p-6">
                {{-- Generate --}}
                <div x-show="activeTool === 'generate'">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">คีย์เวิร์ดหลัก <span class="text-red-500">*</span></label>
                            <input type="text" x-model="toolInput.keyword" placeholder="เช่น วิธีถ่ายภาพ Portrait สวยๆ"
                                   class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">จำนวนคำ</label>
                                <input type="number" x-model="toolInput.wordCount" min="300" max="5000" step="100"
                                       class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">โทน</label>
                                <select x-model="toolInput.tone" class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white">
                                    <option value="professional">มืออาชีพ</option>
                                    <option value="casual">เป็นกันเอง</option>
                                    <option value="academic">วิชาการ</option>
                                    <option value="creative">สร้างสรรค์</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Rewrite --}}
                <div x-show="activeTool === 'rewrite'">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">เนื้อหาต้นฉบับ <span class="text-red-500">*</span></label>
                            <textarea x-model="toolInput.text" rows="6" placeholder="วางเนื้อหาที่ต้องการเขียนใหม่..."
                                      class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white resize-none focus:ring-2 focus:ring-indigo-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">สไตล์การเขียนใหม่</label>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="style in ['ปรับปรุง', 'ง่ายขึ้น', 'เป็นทางการ', 'สร้างสรรค์', 'สั้นลง', 'ยาวขึ้น']">
                                    <button type="button" @click="toolInput.style = style"
                                            :class="toolInput.style === style ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-indigo-300' : 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-400'"
                                            class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors" x-text="style"></button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Summarize --}}
                <div x-show="activeTool === 'summarize'">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">เนื้อหาหรือ URL <span class="text-red-500">*</span></label>
                        <textarea x-model="toolInput.text" rows="8" placeholder="วางเนื้อหาที่ต้องการสรุป หรือใส่ URL..."
                                  class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white resize-none focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>
                </div>

                {{-- Research --}}
                <div x-show="activeTool === 'research'">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">หัวข้อที่ต้องการค้นคว้า <span class="text-red-500">*</span></label>
                        <input type="text" x-model="toolInput.topic" placeholder="เช่น เทรนด์การถ่ายภาพ 2025"
                               class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                {{-- Keywords --}}
                <div x-show="activeTool === 'keywords'">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">หัวข้อหรือ Seed Keyword <span class="text-red-500">*</span></label>
                        <input type="text" x-model="toolInput.topic" placeholder="เช่น กล้องถ่ายรูป"
                               class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                {{-- SEO --}}
                <div x-show="activeTool === 'seo'">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">URL หรือเนื้อหา</label>
                            <textarea x-model="toolInput.text" rows="5" placeholder="วาง URL หรือเนื้อหาที่ต้องการวิเคราะห์ SEO..."
                                      class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white resize-none focus:ring-2 focus:ring-indigo-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Focus Keyword</label>
                            <input type="text" x-model="toolInput.keyword" placeholder="คีย์เวิร์ดหลัก"
                                   class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                {{-- Loading State --}}
                <div x-show="loading" x-cloak class="mt-6">
                    <div class="flex items-center justify-center py-12">
                        <div class="text-center">
                            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center mx-auto mb-4 ai-loading">
                                <i class="bi bi-robot text-white text-2xl"></i>
                            </div>
                            <p class="text-sm font-medium text-slate-700 dark:text-gray-200">AI กำลังประมวลผล...</p>
                            <p class="text-xs text-gray-400 mt-1">กรุณารอสักครู่</p>
                        </div>
                    </div>
                </div>

                {{-- Result Display --}}
                <div x-show="result && !loading" x-cloak class="mt-6">
                    <div class="bg-gray-50 dark:bg-slate-700/50 rounded-xl p-4 border border-gray-200 dark:border-white/10">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-bold text-slate-700 dark:text-gray-200">ผลลัพธ์</h4>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="copyResult()"
                                        class="px-2.5 py-1 text-xs bg-white dark:bg-slate-600 border border-gray-200 dark:border-white/10 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-500 transition-colors">
                                    <i class="bi bi-clipboard mr-1"></i>คัดลอก
                                </button>
                                <button type="button" @click="saveResult()"
                                        class="px-2.5 py-1 text-xs bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                                    <i class="bi bi-floppy mr-1"></i>บันทึก
                                </button>
                            </div>
                        </div>
                        <div class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap max-h-80 overflow-y-auto leading-relaxed" x-text="result"></div>
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="px-6 py-4 border-t border-gray-100 dark:border-white/[0.06] flex items-center justify-between flex-shrink-0">
                <select x-model="selectedProvider" class="text-sm px-3 py-2 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white">
                    <option value="openai">OpenAI</option>
                    <option value="claude">Claude</option>
                    <option value="gemini">Gemini</option>
                </select>
                <div class="flex items-center gap-3">
                    <button type="button" @click="activeTool = null"
                            class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                        ปิด
                    </button>
                    <button type="button" @click="runTool()" :disabled="loading"
                            class="px-5 py-2 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 text-white rounded-xl text-sm font-medium disabled:opacity-50 transition-all shadow-lg shadow-indigo-500/25">
                        <i class="bi" :class="loading ? 'bi-hourglass-split' : 'bi-play-fill'" class="mr-1"></i>
                        <span x-text="loading ? 'กำลังประมวลผล...' : 'เริ่มประมวลผล'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- AI Settings --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-6 mb-6"
         x-data="{ settingsOpen: false }">
        <button type="button" @click="settingsOpen = !settingsOpen"
                class="w-full flex items-center justify-between text-left">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="bi bi-gear text-gray-500"></i>ตั้งค่า AI
            </h3>
            <i class="bi text-gray-400" :class="settingsOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
        </button>
        <div x-show="settingsOpen" x-collapse x-cloak class="mt-4">
            <form method="POST" action="{{ route('admin.blog.ai.settings') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">ผู้ให้บริการ</label>
                    <select name="default_provider" class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white">
                        <option value="openai" {{ ($settings['default_provider'] ?? 'openai') == 'openai' ? 'selected' : '' }}>OpenAI</option>
                        <option value="claude" {{ ($settings['default_provider'] ?? '') == 'claude' ? 'selected' : '' }}>Claude (Anthropic)</option>
                        <option value="gemini" {{ ($settings['default_provider'] ?? '') == 'gemini' ? 'selected' : '' }}>Gemini (Google)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">โมเดล</label>
                    <select name="default_model" class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white">
                        <option value="gpt-4o">GPT-4o</option>
                        <option value="gpt-4o-mini">GPT-4o Mini</option>
                        <option value="claude-3-5-sonnet">Claude 3.5 Sonnet</option>
                        <option value="gemini-pro">Gemini Pro</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                        Temperature: <span class="font-mono" x-data="{ temp: {{ $settings['temperature'] ?? 0.7 }} }" x-text="temp"></span>
                    </label>
                    <input type="range" name="temperature" min="0" max="2" step="0.1" value="{{ $settings['temperature'] ?? 0.7 }}"
                           class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-indigo-600 dark:bg-gray-700 mt-2">
                </div>
                <div class="sm:col-span-3">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-medium transition-colors">
                        <i class="bi bi-check-lg mr-1"></i>บันทึกการตั้งค่า
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Recent Tasks Table --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="bi bi-clock-history text-gray-500"></i>งาน AI ล่าสุด
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/80 dark:bg-slate-700/50">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">ประเภท</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">หัวข้อ</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">ผู้ให้บริการ</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">สถานะ</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Tokens</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">ค่าใช้จ่าย</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">เวลา</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">วันที่</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.06]">
                    @forelse($recentTasks ?? [] as $task)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-slate-700/30">
                        <td class="px-4 py-3">
                            @php
                                $typeLabels = [
                                    'generate' => ['สร้างบทความ', 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-400'],
                                    'rewrite' => ['เขียนใหม่', 'bg-pink-100 text-pink-700 dark:bg-pink-500/20 dark:text-pink-400'],
                                    'summarize' => ['สรุป', 'bg-teal-100 text-teal-700 dark:bg-teal-500/20 dark:text-teal-400'],
                                    'research' => ['ค้นคว้า', 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400'],
                                    'keywords' => ['คีย์เวิร์ด', 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400'],
                                    'seo' => ['SEO', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400'],
                                ];
                                $typeInfo = $typeLabels[$task->type] ?? ['อื่นๆ', 'bg-gray-100 text-gray-700'];
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $typeInfo[1] }}">{{ $typeInfo[0] }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-sm text-slate-700 dark:text-gray-200 truncate block max-w-[200px]">{{ $task->title ?? $task->input_summary ?? '-' }}</span>
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-gray-500 dark:text-gray-400">{{ ucfirst($task->provider ?? '-') }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($task->status === 'completed')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400">สำเร็จ</span>
                            @elseif($task->status === 'failed')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400">ล้มเหลว</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400">{{ $task->status }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-xs font-mono text-gray-600 dark:text-gray-300">{{ number_format($task->tokens_used ?? 0) }}</td>
                        <td class="px-4 py-3 text-center text-xs font-mono text-gray-600 dark:text-gray-300">${{ number_format($task->cost ?? 0, 4) }}</td>
                        <td class="px-4 py-3 text-center text-xs text-gray-500 dark:text-gray-400">{{ number_format($task->processing_time ?? 0, 1) }}s</td>
                        <td class="px-4 py-3 text-center text-xs text-gray-500 dark:text-gray-400">{{ $task->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            <button @click="viewTask({{ $task->id }})" title="ดูผลลัพธ์"
                                    class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-gray-400 flex items-center justify-center hover:bg-indigo-100 hover:text-indigo-600 dark:hover:bg-indigo-500/20 dark:hover:text-indigo-400 transition-colors mx-auto">
                                <i class="bi bi-eye text-xs"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center mb-3">
                                    <i class="bi bi-robot text-2xl text-gray-400"></i>
                                </div>
                                <p class="text-gray-500 dark:text-gray-400 font-medium">ยังไม่มีงาน AI</p>
                                <p class="text-sm text-gray-400 mt-1">เริ่มใช้เครื่องมือ AI จากการ์ดด้านบน</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($recentTasks) && $recentTasks instanceof \Illuminate\Pagination\LengthAwarePaginator && $recentTasks->hasPages())
        <div class="px-4 py-3 border-t border-gray-100 dark:border-white/[0.06]">
            {{ $recentTasks->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
function aiDashboard() {
    return {
        activeTool: null,
        loading: false,
        result: null,
        selectedProvider: 'openai',
        toolInput: {
            keyword: '', text: '', topic: '', wordCount: 1500, tone: 'professional', style: 'ปรับปรุง'
        },
        toolConfig: {
            generate: { title: 'สร้างบทความ AI', icon: 'bi-robot', color: '#7c3aed' },
            rewrite: { title: 'เขียนใหม่', icon: 'bi-pencil-square', color: '#ec4899' },
            summarize: { title: 'สรุปเนื้อหา', icon: 'bi-card-text', color: '#14b8a6' },
            research: { title: 'ค้นหาข้อมูล', icon: 'bi-search', color: '#3b82f6' },
            keywords: { title: 'แนะนำคีย์เวิร์ด', icon: 'bi-tags', color: '#f59e0b' },
            seo: { title: 'วิเคราะห์ SEO', icon: 'bi-graph-up', color: '#22c55e' },
        },

        init() {},

        openTool(tool) {
            this.activeTool = tool;
            this.result = null;
            this.loading = false;
            this.toolInput = { keyword: '', text: '', topic: '', wordCount: 1500, tone: 'professional', style: 'ปรับปรุง' };
        },

        runTool() {
            this.loading = true;
            this.result = null;

            const payload = {
                tool: this.activeTool,
                provider: this.selectedProvider,
                ...this.toolInput
            };

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
                this.result = data.result || data.error || 'ไม่มีผลลัพธ์';
            })
            .catch(err => { this.result = 'เกิดข้อผิดพลาด: ' + err.message; })
            .finally(() => { this.loading = false; });
        },

        copyResult() {
            navigator.clipboard.writeText(this.result).then(() => {
                Swal.fire({ icon: 'success', title: 'คัดลอกแล้ว', timer: 1000, showConfirmButton: false });
            });
        },

        saveResult() {
            Swal.fire({ icon: 'success', title: 'บันทึกแล้ว', text: 'ผลลัพธ์ถูกบันทึกเรียบร้อย', timer: 1500, showConfirmButton: false });
        },

        viewTask(id) {
            fetch(`{{ url('admin/blog/ai/tasks') }}/${id}`, {
                headers: { 'Accept': 'application/json' }
            }).then(r => r.json()).then(data => {
                Swal.fire({
                    title: 'ผลลัพธ์งาน AI',
                    html: `<div class="text-left text-sm max-h-96 overflow-y-auto whitespace-pre-wrap">${data.result || 'ไม่มีผลลัพธ์'}</div>`,
                    width: 600,
                    showCloseButton: true,
                    showConfirmButton: false,
                });
            });
        }
    };
}
</script>
@endpush

@extends('layouts.admin')

@section('title', ($item->exists ? 'แก้ไข' : 'เพิ่ม') . ' Addon · Monetization')

@php
    /**
     * Per-category visual identity. Mirrors the catalog UI on the
     * photographer's store side so the admin sees the same colour
     * + icon associations they're configuring.
     */
    $catShape = [
        'promotion'  => ['icon' => 'bi-rocket-takeoff',   'accent' => '#6366f1', 'tone' => 'indigo',  'title' => 'โปรโมท'],
        'storage'    => ['icon' => 'bi-cloud-arrow-up-fill', 'accent' => '#0ea5e9', 'tone' => 'sky',    'title' => 'พื้นที่เก็บงาน'],
        'ai_credits' => ['icon' => 'bi-cpu-fill',         'accent' => '#a855f7', 'tone' => 'purple', 'title' => 'AI Credits'],
        'branding'   => ['icon' => 'bi-palette-fill',     'accent' => '#10b981', 'tone' => 'emerald','title' => 'Branding'],
        'priority'   => ['icon' => 'bi-lightning-charge-fill', 'accent' => '#f97316', 'tone' => 'orange', 'title' => 'Priority'],
    ];
    $shape = $catShape[$item->category] ?? $catShape['promotion'];

    /** Tone classes that work on light + dark via the rgba hack we use
     *  elsewhere in the app — bg via inline style dodges the
     *  legacy darkmode.css `bg-{color}-50` flatten override. */
    $toneBg = [
        'emerald' => 'rgba(16,185,129,0.15)',
        'amber'   => 'rgba(245,158,11,0.18)',
        'rose'    => 'rgba(244, 63, 94,0.18)',
        'slate'   => 'rgba(100,116,139,0.18)',
        'indigo'  => 'rgba(99,102,241,0.15)',
        'sky'     => 'rgba(14,165,233,0.15)',
        'purple'  => 'rgba(168, 85,247,0.15)',
        'orange'  => 'rgba(249,115, 22,0.15)',
    ];

    $money = fn ($v) => '฿' . number_format((float) $v, 0);

    // Whether this addon currently has any purchase history. Drives the
    // "danger zone" copy + the SKU-locked notice.
    $hasPurchases = isset($purchaseStats) && (int) $purchaseStats->total > 0;

    /**
     * Reusable input classes — applied consistently to every input/select
     * so the form has ONE visual language. Heights match across input
     * types (h-11 ≈ 44px) which fits Apple's hit-target guideline + the
     * Bootstrap-era `.form-control` line height the rest of the app uses.
     *
     *   $cls['input']    — text/number/email
     *   $cls['select']   — dropdown (extra right-padding for the chevron)
     *   $cls['textarea'] — auto-grow textarea
     *   $cls['locked']   — appended when the field is disabled/readonly
     */
    $cls = [
        'input' => 'w-full h-11 px-3.5 rounded-xl border border-slate-200 dark:border-slate-600 '
                 . 'bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 '
                 . 'placeholder:text-slate-400 dark:placeholder:text-slate-500 '
                 . 'focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 '
                 . 'transition-all',
        'select' => 'w-full h-11 pl-3.5 pr-9 rounded-xl border border-slate-200 dark:border-slate-600 '
                  . 'bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 '
                  . 'focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 '
                  . 'transition-all',
        'locked' => 'cursor-not-allowed bg-slate-50 dark:bg-slate-900/40 text-slate-500 dark:text-slate-400 '
                  . '!ring-0 !border-slate-200 dark:!border-slate-700',
        'label' => 'flex items-center justify-between text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5',
        'help'  => 'text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed',
    ];
@endphp

@section('content')
<div class="max-w-7xl mx-auto p-4 md:p-6">

    {{-- ════════════════════════ Header ════════════════════════ --}}
    <div class="mb-6">
        <a href="{{ route('admin.monetization.addons.index') }}"
           class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-indigo-600 mb-3">
            <i class="bi bi-arrow-left"></i> กลับ Addon Catalog
        </a>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4 min-w-0">
                {{-- Category icon "tile" — visual anchor + immediate
                     feedback on what category they're editing --}}
                <div class="shrink-0 w-14 h-14 rounded-2xl flex items-center justify-center text-2xl text-white shadow-lg"
                     style="background:{{ $shape['accent'] }};">
                    <i class="bi {{ $shape['icon'] }}"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                        {{ $item->exists ? $item->label : 'เพิ่ม Addon ใหม่' }}
                    </h1>
                    <div class="flex flex-wrap items-center gap-2 mt-1.5 text-xs">
                        @if($item->exists)
                            <code class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-mono">
                                {{ $item->sku }}
                            </code>
                        @endif
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-semibold ring-1 ring-inset"
                              style="background:{{ $toneBg[$shape['tone']] ?? $toneBg['indigo'] }};
                                     color:{{ $shape['accent'] }};">
                            <i class="bi {{ $shape['icon'] }}"></i> {{ $shape['title'] }}
                        </span>
                        @if($item->exists)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-semibold ring-1 ring-inset {{ $item->is_active ? 'text-emerald-700 dark:text-emerald-300 ring-emerald-300/50' : 'text-slate-700 dark:text-slate-300 ring-slate-300/50' }}"
                                  style="background:{{ $item->is_active ? $toneBg['emerald'] : $toneBg['slate'] }};">
                                @if($item->is_active) ✓ เปิดขาย @else ✗ ปิดอยู่ @endif
                            </span>
                            @if($item->trashed())
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full font-semibold ring-1 ring-rose-300/60 text-rose-700 dark:text-rose-300"
                                      style="background:{{ $toneBg['rose'] }};">soft-deleted</span>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            {{-- Top quick actions (mirror sticky bottom save) --}}
            @if($item->exists)
                <div class="flex items-center gap-2">
                    <button type="button"
                            onclick="document.getElementById('addonForm').requestSubmit();"
                            class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm transition">
                        <i class="bi bi-check-lg"></i> บันทึก
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- ════════════════════════ Flash messages ════════════════════════ --}}
    @if($errors->any())
        <div class="mb-4 rounded-xl ring-1 ring-rose-300/60 px-4 py-3 text-sm"
             style="background:{{ $toneBg['rose'] }};">
            <strong class="text-rose-800 dark:text-rose-200">กรอกข้อมูลไม่ครบหรือผิดรูปแบบ:</strong>
            <ul class="mb-0 mt-2 ml-5 list-disc text-rose-700 dark:text-rose-300">
                @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
        </div>
    @endif
    @if(session('success'))
        <div class="mb-4 rounded-xl ring-1 ring-emerald-300/60 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300"
             style="background:{{ $toneBg['emerald'] }};">
            <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
        </div>
    @endif

    {{-- ════════════════════════ Two-column body ════════════════════════ --}}
    <form method="POST" id="addonForm"
          action="{{ $item->exists
                       ? route('admin.monetization.addons.update', $item->id)
                       : route('admin.monetization.addons.store') }}">
        @csrf
        @if($item->exists) @method('PUT') @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- ─────── LEFT (2/3): Form sections ─────── --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Basic info card --}}
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden">
                    <header class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                        <h2 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="bi bi-info-circle text-indigo-500"></i> ข้อมูลพื้นฐาน
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">รายละเอียดที่แสดงในร้านสำหรับช่างภาพ</p>
                    </header>
                    <div class="p-6 grid md:grid-cols-2 gap-5">

                        {{-- SKU --}}
                        <div class="md:col-span-1">
                            <label class="{{ $cls['label'] }}">
                                <span>SKU <span class="text-rose-500">*</span></span>
                                @if($item->exists)
                                    <span class="text-[10px] uppercase font-bold text-amber-700 dark:text-amber-300 px-1.5 py-0.5 rounded ring-1 ring-amber-300/60"
                                          style="background:{{ $toneBg['amber'] }};">
                                        🔒 immutable
                                    </span>
                                @endif
                            </label>
                            <input type="text" name="sku" required pattern="[a-z0-9_\.]+" maxlength="60"
                                   value="{{ old('sku', $item->sku) }}"
                                   {{ $item->exists ? 'readonly' : '' }}
                                   placeholder="เช่น storage.50gb"
                                   class="{{ $cls['input'] }} font-mono {{ $item->exists ? $cls['locked'] : '' }}">
                            <p class="{{ $cls['help'] }}">
                                @if($item->exists)
                                    SKU ใช้อ้างอิงในประวัติการซื้อ — เปลี่ยนไม่ได้หลังสร้างแล้ว
                                @else
                                    ตัวพิมพ์เล็ก + ตัวเลข + จุด/ขีดล่าง · เช่น <code class="font-mono px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-800">boost.monthly</code>
                                @endif
                            </p>
                        </div>

                        {{-- Category --}}
                        <div class="md:col-span-1">
                            <label class="{{ $cls['label'] }}">
                                <span>หมวด <span class="text-rose-500">*</span></span>
                                @if($item->exists)
                                    <span class="text-[10px] uppercase font-bold text-amber-700 dark:text-amber-300 px-1.5 py-0.5 rounded ring-1 ring-amber-300/60"
                                          style="background:{{ $toneBg['amber'] }};">
                                        🔒 immutable
                                    </span>
                                @endif
                            </label>
                            <select name="category" id="category" required
                                    {{ $item->exists ? 'disabled' : '' }}
                                    class="{{ $cls['select'] }} {{ $item->exists ? $cls['locked'] : '' }}">
                                @foreach($categories as $code => $label)
                                    <option value="{{ $code }}" @selected(old('category', $item->category)===$code)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @if($item->exists)
                                <input type="hidden" name="category" value="{{ $item->category }}">
                            @endif
                            <p class="{{ $cls['help'] }}">
                                หมวดกำหนด activation handler — ห้ามเปลี่ยนหลังสร้าง
                            </p>
                        </div>

                        {{-- Label (full width) --}}
                        <div class="md:col-span-2">
                            <label class="{{ $cls['label'] }}">
                                <span>ชื่อรายการ <span class="text-rose-500">*</span></span>
                                <span class="text-xs text-slate-400 font-normal" id="labelCount">{{ mb_strlen(old('label', $item->label ?? '')) }}/120</span>
                            </label>
                            <input type="text" name="label" required maxlength="120"
                                   value="{{ old('label', $item->label) }}"
                                   placeholder="เช่น +50 GB"
                                   class="{{ $cls['input'] }} font-semibold !text-base"
                                   oninput="document.getElementById('labelCount').textContent = this.value.length + '/120'; window.dispatchEvent(new CustomEvent('addon-preview-update', { detail: { field: 'label', value: this.value } }))">
                            <p class="{{ $cls['help'] }}">หัวเรื่องบนการ์ดในร้าน</p>
                        </div>

                        {{-- Price + Sort order side-by-side --}}
                        <div>
                            <label class="{{ $cls['label'] }}">
                                <span>ราคา (บาท) <span class="text-rose-500">*</span></span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-indigo-500 text-base font-bold pointer-events-none">฿</span>
                                <input type="number" name="price_thb" required min="0" max="999999" step="1"
                                       value="{{ old('price_thb', $item->price_thb) }}"
                                       class="{{ $cls['input'] }} !pl-9 !text-base !font-bold !text-indigo-700 dark:!text-indigo-300"
                                       oninput="window.dispatchEvent(new CustomEvent('addon-preview-update', { detail: { field: 'price_thb', value: this.value } }))">
                            </div>
                            <p class="{{ $cls['help'] }}">ราคาที่ช่างภาพจ่ายผ่าน checkout</p>
                        </div>

                        <div>
                            <label class="{{ $cls['label'] }}">
                                <span>ลำดับการแสดง</span>
                            </label>
                            <input type="number" name="sort_order" min="0" max="9999"
                                   value="{{ old('sort_order', $item->sort_order) }}"
                                   class="{{ $cls['input'] }}">
                            <p class="{{ $cls['help'] }}">เลขน้อย = แสดงก่อน · ปกติเริ่ม 10, 20, 30…</p>
                        </div>

                        {{-- Tagline --}}
                        <div class="md:col-span-2">
                            <label class="{{ $cls['label'] }}">
                                <span>คำโปรย (Tagline)</span>
                                <span class="text-xs text-slate-400 font-normal" id="taglineCount">{{ mb_strlen(old('tagline', $item->tagline ?? '')) }}/200</span>
                            </label>
                            <input type="text" name="tagline" maxlength="200"
                                   value="{{ old('tagline', $item->tagline) }}"
                                   placeholder="เช่น “งาน wedding 1-2 อีเวนต์”"
                                   class="{{ $cls['input'] }}"
                                   oninput="document.getElementById('taglineCount').textContent = this.value.length + '/200'; window.dispatchEvent(new CustomEvent('addon-preview-update', { detail: { field: 'tagline', value: this.value } }))">
                            <p class="{{ $cls['help'] }}">ข้อความเล็กใต้ชื่อรายการ ใช้บอกประโยชน์/use-case</p>
                        </div>

                        {{-- Badge --}}
                        <div>
                            <label class="{{ $cls['label'] }}">
                                <span>Badge (ถ้ามี)</span>
                            </label>
                            <input type="text" name="badge" maxlength="30"
                                   value="{{ old('badge', $item->badge) }}"
                                   placeholder="ขายดี / แนะนำ / พรีเมี่ยม"
                                   class="{{ $cls['input'] }}"
                                   oninput="window.dispatchEvent(new CustomEvent('addon-preview-update', { detail: { field: 'badge', value: this.value } }))">
                            <p class="{{ $cls['help'] }}">ป้ายเล็กบนการ์ด — แสดงสีเหลือง</p>
                        </div>

                        {{-- Active toggle — clickable card style --}}
                        <div class="flex items-stretch">
                            <label class="flex items-center gap-3 cursor-pointer w-full px-3.5 rounded-xl ring-1 ring-slate-200 dark:ring-slate-600 hover:ring-indigo-300 dark:hover:ring-indigo-500 transition has-[:checked]:ring-2 has-[:checked]:ring-indigo-500/40 has-[:checked]:bg-indigo-50/50 dark:has-[:checked]:bg-indigo-900/20">
                                <input type="checkbox" name="is_active" value="1"
                                       @checked(old('is_active', $item->is_active ?? true))
                                       class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500/30 focus:ring-2 cursor-pointer">
                                <div class="flex-1 py-2">
                                    <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">เปิดขายในร้าน</div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">ปิดเพื่อซ่อนชั่วคราวโดยไม่ลบ</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </section>

                {{-- Category-specific meta card --}}
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden">
                    <header class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                        <h2 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="bi bi-sliders text-indigo-500"></i> รายละเอียดเฉพาะหมวด
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">ฟิลด์เพิ่มเติมที่ AddonService ใช้ตอน activation</p>
                    </header>
                    <div class="p-6">
                        @php $meta = $item->meta ?? []; @endphp

                        {{-- promotion --}}
                        <div data-cat="promotion" class="cat-block grid md:grid-cols-3 gap-5">
                            <div>
                                <label class="{{ $cls['label'] }}"><span>Kind</span></label>
                                <select name="meta_kind" class="{{ $cls['select'] }}">
                                    @foreach(['boost'=>'Boost — เพิ่มอันดับค้นหา','featured'=>'Featured — การ์ดเด่น','highlight'=>'Highlight — กรอบ + ป้าย'] as $v=>$l)
                                        <option value="{{ $v }}" @selected(old('meta_kind', $meta['kind'] ?? 'boost')===$v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="{{ $cls['label'] }}"><span>รอบบิล</span></label>
                                <select name="meta_cycle" class="{{ $cls['select'] }}">
                                    @foreach(['daily'=>'รายวัน','monthly'=>'รายเดือน','yearly'=>'รายปี'] as $v=>$l)
                                        <option value="{{ $v }}" @selected(old('meta_cycle', $meta['cycle'] ?? 'monthly')===$v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="{{ $cls['label'] }}"><span>Boost Score (1-25)</span></label>
                                <input type="number" name="meta_boost_score" min="1" max="25"
                                       value="{{ old('meta_boost_score', $meta['boost_score'] ?? 10) }}"
                                       class="{{ $cls['input'] }} !font-bold">
                                <p class="{{ $cls['help'] }}">↑ มาก = ขึ้นอันดับสูงกว่าในผลค้นหา</p>
                            </div>
                        </div>

                        {{-- storage --}}
                        <div data-cat="storage" class="cat-block">
                            <label class="{{ $cls['label'] }}"><span>พื้นที่เพิ่ม</span></label>
                            <div class="flex items-stretch gap-2">
                                <input type="number" name="meta_storage_gb" min="1" max="10240"
                                       value="{{ old('meta_storage_gb', $meta['storage_gb'] ?? 50) }}"
                                       class="{{ $cls['input'] }} max-w-[10rem] !font-bold">
                                <div class="h-11 px-4 flex items-center rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-semibold text-sm">
                                    GB
                                </div>
                            </div>
                            <p class="{{ $cls['help'] }}">เพิ่มจาก quota แผน subscription ปัจจุบันของช่างภาพ</p>
                        </div>

                        {{-- ai_credits --}}
                        <div data-cat="ai_credits" class="cat-block">
                            <label class="{{ $cls['label'] }}"><span>จำนวน Credits</span></label>
                            <div class="flex items-stretch gap-2">
                                <input type="number" name="meta_credits" min="100" max="1000000" step="100"
                                       value="{{ old('meta_credits', $meta['credits'] ?? 5000) }}"
                                       class="{{ $cls['input'] }} max-w-[12rem] !font-bold">
                                <div class="h-11 px-4 flex items-center rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-semibold text-sm">
                                    credits
                                </div>
                            </div>
                            <p class="{{ $cls['help'] }}">ใช้ใน AI Face Search · Best Shot · Auto-tag — 1 credit = 1 ภาพ</p>
                        </div>

                        {{-- branding --}}
                        <div data-cat="branding" class="cat-block space-y-5">
                            <label class="flex items-center gap-3 cursor-pointer px-3.5 py-3 rounded-xl ring-1 ring-slate-200 dark:ring-slate-600 hover:ring-indigo-300 dark:hover:ring-indigo-500 transition has-[:checked]:ring-2 has-[:checked]:ring-indigo-500/40 has-[:checked]:bg-indigo-50/50 dark:has-[:checked]:bg-indigo-900/20">
                                <input type="checkbox" name="meta_one_time" value="1"
                                       @checked(old('meta_one_time', !empty($meta['one_time'])))
                                       class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500/30 focus:ring-2">
                                <div class="flex-1">
                                    <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">ซื้อครั้งเดียว ใช้ตลอดชีพ subscription</div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">ติ๊กถ้า addon นี้ไม่หมดอายุ</p>
                                </div>
                            </label>
                            <div>
                                <label class="{{ $cls['label'] }}"><span>หรือกำหนด Cycle (ถ้าไม่ใช่ one-time)</span></label>
                                <select name="meta_cycle" class="{{ $cls['select'] }} max-w-[14rem]">
                                    @foreach(['monthly'=>'รายเดือน','yearly'=>'รายปี'] as $v=>$l)
                                        <option value="{{ $v }}" @selected(old('meta_cycle', $meta['cycle'] ?? 'monthly')===$v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- priority --}}
                        <div data-cat="priority" class="cat-block">
                            <label class="{{ $cls['label'] }}"><span>รอบบิล</span></label>
                            <select name="meta_cycle" class="{{ $cls['select'] }} max-w-[14rem]">
                                @foreach(['monthly'=>'รายเดือน','yearly'=>'รายปี'] as $v=>$l)
                                    <option value="{{ $v }}" @selected(old('meta_cycle', $meta['cycle'] ?? 'monthly')===$v)>{{ $l }}</option>
                                @endforeach
                            </select>
                            <p class="{{ $cls['help'] }}">รอบเก็บค่าบริการสำหรับ Priority Lane</p>
                        </div>
                    </div>
                </section>

                {{-- Recent buyers (only on edit) --}}
                @if($item->exists && $recentBuyers->count() > 0)
                    <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden">
                        <header class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                            <h2 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <i class="bi bi-people text-emerald-500"></i> ผู้ซื้อล่าสุด
                                <span class="text-sm font-normal text-slate-500 ml-1">(ล่าสุด {{ $recentBuyers->count() }} รายการ)</span>
                            </h2>
                        </header>
                        <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($recentBuyers as $buyer)
                                <li class="px-6 py-3 flex items-center justify-between text-sm">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-slate-900 dark:text-white truncate">
                                            {{ $buyer->display_name ?? $buyer->first_name ?? $buyer->email ?? 'Unknown' }}
                                        </div>
                                        <div class="text-xs text-slate-500 truncate">{{ $buyer->email }}</div>
                                    </div>
                                    <div class="text-right shrink-0 ml-4">
                                        <div class="text-xs text-slate-500">
                                            {{ \Carbon\Carbon::parse($buyer->created_at)->diffForHumans() }}
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1
                                            {{ $buyer->status === 'activated' ? 'text-emerald-700 dark:text-emerald-300 ring-emerald-300/50' : 'text-amber-700 dark:text-amber-300 ring-amber-300/50' }}"
                                              style="background:{{ $buyer->status === 'activated' ? $toneBg['emerald'] : $toneBg['amber'] }};">
                                            {{ $buyer->status }}
                                        </span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Danger zone (edit only, with purchases) --}}
                @if($item->exists)
                    <section class="rounded-2xl ring-1 ring-rose-300/60 overflow-hidden"
                             style="background:{{ $toneBg['rose'] }};">
                        <header class="px-6 py-4 border-b border-rose-300/40">
                            <h2 class="font-bold text-rose-800 dark:text-rose-200 flex items-center gap-2">
                                <i class="bi bi-exclamation-triangle-fill"></i> Danger zone
                            </h2>
                            <p class="text-xs text-rose-700 dark:text-rose-300 mt-0.5">
                                @if($hasPurchases)
                                    มีประวัติการซื้อ {{ number_format((int) $purchaseStats->total) }} รายการ — การลบจะเป็น soft-delete (รักษา audit) แต่ผู้ซื้อยังเห็นในประวัติของตัวเอง
                                @else
                                    ยังไม่มีประวัติการซื้อ — ลบได้ทันที
                                @endif
                            </p>
                        </header>
                        <div class="p-6 flex flex-wrap items-center justify-between gap-3">
                            <div class="text-sm text-rose-800 dark:text-rose-200">
                                @if($hasPurchases)
                                    <strong>Soft delete:</strong> ปิดขายถาวร + ซ่อนจาก index ลูกค้า
                                @else
                                    <strong>Hard delete:</strong> ลบ record ออกจากฐานข้อมูล
                                @endif
                            </div>
                            <form method="POST" action="{{ route('admin.monetization.addons.destroy', $item->id) }}"
                                  onsubmit="return confirm('{{ $hasPurchases ? "Soft-delete \"" . $item->label . "\" (มี " . $purchaseStats->total . " ประวัติการซื้อ) — ดำเนินการ?" : "ลบ \"" . $item->label . "\" ออกจากฐานข้อมูล?" }}');">
                                @csrf @method('DELETE')
                                <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg ring-1 ring-rose-400 text-rose-800 dark:text-rose-100 font-bold text-sm transition hover:bg-rose-100 dark:hover:bg-rose-900/40"
                                        style="background:rgba(244,63,94,0.20);">
                                    <i class="bi bi-trash"></i>
                                    {{ $hasPurchases ? 'Soft Delete' : 'ลบทันที' }}
                                </button>
                            </form>
                        </div>
                    </section>
                @endif
            </div>

            {{-- ─────── RIGHT (1/3): Sidebar ─────── --}}
            <aside class="space-y-6">

                {{-- Live preview card — mirrors the Store catalog card --}}
                <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm overflow-hidden sticky top-4">
                    <header class="px-5 py-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                        <h2 class="font-bold text-slate-900 dark:text-white flex items-center gap-2 text-sm">
                            <i class="bi bi-eye text-indigo-500"></i> Live Preview
                        </h2>
                        <span class="text-xs text-slate-400">มุมมองช่างภาพ</span>
                    </header>
                    <div class="p-5">
                        <div id="addonPreviewCard"
                             class="rounded-2xl p-5 ring-1 ring-slate-200 dark:ring-slate-700 hover:shadow-md transition"
                             style="background:linear-gradient(135deg, rgba(255,255,255,0.5), rgba(255,255,255,0.95));">

                            {{-- Mini icon header from category --}}
                            <div class="flex items-start justify-between mb-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white"
                                     style="background:{{ $shape['accent'] }};">
                                    <i class="bi {{ $shape['icon'] }} text-lg"></i>
                                </div>
                                <span id="previewBadge"
                                      class="text-xs font-bold px-2 py-0.5 rounded-full ring-1 ring-amber-300/60 text-amber-700 dark:text-amber-300
                                             {{ ($item->badge ?? '') !== '' ? '' : 'hidden' }}"
                                      style="background:{{ $toneBg['amber'] }};">
                                    {{ $item->badge ?? 'ขายดี' }}
                                </span>
                            </div>

                            <div id="previewLabel" class="text-lg font-extrabold text-slate-900 mb-1">
                                {{ $item->label ?: 'ชื่อรายการ' }}
                            </div>
                            <div id="previewTagline" class="text-xs text-slate-500 mb-4 min-h-[2em]">
                                {{ $item->tagline ?: 'คำโปรยจะแสดงตรงนี้' }}
                            </div>
                            <div class="flex items-baseline gap-1">
                                <span class="text-xs text-slate-400">฿</span>
                                <span id="previewPrice" class="text-2xl font-extrabold text-indigo-700">
                                    {{ number_format((float) ($item->price_thb ?? 0), 0) }}
                                </span>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500 mt-3 text-center">
                            อัปเดตทันทีเมื่อพิมพ์ในฟอร์ม
                        </p>
                    </div>
                </section>

                {{-- Sales metadata (edit only) --}}
                @if($item->exists)
                    <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                        <h2 class="font-bold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                            <i class="bi bi-graph-up-arrow text-emerald-500"></i> ยอดขาย
                        </h2>
                        <dl class="space-y-2.5 text-sm">
                            <div class="flex justify-between items-baseline border-b border-slate-100 dark:border-slate-700 pb-2">
                                <dt class="text-slate-500">รายได้รวม</dt>
                                <dd class="font-extrabold text-emerald-600 text-lg">
                                    {{ $money($purchaseStats->gross_revenue ?? 0) }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">ขายแล้ว</dt>
                                <dd class="font-bold">{{ number_format((int) $purchaseStats->total) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">เปิดใช้งาน</dt>
                                <dd class="text-emerald-600 font-semibold">
                                    {{ number_format((int) $purchaseStats->activated) }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">รอชำระ</dt>
                                <dd class="text-amber-600 font-semibold">
                                    {{ number_format((int) $purchaseStats->pending) }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">หมดอายุ</dt>
                                <dd class="text-slate-500">{{ number_format((int) $purchaseStats->expired) }}</dd>
                            </div>
                        </dl>
                    </section>

                    {{-- Metadata --}}
                    <section class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-5">
                        <h2 class="font-bold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                            <i class="bi bi-tags text-slate-500"></i> ข้อมูลทั่วไป
                        </h2>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-slate-500">Item ID</dt>
                                <dd class="font-mono text-slate-700 dark:text-slate-300">#{{ $item->id }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">สร้างเมื่อ</dt>
                                <dd class="text-slate-700 dark:text-slate-300 text-xs">{{ $item->created_at?->format('d/m/y H:i') }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500">แก้ไขล่าสุด</dt>
                                <dd class="text-slate-700 dark:text-slate-300 text-xs">{{ $item->updated_at?->diffForHumans() }}</dd>
                            </div>
                            @if($item->trashed())
                                <div class="flex justify-between pt-2 border-t border-slate-100 dark:border-slate-700">
                                    <dt class="text-rose-600">ลบเมื่อ</dt>
                                    <dd class="text-rose-600 text-xs">{{ $item->deleted_at->format('d/m/y H:i') }}</dd>
                                </div>
                            @endif
                        </dl>
                    </section>
                @endif

                {{-- Tip card (create only) --}}
                @if(!$item->exists)
                    <section class="rounded-2xl p-5 ring-1 ring-indigo-200/60 dark:ring-indigo-700/40"
                             style="background:{{ $toneBg['indigo'] }};">
                        <h2 class="font-bold text-indigo-800 dark:text-indigo-200 mb-2 flex items-center gap-2">
                            <i class="bi bi-lightbulb"></i> เคล็ดลับ
                        </h2>
                        <ul class="text-xs text-indigo-700 dark:text-indigo-200 space-y-1.5 list-disc ml-4">
                            <li><strong>SKU</strong> ตั้งครั้งเดียวห้ามเปลี่ยน — ใช้รูปแบบ <code class="font-mono">{category}.{variant}</code></li>
                            <li><strong>หมวด</strong> กำหนด activation handler — เปลี่ยนหลังสร้างไม่ได้</li>
                            <li><strong>ลำดับการแสดง</strong> ตั้งทีละ 10 (10, 20, 30…) — แทรกลำดับใหม่ได้ง่าย</li>
                            <li><strong>Badge</strong> ใส่เพื่อ highlight การ์ดในร้าน เช่น "ขายดี"</li>
                        </ul>
                    </section>
                @endif
            </aside>
        </div>

        {{-- Sticky save bar at bottom --}}
        <div class="sticky bottom-4 mt-6 bg-white dark:bg-slate-800 rounded-2xl shadow-xl ring-1 ring-slate-200 dark:ring-slate-700 p-4 flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs text-slate-500">
                @if($item->exists)
                    แก้ไขรายการ <strong class="text-slate-700 dark:text-slate-300">{{ $item->label }}</strong>
                @else
                    เพิ่มรายการใหม่ในหมวด <strong class="text-slate-700 dark:text-slate-300" id="hintCategory">{{ $shape['title'] }}</strong>
                @endif
            </div>
            <div class="flex gap-2 ml-auto">
                <a href="{{ route('admin.monetization.addons.index') }}"
                   class="px-5 py-2.5 rounded-xl ring-1 ring-slate-300 dark:ring-slate-600 text-slate-700 dark:text-slate-300 font-semibold text-sm transition hover:bg-slate-50 dark:hover:bg-slate-700">
                    ยกเลิก
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm transition shadow-md">
                    <i class="bi bi-check-lg"></i>
                    {{ $item->exists ? 'บันทึกการเปลี่ยนแปลง' : 'สร้าง Addon' }}
                </button>
            </div>
        </div>
    </form>
</div>

<script>
/**
 * (1) Show only the cat-block matching the selected category.
 * (2) Wire live preview updates from the form fields. We listen for
 *     a custom event so each input field can dispatch independently.
 */
(function() {
    const categorySelect = document.getElementById('category');
    const blocks = document.querySelectorAll('.cat-block');

    function refreshBlocks() {
        const cur = categorySelect.value;
        blocks.forEach(b => {
            b.style.display = (b.dataset.cat === cur) ? '' : 'none';
        });
    }
    categorySelect.addEventListener('change', refreshBlocks);
    refreshBlocks();

    // Live preview wiring — listen for input → update preview pieces
    const preview = {
        label:    document.getElementById('previewLabel'),
        tagline:  document.getElementById('previewTagline'),
        price:    document.getElementById('previewPrice'),
        badge:    document.getElementById('previewBadge'),
    };

    window.addEventListener('addon-preview-update', (e) => {
        const { field, value } = e.detail;
        if (field === 'label')   preview.label.textContent = value || 'ชื่อรายการ';
        if (field === 'tagline') preview.tagline.textContent = value || 'คำโปรยจะแสดงตรงนี้';
        if (field === 'price_thb') {
            preview.price.textContent = new Intl.NumberFormat('en-US').format(value || 0);
        }
        if (field === 'badge') {
            if (value && value.trim() !== '') {
                preview.badge.textContent = value;
                preview.badge.classList.remove('hidden');
            } else {
                preview.badge.classList.add('hidden');
            }
        }
    });
})();
</script>
@endsection

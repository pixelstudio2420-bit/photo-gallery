@extends('layouts.admin')

@section('title', $isCreate ? 'เพิ่มเมนูใหม่' : 'แก้ไขเมนู — ' . $item->label)

@section('content')
<div class="max-w-3xl mx-auto" x-data="{
    label:       @js(old('label', $item->label)),
    icon:        @js(old('icon', $item->icon ?? '')),
    location:    @js(old('location', $item->location)),
    audience:    @js(old('audience', $item->audience)),
    cta_style:   @js(old('cta_style', $item->cta_style)),
    badge_text:  @js(old('badge_text', $item->badge_text ?? '')),
    badge_color: @js(old('badge_color', $item->badge_color ?? '')),
}">

    <div class="flex items-center gap-2 mb-5">
        <a href="{{ route('admin.navigation.index') }}" class="text-slate-400 hover:text-slate-700 dark:hover:text-white">
            <i class="bi bi-arrow-left text-lg"></i>
        </a>
        <h4 class="font-bold tracking-tight">
            {{ $isCreate ? 'เพิ่มเมนูใหม่' : 'แก้ไขเมนู' }}
        </h4>
    </div>

    @if($errors->any())
        <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-900 text-sm px-4 py-3 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300">
            @foreach($errors->all() as $err)<div>· {{ $err }}</div>@endforeach
        </div>
    @endif

    <form method="POST"
          action="{{ $isCreate ? route('admin.navigation.store') : route('admin.navigation.update', $item) }}">
        @csrf
        @if(!$isCreate) @method('PUT') @endif

        {{-- Live preview ─────────────────────────────────────────── --}}
        <div class="mb-5 rounded-2xl bg-slate-900 p-5 border border-slate-700">
            <div class="text-[10px] uppercase tracking-wider text-slate-500 mb-2">ตัวอย่าง — แสดงผลใน Navbar</div>
            <a class="inline-flex items-center px-3 py-2 rounded-lg text-sm transition"
               :class="{
                   'font-semibold text-white bg-blue-500/30 hover:bg-blue-500/40': cta_style === 'primary',
                   'font-semibold text-amber-300 hover:text-amber-200': cta_style === 'accent',
                   'font-medium text-white/70 hover:text-white': cta_style === 'default',
               }"
               href="#">
                <template x-if="icon">
                    <i class="bi mr-1" :class="'bi-' + icon"></i>
                </template>
                <span x-text="label || '(ใส่ชื่อเมนู)'"></span>
                <template x-if="badge_text">
                    <span class="ml-1 text-[9px] px-1 py-0.5 rounded font-bold"
                          :class="{
                              'bg-amber-500/20 text-amber-300':   badge_color === 'amber',
                              'bg-rose-500/20 text-rose-300':     badge_color === 'rose',
                              'bg-emerald-500/20 text-emerald-300': badge_color === 'emerald',
                              'bg-indigo-500/20 text-indigo-300': badge_color === 'indigo',
                              'bg-slate-500/20 text-slate-300':   !badge_color || badge_color === 'slate',
                          }"
                          x-text="badge_text"></span>
                </template>
            </a>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-white/10 p-5 space-y-4">

            {{-- Label + URL --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        ชื่อที่แสดง <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="label" x-model="label" required maxlength="80"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                    <p class="text-[10px] text-slate-500 mt-1">ภาษาไทยหรืออังกฤษ — ความยาวไม่เกิน 80 ตัว</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        URL ที่ลิงก์ไป <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="url" required maxlength="500"
                           value="{{ old('url', $item->url) }}"
                           placeholder="/events หรือ https://example.com"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm font-mono">
                    <p class="text-[10px] text-slate-500 mt-1">เริ่มด้วย <code>/</code> สำหรับ path ภายใน หรือ <code>https://</code> สำหรับลิงก์ภายนอก</p>
                </div>
            </div>

            {{-- Icon (with picker hint) --}}
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Icon (Bootstrap Icons)
                </label>
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-white/5 flex items-center justify-center text-xl text-slate-600 dark:text-slate-300">
                        <template x-if="icon"><i class="bi" :class="'bi-' + icon"></i></template>
                        <template x-if="!icon"><i class="bi bi-link-45deg text-slate-300"></i></template>
                    </span>
                    <input type="text" name="icon" x-model="icon" maxlength="60"
                           placeholder="house-door, calendar-event, tag-fill ..."
                           class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm font-mono">
                </div>
                <p class="text-[10px] text-slate-500 mt-1">
                    เลือกชื่อจาก <a href="https://icons.getbootstrap.com" target="_blank" class="underline text-indigo-500">icons.getbootstrap.com</a>
                    — ใส่เฉพาะส่วนหลัง <code>bi-</code> (เช่น <code>house-door</code> ไม่ต้อง <code>bi-house-door</code>)
                </p>
            </div>

            {{-- Location + Audience --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        ตำแหน่งแสดงผล <span class="text-rose-500">*</span>
                    </label>
                    <select name="location" x-model="location" required
                            class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                        @foreach($locations as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        ผู้เห็น <span class="text-rose-500">*</span>
                    </label>
                    <select name="audience" x-model="audience" required
                            class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                        @foreach($audiences as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- CTA style + Sort order --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        รูปแบบปุ่ม
                    </label>
                    <select name="cta_style" x-model="cta_style"
                            class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                        @foreach($ctaStyles as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        ลำดับ (เลขน้อย = ขึ้นก่อน)
                    </label>
                    <input type="number" name="sort_order" min="0" max="9999"
                           value="{{ old('sort_order', $item->sort_order) }}"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                    <p class="text-[10px] text-slate-500 mt-1">หรือเรียงด้วยการลากในหน้ารายการ</p>
                </div>
            </div>

            {{-- Badge --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Badge (ไม่บังคับ)
                    </label>
                    <input type="text" name="badge_text" x-model="badge_text" maxlength="20"
                           placeholder="NEW, ใหม่, HOT, -50%"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        สี Badge
                    </label>
                    <select name="badge_color" x-model="badge_color"
                            class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                        <option value="">— ไม่ใส่ —</option>
                        @foreach($badgeColors as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Visibility regex (advanced) --}}
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    ซ่อนเมื่ออยู่ในหน้าที่ตรงกับ pattern (advanced)
                </label>
                <input type="text" name="visibility_route_pattern" maxlength="200"
                       value="{{ old('visibility_route_pattern', $item->visibility_route_pattern) }}"
                       placeholder="เช่น ^admin\.  หรือ  ^photographer\."
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-sm font-mono">
                <p class="text-[10px] text-slate-500 mt-1">เว้นว่างไว้ถ้าไม่ใช้ — ระบบจะแสดงเมนูในทุกหน้า</p>
            </div>

            {{-- Toggles --}}
            <div class="flex items-center gap-6 pt-2">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1"
                           {{ old('is_active', $item->is_active) ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-300">
                    <span class="text-sm">เปิดใช้งาน (is_active)</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="open_in_new_tab" value="1"
                           {{ old('open_in_new_tab', $item->open_in_new_tab) ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-300">
                    <span class="text-sm">เปิดในแท็บใหม่ (target=_blank)</span>
                </label>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 mt-5">
            <a href="{{ route('admin.navigation.index') }}"
               class="text-sm px-4 py-2 rounded-lg border border-slate-300 dark:border-white/10 hover:bg-slate-50 dark:hover:bg-white/5 transition">
                ยกเลิก
            </a>
            <button type="submit"
                    class="text-sm font-semibold px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition">
                <i class="bi bi-save mr-1"></i>{{ $isCreate ? 'สร้างเมนู' : 'บันทึกการแก้ไข' }}
            </button>
        </div>
    </form>
</div>
@endsection

@extends('layouts.admin')
@section('title', 'สร้างแผนสมัครสมาชิกใหม่')

@php
  // Same feature taxonomy as the edit view — admin can pre-select AI/LINE/
  // platform feature flags right at creation time so they don't have to
  // round-trip through the edit screen for a basic plan.
  $aiFeatures       = collect($allFeatures)->filter(fn ($v) => $v[1] === 'ai');
  $lineFeatures     = collect($allFeatures)->filter(fn ($v) => $v[1] === 'line');
  $platformFeatures = collect($allFeatures)->filter(fn ($v) => in_array($v[1], ['workflow','branding','platform']));
@endphp

@section('content')
<div class="space-y-5 pb-32 lg:pb-24"
     x-data="{
       code: @js(old('code', '')),
       name: @js(old('name', '')),
       priceMo: @js((float) old('price_thb', 0)),
       priceYr: @js((float) old('price_annual_thb', 0)),
       storageGb: @js((float) old('storage_gb', 0)),
       commission: @js((float) old('commission_pct', 0)),
       seats: @js((int) old('max_team_seats', 1)),
       aiCredits: @js((int) old('monthly_ai_credits', 0)),
       isPublic: @js((bool) old('is_public', true)),
       isActive: @js((bool) old('is_active', true)),
       dirty: false,
     }">

  {{-- ── HEADER ───────────────────────────────────────────────────── --}}
  <div class="rounded-2xl border border-gray-100 dark:border-white/5 bg-gradient-to-br from-indigo-50 via-white to-purple-50 dark:from-indigo-500/10 dark:via-slate-800 dark:to-purple-500/10 px-6 py-5">
    <nav class="text-xs text-gray-500 dark:text-gray-400 mb-1.5 flex items-center gap-1.5">
      <a href="{{ route('admin.subscriptions.index') }}" class="hover:text-indigo-600 hover:underline">Subscriptions</a>
      <i class="bi bi-chevron-right text-[10px]"></i>
      <a href="{{ route('admin.subscriptions.plans') }}" class="hover:text-indigo-600 hover:underline">Plans</a>
      <i class="bi bi-chevron-right text-[10px]"></i>
      <span class="text-gray-700 dark:text-gray-300 font-medium">Create</span>
    </nav>
    <div class="flex items-start gap-4 flex-wrap">
      <div class="shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center shadow-md">
        <i class="bi bi-plus-lg text-xl"></i>
      </div>
      <div class="flex-1 min-w-[260px]">
        <h1 class="text-xl md:text-2xl font-bold tracking-tight text-slate-800 dark:text-white">
          สร้างแผนสมัครสมาชิกใหม่
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
          กรอกฟิลด์หลัก — รายละเอียดเพิ่มเติม (สี / badge / AI features / bullets) แก้ไขได้หลังสร้าง
        </p>
      </div>
      <a href="{{ route('admin.subscriptions.plans') }}"
         class="px-3.5 py-2 rounded-lg border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 text-sm font-medium bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-white/5 transition">
        <i class="bi bi-arrow-left mr-1"></i>กลับ
      </a>
    </div>
  </div>

  {{-- ── ALERTS ──────────────────────────────────────────────────── --}}
  @if($errors->any())
    <div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-900 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-200 text-sm px-4 py-3">
      <div class="flex items-start gap-2">
        <i class="bi bi-exclamation-triangle-fill mt-0.5"></i>
        <div>
          <div class="font-semibold mb-1">มีข้อผิดพลาด {{ count($errors->all()) }} จุด</div>
          <ul class="list-disc list-inside space-y-0.5 text-xs">
            @foreach($errors->all() as $err) <li>{{ $err }}</li> @endforeach
          </ul>
        </div>
      </div>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.subscriptions.plans.store') }}"
        class="grid grid-cols-1 lg:grid-cols-3 gap-5"
        @submit="dirty=false">
    @csrf

    {{-- LEFT — form (2/3) ─────────────────────────────────────────── --}}
    <div class="lg:col-span-2 space-y-5">

      {{-- 1. Identity ─────────────────────────────────────────────── --}}
      <section class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
        <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-white/5">
          <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center">
            <i class="bi bi-card-heading text-indigo-600 dark:text-indigo-400"></i>
          </div>
          <div>
            <h2 class="font-semibold text-slate-800 dark:text-gray-100">ตัวตนของแผน</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">รหัสและชื่อ — รหัสจะใช้เป็น URL slug ตลอด ไม่สามารถแก้หลังสร้างได้</p>
          </div>
        </header>

        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
          {{-- Code (slug) --}}
          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">
              รหัสแผน (slug) <span class="text-rose-500">*</span>
              <span class="text-gray-400 font-normal block text-[11px] mt-0.5">a-z, 0-9, _, - เท่านั้น · ไม่สามารถแก้หลังสร้างได้</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-mono">/</span>
              <input type="text" name="code" required maxlength="32"
                     pattern="[a-z0-9_-]+"
                     value="{{ old('code') }}"
                     x-on:input="code = $event.target.value.toLowerCase().replace(/[^a-z0-9_-]/g,''); $event.target.value = code; dirty=true"
                     placeholder="เช่น business, lite, agency"
                     class="w-full pl-7 pr-3.5 py-2.5 rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] font-mono focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
            </div>
          </div>

          {{-- Name --}}
          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">ชื่อแผน <span class="text-rose-500">*</span></label>
            <input type="text" name="name" required maxlength="120"
                   value="{{ old('name') }}"
                   x-on:input="name = $event.target.value; dirty=true"
                   placeholder="เช่น Business, Studio Pro"
                   class="w-full rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
          </div>

          {{-- Tagline --}}
          <div class="md:col-span-2">
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">Tagline
              <span class="text-gray-400 font-normal">— สโลแกนสั้น 1 บรรทัด (เลือกใส่ก็ได้)</span>
            </label>
            <input type="text" name="tagline" maxlength="200"
                   value="{{ old('tagline') }}"
                   placeholder="เช่น เริ่มขายภายในวันนี้"
                   class="w-full rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
          </div>

          {{-- Description --}}
          <div class="md:col-span-2">
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">คำอธิบาย</label>
            <textarea name="description" rows="3" maxlength="600"
                      placeholder="รายละเอียดสั้น ๆ ที่อธิบายว่าใครควรใช้แผนนี้"
                      class="w-full rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">{{ old('description') }}</textarea>
          </div>

          {{-- Badge --}}
          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">
              Badge <span class="text-gray-400 font-normal">(เช่น "ขายดี")</span>
            </label>
            <input type="text" name="badge" maxlength="30"
                   value="{{ old('badge') }}"
                   class="w-full rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
          </div>

          {{-- Color --}}
          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">สีประจำแผน</label>
            <input type="color" name="color_hex"
                   value="{{ old('color_hex', '#6366f1') }}"
                   class="h-10 w-full rounded-lg border border-gray-200 dark:border-white/10 cursor-pointer">
          </div>
        </div>
      </section>

      {{-- 2. Pricing & Limits ───────────────────────────────────── --}}
      <section class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
        <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-white/5">
          <div class="w-9 h-9 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center">
            <i class="bi bi-coin text-emerald-600 dark:text-emerald-400"></i>
          </div>
          <div>
            <h2 class="font-semibold text-slate-800 dark:text-gray-100">ราคา & ขีดจำกัด</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">ค่าธรรมเนียมและโควต้า — แก้ไขได้ตลอดหลังสร้าง</p>
          </div>
        </header>

        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          {{-- Monthly --}}
          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">
              ราคา/เดือน <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">฿</span>
              <input type="number" step="0.01" min="0" name="price_thb" required
                     value="{{ old('price_thb', 0) }}"
                     x-on:input="priceMo = parseFloat($event.target.value) || 0"
                     class="w-full pl-8 pr-3.5 py-2.5 rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
            </div>
          </div>

          {{-- Annual --}}
          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">
              ราคา/ปี <span class="text-gray-400 font-normal">(เลือกใส่ก็ได้)</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">฿</span>
              <input type="number" step="0.01" min="0" name="price_annual_thb"
                     value="{{ old('price_annual_thb') }}"
                     x-on:input="priceYr = parseFloat($event.target.value) || 0"
                     class="w-full pl-8 pr-3.5 py-2.5 rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
            </div>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1"
               x-show="priceMo > 0 && priceYr > 0"
               x-text="(() => { const saved = (priceMo*12 - priceYr); const pct = priceMo > 0 ? saved/(priceMo*12)*100 : 0; return saved > 0 ? `ประหยัด ฿${saved.toLocaleString()} (${pct.toFixed(0)}%)` : '' })()"></p>
          </div>

          {{-- Commission --}}
          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">
              ค่าคอมมิชชั่น <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <input type="number" step="0.01" min="0" max="100" name="commission_pct" required
                     value="{{ old('commission_pct', 0) }}"
                     x-on:input="commission = parseFloat($event.target.value) || 0"
                     class="w-full pl-3.5 pr-9 py-2.5 rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
            </div>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">platform เก็บจากยอดขาย</p>
          </div>

          {{-- Storage --}}
          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">
              พื้นที่จัดเก็บ <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <input type="number" step="0.1" min="0" name="storage_gb" required
                     value="{{ old('storage_gb', 0) }}"
                     x-on:input="storageGb = parseFloat($event.target.value) || 0"
                     class="w-full pl-3.5 pr-10 py-2.5 rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">GB</span>
            </div>
          </div>

          {{-- Concurrent events --}}
          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">
              อีเวนต์พร้อมกัน <span class="text-gray-400 font-normal">(เว้นว่าง = ∞)</span>
            </label>
            <input type="number" min="0" name="max_concurrent_events"
                   value="{{ old('max_concurrent_events') }}"
                   class="w-full rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
          </div>

          {{-- Team seats --}}
          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">
              ที่นั่งทีม <span class="text-rose-500">*</span> <span class="text-gray-400 font-normal">(รวมเจ้าของ)</span>
            </label>
            <input type="number" min="1" name="max_team_seats" required
                   value="{{ old('max_team_seats', 1) }}"
                   x-on:input="seats = parseInt($event.target.value) || 1"
                   class="w-full rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
          </div>

          {{-- AI credits --}}
          <div class="md:col-span-3">
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">
              เครดิต AI / เดือน <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <i class="bi bi-stars absolute left-3 top-1/2 -translate-y-1/2 text-violet-400"></i>
              <input type="number" min="0" name="monthly_ai_credits" required
                     value="{{ old('monthly_ai_credits', 0) }}"
                     x-on:input="aiCredits = parseInt($event.target.value) || 0"
                     class="w-full pl-10 pr-3.5 py-2.5 rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
            </div>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">โควต้ารวมสำหรับ face search / index / compare / detect / OCR ทุก resource</p>
          </div>
        </div>
      </section>

      {{-- 3. Visibility ─────────────────────────────────────────── --}}
      <section class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
        <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-white/5">
          <div class="w-9 h-9 rounded-lg bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center">
            <i class="bi bi-eye text-amber-600 dark:text-amber-400"></i>
          </div>
          <div>
            <h2 class="font-semibold text-slate-800 dark:text-gray-100">การแสดงผล</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">แผนพร้อมขายตอนนี้ไหม</p>
          </div>
        </header>

        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-3">
          {{-- is_active --}}
          <label class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg border border-gray-200 dark:border-white/10 cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5 transition">
            <button type="button"
                    x-on:click="isActive = !isActive"
                    :class="isActive ? 'bg-emerald-600' : 'bg-gray-300 dark:bg-white/10'"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-200">
              <span :class="isActive ? 'translate-x-5' : 'translate-x-0'"
                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition translate-y-0.5 ml-0.5"></span>
            </button>
            <input type="hidden" name="is_active" :value="isActive ? 1 : 0">
            <div class="text-sm">
              <div class="font-medium text-slate-800 dark:text-gray-100">เปิดใช้งาน (is_active)</div>
              <div class="text-xs text-gray-500 dark:text-gray-400">ปิด = ไม่ให้สมัคร แต่คงผู้สมัครเดิมไว้</div>
            </div>
          </label>

          {{-- is_public --}}
          <label class="flex items-center gap-3 px-3.5 py-2.5 rounded-lg border border-gray-200 dark:border-white/10 cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5 transition">
            <button type="button"
                    x-on:click="isPublic = !isPublic"
                    :class="isPublic ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-white/10'"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-200">
              <span :class="isPublic ? 'translate-x-5' : 'translate-x-0'"
                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition translate-y-0.5 ml-0.5"></span>
            </button>
            <input type="hidden" name="is_public" :value="isPublic ? 1 : 0">
            <div class="text-sm">
              <div class="font-medium text-slate-800 dark:text-gray-100">แสดงในหน้าเลือกแพ็กเกจ (is_public)</div>
              <div class="text-xs text-gray-500 dark:text-gray-400">ปิด = ซ่อนจากหน้า /photographer/subscription/plans</div>
            </div>
          </label>
        </div>
      </section>

      {{-- 4. AI Features (optional, can be edited later) ────────── --}}
      <section class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
        <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-white/5">
          <div class="w-9 h-9 rounded-lg bg-violet-50 dark:bg-violet-500/10 flex items-center justify-center">
            <i class="bi bi-stars text-violet-600 dark:text-violet-400"></i>
          </div>
          <div>
            <h2 class="font-semibold text-slate-800 dark:text-gray-100">ฟีเจอร์ที่ปลดล็อก</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">เลือกได้เลย หรือข้ามไปแก้ในหน้า Edit หลังสร้าง</p>
          </div>
        </header>

        @php $current = old('ai_features', []); @endphp

        @if($aiFeatures->count())
          <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-2">
            @foreach($aiFeatures as $key => [$label, $group])
              <label class="group flex items-center gap-2.5 px-3.5 py-2.5 rounded-lg border border-gray-200 dark:border-white/10 hover:border-violet-300 dark:hover:border-violet-500/30 hover:bg-violet-50/50 dark:hover:bg-violet-500/5 has-[:checked]:bg-violet-50 dark:has-[:checked]:bg-violet-500/10 has-[:checked]:border-violet-300 dark:has-[:checked]:border-violet-500/30 cursor-pointer transition-colors">
                <input type="checkbox" name="ai_features[]" value="{{ $key }}"
                       @checked(in_array($key, $current, true))
                       class="rounded text-violet-600 focus:ring-violet-300">
                <i class="bi bi-magic text-violet-500"></i>
                <span class="text-sm text-slate-700 dark:text-gray-200">{{ $label }}</span>
              </label>
            @endforeach
          </div>
        @endif

        @if($lineFeatures->count())
          <div class="px-5 py-3 bg-emerald-50 dark:bg-emerald-500/5 border-t border-emerald-100 dark:border-emerald-500/20 flex items-center gap-2">
            <i class="bi bi-line text-[#06C755]"></i>
            <h3 class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">LINE Integration</h3>
          </div>
          <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-2">
            @foreach($lineFeatures as $key => [$label, $group])
              <label class="group flex items-center gap-2.5 px-3.5 py-2.5 rounded-lg border border-gray-200 dark:border-white/10 hover:border-emerald-300 dark:hover:border-emerald-500/30 has-[:checked]:bg-emerald-50 dark:has-[:checked]:bg-emerald-500/10 has-[:checked]:border-emerald-300 dark:has-[:checked]:border-emerald-500/30 cursor-pointer transition-colors">
                <input type="checkbox" name="ai_features[]" value="{{ $key }}"
                       @checked(in_array($key, $current, true))
                       class="rounded text-emerald-600 focus:ring-emerald-300">
                <i class="bi bi-line text-[#06C755]"></i>
                <span class="text-sm text-slate-700 dark:text-gray-200">{{ $label }}</span>
              </label>
            @endforeach
          </div>
        @endif

        @if($platformFeatures->count())
          <div class="px-5 py-3 bg-gray-50 dark:bg-slate-900/40 border-t border-gray-100 dark:border-white/5 flex items-center gap-2">
            <i class="bi bi-toggles text-gray-400"></i>
            <h3 class="text-sm font-semibold text-slate-700 dark:text-gray-300">Workflow / Branding / Platform</h3>
          </div>
          <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-2">
            @foreach($platformFeatures as $key => [$label, $group])
              <label class="group flex items-center gap-2.5 px-3.5 py-2.5 rounded-lg border border-gray-200 dark:border-white/10 hover:border-indigo-300 dark:hover:border-indigo-500/30 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-500/10 has-[:checked]:border-indigo-300 dark:has-[:checked]:border-indigo-500/30 cursor-pointer transition-colors">
                <input type="checkbox" name="ai_features[]" value="{{ $key }}"
                       @checked(in_array($key, $current, true))
                       class="rounded text-indigo-600 focus:ring-indigo-300">
                <i class="bi bi-{{ $group === 'branding' ? 'palette' : ($group === 'platform' ? 'plug' : 'diagram-3') }} text-indigo-500"></i>
                <span class="text-sm text-slate-700 dark:text-gray-200">{{ $label }}</span>
              </label>
            @endforeach
          </div>
        @endif
      </section>

      {{-- 5. Bullets (optional) ──────────────────────────────── --}}
      <section class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
        <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-white/5">
          <div class="w-9 h-9 rounded-lg bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center">
            <i class="bi bi-list-check text-amber-600 dark:text-amber-400"></i>
          </div>
          <div>
            <h2 class="font-semibold text-slate-800 dark:text-gray-100">Bullets ในการ์ดแพ็กเกจ</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">บรรทัดละ 1 รายการ — เลือกใส่ก็ได้</p>
          </div>
        </header>
        <div class="p-5">
          <textarea name="features_json" rows="6"
                    placeholder="เช่น&#10;100 GB พื้นที่จัดเก็บ&#10;0% ค่าคอมมิชชั่น&#10;5,000 AI Credits ต่อเดือน"
                    class="w-full rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-sm font-mono px-3.5 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">{{ old('features_json') }}</textarea>
        </div>
      </section>
    </div>

    {{-- RIGHT — Live Summary + Submit (1/3 sticky) ──────────────── --}}
    <aside class="lg:col-span-1">
      <div class="lg:sticky lg:top-20 space-y-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
          <header class="px-5 py-4 border-b border-gray-100 dark:border-white/5 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-500/10 dark:to-purple-500/10">
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">สรุป</div>
            <div class="font-bold text-slate-800 dark:text-white text-lg mt-1" x-text="name || 'ชื่อแผน'"></div>
            <div class="text-xs font-mono text-gray-500 dark:text-gray-400 mt-0.5" x-text="code ? '/' + code : '/รหัส'"></div>
          </header>
          <div class="p-5 space-y-3 text-sm">
            <div class="flex items-baseline gap-1">
              <span class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">ราคา/เดือน:</span>
              <span class="ml-auto font-bold" x-text="'฿' + (priceMo || 0).toLocaleString()"></span>
            </div>
            <div class="flex items-baseline gap-1" x-show="priceYr > 0">
              <span class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">ราคา/ปี:</span>
              <span class="ml-auto font-bold" x-text="'฿' + (priceYr || 0).toLocaleString()"></span>
            </div>
            <div class="flex items-baseline gap-1">
              <span class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">พื้นที่:</span>
              <span class="ml-auto font-bold" x-text="(storageGb || 0).toLocaleString() + ' GB'"></span>
            </div>
            <div class="flex items-baseline gap-1">
              <span class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">ค่าคอม:</span>
              <span class="ml-auto font-bold" x-text="(commission || 0) + '%'"></span>
            </div>
            <div class="flex items-baseline gap-1">
              <span class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">ทีม:</span>
              <span class="ml-auto font-bold" x-text="(seats || 1) + ' ที่นั่ง'"></span>
            </div>
            <div class="flex items-baseline gap-1">
              <span class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">AI/เดือน:</span>
              <span class="ml-auto font-bold" x-text="(aiCredits || 0).toLocaleString()"></span>
            </div>
            <div class="border-t border-gray-100 dark:border-white/5 pt-3 mt-3 flex flex-col gap-1.5 text-xs">
              <div :class="isActive ? 'text-emerald-600 dark:text-emerald-300' : 'text-gray-400'">
                <i class="bi" :class="isActive ? 'bi-check-circle-fill' : 'bi-circle'"></i>
                เปิดใช้งาน
              </div>
              <div :class="isPublic ? 'text-emerald-600 dark:text-emerald-300' : 'text-gray-400'">
                <i class="bi" :class="isPublic ? 'bi-check-circle-fill' : 'bi-circle'"></i>
                แสดงในหน้าเลือกแพ็กเกจ
              </div>
            </div>
          </div>
          <div class="px-5 py-4 border-t border-gray-100 dark:border-white/5 space-y-2">
            <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl font-semibold text-white bg-gradient-to-br from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 shadow-md shadow-indigo-500/30 transition">
              <i class="bi bi-plus-lg"></i> สร้างแผน
            </button>
            <a href="{{ route('admin.subscriptions.plans') }}"
               class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-200 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-white/5 transition">
              ยกเลิก
            </a>
          </div>
        </div>
      </div>
    </aside>
  </form>
</div>
@endsection

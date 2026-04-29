@extends('layouts.admin')
@section('title', 'แก้ไขแผน — '.$plan->name)

@php
  $aiFeatures       = collect($allFeatures)->filter(fn ($v) => $v[1] === 'ai');
  $platformFeatures = collect($allFeatures)->filter(fn ($v) => in_array($v[1], ['workflow','branding','platform']));
  $currentFeatures  = old('ai_features', $plan->ai_features ?? []);
  $accent           = old('color_hex', $plan->color_hex ?? '#6366f1');
  $bullets          = old('features_json', is_array($plan->features_json) ? implode("\n", $plan->features_json) : '');
  // Storage in GB for the input + the live preview ribbon
  $storageGbInit    = old('storage_gb', round($plan->storage_bytes / (1024 ** 3), 1));
  // Curated colour palette — hand-picked to look good on dark + light
  // headers and to match Tailwind's tonal scale.
  $palette = [
    '#6366f1', '#8b5cf6', '#d946ef', '#ec4899',
    '#f43f5e', '#f97316', '#eab308', '#10b981',
    '#06b6d4', '#3b82f6', '#0ea5e9', '#64748b',
  ];
@endphp

@section('content')
{{-- Alpine root drives:
     • live preview (header + price + bullets reflect form state)
     • colour palette swap
     • bullet add/remove
     • dirty-state warning before leaving
   No external JS — Alpine + plain Tailwind only.            --}}
<div x-data="planEditor({
        accent:    @js($accent),
        name:      @js(old('name', $plan->name)),
        tagline:   @js(old('tagline', $plan->tagline)),
        badge:     @js(old('badge', $plan->badge)),
        priceMo:   @js((float) old('price_thb', $plan->price_thb)),
        priceYr:   @js((float) old('price_annual_thb', $plan->price_annual_thb)),
        storageGb: @js((float) $storageGbInit),
        commission:@js((float) old('commission_pct', $plan->commission_pct)),
        seats:     @js((int)   old('max_team_seats', $plan->max_team_seats)),
        aiCredits: @js((int)   old('monthly_ai_credits', $plan->monthly_ai_credits)),
        bullets:   @js($bullets),
        isPublic:  @js((bool)  old('is_public', $plan->is_public)),
     })"
     class="space-y-5 pb-32 lg:pb-24">

  {{-- ── HERO HEADER ───────────────────────────────────────────────── --}}
  <div class="relative overflow-hidden rounded-2xl border border-gray-100 dark:border-white/5"
       :style="`background: linear-gradient(120deg, ${accent}15 0%, ${accent}08 50%, transparent 100%);`">
    {{-- decorative accent blob --}}
    <div class="absolute -top-12 -right-12 w-56 h-56 rounded-full opacity-20 blur-3xl pointer-events-none"
         :style="`background: ${accent};`"></div>

    <div class="relative px-6 py-5 flex items-start gap-4">
      {{-- accent square --}}
      <div class="shrink-0 w-12 h-12 rounded-xl flex items-center justify-center shadow-md"
           :style="`background: ${accent};`">
        <i class="bi bi-pencil-square text-white text-xl"></i>
      </div>

      <div class="min-w-0 flex-1">
        <nav class="text-xs text-gray-500 dark:text-gray-400 mb-1.5 flex items-center gap-1.5">
          <a href="{{ route('admin.subscriptions.index') }}" class="hover:text-indigo-600 hover:underline">Subscriptions</a>
          <i class="bi bi-chevron-right text-[10px]"></i>
          <a href="{{ route('admin.subscriptions.plans') }}" class="hover:text-indigo-600 hover:underline">Plans</a>
          <i class="bi bi-chevron-right text-[10px]"></i>
          <span class="text-gray-700 dark:text-gray-300 font-medium">Edit</span>
        </nav>
        <h1 class="text-xl md:text-2xl font-bold tracking-tight text-slate-800 dark:text-white flex items-center gap-2 flex-wrap">
          <span x-text="name || @js($plan->name)"></span>
          <span class="text-xs font-mono px-2 py-0.5 rounded-md bg-gray-100 dark:bg-white/5 text-gray-500 dark:text-gray-400">{{ $plan->code }}</span>
          @if($plan->is_active)
            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300 inline-flex items-center gap-1">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>ACTIVE
            </span>
          @else
            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 dark:bg-white/5 dark:text-gray-400">INACTIVE</span>
          @endif
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1" x-text="tagline || 'ยังไม่ได้ตั้ง tagline'"></p>
      </div>

      <div class="hidden md:flex items-center gap-2 shrink-0">
        <a href="{{ route('admin.subscriptions.plans') }}"
           class="px-3.5 py-2 rounded-lg border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 text-sm font-medium bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-white/5 transition">
          <i class="bi bi-arrow-left mr-1"></i>กลับ
        </a>
      </div>
    </div>
  </div>

  {{-- ── ALERTS ──────────────────────────────────────────────────────── --}}
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

  {{-- ── FORM + LIVE PREVIEW (2-col grid on desktop) ─────────────────── --}}
  <form method="POST" action="{{ route('admin.subscriptions.plans.update', $plan->id) }}"
        class="grid grid-cols-1 lg:grid-cols-3 gap-5"
        @submit="dirty=false">
    @csrf
    @method('PUT')

    {{-- LEFT — form sections (2/3) ───────────────────────────────── --}}
    <div class="lg:col-span-2 space-y-5">

      {{-- 1. Identity ────────────────────────────────────────────── --}}
      <section class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
        <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-white/5">
          <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center">
            <i class="bi bi-card-heading text-indigo-600 dark:text-indigo-400"></i>
          </div>
          <div>
            <h2 class="font-semibold text-slate-800 dark:text-gray-100">ตัวตนของแผน</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">ชื่อ / คำอธิบาย / สี — สิ่งที่ลูกค้าเห็นในหน้าเลือกแพ็กเกจ</p>
          </div>
        </header>

        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2">
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">ชื่อแผน <span class="text-rose-500">*</span></label>
            <input type="text" name="name" required maxlength="120"
                   value="{{ old('name', $plan->name) }}"
                   x-on:input="name = $event.target.value; dirty=true"
                   class="w-full rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
          </div>

          <div class="md:col-span-2">
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">Tagline
              <span class="text-gray-400 font-normal">— สโลแกนสั้น 1 บรรทัด</span>
            </label>
            <input type="text" name="tagline" maxlength="200"
                   value="{{ old('tagline', $plan->tagline) }}"
                   x-on:input="tagline = $event.target.value; dirty=true"
                   placeholder="เช่น เริ่มต้นด้วย LINE Login"
                   class="w-full rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
          </div>

          <div class="md:col-span-2">
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">คำอธิบาย</label>
            <textarea name="description" rows="3" maxlength="600"
                      x-on:input="dirty=true"
                      class="w-full rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">{{ old('description', $plan->description) }}</textarea>
          </div>

          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">Badge
              <span class="text-gray-400 font-normal">(เช่น "ขายดีที่สุด")</span>
            </label>
            <input type="text" name="badge" maxlength="30"
                   value="{{ old('badge', $plan->badge) }}"
                   x-on:input="badge = $event.target.value; dirty=true"
                   class="w-full rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
          </div>

          {{-- Public toggle (modern switch instead of plain checkbox) --}}
          <div class="flex items-end">
            <label class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg border border-gray-200 dark:border-white/10 cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5 transition">
              <button type="button"
                      x-on:click="isPublic = !isPublic; dirty=true"
                      :class="isPublic ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-white/10'"
                      class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-200">
                <span :class="isPublic ? 'translate-x-5' : 'translate-x-0'"
                      class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition translate-y-0.5 ml-0.5"></span>
              </button>
              <input type="hidden" name="is_public" :value="isPublic ? 1 : 0">
              <div class="text-sm">
                <div class="font-medium text-slate-800 dark:text-gray-100">แสดงในหน้าเลือกแพ็กเกจ</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">ปิด = ซ่อนจากผู้ใช้ใหม่ (รักษาผู้สมัครเดิม)</div>
              </div>
            </label>
          </div>
        </div>

        {{-- Colour palette --}}
        <div class="px-5 pb-5">
          <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2 block">สีประจำแผน</label>
          <div class="flex items-center gap-3 flex-wrap">
            <input type="color" name="color_hex"
                   :value="accent"
                   x-on:input="accent = $event.target.value; dirty=true"
                   class="h-10 w-14 rounded-lg border border-gray-200 dark:border-white/10 cursor-pointer">
            <div class="flex items-center gap-1.5 flex-wrap">
              @foreach($palette as $hex)
                <button type="button"
                        x-on:click="accent = @js($hex); dirty=true"
                        :class="accent.toLowerCase() === @js(strtolower($hex)) ? 'ring-2 ring-offset-2 ring-indigo-400 dark:ring-offset-slate-800' : 'hover:scale-110'"
                        class="w-8 h-8 rounded-lg shadow-sm transition transform"
                        style="background: {{ $hex }};"
                        title="{{ $hex }}"></button>
              @endforeach
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 font-mono ml-1" x-text="accent.toUpperCase()"></div>
          </div>
        </div>
      </section>

      {{-- 2. Pricing & Limits ─────────────────────────────────────── --}}
      <section class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
        <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-white/5">
          <div class="w-9 h-9 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center">
            <i class="bi bi-coin text-emerald-600 dark:text-emerald-400"></i>
          </div>
          <div>
            <h2 class="font-semibold text-slate-800 dark:text-gray-100">ราคา & ขีดจำกัด</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">ค่าธรรมเนียมรายเดือน/รายปี และโควต้าการใช้งาน</p>
          </div>
        </header>

        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          {{-- Monthly price --}}
          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">ราคา/เดือน <span class="text-rose-500">*</span></label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">฿</span>
              <input type="number" step="0.01" min="0" name="price_thb" required
                     value="{{ old('price_thb', $plan->price_thb) }}"
                     x-on:input="priceMo = parseFloat($event.target.value) || 0; dirty=true"
                     class="w-full pl-8 pr-3.5 py-2.5 rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
            </div>
          </div>
          {{-- Annual price --}}
          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">ราคา/ปี
              <span class="text-gray-400 font-normal">(เว้นว่าง = ไม่มี)</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">฿</span>
              <input type="number" step="0.01" min="0" name="price_annual_thb"
                     value="{{ old('price_annual_thb', $plan->price_annual_thb) }}"
                     x-on:input="priceYr = parseFloat($event.target.value) || 0; dirty=true"
                     class="w-full pl-8 pr-3.5 py-2.5 rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
            </div>
            {{-- Annual savings hint, computed live --}}
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1"
               x-show="priceMo > 0 && priceYr > 0"
               x-text="(() => { const saved = (priceMo*12 - priceYr); const pct = saved/(priceMo*12)*100; return saved > 0 ? `ประหยัด ฿${saved.toLocaleString()} (${pct.toFixed(0)}%) ต่อปี` : '' })()"></p>
          </div>
          {{-- Commission --}}
          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">ค่าคอมมิชชั่น <span class="text-rose-500">*</span></label>
            <div class="relative">
              <input type="number" step="0.01" min="0" max="100" name="commission_pct" required
                     value="{{ old('commission_pct', $plan->commission_pct) }}"
                     x-on:input="commission = parseFloat($event.target.value) || 0; dirty=true"
                     class="w-full pl-3.5 pr-9 py-2.5 rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
            </div>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">platform เก็บจากยอดขาย</p>
          </div>

          {{-- Storage --}}
          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">พื้นที่จัดเก็บ <span class="text-rose-500">*</span></label>
            <div class="relative">
              <input type="number" step="0.1" min="0" name="storage_gb" required
                     value="{{ $storageGbInit }}"
                     x-on:input="storageGb = parseFloat($event.target.value) || 0; dirty=true"
                     class="w-full pl-3.5 pr-10 py-2.5 rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">GB</span>
            </div>
          </div>
          {{-- Concurrent events --}}
          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">อีเวนต์พร้อมกัน
              <span class="text-gray-400 font-normal">(เว้นว่าง = ไม่จำกัด)</span>
            </label>
            <input type="number" min="0" name="max_concurrent_events"
                   value="{{ old('max_concurrent_events', $plan->max_concurrent_events) }}"
                   x-on:input="dirty=true"
                   class="w-full rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
          </div>
          {{-- Team seats --}}
          <div>
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">ที่นั่งทีม <span class="text-rose-500">*</span>
              <span class="text-gray-400 font-normal">(รวมเจ้าของ)</span>
            </label>
            <input type="number" min="1" name="max_team_seats" required
                   value="{{ old('max_team_seats', $plan->max_team_seats) }}"
                   x-on:input="seats = parseInt($event.target.value) || 0; dirty=true"
                   class="w-full rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] px-3.5 py-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
          </div>

          {{-- AI credits — full width --}}
          <div class="md:col-span-3">
            <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">เครดิต AI / เดือน <span class="text-rose-500">*</span></label>
            <div class="relative">
              <i class="bi bi-stars absolute left-3 top-1/2 -translate-y-1/2 text-violet-400"></i>
              <input type="number" min="0" name="monthly_ai_credits" required
                     value="{{ old('monthly_ai_credits', $plan->monthly_ai_credits) }}"
                     x-on:input="aiCredits = parseInt($event.target.value) || 0; dirty=true"
                     class="w-full pl-10 pr-3.5 py-2.5 rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-[15px] focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
            </div>
          </div>
        </div>
      </section>

      {{-- 3. AI Features (visual chip grid) ───────────────────────── --}}
      <section class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
        <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-white/5">
          <div class="w-9 h-9 rounded-lg bg-violet-50 dark:bg-violet-500/10 flex items-center justify-center">
            <i class="bi bi-stars text-violet-600 dark:text-violet-400"></i>
          </div>
          <div>
            <h2 class="font-semibold text-slate-800 dark:text-gray-100">AI Features</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">ฟีเจอร์ AI ที่ปลดล็อกเมื่อสมัครแผนนี้</p>
          </div>
          <span class="ml-auto text-xs text-gray-500 dark:text-gray-400 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-white/5">
            {{ $aiFeatures->count() }} ทั้งหมด
          </span>
        </header>

        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-2">
          @foreach($aiFeatures as $key => [$label, $group])
            <label class="group flex items-center gap-2.5 px-3.5 py-2.5 rounded-lg border border-gray-200 dark:border-white/10 hover:border-violet-300 dark:hover:border-violet-500/30 hover:bg-violet-50/50 dark:hover:bg-violet-500/5 has-[:checked]:bg-violet-50 dark:has-[:checked]:bg-violet-500/10 has-[:checked]:border-violet-300 dark:has-[:checked]:border-violet-500/30 cursor-pointer transition-colors">
              <input type="checkbox" name="ai_features[]" value="{{ $key }}"
                     @checked(in_array($key, $currentFeatures, true))
                     x-on:change="dirty=true"
                     class="rounded text-violet-600 focus:ring-violet-300">
              <i class="bi bi-magic text-violet-500"></i>
              <span class="text-sm text-slate-700 dark:text-gray-200">{{ $label }}</span>
              <code class="ml-auto hidden xl:inline-block text-[10px] font-mono text-gray-400 dark:text-gray-500 group-hover:text-gray-500 truncate max-w-[120px]" title="{{ $key }}">{{ $key }}</code>
            </label>
          @endforeach
        </div>

        <div class="px-5 py-3 bg-gray-50 dark:bg-slate-900/40 border-t border-gray-100 dark:border-white/5 flex items-center gap-2">
          <i class="bi bi-toggles text-gray-400"></i>
          <h3 class="text-sm font-semibold text-slate-700 dark:text-gray-300">Workflow / Branding / Platform</h3>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-2">
          @foreach($platformFeatures as $key => [$label, $group])
            <label class="group flex items-center gap-2.5 px-3.5 py-2.5 rounded-lg border border-gray-200 dark:border-white/10 hover:border-indigo-300 dark:hover:border-indigo-500/30 hover:bg-indigo-50/50 dark:hover:bg-indigo-500/5 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-500/10 has-[:checked]:border-indigo-300 dark:has-[:checked]:border-indigo-500/30 cursor-pointer transition-colors">
              <input type="checkbox" name="ai_features[]" value="{{ $key }}"
                     @checked(in_array($key, $currentFeatures, true))
                     x-on:change="dirty=true"
                     class="rounded text-indigo-600 focus:ring-indigo-300">
              <i class="bi bi-{{ $group === 'branding' ? 'palette' : ($group === 'platform' ? 'plug' : 'diagram-3') }} text-indigo-500"></i>
              <span class="text-sm text-slate-700 dark:text-gray-200">{{ $label }}</span>
              <span class="ml-auto hidden md:inline-block text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500 shrink-0">{{ $group }}</span>
            </label>
          @endforeach
        </div>
      </section>

      {{-- 4. Marketing bullets (live editor) ──────────────────────── --}}
      <section class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
        <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100 dark:border-white/5">
          <div class="w-9 h-9 rounded-lg bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center">
            <i class="bi bi-list-check text-amber-600 dark:text-amber-400"></i>
          </div>
          <div>
            <h2 class="font-semibold text-slate-800 dark:text-gray-100">Bullet Points สำหรับหน้าเลือกแพ็กเกจ</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">บรรทัดละ 1 รายการ — แสดงในการ์ดแพ็กเกจ</p>
          </div>
        </header>

        <div class="p-5">
          <textarea name="features_json" rows="8"
                    x-on:input="bullets = $event.target.value; dirty=true"
                    placeholder="เช่น
• พื้นที่ 2 GB
• Login ผ่าน LINE 1 คลิก
• AI Preview 10 รูป/วัน"
                    class="w-full rounded-lg border-gray-200 dark:border-white/10 dark:bg-slate-900 text-sm font-mono px-3.5 py-3 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">{{ $bullets }}</textarea>
          <div class="mt-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
            <span>
              <i class="bi bi-info-circle mr-1"></i>
              <span x-text="bullets.split('\n').filter(l => l.trim()).length"></span> รายการ — preview ทางขวา
            </span>
            <span class="text-[10px] uppercase tracking-wider text-gray-400">live preview →</span>
          </div>
        </div>
      </section>
    </div>

    {{-- RIGHT — live preview (1/3, sticky) ──────────────────────── --}}
    <aside class="lg:col-span-1">
      <div class="lg:sticky lg:top-20 space-y-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5 overflow-hidden">
          <header class="px-4 py-2.5 bg-gray-50 dark:bg-slate-900/40 border-b border-gray-100 dark:border-white/5 flex items-center gap-2">
            <i class="bi bi-eye text-gray-400"></i>
            <span class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Live Preview</span>
            <span class="ml-auto text-[10px] text-gray-400">how customers see it</span>
          </header>

          <div class="p-5">
            {{-- Plan card preview — mirrors the public marketing card --}}
            <div class="rounded-2xl border border-gray-200 dark:border-white/10 overflow-hidden shadow-sm">
              {{-- Top accent ribbon --}}
              <div class="h-1.5" :style="`background: ${accent};`"></div>
              <div class="p-5">
                <div class="flex items-start justify-between gap-2 mb-2">
                  <div class="min-w-0">
                    <h3 class="font-bold text-slate-800 dark:text-white truncate" x-text="name || 'ชื่อแผน'"
                        :style="`color: ${accent};`"></h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2"
                       x-text="tagline || ''"></p>
                  </div>
                  <span x-show="badge"
                        :style="`background: ${accent}20; color: ${accent};`"
                        class="text-[10px] font-semibold px-2 py-1 rounded-md whitespace-nowrap shrink-0"
                        x-text="badge"></span>
                </div>

                <div class="my-4">
                  <div class="flex items-baseline gap-1">
                    <span class="text-3xl font-bold text-slate-800 dark:text-white"
                          x-text="priceMo > 0 ? '฿' + priceMo.toLocaleString() : 'ฟรี'"></span>
                    <span class="text-xs text-gray-500 dark:text-gray-400" x-show="priceMo > 0">/ เดือน</span>
                  </div>
                  <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5"
                       x-show="priceYr > 0">
                    หรือ ฿<span x-text="priceYr.toLocaleString()"></span> / ปี
                  </div>
                </div>

                <ul class="space-y-1.5 text-sm">
                  <template x-for="line in bullets.split('\n').filter(l => l.trim()).slice(0, 6)" :key="line">
                    <li class="flex items-start gap-2 text-slate-700 dark:text-gray-200">
                      <i class="bi bi-check-circle-fill text-xs mt-1 shrink-0"
                         :style="`color: ${accent};`"></i>
                      <span x-text="line.replace(/^[•·\-\*]\s*/, '')"></span>
                    </li>
                  </template>
                  <li x-show="bullets.split('\n').filter(l => l.trim()).length === 0"
                      class="text-xs text-gray-400 italic">— ยังไม่มี bullet points —</li>
                </ul>

                <button type="button" disabled
                        :style="`background: ${accent};`"
                        class="mt-5 w-full py-2.5 rounded-lg text-white text-sm font-semibold opacity-90 cursor-default">
                  เลือกแผนนี้
                </button>
              </div>
            </div>

            {{-- Quick-stats summary --}}
            <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
              <div class="px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-900/40">
                <div class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400">พื้นที่</div>
                <div class="font-semibold text-slate-800 dark:text-gray-100" x-text="storageGb + ' GB'"></div>
              </div>
              <div class="px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-900/40">
                <div class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400">ค่าคอม</div>
                <div class="font-semibold text-slate-800 dark:text-gray-100" x-text="commission + '%'"></div>
              </div>
              <div class="px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-900/40">
                <div class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400">ที่นั่งทีม</div>
                <div class="font-semibold text-slate-800 dark:text-gray-100" x-text="seats"></div>
              </div>
              <div class="px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-900/40">
                <div class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400">AI credits</div>
                <div class="font-semibold text-slate-800 dark:text-gray-100">
                  <span x-text="aiCredits.toLocaleString()"></span> / เดือน
                </div>
              </div>
            </div>

            {{-- Visibility status --}}
            <div class="mt-3 px-3 py-2 rounded-lg flex items-center gap-2 text-xs"
                 :class="isPublic ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'">
              <i :class="isPublic ? 'bi-eye-fill' : 'bi-eye-slash-fill'" class="bi"></i>
              <span x-text="isPublic ? 'แสดงในหน้าเลือกแพ็กเกจ' : 'ซ่อนจากผู้ใช้ใหม่'"></span>
            </div>
          </div>
        </div>

        {{-- Effective-immediately reminder --}}
        <div class="rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 px-4 py-3 text-xs text-amber-800 dark:text-amber-200">
          <div class="flex items-start gap-2">
            <i class="bi bi-info-circle-fill mt-0.5 shrink-0"></i>
            <div>
              <div class="font-semibold mb-0.5">การเปลี่ยนแปลงมีผลทันที</div>
              <div>สมาชิกทุกคนของแผนนี้จะเห็นการเปลี่ยนแปลงในการล็อกอินครั้งถัดไป — ไม่มี cache ที่ต้อง bust</div>
            </div>
          </div>
        </div>
      </div>
    </aside>

    {{-- ── STICKY SAVE BAR ─────────────────────────────────────── --}}
    {{-- Full-width strip pinned to the bottom of the viewport on
         every breakpoint. The previous "floating right card" was
         too narrow on a 1440px desktop with the admin sidebar
         taking 280px — the cancel button + dirty indicator got
         clipped. A full-width strip is the most reliable shape. --}}
    <div class="fixed bottom-0 left-0 right-0 z-30
                bg-white/95 dark:bg-slate-800/95 backdrop-blur-md
                border-t border-gray-200 dark:border-white/10
                shadow-[0_-4px_20px_rgba(0,0,0,0.06)] dark:shadow-[0_-4px_20px_rgba(0,0,0,0.4)]">
      <div class="max-w-[1600px] mx-auto px-4 lg:px-6 py-3 flex items-center gap-3">
        {{-- dirty status — visible from sm up --}}
        <div class="hidden sm:flex items-center gap-2 text-sm">
          <span class="w-2.5 h-2.5 rounded-full"
                :class="dirty ? 'bg-amber-400 animate-pulse' : 'bg-emerald-400'"></span>
          <span class="text-gray-600 dark:text-gray-300"
                x-text="dirty ? 'มีการเปลี่ยนแปลงที่ยังไม่ได้บันทึก' : 'บันทึกแล้วทั้งหมด'"></span>
        </div>
        <div class="flex-1"></div>
        <a href="{{ route('admin.subscriptions.plans') }}"
           class="px-5 py-2.5 rounded-lg border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 text-[15px] font-medium hover:bg-gray-50 dark:hover:bg-white/5 transition">
          ยกเลิก
        </a>
        <button type="submit"
                class="px-6 py-2.5 rounded-lg text-white text-[15px] font-semibold shadow-md hover:shadow-lg active:shadow-sm transition flex items-center gap-2 min-w-[140px] justify-center"
                :style="`background: ${accent};`">
          <i class="bi bi-check-lg text-lg"></i>บันทึก
        </button>
      </div>
    </div>
  </form>
</div>

<script>
function planEditor(initial) {
  return {
    ...initial,
    dirty: false,
    init() {
      // Warn before leaving when there are unsaved changes.
      window.addEventListener('beforeunload', (e) => {
        if (this.dirty) {
          e.preventDefault();
          e.returnValue = '';
        }
      });
    },
  };
}
</script>
@endsection

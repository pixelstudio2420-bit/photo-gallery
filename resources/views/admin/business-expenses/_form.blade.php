{{-- Shared form partial for create + edit --}}
@php
  $e           = $expense ?? null;
  $allocatedTo = old('allocated_to', $e->allocated_to ?? []);
  $weights     = old('allocation_weights', $e->allocation_weights ?? []);
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
  {{-- Name --}}
  <div class="md:col-span-2">
    <label class="block text-sm font-medium text-slate-700 dark:text-gray-200 mb-1">ชื่อรายการ <span class="text-rose-500">*</span></label>
    <input type="text" name="name" value="{{ old('name', $e->name ?? '') }}" required
           class="w-full border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm"
           placeholder="เช่น Cloudflare R2 Storage, Google Drive API">
  </div>

  {{-- Category --}}
  <div>
    <label class="block text-sm font-medium text-slate-700 dark:text-gray-200 mb-1">หมวดหมู่ <span class="text-rose-500">*</span></label>
    <select name="category" required
            class="w-full border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm">
      @foreach($categories as $key => $label)
        <option value="{{ $key }}" {{ old('category', $e->category ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
  </div>

  {{-- Provider --}}
  <div>
    <label class="block text-sm font-medium text-slate-700 dark:text-gray-200 mb-1">ผู้ให้บริการ / Provider</label>
    <input type="text" name="provider" value="{{ old('provider', $e->provider ?? '') }}"
           class="w-full border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm"
           placeholder="เช่น Cloudflare, Google, AWS">
  </div>

  {{-- Description --}}
  <div class="md:col-span-2">
    <label class="block text-sm font-medium text-slate-700 dark:text-gray-200 mb-1">รายละเอียด</label>
    <textarea name="description" rows="2"
              class="w-full border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm">{{ old('description', $e->description ?? '') }}</textarea>
  </div>

  {{-- Billing cycle --}}
  <div>
    <label class="block text-sm font-medium text-slate-700 dark:text-gray-200 mb-1">รอบบิล</label>
    <select name="billing_cycle" class="w-full border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm">
      @foreach($cycles as $key => $label)
        <option value="{{ $key }}" {{ old('billing_cycle', $e->billing_cycle ?? 'monthly') === $key ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
  </div>

  {{-- Amount + currency --}}
  <div>
    <label class="block text-sm font-medium text-slate-700 dark:text-gray-200 mb-1">มูลค่า (THB ต่อรอบ) <span class="text-rose-500">*</span></label>
    <div class="flex gap-2">
      <input type="number" name="amount" step="0.01" min="0" value="{{ old('amount', $e->amount ?? 0) }}" required
             class="flex-1 border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm">
      <input type="text" name="currency" value="{{ old('currency', $e->currency ?? 'THB') }}" maxlength="3"
             class="w-20 border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm text-center">
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">ค่านี้จะถูกใช้คำนวณ — แปลงเป็น THB ด้วยเรทที่ล็อก</p>
  </div>

  {{-- Original currency (for USD invoices, etc.) --}}
  <div>
    <label class="block text-sm font-medium text-slate-700 dark:text-gray-200 mb-1">มูลค่าต้นทาง (ถ้าไม่ใช่ THB)</label>
    <div class="flex gap-2">
      <input type="number" name="original_amount" step="0.01" min="0" value="{{ old('original_amount', $e->original_amount ?? '') }}"
             class="flex-1 border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm"
             placeholder="เช่น 25">
      <input type="text" name="original_currency" value="{{ old('original_currency', $e->original_currency ?? '') }}" maxlength="3"
             class="w-20 border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm text-center"
             placeholder="USD">
    </div>
  </div>
  <div>
    <label class="block text-sm font-medium text-slate-700 dark:text-gray-200 mb-1">เรทที่ล็อก (ต่อ 1 หน่วยต้นทาง → THB)</label>
    <input type="number" name="exchange_rate" step="0.0001" min="0" value="{{ old('exchange_rate', $e->exchange_rate ?? '') }}"
           class="w-full border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm"
           placeholder="เช่น 35.50">
  </div>

  {{-- Usage-based fields --}}
  <div>
    <label class="block text-sm font-medium text-slate-700 dark:text-gray-200 mb-1">ต้นทุนต่อหน่วย (usage_based)</label>
    <input type="number" name="unit_cost" step="0.000001" min="0" value="{{ old('unit_cost', $e->unit_cost ?? '') }}"
           class="w-full border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm"
           placeholder="เช่น 0.015 (ต่อ GB)">
  </div>
  <div>
    <label class="block text-sm font-medium text-slate-700 dark:text-gray-200 mb-1">หน่วยนับ (GB, request, user)</label>
    <input type="text" name="usage_unit" value="{{ old('usage_unit', $e->usage_unit ?? '') }}"
           class="w-full border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm">
  </div>
  <div>
    <label class="block text-sm font-medium text-slate-700 dark:text-gray-200 mb-1">ปริมาณใช้งานประมาณ/เดือน</label>
    <input type="number" name="estimated_monthly_usage" step="0.01" min="0"
           value="{{ old('estimated_monthly_usage', $e->estimated_monthly_usage ?? '') }}"
           class="w-full border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm">
  </div>

  {{-- Dates --}}
  <div>
    <label class="block text-sm font-medium text-slate-700 dark:text-gray-200 mb-1">เริ่มใช้งาน</label>
    <input type="date" name="start_date" value="{{ old('start_date', optional($e->start_date ?? null)->format('Y-m-d')) }}"
           class="w-full border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm">
  </div>
  <div>
    <label class="block text-sm font-medium text-slate-700 dark:text-gray-200 mb-1">สิ้นสุด (ถ้ามี)</label>
    <input type="date" name="end_date" value="{{ old('end_date', optional($e->end_date ?? null)->format('Y-m-d')) }}"
           class="w-full border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm">
  </div>

  {{-- Allocation --}}
  <div class="md:col-span-2">
    <label class="block text-sm font-medium text-slate-700 dark:text-gray-200 mb-1">แบกรับโดยบริการ</label>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">เลือกบริการที่ค่าใช้จ่ายนี้ช่วยให้บริการได้ — ระบบจะแบ่งเฉลี่ยตามที่เลือก</p>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
      @foreach($services as $key => $label)
        <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-white/5 cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-700">
          <input type="checkbox" name="allocated_to[]" value="{{ $key }}"
                 {{ in_array($key, $allocatedTo) ? 'checked' : '' }}
                 class="rounded border-gray-300 text-indigo-600">
          <span class="text-xs text-slate-700 dark:text-gray-200">{{ $label }}</span>
          <input type="number" name="allocation_weights[{{ $key }}]" step="0.1" min="0"
                 value="{{ old("allocation_weights.$key", $weights[$key] ?? '') }}"
                 placeholder="weight"
                 class="ml-auto w-16 border border-gray-200 dark:border-white/5 dark:bg-slate-800 dark:text-gray-100 rounded px-1 py-0.5 text-xs">
        </label>
      @endforeach
    </div>
  </div>

  {{-- Flags --}}
  <div class="md:col-span-2 flex flex-wrap gap-4">
    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-gray-200">
      <input type="checkbox" name="is_active" value="1"
             {{ old('is_active', $e?->is_active ?? true) ? 'checked' : '' }}>
      เปิดใช้งาน (Active)
    </label>
    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-gray-200">
      <input type="checkbox" name="is_critical" value="1"
             {{ old('is_critical', $e->is_critical ?? false) ? 'checked' : '' }}>
      วิกฤต (Critical) — แจ้งเตือนถ้าขาด
    </label>
  </div>

  {{-- Notes --}}
  <div class="md:col-span-2">
    <label class="block text-sm font-medium text-slate-700 dark:text-gray-200 mb-1">บันทึก (Notes)</label>
    <textarea name="notes" rows="2"
              class="w-full border border-gray-200 dark:border-white/5 dark:bg-slate-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm">{{ old('notes', $e->notes ?? '') }}</textarea>
  </div>
</div>

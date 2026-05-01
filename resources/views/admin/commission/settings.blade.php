@extends('layouts.admin')

@section('title', 'ตั้งค่าคอมมิชชั่น')

@section('content')
<div class="flex items-center justify-between mb-6">
  <div>
    <h4 class="font-bold mb-1 text-xl tracking-tight">
      <i class="bi bi-gear mr-2 text-indigo-500"></i>ตั้งค่าคอมมิชชั่น
    </h4>
    <p class="text-gray-500 mb-0 text-sm">กำหนดอัตราค่าคอมมิชชั่นและเงื่อนไขสำหรับแพลตฟอร์ม</p>
  </div>
  <a href="{{ route('admin.commission.index') }}" class="inline-flex items-center px-4 py-1.5 bg-indigo-50 text-indigo-600 text-sm font-medium rounded-lg hover:bg-indigo-100 transition dark:bg-white/[0.06] dark:text-gray-300">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

@if(session('success'))
<div class="bg-emerald-50 text-emerald-700 rounded-lg p-4 text-sm mb-4 dark:bg-emerald-500/10 dark:text-emerald-400">
  <i class="bi bi-check-circle mr-1"></i> {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="bg-red-50 text-red-700 rounded-lg p-4 text-sm mb-4 dark:bg-red-500/10 dark:text-red-400">
  <i class="bi bi-exclamation-circle mr-1"></i>
  <ul class="list-disc list-inside">
    @foreach($errors->all() as $error)
      <li>{{ $error }}</li>
    @endforeach
  </ul>
</div>
@endif

<form method="POST" action="{{ route('admin.commission.settings.update') }}">
  @csrf

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Left Column: Forms --}}
    <div class="lg:col-span-2 space-y-6">

      {{-- Card 1: อัตรา Fallback (เมื่อช่างภาพไม่มีแผน) --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06] flex items-center justify-between">
          <h6 class="font-semibold text-sm"><i class="bi bi-percent mr-1 text-indigo-500"></i>อัตรา Fallback (เมื่อช่างภาพไม่มีแผน)</h6>
          <span class="text-[10px] px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full dark:bg-amber-500/15 dark:text-amber-400">
            <i class="bi bi-info-circle mr-0.5"></i>ใช้น้อย
          </span>
        </div>
        <div class="p-6" x-data="{ rate: {{ $settings['platform_commission'] ?? 30 }} }">
          {{-- ── แจ้งเตือนว่านี่ไม่ใช่ค่าจริงในการคำนวณรายได้แล้ว ── --}}
          <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-800 dark:bg-blue-500/10 dark:border-blue-500/30 dark:text-blue-300 leading-relaxed">
            <i class="bi bi-info-circle mr-1"></i>
            ตั้งแต่ 30/04/2026 ระบบใช้ <strong>ค่าคอมมิชชั่นจากแผนสมาชิก</strong> เป็นหลัก (Free 30% / Starter 5% / Pro+ 0%)
            ค่านี้จะถูกใช้เฉพาะกรณีที่ช่างภาพไม่ได้ถูกผูกกับแผนใดๆ เท่านั้น —
            <a href="{{ route('admin.subscriptions.plans') }}" class="underline font-medium hover:text-blue-900 dark:hover:text-blue-200">ไปจัดการแผนสมาชิก →</a>
          </div>

          <div class="space-y-1 mb-4">
            <label for="platform_commission" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">ค่าแพลตฟอร์ม Fallback (%)</label>
            <input type="number" name="platform_commission" id="platform_commission"
              class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/[0.1] dark:text-gray-100"
              value="{{ $settings['platform_commission'] ?? 30 }}"
              min="1" max="50" step="1"
              x-model.number="rate">
            <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">เปอร์เซ็นต์ที่แพลตฟอร์มเก็บ — ใช้เฉพาะกรณีบัญชี legacy ที่ยังไม่มี subscription_plan_code</p>
          </div>
          <div class="flex items-center gap-3 p-4 bg-indigo-50 rounded-xl dark:bg-indigo-500/10">
            <div class="w-10 h-10 rounded-lg bg-indigo-500/10 flex items-center justify-center dark:bg-indigo-500/20">
              <i class="bi bi-camera text-indigo-500"></i>
            </div>
            <div>
              <div class="text-sm text-gray-600 dark:text-gray-400">ช่างภาพได้รับ (fallback)</div>
              <div class="text-lg font-bold text-indigo-600 dark:text-indigo-400">
                <span x-text="Math.max(0, 100 - rate)"></span>%
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Card 2: ขอบเขตอัตรา --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-sliders mr-1 text-amber-500"></i>ขอบเขตอัตรา</h6>
        </div>
        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
            <div class="space-y-1">
              <label for="min_commission_rate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">อัตราต่ำสุด (ช่างภาพ)</label>
              <input type="number" name="min_commission_rate" id="min_commission_rate"
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/[0.1] dark:text-gray-100"
                value="{{ $settings['min_commission_rate'] ?? 50 }}"
                min="30" max="95" step="1">
            </div>
            <div class="space-y-1">
              <label for="max_commission_rate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">อัตราสูงสุด (ช่างภาพ)</label>
              <input type="number" name="max_commission_rate" id="max_commission_rate"
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/[0.1] dark:text-gray-100"
                value="{{ $settings['max_commission_rate'] ?? 90 }}"
                min="50" max="99" step="1">
            </div>
          </div>
          <p class="text-gray-500 dark:text-gray-400 text-xs">กำหนดขอบเขตอัตราคอมมิชชั่นที่สามารถตั้งให้ช่างภาพได้</p>
        </div>
      </div>

      {{-- Card 3: ระดับอัตโนมัติ --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-arrow-repeat mr-1 text-emerald-500"></i>ระดับอัตโนมัติ</h6>
        </div>
        <div class="p-6">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm font-medium text-gray-700 dark:text-gray-300">เปิดใช้ระดับอัตโนมัติ</div>
              <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">ระบบจะปรับอัตราคอมมิชชั่นตามระดับรายได้โดยอัตโนมัติ</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="hidden" name="auto_tier_enabled" value="0">
              <input type="checkbox" name="auto_tier_enabled" value="1" class="sr-only peer"
                {{ ($settings['auto_tier_enabled'] ?? false) ? 'checked' : '' }}>
              <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600 dark:bg-slate-600"></div>
            </label>
          </div>
        </div>
      </div>

      {{-- ═══ Event Pricing Settings ═══ --}}
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/[0.06] p-6">
        <h6 class="font-semibold mb-4">
          <i class="bi bi-tag mr-1.5 text-violet-500"></i>ตั้งค่าราคาอีเวนต์
        </h6>

        {{-- Min Event Price --}}
        <div class="mb-4">
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
            ราคาขั้นต่ำต่อภาพ (บาท) <span class="text-red-500">*</span>
          </label>
          <input type="number" name="min_event_price"
                 class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 @error('min_event_price') border-red-400 ring-1 ring-red-400 @enderror"
                 value="{{ old('min_event_price', $settings['min_event_price'] ?? 100) }}"
                 min="100" max="10000" step="1" required>
          @error('min_event_price')
            <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}</p>
          @enderror
          <p class="text-gray-500 dark:text-gray-400 text-xs mt-1.5">
            <i class="bi bi-info-circle mr-1"></i>
            ระบบบังคับขั้นต่ำที่ <strong>100 บาท/ภาพ</strong> — ช่างภาพจะไม่สามารถตั้งราคาต่ำกว่านี้ได้ (ยกเว้นอีเวนต์ฟรี)
          </p>
        </div>

        {{-- Allow Free Events --}}
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
            อนุญาตอีเวนต์ฟรี
          </label>
          <div class="flex items-center gap-3">
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="hidden" name="allow_free_events" value="0">
              <input type="checkbox" name="allow_free_events" value="1"
                     class="sr-only peer"
                     {{ ($settings['allow_free_events'] ?? '1') == '1' ? 'checked' : '' }}>
              <div class="w-11 h-6 bg-gray-200 peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500 dark:bg-slate-600"></div>
            </label>
            <span class="text-sm text-gray-600 dark:text-gray-400">
              {{ ($settings['allow_free_events'] ?? '1') == '1' ? 'เปิดใช้งาน — ช่างภาพสามารถสร้างอีเวนต์ฟรีได้' : 'ปิดใช้งาน — อีเวนต์ทุกรายการต้องมีค่าบริการ' }}
            </span>
          </div>
        </div>
      </div>

      {{-- Submit --}}
      <div class="flex items-center gap-3">
        <button type="submit" class="inline-flex items-center bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl font-medium px-6 py-2.5 transition hover:from-indigo-600 hover:to-indigo-700 shadow-sm">
          <i class="bi bi-check-lg mr-1"></i> บันทึกการตั้งค่า
        </button>
        <a href="{{ route('admin.commission.index') }}" class="inline-flex items-center px-5 py-2.5 border border-gray-200 text-gray-700 rounded-xl text-sm font-medium hover:bg-gray-50 transition dark:border-white/[0.1] dark:text-gray-300 dark:hover:bg-white/[0.04]">
          ยกเลิก
        </a>
      </div>
    </div>

    {{-- Right Column: Info / Tips --}}
    <div class="space-y-6">

      {{-- Current Tiers --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06] flex items-center justify-between">
          <h6 class="font-semibold text-sm"><i class="bi bi-award mr-1 text-amber-500"></i>ระดับปัจจุบัน</h6>
          <a href="{{ route('admin.commission.tiers') }}" class="text-xs text-indigo-500 hover:text-indigo-700">จัดการ &rarr;</a>
        </div>
        <div class="p-6">
          @if($tiers->count())
          <div class="space-y-2.5">
            @foreach($tiers as $tier)
            <div class="flex items-center gap-3 p-2.5 rounded-lg border border-gray-100 dark:border-white/[0.06]">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center text-sm" style="background:{{ $tier->color }}15;color:{{ $tier->color }};">
                <i class="bi {{ $tier->icon }}"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-medium text-sm">{{ $tier->name }}</div>
                <div class="text-xs text-gray-400">&ge; ฿{{ number_format($tier->min_revenue, 0) }}</div>
              </div>
              <span class="font-bold text-sm" style="color:{{ $tier->color }};">{{ number_format($tier->commission_rate, 0) }}%</span>
            </div>
            @endforeach
          </div>
          @else
          <div class="text-center py-4">
            <i class="bi bi-award text-2xl text-gray-300 dark:text-gray-600"></i>
            <p class="text-gray-400 dark:text-gray-500 text-sm mt-1">ยังไม่มีระดับ</p>
          </div>
          @endif
        </div>
      </div>

      {{-- Tips --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-lightbulb mr-1 text-amber-500"></i>คำแนะนำ</h6>
        </div>
        <div class="p-6">
          <div class="space-y-4 text-sm">
            <div class="flex gap-3">
              <div class="w-6 h-6 rounded-full bg-indigo-500/10 flex items-center justify-center shrink-0 mt-0.5">
                <i class="bi bi-1-circle text-indigo-500 text-xs"></i>
              </div>
              <div>
                <div class="font-medium text-gray-700 dark:text-gray-300">ค่าแพลตฟอร์ม</div>
                <p class="text-gray-500 dark:text-gray-400 text-xs mt-0.5">ค่าเริ่มต้นที่แนะนำคือ 20-30% สำหรับแพลตฟอร์มใหม่</p>
              </div>
            </div>
            <div class="flex gap-3">
              <div class="w-6 h-6 rounded-full bg-amber-500/10 flex items-center justify-center shrink-0 mt-0.5">
                <i class="bi bi-2-circle text-amber-500 text-xs"></i>
              </div>
              <div>
                <div class="font-medium text-gray-700 dark:text-gray-300">ขอบเขตอัตรา</div>
                <p class="text-gray-500 dark:text-gray-400 text-xs mt-0.5">ควรกำหนดขอบเขตเพื่อป้องกันการตั้งอัตราที่ไม่เหมาะสม</p>
              </div>
            </div>
            <div class="flex gap-3">
              <div class="w-6 h-6 rounded-full bg-emerald-500/10 flex items-center justify-center shrink-0 mt-0.5">
                <i class="bi bi-3-circle text-emerald-500 text-xs"></i>
              </div>
              <div>
                <div class="font-medium text-gray-700 dark:text-gray-300">ระดับอัตโนมัติ</div>
                <p class="text-gray-500 dark:text-gray-400 text-xs mt-0.5">เมื่อเปิดใช้ ระบบจะปรับอัตราตามระดับรายได้ที่กำหนดไว้</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Quick Info — ลำดับการคำนวณค่าคอมมิชชั่นจริง --}}
      <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl p-5 text-white shadow-sm">
        <h6 class="font-semibold text-sm mb-2"><i class="bi bi-info-circle mr-1"></i>ลำดับการคำนวณ Commission</h6>
        <p class="text-white/80 text-xs leading-relaxed mb-3">
          ระบบจะหาอัตรา <strong>keep%</strong> (ช่างภาพได้รับ) โดยเริ่มจากแผนสมาชิก แล้วใช้ค่าที่ <strong>สูงที่สุด</strong> ระหว่างแผน, tier และ profile override:
        </p>
        <ol class="text-xs text-white/85 space-y-1.5 list-decimal list-inside leading-relaxed">
          <li><strong>แผนสมาชิก</strong> — Free=70%, Starter=95%, Pro+=100%</li>
          <li><strong>Tier ตาม lifetime revenue</strong> (เพิ่ม keep% ได้)</li>
          <li><strong>Profile override</strong> (admin ตั้ง VIP rate)</li>
          <li><strong>Fallback ในการ์ดข้างซ้าย</strong> — ใช้เฉพาะบัญชี legacy ที่ไม่มีแผน</li>
        </ol>
        <div class="mt-3 pt-3 border-t border-white/20 text-xs text-white/70 leading-relaxed">
          <i class="bi bi-arrow-right-circle mr-1"></i>
          Tier และ Profile override เพิ่ม keep% เท่านั้น — ไม่ลดลงต่ำกว่าแผน
        </div>
      </div>

    </div>
  </div>
</form>
@endsection

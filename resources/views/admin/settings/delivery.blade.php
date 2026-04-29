@extends('layouts.admin')

@section('title', 'ตั้งค่าการจัดส่งรูปภาพ')

{{-- =======================================================================
     PHOTO DELIVERY SETTINGS
     -------------------------------------------------------------------
     Controls how paid-order photos reach the buyer after payment approval:
       • Web   — classic download page (always on as safety net)
       • LINE  — push a Flex bubble with download button (requires OAuth)
       • Email — signed download link in email (best for bulk orders)
       • Auto  — service picks the best channel per order

     Admin can toggle channels on/off, set the auto-switch photo threshold
     to email, and pick the default method for new orders.
     ====================================================================== --}}

@push('styles')
<style>
  /* Re-use the toggle style from line.blade.php */
  .tw-switch { position: relative; display: inline-block; width: 2.75rem; height: 1.5rem; flex-shrink: 0; }
  .tw-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
  .tw-switch .slider {
    position: absolute; inset: 0;
    background: rgb(226 232 240);
    border-radius: 9999px;
    cursor: pointer;
    transition: background-color .2s;
  }
  .dark .tw-switch .slider { background: rgb(51 65 85); }
  .tw-switch .slider::before {
    content: ''; position: absolute;
    height: 1.125rem; width: 1.125rem;
    left: 3px; top: 3px;
    background: #fff;
    border-radius: 9999px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.25);
    transition: transform .2s;
  }
  .tw-switch input:checked + .slider { background: linear-gradient(135deg, #6366f1, #818cf8); }
  .tw-switch input:checked + .slider::before { transform: translateX(1.25rem); }
  .tw-switch input:focus-visible + .slider { box-shadow: 0 0 0 3px rgba(99,102,241,0.35); }
  .tw-switch input:disabled + .slider { opacity: 0.5; cursor: not-allowed; }
</style>
@endpush

@section('content')
<div class="max-w-5xl mx-auto pb-16">

  {{-- Page header --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h4 class="text-2xl font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-3 tracking-tight">
        <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl shadow-md shadow-indigo-500/30"
              style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
          <i class="bi bi-send-fill text-white text-xl"></i>
        </span>
        การจัดส่งรูปภาพ
      </h4>
      <p class="text-sm text-slate-500 dark:text-slate-400 ml-14">
        กำหนดช่องทางที่ลูกค้าจะได้รับรูปภาพหลังจากชำระเงินสำเร็จ
      </p>
    </div>
    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-1.5 px-3 py-2 text-sm rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:border-indigo-400 transition">
      <i class="bi bi-arrow-left"></i> กลับไปหน้าตั้งค่า
    </a>
  </div>

  {{-- Flash --}}
  @if(session('success'))
    <div class="mb-5 px-4 py-3 rounded-xl flex items-start gap-2
                bg-emerald-50 dark:bg-emerald-500/10
                border border-emerald-200 dark:border-emerald-500/30
                text-emerald-800 dark:text-emerald-300">
      <i class="bi bi-check-circle-fill mt-0.5"></i>
      <div class="flex-1 text-sm">{{ session('success') }}</div>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.settings.delivery.update') }}" class="space-y-5">
    @csrf

    {{-- Card 1: Enabled channels --}}
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 bg-gradient-to-r from-indigo-50/50 to-transparent dark:from-indigo-500/5">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
          <i class="bi bi-toggles text-indigo-500"></i> ช่องทางที่เปิดให้ใช้งาน
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          เลือกช่องทางที่ลูกค้าสามารถเลือกในหน้าชำระเงินได้
        </p>
      </div>

      <div class="p-5 space-y-3">
        {{-- Web (always on) --}}
        <div class="flex items-start gap-3 p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-white/5">
          <div class="flex items-center">
            <label class="tw-switch">
              <input type="checkbox" checked disabled>
              <span class="slider"></span>
            </label>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
              <i class="bi bi-globe2 text-indigo-500"></i> ดาวน์โหลดบนเว็บ
              <span class="text-[10px] px-1.5 py-0.5 rounded-md bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 font-normal">เปิดตลอด</span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              ลูกค้าได้รับลิงก์ดาวน์โหลดจากหน้าคำสั่งซื้อ ใช้เป็นตัวเลือกสำรองเสมอ ไม่สามารถปิดได้
            </p>
          </div>
        </div>

        {{-- LINE --}}
        <div class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 dark:border-white/5 hover:border-indigo-300 dark:hover:border-indigo-500/40 transition">
          <div class="flex items-center">
            <label class="tw-switch">
              <input type="checkbox" name="delivery_enabled_line"
                     @if(in_array('line', $settings['delivery_methods_enabled_list'], true)) checked @endif>
              <span class="slider"></span>
            </label>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
              <i class="bi bi-chat-dots-fill text-emerald-500"></i> ส่งผ่าน LINE
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              ส่ง Flex message พร้อมปุ่มดาวน์โหลดเข้า LINE ของลูกค้า
              (ต้องตั้งค่า LINE Messaging API และลูกค้าต้องเชื่อมบัญชี LINE ไว้แล้ว)
            </p>
          </div>
        </div>

        {{-- Email --}}
        <div class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 dark:border-white/5 hover:border-indigo-300 dark:hover:border-indigo-500/40 transition">
          <div class="flex items-center">
            <label class="tw-switch">
              <input type="checkbox" name="delivery_enabled_email"
                     @if(in_array('email', $settings['delivery_methods_enabled_list'], true)) checked @endif>
              <span class="slider"></span>
            </label>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
              <i class="bi bi-envelope-fill text-blue-500"></i> ส่งทางอีเมล
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              อีเมลพร้อมลิงก์ดาวน์โหลดที่ลงลายเซ็นแล้ว
              เหมาะกับออเดอร์ที่มีรูปภาพจำนวนมาก
            </p>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 2: Default method --}}
    <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 dark:border-white/5 bg-gradient-to-r from-purple-50/50 to-transparent dark:from-purple-500/5">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
          <i class="bi bi-magic text-purple-500"></i> ค่าเริ่มต้นและการเลือกอัตโนมัติ
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          ถ้าลูกค้าไม่ได้เลือกช่องทาง ระบบจะใช้ค่านี้เป็นตัวตั้งต้น
        </p>
      </div>

      <div class="p-5 space-y-5">
        {{-- Default method --}}
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
            วิธีเริ่มต้นสำหรับออเดอร์ใหม่
          </label>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            @foreach([
              ['auto', 'bi-magic',         'อัตโนมัติ', 'ระบบเลือกให้'],
              ['web',  'bi-globe2',        'เว็บ',      'ดาวน์โหลดเอง'],
              ['line', 'bi-chat-dots-fill','LINE',      'ส่งเข้าแชท'],
              ['email','bi-envelope-fill', 'อีเมล',     'ส่งลิงก์ทางอีเมล'],
            ] as [$val, $icon, $label, $hint])
              <label class="cursor-pointer">
                <input type="radio" name="delivery_default_method" value="{{ $val }}"
                       class="peer sr-only"
                       @if($settings['delivery_default_method'] === $val) checked @endif>
                <div class="p-3 rounded-xl border-2 text-center transition
                            border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900/50
                            peer-checked:border-indigo-500 peer-checked:bg-indigo-50
                            dark:peer-checked:bg-indigo-500/10 dark:peer-checked:border-indigo-500
                            hover:border-indigo-300 dark:hover:border-indigo-500/60">
                  <i class="bi {{ $icon }} text-xl text-indigo-500 mb-1"></i>
                  <div class="text-sm font-semibold text-slate-900 dark:text-white">{{ $label }}</div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ $hint }}</div>
                </div>
              </label>
            @endforeach
          </div>
        </div>

        <hr class="border-slate-200 dark:border-white/5">

        {{-- Auto-switch toggle --}}
        <div class="flex items-start gap-3">
          <label class="tw-switch mt-1">
            <input type="checkbox" name="delivery_auto_switch"
                   @if($settings['delivery_auto_switch'] === '1') checked @endif>
            <span class="slider"></span>
          </label>
          <div class="flex-1">
            <div class="text-sm font-semibold text-slate-900 dark:text-white">
              เปิดกลไกเลือกช่องทางอัตโนมัติ
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              เมื่อลูกค้าเลือก "อัตโนมัติ" ระบบจะเลือก LINE → อีเมล → เว็บ ตามความเหมาะสม
              และสลับไปใช้อีเมลเมื่อจำนวนรูปเยอะเกินค่าที่กำหนดไว้ด้านล่าง
            </p>
          </div>
        </div>

        {{-- Email threshold --}}
        <div>
          <label for="delivery_email_threshold" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
            สลับไปใช้อีเมลเมื่อรูปภาพมีจำนวน ≥
          </label>
          <div class="flex items-center gap-3">
            <input type="number" id="delivery_email_threshold" name="delivery_email_threshold"
                   min="1" max="500" step="1"
                   value="{{ $settings['delivery_email_threshold'] }}"
                   class="w-32 px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10
                          bg-white dark:bg-slate-900 text-slate-900 dark:text-white
                          focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            <span class="text-sm text-slate-500 dark:text-slate-400">รูป</span>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            ออเดอร์ที่มีรูปน้อยกว่านี้จะพยายามส่งทาง LINE ก่อน ถ้าเกินจะใช้อีเมลเพื่อประสบการณ์ดาวน์โหลดที่ดีกว่า
          </p>
        </div>

        {{-- LINE max photos per push --}}
        <div>
          <label for="delivery_line_max_photos" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
            จำนวนรูปสูงสุดใน LINE ต่อการส่ง 1 ครั้ง
          </label>
          <div class="flex items-center gap-3">
            <input type="number" id="delivery_line_max_photos" name="delivery_line_max_photos"
                   min="1" max="10" step="1"
                   value="{{ $settings['delivery_line_max_photos'] }}"
                   class="w-32 px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10
                          bg-white dark:bg-slate-900 text-slate-900 dark:text-white
                          focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            <span class="text-sm text-slate-500 dark:text-slate-400">รูป/ข้อความ (สูงสุด 10)</span>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            ข้อจำกัดจาก LINE Messaging API — ถ้าเกิน ระบบจะส่งเฉพาะลิงก์ดาวน์โหลดแทน
          </p>
        </div>
      </div>
    </div>

    {{-- Save button --}}
    <div class="flex items-center justify-end gap-3">
      <a href="{{ route('admin.settings.index') }}"
         class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm rounded-xl
                border border-slate-200 dark:border-white/10
                text-slate-600 dark:text-slate-300
                hover:bg-slate-50 dark:hover:bg-slate-800 transition">
        ยกเลิก
      </a>
      <button type="submit"
              class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl
                     bg-gradient-to-r from-indigo-600 to-purple-600
                     hover:from-indigo-700 hover:to-purple-700
                     text-white font-semibold shadow-md hover:shadow-lg transition">
        <i class="bi bi-check2-circle"></i> บันทึกการตั้งค่า
      </button>
    </div>
  </form>
</div>
@endsection

@extends('layouts.admin')
@section('title', 'ตั้งค่าระบบแจ้งถอน')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-sliders text-indigo-500"></i>
        ตั้งค่าระบบแจ้งถอนเงิน
    </h4>
    <a href="{{ route('admin.payments.withdrawals.index') }}"
       class="text-xs px-3 py-1.5 bg-slate-600 text-white rounded-lg hover:bg-slate-700">
        <i class="bi bi-arrow-left mr-1"></i>กลับไปคิวคำขอ
    </a>
</div>

@if(session('success'))
  <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm px-4 py-3">
    <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
  </div>
@endif
@if($errors->any())
  <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-900 text-sm px-4 py-3">
    @foreach($errors->all() as $err)<div>· {{ $err }}</div>@endforeach
  </div>
@endif

<form method="POST" action="{{ route('admin.payments.withdrawals.settings.save') }}" class="space-y-4">
    @csrf

    <div class="bg-white rounded-xl border border-gray-100 p-5">
        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">หลักฐาน</p>

        {{-- Master switch --}}
        <label class="flex items-start gap-3 mb-5">
            <input type="checkbox" name="enabled" value="1"
                   {{ ($settings['enabled'] ?? true) ? 'checked' : '' }}
                   class="mt-1 w-4 h-4 rounded border-slate-300">
            <div>
                <div class="font-semibold text-sm">เปิดให้ช่างภาพแจ้งถอนเงินด้วยตัวเอง</div>
                <div class="text-[11px] text-slate-500">เมื่อปิด ปุ่ม "แจ้งถอน" จะถูกซ่อน</div>
            </div>
        </label>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold mb-1">ขั้นต่ำในการกดถอน (บาท)</label>
                <input type="number" name="min_amount" value="{{ old('min_amount', $settings['min_amount']) }}"
                       min="1" max="1000000" required
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm tabular-nums">
                <p class="text-[10px] text-slate-500 mt-1">ช่างภาพต้องมียอดอย่างน้อยเท่านี้จึงจะกดได้</p>
            </div>
            <div>
                <label class="block text-xs font-bold mb-1">ยอดสูงสุดต่อครั้ง (บาท)</label>
                <input type="number" name="max_amount" value="{{ old('max_amount', $settings['max_amount']) }}"
                       min="1" max="10000000" required
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm tabular-nums">
                <p class="text-[10px] text-slate-500 mt-1">ป้องกันการกรอกผิดเป็นยอดใหญ่ผิดปกติ</p>
            </div>
            <div>
                <label class="block text-xs font-bold mb-1">ค่าธรรมเนียม (บาท)</label>
                <input type="number" name="fee_thb" value="{{ old('fee_thb', $settings['fee_thb']) }}"
                       min="0" max="10000" required
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm tabular-nums">
                <p class="text-[10px] text-slate-500 mt-1">หักจากยอดที่ขอ — กรอก 0 = ฟรี</p>
            </div>
            <div>
                <label class="block text-xs font-bold mb-1">เวลาดำเนินการ (วันทำการ)</label>
                <input type="number" name="processing_days" value="{{ old('processing_days', $settings['processing_days']) }}"
                       min="0" max="30" required
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm tabular-nums">
                <p class="text-[10px] text-slate-500 mt-1">โฆษณาให้ช่างภาพดู (ไม่ใช่ enforced timer)</p>
            </div>
            <div>
                <label class="block text-xs font-bold mb-1">คำขอที่รอตรวจสอบสูงสุดต่อช่างภาพ</label>
                <input type="number" name="max_pending" value="{{ old('max_pending', $settings['max_pending']) }}"
                       min="1" max="10" required
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm tabular-nums">
                <p class="text-[10px] text-slate-500 mt-1">ป้องกันการกดซ้ำเยอะ — แนะนำ 1</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 p-5">
        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">วิธีรับเงินที่รองรับ</p>
        <div class="space-y-2">
            @php $methods = $settings['methods'] ?? []; @endphp
            <label class="flex items-center gap-3">
                <input type="checkbox" name="methods[]" value="bank_transfer"
                       {{ in_array('bank_transfer', $methods, true) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-slate-300">
                <span class="text-sm">โอนเข้าบัญชีธนาคาร</span>
            </label>
            <label class="flex items-center gap-3">
                <input type="checkbox" name="methods[]" value="promptpay"
                       {{ in_array('promptpay', $methods, true) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-slate-300">
                <span class="text-sm">PromptPay (เบอร์มือถือ / เลขประจำตัวประชาชน)</span>
            </label>
        </div>
        <p class="text-[10px] text-slate-500 mt-3">เลือกอย่างน้อย 1 วิธี</p>
    </div>

    <div class="flex items-center justify-end gap-3 pt-2">
        <a href="{{ route('admin.payments.withdrawals.index') }}"
           class="text-xs px-4 py-2 rounded-lg border border-slate-300 hover:bg-slate-50">
            ยกเลิก
        </a>
        <button type="submit"
                class="text-sm font-bold px-5 py-2.5 rounded-lg text-white"
                style="background:linear-gradient(135deg,#6366f1,#7c3aed);">
            <i class="bi bi-save mr-1"></i>บันทึก
        </button>
    </div>
</form>
@endsection

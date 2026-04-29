@extends('layouts.admin')

@section('title', 'แก้ไข Promotion #' . $promotion->id)

@section('content')
<div class="max-w-3xl mx-auto p-4 md:p-6 space-y-6">

    <div>
        <a href="{{ route('admin.monetization.promotions') }}"
           class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-indigo-600 mb-2">
            <i class="bi bi-arrow-left"></i> กลับ Photographer Promotions
        </a>
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
            ✏️ แก้ไข Promotion #{{ $promotion->id }}
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            ช่างภาพ:
            <strong>{{ $promotion->photographer?->first_name }} {{ $promotion->photographer?->last_name }}</strong>
            <span class="text-slate-400">({{ $promotion->photographer?->email }})</span>
        </p>
    </div>

    @if($errors->any())
        <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 text-rose-700 px-4 py-3 text-sm">
            <strong>กรอกข้อมูลไม่ครบ:</strong>
            <ul class="mb-0 mt-2 ml-5 list-disc">
                @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
        </div>
    @endif

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 text-emerald-700 px-4 py-3 text-sm">
            <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 text-rose-700 px-4 py-3 text-sm">
            <i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.monetization.promotions.update', $promotion->id) }}"
          class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-6 space-y-4">
        @csrf @method('PUT')

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    ชนิด <span class="text-rose-500">*</span>
                </label>
                <select name="kind" required class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm">
                    @foreach(['boost'=>'Boost','featured'=>'Featured','highlight'=>'Highlight'] as $v=>$l)
                        <option value="{{ $v }}" @selected(old('kind', $promotion->kind)===$v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    Boost Score (1-25) <span class="text-rose-500">*</span>
                </label>
                <input type="number" name="boost_score" required min="1" max="25"
                       value="{{ old('boost_score', $promotion->boost_score) }}"
                       class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    เริ่ม
                </label>
                <input type="datetime-local" name="starts_at"
                       value="{{ old('starts_at', $promotion->starts_at?->format('Y-m-d\TH:i')) }}"
                       class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    สิ้นสุด
                </label>
                <input type="datetime-local" name="ends_at"
                       value="{{ old('ends_at', $promotion->ends_at?->format('Y-m-d\TH:i')) }}"
                       class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    ราคา (บาท) <span class="text-rose-500">*</span>
                </label>
                <input type="number" name="amount_thb" required min="0" max="99999" step="1"
                       value="{{ old('amount_thb', $promotion->amount_thb) }}"
                       class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">
                    สถานะ <span class="text-rose-500">*</span>
                </label>
                <select name="status" required class="w-full rounded-lg border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm">
                    @foreach(['pending'=>'รอชำระเงิน','active'=>'กำลังเปิด','expired'=>'หมดอายุ','cancelled'=>'ยกเลิก','refunded'=>'คืนเงิน'] as $v=>$l)
                        <option value="{{ $v }}" @selected(old('status', $promotion->status)===$v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 pt-2 border-t border-slate-100 dark:border-slate-700">
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition">
                <i class="bi bi-check-lg"></i> บันทึก
            </button>
            <a href="{{ route('admin.monetization.promotions') }}"
               class="px-5 py-2.5 rounded-xl ring-1 ring-slate-300 dark:ring-slate-600 text-slate-700 dark:text-slate-300 font-semibold transition hover:bg-slate-50 dark:hover:bg-slate-700">
                ยกเลิก
            </a>
        </div>
    </form>

    {{-- Quick actions: cancel + refund as separate POSTs --}}
    @if(!in_array($promotion->status, ['cancelled', 'refunded', 'expired']))
        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-2xl p-5 ring-1 ring-amber-200 dark:ring-amber-700/50">
            <h2 class="font-bold text-amber-900 dark:text-amber-200 mb-2">⚠️ Action ฉุกเฉิน</h2>
            <p class="text-sm text-amber-800 dark:text-amber-300 mb-3">
                ใช้สำหรับเหตุฉุกเฉิน: ยกเลิก promotion ที่ไม่ควรเริ่มหรือ refund ลูกค้า — log ทุก action ลง activity log
            </p>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.monetization.promotions.cancel', $promotion->id) }}"
                      onsubmit="return confirm('ยืนยันยกเลิก promotion นี้? (ยกเลิกอย่างเดียวไม่คืนเงิน)')">
                    @csrf
                    <button class="px-4 py-2 rounded-lg ring-1 ring-amber-400 text-amber-800 dark:text-amber-200 font-semibold text-sm transition hover:bg-amber-100 dark:hover:bg-amber-900/40"
                            style="background:rgba(245,158,11,0.15);">
                        <i class="bi bi-x-circle"></i> ยกเลิก promotion
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.monetization.promotions.refund', $promotion->id) }}"
                      onsubmit="return confirm('บันทึกว่า promotion นี้ refund แล้ว? (ต้องดำเนินการ refund จริงที่ payment gateway แยกต่างหาก)')">
                    @csrf
                    <button class="px-4 py-2 rounded-lg ring-1 ring-rose-400 text-rose-800 dark:text-rose-200 font-semibold text-sm transition hover:bg-rose-100 dark:hover:bg-rose-900/40"
                            style="background:rgba(244,63,94,0.15);">
                        <i class="bi bi-arrow-counterclockwise"></i> บันทึก Refund
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection

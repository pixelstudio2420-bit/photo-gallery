@extends('layouts.app')
@section('title', 'บัตรของขวัญ')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-10">
    <div class="bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-2xl p-8 mb-6 shadow-lg">
        <div class="flex items-center gap-3 mb-2">
            <i class="bi bi-gift text-3xl"></i>
            <h1 class="text-3xl font-bold">บัตรของขวัญ</h1>
        </div>
        <p class="opacity-90">มอบความทรงจำผ่านภาพถ่ายมืออาชีพ — ผู้รับเลือกงาน/รูปได้อย่างอิสระ</p>
    </div>

    @if($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-xl p-3 mb-4 text-sm">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <form action="{{ route('gift-cards.purchase') }}" method="POST" class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-md space-y-5">
        @csrf

        <div>
            <label class="text-sm font-semibold block mb-2">เลือกจำนวน</label>
            <div class="grid grid-cols-3 md:grid-cols-6 gap-2">
                @foreach($presets as $p)
                    <label class="cursor-pointer">
                        <input type="radio" name="amount" value="{{ $p }}" {{ old('amount', 1000) == $p ? 'checked' : '' }} class="peer sr-only">
                        <div class="text-center py-3 rounded-lg border-2 border-gray-200 dark:border-white/10 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 dark:peer-checked:bg-indigo-900/30 font-semibold text-sm">
                            ฿{{ number_format($p) }}
                        </div>
                    </label>
                @endforeach
            </div>
            <div class="mt-3 flex items-center gap-2">
                <span class="text-sm text-gray-500">หรือจำนวนอื่น:</span>
                <input type="number" name="amount_custom" min="100" max="50000" placeholder="กำหนดเอง" value="{{ old('amount_custom') }}"
                       class="flex-1 max-w-xs px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm"
                       onchange="if(this.value){document.querySelectorAll('input[name=amount]').forEach(r=>r.checked=false);this.form.amount.value=this.value;}">
            </div>
        </div>

        <div class="pt-4 border-t border-gray-100 dark:border-white/5">
            <div class="text-sm font-semibold text-gray-500 mb-2">ข้อมูลผู้ซื้อ</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input type="text" name="purchaser_name" required placeholder="ชื่อคุณ" value="{{ old('purchaser_name', auth()->user()?->first_name . ' ' . auth()->user()?->last_name) }}"
                       class="px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                <input type="email" name="purchaser_email" required placeholder="อีเมลคุณ" value="{{ old('purchaser_email', auth()->user()?->email) }}"
                       class="px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
            </div>
        </div>

        <div class="pt-4 border-t border-gray-100 dark:border-white/5">
            <div class="text-sm font-semibold text-gray-500 mb-2">ข้อมูลผู้รับ (ไม่บังคับ)</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input type="text" name="recipient_name" placeholder="ชื่อผู้รับ" value="{{ old('recipient_name') }}"
                       class="px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                <input type="email" name="recipient_email" placeholder="อีเมลผู้รับ" value="{{ old('recipient_email') }}"
                       class="px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
            </div>
            <textarea name="personal_message" rows="3" placeholder="ข้อความส่วนตัวถึงผู้รับ"
                      class="mt-3 w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">{{ old('personal_message') }}</textarea>
        </div>

        <button class="w-full py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold rounded-xl">
            <i class="bi bi-cart-plus mr-2"></i>ซื้อบัตร
        </button>

        <p class="text-xs text-gray-400 text-center">บัตรของขวัญมีอายุ 1 ปีนับจากวันที่ซื้อ · ไม่สามารถขอคืนเป็นเงินสด</p>
    </form>
</div>
@endsection

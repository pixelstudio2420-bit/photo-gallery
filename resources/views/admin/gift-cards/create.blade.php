@extends('layouts.admin')
@section('title', 'ออกบัตรของขวัญ')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-gift text-indigo-500"></i>ออกบัตรของขวัญใหม่
    </h4>
    <a href="{{ route('admin.gift-cards.index') }}" class="text-sm text-gray-500 hover:text-indigo-500">
        <i class="bi bi-arrow-left mr-1"></i>กลับ
    </a>
</div>

<form action="{{ route('admin.gift-cards.store') }}" method="POST" class="bg-white dark:bg-slate-800 rounded-xl p-6 border border-gray-100 dark:border-white/5 space-y-4 max-w-3xl">
    @csrf
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="text-sm font-semibold">จำนวนเงิน (บาท) *</label>
            <input type="number" name="amount" min="1" step="0.01" value="{{ old('amount', 1000) }}" required
                   class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
            @error('amount')<div class="text-xs text-rose-500 mt-1">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="text-sm font-semibold">ที่มา</label>
            <select name="source" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
                @foreach($sources as $k => $v)
                    <option value="{{ $k }}" {{ old('source', 'admin') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-semibold">วันหมดอายุ</label>
            <input type="date" name="expires_at" value="{{ old('expires_at', now()->addYear()->toDateString()) }}"
                   class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
        </div>
        <div>
            <label class="text-sm font-semibold">สกุลเงิน</label>
            <input type="text" name="currency" value="{{ old('currency', 'THB') }}" maxlength="3"
                   class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
        </div>
    </div>

    <div class="pt-3 border-t border-gray-100 dark:border-white/5">
        <div class="text-sm font-semibold text-gray-500 mb-2">ผู้รับ (ไม่บังคับ)</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="recipient_name" placeholder="ชื่อผู้รับ" value="{{ old('recipient_name') }}"
                   class="px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
            <input type="email" name="recipient_email" placeholder="อีเมลผู้รับ" value="{{ old('recipient_email') }}"
                   class="px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
        </div>
        <textarea name="personal_message" rows="2" placeholder="ข้อความส่วนตัว"
                  class="mt-2 w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">{{ old('personal_message') }}</textarea>
    </div>

    <div class="pt-3 border-t border-gray-100 dark:border-white/5">
        <label class="text-sm font-semibold">หมายเหตุ (admin only)</label>
        <textarea name="admin_note" rows="2" class="mt-1 w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">{{ old('admin_note') }}</textarea>
    </div>

    <div class="flex justify-end gap-2 pt-3">
        <a href="{{ route('admin.gift-cards.index') }}" class="px-4 py-2 bg-gray-100 dark:bg-slate-700 rounded-lg text-sm">ยกเลิก</a>
        <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
            <i class="bi bi-check-circle mr-1"></i>ออกบัตร
        </button>
    </div>
</form>
@endsection

@extends('layouts.admin')
@section('title', 'New Push Campaign')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-6">

    <div class="mb-4">
        <a href="{{ route('admin.marketing.push.index') }}" class="text-sm text-slate-500 hover:text-pink-500">
            <i class="bi bi-arrow-left"></i> กลับ
        </a>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            <i class="bi bi-bell-fill text-pink-500"></i> สร้าง Push Campaign
        </h1>
    </div>

    @if($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-500/10 border border-rose-500/30">
            <ul class="text-sm text-rose-500 list-disc list-inside">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.marketing.push.store') }}" class="space-y-4">
        @csrf

        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 space-y-3">
            <div>
                <label class="block text-xs font-semibold mb-1">Title <span class="text-rose-500">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="120"
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Body <span class="text-rose-500">*</span></label>
                <textarea name="body" rows="3" required maxlength="500"
                          class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">{{ old('body') }}</textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold mb-1">Icon URL</label>
                    <input type="text" name="icon" value="{{ old('icon') }}" maxlength="500"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Click URL</label>
                    <input type="url" name="click_url" value="{{ old('click_url') }}" maxlength="500"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold mb-1">Segment</label>
                    <select name="segment" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                        @foreach(['all'=>'All','users'=>'Logged-in users','guests'=>'Guests','tag'=>'By tag'] as $k=>$l)
                            <option value="{{ $k }}" @selected(old('segment')===$k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Segment value (if tag)</label>
                    <input type="text" name="segment_value" value="{{ old('segment_value') }}" maxlength="120"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button class="px-4 py-2 rounded-lg bg-pink-600 hover:bg-pink-500 text-white text-sm font-semibold">
                <i class="bi bi-save"></i> บันทึกเป็น draft
            </button>
            <a href="{{ route('admin.marketing.push.index') }}" class="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">ยกเลิก</a>
        </div>
    </form>
</div>
@endsection

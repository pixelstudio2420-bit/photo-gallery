@extends('layouts.admin')

@section('title', 'New Campaign')

@section('content')
<div class="p-6 max-w-[900px] mx-auto">

    <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
        <a href="{{ route('admin.marketing.index') }}" class="hover:text-white">Marketing Hub</a>
        <i class="bi bi-chevron-right text-[0.6rem]"></i>
        <a href="{{ route('admin.marketing.campaigns.index') }}" class="hover:text-white">Campaigns</a>
        <i class="bi bi-chevron-right text-[0.6rem]"></i>
        <span>New</span>
    </div>
    <h1 class="text-2xl font-bold text-white mb-2 flex items-center gap-2">
        <i class="bi bi-envelope-plus text-pink-400"></i> สร้าง Campaign ใหม่
    </h1>
    <p class="text-sm text-slate-400 mb-6">เขียน subject + body + เลือก segment — บันทึกเป็น draft ก่อน ค่อยส่งในหน้า details</p>

    @if($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.marketing.campaigns.store') }}" class="space-y-4">
        @csrf

        <div class="rounded-2xl border border-slate-700/40 bg-slate-900/60 p-5 space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Campaign Name (internal)</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    placeholder="เช่น: New Year Promo 2026"
                    class="w-full px-3 py-2 rounded-lg bg-slate-950/60 border border-slate-700/40 text-white text-sm focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Email Subject</label>
                <input type="text" name="subject" value="{{ old('subject') }}" required
                    placeholder="เช่น: 🎉 ส่วนลด 30% ต้อนรับปีใหม่"
                    class="w-full px-3 py-2 rounded-lg bg-slate-950/60 border border-slate-700/40 text-white text-sm focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Body (Markdown)</label>
                <textarea name="body_markdown" rows="12" required
                    placeholder="**Hi @{{name}}**&#10;&#10;ข่าวดี! เรามี promotion พิเศษ..."
                    class="w-full px-3 py-2 rounded-lg bg-slate-950/60 border border-slate-700/40 text-white text-sm font-mono focus:border-indigo-500 focus:outline-none">{{ old('body_markdown') }}</textarea>
                <p class="text-[0.68rem] text-slate-500 mt-1">
                    <i class="bi bi-info-circle"></i> รองรับ <code>**bold**</code>, <code>[link](url)</code>, บรรทัดใหม่ + placeholders <code>@{{name}}</code>, <code>@{{email}}</code>
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-700/40 bg-slate-900/60 p-5 space-y-4">
            <h3 class="font-bold text-white flex items-center gap-2">
                <i class="bi bi-people-fill text-sky-400"></i> Segment (ใครรับบ้าง)
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Segment Type</label>
                    <select name="segment_type" class="w-full px-3 py-2 rounded-lg bg-slate-950/60 border border-slate-700/40 text-white text-sm">
                        <option value="all">All Confirmed Subscribers</option>
                        <option value="vip">VIP (Gold+Platinum tier)</option>
                        <option value="dormant">Dormant (ไม่ซื้อ 90+ วัน)</option>
                        <option value="tag">By Tag</option>
                        <option value="users">All Users (email อยู่ในระบบ)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Tag (ถ้าเลือก By Tag)</label>
                    <input type="text" name="segment_value" value="{{ old('segment_value') }}"
                        placeholder="เช่น: photographer, frequent_buyer"
                        class="w-full px-3 py-2 rounded-lg bg-slate-950/60 border border-slate-700/40 text-white text-sm focus:border-indigo-500 focus:outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Schedule (optional)</label>
                <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"
                    class="px-3 py-2 rounded-lg bg-slate-950/60 border border-slate-700/40 text-white text-sm focus:border-indigo-500 focus:outline-none">
                <p class="text-[0.68rem] text-slate-500 mt-1">เว้นว่าง = บันทึกเป็น draft (ส่งเมื่อพร้อมในหน้า details)</p>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.marketing.campaigns.index') }}" class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm">ยกเลิก</a>
            <button type="submit" class="px-6 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold">
                <i class="bi bi-save"></i> บันทึก Draft
            </button>
        </div>
    </form>
</div>
@endsection

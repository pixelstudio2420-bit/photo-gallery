@extends('layouts.admin')
@section('title', $isNew ? 'สร้างแคมเปญใหม่' : 'แก้ไขแคมเปญ')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-6">
  <a href="{{ route('admin.monetization.dashboard') }}" class="text-xs text-slate-500 hover:underline">← Monetization</a>
  <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mb-5">{{ $isNew ? 'สร้างแคมเปญ Brand Ad' : 'แก้ไขแคมเปญ' }}</h1>

  @if($errors->any())
    <div class="mb-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
      @foreach($errors->all() as $e)<div>· {{ $e }}</div>@endforeach
    </div>
  @endif

  <form method="POST" action="{{ route('admin.monetization.campaigns.store') }}" class="space-y-5">
    @csrf
    <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 p-5 space-y-4">
      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1">ชื่อแคมเปญ *</label>
        <input type="text" name="name" required value="{{ old('name', $campaign->name) }}"
               class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Brand / Advertiser *</label>
          <input type="text" name="advertiser" required value="{{ old('advertiser', $campaign->advertiser) }}"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">อีเมลผู้ลงโฆษณา</label>
          <input type="email" name="contact_email" value="{{ old('contact_email', $campaign->contact_email) }}"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Pricing Model *</label>
          <select name="pricing_model" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
            <option value="cpm"          {{ old('pricing_model', $campaign->pricing_model) === 'cpm' ? 'selected' : '' }}>CPM (ต่อ 1,000 impressions)</option>
            <option value="cpc"          {{ old('pricing_model', $campaign->pricing_model) === 'cpc' ? 'selected' : '' }}>CPC (ต่อคลิก)</option>
            <option value="flat_daily"   {{ old('pricing_model', $campaign->pricing_model) === 'flat_daily' ? 'selected' : '' }}>Flat (รายวัน)</option>
            <option value="flat_monthly" {{ old('pricing_model', $campaign->pricing_model) === 'flat_monthly' ? 'selected' : '' }}>Flat (รายเดือน)</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Rate (฿) *</label>
          <input type="number" name="rate_thb" step="0.01" min="0" required value="{{ old('rate_thb', $campaign->rate_thb) }}"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">Budget Cap (฿) <span class="text-slate-400 text-[10px]">เว้นว่าง = ไม่จำกัด</span></label>
          <input type="number" name="budget_cap_thb" step="0.01" min="0" value="{{ old('budget_cap_thb', $campaign->budget_cap_thb) }}"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">เริ่มต้น *</label>
          <input type="datetime-local" name="starts_at" required
                 value="{{ old('starts_at', $campaign->starts_at?->format('Y-m-d\\TH:i')) }}"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">สิ้นสุด *</label>
          <input type="datetime-local" name="ends_at" required
                 value="{{ old('ends_at', $campaign->ends_at?->format('Y-m-d\\TH:i')) }}"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1">สถานะ *</label>
          <select name="status" required class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-slate-800 text-sm">
            <option value="pending"   {{ old('status', $campaign->status) === 'pending' ? 'selected' : '' }}>pending</option>
            <option value="active"    {{ old('status', $campaign->status) === 'active' ? 'selected' : '' }}>active</option>
            <option value="paused"    {{ old('status', $campaign->status) === 'paused' ? 'selected' : '' }}>paused</option>
            <option value="exhausted" {{ old('status', $campaign->status) === 'exhausted' ? 'selected' : '' }}>exhausted</option>
            <option value="ended"     {{ old('status', $campaign->status) === 'ended' ? 'selected' : '' }}>ended</option>
          </select>
        </div>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <button class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">
        <i class="bi bi-save"></i> สร้างแคมเปญ
      </button>
      <a href="{{ route('admin.monetization.dashboard') }}" class="text-sm text-slate-500 hover:underline ml-2">ยกเลิก</a>
    </div>
  </form>
</div>
@endsection

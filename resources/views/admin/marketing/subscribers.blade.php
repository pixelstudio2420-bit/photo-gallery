@extends('layouts.admin')

@section('title', 'Subscribers')

@push('styles')
<style>
  .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(99,102,241,.14) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(139,92,246,.10) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(168,85,247,.12) 0px, transparent 50%);
  }
  .dark .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(99,102,241,.20) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(139,92,246,.16) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(168,85,247,.18) 0px, transparent 50%);
  }
</style>
@endpush

@section('content')
<div class="max-w-[1400px] mx-auto pb-10 space-y-5">

  {{-- ── HERO HEADER ────────────────────────────────────────────── --}}
  <div class="relative overflow-hidden rounded-2xl border border-indigo-100 dark:border-indigo-500/20 gradient-mesh">
    <div class="relative p-6 md:p-7">
      <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-3">
        <a href="{{ route('admin.marketing.index') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">Marketing Hub</a>
        <i class="bi bi-chevron-right text-[0.6rem] opacity-50"></i>
        <span class="text-slate-700 dark:text-slate-200 font-medium">Newsletter Subscribers</span>
      </nav>

      <div class="flex items-start justify-between flex-wrap gap-4">
        <div class="flex items-start gap-4 min-w-0 flex-1">
          <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 via-violet-500 to-purple-500 text-white flex items-center justify-center shadow-lg shrink-0">
            <i class="bi bi-envelope-heart-fill text-2xl"></i>
          </div>
          <div class="min-w-0 flex-1">
            <h1 class="text-2xl md:text-[1.65rem] font-bold text-slate-800 dark:text-slate-100 tracking-tight leading-tight">Newsletter Subscribers</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">จัดการผู้สมัครรับจดหมายข่าว (double opt-in + welcome email)</p>
            <div class="flex items-center gap-2 mt-3 flex-wrap">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                {{ number_format($stats['total']) }} total
              </span>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                <i class="bi bi-check-circle-fill"></i>
                {{ number_format($stats['confirmed']) }} confirmed
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  @if(session('success'))
    <div class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-sm flex items-center gap-2"
         x-data="{ show: true }" x-show="show">
      <i class="bi bi-check-circle-fill text-emerald-500"></i>
      <span class="flex-1">{{ session('success') }}</span>
      <button type="button" @click="show = false" class="text-emerald-600/60 hover:text-emerald-700 dark:text-emerald-400/60 dark:hover:text-emerald-300">
        <i class="bi bi-x-lg text-sm"></i>
      </button>
    </div>
  @endif
  @if(session('error'))
    <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-700 dark:text-rose-300 text-sm flex items-center gap-2">
      <i class="bi bi-exclamation-triangle-fill text-rose-500"></i>
      <span>{{ session('error') }}</span>
    </div>
  @endif

  {{-- ═══ Stats cards ═══ --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-indigo-100 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
          <i class="bi bi-people-fill"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($stats['total']) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">all subscribers</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
          <i class="bi bi-patch-check-fill"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Confirmed</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($stats['confirmed']) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">verified emails</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
          <i class="bi bi-hourglass-split"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pending</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($stats['pending']) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">awaiting opt-in</div>
    </div>
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-rose-100 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400 flex items-center justify-center">
          <i class="bi bi-x-circle-fill"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Unsubscribed</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ number_format($stats['unsubscribed']) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">opted out</div>
    </div>
  </div>

  {{-- ═══ Settings card ═══ --}}
  <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
    <div class="bg-gradient-to-r from-indigo-50/60 to-transparent dark:from-indigo-500/5 p-5 border-b border-slate-200/60 dark:border-white/[0.06] flex items-center gap-2">
      <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-500 text-white flex items-center justify-center shadow-md">
        <i class="bi bi-sliders"></i>
      </div>
      <h3 class="font-bold text-slate-800 dark:text-slate-100">Newsletter Settings</h3>
    </div>
    <form method="POST" action="{{ route('admin.marketing.subscribers.settings') }}" class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
      @csrf
      <div>
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">From Name</label>
        <input type="text" name="email_from_name" value="{{ $settings['email_from_name'] }}" placeholder="{{ config('app.name') }}"
            class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 outline-none transition">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">From Email</label>
        <input type="email" name="email_from_address" value="{{ $settings['email_from_address'] }}" placeholder="newsletter@domain.com"
            class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 outline-none transition">
      </div>
      <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-slate-50/50 dark:bg-slate-900/40 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-900/60 transition">
        <input type="hidden" name="newsletter_double_optin" value="0">
        <input type="checkbox" name="newsletter_double_optin" value="1"
               class="peer sr-only" {{ $settings['newsletter_double_optin'] ? 'checked' : '' }}>
        <span class="relative w-11 h-6 rounded-full bg-slate-300 dark:bg-slate-700 peer-checked:bg-emerald-500 transition-all shrink-0">
          <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-all peer-checked:translate-x-5"></span>
        </span>
        <div>
          <div class="text-sm text-slate-800 dark:text-slate-100 font-semibold">Double Opt-in</div>
          <div class="text-xs text-slate-500 dark:text-slate-400">ส่งอีเมลยืนยันก่อนเพิ่มใน list (แนะนำ: เปิด)</div>
        </div>
      </label>
      <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-slate-50/50 dark:bg-slate-900/40 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-900/60 transition">
        <input type="hidden" name="newsletter_welcome_enabled" value="0">
        <input type="checkbox" name="newsletter_welcome_enabled" value="1"
               class="peer sr-only" {{ $settings['newsletter_welcome_enabled'] ? 'checked' : '' }}>
        <span class="relative w-11 h-6 rounded-full bg-slate-300 dark:bg-slate-700 peer-checked:bg-emerald-500 transition-all shrink-0">
          <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-all peer-checked:translate-x-5"></span>
        </span>
        <div>
          <div class="text-sm text-slate-800 dark:text-slate-100 font-semibold">Welcome Email</div>
          <div class="text-xs text-slate-500 dark:text-slate-400">ส่งอีเมลต้อนรับหลัง confirm</div>
        </div>
      </label>
      <div class="md:col-span-2 flex justify-end">
        <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold bg-gradient-to-br from-indigo-500 via-violet-500 to-purple-500 text-white shadow-lg shadow-indigo-500/30 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200">
          <i class="bi bi-check2"></i> บันทึก
        </button>
      </div>
    </form>
  </div>

  {{-- ═══ Filter + list ═══ --}}
  <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
    <div class="bg-gradient-to-r from-indigo-50/40 to-transparent dark:from-indigo-500/5 p-4 border-b border-slate-200/60 dark:border-white/[0.06]">
      <form method="GET" class="flex items-center gap-2 flex-wrap">
        <div class="relative flex-1 min-w-[200px]">
          <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
          <input type="text" name="q" value="{{ $q }}" placeholder="ค้นหา email / name..."
              class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 outline-none transition">
        </div>
        <select name="status" class="px-3 py-2 rounded-xl border border-slate-200 dark:border-white/[0.08] bg-white dark:bg-slate-900/40 text-slate-800 dark:text-slate-100 text-sm focus:ring-2 focus:ring-indigo-500/40 focus:border-indigo-500 outline-none transition">
          <option value="">ทั้งหมด</option>
          <option value="confirmed"    {{ $status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
          <option value="pending"      {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
          <option value="unsubscribed" {{ $status === 'unsubscribed' ? 'selected' : '' }}>Unsubscribed</option>
          <option value="bounced"      {{ $status === 'bounced' ? 'selected' : '' }}>Bounced</option>
        </select>
        <button class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-700/60 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-medium transition">
          <i class="bi bi-funnel"></i> Filter
        </button>
      </form>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50/80 dark:bg-slate-900/40 text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wider">
          <tr>
            <th class="text-left px-4 py-3 font-semibold">Email</th>
            <th class="text-left px-4 py-3 font-semibold">Name</th>
            <th class="text-left px-4 py-3 font-semibold">Status</th>
            <th class="text-left px-4 py-3 font-semibold">Source</th>
            <th class="text-left px-4 py-3 font-semibold">Added</th>
            <th class="text-right px-4 py-3 font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200/60 dark:divide-white/[0.06]">
          @forelse($subscribers as $s)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
              <td class="px-4 py-3 text-slate-800 dark:text-slate-100 font-medium">{{ $s->email }}</td>
              <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $s->name ?: '—' }}</td>
              <td class="px-4 py-3">
                @php
                  $col = match($s->status) {
                    'confirmed' => 'emerald', 'pending' => 'amber',
                    'unsubscribed' => 'rose', 'bounced' => 'slate', default => 'slate'
                  };
                @endphp
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[0.68rem] font-semibold bg-{{ $col }}-100 text-{{ $col }}-700 dark:bg-{{ $col }}-500/15 dark:text-{{ $col }}-400 border border-{{ $col }}-200 dark:border-{{ $col }}-500/30">
                  {{ $s->status }}
                </span>
              </td>
              <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs">{{ $s->source ?: '—' }}</td>
              <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs">{{ $s->created_at->diffForHumans() }}</td>
              <td class="px-4 py-3 text-right">
                <form method="POST" action="{{ route('admin.marketing.subscribers.delete', $s) }}" class="inline"
                      onsubmit="return confirm('ลบ {{ $s->email }}?')">
                  @csrf @method('DELETE')
                  <button class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition" title="Delete">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
              <i class="bi bi-inbox text-4xl block mb-2 opacity-50"></i>
              ยังไม่มี subscribers
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-4 border-t border-slate-200/60 dark:border-white/[0.06]">
      {{ $subscribers->links() }}
    </div>
  </div>
</div>
@endsection

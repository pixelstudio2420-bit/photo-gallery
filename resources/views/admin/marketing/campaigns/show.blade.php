@extends('layouts.admin')

@section('title', 'Campaign: ' . $campaign->name)

@section('content')
<div class="p-6 max-w-[1200px] mx-auto">

    <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
        <a href="{{ route('admin.marketing.index') }}" class="hover:text-white">Marketing Hub</a>
        <i class="bi bi-chevron-right text-[0.6rem]"></i>
        <a href="{{ route('admin.marketing.campaigns.index') }}" class="hover:text-white">Campaigns</a>
        <i class="bi bi-chevron-right text-[0.6rem]"></i>
        <span>{{ $campaign->name }}</span>
    </div>

    <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-white mb-1">{{ $campaign->name }}</h1>
            <div class="text-sm text-slate-400">
                <i class="bi bi-envelope"></i> {{ $campaign->subject }}
            </div>
        </div>
        @php
            $col = match($campaign->status) {
                'sent' => 'emerald', 'scheduled' => 'amber',
                'sending' => 'sky', 'cancelled' => 'rose',
                'failed' => 'rose', default => 'slate'
            };
        @endphp
        <span class="inline-flex px-3 py-1 rounded-full text-xs bg-{{ $col }}-500/15 text-{{ $col }}-400 border border-{{ $col }}-500/30 font-semibold">
            {{ strtoupper($campaign->status) }}
        </span>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Metrics --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="rounded-xl border border-slate-700/40 bg-slate-900/60 p-4">
            <div class="text-xs text-slate-400 uppercase tracking-widest">Recipients</div>
            <div class="text-2xl font-bold text-white mt-1">{{ number_format($campaign->total_recipients) }}</div>
        </div>
        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-4">
            <div class="text-xs text-emerald-400 uppercase tracking-widest">Sent</div>
            <div class="text-2xl font-bold text-white mt-1">{{ number_format($campaign->sent_count) }}</div>
        </div>
        <div class="rounded-xl border border-sky-500/20 bg-sky-500/5 p-4">
            <div class="text-xs text-sky-400 uppercase tracking-widest">Opens</div>
            <div class="text-2xl font-bold text-white mt-1">{{ number_format($campaign->open_count) }}</div>
            <div class="text-[0.68rem] text-slate-500">{{ $campaign->openRate() }}%</div>
        </div>
        <div class="rounded-xl border border-indigo-500/20 bg-indigo-500/5 p-4">
            <div class="text-xs text-indigo-400 uppercase tracking-widest">Clicks</div>
            <div class="text-2xl font-bold text-white mt-1">{{ number_format($campaign->click_count) }}</div>
            <div class="text-[0.68rem] text-slate-500">CTR {{ $campaign->ctr() }}%</div>
        </div>
        <div class="rounded-xl border border-rose-500/20 bg-rose-500/5 p-4">
            <div class="text-xs text-rose-400 uppercase tracking-widest">Unsubs / Bounces</div>
            <div class="text-2xl font-bold text-white mt-1">{{ number_format($campaign->unsubscribe_count + $campaign->bounce_count) }}</div>
        </div>
    </div>

    {{-- Actions --}}
    @if(in_array($campaign->status, ['draft', 'scheduled']))
    <div class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-4 mb-6">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="text-sm font-semibold text-amber-200">พร้อมส่ง Campaign นี้?</div>
                <div class="text-xs text-slate-400 mt-0.5">
                    Segment: <strong class="text-slate-300">{{ $campaign->segment['type'] ?? 'all' }}</strong>
                    @if(!empty($campaign->segment['value'])) ({{ $campaign->segment['value'] }})@endif
                </div>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('admin.marketing.campaigns.cancel', $campaign) }}"
                      onsubmit="return confirm('ยกเลิก campaign นี้?')">
                    @csrf
                    <button class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm">ยกเลิก</button>
                </form>
                <form method="POST" action="{{ route('admin.marketing.campaigns.send', $campaign) }}"
                      onsubmit="return confirm('ยืนยันส่ง campaign นี้? การกระทำนี้ย้อนกลับไม่ได้')">
                    @csrf
                    <button class="px-6 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold">
                        <i class="bi bi-send"></i> ส่งเลย
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Preview --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="rounded-2xl border border-slate-700/40 bg-slate-900/60 overflow-hidden">
            <div class="p-4 border-b border-slate-700/30 flex items-center gap-2">
                <i class="bi bi-code text-slate-400"></i>
                <h3 class="font-bold text-white text-sm">Body (Markdown)</h3>
            </div>
            <pre class="p-4 text-xs text-slate-300 overflow-x-auto whitespace-pre-wrap font-mono">{{ $campaign->body_markdown }}</pre>
        </div>
        <div class="rounded-2xl border border-slate-700/40 bg-slate-900/60 overflow-hidden">
            <div class="p-4 border-b border-slate-700/30 flex items-center gap-2">
                <i class="bi bi-eye text-slate-400"></i>
                <h3 class="font-bold text-white text-sm">Preview (ประมาณ)</h3>
            </div>
            <div class="p-4 bg-white text-slate-900 text-sm max-h-96 overflow-y-auto">
                {!! nl2br(e($campaign->body_markdown)) !!}
            </div>
        </div>
    </div>

    {{-- Recent recipients --}}
    <div class="rounded-2xl border border-slate-700/40 bg-slate-900/60 overflow-hidden">
        <div class="p-4 border-b border-slate-700/30">
            <h3 class="font-bold text-white">Recent Recipients (latest 50)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-950/60 text-xs text-slate-400 uppercase tracking-widest">
                    <tr>
                        <th class="text-left px-4 py-2">Email</th>
                        <th class="text-left px-4 py-2">Status</th>
                        <th class="text-left px-4 py-2">Sent</th>
                        <th class="text-left px-4 py-2">Opened</th>
                        <th class="text-left px-4 py-2">Clicked</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($recipients as $r)
                        <tr>
                            <td class="px-4 py-2 text-slate-300">{{ $r->email }}</td>
                            <td class="px-4 py-2 text-xs text-slate-400">{{ $r->status }}</td>
                            <td class="px-4 py-2 text-xs text-slate-400">{{ $r->sent_at?->diffForHumans() ?: '—' }}</td>
                            <td class="px-4 py-2 text-xs text-sky-300">{{ $r->opened_at?->diffForHumans() ?: '—' }}</td>
                            <td class="px-4 py-2 text-xs text-indigo-300">{{ $r->clicked_at?->diffForHumans() ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">ยังไม่ได้ส่ง</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($campaign->status !== 'sent')
    <div class="mt-6 flex justify-end">
        <form method="POST" action="{{ route('admin.marketing.campaigns.delete', $campaign) }}"
              onsubmit="return confirm('ลบ campaign นี้?')">
            @csrf @method('DELETE')
            <button class="text-xs text-rose-400 hover:text-rose-300">
                <i class="bi bi-trash"></i> ลบ Campaign
            </button>
        </form>
    </div>
    @endif
</div>
@endsection

@extends('layouts.admin')
@section('title', 'Web Push')
@section('content')
@php
    $push = app(\App\Services\Marketing\PushService::class);
    $hasLib = $push->hasLibrary();
@endphp
<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="bi bi-bell-fill text-pink-500"></i> Web Push Notifications
            </h1>
            <p class="text-sm text-slate-500 mt-1">VAPID + broadcast — ส่งแจ้งเตือนแบบทันใจ</p>
        </div>
        <a href="{{ route('admin.marketing.push.create') }}" class="px-4 py-2 rounded-lg bg-pink-600 hover:bg-pink-500 text-white text-sm font-semibold">
            <i class="bi bi-plus-lg"></i> สร้าง Push Campaign
        </a>
    </div>

    @if(session('success'))
        <div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-500 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="p-3 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-500 text-sm">{{ session('error') }}</div>
    @endif

    @if(! $hasLib)
        <div class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-500 text-sm">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>หมายเหตุ:</strong> ยังไม่ได้ติดตั้ง <code class="font-mono">minishlink/web-push</code> — การส่งจริงจะไม่เกิดขึ้น (mark-as-sent เท่านั้น).
            รัน <code class="font-mono">composer require minishlink/web-push</code> เพื่อเปิด delivery จริง.
        </div>
    @endif

    {{-- Summary tiles --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
        @foreach([
            ['label'=>'Active Subs','value'=>$summary['subscribers'],'color'=>'text-emerald-500'],
            ['label'=>'Stale','value'=>$summary['stale'],'color'=>'text-amber-500'],
            ['label'=>'Revoked','value'=>$summary['revoked'],'color'=>'text-slate-500'],
            ['label'=>'Campaigns','value'=>$summary['campaigns'],'color'=>'text-indigo-500'],
            ['label'=>'Total Sent','value'=>number_format($summary['total_sent']),'color'=>'text-pink-500'],
            ['label'=>'Total Clicks','value'=>number_format($summary['total_clicks']),'color'=>'text-rose-500'],
        ] as $t)
            <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
                <div class="text-xs text-slate-500">{{ $t['label'] }}</div>
                <div class="text-xl font-bold {{ $t['color'] }}">{{ $t['value'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Settings --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 space-y-4">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white"><i class="bi bi-gear-fill text-slate-500"></i> VAPID + Prompt Settings</h3>

        <form method="POST" action="{{ route('admin.marketing.push.settings') }}" class="space-y-3">
            @csrf
            <label class="flex items-center gap-2">
                <input type="checkbox" name="push_enabled" value="1" {{ $settings['push_enabled'] === '1' ? 'checked' : '' }} class="rounded">
                <span class="text-sm font-semibold">เปิดใช้งาน Web Push</span>
            </label>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold mb-1">VAPID Subject (email/URL)</label>
                    <input type="text" name="push_vapid_subject" value="{{ $settings['push_vapid_subject'] }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Prompt delay (seconds)</label>
                    <input type="number" name="push_prompt_delay" value="{{ $settings['push_prompt_delay'] }}" min="0" max="600" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Prompt text (in-page banner)</label>
                <input type="text" name="push_prompt_text" value="{{ $settings['push_prompt_text'] }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold mb-1">VAPID Public Key</label>
                    <input type="text" name="push_vapid_public" value="{{ $settings['push_vapid_public'] }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs font-mono" placeholder="BNc...">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">VAPID Private Key</label>
                    <input type="password" name="push_vapid_private" value="{{ $settings['push_vapid_private'] }}" class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs font-mono" placeholder="(hidden)">
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button class="px-4 py-2 rounded-lg bg-pink-600 hover:bg-pink-500 text-white text-sm font-semibold">บันทึก</button>
                <button type="submit" formaction="{{ route('admin.marketing.push.vapid-generate') }}" class="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                    <i class="bi bi-key"></i> สร้าง VAPID keys ใหม่
                </button>
            </div>
        </form>
    </div>

    {{-- Campaigns table --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-700">
                <tr class="text-left text-xs uppercase text-slate-500">
                    <th class="px-4 py-2">Title</th>
                    <th class="px-4 py-2">Segment</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2 text-right">Targets</th>
                    <th class="px-4 py-2 text-right">Sent</th>
                    <th class="px-4 py-2 text-right">Clicks</th>
                    <th class="px-4 py-2 text-right">CTR</th>
                    <th class="px-4 py-2">Sent At</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse($campaigns as $c)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900 dark:text-white">{{ $c->title }}</div>
                            <div class="text-xs text-slate-500 truncate max-w-md">{{ $c->body }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            {{ $c->segment }}
                            @if($c->segment_value)<span class="text-slate-500">: {{ $c->segment_value }}</span>@endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 text-xs rounded border {{ $c->statusBadgeColor() }}">{{ $c->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono">{{ number_format($c->targets) }}</td>
                        <td class="px-4 py-3 text-right font-mono">{{ number_format($c->sent) }}</td>
                        <td class="px-4 py-3 text-right font-mono">{{ number_format($c->clicks) }}</td>
                        <td class="px-4 py-3 text-right font-mono text-pink-500">{{ $c->clickRate() }}%</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $c->sent_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if(in_array($c->status, ['draft','failed']))
                                <form method="POST" action="{{ route('admin.marketing.push.send', $c) }}" class="inline" onsubmit="return confirm('ส่ง Push แน่นะ?')">
                                    @csrf
                                    <button class="text-emerald-500 hover:text-emerald-400" title="Send"><i class="bi bi-send"></i></button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.marketing.push.delete', $c) }}" class="inline" onsubmit="return confirm('ลบแน่นะ?')">
                                @csrf @method('DELETE')
                                <button class="text-rose-500 hover:text-rose-400 ml-2"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">ยังไม่มี push campaign</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $campaigns->links() }}</div>
</div>
@endsection

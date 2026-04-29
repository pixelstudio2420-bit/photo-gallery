@extends('layouts.admin')
@section('title', 'Landing Pages')
@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <i class="bi bi-file-earmark-richtext text-indigo-500"></i> Landing Pages
            </h1>
            <p class="text-sm text-slate-500 mt-1">สร้างหน้า LP ต่อ campaign ได้ไม่จำกัด</p>
        </div>
        <div class="flex items-center gap-2">
            @if($enabled)
                <span class="px-2 py-0.5 text-xs rounded bg-emerald-500/20 text-emerald-500 border border-emerald-500/30">ON</span>
            @else
                <span class="px-2 py-0.5 text-xs rounded bg-slate-500/20 text-slate-500 border border-slate-500/30">OFF</span>
            @endif
            <a href="{{ route('admin.marketing.landing.create') }}" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold">
                <i class="bi bi-plus-lg"></i> สร้าง Landing Page
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-500 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="p-3 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-500 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Summary tiles --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
        @foreach([
            ['label'=>'Total','value'=>$summary['total'],'color'=>'text-slate-900 dark:text-white'],
            ['label'=>'Published','value'=>$summary['published'],'color'=>'text-emerald-500'],
            ['label'=>'Drafts','value'=>$summary['drafts'],'color'=>'text-amber-500'],
            ['label'=>'Archived','value'=>$summary['archived'],'color'=>'text-slate-500'],
            ['label'=>'Total Views','value'=>number_format($summary['total_views']),'color'=>'text-indigo-500'],
            ['label'=>'Conversions','value'=>number_format($summary['total_conv']),'color'=>'text-pink-500'],
        ] as $t)
            <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
                <div class="text-xs text-slate-500">{{ $t['label'] }}</div>
                <div class="text-xl font-bold {{ $t['color'] }}">{{ $t['value'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Settings --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
        <form method="POST" action="{{ route('admin.marketing.landing.settings') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <label class="flex items-center gap-2">
                <input type="checkbox" name="landing_pages_enabled" value="1"
                       {{ $enabled ? 'checked' : '' }} class="rounded border-slate-300 dark:border-slate-700">
                <span class="text-sm">เปิดใช้งาน Landing Pages</span>
            </label>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Default Theme</label>
                <select name="lp_default_theme" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
                    @foreach(['indigo','pink','emerald','amber','slate'] as $c)
                        <option value="{{ $c }}" @selected(old('lp_default_theme', \App\Models\AppSetting::get('marketing_lp_default_theme','indigo')) === $c)>{{ ucfirst($c) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm">บันทึก</button>
        </form>
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-2 items-center">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="ค้นหา title / slug..."
               class="flex-1 min-w-64 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
        <select name="status" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm">
            <option value="">ทุกสถานะ</option>
            @foreach(['draft','published','archived'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <button class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm">Filter</button>
    </form>

    {{-- Table --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-700">
                <tr class="text-left text-xs uppercase text-slate-500">
                    <th class="px-4 py-2">Title</th>
                    <th class="px-4 py-2">Slug</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2 text-right">Views</th>
                    <th class="px-4 py-2 text-right">Conv</th>
                    <th class="px-4 py-2 text-right">CR %</th>
                    <th class="px-4 py-2">Updated</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse($pages as $p)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900 dark:text-white">{{ $p->title }}</div>
                            @if($p->subtitle)<div class="text-xs text-slate-500 truncate max-w-sm">{{ $p->subtitle }}</div>@endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">/lp/{{ $p->slug }}</td>
                        <td class="px-4 py-3">
                            @php
                                $color = ['draft'=>'amber','published'=>'emerald','archived'=>'slate'][$p->status] ?? 'slate';
                            @endphp
                            <span class="px-2 py-0.5 text-xs rounded bg-{{ $color }}-500/20 text-{{ $color }}-500 border border-{{ $color }}-500/30">{{ $p->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono">{{ number_format($p->views) }}</td>
                        <td class="px-4 py-3 text-right font-mono">{{ number_format($p->conversions) }}</td>
                        <td class="px-4 py-3 text-right font-mono text-pink-500">{{ $p->conversionRate() }}%</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $p->updated_at?->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($p->status === 'published')
                                <a href="{{ $p->publicUrl() }}" target="_blank" class="text-slate-500 hover:text-indigo-500" title="View"><i class="bi bi-box-arrow-up-right"></i></a>
                            @endif
                            <a href="{{ route('admin.marketing.landing.edit', $p) }}" class="text-indigo-500 hover:text-indigo-400 ml-2"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('admin.marketing.landing.delete', $p) }}" class="inline" onsubmit="return confirm('ลบ {{ $p->title }} แน่นะ?')">
                                @csrf @method('DELETE')
                                <button class="text-rose-500 hover:text-rose-400 ml-2"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">ยังไม่มี landing page — กด "สร้าง Landing Page" เพื่อเริ่มต้น</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $pages->links() }}</div>
</div>
@endsection

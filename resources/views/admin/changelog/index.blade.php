@extends('layouts.admin')

@section('title', 'Changelog')

@php $types = \App\Models\ChangelogEntry::types(); $audiences = \App\Models\ChangelogEntry::audiences(); @endphp

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-journal-text text-purple-500"></i>Changelog
        <span class="text-xs font-normal text-gray-400 ml-2">/ บันทึกการเปลี่ยนแปลง</span>
    </h4>
    <a href="{{ route('admin.changelog.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">
        <i class="bi bi-plus-lg mr-1"></i>เพิ่มรายการ
    </a>
</div>

@if(session('success'))
    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 rounded-xl p-3 mb-4 text-sm">{{ session('success') }}</div>
@endif

<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-100 dark:border-white/5">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-slate-900/40 text-left text-xs uppercase text-gray-500 dark:text-gray-400">
            <tr>
                <th class="px-4 py-3">Version / วันที่</th>
                <th class="px-4 py-3">ประเภท</th>
                <th class="px-4 py-3">หัวข้อ</th>
                <th class="px-4 py-3">เผยแพร่ให้</th>
                <th class="px-4 py-3">สถานะ</th>
                <th class="px-4 py-3 text-right">การจัดการ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @forelse($entries as $e)
                @php $t = $types[$e->type] ?? ['label' => $e->type, 'icon' => 'bi-circle', 'color' => 'gray']; @endphp
                <tr>
                    <td class="px-4 py-3">
                        <div class="font-mono font-semibold">{{ $e->version }}</div>
                        <div class="text-[11px] text-gray-400">{{ $e->released_on->format('d M Y') }}</div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] bg-{{ $t['color'] }}-500/15 text-{{ $t['color'] }}-700 dark:text-{{ $t['color'] }}-200">
                            <i class="bi {{ $t['icon'] }}"></i>{{ $t['label'] }}
                        </span>
                    </td>
                    <td class="px-4 py-3">{{ $e->title }}</td>
                    <td class="px-4 py-3 text-xs">{{ $audiences[$e->audience] ?? $e->audience }}</td>
                    <td class="px-4 py-3">
                        <form action="{{ route('admin.changelog.toggle', $e) }}" method="POST" class="inline">
                            @csrf
                            <button class="px-2 py-1 text-xs rounded {{ $e->is_published ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-200' : 'bg-gray-200 text-gray-600 dark:bg-slate-700 dark:text-gray-300' }}">
                                <i class="bi {{ $e->is_published ? 'bi-eye' : 'bi-eye-slash' }}"></i>
                                {{ $e->is_published ? 'เผยแพร่' : 'ซ่อน' }}
                            </button>
                        </form>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('admin.changelog.edit', $e) }}" class="px-2 py-1 text-xs border border-indigo-200 text-indigo-700 dark:text-indigo-200 dark:border-indigo-500/30 rounded hover:bg-indigo-50 dark:hover:bg-indigo-900/20">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('admin.changelog.destroy', $e) }}" method="POST" class="inline" onsubmit="return confirm('ลบ?')">
                            @csrf @method('DELETE')
                            <button class="px-2 py-1 text-xs border border-rose-200 text-rose-700 dark:text-rose-200 dark:border-rose-500/30 rounded hover:bg-rose-50 dark:hover:bg-rose-900/20">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">ยังไม่มีรายการ</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($entries->hasPages())
        <div class="p-3 border-t border-gray-100 dark:border-white/5">{{ $entries->links() }}</div>
    @endif
</div>
@endsection

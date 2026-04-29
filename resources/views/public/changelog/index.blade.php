@extends('layouts.app')
@section('title', 'บันทึกการเปลี่ยนแปลง')

@php $types = \App\Models\ChangelogEntry::types(); @endphp

@section('content')
<div class="max-w-3xl mx-auto py-8 px-4">
    <h2 class="text-2xl font-bold mb-2 flex items-center gap-2">
        <i class="bi bi-journal-text text-purple-500"></i>บันทึกการเปลี่ยนแปลง
    </h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
        อัพเดตและฟีเจอร์ใหม่ของแพลตฟอร์ม
    </p>

    @forelse($grouped as $month => $items)
        <div class="mb-8">
            <h3 class="text-lg font-bold text-gray-700 dark:text-gray-200 mb-3 pb-2 border-b border-gray-200 dark:border-white/10">
                {{ \Carbon\Carbon::parse($month . '-01')->translatedFormat('F Y') }}
            </h3>
            <div class="space-y-4">
                @foreach($items as $e)
                    @php $t = $types[$e->type] ?? ['label' => $e->type, 'icon' => 'bi-circle', 'color' => 'gray']; @endphp
                    <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-100 dark:border-white/5">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] bg-{{ $t['color'] }}-500/15 text-{{ $t['color'] }}-700 dark:text-{{ $t['color'] }}-200">
                                <i class="bi {{ $t['icon'] }}"></i>{{ $t['label'] }}
                            </span>
                            <span class="text-xs text-gray-400 font-mono">v{{ $e->version }}</span>
                            <span class="text-xs text-gray-400 ml-auto">{{ $e->released_on->format('d M Y') }}</span>
                        </div>
                        <h4 class="font-semibold text-base">{{ $e->title }}</h4>
                        @if($e->body)
                            <div class="prose prose-sm dark:prose-invert max-w-none mt-2 text-sm text-gray-600 dark:text-gray-300 whitespace-pre-wrap">{{ $e->body }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <p class="text-center text-gray-500 py-12">ยังไม่มีบันทึกการเปลี่ยนแปลง</p>
    @endforelse

    {{ $entries->links() }}
</div>
@endsection

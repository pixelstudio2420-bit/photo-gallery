@extends('layouts.app')

@section('title', 'ประกาศและข่าวสาร')

@section('content')
<div class="max-w-5xl mx-auto p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">📢 ประกาศ &amp; ข่าวสาร</h1>
        <p class="text-slate-500 dark:text-slate-400 mt-1">ข่าวสาร โปรโมชั่น และกิจกรรมล่าสุด</p>
    </div>

    @if($announcements->count() === 0)
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-12 text-center text-slate-500 dark:text-slate-400">
            <i class="bi bi-megaphone text-5xl mb-3 block"></i>
            <p class="mb-0">ยังไม่มีประกาศ</p>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach($announcements as $a)
                <a href="{{ route('announcements.show', $a->slug) }}"
                   class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition group">
                    @if($a->cover_image_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('media.disk', 'r2'))->url($a->cover_image_path) }}"
                             alt="" class="w-full h-44 object-cover">
                    @else
                        <div class="w-full h-44 bg-gradient-to-br from-pink-500 to-purple-600 flex items-center justify-center text-white text-5xl">
                            <i class="bi bi-megaphone"></i>
                        </div>
                    @endif
                    <div class="p-4">
                        <div class="flex items-center gap-2 mb-2 text-xs">
                            @if($a->is_pinned)
                                <span class="text-red-600"><i class="bi bi-pin-angle-fill"></i> ปักหมุด</span>
                            @endif
                            @if($a->priority === 'high')
                                <span class="text-red-600 font-semibold">สำคัญ</span>
                            @endif
                            <span class="text-slate-400 ml-auto">{{ $a->starts_at?->format('d/m/Y') ?? $a->created_at->format('d/m/Y') }}</span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white group-hover:text-pink-600 dark:group-hover:text-pink-400 transition mb-2">
                            {{ $a->title }}
                        </h3>
                        @if($a->excerpt)
                            <p class="text-sm text-slate-600 dark:text-slate-400 line-clamp-3">{{ $a->excerpt }}</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-8">{{ $announcements->links() }}</div>
    @endif
</div>
@endsection

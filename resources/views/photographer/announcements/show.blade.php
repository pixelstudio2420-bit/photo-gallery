@extends('layouts.photographer')

@section('title', $announcement->title)

@section('content')
<article class="max-w-3xl mx-auto p-6">
    <a href="{{ route('photographer.announcements.index') }}"
       class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-indigo-600 mb-4">
        <i class="bi bi-arrow-left"></i> กลับไปหน้ารายการ
    </a>

    @if($announcement->cover_image_path)
        <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('media.disk', 'r2'))->url($announcement->cover_image_path) }}"
             alt="" class="w-full rounded-2xl mb-6 max-h-96 object-cover">
    @endif

    <div class="flex items-center gap-3 text-sm text-slate-500 mb-3">
        @if($announcement->is_pinned)
            <span class="text-red-600 font-semibold"><i class="bi bi-pin-angle-fill"></i> ปักหมุด</span>
        @endif
        @if($announcement->priority === 'high')
            <span class="px-2 py-1 rounded bg-red-100 text-red-700 text-xs font-bold">สำคัญ</span>
        @endif
        <span>{{ $announcement->starts_at?->format('d M Y H:i') ?? $announcement->created_at->format('d M Y H:i') }}</span>
        <span class="ml-auto"><i class="bi bi-eye"></i> {{ number_format($announcement->view_count) }}</span>
    </div>

    <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 dark:text-white mb-4">{{ $announcement->title }}</h1>

    @if($announcement->excerpt)
        <p class="text-lg text-slate-600 dark:text-slate-400 mb-6">{{ $announcement->excerpt }}</p>
    @endif

    @if($announcement->body)
        <div class="prose prose-slate dark:prose-invert max-w-none">
            {!! $announcement->body !!}
        </div>
    @endif

    @if($announcement->cta_label && $announcement->cta_url)
        <div class="mt-8">
            <a href="{{ $announcement->cta_url }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition">
                {{ $announcement->cta_label }} <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    @endif

    @if($announcement->attachments->count() > 0)
        <div class="mt-10">
            <h2 class="text-xl font-bold mb-4">รูปประกอบ</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                @foreach($announcement->attachments as $att)
                    <figure>
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('media.disk', 'r2'))->url($att->image_path) }}"
                             alt="" class="w-full h-48 object-cover rounded-xl">
                        @if($att->caption)
                            <figcaption class="text-xs text-slate-500 mt-1">{{ $att->caption }}</figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
        </div>
    @endif
</article>
@endsection

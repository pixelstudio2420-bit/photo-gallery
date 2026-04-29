@extends('layouts.app')

@section('title', data_get($page->seo, 'title', $page->title))

@push('head')
    @if($desc = data_get($page->seo, 'description'))
        <meta name="description" content="{{ $desc }}">
    @endif
    <meta property="og:title" content="{{ data_get($page->seo, 'title', $page->title) }}">
    @if($desc) <meta property="og:description" content="{{ $desc }}"> @endif
    @if($og = data_get($page->seo, 'og_image', $page->hero_image))
        <meta property="og:image" content="{{ $og }}">
    @endif
@endpush

@section('content')
<article class="lp-page">

    {{-- Hero --}}
    <section class="relative overflow-hidden {{ $page->themeGradient() }} text-white">
        <div class="absolute inset-0 opacity-20 bg-[url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2280%22 viewBox=%220 0 80 80%22><circle cx=%2240%22 cy=%2240%22 r=%221%22 fill=%22white%22/></svg>')]"></div>
        <div class="relative max-w-5xl mx-auto px-4 py-20 md:py-28 text-center">
            <h1 class="text-3xl md:text-5xl font-bold leading-tight">{{ $page->title }}</h1>
            @if($page->subtitle)
                <p class="mt-4 text-lg md:text-xl text-white/90 max-w-3xl mx-auto">{{ $page->subtitle }}</p>
            @endif
            @if($page->cta_label && $page->cta_url)
                <div class="mt-8">
                    <a href="{{ route('marketing.landing.cta', $page) }}"
                       class="inline-flex items-center gap-2 px-8 py-3 rounded-full bg-white text-slate-900 font-bold hover:scale-105 transition shadow-xl">
                        {{ $page->cta_label }} <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            @endif
            @if($page->hero_image)
                <img src="{{ $page->hero_image }}" alt="{{ $page->title }}"
                     class="mt-10 mx-auto max-w-3xl w-full rounded-2xl shadow-2xl">
            @endif
        </div>
    </section>

    {{-- Dynamic sections --}}
    @foreach(($page->sections ?? []) as $block)
        @php $type = $block['type'] ?? null; $d = $block['data'] ?? []; @endphp

        @if($type === 'heading')
            <section class="max-w-5xl mx-auto px-4 py-12 text-center">
                @if(!empty($d['heading']))<h2 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white">{{ $d['heading'] }}</h2>@endif
                @if(!empty($d['sub']))<p class="mt-3 text-base text-slate-600 dark:text-slate-400">{{ $d['sub'] }}</p>@endif
            </section>

        @elseif($type === 'text')
            <section class="max-w-3xl mx-auto px-4 py-8">
                <div class="prose prose-slate dark:prose-invert max-w-none">
                    {!! \App\Support\HtmlSanitizer::clean(
                        (string) \Illuminate\Support\Str::of($d['body'] ?? '')
                            ->replaceMatches('/\*\*(.+?)\*\*/', '<strong>$1</strong>')
                            ->replaceMatches('/\[(.+?)\]\((https?:\/\/[^\s\)]+)\)/', '<a href="$2" class="text-indigo-500 hover:underline">$1</a>')
                            ->replace("\n", '<br>')
                    ) !!}
                </div>
            </section>

        @elseif($type === 'image')
            <section class="max-w-5xl mx-auto px-4 py-8 text-center">
                @if(!empty($d['src']))
                    <img src="{{ $d['src'] }}" alt="{{ $d['alt'] ?? '' }}" class="mx-auto rounded-xl shadow-lg max-w-full">
                    @if(!empty($d['caption']))<p class="mt-3 text-xs text-slate-500">{{ $d['caption'] }}</p>@endif
                @endif
            </section>

        @elseif($type === 'features')
            @php
                $items = [];
                foreach (preg_split("/\r?\n/", $d['raw'] ?? '') as $line) {
                    $parts = array_map('trim', explode('|', $line));
                    if (count($parts) >= 2) $items[] = [
                        'icon' => $parts[0] ?? 'bi-check', 'title' => $parts[1] ?? '', 'body' => $parts[2] ?? '',
                    ];
                }
            @endphp
            <section class="max-w-5xl mx-auto px-4 py-12">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($items as $f)
                        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-6 text-center">
                            <i class="bi {{ $f['icon'] }} text-3xl text-indigo-500"></i>
                            <h3 class="mt-3 font-bold text-slate-900 dark:text-white">{{ $f['title'] }}</h3>
                            @if($f['body'])<p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ $f['body'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </section>

        @elseif($type === 'testimonial')
            <section class="max-w-3xl mx-auto px-4 py-12">
                <div class="rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 p-8 text-center">
                    <i class="bi bi-quote text-4xl text-indigo-500"></i>
                    <p class="mt-2 text-lg italic text-slate-700 dark:text-slate-300">{{ $d['quote'] ?? '' }}</p>
                    <div class="mt-4 font-semibold text-slate-900 dark:text-white">{{ $d['author'] ?? '' }}</div>
                    @if(!empty($d['role']))<div class="text-sm text-slate-500">{{ $d['role'] }}</div>@endif
                </div>
            </section>

        @elseif($type === 'faq')
            @php
                $faqs = [];
                foreach (preg_split("/\r?\n/", $d['raw'] ?? '') as $line) {
                    $parts = array_map('trim', explode('||', $line));
                    if (count($parts) >= 2) $faqs[] = ['q' => $parts[0], 'a' => $parts[1]];
                }
            @endphp
            <section class="max-w-3xl mx-auto px-4 py-12">
                <div class="space-y-2" x-data="{ open: null }">
                    @foreach($faqs as $i => $f)
                        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900">
                            <button type="button" @click="open = open==={{ $i }} ? null : {{ $i }}"
                                    class="w-full flex items-center justify-between px-4 py-3 text-left">
                                <span class="font-semibold text-slate-900 dark:text-white">{{ $f['q'] }}</span>
                                <i class="bi bi-chevron-down text-slate-500 transition" :class="open==={{ $i }} ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open==={{ $i }}" x-collapse class="px-4 pb-3 text-sm text-slate-600 dark:text-slate-400">
                                {{ $f['a'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

        @elseif($type === 'cta')
            <section class="max-w-5xl mx-auto px-4 py-16 text-center">
                <div class="rounded-3xl {{ $page->themeGradient() }} p-10 text-white">
                    <h3 class="text-2xl md:text-3xl font-bold">{{ $d['label'] ?? 'เริ่มเลย' }}</h3>
                    @if(!empty($d['note']))<p class="mt-2 text-white/80">{{ $d['note'] }}</p>@endif
                    @if(!empty($d['url']))
                        <a href="{{ $d['url'] }}" class="mt-5 inline-flex items-center gap-2 px-8 py-3 rounded-full bg-white text-slate-900 font-bold hover:scale-105 transition">
                            {{ $d['label'] ?? 'Go' }} <i class="bi bi-arrow-right"></i>
                        </a>
                    @endif
                </div>
            </section>
        @endif
    @endforeach

    {{-- Bottom CTA --}}
    @if($page->cta_label && $page->cta_url)
        <section class="max-w-3xl mx-auto px-4 py-16 text-center">
            <a href="{{ route('marketing.landing.cta', $page) }}"
               class="inline-flex items-center gap-2 px-8 py-3 rounded-full {{ $page->themeGradient() }} text-white font-bold hover:scale-105 transition shadow-xl">
                {{ $page->cta_label }} <i class="bi bi-arrow-right"></i>
            </a>
        </section>
    @endif
</article>
@endsection

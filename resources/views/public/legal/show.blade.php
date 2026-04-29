@extends('layouts.app')

@section('title', $page->title)

@push('styles')
<style>
  .legal-prose { line-height: 1.85; font-size: 0.95rem; }
  .legal-prose h2 { font-size: 1.4rem; font-weight: 700; margin-top: 2.2rem; margin-bottom: 0.8rem; color: #1e293b; letter-spacing: -0.01em; }
  .legal-prose h3 { font-size: 1.1rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.5rem; color: #334155; }
  .legal-prose p  { margin-bottom: 1rem; color: #475569; }
  .legal-prose ul, .legal-prose ol { margin: 0.75rem 0 1.25rem 1.5rem; color: #475569; }
  .legal-prose ul { list-style: disc; }
  .legal-prose ol { list-style: decimal; }
  .legal-prose li { margin-bottom: 0.4rem; }
  .legal-prose a  { color: #6366f1; text-decoration: underline; text-decoration-thickness: 1px; text-underline-offset: 2px; }
  .legal-prose a:hover { color: #4f46e5; }
  .legal-prose strong { color: #1e293b; font-weight: 600; }
  .legal-prose .lead { font-size: 1.1rem; color: #334155; margin-bottom: 1.5rem; padding: 1rem 1.25rem; background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(79,70,229,0.03)); border-left: 4px solid #6366f1; border-radius: 8px; }
  .legal-prose code { background: #f1f5f9; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.85em; color: #be185d; }

  /* Dark mode */
  html.dark .legal-prose h2 { color: #f1f5f9; }
  html.dark .legal-prose h3 { color: #e2e8f0; }
  html.dark .legal-prose p,
  html.dark .legal-prose ul,
  html.dark .legal-prose ol { color: #cbd5e1; }
  html.dark .legal-prose strong { color: #f8fafc; }
  html.dark .legal-prose .lead { color: #e2e8f0; background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(79,70,229,0.06)); }
  html.dark .legal-prose a { color: #a5b4fc; }
  html.dark .legal-prose a:hover { color: #c7d2fe; }
  html.dark .legal-prose code { background: rgba(255,255,255,0.08); color: #f472b6; }
</style>
@endpush

@section('og-meta')
@include('layouts.partials.og-meta', [
  'title'       => $page->title . ' — ' . config('app.name'),
  'description' => $page->meta_description ?? $page->title,
])
@endsection

@section('content')
<div class="dark:bg-none dark:from-transparent dark:via-transparent dark:to-transparent">
  <div class="max-w-4xl mx-auto px-4 pt-10 pb-6">
    <nav class="text-xs text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-1">
      <a href="{{ route('home') }}" class="hover:text-indigo-500"><i class="bi bi-house-door"></i> หน้าแรก</a>
      <i class="bi bi-chevron-right text-gray-300 mx-1"></i>
      <span class="text-gray-700 dark:text-gray-300">{{ $page->title }}</span>
    </nav>

    @php
      $icon = match($page->slug) {
        'privacy-policy'   => 'bi-shield-lock-fill',
        'terms-of-service' => 'bi-file-earmark-ruled-fill',
        'refund-policy'    => 'bi-cash-coin',
        default            => 'bi-file-earmark-text-fill',
      };
    @endphp
    <div class="flex items-start gap-4 mb-6">
      <div class="shrink-0 w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-600 text-white flex items-center justify-center shadow-lg shadow-indigo-500/30">
        <i class="bi {{ $icon }} text-2xl"></i>
      </div>
      <div class="flex-1 min-w-0">
        <h1 class="text-3xl md:text-4xl font-bold text-slate-800 dark:text-gray-100 mb-2 tracking-tight" style="letter-spacing:-0.02em;">
          {{ $page->title }}
        </h1>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
          <span><i class="bi bi-tag"></i> เวอร์ชัน <strong class="text-slate-700 dark:text-gray-200">v{{ $page->version }}</strong></span>
          @if($page->effective_date)
          <span><i class="bi bi-calendar-check"></i> มีผลตั้งแต่ <strong class="text-slate-700 dark:text-gray-200">{{ $page->effective_date->format('d F Y') }}</strong></span>
          @endif
          <span><i class="bi bi-arrow-clockwise"></i> ปรับปรุงล่าสุด <strong class="text-slate-700 dark:text-gray-200">{{ $page->updated_at?->diffForHumans() }}</strong></span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="max-w-4xl mx-auto px-4 pb-16">
  <article class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-100 dark:border-white/10 p-6 md:p-10 legal-prose">
    {!! \App\Support\HtmlSanitizer::clean($page->content) !!}
  </article>

  @if($otherPages->isNotEmpty())
  <div class="mt-8 bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-100 dark:border-white/10 p-6">
    <h6 class="font-semibold text-slate-800 dark:text-gray-100 mb-3 text-sm">
      <i class="bi bi-link-45deg text-indigo-500 mr-1"></i> เอกสารที่เกี่ยวข้อง
    </h6>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
      @foreach($otherPages as $other)
        @php
          $otherIcon = match($other->slug) {
            'privacy-policy'   => 'bi-shield-lock',
            'terms-of-service' => 'bi-file-earmark-ruled',
            'refund-policy'    => 'bi-cash-coin',
            default            => 'bi-file-earmark-text',
          };
        @endphp
        <a href="{{ route('legal.show', $other->slug) }}"
           class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 dark:border-white/5 hover:border-indigo-200 dark:hover:border-indigo-500/30 hover:bg-indigo-50/50 dark:hover:bg-indigo-500/5 transition group no-underline">
          <i class="bi {{ $otherIcon }} text-indigo-500 text-lg"></i>
          <span class="flex-1 text-sm font-medium text-slate-700 dark:text-gray-200">{{ $other->title }}</span>
          <i class="bi bi-arrow-right text-gray-300 group-hover:text-indigo-500 group-hover:translate-x-0.5 transition"></i>
        </a>
      @endforeach
    </div>
  </div>
  @endif

  <div class="text-center mt-8 text-xs text-gray-400 dark:text-gray-500">
    <p>หากมีคำถามเพิ่มเติม กรุณา <a href="{{ route('contact') }}" class="text-indigo-500 hover:text-indigo-700">ติดต่อเรา</a></p>
  </div>
</div>
@endsection

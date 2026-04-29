@props([
    'variant' => 'card',   // card | inline | footer
    'heading' => null,
    'body'    => null,
])

@php
    $marketingSvc = app(\App\Services\Marketing\MarketingService::class);
    $enabled = $marketingSvc->enabled('newsletter');
@endphp

@if($enabled)
    @if($variant === 'inline')
        <form method="POST" action="{{ route('newsletter.subscribe') }}"
              class="flex items-center gap-2 w-full max-w-md" {{ $attributes }}>
            @csrf
            <input type="hidden" name="source" value="inline">
            <input type="email" name="email" required placeholder="อีเมลของคุณ"
                   class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
            <button class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold shrink-0">
                Subscribe
            </button>
        </form>

    @elseif($variant === 'footer')
        <div {{ $attributes->merge(['class' => 'w-full']) }}>
            <h3 class="text-sm font-bold text-white mb-2">
                <i class="bi bi-envelope-heart text-pink-400"></i> {{ $heading ?? 'รับข่าวสาร + promotion' }}
            </h3>
            <p class="text-xs text-slate-400 mb-3">{{ $body ?? 'สมัครรับจดหมายข่าว — ไม่มี spam' }}</p>
            <form method="POST" action="{{ route('newsletter.subscribe') }}" class="flex gap-2">
                @csrf
                <input type="hidden" name="source" value="footer">
                <input type="email" name="email" required placeholder="email@domain.com"
                       class="flex-1 px-3 py-2 rounded-lg bg-slate-900 border border-slate-700 text-white text-sm focus:border-indigo-500 focus:outline-none">
                <button class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm shrink-0">OK</button>
            </form>
            @if(session('success'))
                <div class="mt-2 text-xs text-emerald-400">{{ session('success') }}</div>
            @elseif(session('error'))
                <div class="mt-2 text-xs text-rose-400">{{ session('error') }}</div>
            @endif
        </div>

    @else
        {{-- card --}}
        <div {{ $attributes->merge(['class' => 'rounded-2xl border border-indigo-500/30 bg-gradient-to-br from-indigo-500/10 to-purple-500/10 p-6']) }}>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                <i class="bi bi-envelope-heart text-pink-500"></i>
                {{ $heading ?? 'รับข่าวสาร + ส่วนลด' }}
            </h3>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
                {{ $body ?? 'สมัครรับจดหมายข่าวรายเดือน — ได้รับ promotion, tips, และข่าวใหม่ก่อนใคร' }}
            </p>
            <form method="POST" action="{{ route('newsletter.subscribe') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="source" value="card">
                <div class="flex gap-2">
                    <input type="text" name="name" placeholder="ชื่อ (optional)"
                           class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                </div>
                <div class="flex gap-2">
                    <input type="email" name="email" required placeholder="email@domain.com"
                           class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none">
                    <button class="px-6 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold">
                        Subscribe
                    </button>
                </div>
                <p class="text-[0.68rem] text-slate-500">
                    <i class="bi bi-shield-check"></i> ไม่มี spam — ยกเลิกได้ทุกเมื่อ
                </p>
            </form>
        </div>
    @endif
@endif

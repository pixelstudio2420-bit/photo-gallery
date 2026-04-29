@extends('layouts.photographer')
@section('title', 'Store · ซื้อโปรโมท + บริการเสริม')

@section('content')
<div class="max-w-[1400px] mx-auto px-4 py-6">

  <div class="mb-6">
    <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white mb-1">Store</h1>
    <p class="text-sm text-slate-600 dark:text-slate-400">
      โปรโมทช่างภาพให้ขึ้นอันดับสูง · ซื้อบริการเสริมพื้นที่ + AI credits + branding
    </p>
  </div>

  @if(session('success'))
    <div class="mb-5 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 text-emerald-700 dark:text-emerald-300 text-sm">
      <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
  @endif
  @if($errors->any())
    <div class="mb-5 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
      @foreach($errors->all() as $e)<div>· {{ $e }}</div>@endforeach
    </div>
  @endif

  {{-- ── Category sections ─────────────────────────────────────────── --}}
  @foreach($catalog as $key => $cat)
    <section class="mb-8" style="--cat-accent: {{ $cat['accent'] }};">
      <div class="flex items-end justify-between mb-3">
        <div>
          <h2 class="text-xl font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
            <i class="bi {{ $cat['icon'] }}" style="color: var(--cat-accent);"></i>
            {{ $cat['title'] }}
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 max-w-2xl">{{ $cat['description'] }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @foreach($cat['items'] as $item)
        <div class="relative rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 p-4 hover:-translate-y-1 transition shadow-sm hover:shadow-lg flex flex-col">

          @if(!empty($item['badge']))
            <span class="absolute -top-2 right-3 inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-amber-400 text-amber-900 shadow">
              <i class="bi bi-star-fill"></i> {{ $item['badge'] }}
            </span>
          @endif

          <div class="text-base font-bold text-slate-900 dark:text-white">{{ $item['label'] }}</div>
          @if(!empty($item['tagline']))
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $item['tagline'] }}</div>
          @endif

          <div class="mt-3 mb-4">
            <span class="text-2xl font-extrabold text-slate-900 dark:text-white">
              ฿{{ number_format($item['price_thb'], 0) }}
            </span>
            @if(!empty($item['cycle']) && $item['cycle'] !== 'pay_per_use' && empty($item['one_time']))
              <span class="text-xs text-slate-500 dark:text-slate-400">
                / {{ ['daily' => 'วัน', 'monthly' => 'เดือน', 'yearly' => 'ปี'][$item['cycle']] ?? $item['cycle'] }}
              </span>
            @elseif(!empty($item['one_time']))
              <span class="text-xs text-slate-500">· ตลอดอายุ subscription</span>
            @endif
          </div>

          {{-- Per-category specifics --}}
          @if($key === 'storage')
            <div class="text-xs text-slate-600 dark:text-slate-400 mb-4 flex items-center gap-1.5">
              <i class="bi bi-hdd"></i> +{{ number_format($item['storage_gb']) }} GB
            </div>
          @elseif($key === 'ai_credits')
            <div class="text-xs text-slate-600 dark:text-slate-400 mb-4 flex items-center gap-1.5">
              <i class="bi bi-cpu"></i> +{{ number_format($item['credits']) }} credits
            </div>
          @elseif($key === 'promotion')
            <div class="text-xs text-slate-600 dark:text-slate-400 mb-4 flex items-center gap-1.5">
              <i class="bi bi-arrow-up-circle"></i> Boost +{{ $item['boost_score'] }} pts
            </div>
          @endif

          <form method="POST" action="{{ route('photographer.store.buy', ['sku' => $item['sku']]) }}" class="mt-auto">
            @csrf
            <button class="w-full px-3 py-2 rounded-lg font-bold text-sm transition text-white"
                    style="background: var(--cat-accent);">
              <i class="bi bi-bag-plus"></i> ซื้อตอนนี้
            </button>
          </form>
        </div>
        @endforeach
      </div>
    </section>
  @endforeach

  {{-- ── Recent purchases ────────────────────────────────────────── --}}
  @if($history->count() > 0)
  <section class="mt-10">
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-lg font-bold text-slate-900 dark:text-white">การซื้อล่าสุดของคุณ</h2>
      <a href="{{ route('photographer.store.history') }}" class="text-xs text-indigo-600 dark:text-indigo-300 hover:underline">ดูทั้งหมด →</a>
    </div>
    <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase text-slate-500">
          <tr><th class="px-3 py-2 text-left">รายการ</th><th class="px-3 py-2 text-left">หมวด</th><th class="px-3 py-2 text-right">฿</th><th class="px-3 py-2 text-left">สถานะ</th><th class="px-3 py-2 text-left">เมื่อ</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-white/10">
          @foreach($history->take(5) as $h)
          @php $snap = json_decode($h->snapshot ?? '{}', true) ?: []; @endphp
          <tr>
            <td class="px-3 py-2 font-medium">{{ $snap['label'] ?? $h->sku }}</td>
            <td class="px-3 py-2 text-xs text-slate-500">{{ $h->category }}</td>
            <td class="px-3 py-2 text-right font-bold">฿{{ number_format($h->price_thb, 0) }}</td>
            <td class="px-3 py-2">
              <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase
                @if($h->status === 'activated') bg-emerald-100 text-emerald-700
                @elseif($h->status === 'paid') bg-sky-100 text-sky-700
                @elseif($h->status === 'pending') bg-amber-100 text-amber-700
                @else bg-rose-100 text-rose-700 @endif">{{ $h->status }}</span>
            </td>
            <td class="px-3 py-2 text-xs text-slate-500">{{ \Carbon\Carbon::parse($h->created_at)->diffForHumans() }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
  @endif

</div>
@endsection

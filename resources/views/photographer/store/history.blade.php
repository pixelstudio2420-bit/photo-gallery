@extends('layouts.photographer')
@section('title', 'ประวัติการซื้อ')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">
  <a href="{{ route('photographer.store.index') }}" class="text-xs text-slate-500 hover:underline">← Store</a>
  <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mb-1 mt-1">ประวัติการซื้อ</h1>
  <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">รายการ promotion + บริการเสริมที่คุณซื้อ</p>

  <div class="rounded-2xl bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase text-slate-500">
        <tr>
          <th class="px-3 py-2 text-left">รายการ</th>
          <th class="px-3 py-2 text-left">หมวด</th>
          <th class="px-3 py-2 text-right">ราคา</th>
          <th class="px-3 py-2 text-left">เปิดใช้</th>
          <th class="px-3 py-2 text-left">หมดอายุ</th>
          <th class="px-3 py-2 text-left">สถานะ</th>
          <th class="px-3 py-2 text-left">ซื้อเมื่อ</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200 dark:divide-white/10">
        @forelse($history as $h)
          @php $snap = json_decode($h->snapshot ?? '{}', true) ?: []; @endphp
          <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
            <td class="px-3 py-2 font-medium">
              {{ $snap['label'] ?? $h->sku }}
              @if(!empty($snap['tagline']))
                <div class="text-[11px] text-slate-500">{{ $snap['tagline'] }}</div>
              @endif
            </td>
            <td class="px-3 py-2 text-xs text-slate-500">{{ $h->category }}</td>
            <td class="px-3 py-2 text-right font-bold">฿{{ number_format($h->price_thb, 0) }}</td>
            <td class="px-3 py-2 text-xs text-slate-500">
              {{ $h->activated_at ? \Carbon\Carbon::parse($h->activated_at)->format('d M y H:i') : '—' }}
            </td>
            <td class="px-3 py-2 text-xs text-slate-500">
              {{ $h->expires_at ? \Carbon\Carbon::parse($h->expires_at)->format('d M y') : 'ตลอดชีพ' }}
            </td>
            <td class="px-3 py-2">
              <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase
                @if($h->status === 'activated') bg-emerald-100 text-emerald-700
                @elseif($h->status === 'paid')  bg-sky-100 text-sky-700
                @elseif($h->status === 'pending') bg-amber-100 text-amber-700
                @elseif($h->status === 'failed' || $h->status === 'refunded') bg-rose-100 text-rose-700
                @else                            bg-slate-100 text-slate-600 @endif">{{ $h->status }}</span>
            </td>
            <td class="px-3 py-2 text-xs text-slate-500">{{ \Carbon\Carbon::parse($h->created_at)->diffForHumans() }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="px-3 py-12 text-center text-slate-500">
              ยังไม่มีประวัติการซื้อ — <a href="{{ route('photographer.store.index') }}" class="text-indigo-600 hover:underline">ไปที่ Store</a>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $history->links() }}</div>
</div>
@endsection

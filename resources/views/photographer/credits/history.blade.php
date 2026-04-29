@extends('layouts.photographer')

@section('title', 'ประวัติการใช้เครดิต')

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-clock-history',
  'eyebrow'  => 'บริการเสริม',
  'title'    => 'ประวัติการใช้เครดิต',
  'subtitle' => 'บันทึกการเคลื่อนไหวเครดิตทุกรายการ — ซื้อ / ใช้ / คืน / หมดอายุ',
  'actions'  => '<div class="pg-btn-ghost"><i class="bi bi-coin text-amber-500"></i> คงเหลือ <strong>'.number_format($balance).'</strong></div>',
])

{{-- Filter bar --}}
<div class="pg-card pg-card-padded mb-4 pg-anim d1 flex flex-wrap gap-2 items-center" style="padding:.85rem 1rem;">
  <span class="text-xs text-gray-500 mr-2 font-bold uppercase tracking-wider">กรอง:</span>
  <a href="{{ route('photographer.credits.history') }}"
     class="px-3 py-1.5 rounded-lg text-xs font-bold transition
            {{ !$currentKind ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
    ทั้งหมด
  </a>
  @foreach($kinds as $k)
    <a href="{{ route('photographer.credits.history', ['kind' => $k]) }}"
       class="px-3 py-1.5 rounded-lg text-xs font-bold transition
              {{ $currentKind === $k ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-sm' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
      {{ $k }}
    </a>
  @endforeach
</div>

<div class="pg-table-wrap pg-anim d2">
  <table class="pg-table">
    <thead>
      <tr>
        <th>วันที่</th>
        <th>ประเภท</th>
        <th>อ้างอิง</th>
        <th>แพ็คเก็จ</th>
        <th class="text-end">เปลี่ยนแปลง</th>
        <th class="text-end">ยอดคงเหลือ</th>
      </tr>
    </thead>
    <tbody>
      @forelse($transactions as $tx)
        <tr>
          <td class="whitespace-nowrap text-xs text-gray-600">
            {{ $tx->created_at?->format('d M Y H:i') }}
          </td>
          <td>
            @php
              $kindPill = match($tx->kind) {
                'purchase' => 'pg-pill--violet',
                'consume'  => 'pg-pill--gray',
                'refund'   => 'pg-pill--blue',
                'grant'    => 'pg-pill--amber',
                'bonus'    => 'pg-pill--violet',
                'expire'   => 'pg-pill--rose',
                'adjust'   => 'pg-pill--violet',
                default    => 'pg-pill--gray',
              };
            @endphp
            <span class="pg-pill {{ $kindPill }}">{{ $tx->kind_label }}</span>
          </td>
          <td class="text-xs text-gray-600">
            @if($tx->reference_type)
              {{ $tx->reference_type }}{{ $tx->reference_id ? ' #'.$tx->reference_id : '' }}
            @else
              <span class="text-gray-400">—</span>
            @endif
          </td>
          <td class="text-xs text-gray-700">
            {{ $tx->bundle?->package?->name ?? ($tx->bundle?->note ?? '—') }}
          </td>
          <td class="text-end is-mono font-bold {{ $tx->delta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
            {{ $tx->delta >= 0 ? '+' : '' }}{{ number_format($tx->delta) }}
          </td>
          <td class="text-end is-mono font-semibold">
            {{ number_format($tx->balance_after) }}
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6">
            <div class="pg-empty">
              <div class="pg-empty-icon"><i class="bi bi-receipt"></i></div>
              <p class="font-medium">ไม่พบรายการที่ตรงกับตัวกรอง</p>
            </div>
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-4">
  {{ $transactions->links() }}
</div>
@endsection

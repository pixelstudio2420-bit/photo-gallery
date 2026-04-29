@extends('layouts.photographer')

@section('title', 'ซื้อเครดิตอัปโหลด')

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-bag-plus',
  'eyebrow'  => 'บริการเสริม',
  'title'    => 'ซื้อเครดิตอัปโหลด',
  'subtitle' => 'เลือกแพ็คเก็จที่เหมาะกับปริมาณงาน — 1 เครดิต = 1 ภาพ ไม่หักค่าคอมมิชชั่น',
  'actions'  => '<div class="pg-btn-ghost"><i class="bi bi-coin text-amber-500"></i> คงเหลือ <strong>'.number_format($balance).'</strong> เครดิต</div>',
])

@if(session('error'))
  <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-900 text-sm px-4 py-3">
    <i class="bi bi-exclamation-triangle-fill mr-1.5"></i>{{ session('error') }}
  </div>
@endif

{{-- Value-prop banner --}}
<div class="mb-8 grid grid-cols-2 md:grid-cols-4 gap-3 text-center text-xs">
  <div class="bg-white rounded-lg border border-gray-100 py-3">
    <i class="bi bi-percent text-emerald-600 text-xl"></i>
    <p class="mt-1 font-medium text-gray-700">ขายภาพ 0% ค่าคอม</p>
  </div>
  <div class="bg-white rounded-lg border border-gray-100 py-3">
    <i class="bi bi-lightning-charge-fill text-amber-500 text-xl"></i>
    <p class="mt-1 font-medium text-gray-700">เครดิตเข้าทันทีหลังจ่ายเงิน</p>
  </div>
  <div class="bg-white rounded-lg border border-gray-100 py-3">
    <i class="bi bi-shield-check text-indigo-600 text-xl"></i>
    <p class="mt-1 font-medium text-gray-700">ไม่ต้องส่งบัตร/KYC</p>
  </div>
  <div class="bg-white rounded-lg border border-gray-100 py-3">
    <i class="bi bi-arrow-repeat text-sky-600 text-xl"></i>
    <p class="mt-1 font-medium text-gray-700">คืนเครดิตอัตโนมัติถ้าลบภายใน 5 นาที</p>
  </div>
</div>

{{-- Package grid --}}
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
  @forelse($packages as $p)
    @php
      $perCredit = $p->credits > 0 ? ($p->price_thb / $p->credits) : 0;
      $color = $p->color_hex ?: '#6366f1';
    @endphp
    <div class="relative bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition overflow-hidden flex flex-col">
      {{-- Badge ribbon --}}
      @if($p->badge)
        <div class="absolute top-3 right-3 text-[10px] uppercase tracking-widest font-bold text-white px-2 py-0.5 rounded-full"
             style="background-color: {{ $color }};">
          {{ $p->badge }}
        </div>
      @endif

      {{-- Header --}}
      <div class="p-5 pb-3 border-b border-gray-100" style="background: linear-gradient(135deg, {{ $color }}18, transparent);">
        <h3 class="text-lg font-bold text-gray-900">{{ $p->name }}</h3>
        @if($p->description)
          <p class="text-xs text-gray-500 mt-1">{{ $p->description }}</p>
        @endif
      </div>

      {{-- Stats --}}
      <div class="p-5 flex-1">
        <div class="flex items-end gap-1 mb-1">
          <span class="text-4xl font-extrabold tracking-tight" style="color: {{ $color }};">{{ number_format($p->credits) }}</span>
          <span class="text-sm text-gray-500 mb-1.5">เครดิต</span>
        </div>
        <div class="text-2xl font-bold text-gray-900 mb-3">
          ฿{{ number_format($p->price_thb, 0) }}
          <span class="text-xs text-gray-400 font-normal">
            (฿{{ number_format($perCredit, 2) }} / เครดิต)
          </span>
        </div>

        <ul class="text-sm text-gray-700 space-y-1.5">
          <li class="flex items-start gap-2">
            <i class="bi bi-check-circle-fill text-emerald-500 mt-0.5"></i>
            <span>อัปโหลดได้ <strong>{{ number_format($p->credits) }}</strong> ภาพ</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="bi bi-check-circle-fill text-emerald-500 mt-0.5"></i>
            <span>
              อายุเครดิต
              @if($p->validity_days > 0)
                <strong>{{ $p->validity_days }}</strong> วันหลังจ่ายเงิน
              @else
                <strong>ไม่มีวันหมดอายุ</strong>
              @endif
            </span>
          </li>
          <li class="flex items-start gap-2">
            <i class="bi bi-check-circle-fill text-emerald-500 mt-0.5"></i>
            <span>ขายภาพ / ขายอีเวนต์: หัก <strong>0%</strong></span>
          </li>
        </ul>
      </div>

      {{-- CTA --}}
      <form method="POST" action="{{ route('photographer.credits.buy', ['code' => $p->code]) }}" class="p-5 pt-0">
        @csrf
        <button type="submit"
                class="w-full py-2.5 rounded-lg text-white text-sm font-semibold hover:opacity-90 transition"
                style="background-color: {{ $color }};">
          <i class="bi bi-bag-check"></i> ซื้อแพ็คเก็จนี้
        </button>
      </form>
    </div>
  @empty
    <div class="col-span-full text-center py-12 text-gray-500">
      <i class="bi bi-inbox text-3xl"></i>
      <p class="mt-2 text-sm">ยังไม่มีแพ็คเก็จเปิดขาย กรุณาติดต่อผู้ดูแลระบบ</p>
    </div>
  @endforelse
</div>

<div class="mt-10 bg-gray-50 border border-gray-100 rounded-xl p-5 text-sm text-gray-600">
  <p class="font-semibold text-gray-800 mb-2"><i class="bi bi-info-circle mr-1"></i>คำถามที่พบบ่อย</p>
  <ul class="space-y-2 list-disc list-inside">
    <li>1 เครดิต ใช้อัปโหลดรูปต้นฉบับได้ 1 ภาพ ไม่จำกัดขนาดไฟล์ (ไม่เกิน quota)</li>
    <li>เครดิตเข้าเข้าบัญชีอัตโนมัติทันทีที่ระบบตรวจพบการชำระเงิน</li>
    <li>หากลบภาพภายใน 5 นาทีแรก เครดิตจะคืนให้อัตโนมัติ</li>
    <li>เครดิตที่หมดอายุจะถูกล้างทุกคืน หลังหมดอายุแล้วไม่สามารถเรียกคืนได้</li>
  </ul>
</div>
@endsection

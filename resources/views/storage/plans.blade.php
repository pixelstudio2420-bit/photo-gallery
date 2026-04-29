@extends('layouts.app')

@section('title', 'เปลี่ยนแผน — คลาวด์ของฉัน')

@section('content')
@php
  $currentCode = $summary['plan']->code ?? null;
@endphp
<div class="max-w-6xl mx-auto py-6">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-xl font-bold text-gray-900">เลือก / เปลี่ยนแผนสมัครสมาชิก</h1>
      <p class="text-xs text-gray-500 mt-1">
        อัปเกรดจะมีผลทันที ส่วนการดาวน์เกรดจะมีผลเมื่อครบรอบบิล
      </p>
    </div>
    <a href="{{ route('storage.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
      <i class="bi bi-arrow-left"></i> กลับ
    </a>
  </div>

  @if(session('error'))
    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-900 text-sm px-4 py-3">
      <i class="bi bi-exclamation-triangle-fill mr-1.5"></i>{{ session('error') }}
    </div>
  @endif
  @if(session('success'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-4 py-3">
      <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
    </div>
  @endif

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
    @foreach($plans as $p)
      @php
        $isCurrent = $p->code === $currentCode;
        $accent    = $p->color_hex ?: '#6366f1';
      @endphp
      <div class="relative bg-white rounded-xl border {{ $isCurrent ? 'border-indigo-400 ring-2 ring-indigo-100' : 'border-gray-200' }} shadow-sm flex flex-col overflow-hidden">
        @if($p->badge)
          <div class="absolute -top-2 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full text-[11px] font-bold text-white whitespace-nowrap"
               style="background-color: {{ $accent }};">
            {{ $p->badge }}
          </div>
        @endif
        @if($isCurrent)
          <div class="absolute top-3 right-3 px-2 py-0.5 rounded text-[11px] font-semibold bg-indigo-600 text-white">
            ใช้งานอยู่
          </div>
        @endif

        <div class="p-5 border-b border-gray-100">
          <div class="font-bold text-lg text-gray-900">{{ $p->name }}</div>
          @if($p->tagline)
            <div class="text-xs text-gray-500 mt-1">{{ $p->tagline }}</div>
          @endif
          <div class="mt-3">
            @if($p->isFree())
              <span class="text-3xl font-extrabold text-gray-900">ฟรี</span>
            @else
              <span class="text-3xl font-extrabold text-gray-900">฿{{ number_format((float) $p->price_thb, 0) }}</span>
              <span class="text-sm text-gray-500">/เดือน</span>
            @endif
          </div>
        </div>

        <ul class="px-5 py-4 text-sm text-gray-700 space-y-1.5 flex-1">
          @foreach(($p->features_json ?? []) as $f)
            <li class="flex items-start gap-2">
              <i class="bi bi-check text-emerald-500 mt-0.5"></i>
              <span>{{ $f }}</span>
            </li>
          @endforeach
        </ul>

        <div class="p-4 pt-0">
          @if($isCurrent)
            <button disabled class="w-full py-2 rounded-lg bg-gray-100 text-gray-500 text-sm font-semibold cursor-not-allowed">
              ใช้งานแผนนี้อยู่
            </button>
          @else
            <form method="POST" action="{{ route('storage.change', ['code' => $p->code]) }}">
              @csrf
              <button class="w-full py-2 rounded-lg text-white text-sm font-semibold transition hover:opacity-90"
                      style="background-color: {{ $accent }};">
                เปลี่ยนเป็นแผนนี้
              </button>
            </form>
          @endif
        </div>
      </div>
    @endforeach
  </div>
</div>
@endsection

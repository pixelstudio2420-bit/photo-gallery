@extends('layouts.app')

@section('title', 'พื้นที่เก็บไฟล์บนคลาวด์ — เริ่มต้นฟรี 5 GB')

@php
  $featureLabels = [
    'sharing'         => ['แชร์ลิงก์',                   'bi-share'],
    'password_links'  => ['ลิงก์แบบใส่รหัสผ่าน',         'bi-key'],
    'access_logs'     => ['ประวัติการเข้าถึง',           'bi-clock-history'],
    'expiring_links' => ['ลิงก์หมดอายุอัตโนมัติ',         'bi-hourglass-split'],
    'file_preview'   => ['ดูตัวอย่างไฟล์ในเว็บ',          'bi-eye'],
    'public_links'    => ['Public links ไม่จำกัด',        'bi-globe'],
    'bulk_download' => ['ดาวน์โหลดเป็น ZIP',              'bi-download'],
    'advanced_audit' => ['Audit log ละเอียด',              'bi-clipboard-data'],
    'versioning'      => ['File versioning',              'bi-clock-history'],
    'api_access'      => ['API access',                    'bi-braces-asterisk'],
  ];
@endphp

@section('content')
<div class="max-w-6xl mx-auto py-8">
  <div class="text-center mb-10">
    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-semibold mb-4">
      <i class="bi bi-cloud-fill"></i> Cloud Storage
    </div>
    <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight text-gray-900 mb-3">
      พื้นที่เก็บไฟล์บนคลาวด์ — เริ่มต้นฟรี 5 GB
    </h1>
    <p class="text-gray-600 max-w-2xl mx-auto">
      เก็บรูป เอกสาร วิดีโอ ได้ทุกที่ จัดการผ่านเว็บเบราว์เซอร์ แชร์ลิงก์ได้ทันที ปลอดภัยด้วย Cloudflare R2
    </p>
  </div>

  @if(!$salesOpen)
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3 flex items-start gap-3">
      <i class="bi bi-info-circle-fill text-lg"></i>
      <div class="text-sm">
        <div class="font-semibold">เร็ว ๆ นี้ — ระบบอยู่ระหว่างเตรียมความพร้อม</div>
        <div>แผนแบบชำระเงินจะเปิดให้บริการเร็ว ๆ นี้ ติดตามข่าวสารที่หน้าประกาศ หรือเริ่มใช้งานด้วยแผน Free ได้ทันที</div>
      </div>
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
        $accent = $p->color_hex ?: '#6366f1';
        $featList = $p->features_json ?? [];
      @endphp
      <div class="relative bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-lg transition flex flex-col">
        @if($p->badge)
          <div class="absolute -top-2 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full text-[11px] font-bold text-white whitespace-nowrap"
               style="background-color: {{ $accent }};">
            {{ $p->badge }}
          </div>
        @endif

        <div class="p-5 border-b border-gray-100">
          <div class="font-bold text-lg text-gray-900">{{ $p->name }}</div>
          @if($p->tagline)
            <div class="text-xs text-gray-500 mt-1">{{ $p->tagline }}</div>
          @endif
          <div class="mt-4">
            @if($p->isFree())
              <span class="text-3xl font-extrabold text-gray-900">ฟรี</span>
            @else
              <span class="text-3xl font-extrabold text-gray-900">฿{{ number_format((float) $p->price_thb, 0) }}</span>
              <span class="text-sm text-gray-500">/เดือน</span>
              @if($p->price_annual_thb && $p->annualSavings() > 0)
                <p class="text-[11px] text-emerald-600 mt-1">
                  รายปี ฿{{ number_format((float) $p->price_annual_thb, 0) }} — ประหยัด ฿{{ number_format($p->annualSavings(), 0) }}
                </p>
              @endif
            @endif
          </div>
          <div class="mt-3 text-xs text-gray-600">
            พื้นที่ <span class="font-bold text-gray-900">{{ number_format($p->storage_gb, 0) }} GB</span>
            @if($p->max_file_size_mb)
              · ต่อไฟล์สูงสุด {{ number_format($p->max_file_size_mb, 0) }} MB
            @endif
          </div>
        </div>

        <ul class="px-5 py-4 space-y-2 text-sm text-gray-700 flex-1">
          @foreach($featList as $f)
            <li class="flex items-start gap-2">
              <i class="bi bi-check-circle-fill text-emerald-500 mt-0.5"></i>
              <span>{{ $f }}</span>
            </li>
          @endforeach
        </ul>

        <div class="p-4 pt-0">
          @auth
            @if($p->isFree() || $salesOpen)
              <form method="POST" action="{{ route('storage.subscribe', ['code' => $p->code]) }}">
                @csrf
                <button class="w-full py-2.5 rounded-lg text-white text-sm font-semibold transition hover:opacity-90"
                        style="background-color: {{ $accent }};">
                  {{ $p->isFree() ? 'เริ่มใช้ฟรี' : 'สมัครแผนนี้' }}
                </button>
              </form>
            @else
              <button disabled class="w-full py-2.5 rounded-lg bg-gray-100 text-gray-400 text-sm font-semibold cursor-not-allowed">
                เร็ว ๆ นี้
              </button>
            @endif
          @else
            <a href="{{ route('login') }}?redirect={{ urlencode(url()->current()) }}"
               class="block text-center w-full py-2.5 rounded-lg text-white text-sm font-semibold transition hover:opacity-90"
               style="background-color: {{ $accent }};">
              เข้าสู่ระบบเพื่อสมัคร
            </a>
          @endauth
        </div>
      </div>
    @endforeach
  </div>

  <div class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
    <div class="rounded-xl border border-gray-200 bg-white p-5">
      <i class="bi bi-shield-check text-2xl text-indigo-500"></i>
      <div class="font-semibold text-gray-900 mt-2">ปลอดภัยระดับองค์กร</div>
      <p class="text-gray-600 mt-1">ข้อมูลเก็บบน Cloudflare R2 เข้ารหัสทั้งระหว่างส่งและเก็บ</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-5">
      <i class="bi bi-lightning-charge text-2xl text-indigo-500"></i>
      <div class="font-semibold text-gray-900 mt-2">เร็วจากทั่วโลก</div>
      <p class="text-gray-600 mt-1">CDN ครอบคลุม 300+ เมืองทั่วโลก — ดาวน์โหลดเร็วทุกที่</p>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-5">
      <i class="bi bi-credit-card text-2xl text-indigo-500"></i>
      <div class="font-semibold text-gray-900 mt-2">ยกเลิกได้ทุกเมื่อ</div>
      <p class="text-gray-600 mt-1">ไม่มีค่าเริ่มต้น ยกเลิกเมื่อไรก็ได้ ใช้งานได้จนสิ้นรอบบิล</p>
    </div>
  </div>
</div>
@endsection

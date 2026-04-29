@extends('layouts.photographer')

@section('title', 'Stripe Connect')

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-shield-check',
  'eyebrow'  => 'การเงิน',
  'title'    => 'Stripe Connect',
  'subtitle' => 'เชื่อมต่อบัญชีรับเงินระหว่างประเทศผ่าน Stripe',
])

{{-- Alerts --}}
@if(session('success'))
  <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl text-sm" style="background:rgba(34,197,94,0.1);color:#15803d;">
    <i class="bi bi-check-circle-fill"></i>
    <span>{{ session('success') }}</span>
  </div>
@endif
@if(session('error'))
  <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl text-sm" style="background:rgba(239,68,68,0.1);color:#dc2626;">
    <i class="bi bi-exclamation-circle-fill"></i>
    <span>{{ session('error') }}</span>
  </div>
@endif
@if(session('warning'))
  <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl text-sm bg-amber-50 text-amber-800">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span>{{ session('warning') }}</span>
  </div>
@endif

@php
  $isConnected   = !empty($stripeAccountId);
  $isFullyEnabled = $stripeChargesEnabled && $stripePayoutsEnabled;
  $photographerPct = 100 - intval($platformCommission);
@endphp

{{-- ===== Status Section ===== --}}
@if(!$isConnected)
  {{-- Not Connected --}}
  <div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
    <div class="p-10 text-center">
      <div class="flex items-center justify-center mx-auto mb-4 w-20 h-20 rounded-full" style="background:rgba(99,102,241,0.08);">
        <i class="bi bi-stripe text-4xl text-indigo-500"></i>
      </div>
      <h5 class="font-bold text-lg mb-2 text-gray-900">เชื่อมต่อ Stripe เพื่อรับการชำระเงิน</h5>
      <p class="text-gray-500 text-sm max-w-md mx-auto mb-6">
        เชื่อมต่อบัญชี Stripe Express เพื่อรับยอดเงินจากการขายรูปภาพโดยตรงเข้าบัญชีของคุณ
      </p>
      <form method="POST" action="{{ route('photographer.stripe-connect.onboard') }}">
        @csrf
        <button type="submit" class="font-semibold px-8 py-3 rounded-xl text-white transition hover:shadow-lg" style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
          <i class="bi bi-link-45deg mr-2"></i>เชื่อมต่อ Stripe ตอนนี้
        </button>
      </form>
    </div>
  </div>

@elseif($isFullyEnabled)
  {{-- Fully Connected --}}
  <div class="rounded-xl mb-6 border" style="background:linear-gradient(135deg,rgba(34,197,94,0.06),rgba(16,185,129,0.04));border-color:rgba(34,197,94,0.2);">
    <div class="p-5">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-[50px] h-[50px] rounded-full flex items-center justify-center shrink-0" style="background:rgba(34,197,94,0.15);">
          <i class="bi bi-check-circle-fill text-2xl text-green-600"></i>
        </div>
        <div>
          <h6 class="font-bold text-green-800">เชื่อมต่อ Stripe สำเร็จแล้ว</h6>
          <p class="text-gray-500 text-sm">Account: {{ $stripeAccountId }}</p>
        </div>
        <div class="ml-auto">
          <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium" style="background:rgba(34,197,94,0.15);color:#15803d;">
            <i class="bi bi-circle-fill" style="font-size:0.5rem;"></i>Active
          </span>
        </div>
      </div>
      <form method="POST" action="{{ route('photographer.stripe-connect.dashboard') }}">
        @csrf
        <button type="submit" class="font-semibold text-white px-5 py-2.5 rounded-lg transition hover:shadow-lg" style="background:#635bff;">
          <i class="bi bi-box-arrow-up-right mr-2"></i>เปิด Stripe Dashboard
        </button>
      </form>
    </div>
  </div>

@else
  {{-- Connected but incomplete --}}
  <div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
    <div class="p-5">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-[50px] h-[50px] rounded-full flex items-center justify-center shrink-0" style="background:rgba(251,191,36,0.15);">
          <i class="bi bi-exclamation-triangle-fill text-xl text-amber-600"></i>
        </div>
        <div>
          <h6 class="font-bold text-amber-900">ตั้งค่า Stripe ยังไม่สมบูรณ์</h6>
          <p class="text-gray-500 text-sm">กรุณาดำเนินการตามขั้นตอนด้านล่างให้ครบถ้วน</p>
        </div>
      </div>

      {{-- 4-step checklist --}}
      <div class="mb-6">
        @php
          $steps = [
            ['label' => 'สร้างบัญชี Stripe Express', 'done' => $isConnected],
            ['label' => 'กรอกรายละเอียดบัญชี',   'done' => $stripeDetailsSubmitted],
            ['label' => 'เปิดใช้งานการรับชำระเงิน', 'done' => $stripeChargesEnabled],
            ['label' => 'เปิดใช้งานการโอนเงินออก', 'done' => $stripePayoutsEnabled],
          ];
        @endphp
        @foreach($steps as $i => $step)
          <div class="flex items-center gap-3 py-2.5 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
            <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 {{ $step['done'] ? '' : 'bg-gray-100' }}" style="{{ $step['done'] ? 'background:rgba(34,197,94,0.15);' : '' }}">
              @if($step['done'])
                <i class="bi bi-check2 text-green-600 font-bold"></i>
              @else
                <span class="text-gray-500 text-sm font-semibold">{{ $i + 1 }}</span>
              @endif
            </div>
            <span class="text-sm {{ $step['done'] ? 'text-green-700' : 'text-gray-500' }}">
              {{ $step['label'] }}
            </span>
            @if($step['done'])
              <span class="ml-auto inline-block text-xs font-medium px-2.5 py-0.5 rounded-full" style="background:rgba(34,197,94,0.1);color:#15803d;">เสร็จแล้ว</span>
            @else
              <span class="ml-auto inline-block text-xs font-medium px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-500">รอดำเนินการ</span>
            @endif
          </div>
        @endforeach
      </div>

      <form method="POST" action="{{ route('photographer.stripe-connect.onboard') }}">
        @csrf
        <button type="submit" class="font-semibold text-white px-5 py-2.5 rounded-lg transition hover:shadow-lg" style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
          <i class="bi bi-arrow-repeat mr-2"></i>ดำเนินการต่อที่ Stripe
        </button>
      </form>
    </div>
  </div>
@endif

{{-- Commission Info --}}
<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6">
  <div class="p-5">
    <h6 class="font-bold text-gray-900 mb-4">
      <i class="bi bi-pie-chart mr-2 text-indigo-600"></i>การแบ่งรายได้
    </h6>
    <div class="grid grid-cols-2 gap-4 text-center">
      <div class="p-4 rounded-xl" style="background:rgba(99,102,241,0.06);">
        <div class="font-bold text-3xl tracking-tight text-indigo-500">{{ $photographerPct }}%</div>
        <div class="text-sm text-gray-500 mt-1">ช่างภาพได้รับ</div>
      </div>
      <div class="p-4 rounded-xl" style="background:rgba(244,63,94,0.06);">
        <div class="font-bold text-3xl tracking-tight text-rose-500">{{ $platformCommission }}%</div>
        <div class="text-sm text-gray-500 mt-1">แพลตฟอร์มหัก</div>
      </div>
    </div>
  </div>
</div>

{{-- How Payouts Work --}}
<div class="bg-white rounded-xl shadow-md border border-gray-100">
  <div class="p-5">
    <h6 class="font-bold text-gray-900 mb-4">
      <i class="bi bi-info-circle mr-2 text-indigo-600"></i>วิธีการรับเงิน
    </h6>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="flex gap-3">
        <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" style="background:rgba(99,102,241,0.08);">
          <i class="bi bi-1-circle text-indigo-600"></i>
        </div>
        <div>
          <p class="font-semibold text-sm text-gray-900 mb-1">เชื่อมต่อบัญชี</p>
          <p class="text-gray-500 text-xs">สมัครและเชื่อมต่อบัญชี Stripe Express ของคุณ</p>
        </div>
      </div>
      <div class="flex gap-3">
        <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" style="background:rgba(99,102,241,0.08);">
          <i class="bi bi-2-circle text-indigo-600"></i>
        </div>
        <div>
          <p class="font-semibold text-sm text-gray-900 mb-1">ลูกค้าซื้อรูป</p>
          <p class="text-gray-500 text-xs">เมื่อมีการขายเกิดขึ้น ระบบบันทึกรายการอัตโนมัติ</p>
        </div>
      </div>
      <div class="flex gap-3">
        <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" style="background:rgba(99,102,241,0.08);">
          <i class="bi bi-3-circle text-indigo-600"></i>
        </div>
        <div>
          <p class="font-semibold text-sm text-gray-900 mb-1">รับเงินอัตโนมัติ</p>
          <p class="text-gray-500 text-xs">Stripe โอนเงิน {{ $photographerPct }}% เข้าบัญชีธนาคารของคุณ</p>
        </div>
      </div>
    </div>
    <div class="mt-4 p-3 rounded-xl text-sm bg-gray-50 border border-gray-200 text-gray-500">
      <i class="bi bi-shield-lock mr-1 text-indigo-600"></i>
      ข้อมูลบัญชีธนาคารของคุณปลอดภัยและเข้ารหัสโดย Stripe — แพลตฟอร์มไม่มีการเข้าถึงข้อมูลบัตรหรือบัญชีของคุณโดยตรง
    </div>
  </div>
</div>

@endsection

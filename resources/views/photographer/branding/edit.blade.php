@extends('layouts.photographer')

@section('title', 'กำหนดแบรนด์')

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-palette',
  'eyebrow'  => 'บริการเสริม',
  'title'    => 'กำหนดแบรนด์',
  'subtitle' => 'โลโก้ · สีประจำ · ลายน้ำ · ซ่อนเครดิตแพลตฟอร์ม (Business+)',
])

@if(session('success'))
  <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-4 py-3">
    <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
  </div>
@endif
@if(session('error'))
  <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-900 text-sm px-4 py-3">
    <i class="bi bi-exclamation-triangle-fill mr-1.5"></i>{{ session('error') }}
  </div>
@endif

@if(!$canCustom)
<div class="rounded-xl border border-amber-200 bg-amber-50 p-5 mb-6">
  <p class="font-semibold text-amber-900">
    <i class="bi bi-lock-fill mr-1.5"></i> ฟีเจอร์ Custom Branding ยังไม่เปิดใช้
  </p>
  <p class="text-sm text-amber-800 mt-2">
    กำหนดแบรนด์ (โลโก้, สีประจำ, ลายน้ำ) เปิดสำหรับแผน Business ขึ้นไป —
    <a href="{{ route('photographer.subscription.plans') }}" class="font-medium underline">อัปเกรดเพื่อปลดล็อก</a>
  </p>
</div>
@endif

<form method="POST" action="{{ route('photographer.branding.update') }}" enctype="multipart/form-data" class="space-y-6">
  @csrf

  {{-- Logo --}}
  <div class="pg-card p-5">
    <h5 class="font-semibold text-gray-900 mb-4">
      <i class="bi bi-image mr-1.5 text-indigo-500"></i>โลโก้แบรนด์
    </h5>
    @if($settings->logo_path)
      <div class="flex items-center gap-4 mb-4">
        <img src="{{ app(\App\Services\StorageManager::class)->url($settings->logo_path) }}"
             alt="Logo" class="h-16 w-auto rounded border border-gray-200 bg-gray-50 p-2">
        <form method="POST" action="{{ route('photographer.branding.logo.remove') }}" class="inline">
          @csrf @method('DELETE')
          <button class="text-xs text-rose-600 hover:underline">ลบโลโก้</button>
        </form>
      </div>
    @endif
    <input type="file" name="logo" accept="image/*"
           {{ $canCustom ? '' : 'disabled' }}
           class="block text-sm text-gray-700 file:mr-3 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-medium hover:file:bg-indigo-100">
    <p class="text-xs text-gray-500 mt-2">PNG, JPG, SVG, WebP — ขนาดไม่เกิน 2 MB</p>
  </div>

  {{-- Accent color --}}
  <div class="pg-card p-5">
    <h5 class="font-semibold text-gray-900 mb-4">
      <i class="bi bi-droplet mr-1.5 text-indigo-500"></i>สีประจำแบรนด์
    </h5>
    <div class="flex items-center gap-3">
      <input type="color"
             name="accent_hex"
             value="{{ $settings->accent_hex ?? '#6366f1' }}"
             {{ $canCustom ? '' : 'disabled' }}
             class="h-10 w-20 rounded border-gray-300 cursor-pointer">
      <input type="text" name="accent_hex"
             value="{{ $settings->accent_hex }}"
             pattern="#[0-9A-Fa-f]{6}"
             placeholder="#6366f1"
             {{ $canCustom ? '' : 'disabled' }}
             class="rounded-lg border-gray-300 text-sm font-mono w-32 focus:border-indigo-500 focus:ring-indigo-500"
             oninput="this.previousElementSibling.value=this.value;">
    </div>
    <p class="text-xs text-gray-500 mt-2">ใช้ในปุ่ม CTA และเส้นขอบของหน้า public event</p>
  </div>

  {{-- Watermark --}}
  <div class="pg-card p-5">
    <h5 class="font-semibold text-gray-900 mb-4">
      <i class="bi bi-droplet-half mr-1.5 text-indigo-500"></i>ลายน้ำ
    </h5>
    <label class="inline-flex items-center gap-2 mb-3">
      <input type="checkbox" name="watermark_enabled" value="1"
             @checked($settings->watermark_enabled)
             {{ $canCustom ? '' : 'disabled' }}
             class="rounded text-indigo-600 focus:ring-indigo-500">
      <span class="text-sm text-gray-700">เปิดใช้ลายน้ำในภาพพรีวิว</span>
    </label>
    <input type="text" name="watermark_text"
           value="{{ $settings->watermark_text }}"
           maxlength="80"
           placeholder="© ชื่อสตูดิโอ ของคุณ"
           {{ $canCustom ? '' : 'disabled' }}
           class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
  </div>

  {{-- White-label (Studio) --}}
  <div class="pg-card p-5">
    <div class="flex items-start justify-between mb-4">
      <h5 class="font-semibold text-gray-900">
        <i class="bi bi-incognito mr-1.5 text-indigo-500"></i>White-label (Studio)
      </h5>
      @if(!$canWhiteLabel)
        <span class="text-[11px] px-2 py-0.5 rounded bg-gray-100 text-gray-600">เปิดสำหรับแผน Studio</span>
      @endif
    </div>
    <label class="inline-flex items-start gap-2 mb-4">
      <input type="checkbox" name="hide_platform_credits" value="1"
             @checked($settings->hide_platform_credits)
             {{ $canWhiteLabel ? '' : 'disabled' }}
             class="mt-1 rounded text-indigo-600 focus:ring-indigo-500">
      <span class="text-sm text-gray-700">
        ซ่อน "Powered by" ในหน้าสาธารณะ
        <span class="block text-xs text-gray-500">ลูกค้าของคุณจะไม่เห็นแบรนด์ของแพลตฟอร์มในหน้า event/portfolio</span>
      </span>
    </label>
    <div>
      <label class="text-xs text-gray-600 mb-1 block">Custom Domain (optional)</label>
      <input type="text" name="custom_domain"
             value="{{ $settings->custom_domain }}"
             placeholder="gallery.yourstudio.com"
             {{ $canWhiteLabel ? '' : 'disabled' }}
             class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
      <p class="text-xs text-gray-500 mt-1">ทำ CNAME มาที่ระบบเรา — แอดมินยืนยันก่อนใช้งาน</p>
    </div>
  </div>

  <div class="flex justify-end">
    <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
            {{ $canCustom ? '' : 'disabled' }}>
      <i class="bi bi-save"></i> บันทึก
    </button>
  </div>
</form>
@endsection

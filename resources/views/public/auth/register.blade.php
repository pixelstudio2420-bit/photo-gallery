@extends('layouts.app')

@section('title', 'สมัครสมาชิก')

@php
  $svc = app(\App\Services\Auth\SocialAuthService::class);
  $emailReg = $svc->isEmailRegistrationEnabled();
@endphp

@push('styles')
<style>
  .reg-bg{
    background:
      radial-gradient(1200px 600px at 10% -10%, rgba(99,102,241,.18), transparent 60%),
      radial-gradient(1000px 500px at 90% 10%, rgba(236,72,153,.15), transparent 60%),
      radial-gradient(900px 500px at 50% 110%, rgba(16,185,129,.10), transparent 60%),
      linear-gradient(160deg,#f8fafc 0%,#f1f5f9 55%,#fdf2f8 100%);
  }
  html.dark .reg-bg{
    background:
      radial-gradient(1200px 600px at 10% -10%, rgba(99,102,241,.25), transparent 60%),
      radial-gradient(1000px 500px at 90% 10%, rgba(236,72,153,.18), transparent 60%),
      radial-gradient(900px 500px at 50% 110%, rgba(16,185,129,.08), transparent 60%),
      linear-gradient(160deg,#020617 0%,#0f172a 55%,#1e1b4b 100%);
  }
  .role-card{
    background:#fff; border:2px solid #e5e7eb; border-radius:24px;
    padding:2rem 1.75rem; transition:all .3s cubic-bezier(.4,0,.2,1); position:relative; overflow:hidden;
    display:flex; flex-direction:column; height:100%;
  }
  html.dark .role-card{ background:#0f172a; border-color:rgba(255,255,255,.08); color:#e2e8f0; }
  .role-card::before{
    content:''; position:absolute; inset:0; opacity:0; transition:opacity .3s;
    background:linear-gradient(135deg,var(--c1,#6366f1),var(--c2,#ec4899));
    mix-blend-mode:multiply;
  }
  .role-card:hover{ transform:translateY(-6px); box-shadow:0 24px 48px -14px rgba(99,102,241,.25); border-color:transparent; }
  .role-card .role-icon{
    width:72px; height:72px; border-radius:20px;
    background:linear-gradient(135deg,var(--c1,#6366f1),var(--c2,#ec4899));
    display:inline-flex; align-items:center; justify-content:center; color:#fff; font-size:2rem;
    box-shadow:0 10px 30px -6px rgba(99,102,241,.5);
    transition:transform .3s;
  }
  .role-card:hover .role-icon{ transform:scale(1.08) rotate(-4deg); }
  .role-card h3{ font-size:1.6rem; font-weight:800; letter-spacing:-0.02em; color:#0f172a; }
  html.dark .role-card h3{ color:#f1f5f9; }
  .role-card p.subtitle{ color:#64748b; font-size:.92rem; }
  html.dark .role-card p.subtitle{ color:#94a3b8; }
  .role-card ul.perks li{ display:flex; gap:.5rem; font-size:.83rem; color:#475569; padding:.2rem 0; }
  html.dark .role-card ul.perks li{ color:#cbd5e1; }
  .role-card ul.perks li i{ color:#10b981; margin-top:3px; flex-shrink:0; }

  .role-card.customer{ --c1:#6366f1; --c2:#ec4899; }
  .role-card.photographer{ --c1:#f59e0b; --c2:#ef4444; }
  .role-card.photographer .role-icon{ box-shadow:0 10px 30px -6px rgba(239,68,68,.45); }

  .hero-title{ font-size:clamp(1.75rem,4vw,2.75rem); letter-spacing:-0.03em; }

  @keyframes regFade{ from{opacity:0; transform:translateY(12px);} to{opacity:1; transform:translateY(0);} }
  .reg-anim{ animation:regFade .5s ease-out both; }
  .reg-anim.d1{ animation-delay:.05s; } .reg-anim.d2{ animation-delay:.15s; }
  .reg-anim.d3{ animation-delay:.25s; } .reg-anim.d4{ animation-delay:.35s; }

  .step-dot{
    width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;
    font-weight:700;font-size:.82rem;background:#e0e7ff;color:#4f46e5;flex-shrink:0;
  }
  .step-dot.active{ background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; box-shadow:0 4px 12px rgba(99,102,241,.4); }
  .step-line{ height:2px; flex:1; background:#e0e7ff; margin:0 .4rem; }
  html.dark .step-line{ background:rgba(255,255,255,.12); }
</style>
@endpush

@section('content-full')
<div class="reg-bg min-h-[90vh] py-10 sm:py-14">
  <div class="max-w-5xl mx-auto px-4">

    {{-- Step indicator --}}
    <div class="flex items-center justify-center mb-8 reg-anim" x-data="{step:1}" x-init="window.__regStep=() => step;">
      <div class="step-dot active">1</div>
      <span class="text-sm font-semibold ml-2 text-indigo-700 dark:text-indigo-300">เลือกบทบาท</span>
      <div class="step-line"></div>
      <div class="step-dot">2</div>
      <span class="text-sm ml-2 text-gray-500">เชื่อมต่อบัญชี</span>
    </div>

    {{-- Hero --}}
    <div class="text-center mb-10 reg-anim d1">
      <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/70 dark:bg-white/5 backdrop-blur border border-white/40 dark:border-white/10 text-xs font-semibold text-indigo-700 dark:text-indigo-300 mb-4">
        <i class="bi bi-stars"></i> ยินดีต้อนรับสู่ Photo Gallery
      </div>
      <h1 class="hero-title font-bold text-slate-900 dark:text-white mb-3">
        เริ่มต้นใช้งานใน <span class="bg-gradient-to-r from-indigo-600 via-pink-500 to-amber-500 bg-clip-text text-transparent">ไม่ถึงนาที</span>
      </h1>
      <p class="text-base sm:text-lg text-slate-600 dark:text-slate-400 max-w-xl mx-auto">
        เลือกบทบาทการใช้งาน แล้วเข้าระบบด้วย Social Login ที่คุณใช้อยู่ทุกวัน
      </p>
    </div>

    @if($errors->any())
      <div class="max-w-2xl mx-auto mb-5 reg-anim d2">
        <div class="rounded-2xl p-4 text-sm bg-red-50 text-red-700 border border-red-200 dark:bg-red-500/10 dark:text-red-300 dark:border-red-400/30">
          <i class="bi bi-exclamation-circle mr-1"></i>{{ $errors->first() }}
        </div>
      </div>
    @endif

    {{-- Role cards --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 sm:gap-6">

      {{-- Customer --}}
      <section class="role-card customer reg-anim d2" aria-labelledby="role-customer-title">
        <div class="flex items-start justify-between mb-5">
          <div class="role-icon"><i class="bi bi-person-heart"></i></div>
          <span class="text-[0.7rem] font-bold px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">สำหรับผู้ซื้อ</span>
        </div>
        <h3 id="role-customer-title" class="mb-1.5">ฉันต้องการซื้อภาพ</h3>
        <p class="subtitle mb-5">ค้นหาและดาวน์โหลดภาพจากอีเวนต์</p>

        <ul class="perks mb-6 space-y-0.5">
          <li><i class="bi bi-check-circle-fill"></i>ค้นหารูปจากงานอีเวนต์ได้ทันที</li>
          <li><i class="bi bi-check-circle-fill"></i>AI Face Search ค้นหารูปของคุณเอง</li>
          <li><i class="bi bi-check-circle-fill"></i>ดาวน์โหลดคุณภาพเต็ม ไม่มีลายน้ำ</li>
          <li><i class="bi bi-check-circle-fill"></i>แจ้งเตือนผ่าน LINE ทุกออเดอร์</li>
        </ul>

        <div class="mt-auto">
          <x-social-buttons role="customer" size="lg" intent="register"/>
        </div>
      </section>

      {{-- Photographer --}}
      <section class="role-card photographer reg-anim d3" aria-labelledby="role-photographer-title">
        <div class="flex items-start justify-between mb-5">
          <div class="role-icon"><i class="bi bi-camera-fill"></i></div>
          <span class="text-[0.7rem] font-bold px-3 py-1 rounded-full bg-gradient-to-r from-amber-100 to-red-100 text-red-700 dark:from-amber-500/15 dark:to-red-500/15 dark:text-red-300">สร้างรายได้</span>
        </div>
        <h3 id="role-photographer-title" class="mb-1.5">ฉันต้องการขายภาพ</h3>
        <p class="subtitle mb-5">สร้างรายได้จากการถ่ายภาพอีเวนต์</p>

        <ul class="perks mb-6 space-y-0.5">
          <li><i class="bi bi-check-circle-fill"></i>อัปโหลด &amp; ขายรูปไม่จำกัด</li>
          <li><i class="bi bi-check-circle-fill"></i>นำเข้าจาก Google Drive อัตโนมัติ</li>
          <li><i class="bi bi-check-circle-fill"></i>รับส่วนแบ่งสูงสุด 80%</li>
          <li><i class="bi bi-check-circle-fill"></i>Dashboard จัดการรายได้ครบมือ</li>
        </ul>

        <div class="mt-auto">
          <x-social-buttons role="photographer" size="lg" intent="register"/>
          <p class="text-[.72rem] text-emerald-600 dark:text-emerald-300 mt-3 text-center font-semibold">
            <i class="bi bi-lightning-charge-fill mr-1"></i>สมัครเสร็จ เริ่มขายได้ทันที — ใช้เวลา ~1 นาที
          </p>
        </div>
      </section>
    </div>

    {{-- Email registration fallback --}}
    @if($emailReg)
      <details class="max-w-2xl mx-auto mt-8 reg-anim d4 group" x-data="{open:false}">
        <summary class="cursor-pointer list-none flex items-center justify-center gap-2 text-sm text-slate-500 dark:text-slate-400 hover:text-indigo-600 transition"
          @click.prevent="open = !open; $el.parentElement.open = open;">
          <i class="bi bi-envelope"></i>
          <span x-text="open ? 'ซ่อนฟอร์ม' : 'หรือสมัครด้วยอีเมล'"></span>
          <i class="bi bi-chevron-down transition-transform" :class="open ? 'rotate-180' : ''"></i>
        </summary>
        <div class="mt-4 bg-white/90 dark:bg-slate-900/80 backdrop-blur border border-gray-200 dark:border-white/10 rounded-2xl p-6 shadow-xl">
          <form method="POST" action="{{ route('auth.register.post') }}" class="space-y-3">
            @csrf
            {{-- Anti-abuse signal: device-fingerprint.js auto-fills this hidden
                 input on page load. Server treats null as "no signal". --}}
            <input type="hidden" name="device_fingerprint" data-device-fingerprint>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">ชื่อจริง</label>
                <input type="text" name="first_name" required value="{{ old('first_name') }}" class="w-full px-4 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white dark:bg-slate-800 dark:text-gray-100">
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">นามสกุล</label>
                <input type="text" name="last_name" value="{{ old('last_name') }}" class="w-full px-4 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white dark:bg-slate-800 dark:text-gray-100">
              </div>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">อีเมล</label>
              <input type="email" name="email" required value="{{ old('email') }}" class="w-full px-4 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white dark:bg-slate-800 dark:text-gray-100">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">รหัสผ่าน</label>
                <input type="password" name="password" required minlength="8" class="w-full px-4 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white dark:bg-slate-800 dark:text-gray-100">
              </div>
              <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">ยืนยันรหัสผ่าน</label>
                <input type="password" name="password_confirmation" required minlength="8" class="w-full px-4 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white dark:bg-slate-800 dark:text-gray-100">
              </div>
            </div>
            <button type="submit" class="w-full py-3 font-semibold text-white rounded-xl transition hover:opacity-95"
              style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
              <i class="bi bi-person-plus mr-1"></i> สมัครด้วยอีเมล
            </button>
          </form>
        </div>
      </details>
    @endif

    {{-- Already have account --}}
    <div class="text-center mt-10 reg-anim d4">
      <p class="text-slate-600 dark:text-slate-400 text-sm">
        มีบัญชีอยู่แล้ว?
        <a href="{{ route('auth.login') }}" class="ml-1 font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 hover:underline">
          เข้าสู่ระบบ <i class="bi bi-arrow-right"></i>
        </a>
      </p>
    </div>
  </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'เชื่อมต่อบัญชี LINE')

@php
  $svc = app(\App\Services\Auth\SocialAuthService::class);
  $allowSkip = $svc->allowLineConnectSkip();
@endphp

@push('styles')
<style>
  .cl-bg{
    background:
      radial-gradient(1000px 500px at 50% -10%, rgba(6,199,85,.22), transparent 60%),
      radial-gradient(800px 500px at 15% 90%, rgba(99,102,241,.12), transparent 60%),
      linear-gradient(160deg,#f8fafc 0%,#f0fdf4 60%,#dcfce7 100%);
  }
  html.dark .cl-bg{
    background:
      radial-gradient(1000px 500px at 50% -10%, rgba(6,199,85,.25), transparent 60%),
      radial-gradient(800px 500px at 15% 90%, rgba(99,102,241,.15), transparent 60%),
      linear-gradient(160deg,#020617 0%,#052e16 60%,#14532d 100%);
  }
  .cl-card{
    background:#fff; border-radius:32px; border:1px solid rgba(6,199,85,.15);
    box-shadow:0 30px 60px -12px rgba(6,199,85,.25); padding:2.75rem 2rem;
  }
  html.dark .cl-card{ background:#0f172a; color:#e2e8f0; border-color:rgba(255,255,255,.08); }
  .cl-badge{
    width:110px; height:110px; border-radius:30px;
    background:linear-gradient(135deg,#06C755 0%,#05b34c 100%);
    box-shadow:0 18px 40px -10px rgba(6,199,85,.65);
    display:inline-flex; align-items:center; justify-content:center; color:#fff; font-size:3.5rem;
    position:relative;
  }
  .cl-badge::before{
    content:''; position:absolute; inset:-8px; border-radius:34px;
    background:linear-gradient(135deg,#06C755, #34d399);
    filter:blur(18px); opacity:.3; z-index:-1;
  }
  .cl-badge::after{
    content:''; position:absolute; inset:0; border-radius:30px;
    border:2px solid rgba(255,255,255,.3);
  }
  .btn-line-connect{
    background:linear-gradient(135deg,#06C755,#05b34c);
    color:#fff; font-weight:700; padding:1rem 2rem; border-radius:14px; border:none;
    font-size:1.05rem; width:100%; display:inline-flex; align-items:center; justify-content:center; gap:.65rem;
    box-shadow:0 14px 30px -8px rgba(6,199,85,.55); transition:transform .15s, box-shadow .2s;
  }
  .btn-line-connect:hover{ transform:translateY(-2px); box-shadow:0 20px 40px -8px rgba(6,199,85,.7); }
  .btn-line-connect i{ font-size:1.35rem; }

  .reason-list li{ display:flex; gap:.65rem; align-items:flex-start; padding:.4rem 0; font-size:.92rem; color:#334155; }
  html.dark .reason-list li{ color:#cbd5e1; }
  .reason-list li i{ color:#06C755; font-size:1.15rem; margin-top:2px; flex-shrink:0; }

  @keyframes popIn{ 0%{opacity:0; transform:scale(.85);} 100%{opacity:1; transform:scale(1);} }
  @keyframes fadeUp{ from{opacity:0; transform:translateY(14px);} to{opacity:1; transform:translateY(0);} }
  .cl-pop{ animation:popIn .55s cubic-bezier(.34,1.56,.64,1) both; }
  .cl-fade{ animation:fadeUp .5s ease-out both; }
  .cl-fade.d1{ animation-delay:.15s; } .cl-fade.d2{ animation-delay:.25s; } .cl-fade.d3{ animation-delay:.4s; }
</style>
@endpush

@section('content-full')
<div class="cl-bg min-h-[90vh] flex items-center justify-center py-10 px-4">
  <div class="w-full max-w-lg">

    <div class="cl-card text-center">
      {{-- Icon --}}
      <div class="cl-pop mb-5 inline-block">
        <span class="cl-badge" aria-hidden="true"><i class="bi bi-line"></i></span>
      </div>

      <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white mb-2 cl-fade">
        เชื่อมต่อบัญชี <span style="color:#06C755">LINE</span>
      </h1>
      <p class="text-slate-600 dark:text-slate-400 mb-6 cl-fade d1">
        อีกเพียงขั้นตอนเดียวก็พร้อมใช้งานเต็มรูปแบบ
      </p>

      {{-- Reasons --}}
      <ul class="reason-list text-left max-w-sm mx-auto mb-7 cl-fade d2">
        <li><i class="bi bi-bell-fill"></i><span>รับการแจ้งเตือนออเดอร์ &amp; การดาวน์โหลดทาง LINE แบบเรียลไทม์</span></li>
        <li><i class="bi bi-chat-dots-fill"></i><span>ติดต่อกับช่างภาพได้โดยตรงผ่านช่องทาง LINE</span></li>
        <li><i class="bi bi-shield-check"></i><span>เพิ่มความปลอดภัยด้วย 2-Step Verification ผ่าน LINE</span></li>
        <li><i class="bi bi-lightning-charge-fill"></i><span>เข้าสู่ระบบในอนาคตได้เร็วขึ้นด้วย LINE LIFF</span></li>
      </ul>

      @if(session('error'))
        <div class="rounded-xl p-3 text-sm mb-4 bg-red-50 text-red-700 border border-red-200 dark:bg-red-500/10 dark:text-red-300 dark:border-red-400/30">
          <i class="bi bi-exclamation-circle mr-1"></i>{{ session('error') }}
        </div>
      @endif

      {{-- Primary CTA --}}
      <a href="{{ url('/auth/line') }}?connect=1" class="btn-line-connect cl-fade d2"
         aria-label="เชื่อมต่อกับ LINE" onclick="this.classList.add('is-loading');">
        <i class="bi bi-line"></i>
        <span>เชื่อมต่อกับ LINE</span>
        <i class="bi bi-arrow-right ml-auto"></i>
      </a>

      {{-- Skip --}}
      @if($allowSkip)
        <form method="POST" action="{{ route('auth.connect-line.skip') }}" class="mt-4 cl-fade d3">
          @csrf
          <button type="submit" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition">
            <i class="bi bi-skip-forward mr-1"></i>ข้ามไปก่อน (เชื่อมภายหลังได้)
          </button>
        </form>
      @else
        <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
          <i class="bi bi-info-circle mr-1"></i>ผู้ดูแลระบบกำหนดให้ต้องเชื่อมต่อ LINE เพื่อใช้งานต่อ
        </p>
      @endif
    </div>

    <p class="text-center mt-5 text-xs text-slate-500 dark:text-slate-400 cl-fade d3">
      มีปัญหา? <a href="{{ url('/help') }}" class="text-indigo-600 hover:underline">ติดต่อฝ่ายสนับสนุน</a>
    </p>
  </div>
</div>

<script>
  // Simple loading state
  document.querySelectorAll('.btn-line-connect').forEach(btn => {
    btn.addEventListener('click', () => {
      btn.style.opacity = '.8';
      btn.style.pointerEvents = 'none';
    });
  });
</script>
@endsection

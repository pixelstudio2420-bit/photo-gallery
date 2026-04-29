@extends('layouts.app')

@section('title', 'เริ่มเป็นช่างภาพ')

@push('styles')
<style>
  /* Match site auth-flow theme — same gradient, card, button styles as login page */
  .pq-bg{
    background:
      radial-gradient(900px 500px at 15% -10%, rgba(99,102,241,.18), transparent 60%),
      radial-gradient(800px 500px at 85% 15%, rgba(236,72,153,.12), transparent 60%),
      linear-gradient(160deg,#f8fafc 0%,#f1f5f9 60%,#ede9fe 100%);
  }
  html.dark .pq-bg{
    background:
      radial-gradient(900px 500px at 15% -10%, rgba(99,102,241,.26), transparent 60%),
      radial-gradient(800px 500px at 85% 15%, rgba(236,72,153,.18), transparent 60%),
      linear-gradient(160deg,#020617 0%,#0f172a 60%,#1e1b4b 100%);
  }

  .pq-card{
    background:#fff; border-radius:28px;
    box-shadow:0 40px 80px -20px rgba(15,23,42,.2);
    border:1px solid rgba(99,102,241,.08); overflow:hidden;
  }
  html.dark .pq-card{ background:#0f172a; border-color:rgba(255,255,255,.08); color:#e2e8f0; }

  .pq-header{
    background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 45%,#ec4899 100%);
    padding:2.25rem 2rem 4rem; color:#fff; position:relative; overflow:hidden;
  }
  /* Decorative shapes */
  .pq-header::before{
    content:''; position:absolute; right:-30px; top:-30px;
    width:140px; height:140px; border-radius:50%;
    background:radial-gradient(circle, rgba(255,255,255,.15), transparent 70%);
    pointer-events:none;
  }
  .pq-header::after{
    content:''; position:absolute; left:30px; bottom:30px;
    width:80px; height:80px; border-radius:50%;
    background:radial-gradient(circle, rgba(255,255,255,.08), transparent 70%);
    pointer-events:none;
  }
  .pq-header h2{ font-weight:800; letter-spacing:-0.02em; font-size:1.7rem; margin-bottom:.25rem; }

  .pq-logo{
    width:56px; height:56px; border-radius:18px;
    background:rgba(255,255,255,.2); backdrop-filter:blur(8px);
    display:inline-flex; align-items:center; justify-content:center;
    font-size:1.7rem; color:#fff;
    border:1px solid rgba(255,255,255,.25);
    box-shadow:0 8px 24px rgba(0,0,0,.18);
  }

  .pq-body{
    padding:2rem 2rem 2.25rem; margin-top:-2rem;
    position:relative; z-index:1; background:inherit;
    border-radius:28px 28px 0 0;
  }

  /* Account chip — pre-filled identity row */
  .pq-account{
    display:flex; align-items:center; gap:.75rem;
    padding:.85rem 1rem; border-radius:14px;
    background:linear-gradient(135deg,rgba(99,102,241,.08),rgba(236,72,153,.06));
    border:1px solid rgba(99,102,241,.18);
  }
  html.dark .pq-account{
    background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(236,72,153,.08));
    border-color:rgba(99,102,241,.25);
  }
  .pq-avatar{
    width:38px; height:38px; border-radius:12px;
    background:linear-gradient(135deg,#6366f1,#a855f7);
    display:inline-flex; align-items:center; justify-content:center;
    color:#fff; font-size:1.1rem; flex-shrink:0;
  }
  .pq-account-meta-label{ font-size:.7rem; color:#64748b; margin:0; line-height:1; }
  html.dark .pq-account-meta-label{ color:#94a3b8; }
  .pq-account-meta-email{ font-weight:600; font-size:.9rem; color:#0f172a; margin:.15rem 0 0; line-height:1.2; }
  html.dark .pq-account-meta-email{ color:#f1f5f9; }
  .pq-badge-ok{
    margin-left:auto; flex-shrink:0;
    padding:.25rem .65rem; border-radius:999px;
    background:rgba(16,185,129,.1); color:#059669;
    border:1px solid rgba(16,185,129,.25);
    font-size:.68rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
  }
  html.dark .pq-badge-ok{
    background:rgba(16,185,129,.15); color:#34d399;
    border-color:rgba(52,211,153,.3);
  }

  /* Field label */
  .pq-label{
    font-size:.7rem; font-weight:700; letter-spacing:.08em;
    text-transform:uppercase; color:#475569;
    display:flex; align-items:center; gap:.4rem;
    margin-bottom:.45rem;
  }
  html.dark .pq-label{ color:#cbd5e1; }
  .pq-label .opt{
    font-weight:500; color:#94a3b8; text-transform:none;
    letter-spacing:0; font-size:.7rem;
  }

  /* Inputs match the login fancy-input style */
  .pq-input-wrap{ position:relative; }
  .pq-input-wrap > i.lead{
    position:absolute; left:1rem; top:50%; transform:translateY(-50%);
    color:#9ca3af; pointer-events:none;
  }
  .pq-input{
    width:100%; padding:.78rem 1rem .78rem 2.6rem;
    border-radius:12px; border:1.5px solid #e5e7eb;
    font-size:.92rem; outline:none;
    background:#fff; color:#0f172a;
    transition:border-color .2s, box-shadow .2s, background .2s;
  }
  html.dark .pq-input{
    background:#1e293b; color:#f1f5f9;
    border-color:rgba(255,255,255,.1);
  }
  .pq-input:focus{
    border-color:#6366f1;
    box-shadow:0 0 0 4px rgba(99,102,241,.12);
  }
  .pq-input::placeholder{ color:#94a3b8; }

  .pq-help{
    font-size:.78rem; color:#64748b; margin-top:.4rem; margin-bottom:0;
  }
  html.dark .pq-help{ color:#94a3b8; }

  .pq-tip{
    margin-top:.65rem; padding:.7rem .85rem; border-radius:10px;
    background:rgba(99,102,241,.06);
    border:1px solid rgba(99,102,241,.15);
    font-size:.75rem; color:#475569; line-height:1.55;
  }
  html.dark .pq-tip{
    background:rgba(99,102,241,.1);
    border-color:rgba(99,102,241,.2);
    color:#cbd5e1;
  }
  .pq-tip strong{ color:#4338ca; }
  html.dark .pq-tip strong{ color:#a5b4fc; }

  /* Agreement checkbox */
  .pq-agree{
    display:flex; gap:.7rem; align-items:flex-start;
    padding:.85rem 1rem; border-radius:14px;
    background:#f8fafc; border:1.5px solid #e5e7eb;
    cursor:pointer; transition:border-color .2s, background .2s;
  }
  html.dark .pq-agree{
    background:rgba(255,255,255,.03);
    border-color:rgba(255,255,255,.08);
  }
  .pq-agree:hover{ border-color:#6366f1; background:rgba(99,102,241,.04); }
  html.dark .pq-agree:hover{ background:rgba(99,102,241,.08); border-color:rgba(99,102,241,.4); }
  .pq-agree input[type=checkbox]{
    margin-top:.15rem; width:18px; height:18px;
    accent-color:#6366f1; flex-shrink:0; cursor:pointer;
  }
  .pq-agree-text{ font-size:.85rem; color:#334155; line-height:1.55; }
  html.dark .pq-agree-text{ color:#cbd5e1; }
  .pq-agree-text a{ color:#4f46e5; font-weight:600; }
  .pq-agree-text a:hover{ text-decoration:underline; }
  html.dark .pq-agree-text a{ color:#a5b4fc; }

  /* Submit button */
  .pq-submit{
    padding:.95rem 1.5rem; border:none; border-radius:14px;
    font-weight:700; font-size:.98rem;
    background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 45%,#ec4899 100%);
    color:#fff;
    box-shadow:0 12px 28px -8px rgba(124,58,237,.55);
    transition:transform .15s, box-shadow .2s, filter .2s;
    display:inline-flex; align-items:center; gap:.55rem; justify-content:center;
  }
  .pq-submit:hover{
    transform:translateY(-1px) scale(1.01);
    box-shadow:0 16px 36px -8px rgba(124,58,237,.7);
    filter:brightness(1.05);
  }
  .pq-submit:active{ transform:translateY(0) scale(.99); }

  .pq-cancel{
    color:#64748b; font-size:.88rem; padding:.55rem .9rem;
    border-radius:10px; transition:color .2s, background .2s;
  }
  .pq-cancel:hover{ color:#0f172a; background:rgba(0,0,0,.04); }
  html.dark .pq-cancel{ color:#94a3b8; }
  html.dark .pq-cancel:hover{ color:#f1f5f9; background:rgba(255,255,255,.06); }

  /* Trust strip below card */
  .pq-trust{
    display:flex; align-items:center; justify-content:center;
    gap:1.2rem; flex-wrap:wrap;
    font-size:.74rem; color:#64748b;
    margin-top:1.25rem;
  }
  html.dark .pq-trust{ color:#94a3b8; }
  .pq-trust span{ display:inline-flex; align-items:center; gap:.3rem; }
  .pq-trust i{ color:#10b981; font-size:.85rem; }

  /* Errors box */
  .pq-errors{
    padding:.9rem 1rem; border-radius:12px;
    background:rgba(239,68,68,.05);
    border:1px solid rgba(239,68,68,.2);
    color:#b91c1c; font-size:.85rem; line-height:1.55;
  }
  html.dark .pq-errors{
    background:rgba(239,68,68,.1);
    border-color:rgba(239,68,68,.3);
    color:#fca5a5;
  }

  /* Animation */
  @keyframes pqfade{ from{opacity:0; transform:translateY(12px);} to{opacity:1; transform:translateY(0);} }
  .pq-anim{ animation:pqfade .5s ease-out both; }
  .pq-anim.d1{ animation-delay:.05s; }
  .pq-anim.d2{ animation-delay:.15s; }
  .pq-anim.d3{ animation-delay:.25s; }
</style>
@endpush

@section('content-full')
<div class="pq-bg min-h-[90vh] flex items-center justify-center py-10 px-4">
  <div class="w-full max-w-md pq-anim">

    <div class="pq-card">
      {{-- Header --}}
      <div class="pq-header">
        <div class="flex items-center gap-3">
          <span class="pq-logo" aria-hidden="true"><i class="bi bi-camera-fill"></i></span>
          <div>
            <h2>เริ่มเป็นช่างภาพ</h2>
            <p class="text-white/85 text-sm mb-0">ใช้บัญชีเดิม — ไม่ต้องสมัครใหม่</p>
          </div>
        </div>
      </div>

      {{-- Body --}}
      <div class="pq-body">
        <form method="POST" action="{{ route('photographer-onboarding.quick.save') }}" class="space-y-4">
          @csrf

          {{-- Account chip --}}
          <div class="pq-account pq-anim d1">
            <div class="pq-avatar"><i class="bi bi-person-fill"></i></div>
            <div class="min-w-0 flex-1">
              <p class="pq-account-meta-label">เข้าสู่ระบบด้วย</p>
              <p class="pq-account-meta-email truncate">{{ $user->email }}</p>
            </div>
            <span class="pq-badge-ok">
              <i class="bi bi-check-circle-fill" style="font-size:.7rem;"></i> Verified
            </span>
          </div>

          @if($errors->any())
            <div class="pq-errors pq-anim">
              <ul class="list-disc list-inside m-0 space-y-0.5">
                @foreach($errors->all() as $err) <li>{{ $err }}</li> @endforeach
              </ul>
            </div>
          @endif

          {{-- Display name --}}
          <div class="pq-anim d1">
            <label class="pq-label">
              <i class="bi bi-person-badge"></i> ชื่อที่แสดง
            </label>
            <div class="pq-input-wrap">
              <i class="bi bi-pencil-square lead"></i>
              <input type="text" name="display_name"
                     value="{{ old('display_name', $defaultDisplayName) }}"
                     required maxlength="200" class="pq-input"
                     placeholder="เช่น John Photography">
            </div>
            <p class="pq-help">ลูกค้าจะเห็นชื่อนี้ในหน้าอีเวนต์ของคุณ</p>
          </div>

          {{-- Phone (only show if user has none on file) --}}
          @if(empty($defaultPhone))
            <div class="pq-anim d2">
              <label class="pq-label">
                <i class="bi bi-telephone"></i> เบอร์โทร <span class="opt">(ไม่บังคับ)</span>
              </label>
              <div class="pq-input-wrap">
                <i class="bi bi-phone lead"></i>
                <input type="tel" name="phone" value="{{ old('phone') }}" maxlength="30"
                       class="pq-input" placeholder="08x-xxx-xxxx">
              </div>
            </div>
          @else
            <input type="hidden" name="phone" value="{{ $defaultPhone }}">
          @endif

          {{-- PromptPay --}}
          <div class="pq-anim d2">
            <label class="pq-label">
              <i class="bi bi-qr-code"></i> PromptPay <span class="opt">(ไม่บังคับ — ใส่ทีหลังได้)</span>
            </label>
            <div class="pq-input-wrap">
              <i class="bi bi-bank lead"></i>
              <input type="text" name="promptpay_number" value="{{ old('promptpay_number') }}"
                     maxlength="20" class="pq-input font-mono"
                     placeholder="เบอร์โทรหรือเลขบัตรประชาชน">
            </div>
            <div class="pq-tip">
              <p class="m-0 mb-1">
                <i class="bi bi-rocket-takeoff text-indigo-500"></i>
                <strong>ใส่เลย:</strong> เริ่มขายรูปได้ทันที (Seller tier)
              </p>
              <p class="m-0">
                <i class="bi bi-clock text-amber-500"></i>
                <strong>ใส่ทีหลัง:</strong> เป็นช่างภาพได้แต่ยังขายรูปไม่ได้ — เพิ่ม PromptPay ในโปรไฟล์เมื่อพร้อม
              </p>
            </div>
          </div>

          {{-- Agreement --}}
          <label class="pq-agree pq-anim d3">
            <input type="checkbox" name="agree" value="1" required @checked(old('agree'))>
            <span class="pq-agree-text">
              ฉันยอมรับ
              <a href="{{ route('legal.terms') }}" target="_blank">เงื่อนไขการให้บริการ</a>
              และยินยอมให้ระบบเก็บข้อมูลตาม
              <a href="{{ route('legal.privacy') }}" target="_blank">นโยบายความเป็นส่วนตัว</a>
            </span>
          </label>

          {{-- Actions --}}
          <div class="flex items-center justify-between gap-3 pt-2 pq-anim d3">
            <a href="{{ url()->previous() }}" class="pq-cancel">ยกเลิก</a>
            <button type="submit" class="pq-submit">
              <i class="bi bi-rocket-takeoff"></i>
              เริ่มเป็นช่างภาพ
            </button>
          </div>
        </form>
      </div>
    </div>

    {{-- Trust strip --}}
    <div class="pq-trust">
      <span><i class="bi bi-check-circle-fill"></i> ใช้งานทันที</span>
      <span><i class="bi bi-check-circle-fill"></i> ไม่ต้องอนุมัติ</span>
      <span><i class="bi bi-check-circle-fill"></i> เปลี่ยนกลับโหมดลูกค้าได้</span>
    </div>
  </div>
</div>
@endsection

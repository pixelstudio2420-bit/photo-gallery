<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>สมัครช่างภาพ — {{ $siteName ?? config('app.name') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  @vite(['resources/css/app.css'])
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
  <style>
    * { font-family: 'Sarabun', sans-serif; }
    [x-cloak] { display: none !important; }
    @keyframes pulse-line {
      0%, 100% { box-shadow: 0 0 0 0 rgba(6, 199, 85, 0.5); }
      50%      { box-shadow: 0 0 0 12px rgba(6, 199, 85, 0); }
    }
    .pulse-line { animation: pulse-line 2.4s ease-in-out infinite; }
    @keyframes float-soft {
      0%, 100% { transform: translateY(0px); }
      50%      { transform: translateY(-8px); }
    }
    .float-soft { animation: float-soft 4s ease-in-out infinite; }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center relative overflow-hidden py-8" style="background:linear-gradient(135deg, #0f172a 0%, #064e3b 35%, #065f46 70%, #0f172a 100%);">
  {{-- Decorative blobs (LINE-themed gradients) --}}
  <div class="absolute w-[500px] h-[500px] rounded-full top-[-150px] right-[-100px]" style="background:radial-gradient(circle, rgba(6,199,85,0.20) 0%, transparent 70%);"></div>
  <div class="absolute w-[400px] h-[400px] rounded-full bottom-[-100px] left-[-100px]" style="background:radial-gradient(circle, rgba(220,38,38,0.10) 0%, transparent 70%);"></div>
  <div class="absolute w-[300px] h-[300px] rounded-full top-[40%] left-[60%]" style="background:radial-gradient(circle, rgba(99,102,241,0.08) 0%, transparent 70%);"></div>

  <div class="relative z-10 w-full max-w-[520px] mx-4 rounded-3xl border shadow-[0_25px_50px_-12px_rgba(0,0,0,0.5)]" style="background:rgba(15, 23, 42, 0.85);-webkit-backdrop-filter:blur(20px) saturate(180%);backdrop-filter:blur(20px) saturate(180%);border-color:rgba(255,255,255,0.08);">
    <div class="p-8 md:p-10">

      {{-- ───── HEADER ───── --}}
      <div class="text-center mb-6">
        <div class="float-soft inline-flex items-center justify-center mx-auto mb-4 w-[68px] h-[68px] rounded-2xl shadow-lg" style="background:linear-gradient(135deg,#06C755 0%,#00b04f 100%);box-shadow:0 8px 24px -4px rgba(6,199,85,0.5);">
          <i class="bi bi-camera2 text-white text-3xl"></i>
        </div>
        <h1 class="font-bold mb-2 text-white tracking-tight text-2xl">เริ่มขายรูปของคุณ</h1>
        <p class="text-slate-400 text-sm">สมัครเป็นช่างภาพ — เพียง 2 ขั้นตอน</p>
      </div>

      {{-- ───── 2-Step Progress Diagram ───── --}}
      <div class="relative flex items-center justify-between mb-7 px-2">
        {{-- Connecting line --}}
        <div class="absolute top-5 left-[15%] right-[15%] h-0.5 bg-gradient-to-r from-emerald-500 via-slate-600 to-rose-500 opacity-40"></div>

        <div class="relative z-10 flex flex-col items-center gap-1.5">
          <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold pulse-line" style="background:linear-gradient(135deg,#06C755,#00b04f);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zM24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
          </div>
          <div class="text-[11px] font-semibold text-emerald-400">ขั้นที่ 1</div>
          <div class="text-[10px] text-slate-500">เข้าด้วย LINE</div>
        </div>

        <div class="relative z-10 flex flex-col items-center gap-1.5">
          <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold border-2 border-slate-600" style="background:rgba(15, 23, 42, 0.8);">
            <i class="bi bi-google text-rose-400"></i>
          </div>
          <div class="text-[11px] font-semibold text-rose-400">ขั้นที่ 2</div>
          <div class="text-[10px] text-slate-500">เชื่อม Google</div>
        </div>

        <div class="relative z-10 flex flex-col items-center gap-1.5">
          <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold border-2 border-slate-600" style="background:rgba(15, 23, 42, 0.8);">
            <i class="bi bi-camera-fill text-violet-400"></i>
          </div>
          <div class="text-[11px] font-semibold text-violet-400">เริ่มขาย</div>
          <div class="text-[10px] text-slate-500">เปิดใช้งาน</div>
        </div>
      </div>

      @if($errors->any())
        <div class="rounded-xl p-3 text-sm mb-4" style="background:rgba(239,68,68,0.12);color:#fca5a5;">
          <ul class="mb-0 list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
      @endif

      @if(!empty($authedUser))
        {{-- ═══════════════════════════════════════════════════════════
             LOGGED-IN PATH — user is already a customer (e.g. signed up
             via LINE on the public site) and now wants to become a
             photographer. Skip the LINE/email re-registration step
             entirely; just spawn the PhotographerProfile via the
             "claim" endpoint and route them on to /connect-google.
             ═══════════════════════════════════════════════════════════ --}}
        <div class="mb-5 px-4 py-3.5 rounded-2xl flex items-start gap-3" style="background:rgba(6,199,85,0.10);border:1px solid rgba(6,199,85,0.25);">
          <div class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center" style="background:rgba(6,199,85,0.20);">
            <i class="bi bi-check-circle-fill text-emerald-400 text-lg"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-bold text-emerald-200">
              สวัสดีคุณ {{ $authedUser->first_name ?: 'ลูกค้า' }} 👋
            </div>
            <div class="text-xs text-emerald-200/85 mt-1 leading-relaxed">
              คุณมีบัญชีอยู่แล้ว — ไม่ต้องสมัครใหม่ แค่กดปุ่มเปิดบัญชีช่างภาพด้านล่าง
            </div>
            {{-- Show what's already linked --}}
            <div class="flex items-center gap-2 mt-2.5 flex-wrap">
              @if($hasLine)
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[10px] font-semibold" style="background:rgba(6,199,85,0.25);color:#86efac;">
                  <i class="bi bi-check2-circle"></i> เชื่อม LINE แล้ว
                </span>
              @endif
              @if($hasGoogle)
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[10px] font-semibold" style="background:rgba(244,63,94,0.20);color:#fda4af;">
                  <i class="bi bi-check2-circle"></i> เชื่อม Google แล้ว
                </span>
              @endif
              @if(!$hasLine && !$hasGoogle)
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[10px] font-semibold" style="background:rgba(99,102,241,0.20);color:#c7d2fe;">
                  <i class="bi bi-envelope-fill"></i> บัญชีอีเมล
                </span>
              @endif
            </div>
          </div>
        </div>

        {{-- ───── Optional: display name input ───── --}}
        <form method="POST" action="{{ route('photographer.register.claim') }}">
          @csrf
          <div class="mb-4">
            <label class="block text-xs font-medium text-slate-400 mb-1.5">ชื่อที่จะแสดงในเว็บ <span class="text-slate-600">(แก้ทีหลังได้)</span></label>
            <input type="text" name="display_name"
                   class="w-full px-3.5 py-2.5 rounded-xl text-sm transition outline-none focus:ring-2 focus:ring-emerald-500/30"
                   style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.10);color:#f1f5f9;"
                   value="{{ old('display_name', trim(($authedUser->first_name ?? '') . ' ' . ($authedUser->last_name ?? ''))) }}"
                   placeholder="เช่น Mali Wedding Studio · Jey Phuket Run"
                   maxlength="100">
          </div>

          {{-- ───── PRIMARY: Become photographer ───── --}}
          <button type="submit"
                  class="block w-full py-3.5 rounded-2xl text-center text-white font-bold text-base transition-all hover:scale-[1.02] active:scale-[0.98] mb-3 shadow-lg"
                  style="background:linear-gradient(135deg,#06C755 0%,#00b04f 100%); box-shadow:0 10px 30px -8px rgba(6,199,85,0.5);">
            <span class="inline-flex items-center justify-center gap-3">
              <i class="bi bi-camera2 text-xl"></i>
              <span>เปิดบัญชีช่างภาพ</span>
              @if(!$hasGoogle)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white/20 backdrop-blur text-[10px] font-semibold tracking-wider">→ เชื่อม Google</span>
              @endif
            </span>
          </button>
        </form>

        {{-- Next step hint --}}
        @if(!$hasGoogle)
          <div class="mb-5 px-3 py-2.5 rounded-xl flex items-start gap-2" style="background:rgba(244,63,94,0.08);border:1px solid rgba(244,63,94,0.2);">
            <i class="bi bi-1-circle-fill text-rose-400 text-sm mt-0.5 shrink-0"></i>
            <div class="text-[11px] text-rose-200/90 leading-relaxed">
              <strong class="text-rose-300">ขั้นต่อไป:</strong> หลังเปิดบัญชี ระบบจะพาไปเชื่อม <strong>Google</strong> — ใช้ส่งใบเสร็จลูกค้า · backup รูปไป Google Drive · Calendar นัดงาน
            </div>
          </div>
        @else
          <div class="mb-5 px-3 py-2.5 rounded-xl flex items-start gap-2" style="background:rgba(6,199,85,0.08);border:1px solid rgba(6,199,85,0.2);">
            <i class="bi bi-check-all text-emerald-400 text-sm mt-0.5 shrink-0"></i>
            <div class="text-[11px] text-emerald-200/90 leading-relaxed">
              <strong class="text-emerald-300">พร้อมขาย!</strong> Google เชื่อมแล้ว — กดเปิดบัญชีจะเข้าสู่ Dashboard ทันที เริ่มอัปโหลดผลงานได้เลย
            </div>
          </div>
        @endif

        {{-- "Not me?" logout escape hatch — use the public-side logout
             route since most claim users came in as customers via /login. --}}
        <div class="text-center pt-3 border-t border-slate-800">
          <form method="POST" action="{{ route('auth.logout') }}" class="inline-block">
            @csrf
            <button type="submit" class="text-xs text-slate-500 hover:text-slate-300 transition">
              ไม่ใช่ {{ $authedUser->first_name ?: 'คุณ' }}? <span class="underline">ออกจากระบบ + สมัครบัญชีใหม่</span>
            </button>
          </form>
        </div>

      @else
        {{-- ═══════════════════════════════════════════════════════════
             GUEST PATH — original UX: LINE button + email signup form.
             ═══════════════════════════════════════════════════════════ --}}

        {{-- ───── PRIMARY: LINE Signup ───── --}}
        <a href="{{ route('photographer.auth.redirect', ['provider' => 'line']) }}"
           class="block w-full py-3.5 rounded-2xl text-center text-white font-bold text-base transition-all hover:scale-[1.02] active:scale-[0.98] mb-3 shadow-lg"
           style="background:linear-gradient(135deg,#06C755 0%,#00b04f 100%); box-shadow:0 10px 30px -8px rgba(6,199,85,0.5);">
          <span class="inline-flex items-center justify-center gap-3">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="white"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zM24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
            <span>สมัครด้วย LINE</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-white/20 backdrop-blur text-[10px] font-semibold tracking-wider">แนะนำ</span>
          </span>
        </a>

        {{-- Why LINE? --}}
        <div class="mb-5 px-3 py-2.5 rounded-xl flex items-start gap-2" style="background:rgba(6,199,85,0.08);border:1px solid rgba(6,199,85,0.2);">
          <i class="bi bi-info-circle-fill text-emerald-400 text-sm mt-0.5 shrink-0"></i>
          <div class="text-[11px] text-emerald-200/90 leading-relaxed">
            <strong class="text-emerald-300">ทำไมต้อง LINE?</strong> ใช้ส่งรูปให้ลูกค้าทันทีหลังจ่ายเงิน · แจ้งเตือนยอดขายเข้า LINE · ลูกค้าทักหาคุยได้ในแชท
          </div>
        </div>

        {{-- ───── Required Step 2 Notice ───── --}}
        <div class="mb-5 px-3 py-2.5 rounded-xl flex items-start gap-2" style="background:rgba(244,63,94,0.08);border:1px solid rgba(244,63,94,0.2);">
          <i class="bi bi-exclamation-triangle-fill text-rose-400 text-sm mt-0.5 shrink-0"></i>
          <div class="text-[11px] text-rose-200/90 leading-relaxed">
            <strong class="text-rose-300">หลัง LINE login</strong> ต้องเชื่อม <strong>Google</strong> ก่อนเริ่มขาย — ใช้ส่งใบเสร็จลูกค้า · backup รูปไป Google Drive · Calendar นัดงาน
          </div>
        </div>

        {{-- ───── Secondary: Email signup (collapsed) ───── --}}
        <div x-data="{ show: {{ $errors->any() ? 'true' : 'false' }} }">

          <div class="flex items-center gap-3 my-4">
            <div class="flex-1 h-px bg-slate-700"></div>
            <button type="button" @click="show = !show"
                    class="text-xs text-slate-500 hover:text-slate-300 transition flex items-center gap-1 font-medium">
              <i class="bi" :class="show ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
              <span x-show="!show">หรือสมัครด้วยอีเมล</span>
              <span x-show="show" x-cloak>ปิดฟอร์มอีเมล</span>
            </button>
            <div class="flex-1 h-px bg-slate-700"></div>
          </div>

          <div x-show="show" x-collapse x-cloak>
            <form method="POST" action="{{ route('photographer.register.post') }}" class="text-left">
              @csrf
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-medium text-slate-400 mb-1.5">ชื่อจริง</label>
                  <input type="text" name="first_name" class="w-full px-3.5 py-2 rounded-lg text-sm transition outline-none focus:ring-2 focus:ring-emerald-500/30" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#f1f5f9;" value="{{ old('first_name') }}">
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-400 mb-1.5">นามสกุล</label>
                  <input type="text" name="last_name" class="w-full px-3.5 py-2 rounded-lg text-sm transition outline-none focus:ring-2 focus:ring-emerald-500/30" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#f1f5f9;" value="{{ old('last_name') }}">
                </div>
              </div>
              <div class="mt-3">
                <label class="block text-xs font-medium text-slate-400 mb-1.5">ชื่อที่แสดง (Display Name)</label>
                <input type="text" name="display_name" class="w-full px-3.5 py-2 rounded-lg text-sm transition outline-none focus:ring-2 focus:ring-emerald-500/30" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#f1f5f9;" value="{{ old('display_name') }}" placeholder="ชื่อที่จะแสดงในเว็บ">
              </div>
              <div class="mt-3">
                <label class="block text-xs font-medium text-slate-400 mb-1.5">อีเมล</label>
                <input type="email" name="email" class="w-full px-3.5 py-2 rounded-lg text-sm transition outline-none focus:ring-2 focus:ring-emerald-500/30" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#f1f5f9;" value="{{ old('email') }}">
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                <div>
                  <label class="block text-xs font-medium text-slate-400 mb-1.5">รหัสผ่าน</label>
                  <input type="password" name="password" class="w-full px-3.5 py-2 rounded-lg text-sm transition outline-none focus:ring-2 focus:ring-emerald-500/30" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#f1f5f9;">
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-400 mb-1.5">ยืนยันรหัสผ่าน</label>
                  <input type="password" name="password_confirmation" class="w-full px-3.5 py-2 rounded-lg text-sm transition outline-none focus:ring-2 focus:ring-emerald-500/30" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#f1f5f9;">
                </div>
              </div>
              <div class="mt-3 px-3 py-2 rounded-lg flex items-start gap-2" style="background:rgba(99,102,241,0.08);">
                <i class="bi bi-info-circle text-indigo-300 text-xs mt-0.5 shrink-0"></i>
                <div class="text-[10px] text-indigo-200/80 leading-relaxed">
                  สมัครด้วยอีเมลก็ยังต้อง <strong>เชื่อม Google</strong> ก่อนเริ่มขาย — แทนการยืนยันอีเมล
                </div>
              </div>
              <button type="submit" class="w-full mt-4 py-2.5 font-semibold text-white rounded-xl border-none transition hover:opacity-90 text-sm" style="background:linear-gradient(135deg,#475569,#334155);">
                <i class="bi bi-envelope me-1"></i> สมัครด้วยอีเมล
              </button>
            </form>
          </div>
        </div>
      @endif

      {{-- ───── Footer Links ───── --}}
      <div class="text-center mt-6 space-y-2">
        <div>
          <span class="text-slate-500 text-sm">มีบัญชีแล้ว?</span>
          <a href="{{ route('photographer.login') }}" class="text-sm font-semibold text-emerald-400 hover:text-emerald-300 transition">เข้าสู่ระบบ</a>
        </div>
        <div>
          <a href="{{ url('/') }}" class="text-sm text-slate-500 hover:text-slate-400 transition">
            <i class="bi bi-arrow-left me-1"></i> กลับไปหน้าเว็บไซต์
          </a>
        </div>
      </div>

    </div>
  </div>
</body>
</html>

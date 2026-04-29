<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>2FA Challenge — {{ $siteName ?? config('app.name') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  @vite(['resources/css/app.css'])
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    * { font-family: 'Sarabun', sans-serif; }
    .otp-input {
      letter-spacing: 0.5em;
      text-align: center;
      font-feature-settings: "tnum";
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center relative overflow-hidden" style="background:linear-gradient(135deg, #0f172a 0%, #1a1145 40%, #1e1e2d 100%);">
  {{-- Decorative blobs --}}
  <div class="absolute w-[500px] h-[500px] rounded-full top-[-150px] right-[-100px]" style="background:radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 70%);"></div>
  <div class="absolute w-[400px] h-[400px] rounded-full bottom-[-100px] left-[-100px]" style="background:radial-gradient(circle, rgba(244,63,94,0.08) 0%, transparent 70%);"></div>

  <div class="relative z-10 w-full max-w-[420px] mx-4 rounded-3xl border shadow-[0_25px_50px_-12px_rgba(0,0,0,0.4)]" style="background:rgba(30, 41, 59, 0.8);-webkit-backdrop-filter:blur(20px) saturate(180%);backdrop-filter:blur(20px) saturate(180%);border-color:rgba(255,255,255,0.08);">
    <div class="p-10 text-center">
      {{-- Brand --}}
      <div class="flex items-center justify-center mx-auto mb-3 w-[60px] h-[60px] rounded-2xl" style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
        <i class="bi bi-shield-check text-white text-3xl"></i>
      </div>
      <h4 class="font-bold mb-1 text-gray-100 tracking-tight text-xl">Two-Factor Authentication</h4>
      <p class="text-slate-500 text-[0.9rem] mb-6">
        {{ $admin->email }}<br>
        <span class="text-xs text-slate-600">กรอกรหัส 6 หลักจากแอป Authenticator หรือ Backup code 8 หลัก</span>
      </p>

      @if(session('info'))
        <div class="rounded-xl p-3 text-sm text-left mb-3" style="background:rgba(59,130,246,0.15);color:#93c5fd;">
          <i class="bi bi-info-circle me-1"></i>{{ session('info') }}
        </div>
      @endif

      @if(session('warning'))
        <div class="rounded-xl p-3 text-sm text-left mb-3" style="background:rgba(234,179,8,0.15);color:#fde68a;">
          <i class="bi bi-exclamation-triangle me-1"></i>{{ session('warning') }}
        </div>
      @endif

      @if($errors->any())
        <div class="rounded-xl p-3 text-sm text-left mb-3" style="background:rgba(239,68,68,0.12);color:#fca5a5;">
          <i class="bi bi-exclamation-circle me-1"></i>{{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ route('admin.2fa.challenge.verify') }}" class="text-left">
        @csrf
        <div class="mb-5">
          <label class="block text-sm font-medium text-slate-400 mb-1.5">Verification Code</label>
          <input
            type="text"
            name="code"
            class="otp-input w-full px-4 py-3 rounded-xl text-lg tracking-wider outline-none transition"
            style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:#f1f5f9;"
            placeholder="000000"
            required
            autofocus
            autocomplete="one-time-code"
            inputmode="text"
            maxlength="16"
            pattern="[0-9A-Fa-f ]{6,16}">
          <p class="text-xs text-slate-500 mt-2">
            <i class="bi bi-clock-history me-1"></i>รหัสจะเปลี่ยนทุก 30 วินาที
          </p>
        </div>

        <button type="submit" class="w-full py-2.5 font-semibold text-white rounded-xl border-none transition hover:opacity-90 text-[0.95rem]" style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
          <i class="bi bi-shield-check me-1"></i> ยืนยัน
        </button>
      </form>

      <form method="POST" action="{{ route('admin.2fa.cancel') }}" class="mt-4">
        @csrf
        <button type="submit" class="text-sm text-slate-500 hover:text-slate-400 transition">
          <i class="bi bi-box-arrow-left me-1"></i> ยกเลิกและออกจากระบบ
        </button>
      </form>
    </div>
  </div>
</body>
</html>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — {{ $siteName ?? config('app.name') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  @vite(['resources/css/app.css'])
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    * { font-family: 'Sarabun', sans-serif; }
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
        <i class="bi bi-shield-lock-fill text-white text-3xl"></i>
      </div>
      <h4 class="font-bold mb-1 text-gray-100 tracking-tight text-xl">Admin Panel</h4>
      <p class="text-slate-500 text-[0.9rem] mb-6">เข้าสู่ระบบจัดการเว็บไซต์</p>

      @if(session('logout_success'))
        <div class="rounded-xl p-3 text-sm text-left mb-3" style="background:rgba(16,185,129,0.15);color:#6ee7b7;">
          <i class="bi bi-check-circle me-1"></i>{{ session('logout_success') }}
        </div>
      @endif

      @if(session('success'))
        <div class="rounded-xl p-3 text-sm text-left mb-3" style="background:rgba(16,185,129,0.15);color:#6ee7b7;">
          <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
        </div>
      @endif

      @if(session('info'))
        <div class="rounded-xl p-3 text-sm text-left mb-3" style="background:rgba(59,130,246,0.15);color:#93c5fd;">
          <i class="bi bi-info-circle me-1"></i>{{ session('info') }}
        </div>
      @endif

      @if($errors->any())
        <div class="rounded-xl p-3 text-sm text-left mb-3" style="background:rgba(239,68,68,0.12);color:#fca5a5;">
          <i class="bi bi-exclamation-circle me-1"></i>{{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ route('admin.login.post') }}" class="text-left">
        @csrf
        <div class="mb-4">
          <label class="block text-sm font-medium text-slate-400 mb-1.5">Email</label>
          <div class="flex">
            <span class="px-3 py-2.5 rounded-l-xl flex items-center" style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-right:none;color:rgba(255,255,255,0.5);"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" class="w-full px-4 py-2.5 rounded-r-xl text-sm transition outline-none" style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-left:none;color:#f1f5f9;" value="{{ old('email') }}" placeholder="admin@example.com" required autofocus>
          </div>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-slate-400 mb-1.5">Password</label>
          <div class="flex">
            <span class="px-3 py-2.5 rounded-l-xl flex items-center" style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-right:none;color:rgba(255,255,255,0.5);"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" class="w-full px-4 py-2.5 rounded-r-xl text-sm transition outline-none" style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-left:none;color:#f1f5f9;" required>
          </div>
        </div>
        <div class="flex items-center gap-2 mb-6">
          <input type="checkbox" class="w-4 h-4 rounded border-white/20 focus:ring-indigo-500" style="background-color:rgba(255,255,255,0.1);accent-color:#6366f1;" name="remember" id="remember">
          <label class="text-sm text-slate-400" for="remember">จดจำฉัน</label>
        </div>
        <button type="submit" class="w-full py-2.5 font-semibold text-white rounded-xl border-none transition hover:opacity-90 text-[0.95rem]" style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
          <i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบ
        </button>
      </form>

      <div class="mt-6">
        <a href="{{ url('/') }}" class="text-sm text-indigo-400 hover:text-indigo-300 transition">
          <i class="bi bi-arrow-left me-1"></i> กลับไปหน้าเว็บไซต์
        </a>
      </div>
    </div>
  </div>
</body>
</html>

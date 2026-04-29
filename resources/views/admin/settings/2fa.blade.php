@extends('layouts.admin')

@section('title', '2FA — Two-Factor Authentication')

@push('styles')
@include('admin.settings._shared-styles')
@endpush

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- ═══ Page Header ═══ --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white flex items-center justify-center shadow-lg shadow-indigo-500/30">
        <i class="bi bi-shield-lock-fill text-lg"></i>
      </div>
      <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Two-Factor Authentication</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          Protect your admin account with Time-based One-Time Passwords (TOTP).
        </p>
      </div>
    </div>
    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium rounded-lg
              bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10
              text-slate-700 dark:text-slate-200
              hover:bg-slate-50 dark:hover:bg-slate-700 transition">
      <i class="bi bi-arrow-left"></i> Back to Settings
    </a>
  </div>

  {{-- ═══ Alerts ═══ --}}
  @if(session('success'))
  <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl
              bg-emerald-50 dark:bg-emerald-500/10
              text-emerald-700 dark:text-emerald-300
              border border-emerald-200 dark:border-emerald-500/30 text-sm">
    <i class="bi bi-check-circle-fill"></i>
    <span>{{ session('success') }}</span>
  </div>
  @endif

  @if(session('error') || $errors->any())
  <div class="mb-4 flex items-center gap-2 px-4 py-3 rounded-xl
              bg-rose-50 dark:bg-rose-500/10
              text-rose-700 dark:text-rose-300
              border border-rose-200 dark:border-rose-500/30 text-sm">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span>@if(session('error')){{ session('error') }}@else{{ $errors->first() }}@endif</span>
  </div>
  @endif

  {{-- ═══ Mandatory 2FA banner ═══
       Shown when enforcement is ON and this admin hasn't enrolled yet.
       The flag is resolved by the controller (env override wins, then the
       enforce_admin_2fa AppSetting, default OFF) so this view stays free
       of env() / DB lookups. --}}
  @php $twoFaRequired = $enforcementActive ?? false; @endphp
  @if(!$enabled && $twoFaRequired)
  <div class="mb-4 flex items-start gap-3 px-4 py-3 rounded-xl
              bg-amber-50 dark:bg-amber-500/10
              text-amber-800 dark:text-amber-200
              border border-amber-300 dark:border-amber-500/40 text-sm">
    <i class="bi bi-shield-exclamation text-lg mt-0.5"></i>
    <div class="flex-1">
      <div class="font-semibold mb-0.5">2FA จำเป็นสำหรับแอดมินทุกคน</div>
      <div class="text-xs leading-relaxed">
        บัญชีของคุณยังไม่ได้เปิดใช้งาน 2FA — กรุณาตั้งค่าให้เสร็จก่อนเข้าใช้งานระบบส่วนอื่น
        หน้าการตั้งค่า, แดชบอร์ด และเมนูอื่น ๆ ทั้งหมดจะเปิดใช้งานได้หลังจากเปิด 2FA แล้วเท่านั้น
      </div>
    </div>
  </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

    {{-- ═══════════════════════════════════════════════════════
       LEFT: Main state (enabled / qr setup / disabled)
    ═══════════════════════════════════════════════════════ --}}
    <div class="lg:col-span-7">

      @if($enabled)
        {{-- ───── 2FA ENABLED ───── --}}
        <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm">
          <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-emerald-100 dark:bg-emerald-500/15 flex items-center justify-center">
              <i class="bi bi-shield-check-fill text-xl text-emerald-600 dark:text-emerald-400"></i>
            </div>
            <div class="flex-1">
              <h3 class="font-bold text-slate-900 dark:text-white">2FA Enabled</h3>
              <span class="status-dot connected mt-1">Active</span>
            </div>
          </div>

          <div class="p-5">
            <p class="text-sm text-slate-600 dark:text-slate-300 mb-5">
              Your account is protected with Time-based One-Time Passwords (TOTP).
              Every login requires a 6-digit code from your authenticator app.
            </p>

            {{-- Backup Codes (one-time display) --}}
            @if(session('backup_codes'))
            <div class="mb-5 p-4 rounded-xl
                        bg-indigo-50 dark:bg-indigo-500/10
                        border border-indigo-200 dark:border-indigo-500/30">
              <h4 class="font-semibold text-sm mb-2 text-indigo-700 dark:text-indigo-300">
                <i class="bi bi-key-fill mr-1"></i>Backup Codes — Save these now!
              </h4>
              <p class="text-xs text-slate-600 dark:text-slate-400 mb-3">
                These one-time codes let you access your account if you lose your authenticator.
                Each code can only be used once. Store them somewhere safe.
              </p>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                @foreach(session('backup_codes') as $code)
                <code class="block bg-white dark:bg-slate-900
                             border border-dashed border-slate-300 dark:border-white/10
                             rounded-lg py-2 px-1 text-center text-sm tracking-wider font-semibold
                             text-slate-800 dark:text-slate-100">{{ $code }}</code>
                @endforeach
              </div>
            </div>
            @endif

            {{-- Disable 2FA --}}
            <div class="mt-6 pt-5 border-t border-slate-200 dark:border-white/10">
              <h4 class="font-semibold mb-3 text-sm text-rose-600 dark:text-rose-400">
                <i class="bi bi-shield-x mr-1"></i>Disable 2FA
              </h4>
              <form method="POST" action="{{ route('admin.settings.2fa.disable') }}"
                    onsubmit="return confirm('Are you sure you want to disable 2FA? This will make your account less secure.');">
                @csrf
                <div class="mb-3">
                  <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Confirm your password</label>
                  <input type="password" name="password" required
                         placeholder="Enter your current password"
                         class="w-full px-3 py-2 rounded-lg text-sm
                                bg-white dark:bg-slate-800
                                border border-slate-300 dark:border-white/10
                                text-slate-900 dark:text-slate-100
                                focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none">
                </div>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold
                               bg-rose-600 hover:bg-rose-700 text-white transition shadow-sm shadow-rose-500/30">
                  <i class="bi bi-shield-x"></i> Disable Two-Factor Auth
                </button>
              </form>
            </div>
          </div>
        </div>

      @elseif($secret && $qrImageUrl)
        {{-- ───── QR CODE SETUP ───── --}}
        <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm">
          <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10">
            <h3 class="font-bold text-slate-900 dark:text-white">
              <i class="bi bi-qr-code mr-2 text-indigo-600 dark:text-indigo-400"></i>Scan QR Code
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
              Open your authenticator app (e.g. Google Authenticator, Authy) and scan the QR code below.
            </p>
          </div>

          <div class="p-5">
            <div class="text-center mb-5">
              <div class="inline-block p-3 rounded-2xl bg-white border border-slate-200 shadow-md">
                <img src="{{ $qrImageUrl }}" alt="QR Code" width="200" height="200" class="block">
              </div>
            </div>

            <div class="mb-5">
              <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">
                <i class="bi bi-key mr-1"></i>Or enter this secret manually
              </label>
              <div class="flex">
                <input type="text" readonly value="{{ $secret }}"
                       class="flex-1 px-3 py-2 rounded-l-lg text-sm font-mono tracking-wide
                              bg-slate-50 dark:bg-slate-800
                              border border-slate-300 dark:border-white/10
                              text-slate-900 dark:text-slate-100">
                <button type="button"
                        onclick="navigator.clipboard.writeText('{{ $secret }}').then(()=>this.innerHTML='<i class=\'bi bi-check-lg\'></i> Copied!')"
                        class="px-3 py-2 rounded-r-lg text-sm font-medium
                               bg-indigo-50 dark:bg-indigo-500/20
                               text-indigo-700 dark:text-indigo-300
                               border border-l-0 border-slate-300 dark:border-white/10
                               hover:bg-indigo-100 dark:hover:bg-indigo-500/30 transition">
                  <i class="bi bi-clipboard"></i> Copy
                </button>
              </div>
            </div>

            {{-- Verify code --}}
            <form method="POST" action="{{ route('admin.settings.2fa.verify') }}">
              @csrf
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                <i class="bi bi-123 mr-1 text-indigo-600 dark:text-indigo-400"></i>Enter the 6-digit code from your app
              </label>
              <div class="flex flex-wrap gap-2">
                <input type="text" name="code" maxlength="6" inputmode="numeric" pattern="\d{6}"
                       placeholder="000000" required autofocus
                       class="w-44 px-3 py-2.5 rounded-lg text-center text-lg font-mono tracking-[0.3em]
                              bg-white dark:bg-slate-800
                              border border-slate-300 dark:border-white/10
                              text-slate-900 dark:text-slate-100
                              focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-lg text-sm font-semibold text-white
                               bg-gradient-to-r from-indigo-600 to-violet-600
                               hover:from-indigo-500 hover:to-violet-500
                               shadow-md shadow-indigo-500/30 transition">
                  <i class="bi bi-shield-check"></i> Verify &amp; Enable
                </button>
              </div>
            </form>
          </div>
        </div>

      @else
        {{-- ───── 2FA DISABLED (initial state) ───── --}}
        <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm">
          <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-rose-100 dark:bg-rose-500/15 flex items-center justify-center">
              <i class="bi bi-shield-x text-xl text-rose-600 dark:text-rose-400"></i>
            </div>
            <div class="flex-1">
              <h3 class="font-bold text-slate-900 dark:text-white">2FA Disabled</h3>
              <span class="status-dot disconnected mt-1">Not Active</span>
            </div>
          </div>

          <div class="p-5">
            <p class="text-sm text-slate-600 dark:text-slate-300 mb-5">
              Two-Factor Authentication adds an extra layer of security to your admin account.
              After enabling, each login will require a one-time code from your authenticator app.
            </p>

            <form method="POST" action="{{ route('admin.settings.2fa.enable') }}">
              @csrf
              <button type="submit"
                      class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-lg text-sm font-semibold text-white
                             bg-gradient-to-r from-indigo-600 to-violet-600
                             hover:from-indigo-500 hover:to-violet-500
                             shadow-md shadow-indigo-500/30 transition">
                <i class="bi bi-shield-plus"></i> Set Up Two-Factor Auth
              </button>
            </form>
          </div>
        </div>
      @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════
       RIGHT: Enforcement toggle + info sidebar
    ═══════════════════════════════════════════════════════ --}}
    <div class="lg:col-span-5 space-y-5">

      {{-- ───── Global enforcement toggle ─────
           Flips `enforce_admin_2fa` AppSetting. Locked read-only when the
           env var is pinning the value. --}}
      <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl
                      {{ ($enforcementActive ?? false) ? 'bg-emerald-100 dark:bg-emerald-500/15' : 'bg-slate-100 dark:bg-slate-800' }}
                      flex items-center justify-center">
            <i class="bi {{ ($enforcementActive ?? false) ? 'bi-shield-lock-fill text-emerald-600 dark:text-emerald-400' : 'bi-shield-slash text-slate-500 dark:text-slate-400' }} text-lg"></i>
          </div>
          <div class="flex-1">
            <h3 class="font-semibold text-slate-900 dark:text-white text-sm">บังคับใช้ 2FA สำหรับแอดมินทุกคน</h3>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
              @if($enforcementActive ?? false)
                เปิดอยู่ — แอดมินที่ยังไม่ตั้งค่าจะถูกบังคับให้ตั้งก่อนใช้งาน
              @else
                ปิดอยู่ — แอดมินเลือกเปิด/ปิด 2FA ได้เอง
              @endif
            </p>
          </div>
        </div>

        <div class="p-5">
          @if($enforcementLocked ?? false)
            {{-- Env override in effect — the DB toggle would be ignored, so
                 show a read-only explanation instead of a form. --}}
            <div class="flex items-start gap-2 px-3 py-2.5 rounded-lg
                        bg-amber-50 dark:bg-amber-500/10
                        text-amber-800 dark:text-amber-200
                        border border-amber-200 dark:border-amber-500/30 text-xs">
              <i class="bi bi-lock-fill mt-0.5"></i>
              <div>
                <div class="font-semibold mb-0.5">ถูกล็อกจาก .env</div>
                <div class="leading-relaxed">
                  ค่า <code class="font-mono">ENFORCE_ADMIN_2FA</code> ถูกตั้งไว้ใน <code class="font-mono">.env</code>
                  — ค่านี้มีลำดับสูงกว่าการตั้งในหน้าเว็บ หากต้องการเปิด/ปิดจากหน้าเว็บ
                  ให้ลบหรือปล่อย <code class="font-mono">ENFORCE_ADMIN_2FA</code> เป็นค่าว่าง
                </div>
              </div>
            </div>
          @else
            <form method="POST" action="{{ route('admin.settings.2fa.enforcement') }}">
              @csrf

              {{-- Switch-style toggle. We submit "1" when checked and "0"
                   when not — the controller reads the request input. The
                   hidden "0" guarantees the field is always present, so an
                   unchecked box still saves an explicit OFF. --}}
              <label class="flex items-center gap-3 cursor-pointer select-none">
                <input type="hidden" name="enforce" value="0">
                <span class="relative inline-block w-11 h-6">
                  <input type="checkbox" name="enforce" value="1"
                         class="peer sr-only"
                         {{ ($enforcementActive ?? false) ? 'checked' : '' }}>
                  <span class="absolute inset-0 rounded-full bg-slate-300 dark:bg-slate-700
                               peer-checked:bg-emerald-500 transition"></span>
                  <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow
                               peer-checked:translate-x-5 transition"></span>
                </span>
                <span class="text-sm font-medium text-slate-800 dark:text-slate-200">
                  เปิดบังคับใช้ 2FA
                </span>
              </label>

              <div class="mt-3 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                เมื่อเปิด: แอดมินทุกคนจะต้องตั้งค่า 2FA ก่อนใช้งานหน้าใด ๆ ในระบบแอดมิน<br>
                เมื่อปิด (ค่าเริ่มต้น): แอดมินเลือกเปิด 2FA เองได้ และหากเปิดไว้จะต้องยืนยัน OTP ทุกเซสชัน
              </div>

              <div class="mt-4 flex gap-2">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold
                               bg-slate-900 hover:bg-slate-800 dark:bg-white dark:hover:bg-slate-100
                               text-white dark:text-slate-900 transition">
                  <i class="bi bi-check2"></i> บันทึกการตั้งค่า
                </button>
              </div>
            </form>
          @endif
        </div>
      </div>

      {{-- ───── Info sidebar ───── --}}
      <div class="rounded-2xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10">
          <h3 class="font-semibold text-slate-900 dark:text-white">
            <i class="bi bi-info-circle mr-1 text-indigo-600 dark:text-indigo-400"></i>Supported Apps
          </h3>
        </div>
        <div class="p-5">
          <ul class="space-y-2.5 text-sm text-slate-700 dark:text-slate-300">
            <li class="flex items-center gap-2"><i class="bi bi-google" style="color:#4285F4;"></i>Google Authenticator</li>
            <li class="flex items-center gap-2"><i class="bi bi-phone" style="color:#0073CF;"></i>Authy</li>
            <li class="flex items-center gap-2"><i class="bi bi-microsoft" style="color:#00A4EF;"></i>Microsoft Authenticator</li>
            <li class="flex items-center gap-2"><i class="bi bi-1-circle" style="color:#E5652A;"></i>1Password</li>
            <li class="flex items-center gap-2"><i class="bi bi-safe text-indigo-600 dark:text-indigo-400"></i>Any TOTP-compatible app</li>
          </ul>

          <div class="my-4 border-t border-slate-200 dark:border-white/10"></div>

          <h4 class="font-semibold text-sm mb-2 text-slate-900 dark:text-white">
            <i class="bi bi-question-circle mr-1 text-slate-500 dark:text-slate-400"></i>How it works
          </h4>
          <ol class="text-xs text-slate-600 dark:text-slate-400 pl-4 list-decimal space-y-1.5">
            <li>Install an authenticator app on your phone</li>
            <li>Scan the QR code with your app</li>
            <li>Enter the 6-digit code to verify</li>
            <li>On each login, enter the current code from your app</li>
          </ol>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection

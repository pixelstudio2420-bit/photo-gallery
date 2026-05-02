{{--
  Branded 419 — page expired / CSRF token mismatch.
  Self-contained inline CSS so it renders even if the asset pipeline
  is what produced the 419 (rare, but possible during deploys).
  Matches the look of errors/500.blade.php for visual consistency.
--}}
@php
  $siteName  = $siteName ?? \App\Models\AppSetting::get('site_name', '') ?: config('app.name', 'Loadroop');
  $brandHost = preg_replace('/^www\./i', '', parse_url(config('app.url', 'https://loadroop.com'), PHP_URL_HOST) ?: 'loadroop.com');
  // Where to send "ย้อนกลับ"? Prefer the referrer (the form they were
  // submitting), fall back to home if blank/external.
  $backUrl = url()->previous() && parse_url(url()->previous(), PHP_URL_HOST) === request()->getHost()
      ? url()->previous()
      : url('/');
@endphp
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title>419 — เซสชันหมดอายุ · {{ $siteName }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    :root { color-scheme: light dark; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; min-height: 100vh; }
    body {
      font-family: 'Sarabun', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      color: #0f172a;
      background:
        radial-gradient(900px 500px at 15% -10%, rgba(245,158,11,.18), transparent 60%),
        radial-gradient(800px 500px at 85% 110%, rgba(236,72,153,.10), transparent 60%),
        linear-gradient(160deg,#f8fafc 0%,#fef3c7 60%,#fce7f3 100%);
      display: flex; align-items: center; justify-content: center;
      padding: 24px;
    }
    @media (prefers-color-scheme: dark) {
      body {
        color: #f1f5f9;
        background:
          radial-gradient(900px 500px at 15% -10%, rgba(245,158,11,.20), transparent 60%),
          radial-gradient(800px 500px at 85% 110%, rgba(236,72,153,.15), transparent 60%),
          linear-gradient(160deg,#020617 0%,#1c1917 60%,#1e1b4b 100%);
      }
      .err-meta { color: #94a3b8; }
      .btn-ghost { background: rgba(245,158,11,.15); color: #fbbf24; }
      .btn-ghost:hover { background: rgba(245,158,11,.25); }
    }

    .err-stage { width: 100%; max-width: 520px; text-align: center; }

    .err-mark {
      font-size: clamp(96px, 18vw, 168px);
      font-weight: 800; line-height: 1; letter-spacing: -.04em;
      margin: 0;
      background: linear-gradient(135deg, #f59e0b 0%, #ec4899 50%, #7c3aed 100%);
      -webkit-background-clip: text; background-clip: text;
      color: transparent;
      filter: drop-shadow(0 12px 30px rgba(245,158,11,.3));
    }
    @supports not (background-clip: text) {
      .err-mark { color: #f59e0b; background: none; }
    }

    .err-icon {
      width: 72px; height: 72px;
      margin: 0 auto 12px;
      border-radius: 18px;
      background: linear-gradient(135deg, #f59e0b 0%, #ec4899 100%);
      color: #fff;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 32px;
      box-shadow: 0 16px 32px -8px rgba(245,158,11,.45);
      animation: float 3s ease-in-out infinite;
    }
    @keyframes float {
      0%,100% { transform: translateY(0); }
      50%     { transform: translateY(-6px); }
    }

    .err-eyebrow {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 12px; font-weight: 700;
      letter-spacing: .18em; text-transform: uppercase;
      color: #b45309;
      background: rgba(245,158,11,.12);
      padding: 5px 12px; border-radius: 999px;
      margin: 16px 0 8px;
    }
    @media (prefers-color-scheme: dark) {
      .err-eyebrow { color: #fbbf24; background: rgba(245,158,11,.18); }
    }

    .err-headline {
      font-size: 22px; font-weight: 700;
      margin: 0 0 6px; line-height: 1.3;
    }
    @media (min-width: 600px) {
      .err-headline { font-size: 26px; }
    }

    .err-meta {
      font-size: 14px; color: #475569;
      margin: 0 0 28px; line-height: 1.6;
    }

    .err-actions {
      display: flex; flex-wrap: wrap; gap: 10px;
      justify-content: center;
    }
    .btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 12px 22px;
      border-radius: 12px;
      font-weight: 600; font-size: 14px;
      text-decoration: none;
      border: 0; cursor: pointer;
      transition: transform .15s, box-shadow .2s, background .2s;
    }
    .btn-primary {
      background: linear-gradient(135deg, #f59e0b 0%, #ec4899 100%);
      color: #fff;
      box-shadow: 0 8px 18px -6px rgba(245,158,11,.45);
    }
    .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 24px -6px rgba(245,158,11,.55);
    }
    .btn-ghost {
      background: rgba(245,158,11,.1);
      color: #b45309;
    }
    .btn-ghost:hover { background: rgba(245,158,11,.18); }

    .err-footer {
      margin-top: 48px;
      font-size: 13px;
      color: #64748b;
      opacity: .7;
    }
    .err-footer i { color: #f59e0b; margin-right: 4px; }

    /* Recovery hint — only useful info */
    .err-tip {
      margin-top: 24px;
      padding: 12px 16px;
      border-radius: 12px;
      background: rgba(245,158,11,.06);
      border: 1px solid rgba(245,158,11,.18);
      font-size: 12px; color: #92400e;
      text-align: left;
    }
    @media (prefers-color-scheme: dark) {
      .err-tip { background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.25); color: #fbbf24; }
    }
    .err-tip i { margin-right: 6px; color: #f59e0b; }
  </style>
</head>
<body>
  <div class="err-stage">
    <div class="err-icon" aria-hidden="true">
      <i class="bi bi-clock-history"></i>
    </div>

    <h1 class="err-mark">419</h1>

    <div class="err-eyebrow">
      <i class="bi bi-shield-lock"></i>
      Session Expired
    </div>

    <h2 class="err-headline">เซสชันหมดอายุ</h2>
    <p class="err-meta">
      หน้านี้เปิดทิ้งไว้นานเกินไป — token ความปลอดภัยหมดอายุแล้ว<br>
      โปรดโหลดหน้าใหม่แล้วลองอีกครั้ง · ข้อมูลของคุณยังปลอดภัย
    </p>

    <div class="err-actions">
      <a href="{{ $backUrl }}" class="btn btn-primary">
        <i class="bi bi-arrow-clockwise"></i>
        โหลดหน้าใหม่
      </a>
      <a href="{{ url('/') }}" class="btn btn-ghost">
        <i class="bi bi-house-fill"></i>
        กลับหน้าแรก
      </a>
    </div>

    <div class="err-tip">
      <i class="bi bi-info-circle"></i>
      <strong>ทำไมถึงเกิดขึ้น?</strong>
      ระบบป้องกัน CSRF บังคับ refresh token ทุก 2 ชั่วโมง · เปิดหน้า login หลายแท็บ
      หรือทิ้งหน้าไว้นานๆ จะเจอกรณีนี้ได้ — เป็นการป้องกัน ไม่ใช่ข้อผิดพลาด
    </div>

    <div class="err-footer">
      <i class="bi bi-shield-check"></i>
      {{ $siteName }} · {{ $brandHost }}
    </div>
  </div>

  {{-- Auto-refresh CSRF token via the real /csrf-token endpoint.
       This sets a fresh XSRF-TOKEN cookie via the Set-Cookie response
       header, so a user who clicks "back" then re-submits gets a
       valid token without manually reloading. Best-effort — silently
       ignored on failure (CDN block, network blip, etc.). --}}
  <script>
    setTimeout(() => {
      fetch('{{ url('/csrf-token') }}', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
      }).catch(() => { /* silent — user can still reload manually */ });
    }, 1500);
  </script>
</body>
</html>

{{--
  Branded 500 — self-contained to survive a broken deploy.
  All CSS is inline so the page renders even if the Vite pipeline is
  what's broken. Uses the same indigo→purple→pink gradient as the rest
  of the site for visual continuity.
--}}
@php
  $siteName = $siteName ?? \App\Models\AppSetting::get('site_name', '') ?: config('app.name', 'Loadroop');
  $brandHost = preg_replace('/^www\./i', '', parse_url(config('app.url', 'https://loadroop.com'), PHP_URL_HOST) ?: 'loadroop.com');
  $supportEmail = (string) \App\Models\AppSetting::get('support_email', 'support@' . $brandHost);
  // Stable error reference admins can quote when reporting / searching logs.
  // Sentry (when configured) injects its own ID into the response — fall back
  // to a request-time random hex so the user always has SOMETHING to copy.
  $errorRef = function_exists('\\Sentry\\getLastEventId')
      ? (\Sentry\getLastEventId() ?? bin2hex(random_bytes(4)))
      : bin2hex(random_bytes(4));
@endphp
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title>500 — เกิดข้อผิดพลาด · {{ $siteName }}</title>
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
        radial-gradient(900px 500px at 15% -10%, rgba(99,102,241,.18), transparent 60%),
        radial-gradient(800px 500px at 85% 110%, rgba(236,72,153,.12), transparent 60%),
        linear-gradient(160deg,#f8fafc 0%,#f1f5f9 60%,#ede9fe 100%);
      display: flex; align-items: center; justify-content: center;
      padding: 24px;
    }
    @media (prefers-color-scheme: dark) {
      body {
        color: #f1f5f9;
        background:
          radial-gradient(900px 500px at 15% -10%, rgba(99,102,241,.25), transparent 60%),
          radial-gradient(800px 500px at 85% 110%, rgba(236,72,153,.18), transparent 60%),
          linear-gradient(160deg,#020617 0%,#0f172a 60%,#1e1b4b 100%);
      }
      .err-card { background: rgba(15,23,42,.6); border-color: rgba(124,58,237,.25); }
      .err-eyebrow { color: #a78bfa !important; }
      .err-meta { color: #94a3b8; }
      .err-ref code { background: rgba(124,58,237,.15); color: #c4b5fd; }
      .btn-ghost { background: rgba(124,58,237,.15); color: #c4b5fd; }
      .btn-ghost:hover { background: rgba(124,58,237,.25); }
    }

    .err-stage {
      width: 100%; max-width: 560px; text-align: center;
    }

    /* Big "500" mark — gradient text, bold, dramatic */
    .err-mark {
      font-size: clamp(96px, 18vw, 168px);
      font-weight: 800; line-height: 1; letter-spacing: -.04em;
      margin: 0;
      background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #ec4899 100%);
      -webkit-background-clip: text; background-clip: text;
      color: transparent;
      filter: drop-shadow(0 12px 30px rgba(124,58,237,.3));
    }
    @supports not (background-clip: text) {
      .err-mark { color: #4f46e5; background: none; }
    }

    /* Floating icon above the number */
    .err-icon {
      width: 72px; height: 72px;
      margin: 0 auto 12px;
      border-radius: 18px;
      background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #ec4899 100%);
      color: #fff;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 32px;
      box-shadow: 0 16px 32px -8px rgba(124,58,237,.45);
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
      color: #7c3aed;
      background: rgba(124,58,237,.1);
      padding: 5px 12px; border-radius: 999px;
      margin: 16px 0 8px;
    }

    .err-headline {
      font-size: 22px; font-weight: 700;
      margin: 0 0 6px;
      line-height: 1.3;
    }
    @media (min-width: 600px) {
      .err-headline { font-size: 26px; }
    }

    .err-meta {
      font-size: 14px; color: #475569;
      margin: 0 0 24px;
      line-height: 1.6;
    }

    /* Error reference chip — admins can quote this when reporting */
    .err-ref {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 12px;
      margin-bottom: 28px;
      color: #64748b;
    }
    .err-ref code {
      font-family: 'Courier New', monospace;
      background: rgba(99,102,241,.08);
      color: #4f46e5;
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 11px;
      cursor: pointer;
      transition: background .15s;
    }
    .err-ref code:hover { background: rgba(99,102,241,.16); }

    /* Action buttons row */
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
      background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
      color: #fff;
      box-shadow: 0 8px 18px -6px rgba(124,58,237,.45);
    }
    .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 24px -6px rgba(124,58,237,.55);
    }
    .btn-ghost {
      background: rgba(99,102,241,.08);
      color: #4f46e5;
    }
    .btn-ghost:hover { background: rgba(99,102,241,.14); }

    /* Footer ribbon — subtle brand reminder */
    .err-footer {
      margin-top: 48px;
      font-size: 13px;
      color: #64748b;
      opacity: .7;
    }
    .err-footer i { color: #7c3aed; margin-right: 4px; }
  </style>
</head>
<body>
  <div class="err-stage">
    <div class="err-icon" aria-hidden="true">
      <i class="bi bi-cone-striped"></i>
    </div>

    <h1 class="err-mark">500</h1>

    <div class="err-eyebrow">
      <i class="bi bi-tools"></i>
      Server Error
    </div>

    <h2 class="err-headline">เกิดข้อผิดพลาดบนเซิร์ฟเวอร์</h2>
    <p class="err-meta">
      ขออภัย เซิร์ฟเวอร์ทำงานผิดปกติชั่วคราว — ทีมงานได้รับการแจ้งเตือนแล้ว<br>
      โปรดลองโหลดหน้านี้อีกครั้งในอีกสักครู่
    </p>

    <div class="err-ref">
      <i class="bi bi-hash"></i>
      <span>รหัสอ้างอิง:</span>
      <code id="err-ref-code"
            onclick="navigator.clipboard.writeText(this.textContent.trim()); this.innerHTML='✓ คัดลอกแล้ว'; setTimeout(()=>this.textContent='{{ $errorRef }}',1500);"
            title="คลิกเพื่อคัดลอก">{{ $errorRef }}</code>
    </div>

    <div class="err-actions">
      <a href="{{ url('/') }}" class="btn btn-primary">
        <i class="bi bi-house-fill"></i>
        กลับหน้าแรก
      </a>
      <button type="button" onclick="location.reload()" class="btn btn-ghost">
        <i class="bi bi-arrow-clockwise"></i>
        ลองใหม่
      </button>
      @if($supportEmail)
      <a href="mailto:{{ $supportEmail }}?subject=Error%20{{ $errorRef }}&body=URL:%20{{ urlencode(request()->fullUrl()) }}%0A%0A" class="btn btn-ghost">
        <i class="bi bi-envelope"></i>
        แจ้งทีมงาน
      </a>
      @endif
    </div>

    <div class="err-footer">
      <i class="bi bi-shield-check"></i>
      {{ $siteName }} · {{ $brandHost }}
    </div>
  </div>
</body>
</html>

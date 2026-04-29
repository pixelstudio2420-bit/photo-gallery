<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>{{ $title ?? ($siteName ?? config('app.name')) }}</title>
<style>
  body { margin:0; padding:0; background:#f4f6f8; font-family:'Sarabun','Segoe UI',Arial,sans-serif; color:#333; -webkit-font-smoothing:antialiased; }
  table { border-collapse:collapse; border-spacing:0; }
  img { border:0; display:block; max-width:100%; height:auto; }
  a { color:#6366f1; text-decoration:none; }

  .email-wrapper { max-width:600px; margin:32px auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,.08); }
  .email-header { background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 50%,#ec4899 100%); padding:36px 40px; text-align:center; }
  .email-header h1 { margin:0; color:#fff; font-size:26px; font-weight:700; letter-spacing:.5px; text-shadow:0 2px 4px rgba(0,0,0,.1); }
  .email-header p.subtitle { margin:8px 0 0; color:rgba(255,255,255,.9); font-size:13px; font-weight:500; }
  .email-header .logo-icon { display:inline-block; width:56px; height:56px; background:rgba(255,255,255,.18); border-radius:50%; line-height:56px; font-size:28px; margin-bottom:12px; }

  .email-body { padding:36px 40px; }
  .email-body h2 { font-size:22px; margin:0 0 16px 0; color:#111827; font-weight:700; }
  .email-body h3 { font-size:17px; margin:24px 0 12px 0; color:#1f2937; font-weight:600; }
  .email-body p { line-height:1.7; color:#4b5563; font-size:15px; margin:0 0 14px 0; }
  .email-body strong { color:#111827; font-weight:600; }

  .email-footer { background:#f8f9fa; padding:24px 40px; text-align:center; border-top:1px solid #e9ecef; }
  .email-footer p { margin:4px 0; color:#9ca3af; font-size:12px; line-height:1.5; }
  .email-footer a { color:#6366f1; }
  .email-footer .social-icons { margin:12px 0; }
  .email-footer .social-icons a { display:inline-block; margin:0 6px; }

  /* Info box & rows */
  .info-box { background:#f8f9fa; border:1px solid #e9ecef; border-radius:10px; padding:18px 22px; margin:22px 0; }
  .info-box.highlight { background:linear-gradient(135deg,#f0f4ff 0%,#fae8ff 100%); border-color:#c7d2fe; }
  .info-row { padding:8px 0; border-bottom:1px solid #e9ecef; font-size:14px; }
  .info-row:last-child { border-bottom:none; padding-bottom:0; }
  .info-row:first-child { padding-top:0; }
  .info-row .label { color:#6b7280; font-weight:500; display:inline-block; min-width:140px; }
  .info-row .value { color:#111827; font-weight:600; }
  .info-row.total { border-top:2px solid #6366f1; padding-top:12px; margin-top:4px; }
  .info-row.total .label, .info-row.total .value { font-size:16px; font-weight:700; color:#111827; }

  /* Buttons */
  .btn-wrap { text-align:center; margin:28px 0; }
  .btn { display:inline-block; padding:14px 36px; background:linear-gradient(135deg,#6366f1,#4f46e5); color:#fff !important; text-decoration:none; border-radius:10px; font-weight:600; font-size:15px; box-shadow:0 4px 12px rgba(99,102,241,.35); transition:transform .2s; }
  .btn:hover { transform:translateY(-1px); }
  .btn-danger { background:linear-gradient(135deg,#ef4444,#dc2626); box-shadow:0 4px 12px rgba(239,68,68,.35); }
  .btn-success { background:linear-gradient(135deg,#22c55e,#16a34a); box-shadow:0 4px 12px rgba(34,197,94,.35); }
  .btn-warning { background:linear-gradient(135deg,#f59e0b,#d97706); box-shadow:0 4px 12px rgba(245,158,11,.35); }
  .btn-outline { background:#fff !important; color:#6366f1 !important; border:2px solid #6366f1; box-shadow:none; }

  /* Badges */
  .badge { display:inline-block; padding:5px 12px; border-radius:999px; font-size:12px; font-weight:600; }
  .badge-success { background:#dcfce7; color:#166534; }
  .badge-danger  { background:#fee2e2; color:#991b1b; }
  .badge-warning { background:#fef9c3; color:#854d0e; }
  .badge-info    { background:#dbeafe; color:#1e40af; }
  .badge-purple  { background:#f3e8ff; color:#6b21a8; }

  /* Highlight boxes */
  .alert-box { border-left:4px solid #6366f1; background:#f0f4ff; padding:16px 20px; border-radius:0 10px 10px 0; margin:20px 0; }
  .alert-box.success { border-color:#22c55e; background:#f0fdf4; }
  .alert-box.warning { border-color:#f59e0b; background:#fffbeb; }
  .alert-box.danger  { border-color:#ef4444; background:#fef2f2; }
  .alert-box p { margin:0; font-size:14px; }

  /* Divider */
  .divider { height:1px; background:#e9ecef; margin:24px 0; }

  /* Product card */
  .product-card { display:flex; align-items:center; gap:14px; background:#fafbfc; padding:14px; border-radius:10px; margin:10px 0; }
  .product-thumb { width:60px; height:60px; border-radius:8px; object-fit:cover; }
  .product-info { flex:1; }
  .product-name { font-weight:600; color:#111827; font-size:14px; margin:0; }
  .product-meta { font-size:12px; color:#6b7280; margin:3px 0 0 0; }
  .product-price { font-weight:700; color:#6366f1; font-size:15px; }

  /* Table (for items) */
  .items-table { width:100%; border-collapse:collapse; margin:16px 0; }
  .items-table th { background:#f8f9fa; padding:10px 12px; text-align:left; font-size:13px; color:#6b7280; font-weight:600; border-bottom:1px solid #e5e7eb; }
  .items-table td { padding:10px 12px; border-bottom:1px solid #f1f5f9; font-size:14px; color:#374151; }
  .items-table tr:last-child td { border-bottom:none; }
  .items-table .text-right { text-align:right; }

  /* Mobile responsive */
  @media (max-width: 600px) {
    .email-wrapper { margin:0; border-radius:0; }
    .email-header { padding:28px 24px; }
    .email-body { padding:28px 24px; }
    .email-footer { padding:20px 24px; }
    .info-row .label { display:block; min-width:0; }
    .btn { padding:14px 24px; font-size:14px; width:100%; box-sizing:border-box; }
  }
</style>
</head>
<body>

<!-- Preheader (hidden preview text) -->
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">
  {{ $preheader ?? '' }}
</div>

<div class="email-wrapper">

  {{-- Header --}}
  <div class="email-header">
    <div class="logo-icon">📸</div>
    <h1>{{ $siteName ?? config('app.name') }}</h1>
    @if(!empty($title))
      <p class="subtitle">{{ $title }}</p>
    @endif
  </div>

  {{-- Body --}}
  <div class="email-body">
    {!! $slot ?? ($body ?? '') !!}
  </div>

  {{-- Footer --}}
  <div class="email-footer">
    <p>
      <strong>{{ $siteName ?? config('app.name') }}</strong><br>
      อีเมลนี้ถูกส่งโดยอัตโนมัติ กรุณาอย่าตอบกลับ
    </p>
    @if(!empty($supportEmail))
      <p>หากมีข้อสงสัย กรุณาติดต่อ <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></p>
    @endif
    <p>&copy; {{ date('Y') }} {{ $siteName ?? config('app.name') }}. สงวนลิขสิทธิ์</p>
    @if(!empty($unsubscribeUrl))
      <p style="margin-top:10px;"><a href="{{ $unsubscribeUrl }}" style="color:#9ca3af;font-size:11px;">ยกเลิกการรับอีเมล</a></p>
    @endif
  </div>
</div>

</body>
</html>

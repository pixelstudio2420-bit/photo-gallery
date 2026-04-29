<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title ?? 'Notification' }}</title>
<style>
 body { margin:0; padding:0; background:#f4f6f8; font-family:'Segoe UI',Arial,sans-serif; color:#333; }
 .email-wrapper { max-width:600px; margin:32px auto; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
 .email-header { background:linear-gradient(135deg,#6366f1,#4f46e5); padding:32px 40px; text-align:center; }
 .email-header h1 { margin:0; color:#fff; font-size:24px; font-weight:700; letter-spacing:.5px; }
 .email-header p.subtitle { margin:6px 0 0; color:rgba(255,255,255,.85); font-size:13px; }
 .email-body { padding:36px 40px; }
 .email-footer { background:#f8f9fa; padding:20px 40px; text-align:center; border-top:1px solid #e9ecef; }
 .email-footer p { margin:0; color:#9ca3af; font-size:12px; }

 /* Info box & rows */
 .info-box { background:#f8f9fa; border-radius:8px; padding:16px 20px; margin:20px 0; }
 .info-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e9ecef; font-size:14px; }
 .info-row:last-child { border-bottom:none; }
 .info-row .label { color:#6b7280; font-weight:500; }
 .info-row .value { color:#111827; font-weight:600; }

 /* Buttons */
 .btn { display:inline-block; padding:12px 28px; background:linear-gradient(135deg,#6366f1,#4f46e5); color:#fff !important; text-decoration:none; border-radius:8px; font-weight:600; font-size:15px; margin:16px 0; }
 .btn-danger { background:linear-gradient(135deg,#ef4444,#dc2626); }
 .btn-success { background:linear-gradient(135deg,#22c55e,#16a34a); }

 /* Badges */
 .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:600; }
 .badge-success { background:#dcfce7; color:#166534; }
 .badge-danger { background:#fee2e2; color:#991b1b; }
 .badge-warning { background:#fef9c3; color:#854d0e; }
 .badge-info  { background:#dbeafe; color:#1e40af; }

 h2 { font-size:20px; margin-top:0; color:#111827; }
 p { line-height:1.6; color:#4b5563; font-size:14px; }
</style>
</head>
<body>
<div class="email-wrapper">

 {{-- Header --}}
 <div class="email-header">
  <h1>{{ $siteName ?? config('app.name') }}</h1>
  <p class="subtitle">{{ $title ?? '' }}</p>
 </div>

 {{-- Body --}}
 <div class="email-body">
  {!! $content !!}
 </div>

 {{-- Footer --}}
 <div class="email-footer">
  <p>&copy; {{ date('Y') }} {{ $siteName ?? config('app.name') }}. All rights reserved.</p>
 </div>

</div>
</body>
</html>

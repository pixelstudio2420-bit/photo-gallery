<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $siteName ?? config('app.name') }} API — ReDoc</title>
  <meta name="robots" content="noindex, nofollow">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <style>
    body { margin: 0; padding: 0; font-family: 'Sarabun', 'Segoe UI', sans-serif; }

    .api-topbar {
      background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
      color: white;
      padding: 18px 30px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .api-topbar .brand {
      display: flex; align-items: center; gap: 12px;
      font-size: 20px; font-weight: 700;
    }
    .api-topbar .brand-icon {
      width: 40px; height: 40px;
      background: rgba(255,255,255,0.2);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px;
    }
    .nav-links { display: flex; gap: 10px; }
    .nav-links a {
      color: white; text-decoration: none;
      padding: 8px 16px; border-radius: 8px;
      font-size: 14px; font-weight: 500;
      transition: background 0.2s;
    }
    .nav-links a:hover, .nav-links a.active { background: rgba(255,255,255,0.2); }
  </style>
</head>
<body>

<div class="api-topbar">
  <div class="brand">
    <div class="brand-icon">📡</div>
    <div>
      <div style="font-size:18px;">{{ $siteName ?? config('app.name') }} API</div>
      <div style="font-size:11px; opacity:0.8; font-weight:400;">ReDoc · OpenAPI 3.0</div>
    </div>
  </div>
  <nav class="nav-links">
    <a href="{{ route('api.docs') }}">Swagger UI</a>
    <a href="{{ route('api.docs.redoc') }}" class="active">ReDoc</a>
    <a href="{{ route('api.docs.guide') }}">Guide</a>
    <a href="{{ route('api.docs.webhooks') }}">Webhooks</a>
    <a href="{{ route('api.docs.spec') }}" target="_blank">Download JSON</a>
    <a href="{{ url('/') }}">← Back to App</a>
  </nav>
</div>

<redoc spec-url="{{ route('api.docs.spec') }}"
       theme='{ "colors": { "primary": { "main": "#6366f1" } }, "typography": { "fontFamily": "Sarabun, Segoe UI, sans-serif" } }'
       hide-download-button
       expand-responses="200,201"
       required-props-first>
</redoc>

<script src="https://cdn.jsdelivr.net/npm/redoc@2.1.5/bundles/redoc.standalone.js"></script>

</body>
</html>

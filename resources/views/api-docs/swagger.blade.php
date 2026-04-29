<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $siteName ?? config('app.name') }} API Documentation</title>
  <meta name="description" content="REST API documentation for the Photo Gallery platform">
  <meta name="robots" content="noindex, nofollow">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.11.8/swagger-ui.css">
  <link rel="icon" type="image/png" href="{{ asset('favicon.ico') }}">

  <style>
    body { margin: 0; padding: 0; font-family: 'Sarabun', 'Segoe UI', sans-serif; }

    /* Custom top bar */
    .api-topbar {
      background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
      color: white;
      padding: 18px 30px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .api-topbar .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 20px;
      font-weight: 700;
    }
    .api-topbar .brand-icon {
      width: 40px; height: 40px;
      background: rgba(255,255,255,0.2);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
    }
    .api-topbar .nav-links {
      display: flex;
      gap: 10px;
    }
    .api-topbar .nav-links a {
      color: white;
      text-decoration: none;
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
      transition: background 0.2s;
    }
    .api-topbar .nav-links a:hover,
    .api-topbar .nav-links a.active {
      background: rgba(255,255,255,0.2);
    }

    /* Hide default Swagger topbar */
    .swagger-ui .topbar { display: none; }

    /* Tweak Swagger UI */
    .swagger-ui .info .title { color: #111827; }
    .swagger-ui .opblock.opblock-get .opblock-summary-method { background: #3b82f6; }
    .swagger-ui .opblock.opblock-post .opblock-summary-method { background: #10b981; }
    .swagger-ui .opblock.opblock-put .opblock-summary-method { background: #f59e0b; }
    .swagger-ui .opblock.opblock-delete .opblock-summary-method { background: #ef4444; }

    #swagger-ui { max-width: 1400px; margin: 0 auto; }
  </style>
</head>
<body>

<div class="api-topbar">
  <div class="brand">
    <div class="brand-icon">📡</div>
    <div>
      <div style="font-size:18px;">{{ $siteName ?? config('app.name') }} API</div>
      <div style="font-size:11px; opacity:0.8; font-weight:400;">Interactive Documentation · OpenAPI 3.0</div>
    </div>
  </div>
  <nav class="nav-links">
    <a href="{{ route('api.docs') }}" class="active">Swagger UI</a>
    <a href="{{ route('api.docs.redoc') }}">ReDoc</a>
    <a href="{{ route('api.docs.guide') }}">Guide</a>
    <a href="{{ route('api.docs.webhooks') }}">Webhooks</a>
    <a href="{{ route('api.docs.spec') }}" target="_blank">Download JSON</a>
    <a href="{{ url('/') }}">← Back to App</a>
  </nav>
</div>

<div id="swagger-ui"></div>

<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.11.8/swagger-ui-bundle.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.11.8/swagger-ui-standalone-preset.js"></script>
<script>
window.onload = () => {
  SwaggerUIBundle({
    url: "{{ route('api.docs.spec') }}",
    dom_id: '#swagger-ui',
    deepLinking: true,
    presets: [
      SwaggerUIBundle.presets.apis,
      SwaggerUIStandalonePreset,
    ],
    plugins: [
      SwaggerUIBundle.plugins.DownloadUrl,
    ],
    layout: "BaseLayout",
    tryItOutEnabled: true,
    filter: true,
    displayRequestDuration: true,
    persistAuthorization: true,
    defaultModelsExpandDepth: 1,
    docExpansion: "list",
    syntaxHighlight: {
      theme: "monokai",
    },
    requestInterceptor: (request) => {
      // Add CSRF token automatically
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
      if (csrf) {
        request.headers['X-CSRF-TOKEN'] = csrf;
      }
      return request;
    },
  });
};
</script>

</body>
</html>

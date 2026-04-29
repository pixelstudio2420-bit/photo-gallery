@extends('layouts.app')

@section('title', 'API Developer Guide')

@section('content')
<div class="max-w-4xl mx-auto py-6 prose-content">

  {{-- Header --}}
  <div class="mb-8">
    <nav class="flex gap-2 mb-6 flex-wrap text-sm">
      <a href="{{ route('api.docs') }}" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-indigo-100 hover:text-indigo-700">Swagger UI</a>
      <a href="{{ route('api.docs.redoc') }}" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-indigo-100 hover:text-indigo-700">ReDoc</a>
      <a href="{{ route('api.docs.guide') }}" class="px-3 py-1.5 bg-indigo-500 text-white rounded-lg font-semibold">Developer Guide</a>
      <a href="{{ route('api.docs.webhooks') }}" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-indigo-100 hover:text-indigo-700">Webhooks</a>
      <a href="{{ route('api.docs.spec') }}" target="_blank" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-indigo-100 hover:text-indigo-700">OpenAPI JSON</a>
    </nav>

    <h1 class="text-4xl font-bold text-slate-800 mb-3">
      <i class="bi bi-book text-indigo-500 mr-2"></i>API Developer Guide
    </h1>
    <p class="text-lg text-gray-600">คู่มือสำหรับนักพัฒนา การใช้งาน Photo Gallery REST API</p>
  </div>

  {{-- Quick Start --}}
  <section class="mb-10 bg-gradient-to-br from-indigo-50 to-violet-50 border border-indigo-100 rounded-2xl p-6">
    <h2 class="text-2xl font-bold text-slate-800 mb-3">
      <i class="bi bi-rocket-takeoff text-indigo-500 mr-1"></i>Quick Start
    </h2>
    <div class="space-y-3 text-gray-700">
      <p><strong>Base URL:</strong> <code class="bg-white px-2 py-1 rounded text-indigo-600">{{ url('/') }}</code></p>
      <p><strong>Content-Type:</strong> <code class="bg-white px-2 py-1 rounded">application/json</code></p>
      <p><strong>Format:</strong> OpenAPI 3.0.3</p>
    </div>
  </section>

  {{-- Authentication --}}
  <section class="mb-10">
    <h2 class="text-2xl font-bold text-slate-800 mb-4">
      <i class="bi bi-shield-lock text-indigo-500 mr-1"></i>Authentication
    </h2>

    <p class="mb-4 text-gray-700">API นี้รองรับ 2 วิธีการยืนยันตัวตน:</p>

    {{-- Session Cookie --}}
    <div class="mb-6 bg-white border border-gray-100 rounded-2xl p-5">
      <h3 class="font-bold text-slate-800 mb-2">
        <span class="inline-block w-6 h-6 bg-blue-500 text-white rounded-full text-sm text-center leading-6 mr-1">1</span>
        Session Cookie (Default)
      </h3>
      <p class="text-gray-700 mb-3">สำหรับ web app ที่ login ผ่าน browser — ส่ง cookies อัตโนมัติ</p>

      <div class="bg-slate-900 text-slate-100 p-4 rounded-lg font-mono text-sm overflow-x-auto">
<pre>// Laravel Blade example
const csrf = document.querySelector('meta[name="csrf-token"]').content;

fetch('/api/cart', {
  method: 'POST',
  credentials: 'include',        // ส่ง cookies ไปด้วย
  headers: {
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': csrf,         // จำเป็นสำหรับ POST/PUT/DELETE
    'Accept': 'application/json'
  },
  body: JSON.stringify({ file_id: '...', event_id: 123 })
});</pre>
      </div>
    </div>

    {{-- API Key --}}
    <div class="mb-6 bg-white border border-gray-100 rounded-2xl p-5">
      <h3 class="font-bold text-slate-800 mb-2">
        <span class="inline-block w-6 h-6 bg-emerald-500 text-white rounded-full text-sm text-center leading-6 mr-1">2</span>
        API Key (Machine-to-Machine)
      </h3>
      <p class="text-gray-700 mb-3">สำหรับ integration, mobile apps, CLI scripts — สร้าง key ใน Admin Panel</p>

      <div class="bg-slate-900 text-slate-100 p-4 rounded-lg font-mono text-sm overflow-x-auto">
<pre>// cURL example
curl -X GET "{{ url('/api/notifications') }}" \
  -H "X-API-Key: YOUR_API_KEY_HERE" \
  -H "Accept: application/json"

// JavaScript (Node.js, axios)
const response = await axios.get('{{ url('/api/notifications') }}', {
  headers: {
    'X-API-Key': process.env.PHOTO_GALLERY_API_KEY,
    'Accept': 'application/json'
  }
});

// Python (requests)
import requests
response = requests.get(
    '{{ url('/api/notifications') }}',
    headers={'X-API-Key': 'YOUR_KEY', 'Accept': 'application/json'}
)</pre>
      </div>

      <div class="mt-3 p-3 bg-amber-50 border-l-4 border-amber-400 rounded-r-lg">
        <p class="text-sm text-amber-900">
          <strong><i class="bi bi-exclamation-triangle"></i> ข้อควรระวัง:</strong>
          API Key เป็นข้อมูลลับ ห้ามส่งผ่าน URL หรือ commit เข้า git repository
        </p>
      </div>
    </div>
  </section>

  {{-- Rate Limiting --}}
  <section class="mb-10">
    <h2 class="text-2xl font-bold text-slate-800 mb-4">
      <i class="bi bi-speedometer2 text-indigo-500 mr-1"></i>Rate Limiting
    </h2>

    <table class="w-full border-collapse bg-white rounded-2xl overflow-hidden shadow-sm mb-4">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left p-3 border-b">ประเภท</th>
          <th class="text-left p-3 border-b">Limit</th>
          <th class="text-left p-3 border-b">Key</th>
        </tr>
      </thead>
      <tbody>
        <tr><td class="p-3 border-b">Authenticated User</td><td class="p-3 border-b"><code>60/min</code></td><td class="p-3 border-b">user_id</td></tr>
        <tr><td class="p-3 border-b">Unauthenticated</td><td class="p-3 border-b"><code>20/min</code></td><td class="p-3 border-b">ip_address</td></tr>
        <tr><td class="p-3 border-b">Webhooks</td><td class="p-3 border-b"><code>120/min</code></td><td class="p-3 border-b">ip + signature</td></tr>
        <tr><td class="p-3 border-b">Blog AI</td><td class="p-3 border-b"><code>10/min</code></td><td class="p-3 border-b">admin_id</td></tr>
        <tr><td class="p-3">Drive Image Proxy</td><td class="p-3"><code>120/min</code></td><td class="p-3">ip + file_id</td></tr>
      </tbody>
    </table>

    <p class="text-gray-700 mb-2">Response headers ที่ส่งกลับ:</p>
    <div class="bg-slate-900 text-slate-100 p-4 rounded-lg font-mono text-sm">
<pre>X-RateLimit-Limit: 60
X-RateLimit-Remaining: 42
X-RateLimit-Reset: 1713350400</pre>
    </div>
  </section>

  {{-- Response Format --}}
  <section class="mb-10">
    <h2 class="text-2xl font-bold text-slate-800 mb-4">
      <i class="bi bi-braces text-indigo-500 mr-1"></i>Response Format
    </h2>

    <div class="grid md:grid-cols-2 gap-4 mb-4">
      <div>
        <h3 class="font-semibold text-emerald-600 mb-2">✅ Success</h3>
        <div class="bg-slate-900 text-slate-100 p-4 rounded-lg font-mono text-sm">
<pre>{
  "success": true,
  "data": {
    "id": 123,
    "name": "Example"
  },
  "message": "Done"
}</pre>
        </div>
      </div>
      <div>
        <h3 class="font-semibold text-red-600 mb-2">❌ Error</h3>
        <div class="bg-slate-900 text-slate-100 p-4 rounded-lg font-mono text-sm">
<pre>{
  "success": false,
  "error": "Unauthenticated",
  "code": "AUTH_REQUIRED"
}</pre>
        </div>
      </div>
    </div>
  </section>

  {{-- Example Requests --}}
  <section class="mb-10">
    <h2 class="text-2xl font-bold text-slate-800 mb-4">
      <i class="bi bi-code-square text-indigo-500 mr-1"></i>Example Requests
    </h2>

    <div class="space-y-4">
      {{-- Get notifications --}}
      <details class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
        <summary class="p-4 cursor-pointer font-semibold text-slate-800 hover:bg-gray-50">
          <span class="inline-block px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-bold mr-2">GET</span>
          Get unread notifications
        </summary>
        <div class="p-4 border-t border-gray-100">
          <div class="bg-slate-900 text-slate-100 p-4 rounded-lg font-mono text-sm">
<pre>// Request
GET /api/notifications?since=2026-04-17T00:00:00Z
Accept: application/json

// Response 200 OK
{
  "success": true,
  "unread_count": 3,
  "notifications": [
    {
      "id": 1,
      "type": "order",
      "title": "คำสั่งซื้อใหม่",
      "message": "ยอด ฿500 รอการชำระเงิน",
      "is_read": false,
      "action_url": "orders/123",
      "created_at": "2026-04-17T12:00:00Z"
    }
  ]
}</pre>
          </div>
        </div>
      </details>

      {{-- Add to cart --}}
      <details class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
        <summary class="p-4 cursor-pointer font-semibold text-slate-800 hover:bg-gray-50">
          <span class="inline-block px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-xs font-bold mr-2">POST</span>
          Add item to cart
        </summary>
        <div class="p-4 border-t border-gray-100">
          <div class="bg-slate-900 text-slate-100 p-4 rounded-lg font-mono text-sm">
<pre>// Request
POST /api/cart/add
Content-Type: application/json
X-CSRF-TOKEN: abc123...

{
  "file_id": "1ABC_drive_file_id",
  "event_id": 42,
  "name": "IMG_5432.jpg",
  "thumbnail": "https://..."
}

// Response 200 OK
{
  "success": true,
  "count": 4,
  "total": 200.00
}</pre>
          </div>
        </div>
      </details>

      {{-- Generate blog article --}}
      <details class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
        <summary class="p-4 cursor-pointer font-semibold text-slate-800 hover:bg-gray-50">
          <span class="inline-block px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-xs font-bold mr-2">POST</span>
          Generate SEO article with AI (Admin)
        </summary>
        <div class="p-4 border-t border-gray-100">
          <div class="bg-slate-900 text-slate-100 p-4 rounded-lg font-mono text-sm">
<pre>// Request
POST /admin/blog/ai/generate-article
Content-Type: application/json
X-API-Key: YOUR_KEY

{
  "keyword": "การถ่ายภาพพรีเวดดิ้ง",
  "word_count": 1500,
  "tone": "professional",
  "language": "th",
  "provider": "claude",
  "include_faq": true
}

// Response 200 OK (truncated)
{
  "success": true,
  "data": {
    "title": "การถ่ายภาพพรีเวดดิ้ง: คู่มือฉบับสมบูรณ์",
    "content": "&lt;h2&gt;ทำไมต้องถ่ายพรีเวดดิ้ง...&lt;/h2&gt;",
    "meta_title": "การถ่ายภาพพรีเวดดิ้ง - คู่มือครบ...",
    "meta_description": "เรียนรู้...",
    "tags": ["พรีเวดดิ้ง", "ถ่ายภาพ"],
    "tokens_used": 3500,
    "cost_usd": 0.025
  }
}</pre>
          </div>
        </div>
      </details>
    </div>
  </section>

  {{-- Error Codes --}}
  <section class="mb-10">
    <h2 class="text-2xl font-bold text-slate-800 mb-4">
      <i class="bi bi-exclamation-diamond text-indigo-500 mr-1"></i>Error Codes
    </h2>

    <table class="w-full border-collapse bg-white rounded-2xl overflow-hidden shadow-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left p-3 border-b">HTTP</th>
          <th class="text-left p-3 border-b">Code</th>
          <th class="text-left p-3 border-b">Meaning</th>
        </tr>
      </thead>
      <tbody>
        <tr><td class="p-3 border-b font-mono text-blue-600">200</td><td class="p-3 border-b"><code>OK</code></td><td class="p-3 border-b">Success</td></tr>
        <tr><td class="p-3 border-b font-mono text-blue-600">201</td><td class="p-3 border-b"><code>CREATED</code></td><td class="p-3 border-b">Resource created</td></tr>
        <tr><td class="p-3 border-b font-mono text-amber-600">400</td><td class="p-3 border-b"><code>BAD_REQUEST</code></td><td class="p-3 border-b">Invalid request payload</td></tr>
        <tr><td class="p-3 border-b font-mono text-amber-600">401</td><td class="p-3 border-b"><code>AUTH_REQUIRED</code></td><td class="p-3 border-b">Authentication needed</td></tr>
        <tr><td class="p-3 border-b font-mono text-amber-600">403</td><td class="p-3 border-b"><code>FORBIDDEN</code></td><td class="p-3 border-b">Permission denied</td></tr>
        <tr><td class="p-3 border-b font-mono text-amber-600">404</td><td class="p-3 border-b"><code>NOT_FOUND</code></td><td class="p-3 border-b">Resource not found</td></tr>
        <tr><td class="p-3 border-b font-mono text-amber-600">419</td><td class="p-3 border-b"><code>CSRF_MISMATCH</code></td><td class="p-3 border-b">Missing/invalid CSRF token</td></tr>
        <tr><td class="p-3 border-b font-mono text-amber-600">422</td><td class="p-3 border-b"><code>VALIDATION_ERROR</code></td><td class="p-3 border-b">Validation failed</td></tr>
        <tr><td class="p-3 border-b font-mono text-red-600">429</td><td class="p-3 border-b"><code>TOO_MANY_REQUESTS</code></td><td class="p-3 border-b">Rate limit exceeded</td></tr>
        <tr><td class="p-3 font-mono text-red-600">500</td><td class="p-3"><code>SERVER_ERROR</code></td><td class="p-3">Internal server error</td></tr>
      </tbody>
    </table>
  </section>

  {{-- SDK / Libraries --}}
  <section class="mb-10 bg-gradient-to-br from-slate-50 to-gray-100 border border-gray-200 rounded-2xl p-6">
    <h2 class="text-2xl font-bold text-slate-800 mb-3">
      <i class="bi bi-code-slash text-indigo-500 mr-1"></i>Recommended Libraries
    </h2>
    <div class="grid md:grid-cols-3 gap-4">
      <div class="bg-white rounded-xl p-4">
        <div class="font-semibold text-slate-800 mb-1">JavaScript / Node.js</div>
        <code class="text-sm text-indigo-600">axios</code> · <code class="text-sm text-indigo-600">fetch</code>
      </div>
      <div class="bg-white rounded-xl p-4">
        <div class="font-semibold text-slate-800 mb-1">Python</div>
        <code class="text-sm text-indigo-600">requests</code> · <code class="text-sm text-indigo-600">httpx</code>
      </div>
      <div class="bg-white rounded-xl p-4">
        <div class="font-semibold text-slate-800 mb-1">PHP</div>
        <code class="text-sm text-indigo-600">Guzzle</code> · <code class="text-sm text-indigo-600">Laravel Http</code>
      </div>
    </div>
  </section>

  <div class="mt-8 pt-6 border-t text-center text-gray-500 text-sm">
    <p>มีคำถาม? ติดต่อทีมงานผ่าน <a href="{{ route('contact') }}" class="text-indigo-600 hover:underline">Contact</a></p>
  </div>
</div>
@endsection

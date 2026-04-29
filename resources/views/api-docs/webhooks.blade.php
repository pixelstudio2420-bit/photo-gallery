@extends('layouts.app')

@section('title', 'Webhooks Documentation')

@section('content')
<div class="max-w-4xl mx-auto py-6">

  {{-- Nav --}}
  <nav class="flex gap-2 mb-6 flex-wrap text-sm">
    <a href="{{ route('api.docs') }}" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-indigo-100 hover:text-indigo-700">Swagger UI</a>
    <a href="{{ route('api.docs.redoc') }}" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-indigo-100 hover:text-indigo-700">ReDoc</a>
    <a href="{{ route('api.docs.guide') }}" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-indigo-100 hover:text-indigo-700">Developer Guide</a>
    <a href="{{ route('api.docs.webhooks') }}" class="px-3 py-1.5 bg-indigo-500 text-white rounded-lg font-semibold">Webhooks</a>
    <a href="{{ route('api.docs.spec') }}" target="_blank" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-indigo-100 hover:text-indigo-700">OpenAPI JSON</a>
  </nav>

  <h1 class="text-4xl font-bold text-slate-800 mb-3">
    <i class="bi bi-plug text-indigo-500 mr-2"></i>Webhooks Documentation
  </h1>
  <p class="text-lg text-gray-600 mb-8">Documentation for incoming webhooks (payment gateways & integrations)</p>

  {{-- Overview --}}
  <section class="mb-8 bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100 rounded-2xl p-6">
    <h2 class="text-xl font-bold text-slate-800 mb-2">
      <i class="bi bi-info-circle text-blue-500 mr-1"></i>Overview
    </h2>
    <p class="text-gray-700 mb-2">Webhooks คือ HTTP POST requests ที่ external services ส่งเข้ามาเมื่อมี event เกิดขึ้น เช่น การชำระเงินสำเร็จ การ refund การอัพเดท Google Drive</p>
    <p class="text-gray-700"><strong>Authentication:</strong> แต่ละ webhook ใช้ signature verification หรือ secret token — ไม่ต้อง login</p>
  </section>

  {{-- Webhook Endpoints --}}
  <section class="mb-10">
    <h2 class="text-2xl font-bold text-slate-800 mb-4">Available Webhooks</h2>

    @php
      $webhooks = [
        [
          'provider' => 'Stripe',
          'icon'     => '💳',
          'url'      => '/api/webhooks/stripe',
          'method'   => 'POST',
          'signature' => 'Stripe-Signature header',
          'events'   => ['payment_intent.succeeded', 'payment_intent.payment_failed', 'charge.refunded', 'charge.dispute.created'],
          'example_payload' => '{
  "id": "evt_1ABC...",
  "object": "event",
  "type": "payment_intent.succeeded",
  "data": {
    "object": {
      "id": "pi_1ABC...",
      "amount": 50000,
      "currency": "thb",
      "status": "succeeded",
      "metadata": { "order_id": "123" }
    }
  }
}',
        ],
        [
          'provider' => 'Omise',
          'icon'     => '💰',
          'url'      => '/api/webhooks/omise',
          'method'   => 'POST',
          'signature' => 'Omise webhook secret in body',
          'events'   => ['charge.complete', 'charge.failed', 'refund.create'],
          'example_payload' => '{
  "object": "event",
  "key": "charge.complete",
  "data": {
    "id": "chrg_test_...",
    "amount": 50000,
    "status": "successful",
    "metadata": { "order_id": "123" }
  }
}',
        ],
        [
          'provider' => 'PayPal',
          'icon'     => '🅿️',
          'url'      => '/api/webhooks/paypal',
          'method'   => 'POST',
          'signature' => 'PAYPAL-TRANSMISSION-SIG header',
          'events'   => ['PAYMENT.SALE.COMPLETED', 'PAYMENT.SALE.REFUNDED'],
          'example_payload' => '{
  "event_type": "PAYMENT.SALE.COMPLETED",
  "resource": {
    "id": "...",
    "amount": { "total": "500.00", "currency": "THB" },
    "state": "completed",
    "custom": "order_id:123"
  }
}',
        ],
        [
          'provider' => 'LINE Pay',
          'icon'     => '💚',
          'url'      => '/api/webhooks/linepay',
          'method'   => 'POST',
          'signature' => 'x-line-signature header',
          'events'   => ['payment.completed', 'payment.cancelled'],
          'example_payload' => '{
  "transactionId": "...",
  "orderId": "ORD-123",
  "status": "SUCCESS"
}',
        ],
        [
          'provider' => '2C2P',
          'icon'     => '🏦',
          'url'      => '/api/webhooks/2c2p',
          'method'   => 'POST',
          'signature' => 'paymentToken JWT',
          'events'   => ['payment success', 'payment failed'],
          'example_payload' => 'payload=<jwt_token>',
        ],
        [
          'provider' => 'TrueMoney Wallet',
          'icon'     => '💸',
          'url'      => '/api/webhooks/truemoney',
          'method'   => 'POST',
          'signature' => 'HMAC-SHA256 signature',
          'events'   => ['transaction.completed', 'transaction.failed'],
          'example_payload' => '{
  "transaction_id": "...",
  "reference_id": "ORD-123",
  "amount": 500.00,
  "status": "completed"
}',
        ],
        [
          'provider' => 'SlipOK',
          'icon'     => '📄',
          'url'      => '/api/webhooks/slipok',
          'method'   => 'POST',
          'signature' => 'x-webhook-secret header',
          'events'   => ['slip.verified', 'slip.rejected'],
          'example_payload' => '{
  "reference": "SLIP-123",
  "success": true,
  "amount": 500.00,
  "transaction_date": "2026-04-17",
  "receiver_bank": "SCB"
}',
        ],
        [
          'provider' => 'Google Drive',
          'icon'     => '📁',
          'url'      => '/api/webhooks/google-drive',
          'method'   => 'POST',
          'signature' => 'X-Goog-Channel-Token header',
          'events'   => ['file.added', 'file.updated', 'file.removed'],
          'example_payload' => 'Empty body (query Drive API for changes)',
        ],
        [
          'provider' => 'LINE Notify',
          'icon'     => '🔔',
          'url'      => '/api/webhooks/line',
          'method'   => 'POST',
          'signature' => 'x-line-signature header',
          'events'   => ['message.received', 'follow', 'unfollow'],
          'example_payload' => '{
  "events": [{
    "type": "message",
    "source": { "userId": "U..." },
    "message": { "text": "Hello" }
  }]
}',
        ],
      ];
    @endphp

    <div class="space-y-4">
      @foreach($webhooks as $wh)
      <details class="bg-white border border-gray-100 rounded-2xl overflow-hidden group">
        <summary class="p-5 cursor-pointer font-semibold text-slate-800 hover:bg-gray-50 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-2xl">{{ $wh['icon'] }}</span>
            <div>
              <div class="text-lg">{{ $wh['provider'] }}</div>
              <div class="flex items-center gap-2 mt-1">
                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-[10px] font-bold">{{ $wh['method'] }}</span>
                <code class="text-sm text-gray-500 font-mono">{{ $wh['url'] }}</code>
              </div>
            </div>
          </div>
          <i class="bi bi-chevron-down text-gray-400 group-open:rotate-180 transition"></i>
        </summary>

        <div class="p-5 border-t border-gray-100 space-y-4">
          <div>
            <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Signature Verification</div>
            <code class="text-sm text-indigo-600">{{ $wh['signature'] }}</code>
          </div>

          <div>
            <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Events Handled</div>
            <div class="flex flex-wrap gap-1">
              @foreach($wh['events'] as $ev)
              <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs font-mono">{{ $ev }}</span>
              @endforeach
            </div>
          </div>

          <div>
            <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Example Payload</div>
            <pre class="bg-slate-900 text-slate-100 p-4 rounded-lg text-sm overflow-x-auto"><code>{{ $wh['example_payload'] }}</code></pre>
          </div>

          <div class="bg-emerald-50 border-l-4 border-emerald-400 p-3 rounded-r-lg">
            <p class="text-sm text-emerald-800">
              <strong><i class="bi bi-check-circle"></i> Expected Response:</strong>
              HTTP 200 with <code>{"success": true}</code>
            </p>
          </div>
        </div>
      </details>
      @endforeach
    </div>
  </section>

  {{-- Configure --}}
  <section class="mb-10">
    <h2 class="text-2xl font-bold text-slate-800 mb-4">
      <i class="bi bi-gear text-indigo-500 mr-1"></i>How to Configure
    </h2>

    <div class="bg-white border border-gray-100 rounded-2xl p-6">
      <ol class="space-y-3 text-gray-700 list-decimal list-inside">
        <li>ไปที่ <strong>Admin Panel → Settings → Webhooks</strong></li>
        <li>เลือก payment gateway ที่ต้องการ configure</li>
        <li>คัดลอก webhook URL จากด้านบน</li>
        <li>ตั้งค่า webhook ใน dashboard ของ gateway (เช่น Stripe Dashboard → Developers → Webhooks)</li>
        <li>ตั้ง signing secret / token ใน <code>Admin → Settings → Payment Gateways</code></li>
        <li>ทดสอบโดยกด "Send test webhook" จาก gateway dashboard</li>
      </ol>
    </div>
  </section>

  {{-- Testing --}}
  <section class="mb-10">
    <h2 class="text-2xl font-bold text-slate-800 mb-4">
      <i class="bi bi-bug text-indigo-500 mr-1"></i>Testing Webhooks
    </h2>

    <p class="text-gray-700 mb-3">Tools สำหรับ test webhooks ในระหว่างการพัฒนา:</p>

    <div class="grid md:grid-cols-2 gap-4">
      <div class="bg-white border border-gray-100 rounded-2xl p-5">
        <h3 class="font-bold text-slate-800 mb-2">
          <i class="bi bi-terminal text-indigo-500 mr-1"></i>Stripe CLI
        </h3>
        <pre class="bg-slate-900 text-slate-100 p-3 rounded text-sm overflow-x-auto"><code>stripe listen --forward-to \
  {{ url('/api/webhooks/stripe') }}

stripe trigger payment_intent.succeeded</code></pre>
      </div>

      <div class="bg-white border border-gray-100 rounded-2xl p-5">
        <h3 class="font-bold text-slate-800 mb-2">
          <i class="bi bi-globe text-indigo-500 mr-1"></i>ngrok
        </h3>
        <pre class="bg-slate-900 text-slate-100 p-3 rounded text-sm overflow-x-auto"><code># Expose local dev to public URL
ngrok http 8001

# Use the https URL for webhook config
https://abc123.ngrok.io/api/webhooks/stripe</code></pre>
      </div>

      <div class="bg-white border border-gray-100 rounded-2xl p-5">
        <h3 class="font-bold text-slate-800 mb-2">
          <i class="bi bi-curl text-indigo-500 mr-1"></i>Manual cURL
        </h3>
        <pre class="bg-slate-900 text-slate-100 p-3 rounded text-sm overflow-x-auto"><code>curl -X POST {{ url('/api/webhooks/stripe') }} \
  -H "Content-Type: application/json" \
  -H "Stripe-Signature: t=...,v1=..." \
  -d '&#123;"type":"payment_intent.succeeded","data":&#123;...&#125;&#125;'</code></pre>
      </div>

      <div class="bg-white border border-gray-100 rounded-2xl p-5">
        <h3 class="font-bold text-slate-800 mb-2">
          <i class="bi bi-list-check text-indigo-500 mr-1"></i>Logs
        </h3>
        <p class="text-sm text-gray-700 mb-2">ดู webhook logs ที่:</p>
        <code class="text-sm text-indigo-600">Admin → Activity Log → Filter "webhook"</code>
      </div>
    </div>
  </section>
</div>
@endsection

@extends('layouts.admin')

@section('title', 'ทดสอบ LINE Messaging')

@section('content')
<div class="max-w-5xl mx-auto pb-16">

  {{-- ────────── HEADER ────────── --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h4 class="text-2xl font-bold text-slate-900 dark:text-white mb-1 flex items-center gap-3 tracking-tight">
        <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl shadow-md"
              style="background:#06C755;">
          <i class="bi bi-line text-white text-xl"></i>
        </span>
        ทดสอบ LINE Messaging
      </h4>
      <p class="text-sm text-slate-500 dark:text-slate-400 ml-14">
        ตรวจสอบการตั้งค่าแบบ end-to-end ก่อนใช้งานจริง
      </p>
    </div>
    <a href="{{ route('admin.settings.line') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-medium hover:bg-slate-200">
      <i class="bi bi-gear"></i> ตั้งค่า LINE
    </a>
  </div>

  {{-- ────────── DIAGNOSTICS HEALTH ────────── --}}
  <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden mb-5">
    <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center justify-between">
      <h6 class="font-bold text-slate-900 dark:text-white m-0 flex items-center gap-2">
        <i class="bi bi-clipboard-check text-emerald-500"></i> 1. Diagnostics
      </h6>
      <span class="px-3 py-1 rounded-full text-xs font-bold {{ $diagnostics['all_ok'] ? 'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300' : 'bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300' }}">
        {{ $diagnostics['all_ok'] ? '✓ พร้อมใช้งาน' : '✗ ยังไม่พร้อม' }}
      </span>
    </div>
    <div class="p-5">
      <div class="space-y-2">
        @foreach($diagnostics['checks'] as $check)
          <div class="flex items-start gap-3 p-3 rounded-lg {{ $check['ok'] ? 'bg-emerald-50 dark:bg-emerald-500/10' : 'bg-rose-50 dark:bg-rose-500/10' }}">
            <i class="bi {{ $check['ok'] ? 'bi-check-circle-fill text-emerald-600 dark:text-emerald-400' : 'bi-x-circle-fill text-rose-600 dark:text-rose-400' }} text-lg shrink-0 mt-0.5"></i>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $check['name'] }}</div>
              @if($check['hint'])
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $check['hint'] }}</div>
              @endif
            </div>
          </div>
        @endforeach
      </div>

      @if($channelInfo)
        <div class="mt-4 pt-4 border-t border-slate-200 dark:border-white/10 grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
          <div>
            <div class="text-slate-500 dark:text-slate-400">Channel ID</div>
            <div class="font-mono text-slate-900 dark:text-white truncate">{{ $channelInfo['userId'] ?? '—' }}</div>
          </div>
          <div>
            <div class="text-slate-500 dark:text-slate-400">Bot Display Name</div>
            <div class="font-bold text-slate-900 dark:text-white">{{ $channelInfo['displayName'] ?? '—' }}</div>
          </div>
          @if($diagnostics['quota'])
            <div>
              <div class="text-slate-500 dark:text-slate-400">Quota</div>
              <div class="font-bold text-slate-900 dark:text-white">
                {{ number_format((int)($diagnostics['quota']['consumed'] ?? 0)) }}
                <span class="text-slate-400 text-[10px]">/ {{ $diagnostics['quota']['limit'] ?? '—' }}</span>
              </div>
            </div>
            <div>
              <div class="text-slate-500 dark:text-slate-400">Followers</div>
              <div class="font-bold text-slate-900 dark:text-white">{{ number_format((int)($diagnostics['quota']['followers'] ?? 0)) }}</div>
            </div>
          @endif
        </div>
      @endif
    </div>
  </div>

  {{-- ────────── TEST RESULT BANNER ────────── --}}
  @if(session('test_result'))
    @php $tr = session('test_result'); @endphp
    <div class="rounded-2xl border-2 mb-5 overflow-hidden
                {{ $tr['ok'] ? 'border-emerald-300 dark:border-emerald-500/40 bg-emerald-50 dark:bg-emerald-500/10' : 'border-rose-300 dark:border-rose-500/40 bg-rose-50 dark:bg-rose-500/10' }}">
      <div class="px-5 py-4 flex items-start gap-3">
        <i class="bi {{ $tr['ok'] ? 'bi-check-circle-fill text-emerald-600 dark:text-emerald-400' : 'bi-x-circle-fill text-rose-600 dark:text-rose-400' }} text-2xl shrink-0"></i>
        <div class="flex-1 min-w-0">
          <div class="font-bold text-slate-900 dark:text-white mb-1">
            @if(($tr['kind'] ?? '') === 'text')   ผลทดสอบส่งข้อความ
            @elseif(($tr['kind'] ?? '') === 'photo') ผลทดสอบส่งภาพ
            @elseif(($tr['kind'] ?? '') === 'replay') ผลทดสอบ replay order
            @else ผลทดสอบ
            @endif
            <span class="ml-1 text-xs font-normal text-slate-500 dark:text-slate-400">
              {{ $tr['ok'] ? '— สำเร็จ' : '— ไม่สำเร็จ' }}
            </span>
          </div>
          @if(!empty($tr['message']))
            <div class="text-sm text-slate-700 dark:text-slate-200">{{ $tr['message'] }}</div>
          @endif
          @if(!empty($tr['error']))
            <div class="text-sm text-rose-700 dark:text-rose-300">⚠️ {{ $tr['error'] }}</div>
          @endif
          @if(!empty($tr['sent_to']))
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">→ ส่งให้: <code>{{ $tr['sent_to'] }}</code></div>
          @endif
          @if(!empty($tr['image_url']))
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">รูป: <a href="{{ $tr['image_url'] }}" target="_blank" class="text-blue-600 hover:underline">{{ $tr['image_url'] }}</a></div>
          @endif
          @if(!empty($tr['status']))
            <details class="mt-2 text-xs">
              <summary class="cursor-pointer text-slate-500 dark:text-slate-400 hover:text-slate-700">Raw response (HTTP {{ $tr['status'] }})</summary>
              <pre class="mt-2 p-2 bg-slate-100 dark:bg-slate-900 rounded text-[11px] overflow-auto">{{ $tr['body'] ?? '—' }}</pre>
            </details>
          @endif
        </div>
      </div>
    </div>
  @endif

  {{-- ────────── 2. SEND TEXT ────────── --}}
  <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden mb-5">
    <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10">
      <h6 class="font-bold text-slate-900 dark:text-white m-0 flex items-center gap-2">
        <i class="bi bi-chat-text text-blue-500"></i> 2. ส่งข้อความทดสอบ (text)
      </h6>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
        ทดสอบครั้งแรก — ใช้รูปแบบที่เล็กที่สุด ถ้าส่งสำเร็จ = token + follow-state ของลูกค้าถูกต้อง
      </p>
    </div>
    <form method="POST" action="{{ route('admin.settings.line-test.send-text') }}" class="p-5 space-y-3">
      @csrf

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">LINE userId (Uxxx...)</label>
          <input type="text" name="line_user_id" value="{{ $selectedLineId ?? old('line_user_id') }}"
                 placeholder="U1234567890abcdef..."
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-100 text-sm font-mono">
          <p class="text-[10px] text-slate-400 mt-1">รับโดย: ลูกค้า login LINE ที่เว็บ → ดูใน DB.auth_social_logins</p>
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">หรือ Platform User ID</label>
          <input type="number" name="user_id" value="{{ $selectedUser?->id ?? old('user_id') }}"
                 placeholder="42"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-100 text-sm">
          <p class="text-[10px] text-slate-400 mt-1">
            @if($selectedUser)
              <i class="bi bi-check-circle text-emerald-500"></i> {{ $selectedUser->email }}
              {!! $selectedLineId ? '<span class="text-emerald-600">— linked LINE</span>' : '<span class="text-rose-600">— ยังไม่ link LINE</span>' !!}
            @else
              ระบบจะ lookup line_user_id ให้อัตโนมัติ
            @endif
          </p>
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">ข้อความ</label>
        <input type="text" name="text" value="{{ old('text', '🧪 Test message from admin console') }}"
               class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-100 text-sm">
      </div>

      <button type="submit" class="px-5 py-2 rounded-lg text-white text-sm font-bold shadow-md"
              style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);">
        <i class="bi bi-send-fill mr-1"></i> ส่งข้อความ
      </button>
    </form>
  </div>

  {{-- ────────── 3. SEND PHOTO ────────── --}}
  <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden mb-5">
    <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10">
      <h6 class="font-bold text-slate-900 dark:text-white m-0 flex items-center gap-2">
        <i class="bi bi-image text-purple-500"></i> 3. ส่งภาพทดสอบ (image)
      </h6>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
        ทดสอบโฟลว์ส่งภาพจริง — ใช้ URL ที่ HTTPS only, JPG/PNG, ≤10 MB, ≤4096×4096
      </p>
    </div>
    <form method="POST" action="{{ route('admin.settings.line-test.send-photo') }}" class="p-5 space-y-3">
      @csrf

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">LINE userId</label>
          <input type="text" name="line_user_id" value="{{ $selectedLineId ?? old('line_user_id') }}"
                 placeholder="U1234567890abcdef..."
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-100 text-sm font-mono">
        </div>
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">หรือ Platform User ID</label>
          <input type="number" name="user_id" value="{{ $selectedUser?->id ?? old('user_id') }}"
                 class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-100 text-sm">
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">URL รูป (HTTPS)</label>
        <input type="url" name="image_url" value="{{ old('image_url') }}"
               placeholder="https://example.com/image.jpg (เว้นว่าง = ใช้รูปทดสอบจาก picsum.photos)"
               class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-100 text-sm">
        <p class="text-[10px] text-slate-400 mt-1">
          เว้นว่าง = ระบบใช้รูปทดสอบจาก <code>picsum.photos</code> (HTTPS, ไม่บล็อก, การันตีว่าผ่าน LINE validate)
        </p>
      </div>

      <div>
        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Caption (ข้อความก่อนรูป)</label>
        <input type="text" name="caption" value="{{ old('caption', '🧪 Test photo from admin console') }}"
               class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-100 text-sm">
      </div>

      <button type="submit" class="px-5 py-2 rounded-lg text-white text-sm font-bold shadow-md"
              style="background:linear-gradient(135deg,#9333ea,#7c3aed);">
        <i class="bi bi-image-fill mr-1"></i> ส่งภาพทดสอบ
      </button>
    </form>
  </div>

  {{-- ────────── 4. REPLAY ORDER ────────── --}}
  <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10">
      <h6 class="font-bold text-slate-900 dark:text-white m-0 flex items-center gap-2">
        <i class="bi bi-arrow-clockwise text-amber-500"></i> 4. Replay LINE delivery สำหรับออเดอร์จริง
      </h6>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
        ใช้กรณีลูกค้าแจ้งว่าไม่ได้รับภาพ — ระบบจะ re-run PhotoDeliveryService::deliverViaLine() (idempotent)
      </p>
    </div>
    <form method="POST" action="{{ route('admin.settings.line-test.replay-order') }}" class="p-5 space-y-3">
      @csrf
      <div>
        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Order ID</label>
        <input type="number" name="order_id" required value="{{ old('order_id') }}"
               class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-100 text-sm">
      </div>

      @if($recentLineOrders->count() > 0)
        <div>
          <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">หรือเลือกจาก 10 ออเดอร์ล่าสุดที่ใช้ LINE delivery</label>
          <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
            @foreach($recentLineOrders as $o)
              <button type="button"
                      onclick="this.form.order_id.value='{{ $o->id }}';"
                      class="px-3 py-2 rounded-lg border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-slate-900 hover:bg-slate-100 text-xs text-left">
                <div class="font-bold text-slate-900 dark:text-white">#{{ $o->order_number }}</div>
                <div class="text-[10px] text-slate-500">{{ $o->delivery_status ?? '-' }}</div>
              </button>
            @endforeach
          </div>
        </div>
      @endif

      <button type="submit" class="px-5 py-2 rounded-lg text-white text-sm font-bold shadow-md"
              style="background:linear-gradient(135deg,#f59e0b,#d97706);">
        <i class="bi bi-arrow-repeat mr-1"></i> Replay delivery
      </button>
    </form>
  </div>

  {{-- ────────── HELP ────────── --}}
  <div class="mt-5 px-4 py-3 rounded-lg bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30 text-xs text-blue-800 dark:text-blue-200">
    <p class="font-bold mb-1">💡 ลำดับการเซตอัปจริง</p>
    <ol class="list-decimal list-inside space-y-0.5 ml-1">
      <li>สร้าง LINE Messaging API channel ใน <a href="https://developers.line.biz/" target="_blank" class="underline">LINE Developer Console</a></li>
      <li>Copy <strong>Channel access token (long-lived)</strong> มาวางที่ <a href="{{ route('admin.settings.line') }}" class="underline">/admin/settings/line</a></li>
      <li>เปิด toggle <code>line_messaging_enabled</code> + <code>line_user_push_enabled</code></li>
      <li>(สำหรับ auto-delivery) เปิด <code>delivery_line_send_photos</code></li>
      <li>ลูกค้าต้อง <strong>add LINE OA เป็นเพื่อน + login ด้วย LINE</strong> ที่เว็บ</li>
      <li>ทดสอบที่หน้านี้: ส่ง text → ส่ง photo → replay order จริง</li>
    </ol>
  </div>
</div>
@endsection
